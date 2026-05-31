<?php
require __DIR__ . '/../vendor/autoload.php';

$uri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017';
$dbName = getenv('MONGODB_DB') ?: 'dot_ship';

$dataFile = __DIR__ . '/../storage/dotship-data.json';
if (!file_exists($dataFile)) {
    echo "No seed file found: $dataFile\n";
    exit(1);
}

$json = json_decode(file_get_contents($dataFile), true);
if (!is_array($json)) {
    echo "Invalid JSON in seed file\n";
    exit(1);
}

// Support both flat and nested (dot_ship) JSON formats
$data = isset($json['dot_ship']) && is_array($json['dot_ship']) ? $json['dot_ship'] : $json;

try {
    $client = new MongoDB\Client($uri);
    $db = $client->selectDatabase($dbName);
    if (!empty($data['users'])) {
        foreach ($data['users'] as $u) {
            $db->users->replaceOne(['email' => $u['email']], $u, ['upsert' => true]);
        }
    }
    if (!empty($data['shipments'])) {
        foreach ($data['shipments'] as $s) {
            // ensure ObjectId for _id if string
            if (isset($s['_id']) && is_string($s['_id'])) {
                // leave as-is; Mongo will create _id if absent
                unset($s['_id']);
            }
            $db->shipments->replaceOne(['tracking_id' => $s['tracking_id']], $s, ['upsert' => true]);
        }
    }
    echo "Seed complete\n";
} catch (MongoDB\Driver\Exception\ConnectionException $e) {
    echo "MongoDB connection failed. Start MongoDB or update MONGODB_URI.\n";
    echo $e->getMessage() . PHP_EOL;
    exit(1);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
