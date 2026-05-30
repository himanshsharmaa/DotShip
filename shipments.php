<?php
require_once __DIR__ . '/includes/init.php';

dotship_require_login();

if (dotship_is_admin()) {
    header('Location: ' . dotship_path('admin/shipments.php'));
    exit;
}

$user = dotship_current_user();
$query = trim((string) ($_GET['q'] ?? ''));

$filter = ['user_id' => $user['_id']];
if ($query !== '') {
  $safeQuery = preg_quote($query, '/');
    $filter['$or'] = [
    ['tracking_id' => new MongoDB\BSON\Regex($safeQuery, 'i')],
    ['receiver_name' => new MongoDB\BSON\Regex($safeQuery, 'i')],
    ['status' => new MongoDB\BSON\Regex($safeQuery, 'i')],
    ];
}

$shipments = dotship_collection('shipments')->find($filter, ['sort' => ['created_at' => -1]]);

dotship_render_head('My Shipments', 'app-page');
dotship_render_app_shell_start('My Shipments', 'shipments', 'user');
dotship_render_flash();
?>
<div class="table-card p-4">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
      <h5 class="table-title fw-bold mb-1">Shipment history</h5>
      <div class="small-muted">Search and review all parcels booked under your account.</div>
    </div>
    <div class="d-flex gap-2 w-100 w-lg-auto ms-lg-auto search-max-420">
      <div class="input-group tracking-search w-100">
        <span class="input-group-text border-0 bg-white"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control border-0" placeholder="Search tracking ID or status" data-live-search="#shipmentsTable" value="<?php echo dotship_escape($query); ?>">
      </div>
      <a href="<?php echo dotship_path('book.php'); ?>" class="btn btn-primary-gradient btn-ripple px-4">Book</a>
    </div>
  </div>

  <div class="responsive-scroll">
    <table id="shipmentsTable" class="table table-modern align-middle mb-0">
      <thead>
        <tr>
          <th>Tracking ID</th>
          <th>Receiver</th>
          <th>Parcel</th>
          <th>Status</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($shipments as $shipment): $row = $shipment->getArrayCopy(); ?>
        <tr>
          <td class="fw-semibold"><?php echo dotship_escape($row['tracking_id']); ?></td>
          <td>
            <div class="fw-semibold"><?php echo dotship_escape($row['receiver_name']); ?></div>
            <div class="small-muted"><?php echo dotship_escape($row['receiver_phone']); ?></div>
          </td>
          <td>
            <div class="fw-semibold"><?php echo dotship_escape($row['parcel_description']); ?></div>
            <div class="small-muted"><?php echo dotship_escape($row['parcel_type']); ?> · <?php echo dotship_escape($row['parcel_weight']); ?> kg</div>
          </td>
          <td><span class="badge badge-soft <?php echo dotship_status_badge($row['status']); ?>"><?php echo dotship_escape(dotship_status_label($row['status'])); ?></span></td>
          <td><?php echo dotship_escape(dotship_format_date($row['created_at'])); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php dotship_render_app_shell_end(); ?>
<?php dotship_render_assets(); echo '</body></html>'; ?>