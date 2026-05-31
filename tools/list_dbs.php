<?php
require __DIR__ . '/../vendor/autoload.php';
try {
    $client = new MongoDB\Client(getenv('MONGODB_URI'));
    foreach ($client->listDatabases() as $db) {
        echo $db->getName() . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
