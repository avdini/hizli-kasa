<?php
// Bu dosya AWS_Search sınıfını incelemek için oluşturuldu.
require_once('../../../../wp-load.php');

if (class_exists('AWS_Search')) {
    echo "AWS_Search class found.\n";
    $reflection = new ReflectionClass('AWS_Search');
    echo "Methods:\n";
    foreach ($reflection->getMethods() as $method) {
        echo "- " . $method->getName() . "\n";
    }
} else {
    echo "AWS_Search class NOT found.\n";
}
