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

// Use the delivery-code processor which enforces expiry and lockouts and marks delivered
$result = dotship_process_delivery_code($trackingId, $code);
if (is_array($result) && ($result['ok'] ?? false) === true) {
    dotship_flash('success', $result['message'] ?? 'Delivery code verified. Shipment updated.');
} else {
    dotship_flash('danger', $result['message'] ?? 'Invalid or expired delivery code');
}

header('Location: ' . dotship_path('track.php') . '?tracking_id=' . urlencode($trackingId));
exit;
