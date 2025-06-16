All-Inkl Backup Script
======================

Copyright (c) 2025 Tobias Zeising, http://www.aditu.de
Licensed under the MIT license
Version 0.2

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
$base = "/www/htdocs/<your-all-inkl-ftp-username>/";
</pre>

Set your backup script Subdirectory, e.g. for /www/htdocs/ftpuser/backup/ use following option:<br />
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
</pre>

Optional you can upload the backup files on Amazon AWS S3. Set $awsDeleteZipFileOnFtp = true if you want the backup only on S2 and not on your ftp. Set false if you want it both: on your S3 and ftp.