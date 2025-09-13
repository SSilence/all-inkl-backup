<?PHP

$sshUser = 'youruser';
$sshPassword = 'yourpass';
$sshHost = 'yourhost';
$base = "/www/htdocs/<ftpuser>/";
$backupDir = "backup/";
$zipPassword = "secret";
$ftpBackupRetentionCount = false;

// set if you want to upload backups on Amazon AWS S3
$awsRegion = ""; // e.g. eu-central-1
$awsKey = "";
$awsSecret = "";
$awsBucket = "";
$awsBackupRetentionCount = false;

$toBackup = array(
    array(
        "name"   => "wordpress",
        "dbname" => "d1234567",
        "passwd" => "secret",
        "dir"    => "wordpress",
        "exclude" => array(
            "*.zip",
            "wp-content/cache"
        )
    ),
    array(
        "name"   => "website",
        "dbname" => "d321",
        "passwd" => "secret",
        "dir"    => "website/www"
    ),
    array(
        "name"   => "selfoss",
        "dir"    => "website/selfoss"
    ),
    array(
        "name"   => "database123",
        "dbname" => "d987324",
        "passwd" => "secret",
    )	
);