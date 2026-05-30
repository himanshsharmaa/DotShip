<?php
require_once __DIR__ . '/../includes/init.php';

dotship_require_login();
dotship_require_admin();

$collection = dotship_collection('shipments');
$query = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;
$filter = [];
$modals = '';

if ($query !== '') {
  $safeQuery = preg_quote($query, '/');
    $filter['$or'] = [
    ['tracking_id' => new MongoDB\BSON\Regex($safeQuery, 'i')],
    ['sender_name' => new MongoDB\BSON\Regex($safeQuery, 'i')],
    ['receiver_name' => new MongoDB\BSON\Regex($safeQuery, 'i')],
    ['status' => new MongoDB\BSON\Regex($safeQuery, 'i')],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dotship_verify_csrf();

    $action = dotship_post('action');
    $shipmentId = dotship_post('shipment_id');

    if ($action === 'update') {
        $status = dotship_post('status');
        $note = dotship_post('note');
        $receiverEmail = dotship_post('receiver_email');
        $existing = dotship_collection('shipments')->findOne(['_id' => new MongoDB\BSON\ObjectId($shipmentId)]);
        $existingRow = $existing ? $existing->getArrayCopy() : null;

        dotship_collection('shipments')->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($shipmentId)],
            ['$set' => ['status' => $status, 'receiver_email' => $receiverEmail !== '' ? $receiverEmail : null, 'updated_at' => dotship_now()], '$push' => ['history' => ['status' => $status, 'label' => dotship_status_label($status), 'note' => $note !== '' ? $note : 'Status updated by admin', 'at' => dotship_now()]]]
        );

        if ($existingRow !== null) {
            $previousStatus = (string) ($existingRow['status'] ?? 'booked');
            $targetEmail = $receiverEmail !== '' ? $receiverEmail : (string) ($existingRow['receiver_email'] ?? '');

            if ($status === 'transit' && $previousStatus !== 'transit' && $targetEmail !== '') {
                $trackingId = (string) ($existingRow['tracking_id'] ?? '');
                $otp = dotship_create_otp($trackingId, $targetEmail, 'email');
                dotship_send_otp($targetEmail, $otp['code'], 'email', $trackingId);
                dotship_notify($existingRow, 'delivery_code_sent', 'Delivery code sent automatically to ' . $targetEmail);
            }
        }

        dotship_flash('success', 'Shipment status updated successfully.');
        header('Location: ' . dotship_path('admin/shipments.php'));
        exit;
    }

    if ($action === 'delete') {
        dotship_collection('shipments')->deleteOne(['_id' => new MongoDB\BSON\ObjectId($shipmentId)]);
        dotship_flash('success', 'Shipment deleted.');
        header('Location: ' . dotship_path('admin/shipments.php'));
        exit;
    }
}

$total = $collection->countDocuments($filter);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$cursor = $collection->find($filter, ['sort' => ['created_at' => -1], 'skip' => ($page - 1) * $perPage, 'limit' => $perPage]);

dotship_render_head('Shipment Manager', 'app-page admin-page');
dotship_render_app_shell_start('Shipment Manager', 'shipments', 'admin');
dotship_render_flash();
?>
<div class="table-card p-4">
  <form method="get" class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
      <h5 class="table-title fw-bold mb-1">Manage shipments</h5>
      <div class="small-muted">Search, update status, and remove shipments using a polished admin interface.</div>
    </div>
    <div class="input-group tracking-search search-max-420">
      <span class="input-group-text border-0 bg-white"><i class="bi bi-search"></i></span>
      <input type="search" class="form-control border-0" name="q" placeholder="Search by tracking, sender, receiver" value="<?php echo dotship_escape($query); ?>">
      <button class="btn btn-primary-gradient btn-ripple px-4" type="submit">Search</button>
    </div>
  </form>

  <div class="responsive-scroll">
    <table class="table table-modern align-middle mb-0">
      <thead>
        <tr>
          <th>Tracking ID</th>
          <th>Sender</th>
          <th>Receiver</th>
          <th>Parcel</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($cursor as $shipment): $row = $shipment->getArrayCopy(); ?>
        <tr>
          <td class="fw-semibold"><?php echo dotship_escape($row['tracking_id']); ?></td>
          <td><?php echo dotship_escape($row['sender_name']); ?></td>
          <td><?php echo dotship_escape($row['receiver_name']); ?></td>
          <td><?php echo dotship_escape($row['parcel_description']); ?></td>
          <td><span class="badge badge-soft <?php echo dotship_status_badge($row['status']); ?>"><?php echo dotship_escape(dotship_status_label($row['status'])); ?></span></td>
          <td>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#editModal<?php echo (string) $row['_id']; ?>">Update</button>
              <form method="post" class="d-inline">
                <?php echo dotship_csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="shipment_id" value="<?php echo dotship_escape((string) $row['_id']); ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm-delete="This shipment will be removed permanently.">Delete</button>
              </form>
            </div>
          </td>
        </tr>

        <?php $modals .= '<div class="modal fade" id="editModal' . (string) $row['_id'] . '" tabindex="-1" aria-hidden="true">'; ?>
        <?php $modals .= '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header border-0"><div><h5 class="modal-title fw-bold mb-1">Update shipment</h5><div class="small-muted">Tracking ID ' . dotship_escape($row['tracking_id']) . '</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="post"><div class="modal-body pt-0">'; ?>
        <?php $modals .= dotship_csrf_field(); ?>
        <?php $modals .= '<input type="hidden" name="action" value="update"><input type="hidden" name="shipment_id" value="' . dotship_escape((string) $row['_id']) . '"><div class="row g-3"><div class="col-md-6 form-floating"><select class="form-select" id="status' . (string) $row['_id'] . '" name="status" required>'; ?>
        <?php foreach (['booked','packed','transit','delivered'] as $status): ?>
          <?php $modals .= '<option value="' . $status . '"' . ($row['status'] === $status ? ' selected' : '') . '>' . dotship_escape(dotship_status_label($status)) . '</option>'; ?>
        <?php endforeach; ?>
        <?php $modals .= '</select><label for="status' . (string) $row['_id'] . '">Status</label></div><div class="col-md-6 form-floating"><input type="email" class="form-control" id="receiver_email' . (string) $row['_id'] . '" name="receiver_email" placeholder="Receiver email" value="' . dotship_escape((string) ($row['receiver_email'] ?? '')) . '"><label for="receiver_email' . (string) $row['_id'] . '">Receiver email</label></div><div class="col-md-12 form-floating"><input type="text" class="form-control" id="note' . (string) $row['_id'] . '" name="note" placeholder="Update note"><label for="note' . (string) $row['_id'] . '">Update note</label></div></div></div><div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary-gradient btn-ripple px-4">Save changes</button></div></form></div></div></div>'; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <nav class="mt-4">
    <ul class="pagination justify-content-end mb-0">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&q=<?php echo urlencode($query); ?>"><?php echo $i; ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>
<?php dotship_render_app_shell_end(); ?>
<?php echo $modals; ?>
<?php dotship_render_assets(); echo '</body></html>'; ?>