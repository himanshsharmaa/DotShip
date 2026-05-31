<?php
require __DIR__ . '/../vendor/autoload.php';

try {
    dotship_bootstrap();
    $doc = dotship_collection('shipments')->findOne([]);
    var_export($doc);
    echo PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
