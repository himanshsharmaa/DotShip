<?php
require __DIR__ . '/../vendor/autoload.php';
try {
    $client = new MongoDB\Client(getenv('MONGODB_URI'));
    $doc = $client->selectDatabase('dot_ship')->shipments->findOne([]);
    var_export($doc);
    echo PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
