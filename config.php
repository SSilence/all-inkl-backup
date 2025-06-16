<?PHP

$sshUser = 'youruser';
$sshPassword = 'yourpass';
$sshHost = 'yourhost';
$base = "/www/htdocs/<ftpuser>/";
$backupDir = "backup/";
$zipPassword = "secret";

// set if you want to upload backups on Amazon AWS S3
$awsRegion = ""; // e.g. eu-central-1
$awsKey = "";
$awsSecret = "";
$awsBucket = "";
$awsDeleteZipFileOnFtp = true; // delete tar files in ftp dir or not

$toBackup = array(
    array(
        "name"   => "wordpress",
        "dbname" => "d1234567",
        "passwd" => "secret",
        "dir"    => "wordpress"
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