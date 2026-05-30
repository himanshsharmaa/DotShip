<?php
require_once __DIR__ . '/includes/init.php';

dotship_require_login();

if (dotship_is_admin()) {
    header('Location: ' . dotship_path('admin/index.php'));
    exit;
}

$user = dotship_current_user();
$shipments = dotship_collection('shipments');
$userId = $user['_id'];

$total = $shipments->countDocuments(['user_id' => $userId]);
$delivered = $shipments->countDocuments(['user_id' => $userId, 'status' => 'delivered']);
$pending = $shipments->countDocuments(['user_id' => $userId, 'status' => ['$in' => ['booked', 'packed']]]);
$transit = $shipments->countDocuments(['user_id' => $userId, 'status' => 'transit']);

$recent = $shipments->find(['user_id' => $userId], ['sort' => ['created_at' => -1], 'limit' => 5]);

$statusLabels = ['Booked', 'Packed', 'Transit', 'Delivered'];
$statusData = [
    $shipments->countDocuments(['user_id' => $userId, 'status' => 'booked']),
    $shipments->countDocuments(['user_id' => $userId, 'status' => 'packed']),
    $shipments->countDocuments(['user_id' => $userId, 'status' => 'transit']),
    $shipments->countDocuments(['user_id' => $userId, 'status' => 'delivered']),
];

dotship_render_head('Dashboard', 'app-page');
dotship_render_app_shell_start('Dashboard', 'dashboard', 'user');
dotship_render_flash();
?>
<div class="row g-4 mb-4">
  <div class="col-md-6 col-xl-3">
    <div class="stat-card p-4 h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="stat-title small text-uppercase mb-2">Total Shipments</div>
          <div class="stat-value"><?php echo (int) $total; ?></div>
        </div>
        <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="stat-card p-4 h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="stat-title small text-uppercase mb-2">Delivered</div>
          <div class="stat-value"><?php echo (int) $delivered; ?></div>
        </div>
        <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="stat-card p-4 h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="stat-title small text-uppercase mb-2">Pending</div>
          <div class="stat-value"><?php echo (int) $pending; ?></div>
        </div>
        <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="stat-card p-4 h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="stat-title small text-uppercase mb-2">Transit</div>
          <div class="stat-value"><?php echo (int) $transit; ?></div>
        </div>
        <div class="stat-icon"><i class="bi bi-truck"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="dashboard-panel p-4 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h5 class="panel-title fw-bold mb-1">Shipment activity</h5>
          <div class="small-muted">A quick overview of your shipment status mix.</div>
        </div>
        <a href="<?php echo dotship_path('book.php'); ?>" class="btn btn-primary-gradient btn-sm btn-ripple">Book shipment</a>
      </div>
      <div class="analytics-wrap">
        <canvas id="shipmentChart" height="240"></canvas>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="dashboard-panel p-4 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h5 class="panel-title fw-bold mb-1">Recent shipment</h5>
          <div class="small-muted">Latest activity under your account.</div>
        </div>
        <a href="<?php echo dotship_path('shipments.php'); ?>" class="btn btn-outline-dark btn-sm">View all</a>
      </div>
      <div class="responsive-scroll">
        <table class="table table-modern mb-0">
          <tbody>
          <?php foreach ($recent as $shipment): $row = $shipment->getArrayCopy(); ?>
            <tr>
              <td>
                <div class="fw-bold"><?php echo dotship_escape($row['tracking_id']); ?></div>
                <div class="small-muted"><?php echo dotship_escape($row['receiver_name']); ?></div>
              </td>
              <td><span class="badge badge-soft <?php echo dotship_status_badge($row['status']); ?>"><?php echo dotship_escape(dotship_status_label($row['status'])); ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="table-card p-4 mt-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h5 class="table-title fw-bold mb-1">Recent shipments</h5>
      <div class="small-muted">Most recent shipments booked by your profile.</div>
    </div>
    <a href="<?php echo dotship_path('track.php'); ?>" class="btn btn-outline-dark btn-sm">Track shipment</a>
  </div>
  <div class="responsive-scroll">
    <table class="table table-modern align-middle mb-0">
      <thead>
        <tr>
          <th>Tracking ID</th>
          <th>Receiver</th>
          <th>Parcel</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($recent as $shipment): $row = $shipment->getArrayCopy(); ?>
        <tr>
          <td class="fw-semibold"><?php echo dotship_escape($row['tracking_id']); ?></td>
          <td><?php echo dotship_escape($row['receiver_name']); ?></td>
          <td><?php echo dotship_escape($row['parcel_description']); ?></td>
          <td><span class="badge badge-soft <?php echo dotship_status_badge($row['status']); ?>"><?php echo dotship_escape(dotship_status_label($row['status'])); ?></span></td>
          <td><?php echo dotship_escape(dotship_format_date($row['created_at'])); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php dotship_render_app_shell_end(); ?>
<?php dotship_render_assets(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const canvas = document.getElementById('shipmentChart');
  if (!canvas || !window.Chart) return;

  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_SLASHES); ?>,
      datasets: [{
        data: <?php echo json_encode($statusData, JSON_UNESCAPED_SLASHES); ?>,
        backgroundColor: ['#6b7280', '#0ea5e9', '#ff7a00', '#16a34a'],
        borderWidth: 0,
      }],
    },
    options: {
      plugins: { legend: { position: 'bottom' } },
      cutout: '68%',
      maintainAspectRatio: false,
    }
  });
});
</script>
<?php echo '</body></html>'; ?>