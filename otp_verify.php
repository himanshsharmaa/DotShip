<?php
require_once __DIR__ . '/includes/init.php';

$trackingId = strtoupper(trim((string) ($_POST['tracking_id'] ?? '')));
$code = trim((string) ($_POST['otp_code'] ?? ''));

if ($trackingId === '' || $code === '') {
    dotship_flash('warning', 'Tracking ID and delivery code required');
    header('Location: ' . dotship_path('track.php') . '?tracking_id=' . urlencode($trackingId));
    exit;
}

$shipmentObj = dotship_collection('shipments')->findOne(['tracking_id' => $trackingId]);
if (!$shipmentObj) {
    dotship_flash('danger', 'Shipment not found');
    header('Location: ' . dotship_path('track.php') . '?tracking_id=' . urlencode($trackingId));
    exit;
}

$shipment = $shipmentObj->getArrayCopy();

if (dotship_verify_otp($trackingId, $code)) {
    // advance to next status
    $current = $shipment['status'] ?? 'booked';
    $order = ['booked', 'packed', 'transit', 'delivered'];
    $idx = array_search($current, $order, true);
    $next = $order[$idx + 1] ?? null;
    if ($next !== null) {
        $now = dotship_now();
        dotship_collection('shipments')->updateOne(['_id' => $shipment['_id']], ['$set' => ['status' => $next, 'updated_at' => $now], '$push' => ['history' => ['status' => $next, 'label' => ucfirst($next), 'note' => 'Verified via OTP', 'at' => $now]]]);
        dotship_notify($shipment, 'status_update', 'Shipment advanced via delivery code to ' . $next);
        dotship_flash('success', 'Delivery code verified. Shipment advanced to ' . ucfirst($next));
    } else {
        dotship_flash('info', 'Shipment already at final status');
    }
} else {
    dotship_flash('danger', 'Invalid or expired delivery code');
}

header('Location: ' . dotship_path('track.php') . '?tracking_id=' . urlencode($trackingId));
exit;
