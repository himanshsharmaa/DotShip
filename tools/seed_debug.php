<?php
require __DIR__ . '/../vendor/autoload.php';
$uri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017';
$dbName = getenv('MONGODB_DB') ?: 'dot_ship';
$json = json_decode(file_get_contents(__DIR__ . '/../storage/dotship-data.json'), true);
$client = new MongoDB\Client($uri);
$db = $client->selectDatabase($dbName);
foreach ($json['shipments'] as $s) {
    echo "Upserting: " . ($s['tracking_id'] ?? '[no id]') . "\n";
    if (isset($s['_id']) && is_string($s['_id'])) unset($s['_id']);
    $res = $db->shipments->replaceOne(['tracking_id' => $s['tracking_id']], $s, ['upsert' => true]);
    var_export($res->getUpsertedCount());
    echo " upsertedCount\n";
}
foreach ($json['users'] as $u) {
    echo "User: " . ($u['email'] ?? '[no email]') . "\n";
    $db->users->replaceOne(['email' => $u['email']], $u, ['upsert' => true]);
}
echo "Done\n";
