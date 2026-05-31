<?php
require_once __DIR__ . '/includes/init.php';

if (dotship_is_logged_in()) {
    header('Location: ' . (dotship_is_admin() ? dotship_path('admin/index.php') : dotship_path('dashboard.php')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dotship_verify_csrf();

    $email = strtolower(dotship_post('email'));
    $password = (string) ($_POST['password'] ?? '');
    $user = dotship_authenticate($email, $password);

    if ($user) {
        dotship_login_user($user);
        dotship_flash('success', 'Welcome back to DOT SHIP.');
        header('Location: ' . ((($user['role'] ?? 'user') === 'admin') ? dotship_path('admin/index.php') : dotship_path('dashboard.php')));
        exit;
    }

    dotship_flash('error', 'Invalid email or password.');
    header('Location: ' . dotship_path('login.php'));
    exit;
}

dotship_render_head('Login', 'auth-page');
?>
<div class="auth-shell container-fluid px-0">
  <div class="row g-0 auth-shell">
    <div class="col-lg-5 auth-visual d-flex align-items-center justify-content-center p-4 p-lg-5 text-white">
      <div class="w-100 position-relative" data-aos="fade-up">
        <a href="<?php echo dotship_path('index.php'); ?>" class="brand-mark mb-4 d-inline-flex"><span class="brand-icon"><i class="bi bi-truck-front-fill"></i></span><span class="brand-text">DOT SHIP</span></a>
        <h1 class="display-4 fw-bold mb-3">Login to a premium logistics workspace.</h1>
        <p class="text-white-50 mb-4">Manage shipments with a product-grade interface inspired by modern SaaS platforms and startup operations tools.</p>
        <div class="row g-3 mb-4">
          <div class="col-6"><div class="glass-card p-3 text-white"><div class="fw-bold fs-4">24/7</div><div class="small text-white-50">Tracking access</div></div></div>
          <div class="col-6"><div class="glass-card p-3 text-white"><div class="fw-bold fs-4">Safe</div><div class="small text-white-50">Session handling</div></div></div>
        </div>
        <img src="<?php echo dotship_asset('assets/img/hero-illustration.svg'); ?>" alt="DOT SHIP illustration" class="img-fluid floating">
      </div>
    </div>
    <div class="col-lg-7 auth-panel d-flex align-items-center justify-content-center p-4 p-lg-5">
      <div class="w-100 auth-form-wrap motion-fade-up" data-aos="fade-up">
        <?php dotship_render_flash_toast('DOT SHIP Login'); ?>
        <div class="auth-card p-4 p-lg-5 card-tilt motion-pop">
          <div class="mb-4">
            <span class="section-kicker mb-3">Welcome back</span>
            <h2 class="auth-title mt-3 mb-2">Sign in to continue</h2>
            <p class="auth-subtitle mb-0">Use your DOT SHIP customer or admin account to access the platform.</p>
          </div>
          <form method="post" class="needs-validation" novalidate>
            <?php echo dotship_csrf_field(); ?>
            <div class="form-floating mb-3">
              <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
              <label for="email">Email address</label>
            </div>
            <div class="form-floating mb-3 position-relative">
              <input type="password" class="form-control pe-5" id="password" name="password" placeholder="Password" required>
              <label for="password">Password</label>
              <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2 text-muted" data-toggle-password="#password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="remember">
                <label class="form-check-label" for="remember">Remember me</label>
              </div>
              <a href="<?php echo dotship_path('track.php'); ?>" class="small text-accent fw-semibold">Track shipment instead</a>
            </div>
            <button type="submit" class="btn btn-primary-gradient btn-lg w-100 btn-ripple py-3">Login</button>
            <div class="d-flex gap-2 mt-3">
              <button type="button" id="show-otp-btn" class="btn btn-outline-secondary btn-sm flex-grow-1">Use delivery OTP</button>
              <a href="<?php echo dotship_path('register.php'); ?>" class="btn btn-link btn-sm text-muted">Sign up</a>
            </div>
          </form>
          <div id="otp-demo" class="mt-4" style="display:none">
            <div class="small-muted mb-2 text-center">Enter the 6-digit delivery code</div>
            <div class="d-flex justify-content-center gap-2 otp-box">
              <?php for ($i=0;$i<6;$i++): ?>
                <input inputmode="numeric" maxlength="1" class="form-control otp-digit text-center" style="width:3rem;min-width:48px;font-size:1.25rem;padding:.5rem" />
              <?php endfor; ?>
            </div>
            <div class="text-center mt-3">
              <button id="otp-submit-demo" class="btn btn-sm btn-primary">Verify</button>
              <button id="otp-cancel-demo" class="btn btn-link text-muted">Cancel</button>
            </div>
          </div>

          <div class="text-center mt-4 small-muted">Admin? <a href="<?php echo dotship_path('admin/login.php'); ?>" class="fw-semibold text-accent">Admin login</a></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const showOtp = document.getElementById('show-otp-btn');
  const otpDemo = document.getElementById('otp-demo');
  const otpDigits = Array.from(document.querySelectorAll('.otp-digit'));
  const otpCancel = document.getElementById('otp-cancel-demo');

  if (showOtp && otpDemo) {
    showOtp.addEventListener('click', () => { otpDemo.style.display = 'block'; otpDigits[0]?.focus(); });
  }

  otpDigits.forEach((el, idx) => {
    el.addEventListener('input', (e) => {
      const v = el.value.replace(/[^0-9]/g, '');
      el.value = v;
      if (v && otpDigits[idx+1]) otpDigits[idx+1].focus();
    });
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !el.value && otpDigits[idx-1]) {
        otpDigits[idx-1].focus();
      }
    });
  });

  if (otpCancel) {
    otpCancel.addEventListener('click', () => { otpDemo.style.display = 'none'; });
  }
});
</script>
<?php dotship_render_assets(); echo '</body></html>'; ?>