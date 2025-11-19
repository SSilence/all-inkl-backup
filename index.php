<?PHP

if (!$_GET["run"]) {
    die("ok");
}

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
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

logging("start backup: " . count($toBackup) . " files<br>");
$date = date('Y.m.d');

foreach($toBackup as $backup) {
    logging("{$backup["name"]} start");

    // s3 client
    if (isNotBlank($backup["awsKey"]) && isNotBlank($backup["awsSecret"]) && isNotBlank($backup["awsRegion"]) && isNotBlank($backup["awsBucket"])) {
        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => $backup["awsRegion"],
            'credentials' => [
                'key'    => $backup["awsKey"],
                'secret' => $backup["awsSecret"],
            ],
        ]);
    } else {
        $s3 = false;
    }

    // ssh connection
    $ssh = new SSH2($backup["sshHost"], 22);
    if(!$ssh->login($backup["sshUser"], $backup["sshPassword"])) {
        die($ssh->isConnected() ? 'bad username or password' : 'unable to establish connection');
    }
    $ssh->setTimeout(false);
    $ssh->setKeepAlive(10);

    // ensure backup directory exists
    $checkDir = $ssh->exec("test -d {$backup["backupDir"]} && echo 'exists' || echo 'missing'");
    if (trim($checkDir) !== 'exists') {
        $ssh->exec("mkdir -p {$backup["backupDir"]}");
        logging("{$backup["name"]} created backup directory {$backup["backupDir"]}");
    }

    $command = "";

    // backup database
    $sqlFile = "";
    if(isset($backup["dbname"]) && isNotBlank($backup["dbname"])) {
        $sqlFile = "{$backup["backupDir"]}{$backup["name"]}_{$date}.sql";
        $command = $command . "mysqldump --user={$backup["dbname"]} --password={$backup["passwd"]} --allow-keywords --add-drop-table --complete-insert --quote-names {$backup["dbname"]} > $sqlFile \n";
    }
    
    // generate zip file
    $zipFile = "{$backup["backupDir"]}{$date}_{$backup["name"]}.zip";
    $exclude = '';
    if (!empty($backup['exclude']) && is_array($backup['exclude'])) {
        $exclude = '-x ' . implode(' ', array_map(fn($ex) => "'$ex'", $backup['exclude']));
    }
    $command = $command . "zip -r -P {$backup["zipPassword"]} $zipFile {$backup["dir"]} $sqlFile $exclude && echo \"finished\" \n";
    
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
        $uploader = new ObjectUploader($s3, $backup["awsBucket"], $key, $source, 'private',
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
    if (is_numeric($backup["ftpBackupRetentionCount"]) && $backup["ftpBackupRetentionCount"] > 0) {
        logging($backup["name"] . " keep only last {$backup["ftpBackupRetentionCount"]} backups on FTP");
        
        // Get all backup files for this specific backup
        $fileList = $ssh->exec("ls -1t {$backup["backupDir"]}*{$backup["name"]}.zip");
        $backupFiles = array_filter(explode("\n", trim($fileList)), fn($f) => $f !== '');
        
        // Keep only the required number of backup files
        if (count($backupFiles) > $backup["ftpBackupRetentionCount"]) {
            $toDelete = array_slice($backupFiles, $backup["ftpBackupRetentionCount"]);
            foreach ($toDelete as $filePath) {
                $ssh->exec("rm $filePath");
                logging("{$backup["name"]} {basename($filePath)} deleted");
            }
        }
        
        // Delete all non-backup files from backup directory
        logging("{$backup["name"]} cleaning up non-backup files from FTP");
        $allFilesList = $ssh->exec("ls -1 {$backup["backupDir"]}");
        $allFiles = array_filter(explode("\n", trim($allFilesList)), fn($f) => $f !== '');
        
        foreach ($allFiles as $fileName) {
            // Skip if it's a backup file (.zip file with date pattern)
            if (preg_match('/^\d{4}\.\d{2}\.\d{2}_.*\.zip$/', $fileName)) {
                continue;
            }
            
            $fullPath = "{$backup["backupDir"]}{$fileName}";
            $ssh->exec("rm -rf $fullPath");
            logging("{$backup["name"]} non-backup file/folder {$fileName} deleted");
        }
    }

    // cleanup aws s3
    if ($s3 && is_numeric($backup["awsBackupRetentionCount"]) && $backup["awsBackupRetentionCount"] > 0) {
        logging("{$backup["name"]} keep only last {$backup["awsBackupRetentionCount"]} backups on AWS");
        try {
            $objects = $s3->listObjectsV2([ 'Bucket' => $backup["awsBucket"], 'Prefix' => '' ]);
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
            if (count($zips) > $backup["awsBackupRetentionCount"]) {
                $toDelete = array_slice($zips, $backup["awsBackupRetentionCount"]);
                foreach ($toDelete as $obj) {
                    $s3->deleteObject([ 'Bucket' => $backup["awsBucket"], 'Key' => $obj['Key'] ]);
                    logging("{$backup["name"]} {$obj['Key']} deleted");
                }
            }
        } catch (Exception $e) {
            logging("{$backup["name"]} error deleting old backups on AWS: {$e->getMessage()}");
        }
    }

    // ensure backup directory has .htaccess file for security
    $htaccessPath = "{$backup["backupDir"]}.htaccess";
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
        logging("{$backup["name"]} created .htaccess file in backup directory for security");
    }

    logging("{$backup["name"]} finished<br>");
}

logging("finished backup");