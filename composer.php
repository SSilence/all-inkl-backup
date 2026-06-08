<?php
/**
 * All-Inkl Backup Phar Builder
 *
 * This script runs composer install, packs the vendor directory into a single vendor.phar,
 * and then deletes the vendor directory.
 */

// 1. Check/adjust phar.readonly configuration
if (ini_get('phar.readonly') === '1') {
    echo "phar.readonly is enabled in php.ini. Attempting to restart with phar.readonly=0...\n";
    $args = $_SERVER['argv'];
    $cmd = 'php -d phar.readonly=0 ' . implode(' ', array_map('escapeshellarg', $args));
    passthru($cmd, $returnVar);
    exit($returnVar);
}

// Helper to calculate directory size
function getDirSize($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

// Helper to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Helper to recursively delete a directory
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

echo "=== Starting PHAR Build ===\n";

// 2. Run Composer Install
echo "Installing dependencies using Composer...\n";
$composerCmd = 'composer install --no-dev --optimize-autoloader --prefer-dist --no-progress';
passthru($composerCmd, $returnVar);

if ($returnVar !== 0) {
    echo "Error: Composer install failed!\n";
    exit(1);
}

$vendorDir = __DIR__ . '/vendor';
if (!is_dir($vendorDir)) {
    echo "Error: vendor directory was not created!\n";
    exit(1);
}

$originalSize = getDirSize($vendorDir);
echo "Original vendor directory size: " . formatBytes($originalSize) . "\n";

// 3. Create vendor.phar
$pharFile = __DIR__ . '/vendor.phar';
if (file_exists($pharFile)) {
    echo "Removing existing vendor.phar...\n";
    unlink($pharFile);
}

echo "Compiling vendor directory into vendor.phar...\n";
try {
    $phar = new Phar($pharFile, 0, 'vendor.phar');
    $phar->startBuffering();
    
    // Add all files in vendor/ directory
    $phar->buildFromDirectory($vendorDir);
    
    // Set stub to run autoload.php (which is now in the root of the PHAR)
    $phar->setStub($phar->createDefaultStub('autoload.php'));
    
    if (Phar::canCompress(Phar::GZ)) {
        echo "Compressing files inside PHAR using Gzip...\n";
        $phar->compressFiles(Phar::GZ);
    } else {
        echo "Gzip compression not supported, skipping...\n";
    }
    
    $phar->stopBuffering();
    
    // Verify file exists
    if (file_exists($pharFile)) {
        $pharSize = filesize($pharFile);
        echo "Successfully created vendor.phar!\n";
        echo "Phar file size: " . formatBytes($pharSize) . "\n";
        echo "Size reduction: " . round((1 - ($pharSize / $originalSize)) * 100, 2) . "%\n";
    } else {
        throw new Exception("PHAR file was not written to disk.");
    }
} catch (Exception $e) {
    echo "Error creating PHAR: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Cleanup vendor directory
echo "Cleaning up vendor directory...\n";
if (deleteDirectory($vendorDir)) {
    echo "Vendor directory cleaned up successfully.\n";
} else {
    echo "Warning: Could not completely delete the vendor directory.\n";
}

echo "=== PHAR Build Completed Successfully ===\n";
