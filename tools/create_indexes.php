<?php
require __DIR__ . '/../vendor/autoload.php';

$uri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017';
$dbName = getenv('MONGODB_DB') ?: 'dot_ship';
try {
    $client = new MongoDB\Client($uri);
    $db = $client->selectDatabase($dbName);
    $result = $db->shipments->createIndex(['tracking_id' => 1], ['unique' => true]);
    echo "Created index: " . $result . PHP_EOL;
} catch (MongoDB\Driver\Exception\ConnectionException $e) {
    echo "MongoDB connection failed. Start MongoDB or update MONGODB_URI.\n";
    echo $e->getMessage() . PHP_EOL;
    exit(1);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
