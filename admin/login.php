<?php
require_once __DIR__ . '/../includes/init.php';

if (dotship_is_logged_in() && dotship_is_admin()) {
    header('Location: ' . dotship_path('admin/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dotship_verify_csrf();

    $email = strtolower(dotship_post('email'));
    $password = (string) ($_POST['password'] ?? '');
    $user = dotship_authenticate($email, $password, 'admin');

    if ($user) {
        dotship_login_user($user);
        dotship_flash('success', 'Admin access granted.');
        header('Location: ' . dotship_path('admin/index.php'));
        exit;
    }

    dotship_flash('error', 'Admin credentials are invalid.');
    header('Location: ' . dotship_path('admin/login.php'));
    exit;
}

dotship_render_head('Admin Login', 'auth-page');
?>
<div class="auth-shell container-fluid px-0">
  <div class="row g-0 auth-shell">
    <div class="col-lg-5 auth-visual d-flex align-items-center justify-content-center p-4 p-lg-5 text-white">
      <div class="w-100 position-relative" data-aos="fade-up">
        <a href="<?php echo dotship_path('index.php'); ?>" class="brand-mark mb-4 d-inline-flex"><span class="brand-icon"><i class="bi bi-truck-front-fill"></i></span><span class="brand-text">DOT SHIP</span></a>
        <h1 class="display-4 fw-bold mb-3">Admin control for premium courier operations.</h1>
        <p class="text-white-50 mb-4">Access analytics, shipment updates, and management tools inside a dark, polished operations dashboard.</p>
        <img src="<?php echo dotship_asset('assets/img/hero-illustration.svg'); ?>" alt="DOT SHIP illustration" class="img-fluid floating">
      </div>
    </div>
    <div class="col-lg-7 auth-panel d-flex align-items-center justify-content-center p-4 p-lg-5">
      <div class="w-100 auth-form-wrap" data-aos="fade-up">
        <?php dotship_render_flash_toast('DOT SHIP Admin'); ?>
        <div class="auth-card p-4 p-lg-5">
          <div class="mb-4">
            <span class="section-kicker mb-3">Admin access</span>
            <h2 class="auth-title mt-3 mb-2">Sign in to the control room</h2>
            <p class="auth-subtitle mb-0">Use the seeded admin account or your own secured admin profile.</p>
          </div>
          <form method="post">
            <?php echo dotship_csrf_field(); ?>
            <div class="form-floating mb-3">
              <input type="email" class="form-control" id="admin_email" name="email" placeholder="admin@example.com" required>
              <label for="admin_email">Admin email</label>
            </div>
            <div class="form-floating mb-3 position-relative">
              <input type="password" class="form-control pe-5" id="admin_password" name="password" placeholder="Password" required>
              <label for="admin_password">Password</label>
              <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2 text-muted" data-toggle-password="#admin_password"><i class="bi bi-eye"></i></button>
            </div>
            <button type="submit" class="btn btn-primary-gradient btn-lg w-100 btn-ripple py-3">Login as admin</button>
          </form>
          <div class="text-center mt-4 small-muted"><a href="<?php echo dotship_path('login.php'); ?>" class="fw-semibold text-accent">Back to customer login</a></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php dotship_render_assets(); echo '</body></html>'; ?>