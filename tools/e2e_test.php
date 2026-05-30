<?php
// End-to-end smoke test for DOT SHIP delivery verification flow
// Run with: php tools/e2e_test.php

require_once __DIR__ . '/../includes/init.php';

function ok(string $msg): void { echo "[OK] $msg\n"; }
function fail(string $msg): void { echo "[FAIL] $msg\n"; exit(2); }

echo "DOT SHIP E2E Smoke Test\n";

try {
    $shipColl = dotship_collection('shipments');
    $otpColl = dotship_collection('otps');
} catch (Throwable $e) {
    fail('Cannot access MongoDB/compat store: ' . $e->getMessage());
}

// Step 1: create a test shipment
$tracking = dotship_generate_tracking_id();
$now = dotship_now();
$shipment = [
    'tracking_id' => $tracking,
    'sender_name' => 'E2E Sender',
    'sender_email' => 'sender@example.com',
    'receiver_name' => 'E2E Receiver',
    'receiver_email' => 'receiver@example.com',
    'parcel_description' => 'E2E Test Parcel',
    'parcel_weight' => '0.5',
    'parcel_type' => 'Test',
    'status' => 'booked',
    'created_at' => $now,
    'updated_at' => $now,
    'history' => [
        ['status' => 'booked', 'label' => 'Booked', 'note' => 'E2E created', 'at' => $now]
    ],
];

$res = $shipColl->insertOne($shipment);
$insertedId = $res->getInsertedId();
if (!$insertedId) {
    fail('Failed to create test shipment');
}
ok("Created shipment $tracking");

// Step 2: move to 'transit'
try {
    $shipColl->updateOne(['_id' => $insertedId], ['$set' => ['status' => 'transit', 'updated_at' => dotship_now()], '$push' => ['history' => ['status' => 'transit', 'label' => dotship_status_label('transit'), 'note' => 'E2E moved to transit', 'at' => dotship_now()]]]);
    ok('Moved to transit');
} catch (Throwable $e) {
    fail('Failed to move to transit: ' . $e->getMessage());
}

// Step 3: move to 'out_for_delivery' and generate OTP (simulate auto_progress)
try {
    $shipColl->updateOne(['_id' => $insertedId], ['$set' => ['status' => 'out_for_delivery', 'updated_at' => dotship_now()], '$push' => ['history' => ['status' => 'out_for_delivery', 'label' => dotship_status_label('out_for_delivery'), 'note' => 'E2E moved to out_for_delivery', 'at' => dotship_now()]]]);
    ok('Moved to out_for_delivery');
} catch (Throwable $e) {
    fail('Failed to move to out_for_delivery: ' . $e->getMessage());
}

$otp = dotship_create_otp($tracking, (string) $shipment['receiver_email'], 'email', 1800);
$code = $otp['code'] ?? null;
if (!$code) {
    fail('OTP generation failed');
}
// send OTP (logs or Formspree)
dotship_send_otp((string) $shipment['receiver_email'], $code, 'email', $tracking);
ok('Generated and sent delivery code (OTP)');

// Step 4: attempt 3 wrong codes -> expect lock
for ($i = 1; $i <= 3; $i++) {
    $result = dotship_process_delivery_code($tracking, '0000');
    if ($result['ok'] ?? false) {
        fail('Unexpected success with wrong code on attempt ' . $i);
    }
    echo "Attempt $i: " . ($result['message'] ?? 'no message') . "\n";
}
// verify locked
$doc = $shipColl->findOne(['_id' => $insertedId])->getArrayCopy();
if (empty($doc['verification_locked']) && ($doc['status'] ?? '') !== 'verification_failed') {
    fail('Shipment not locked after 3 failed attempts');
}
ok('Shipment locked after 3 failed attempts');

// Step 5: admin reissue (reset counters and generate new OTP)
try {
    $shipColl->updateOne(['_id' => $insertedId], ['$set' => ['failed_attempts' => 0, 'verification_locked' => false, 'status' => 'out_for_delivery', 'updated_at' => dotship_now()]]);
    $regen = dotship_create_otp($tracking, (string) $shipment['receiver_email'], 'email', 1800);
    $regenCode = $regen['code'] ?? null;
    dotship_send_otp((string) $shipment['receiver_email'], $regenCode, 'email', $tracking);
    ok('Admin reissued delivery code');
} catch (Throwable $e) {
    fail('Failed to reissue OTP: ' . $e->getMessage());
}

// Step 6: verify with correct code
$result = dotship_process_delivery_code($tracking, $regenCode);
if (!($result['ok'] ?? false)) {
    fail('Delivery verification with correct code failed: ' . ($result['message'] ?? 'no message'));
}
ok('Delivery verified with correct code');

// Step 7: confirm DB status delivered
$final = $shipColl->findOne(['_id' => $insertedId])->getArrayCopy();
if (($final['status'] ?? '') !== 'delivered' || empty($final['delivery_verified'])) {
    fail('Final shipment state incorrect after verification');
}
ok('Shipment status is delivered and verified');

// Cleanup: remove test shipment and otps
try {
    $shipColl->deleteOne(['_id' => $insertedId]);
    $otpColl->deleteOne(['tracking_id' => $tracking]);
    ok('Cleaned up test data');
} catch (Throwable $e) {
    echo "Warning: cleanup failed: " . $e->getMessage() . "\n";
}

echo "E2E Smoke Test completed successfully.\n";
exit(0);
