<?php
require __DIR__ . '/../vendor/autoload.php';

use DotShipSqlStore\UTCDateTime;

$shipments = dotship_collection('shipments');
$notifications = dotship_collection('notifications');

// Configuration: time (seconds) to wait before advancing to next status
$waitBookedToPacked = (int) (getenv('DS_WAIT_BOOKED_TO_PACKED') ?: 60); // default 60s for demo
$waitPackedToTransit = (int) (getenv('DS_WAIT_PACKED_TO_TRANSIT') ?: 60);
$waitTransitToDelivered = (int) (getenv('DS_WAIT_TRANSIT_TO_DELIVERED') ?: 120);

function next_status(string $current): ?string
{
    // Use a conservative flow and do not auto-advance to 'delivered'
    $order = ['booked', 'packed', 'transit', 'out_for_delivery', 'delivered'];
    $idx = array_search($current, $order, true);
    if ($idx === false) return null;
    // Prevent automatic progression from out_for_delivery -> delivered
    $candidate = $order[$idx + 1] ?? null;
    if ($candidate === 'delivered') {
        return null;
    }
    return $candidate;
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

            // When we auto-advance TO out_for_delivery, generate and send delivery code
            if ($next === 'out_for_delivery' && !empty($doc['receiver_email']) && function_exists('dotship_create_otp') && function_exists('dotship_send_otp')) {
                $tracking = (string) ($doc['tracking_id'] ?? '');
                $deliveryCode = dotship_create_otp($tracking, (string) $doc['receiver_email'], 'email', 1800);
                dotship_send_otp((string) $doc['receiver_email'], (string) $deliveryCode['code'], 'email', $tracking);
                try {
                    $shipments->updateOne(['_id' => $doc['_id']], ['$set' => ['code_generated_at' => new UTCDateTime(), 'expiry_time' => new UTCDateTime((int) round((microtime(true) + 1800) * 1000)), 'failed_attempts' => 0, 'verification_locked' => false]]);
                } catch (Throwable) {
                }
            }

            // insert a notification record
            try {
                $notifications->insertOne([
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
    echo "Operation failed.\n";
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
