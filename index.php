<?PHP

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
@error_reporting(E_ALL ^ E_WARNING);
@ini_set("max_execution_time", 3600);
@ini_set("memory_limit", "256M");

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Aws\S3\S3Client;
use phpseclib3\Net\SSH2;
use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;

function logging($msg, $newLine = true) {
    echo date("Y-m-d H:i:s") . " " . $msg . ($newLine ? "<br />" : "");
    flush();
}

function isNotBlank($var) {
    return strlen(trim($var))>0;
}

// s3 client
if (isNotBlank($awsKey) && isNotBlank($awsSecret) && isNotBlank($awsRegion) && isNotBlank($awsBucket)) {
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $awsRegion,
        'credentials' => [
            'key'    => $awsKey,
            'secret' => $awsSecret,
        ],
    ]);
} else {
    $s3 = false;
}

// ssh connection
$ssh = new SSH2($sshHost, 22);
if(!$ssh->login($sshUser, $sshPassword)) {
    die($ssh->isConnected() ? 'bad username or password' : 'unable to establish connection');
}
$ssh->setTimeout(false);
$ssh->setKeepAlive(10);

logging("start backup: " . count($toBackup) . " files<br>");
$date = date('Y.m.d');

foreach($toBackup as $backup) {
    logging("{$backup["name"]} start");

    $command = "";

    // backup database
    $sqlFile = "";
    if(isset($backup["dbname"]) && isNotBlank($backup["dbname"])) {
        $sqlFile = "{$base}{$backupDir}{$backup["name"]}_{$date}.sql";
        $command = $command . "mysqldump --user={$backup["dbname"]} --password={$backup["passwd"]} --allow-keywords --add-drop-table --complete-insert --quote-names {$backup["dbname"]} > $sqlFile \n";
    }
    
    // generate zip file
    $toBackupPath = $base . $backup["dir"];
    $zipFile = "{$base}{$backupDir}{$date}_{$backup["name"]}.zip";
    $exclude = '';
    if (!empty($backup['exclude']) && is_array($backup['exclude'])) {
        $exclude = '-x ' . implode(' ', array_map(fn($ex) => "'$ex'", $backup['exclude']));
    }
    $command = $command . "zip -r -P $zipPassword $zipFile $toBackupPath $sqlFile $exclude && echo \"finished\" \n";
    
    // delete temporarily database dump
    if(isNotBlank($sqlFile) && strlen(trim($sqlFile))>4) {
        $command = $command . "rm $sqlFile && echo \"finished\" \n";
    }

    // ssh execute backup
    global $zipCounter, $zipLoggedDots;
    $zipCounter = 0;
    logging("{$backup["name"]} zipping ", false);
    $sshResult = $ssh->exec($command, function($output) {
        global $zipCounter, $zipLoggedDots;
        if (($zipCounter++)%1000 == 0) {
            echo(".");
            flush();
        }
    });
    echo("<br />");
    if ($ssh->getExitStatus() != 0) {
        logging("{$backup["name"]} ssh exist status error $sshResult");
    }
    logging("{$backup["name"]} backup finished");

    sleep(3);

    // upload to s3
    if ($s3) {
        logging("{$backup["name"]} upload to s3 ", false);
        $key = basename($zipFile);
        $partSize = 100 * 1024 * 1024;
        $source = fopen($zipFile, 'rb');
        $uploader = new ObjectUploader($s3, $awsBucket, $key, $source, 'private',
            [
                'part_size' => $partSize,
                'before_upload' => function($command) {
                    echo(".");
                    flush();
                }
            ]
        );
        try {
            $result = $uploader->upload();
            echo("<br />");
        } catch (MultipartUploadException $e) {
            echo("<br />");
            logging("{$backup["name"]} error uploading to s3: " . $e->getMessage());
            fclose($source);
            continue;
        }
        fclose($source);

        if (!isset($result['ObjectURL'])) {
            logging("{$backup["name"]} error uploading to s3");
        } 
        logging("{$backup["name"]} upload finished");
    }

    // cleanup ftp
    if (is_numeric($ftpBackupRetentionCount) && $ftpBackupRetentionCount > 0) {
        logging($backup["name"] . " keep only last $ftpBackupRetentionCount backups on FTP");
        
        // Get all backup files for this specific backup
        $fileList = $ssh->exec("ls -1t {$base}{$backupDir}*{$backup["name"]}.zip");
        $backupFiles = array_filter(explode("\n", trim($fileList)), fn($f) => $f !== '');
        
        // Keep only the required number of backup files
        if (count($backupFiles) > $ftpBackupRetentionCount) {
            $toDelete = array_slice($backupFiles, $ftpBackupRetentionCount);
            foreach ($toDelete as $filePath) {
                $ssh->exec("rm $filePath");
                logging("{$backup["name"]} {basename($filePath)} deleted");
            }
        }
        
        // Delete all non-backup files from backup directory
        logging("{$backup["name"]} cleaning up non-backup files from FTP");
        $allFilesList = $ssh->exec("ls -1 {$base}{$backupDir}");
        $allFiles = array_filter(explode("\n", trim($allFilesList)), fn($f) => $f !== '');
        
        foreach ($allFiles as $fileName) {
            // Skip if it's a backup file (.zip file with date pattern)
            if (preg_match('/^\d{4}\.\d{2}\.\d{2}_.*\.zip$/', $fileName)) {
                continue;
            }
            
            $fullPath = "{$base}{$backupDir}{$fileName}";
            $ssh->exec("rm -rf $fullPath");
            logging("{$backup["name"]} non-backup file/folder {$fileName} deleted");
        }
    }

    // cleanup aws s3
    if ($s3 && is_numeric($awsBackupRetentionCount) && $awsBackupRetentionCount > 0) {
        logging("{$backup["name"]} keep only last $awsBackupRetentionCount backups on AWS");
        try {
            $objects = $s3->listObjectsV2([ 'Bucket' => $awsBucket, 'Prefix' => '' ]);
            $zips = [];
            foreach (($objects['Contents'] ?? []) as $object) {
                $key = $object['Key'];
                if (strpos($key, $backup["name"]) !== false && substr($key, -4) === '.zip') {
                    $zips[] = [
                        'Key' => $key,
                        'LastModified' => strtotime($object['LastModified'])
                    ];
                }
            }
            usort($zips, fn($a, $b) => $b['LastModified'] <=> $a['LastModified']);
            if (count($zips) > $awsBackupRetentionCount) {
                $toDelete = array_slice($zips, $awsBackupRetentionCount);
                foreach ($toDelete as $obj) {
                    $s3->deleteObject([ 'Bucket' => $awsBucket, 'Key' => $obj['Key'] ]);
                    logging("{$backup["name"]} {$obj['Key']} deleted");
                }
            }
        } catch (Exception $e) {
            logging("{$backup["name"]} error deleting old backups on AWS: {$e->getMessage()}");
        }
    }

    logging("{$backup["name"]} finished<br>");
}

// ensure backup directory has .htaccess file for security
$htaccessPath = "{$base}{$backupDir}.htaccess";
$htaccessContent = "# Backup Directory Security - Deny all web access\n";
$htaccessContent .= "Order Deny,Allow\n";
$htaccessContent .= "Deny from all\n";
$htaccessContent .= "# Prevent directory browsing\n";
$htaccessContent .= "Options -Indexes\n";
$htaccessContent .= "# Prevent access to specific file types\n";
$htaccessContent .= "<Files ~ \"\\.(zip|sql|log|txt)$\">\n";
$htaccessContent .= "    Order Allow,Deny\n";
$htaccessContent .= "    Deny from all\n";
$htaccessContent .= "</Files>\n";

$checkHtaccess = $ssh->exec("test -f $htaccessPath && echo 'exists' || echo 'missing'");
if (trim($checkHtaccess) !== 'exists') {
    $ssh->exec("cat > $htaccessPath << 'EOF'\n$htaccessContent\nEOF");
    logging("Created .htaccess file in backup directory for security");
} else {
    logging("Backup directory .htaccess file already exists");
}

logging("finished backup");