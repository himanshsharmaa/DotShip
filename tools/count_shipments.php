<?php
require __DIR__ . '/../vendor/autoload.php';

try {
    dotship_bootstrap();
    $count = dotship_collection('shipments')->countDocuments();
    echo "shipments: $count\n";
} catch (Throwable $e) {
    echo "ERR: " . $e->getMessage() . "\n";
    exit(1);
}
