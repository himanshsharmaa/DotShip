<?php
require_once __DIR__ . '/../includes/init.php';

dotship_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dotship_verify_csrf();

    $advanceId = $_POST['advance_id'] ?? '';
    $sendOtpId = $_POST['send_otp_id'] ?? '';
    $revertId = $_POST['revert_id'] ?? '';
    $advanceAll = isset($_POST['advance_all']);

    if ($advanceId !== '') {
        $shipment = dotship_collection('shipments')->findOne(['_id' => new MongoDB\BSON\ObjectId($advanceId)]);
        if ($shipment) {
            $s = $shipment->getArrayCopy();
            $current = $s['status'] ?? 'booked';
            $order = ['booked', 'packed', 'transit', 'delivered'];
            $idx = array_search($current, $order, true);
            $next = $order[$idx + 1] ?? null;
            if ($next !== null) {
                $now = dotship_now();
                dotship_collection('shipments')->updateOne(['_id' => $s['_id']], ['$set' => ['status' => $next, 'updated_at' => $now], '$push' => ['history' => ['status' => $next, 'label' => ucfirst($next), 'note' => 'Status updated by admin', 'at' => $now]]]);
                dotship_notify($s, 'status_update', 'Admin advanced shipment to ' . $next);
                dotship_flash('success', 'Shipment advanced to ' . ucfirst($next));
            }
        }
    }

    if ($sendOtpId !== '') {
        $shipment = dotship_collection('shipments')->findOne(['_id' => new MongoDB\BSON\ObjectId($sendOtpId)]);
        if ($shipment) {
            $s = $shipment->getArrayCopy();
            $contact = $s['receiver_email'] ?? $s['receiver_phone'] ?? '';
            $tracking = $s['tracking_id'] ?? '';
            if ($contact !== '') {
                $otp = dotship_create_otp($tracking, $contact, filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms');
                dotship_send_otp($contact, $otp['code'], filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms', $tracking);
                dotship_flash('success', 'OTP sent to ' . $contact);
            } else {
                dotship_flash('warning', 'No contact found for shipment');
            }
        }
    }

    if ($revertId !== '') {
        $shipment = dotship_collection('shipments')->findOne(['_id' => new MongoDB\BSON\ObjectId($revertId)]);
        if ($shipment) {
            $s = $shipment->getArrayCopy();
            $history = $s['history'] ?? [];
            if (is_array($history) && count($history) >= 2) {
                array_pop($history);
                $last = end($history);
                $newStatus = $last['status'] ?? ($s['status'] ?? 'booked');
                $now = dotship_now();
                dotship_collection('shipments')->updateOne(['_id' => $s['_id']], ['$set' => ['status' => $newStatus, 'updated_at' => $now, 'history' => $history]]);
                dotship_notify($s, 'status_revert', 'Admin reverted shipment to ' . $newStatus);
                dotship_flash('success', 'Shipment reverted to ' . ucfirst($newStatus));
            } else {
                dotship_flash('warning', 'No previous history to revert to');
            }
        }
    }

    if ($advanceAll) {
        $cursor = dotship_collection('shipments')->find();
        $count = 0;
        foreach ($cursor as $doc) {
            $s = $doc->getArrayCopy();
            $current = $s['status'] ?? 'booked';
            $order = ['booked', 'packed', 'transit', 'delivered'];
            $idx = array_search($current, $order, true);
            $next = $order[$idx + 1] ?? null;
            if ($next !== null) {
                $now = dotship_now();
                dotship_collection('shipments')->updateOne(['_id' => $s['_id']], ['$set' => ['status' => $next, 'updated_at' => $now], '$push' => ['history' => ['status' => $next, 'label' => ucfirst($next), 'note' => 'Admin bulk advance', 'at' => $now]]]);
                dotship_notify($s, 'status_update', 'Admin bulk advanced shipment to ' . $next);
                $count++;
            }
        }

        dotship_flash('success', "Advanced $count shipments");
    }

    header('Location: ' . dotship_path('admin/auto_progress.php'));
    exit;
}

$shipments = dotship_collection('shipments')->find([], ['sort' => ['created_at' => -1]]);

dotship_render_head('Auto Progress', '');
dotship_render_app_shell_start('Auto Progress', 'shipments', 'admin');

echo '<div class="container-fluid">';
echo '<div class="d-flex justify-content-between mb-3"><h5>Admin: Auto Progress</h5><form method="post"><input type="hidden" name="csrf_token" value="' . dotship_csrf_token() . '"><button type="submit" name="advance_all" class="btn btn-sm btn-warning">Advance All</button></form></div>';

echo '<div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Tracking</th><th>Receiver</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead><tbody>';

foreach ($shipments as $sObj) {
    $s = $sObj->getArrayCopy();
    $id = (string) ($s['_id'] ?? '');
    $tracking = $s['tracking_id'] ?? $id;
    $status = $s['status'] ?? 'booked';
    $updated = $s['updated_at'] ?? $s['created_at'] ?? null;

    echo '<tr>';
    echo '<td>' . dotship_escape($tracking) . '</td>';
    echo '<td>' . dotship_escape((string) ($s['receiver_name'] ?? '')) . '</td>';
    echo '<td><span class="badge ' . dotship_status_badge($status) . '">' . dotship_escape(dotship_status_label($status)) . '</span></td>';
    echo '<td>' . dotship_escape(dotship_format_date($updated)) . '</td>';
    echo '<td>';
    echo '<form method="post" style="display:inline-block;margin-right:6px">' . dotship_csrf_field() . '<input type="hidden" name="advance_id" value="' . dotship_escape($id) . '"><button class="btn btn-sm btn-primary">Advance</button></form>';
    echo '<form method="post" style="display:inline-block;margin-right:6px">' . dotship_csrf_field() . '<input type="hidden" name="send_otp_id" value="' . dotship_escape($id) . '"><button class="btn btn-sm btn-outline-success">Send OTP</button></form>';
    echo '<form method="post" style="display:inline-block">' . dotship_csrf_field() . '<input type="hidden" name="revert_id" value="' . dotship_escape($id) . '"><button class="btn btn-sm btn-outline-danger">Revert</button></form>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody></table></div></div></div>';

dotship_render_app_shell_end();
dotship_render_footer();
