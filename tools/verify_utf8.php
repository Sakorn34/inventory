<?php
$c = file_get_contents(dirname(__DIR__) . '/stock_overview.php');
preg_match("/\\\$statusNotYet = '([^']+)'/", $c, $m);
echo "statusNotYet={$m[1]}\n";
echo "ok=" . ($m[1] === json_decode('"\u0e22\u0e31\u0e07\u0e44\u0e21\u0e48\u0e16\u0e36\u0e07\u0e08\u0e38\u0e14\u0e2a\u0e31\u0e48\u0e07"') ? 'yes' : 'no') . "\n";
