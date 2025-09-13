All-Inkl Backup Script
======================

Copyright (c) 2025 Tobias Zeising, http://www.aditu.de
Licensed under the MIT license
Version 1.0

This is a simple backup script for All-Inkl.com Webspace.

IMPORTANT: You can only use this script for the premium package with SSH support.

Usage
-----

Configure in config.php. Fill in your SSH Username, Password and Host:<br />
<pre>
$sshUser = 'youruser';
$sshPassword = 'yourpass';
$sshHost = 'yourhost';
</pre>

Set your base directory:<br />
<pre>
$base = "/www/htdocs/w00123456/";
</pre>

Set your backup script Subdirectory, e.g. for /www/htdocs/w00123456/backup/ use following option:<br />
<pre>
$backupDir = "backup/";
</pre>

Set the passwort for ZIP file encryption:
<pre>
$zipPassword = "secret";
</pre>
 
Configure your projects for backup. You can specify your database and/or an directory:
<pre>
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
</pre>

Optional you can upload the backup files on Amazon AWS S3. Set the parameters:
<pre>
$awsRegion = "eu-central-1";
$awsKey = "AI8C0CA...";
$awsSecret = "SALKdjlkajsdlaadsasdlkj";
$awsBucket = "mybackupbucket";
</pre>

Set ``$ftpBackupRetentionCount`` to a number for automatically deleting older backups on ftp.
<pre>
$ftpBackupRetentionCount = 3; // only preserve the last 3 backups on ftp
</pre>

Set ``$awsBackupRetentionCount`` to a number for automatically deleting older backups on s3.
<pre>
$awsBackupRetentionCount = 2; // only preserve the last 2 backups on S3
</pre>