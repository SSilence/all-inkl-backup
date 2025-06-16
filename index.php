<?PHP

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Aws\S3\S3Client;
use phpseclib3\Net\SSH2;
use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;

function logging($msg) {
    echo $msg . "<br />";
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
$ssh->setTimeout(3600);

logging("start backup: " . count($toBackup) . " files");
logging("----------");
$date = date('Y.m.d');

foreach($toBackup as $backup) {
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
    $command = $command . "zip -8 -r -q -P $zipPassword $zipFile $toBackupPath $sqlFile && echo \"finished\" \n";
    
    // delete temporarily database dump
    if(isNotBlank($sqlFile) && strlen(trim($sqlFile))>4) {
        $command = $command . "rm $sqlFile && echo \"finished\" \n";
    }

    // ssh execute backup
    $ssh->exec($command);

    // upload to s3
    if ($s3) {
        $zipFile = "{$base}{$backupDir}{$date}_{$backup["name"]}.zip";
        $key = basename($zipFile);
        $partSize = 10 * 1024 * 1024;

        $source = fopen($zipFile, 'rb');

        $uploader = new ObjectUploader($s3, $awsBucket, $key, $source);
        do {
            try {
                $result = $uploader->upload();
            } catch (MultipartUploadException $e) {
                rewind($source);
                $uploader = new MultipartUploader($s3Client, $source, [ 'state' => $e->getState() ]);
            }
        } while (!isset($result));

        fclose($source);

        if (isset($result['ObjectURL']) && $awsDeleteZipFileOnFtp) {
            $ssh->exec("rm $zipFile && echo \"finished\"");
        }
    }

    logging($backup["name"]);
}

logging("----------");
logging("finished backup");