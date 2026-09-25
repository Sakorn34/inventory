<?php
header('Content-Type: text/plain; charset=UTF-8');

echo "=== Server File Check ===\n\n";

// 1. Current directory
echo "Current directory: " . __DIR__ . "\n";
echo "Current file: " . __FILE__ . "\n\n";

// 2. List all .php files in current directory
echo "--- Files in inventory folder ---\n";
$files = glob(__DIR__ . '/*.php');
foreach ($files as $file) {
    $basename = basename($file);
    $size = filesize($file);
    $md5 = md5_file($file);
    $time = date('Y-m-d H:i:s', filemtime($file));
    
    if ($basename === 'pickup_overview.php') {
        echo ">>> ";
    }
    echo "$basename | Size: $size | MD5: $md5 | Modified: $time\n";
}

// 3. Check if there are backup files
echo "\n--- Backup/Old files ---\n";
$backup_patterns = ['*.bak', '*.old', '*_old.php', '*_backup.php', 'pickup_overview*.php'];
$found_backup = false;
foreach ($backup_patterns as $pattern) {
    $matches = glob(__DIR__ . '/' . $pattern);
    if ($matches) {
        foreach ($matches as $file) {
            echo "Found: " . basename($file) . " | Size: " . filesize($file) . "\n";
            $found_backup = true;
        }
    }
}
if (!$found_backup) {
    echo "No backup files found\n";
}

// 4. Expected MD5 from local file
echo "\n--- Expected MD5 ---\n";
echo "Local file MD5: 92f9e242adbf985c0aa908b74063359b\n";
echo "Server file MD5: " . md5_file(__DIR__ . '/pickup_overview.php') . "\n";

if (md5_file(__DIR__ . '/pickup_overview.php') === '92f9e242adbf985c0aa908b74063359b') {
    echo "✅ MATCH - File uploaded correctly!\n";
} else {
    echo "❌ MISMATCH - Wrong file on server!\n";
}

// 5. Show first 200 characters of pickup_overview.php
echo "\n--- First 200 chars of pickup_overview.php ---\n";
$content = file_get_contents(__DIR__ . '/pickup_overview.php');
echo substr($content, 0, 200) . "\n";

echo "\n=== End Check ===\n";
