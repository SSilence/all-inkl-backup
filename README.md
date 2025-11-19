All-Inkl Backup Script
======================

Copyright (c) 2025 Tobias Zeising, http://www.aditu.de<br>
Licensed under the MIT license<br>
Version 1.1

This is a simple backup script for All-Inkl.com Webspace.

IMPORTANT: You can only use this script for the premium package with SSH support.

Usage
-----

Run the backup with ``https://yoururl.com/backup/?run=true``.

Configure in config.php. Fill in your SSH Username, Password and Host:<br />
<pre>
"sshUser" => 'youruser',
"sshPassword" => 'yourpass',
"sshHost" => 'yourhost',
</pre>

Set your backup directory, e.g. for /www/htdocs/w00123456/backup/ use following option:<br />
<pre>
"backupDir" => "/www/htdocs/w00123456/backup/",
</pre>

Set the passwort for ZIP file encryption:
<pre>
"zipPassword" => "secret",
</pre>
 
Configure your projects for backup. You can specify your database and/or an directory:
<pre>
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
</pre>

Optional you can upload the backup files on Amazon AWS S3. Set the parameters:
<pre>
"awsRegion" => "eu-central-1",
"awsKey" => "AI8C0CA...",
"awsSecret" => "SALKdjlkajsdlaadsasdlkj",
"awsBucket" => "mybackupbucket"
</pre>

Set ``$ftpBackupRetentionCount`` to a number for automatically deleting older backups on ftp.
<pre>
"ftpBackupRetentionCount" => 3, // only preserve the last 3 backups on ftp
</pre>

Set ``$awsBackupRetentionCount`` to a number for automatically deleting older backups on s3.
<pre>
"awsBackupRetentionCount" = 2, // only preserve the last 2 backups on S3
</pre>