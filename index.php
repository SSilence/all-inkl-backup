<?PHP

require __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SSH2;

$sshUser = 'youruser';
$sshPassword = 'yourpass';
$sshHost = 'yourhost';
$base = "/www/htdocs/<ftpuser>/";
$backupDir = "backup/";

$dbs = array(
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


// ssh connection
$ssh = new SSH2($sshHost, 22);
if(!$ssh->login($sshUser, $sshPassword)) {
	die($ssh->isConnected() ? 'bad username or password' : 'unable to establish connection');
}

$baseWithoutTrailingSlash = substr($base, 1);
foreach($dbs as $db) {
    $str = "";
    echo $db["name"] . "<br />";

    // directory for backup
    $dir = "";
    if(isset($db["dir"]) && strlen(trim($db["dir"]))>0) {
        $dir = $baseWithoutTrailingSlash . $db["dir"];
    }

    // backup database
    $sql = "";
    if(isset($db["dbname"]) && strlen(trim($db["dbname"]))>0) {
        $db_name = $db["dbname"];
        $db_passwd = $db["passwd"];
        $sql_file = $db["name"] . "_" . date('Y.m.d') . ".sql";
        $sql = $baseWithoutTrailingSlash . $backupDir . $sql_file;
        $str = $str . "mysqldump --user=" . $db_name . " --password=" . $db_passwd . " --allow-keywords --add-drop-table --complete-insert --quote-names " . $db_name . " > " . $base . $backupDir . $sql_file . "\n";
    }
	
    // generate tar.gz file
    $tar_file = $base . $backupDir . date('Y.m.d') . "_" . $db["name"] . ".tar.gz";
    $str = $str . "tar cfz " . $tar_file . " -C / " . $dir . " " . $sql . "\n";
    
    // delete temporarily database dump
    if(strlen($sql)>0 && strlen(trim($base))>4 && strlen(trim($sql_file))>4)
        $str = $str . "rm " . $base . $backupDir . $sql_file . "\n";

    $ssh->exec($str);
}

echo "finished";