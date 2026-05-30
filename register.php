<?php
require_once __DIR__ . '/includes/init.php';

if (dotship_is_logged_in()) {
    header('Location: ' . (dotship_is_admin() ? dotship_path('admin/index.php') : dotship_path('dashboard.php')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dotship_verify_csrf();

    $name = dotship_post('name');
    $email = strtolower(dotship_post('email'));
    $phone = dotship_post('phone');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '' || $email === '' || $phone === '' || $password === '') {
        dotship_flash('error', 'Please complete every field.');
        header('Location: ' . dotship_path('register.php'));
        exit;
    }

    if ($password !== $confirm) {
        dotship_flash('error', 'Passwords do not match.');
        header('Location: ' . dotship_path('register.php'));
        exit;
    }

    if (dotship_collection('users')->findOne(['email' => $email])) {
        dotship_flash('error', 'An account already exists with that email.');
        header('Location: ' . dotship_path('register.php'));
        exit;
    }

    $insert = dotship_collection('users')->insertOne([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'user',
        'created_at' => dotship_now(),
    ]);

    $user = dotship_collection('users')->findOne(['_id' => $insert->getInsertedId()]);
    if ($user) {
        dotship_login_user($user->getArrayCopy());
    }

    dotship_flash('success', 'Account created successfully.');
    header('Location: ' . dotship_path('dashboard.php'));
    exit;
}

dotship_render_head('Register', 'auth-page');
?>
<div class="auth-shell container-fluid px-0">
  <div class="row g-0 auth-shell">
    <div class="col-lg-5 auth-visual d-flex align-items-center justify-content-center p-4 p-lg-5 text-white">
      <div class="w-100 position-relative" data-aos="fade-up">
        <a href="<?php echo dotship_path('index.php'); ?>" class="brand-mark mb-4 d-inline-flex"><span class="brand-icon"><i class="bi bi-truck-front-fill"></i></span><span class="brand-text">DOT SHIP</span></a>
        <h1 class="display-4 fw-bold mb-3">Create your DOT SHIP account.</h1>
        <p class="text-white-50 mb-4">Register once and access booking, shipment history, and premium tracking in a single clean interface.</p>
        <div class="row g-3 mb-4">
          <div class="col-6"><div class="glass-card p-3 text-white"><div class="fw-bold fs-4">Easy</div><div class="small text-white-50">Onboarding</div></div></div>
          <div class="col-6"><div class="glass-card p-3 text-white"><div class="fw-bold fs-4">Clean</div><div class="small text-white-50">User profile</div></div></div>
        </div>
        <img src="<?php echo dotship_asset('assets/img/hero-illustration.svg'); ?>" alt="DOT SHIP illustration" class="img-fluid floating">
      </div>
    </div>
    <div class="col-lg-7 auth-panel d-flex align-items-center justify-content-center p-4 p-lg-5">
      <div class="w-100 auth-form-wrap" data-aos="fade-up">
        <?php dotship_render_flash_toast('DOT SHIP Register'); ?>
        <div class="auth-card p-4 p-lg-5">
          <div class="mb-4">
            <span class="section-kicker mb-3">Join the platform</span>
            <h2 class="auth-title mt-3 mb-2">Register a new account</h2>
            <p class="auth-subtitle mb-0">Create your customer profile and start booking shipments immediately.</p>
          </div>
          <form method="post" class="row g-3">
            <?php echo dotship_csrf_field(); ?>
            <div class="col-12 form-floating">
              <input type="text" class="form-control" id="name" name="name" placeholder="Full name" required>
              <label for="name">Full name</label>
            </div>
            <div class="col-md-6 form-floating">
              <input type="email" class="form-control" id="reg_email" name="email" placeholder="name@example.com" required>
              <label for="reg_email">Email address</label>
            </div>
            <div class="col-md-6 form-floating">
              <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone number" required>
              <label for="phone">Phone number</label>
            </div>
            <div class="col-md-6 form-floating position-relative">
              <input type="password" class="form-control pe-5" id="reg_password" name="password" placeholder="Password" required>
              <label for="reg_password">Password</label>
              <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2 text-muted" data-toggle-password="#reg_password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="col-md-6 form-floating position-relative">
              <input type="password" class="form-control pe-5" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
              <label for="confirm_password">Confirm password</label>
              <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2 text-muted" data-toggle-password="#confirm_password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary-gradient btn-lg w-100 btn-ripple py-3">Create account</button>
            </div>
          </form>
          <div class="text-center mt-4 small-muted">Already registered? <a href="<?php echo dotship_path('login.php'); ?>" class="fw-semibold text-accent">Login here</a></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php dotship_render_assets(); echo '</body></html>'; ?>