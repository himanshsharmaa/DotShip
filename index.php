<?php
require_once __DIR__ . '/includes/init.php';

dotship_render_head('Home', 'landing-page');
dotship_render_navbar('home');
?>
<main>
  <section class="hero-wrap">
    <div class="container">
      <div class="row align-items-center g-5">
        <div class="col-lg-6" data-aos="fade-up">
          <div class="section-kicker mb-3"><i class="bi bi-send-check"></i> Premium logistics platform</div>
          <h1 class="hero-title text-navy mb-4">DOT SHIP</h1>
          <p class="display-6 fw-semibold mb-3 text-navy">Fast. Smart. Reliable.</p>
          <p class="hero-copy mb-4">A premium web-based courier and parcel management system built for fast booking, real-time tracking, and polished operations that feel closer to a modern SaaS product than a classroom CRUD app.</p>
          <div class="d-flex flex-wrap gap-3 hero-actions mb-4">
            <a href="<?php echo dotship_path('track.php'); ?>" class="btn btn-primary-gradient btn-lg btn-ripple px-4"><i class="bi bi-search me-2"></i>Track Shipment</a>
            <a href="<?php echo dotship_path('login.php'); ?>" class="btn btn-outline-dark btn-lg px-4"><i class="bi bi-box-arrow-in-right me-2"></i>Login</a>
          </div>
          <div class="hero-stats">
            <div class="hero-stat">
              <span class="value">99.2%</span>
              <span class="label">Delivery reliability</span>
            </div>
            <div class="hero-stat">
              <span class="value">24/7</span>
              <span class="label">Tracking visibility</span>
            </div>
            <div class="hero-stat">
              <span class="value">Smart</span>
              <span class="label">Dashboard analytics</span>
            </div>
          </div>
        </div>
        <div class="col-lg-6" data-aos="fade-left">
          <div class="hero-panel floating">
            <div class="d-flex justify-content-between align-items-center mb-4 text-white">
              <div>
                <div class="hero-badge-wrap mb-2"><span class="hero-badge"><i class="bi bi-broadcast-pin"></i> Live network sync</span></div>
                <h3 class="fw-bold mb-1">Courier operations reimagined</h3>
                <p class="text-white-50 mb-0">A premium interface for modern shipment workflows.</p>
              </div>
              <div class="glass-ribbon"><i class="bi bi-shield-check"></i> Secure</div>
            </div>
            <div class="illustration-frame d-flex align-items-center justify-content-center">
              <img src="<?php echo dotship_asset('assets/img/hero-illustration.svg'); ?>" alt="DOT SHIP courier illustration" class="img-fluid">
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-pad pt-0">
    <div class="container">
      <div class="text-center mb-5" data-aos="fade-up">
        <span class="section-kicker mb-3">Features</span>
        <h2 class="section-title mt-3 mb-3">Everything you need in a premium logistics flow</h2>
        <p class="section-subtitle mx-auto">From booking to delivery, DOT SHIP keeps the interface elegant while the workflows stay fast, measurable, and easy to operate.</p>
      </div>
      <div class="row g-4">
        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="50">
          <div class="feature-card p-4 h-100">
            <div class="feature-icon mb-3"><i class="bi bi-lightning-charge-fill"></i></div>
            <h5 class="fw-bold">Fast Booking</h5>
            <p class="mb-0">Book shipments in a clean guided flow with validation and instant tracking ID generation.</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
          <div class="feature-card p-4 h-100">
            <div class="feature-icon mb-3"><i class="bi bi-geo-alt-fill"></i></div>
            <h5 class="fw-bold">Live Tracking</h5>
            <p class="mb-0">Track a parcel using a premium search experience with a progress timeline and status stages.</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="150">
          <div class="feature-card p-4 h-100">
            <div class="feature-icon mb-3"><i class="bi bi-shield-lock-fill"></i></div>
            <h5 class="fw-bold">Secure Delivery</h5>
            <p class="mb-0">Session protection, password hashing, CSRF checks, and SQLite-backed storage keep data guarded.</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
          <div class="feature-card p-4 h-100">
            <div class="feature-icon mb-3"><i class="bi bi-graph-up-arrow"></i></div>
            <h5 class="fw-bold">Smart Dashboard</h5>
            <p class="mb-0">Admin and user dashboards show operational counts, charts, and recent shipment activity.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-pad pt-0">
    <div class="container">
      <div class="row align-items-center g-5">
        <div class="col-lg-5" data-aos="fade-right">
          <div class="soft-card p-4 p-lg-5 overflow-hidden">
            <img src="<?php echo dotship_asset('assets/img/hero-illustration.svg'); ?>" alt="Premium logistics platform" class="img-fluid rounded-4xl shadow-soft">
          </div>
        </div>
        <div class="col-lg-7" data-aos="fade-left">
          <span class="section-kicker mb-3">About</span>
          <h2 class="section-title mt-3 mb-3">A logistics startup aesthetic with a clean operations core</h2>
          <p class="section-subtitle mb-4">DOT SHIP is designed to feel like a polished product demo: modern gradients, glass cards, soft shadows, responsive layouts, and purposeful interactions that elevate the ordinary courier workflow.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="glass-card p-4 h-100">
                <h6 class="fw-bold mb-2"><i class="bi bi-palette2 text-accent me-2"></i>Premium visual language</h6>
                <p class="mb-0 small-muted">Deep navy, premium orange, and spacious layouts inspired by modern SaaS dashboards.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="glass-card p-4 h-100">
                <h6 class="fw-bold mb-2"><i class="bi bi-layout-text-window-reverse text-accent me-2"></i>Reusable system</h6>
                <p class="mb-0 small-muted">Shared cards, buttons, forms, tables, and modals keep the entire app coherent and maintainable.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-pad pt-0">
    <div class="container">
      <div class="text-center mb-5" data-aos="fade-up">
        <span class="section-kicker mb-3">Process</span>
        <h2 class="section-title mt-3 mb-3">Book to delivered in four clear steps</h2>
        <p class="section-subtitle mx-auto">A refined timeline gives users a quick understanding of where the parcel is in the courier chain.</p>
      </div>
      <div class="timeline-card p-4 p-lg-5">
        <div class="timeline-track">
          <div class="timeline-item" data-aos="fade-up">
            <div class="timeline-marker is-done"><i class="bi bi-clipboard-check"></i></div>
            <h5 class="fw-bold mb-1">Book</h5>
            <p class="mb-0 small-muted">Create shipment details with sender, receiver, and parcel information.</p>
          </div>
          <div class="timeline-item" data-aos="fade-up" data-aos-delay="80">
            <div class="timeline-marker is-done"><i class="bi bi-box-seam"></i></div>
            <h5 class="fw-bold mb-1">Dispatch</h5>
            <p class="mb-0 small-muted">Parcel moves into the handling queue for packaging and routing.</p>
          </div>
          <div class="timeline-item" data-aos="fade-up" data-aos-delay="120">
            <div class="timeline-marker is-active"><i class="bi bi-truck"></i></div>
            <h5 class="fw-bold mb-1">Transit</h5>
            <p class="mb-0 small-muted">Track movement through a smooth and visible logistics pipeline.</p>
          </div>
          <div class="timeline-item mb-0" data-aos="fade-up" data-aos-delay="160">
            <div class="timeline-marker"><i class="bi bi-check2-circle"></i></div>
            <h5 class="fw-bold mb-1">Delivered</h5>
            <p class="mb-0 small-muted">The shipment reaches the receiver and the status is finalized.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
<?php dotship_render_footer(); ?>