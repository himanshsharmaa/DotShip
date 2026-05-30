<?php
require_once __DIR__ . '/includes/init.php';

dotship_require_login();

if (dotship_is_admin()) {
    header('Location: ' . dotship_path('admin/index.php'));
    exit;
}

$user = dotship_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dotship_verify_csrf();

    $senderName = dotship_post('sender_name');
    $senderPhone = dotship_post('sender_phone');
    $senderAddress = dotship_post('sender_address');
    $receiverName = dotship_post('receiver_name');
    $receiverPhone = dotship_post('receiver_phone');
    $receiverEmail = dotship_post('receiver_email');
    $receiverAddress = dotship_post('receiver_address');
    $parcelDescription = dotship_post('parcel_description');
    $parcelWeight = dotship_post('parcel_weight');
    $parcelType = dotship_post('parcel_type');

    if ($senderName === '' || $senderPhone === '' || $senderAddress === '' || $receiverName === '' || $receiverPhone === '' || $receiverAddress === '' || $parcelDescription === '' || $parcelWeight === '' || $parcelType === '') {
        dotship_flash('error', 'Please fill in all shipment details.');
        header('Location: ' . dotship_path('book.php'));
        exit;
    }

    $trackingId = dotship_generate_tracking_id();
    dotship_collection('shipments')->insertOne([
        'tracking_id' => $trackingId,
        'user_id' => $user['_id'],
        'sender_name' => $senderName,
        'sender_phone' => $senderPhone,
        'sender_address' => $senderAddress,
        'receiver_name' => $receiverName,
        'receiver_phone' => $receiverPhone,
        'receiver_email' => $receiverEmail !== '' ? $receiverEmail : null,
        'receiver_address' => $receiverAddress,
        'parcel_description' => $parcelDescription,
        'parcel_weight' => $parcelWeight,
        'parcel_type' => $parcelType,
        'status' => 'booked',
        'history' => [
            ['status' => 'booked', 'label' => 'Booked', 'note' => 'Shipment created on DOT SHIP', 'at' => dotship_now()],
        ],
        'created_at' => dotship_now(),
        'updated_at' => dotship_now(),
    ]);

    $_SESSION['booking_success'] = ['tracking_id' => $trackingId];
    dotship_flash('success', 'Shipment booked successfully.');
    header('Location: ' . dotship_path('book.php'));
    exit;
}

$booking = $_SESSION['booking_success'] ?? null;
unset($_SESSION['booking_success']);

dotship_render_head('Book Shipment', 'app-page');
dotship_render_app_shell_start('Book Shipment', 'book', 'user');
dotship_render_flash();
if ($booking):
?>
<div class="alert alert-success shadow-soft rounded-4xl border-0 mb-4">
  <div class="d-flex align-items-start gap-3">
    <div class="stat-icon bg-white"><i class="bi bi-check2-circle text-success"></i></div>
    <div>
      <h5 class="mb-1 fw-bold">Shipment booked successfully</h5>
      <div class="small-muted mb-2">Your tracking ID is ready for real-time parcel monitoring.</div>
      <div class="fw-semibold">Tracking ID: <?php echo dotship_escape($booking['tracking_id']); ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<form method="post" class="row g-4">
  <?php echo dotship_csrf_field(); ?>
  <div class="col-12">
    <div class="dashboard-panel p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h5 class="panel-title fw-bold mb-1">Sender Information</h5>
          <div class="small-muted">The sender details for pickup and internal coordination.</div>
        </div>
        <span class="nav-pill"><i class="bi bi-person-badge"></i> Step 1</span>
      </div>
      <div class="row g-3">
        <div class="col-md-4 form-floating"><input type="text" class="form-control" name="sender_name" id="sender_name" placeholder="Sender name" value="<?php echo dotship_escape((string) ($user['name'] ?? '')); ?>" required><label for="sender_name">Sender name</label></div>
        <div class="col-md-4 form-floating"><input type="tel" class="form-control" name="sender_phone" id="sender_phone" placeholder="Sender phone" value="<?php echo dotship_escape((string) ($user['phone'] ?? '')); ?>" required><label for="sender_phone">Sender phone</label></div>
        <div class="col-md-4 form-floating"><input type="text" class="form-control" name="sender_address" id="sender_address" placeholder="Sender address" required><label for="sender_address">Sender address</label></div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="dashboard-panel p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h5 class="panel-title fw-bold mb-1">Receiver Information</h5>
          <div class="small-muted">Where the parcel will be delivered.</div>
        </div>
        <span class="nav-pill"><i class="bi bi-geo-alt"></i> Step 2</span>
      </div>
      <div class="row g-3">
        <div class="col-md-4 form-floating"><input type="text" class="form-control" name="receiver_name" id="receiver_name" placeholder="Receiver name" required><label for="receiver_name">Receiver name</label></div>
        <div class="col-md-4 form-floating"><input type="tel" class="form-control" name="receiver_phone" id="receiver_phone" placeholder="Receiver phone" required><label for="receiver_phone">Receiver phone</label></div>
        <div class="col-md-4 form-floating"><input type="email" class="form-control" name="receiver_email" id="receiver_email" placeholder="Receiver email"><label for="receiver_email">Receiver email</label></div>
        <div class="col-md-4 form-floating"><input type="text" class="form-control" name="receiver_address" id="receiver_address" placeholder="Receiver address" required><label for="receiver_address">Receiver address</label></div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="dashboard-panel p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h5 class="panel-title fw-bold mb-1">Parcel Information</h5>
          <div class="small-muted">Package metadata for the courier workflow.</div>
        </div>
        <span class="nav-pill"><i class="bi bi-box-seam"></i> Step 3</span>
      </div>
      <div class="row g-3">
        <div class="col-md-6 form-floating"><input type="text" class="form-control" name="parcel_description" id="parcel_description" placeholder="Parcel description" required><label for="parcel_description">Parcel description</label></div>
        <div class="col-md-3 form-floating"><input type="text" class="form-control" name="parcel_weight" id="parcel_weight" placeholder="Parcel weight" required><label for="parcel_weight">Weight (kg)</label></div>
        <div class="col-md-3 form-floating">
          <select class="form-select" name="parcel_type" id="parcel_type" required>
            <option value="" selected disabled>Select type</option>
            <option>Documents</option>
            <option>Parcel</option>
            <option>Fragile</option>
            <option>Express</option>
          </select>
          <label for="parcel_type">Parcel type</label>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 d-flex justify-content-end">
    <button type="submit" class="btn btn-primary-gradient btn-lg btn-ripple px-5 py-3"><i class="bi bi-send me-2"></i>Book shipment</button>
  </div>
</form>
<?php dotship_render_app_shell_end(); ?>
<?php dotship_render_assets(); echo '</body></html>'; ?>