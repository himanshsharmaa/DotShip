<?php
require __DIR__ . '/../vendor/autoload.php';
try {
    $client = new MongoDB\Client(getenv('MONGODB_URI'));
    $db = $client->selectDatabase('dot_ship');
    foreach ($db->listCollections() as $c) {
        echo $c->getName() . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
