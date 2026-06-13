<?php
$file = __DIR__ . '/uploads/test.txt';
if (file_put_contents($file, 'Hello')) {
    echo "Write successful!";
} else {
    echo "Write FAILED. Check folder permissions.";
}
?>