<?PHP

$base = [
    "backupDir" => "/www/htdocs/<ftpuser>/backup/",
    "zipPassword" => "secret",
    "ftpBackupRetentionCount" => false,
    
    "sshUser" => 'youruser',
    "sshPassword" => 'yourpass',
    "sshHost" => 'yourhost',

    // set if you want to upload backups on Amazon AWS S3
    "awsRegion" => "", // e.g. eu-central-1
    "awsKey" => "",
    "awsSecret" => "",
    "awsBucket" => "",
    "awsBackupRetentionCount" => false
];

$toBackup = [
    array_merge($base, [
        "name"   => "wordpress",
        "dbname" => "d1234567",
        "passwd" => "secret",
        "dir"    => "wordpress",
        "exclude" => [
            "*.zip",
            "wp-content/cache"
        ]    
    ]),

    array_merge($base, [
        "name"   => "selfoss",
        "dir"    => "website/selfoss"
    ]),

    array_merge($base, [
        "name"   => "database123",
        "dbname" => "d987324",
        "passwd" => "secret",
    ])
];