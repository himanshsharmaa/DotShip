<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\UTCDateTime;

$uri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017';
$dbName = getenv('MONGODB_DB') ?: 'dot_ship';

try {
    $client = new MongoDB\Client($uri);
    $db = $client->selectDatabase($dbName);
    $shipments = $db->shipments;
} catch (MongoDB\Driver\Exception\ConnectionException $e) {
    echo "MongoDB connection failed. Start MongoDB or update MONGODB_URI.\n";
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

// Configuration: time (seconds) to wait before advancing to next status
$waitBookedToPacked = (int) (getenv('DS_WAIT_BOOKED_TO_PACKED') ?: 60); // default 60s for demo
$waitPackedToTransit = (int) (getenv('DS_WAIT_PACKED_TO_TRANSIT') ?: 60);
$waitTransitToDelivered = (int) (getenv('DS_WAIT_TRANSIT_TO_DELIVERED') ?: 120);

function next_status(string $current): ?string
{
    $order = ['booked', 'packed', 'transit', 'delivered'];
    $idx = array_search($current, $order, true);
    if ($idx === false) return null;
    return $order[$idx + 1] ?? null;
}

function ms_ago_to_utcdatetime(int $seconds): UTCDateTime
{
    return new UTCDateTime((int) round((microtime(true) - $seconds) * 1000));
}

// Find shipments that are eligible to advance
// We treat updated_at as the last status change time

try {
    $nowMs = (int) round(microtime(true) * 1000);
    $candidates = $shipments->find([], ['limit' => 1000]);
    $updated = 0;

    foreach ($candidates as $docObj) {
        $doc = $docObj->getArrayCopy();
        $status = $doc['status'] ?? 'booked';
        $updatedAtObj = $doc['updated_at'] ?? null;

        $updatedAtMs = null;
        if ($updatedAtObj instanceof UTCDateTime) {
            $updatedAtMs = $updatedAtObj->toDateTime()->format('U') * 1000 + (int) (($updatedAtObj->toDateTime()->format('u')) / 1000);
        }

        // fallback: use created_at
        if ($updatedAtMs === null && isset($doc['created_at']) && $doc['created_at'] instanceof UTCDateTime) {
            $updatedAtMs = $doc['created_at']->toDateTime()->format('U') * 1000 + (int) (($doc['created_at']->toDateTime()->format('u')) / 1000);
        }

        if ($updatedAtMs === null) {
            continue;
        }

        $elapsedSec = max(0, (int) floor(($nowMs - $updatedAtMs) / 1000));

        $advance = false;
        switch ($status) {
            case 'booked':
                if ($elapsedSec >= $GLOBALS['waitBookedToPacked']) $advance = true;
                break;
            case 'packed':
                if ($elapsedSec >= $GLOBALS['waitPackedToTransit']) $advance = true;
                break;
            case 'transit':
                if ($elapsedSec >= $GLOBALS['waitTransitToDelivered']) $advance = true;
                break;
            default:
                $advance = false;
        }

        if (!$advance) continue;

        $next = next_status($status);
        if ($next === null) continue;

        $now = new UTCDateTime();

        $update = [
            '$set' => ['status' => $next, 'updated_at' => $now],
            '$push' => ['history' => ['status' => $next, 'label' => ucfirst($next), 'note' => 'Automated status progression', 'at' => $now]],
        ];

        $filter = ['_id' => $doc['_id']];
        $result = $shipments->updateOne($filter, $update);

        if ($result->getModifiedCount() > 0) {
            $updated++;

            if ($next === 'transit' && !empty($doc['receiver_email']) && function_exists('dotship_create_otp') && function_exists('dotship_send_otp')) {
                $deliveryCode = dotship_create_otp((string) ($doc['tracking_id'] ?? ''), (string) $doc['receiver_email'], 'email');
                dotship_send_otp((string) $doc['receiver_email'], (string) $deliveryCode['code'], 'email', (string) ($doc['tracking_id'] ?? ''));
            }

            // insert a notification record
            try {
                $db->notifications->insertOne([
                    'shipment_id' => $doc['_id'],
                    'tracking_id' => $doc['tracking_id'] ?? null,
                    'type' => 'status_update',
                    'from' => $status,
                    'to' => $next,
                    'message' => 'Automated status progression',
                    'created_at' => new UTCDateTime(),
                ]);
            } catch (Throwable) {
                // ignore notification failures
            }

            echo "Advanced " . ($doc['tracking_id'] ?? (string) $doc['_id']) . " to $next\n";
        }
    }

    echo "Done. Updated: $updated shipments\n";
} catch (Throwable $e) {
    echo "MongoDB operation failed. Make sure MongoDB is running and the URI is correct.\n";
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
