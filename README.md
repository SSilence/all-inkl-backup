All-Inkl Backup Script
======================

Copyright (c) 2013 Tobias Zeising, http://www.aditu.de
Licensed under the MIT license
Version 0.1

This is a simple Backup Script for All-Inkl.com Webspace.

IMPORTANT: You can only use this script for the premium package with SSH support.


Usage
-----

Fill in your SSH Username, Password and Host:<br />
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
 
Configure your projects for backup. You can specify your database and/or an directory:
<pre>
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
</pre>