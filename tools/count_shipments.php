<?php
require __DIR__ . '/../vendor/autoload.php';
try {
    $client = new MongoDB\Client(getenv('MONGODB_URI'));
    $count = $client->selectDatabase('dot_ship')->shipments->countDocuments();
    echo "shipments: $count\n";
} catch (Throwable $e) {
    echo "ERR: " . $e->getMessage() . "\n";
    exit(1);
}
