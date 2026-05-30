<?php
require_once __DIR__ . '/../includes/init.php';

dotship_require_login();
dotship_require_admin();

$users = dotship_collection('users');
$shipments = dotship_collection('shipments');

$totalUsers = $users->countDocuments(['role' => 'user']);
$totalShipments = $shipments->countDocuments();
$delivered = $shipments->countDocuments(['status' => 'delivered']);
$pending = $shipments->countDocuments(['status' => ['$in' => ['booked', 'packed', 'transit']]]);

$statusLabels = ['Booked', 'Packed', 'Transit', 'Delivered'];
$statusData = [
    $shipments->countDocuments(['status' => 'booked']),
    $shipments->countDocuments(['status' => 'packed']),
    $shipments->countDocuments(['status' => 'transit']),
    $shipments->countDocuments(['status' => 'delivered']),
];

$recent = $shipments->find([], ['sort' => ['created_at' => -1], 'limit' => 6]);

dotship_render_head('Admin Dashboard', 'app-page admin-page');
dotship_render_app_shell_start('Admin Dashboard', 'dashboard', 'admin');
dotship_render_flash();
?>
<div class="row g-4 mb-4">
  <div class="col-md-6 col-xl-3"><div class="stat-card p-4 h-100"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-title small text-uppercase mb-2">Users</div><div class="stat-value"><?php echo (int) $totalUsers; ?></div></div><div class="stat-icon"><i class="bi bi-people"></i></div></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card p-4 h-100"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-title small text-uppercase mb-2">Shipments</div><div class="stat-value"><?php echo (int) $totalShipments; ?></div></div><div class="stat-icon"><i class="bi bi-box-seam"></i></div></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card p-4 h-100"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-title small text-uppercase mb-2">Delivered</div><div class="stat-value"><?php echo (int) $delivered; ?></div></div><div class="stat-icon"><i class="bi bi-check2-circle"></i></div></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card p-4 h-100"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-title small text-uppercase mb-2">Pending</div><div class="stat-value"><?php echo (int) $pending; ?></div></div><div class="stat-icon"><i class="bi bi-clock-history"></i></div></div></div></div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="dashboard-panel p-4 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h5 class="panel-title fw-bold mb-1">Shipment analytics</h5>
          <div class="small-muted">Operational distribution across current shipment statuses.</div>
        </div>
        <a href="<?php echo dotship_path('admin/shipments.php'); ?>" class="btn btn-primary-gradient btn-sm btn-ripple">Manage</a>
      </div>
      <div class="analytics-wrap">
        <canvas id="adminChart" height="240"></canvas>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="dashboard-panel p-4 h-100">
      <h5 class="panel-title fw-bold mb-1">Operations notes</h5>
      <p class="small-muted mb-3">The admin UI uses a dark sidebar, premium cards, and modal-based shipment updates for a cleaner management experience.</p>
      <div class="glass-card p-4 mb-3">
        <div class="d-flex align-items-center gap-3"><div class="stat-icon"><i class="bi bi-shield-check"></i></div><div><div class="fw-semibold">Secure handling</div><div class="small-muted">Session-gated admin routes with hashed credentials.</div></div></div>
      </div>
      <div class="glass-card p-4">
        <div class="d-flex align-items-center gap-3"><div class="stat-icon"><i class="bi bi-lightning-charge"></i></div><div><div class="fw-semibold">Fast workflow</div><div class="small-muted">Search, update, and delete without a cluttered interface.</div></div></div>
      </div>
    </div>
  </div>
</div>

<div class="table-card p-4 mt-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h5 class="table-title fw-bold mb-1">Latest shipments</h5>
      <div class="small-muted">Recent system activity visible at a glance.</div>
    </div>
    <a href="<?php echo dotship_path('admin/shipments.php'); ?>" class="btn btn-outline-dark btn-sm">Open shipment manager</a>
  </div>
  <div class="responsive-scroll">
    <table class="table table-modern align-middle mb-0">
      <thead>
        <tr><th>Tracking ID</th><th>Sender</th><th>Receiver</th><th>Status</th><th>Date</th></tr>
      </thead>
      <tbody>
      <?php foreach ($recent as $shipment): $row = $shipment->getArrayCopy(); ?>
        <tr>
          <td class="fw-semibold"><?php echo dotship_escape($row['tracking_id']); ?></td>
          <td><?php echo dotship_escape($row['sender_name']); ?></td>
          <td><?php echo dotship_escape($row['receiver_name']); ?></td>
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
  const canvas = document.getElementById('adminChart');
  if (!canvas || !window.Chart) return;

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_SLASHES); ?>,
      datasets: [{
        label: 'Shipments',
        data: <?php echo json_encode($statusData, JSON_UNESCAPED_SLASHES); ?>,
        backgroundColor: ['#6b7280', '#0ea5e9', '#ff7a00', '#16a34a'],
        borderRadius: 14,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      },
      maintainAspectRatio: false,
    }
  });
});
</script>
<?php echo '</body></html>'; ?>