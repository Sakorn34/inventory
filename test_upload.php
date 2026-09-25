<?php
header('Content-Type: text/plain');
echo "Upload test successful!\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "MD5 of pickup_overview.php: " . md5_file(__DIR__ . '/pickup_overview.php') . "\n";
