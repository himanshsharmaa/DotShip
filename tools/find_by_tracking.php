<?php
require __DIR__ . '/../vendor/autoload.php';

$tracking = $argv[1] ?? 'DSA1B2C101';
try {
    dotship_bootstrap();
    $doc = dotship_collection('shipments')->findOne(['tracking_id' => $tracking]);
    if ($doc) {
        echo "Found: \n";
        print_r($doc);
    } else {
        echo "Not found\n";
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
