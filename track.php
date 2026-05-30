<?php
require_once __DIR__ . '/includes/init.php';

$trackingId = strtoupper(trim((string) ($_GET['tracking_id'] ?? $_POST['tracking_id'] ?? '')));
$shipment = null;

if ($trackingId !== '') {
    $shipment = dotship_collection('shipments')->findOne(['tracking_id' => $trackingId]);
    $shipment = $shipment ? $shipment->getArrayCopy() : null;
}

$trackingEvents = $shipment ? dotship_tracking_events($shipment) : [];
$latestEvent = $trackingEvents !== [] ? end($trackingEvents) : null;

dotship_render_head('Track Shipment', 'landing-page');
dotship_render_navbar('track');
?>
<main class="section-pad hero-wrap pt-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10 col-xl-9" data-aos="fade-up">
        <div class="tracking-search mb-4">
          <div class="text-center mb-4">
            <span class="section-kicker mb-3">Tracking</span>
            <h1 class="section-title mt-3 mb-3">Track your shipment instantly</h1>
            <p class="section-subtitle mx-auto mb-0">Enter a tracking ID to view premium courier status and delivery progress.</p>
          </div>
            <form method="get" class="row g-3 align-items-end">
            <div class="col-lg-9">
              <label for="tracking_id" class="form-label fw-semibold">Tracking ID</label>
              <input type="text" class="form-control tracking-input" id="tracking_id" name="tracking_id" placeholder="Example: DSA1B2C101" value="<?php echo dotship_escape($trackingId); ?>">
            </div>
            <div class="col-lg-3 d-grid">
              <button type="submit" class="btn btn-primary-gradient btn-ripple py-3"><i class="bi bi-search me-2"></i>Track</button>
            </div>
          </form>
        </div>

        <?php if ($trackingId !== '' && !$shipment): ?>
          <div class="alert alert-warning shadow-soft rounded-4xl border-0">No shipment found for tracking ID <strong><?php echo dotship_escape($trackingId); ?></strong>.</div>
        <?php endif; ?>

        <?php if ($shipment): ?>
          <div class="tracking-card p-4 p-lg-5" data-aos="fade-up">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
              <div>
                <span class="section-kicker mb-3">Live status</span>
                <h3 class="fw-bold mt-3 mb-2">Tracking ID <?php echo dotship_escape($shipment['tracking_id']); ?></h3>
                <p class="small-muted mb-0">Receiver: <?php echo dotship_escape($shipment['receiver_name']); ?> · Status: <?php echo dotship_escape(dotship_status_label($shipment['status'])); ?></p>
              </div>
              <div class="text-md-end">
                <span class="badge badge-soft <?php echo dotship_status_badge($shipment['status']); ?> fs-6"><?php echo dotship_escape(dotship_status_label($shipment['status'])); ?></span>
                <div class="small-muted mt-2">Updated <?php echo dotship_escape(dotship_format_date($shipment['updated_at'] ?? $shipment['created_at'])); ?></div>
                <?php if ($latestEvent): ?>
                  <div class="tracking-meta mt-2">Latest scan: <?php echo dotship_escape((string) ($latestEvent['label'] ?? 'Update')); ?></div>
                <?php endif; ?>
              </div>
            </div>

            <div class="progress-steps mb-5">
              <?php foreach (dotship_progress_steps((string) $shipment['status']) as $step): ?>
                <div class="progress-step <?php echo $step['done'] ? 'done' : ''; ?> <?php echo $step['key'] === $shipment['status'] ? 'active' : ''; ?>">
                  <div class="bubble"><i class="bi bi-<?php echo $step['done'] ? 'check2' : 'dot'; ?>"></i></div>
                  <div class="label"><?php echo dotship_escape($step['label']); ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="row g-3">
              <div class="col-lg-5">
                <div class="glass-card p-4 h-100">
                  <h6 class="fw-bold mb-3">Shipment details</h6>
                  <div class="small-muted mb-2">Sender: <?php echo dotship_escape($shipment['sender_name']); ?></div>
                  <div class="small-muted mb-2">Parcel: <?php echo dotship_escape($shipment['parcel_description']); ?></div>
                  <div class="small-muted mb-2">Type: <?php echo dotship_escape($shipment['parcel_type']); ?></div>
                  <div class="small-muted">Weight: <?php echo dotship_escape($shipment['parcel_weight']); ?> kg</div>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="glass-card p-4 h-100">
                  <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                    <div>
                      <h6 class="fw-bold mb-1">Movement timeline</h6>
                      <div class="small-muted">Real shipment scans captured in the shipment history.</div>
                    </div>
                    <span class="nav-pill"><i class="bi bi-broadcast me-1"></i> <?php echo count($trackingEvents); ?> events</span>
                  </div>
                  <div class="tracking-activity">
                    <?php foreach ($trackingEvents as $item): ?>
                      <div class="tracking-activity-item <?php echo $item['done'] ? 'is-done' : ''; ?> <?php echo $item['active'] ? 'is-active' : ''; ?>">
                        <div class="timeline-marker <?php echo $item['done'] ? 'is-done' : ''; ?> <?php echo $item['active'] ? 'is-active' : ''; ?>"><i class="bi bi-<?php echo $item['icon']; ?>"></i></div>
                        <div class="tracking-activity-body">
                          <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                            <h6 class="fw-bold mb-1"><?php echo dotship_escape((string) $item['label']); ?></h6>
                            <span class="tracking-time"><?php echo dotship_escape(dotship_format_date($item['at'] ?? $shipment['updated_at'] ?? $shipment['created_at'])); ?></span>
                          </div>
                          <p class="mb-0 small-muted"><?php echo dotship_escape((string) $item['note']); ?></p>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php if (in_array($shipment['status'], ['packed','transit'], true)): ?>
                    <div class="mt-4">
                      <h6 class="fw-bold">Enter delivery code to confirm delivery</h6>
                      <form method="post" action="<?php echo dotship_path('otp_verify.php'); ?>" class="row g-2 align-items-center mt-2">
                        <input type="hidden" name="tracking_id" value="<?php echo dotship_escape($shipment['tracking_id']); ?>">
                        <div class="col-auto"><input type="text" name="otp_code" class="form-control" placeholder="Enter delivery code"></div>
                        <div class="col-auto"><button class="btn btn-success">Verify code</button></div>
                      </form>
                      <div class="small-muted mt-2">If you were sent a delivery code by DOT SHIP, enter it here to confirm receipt and advance status.</div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php dotship_render_footer(); ?>