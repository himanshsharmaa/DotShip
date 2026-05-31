<?php
require __DIR__ . '/../vendor/autoload.php';
$uri = getenv('MONGODB_URI');
try {
    $client = new MongoDB\Client($uri);
    $dbs = iterator_to_array($client->listDatabases());
    echo "OK: " . count($dbs) . " dbs\n";
} catch (Throwable $e) {
    echo "ERR: " . $e->getMessage() . "\n";
    exit(1);
}
