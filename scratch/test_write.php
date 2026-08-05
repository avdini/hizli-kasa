<?php
$file = dirname(__FILE__) . '/permission-test.txt';
$result = file_put_contents($file, "Test at " . date('Y-m-d H:i:s'));
if ($result === false) {
    echo "FAILED to write file.";
} else {
    echo "SUCCESS: Wrote $result bytes to $file";
}
