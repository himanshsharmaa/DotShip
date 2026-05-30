<?php
declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

function dotship_path(string $path = ''): string
{
    // Build a site-root aware path that works when the app is hosted in a subfolder.
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    } elseif (str_ends_with($dir, '/admin')) {
        $dir = rtrim(dirname($dir), '/');
    }

    return $dir . '/' . ltrim($path, '/');
}

function dotship_asset(string $asset): string
{
    return dotship_path($asset);
}

function dotship_bootstrap(): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    $booted = true;

    dotship_seed_users();

    if (dotship_config()['seed_demo']) {
        dotship_seed_shipments();
    }
}

function dotship_now(): UTCDateTime
{
    return new UTCDateTime((int) round(microtime(true) * 1000));
}

function dotship_clean(?string $value): string
{
    return trim((string) filter_var($value ?? '', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW));
}

function dotship_post(string $key, string $default = ''): string
{
    return dotship_clean($_POST[$key] ?? $default);
}

function dotship_get(string $key, string $default = ''): string
{
    return dotship_clean($_GET[$key] ?? $default);
}

function dotship_flash(string $type, string $message = ''): ?array
{
    if ($message !== '') {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }

    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : null;
    }

    return null;
}

function dotship_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function dotship_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(dotship_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function dotship_verify_csrf(): void
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';

    if (!$sessionToken || !$postToken || !hash_equals((string) $sessionToken, (string) $postToken)) {
        http_response_code(419);
        exit('Invalid session token. Refresh the page and try again.');
    }
}

function dotship_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function dotship_format_date(mixed $value, string $format = 'd M Y, h:i A'): string
{
    if ($value instanceof UTCDateTime) {
        return $value->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($format);
    }

    if ($value instanceof DateTimeInterface) {
        return $value->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($format);
    }

    if (is_string($value) && $value !== '') {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($format);
    }

    return '';
}

function dotship_status_map(): array
{
    return [
        'booked' => ['label' => 'Booked', 'badge' => 'bg-secondary', 'progress' => 1, 'icon' => 'clipboard-check'],
        'packed' => ['label' => 'Packed', 'badge' => 'bg-info text-dark', 'progress' => 2, 'icon' => 'box-seam'],
        'transit' => ['label' => 'Transit', 'badge' => 'bg-primary', 'progress' => 3, 'icon' => 'truck'],
        'delivered' => ['label' => 'Delivered', 'badge' => 'bg-success', 'progress' => 4, 'icon' => 'check2-circle'],
    ];
}

function dotship_status_label(string $status): string
{
    $map = dotship_status_map();
    return $map[$status]['label'] ?? ucfirst($status);
}

function dotship_status_badge(string $status): string
{
    $map = dotship_status_map();
    return $map[$status]['badge'] ?? 'bg-secondary';
}

function dotship_status_progress(string $status): int
{
    $map = dotship_status_map();
    return (int) ($map[$status]['progress'] ?? 1);
}

function dotship_progress_steps(string $status): array
{
    $statusIndex = dotship_status_progress($status);
    $steps = [];

    foreach (dotship_status_map() as $key => $meta) {
        $steps[] = [
            'key' => $key,
            'label' => $meta['label'],
            'done' => $meta['progress'] <= $statusIndex,
        ];
    }

    return $steps;
}

function dotship_tracking_timeline(string $status): array
{
    $base = [
        ['key' => 'booked', 'label' => 'Booked', 'note' => 'Order received and scheduled'],
        ['key' => 'packed', 'label' => 'Packed', 'note' => 'Parcel prepared for dispatch'],
        ['key' => 'transit', 'label' => 'Transit', 'note' => 'Moving through the network'],
        ['key' => 'delivered', 'label' => 'Delivered', 'note' => 'Completed and signed off'],
    ];

    $progress = dotship_status_progress($status);

    foreach ($base as &$step) {
        $step['done'] = dotship_status_progress($step['key']) <= $progress;
        $step['active'] = $step['key'] === $status;
    }

    return $base;
}

function dotship_tracking_events(array $shipment): array
{
    $history = $shipment['history'] ?? [];
    $events = [];

    if (is_array($history) && $history !== []) {
        foreach ($history as $entry) {
            $entry = is_object($entry) && method_exists($entry, 'getArrayCopy') ? $entry->getArrayCopy() : (array) $entry;
            $status = (string) ($entry['status'] ?? '');

            if ($status === '') {
                continue;
            }

            $events[] = [
                'status' => $status,
                'label' => (string) ($entry['label'] ?? dotship_status_label($status)),
                'note' => (string) ($entry['note'] ?? ''),
                'at' => $entry['at'] ?? null,
            ];
        }
    }

    if ($events === []) {
        $createdAt = $shipment['created_at'] ?? null;
        $status = (string) ($shipment['status'] ?? 'booked');

        $events[] = [
            'status' => 'booked',
            'label' => 'Booked',
            'note' => 'Shipment created on DOT SHIP',
            'at' => $createdAt,
        ];

        if ($status !== 'booked') {
            $events[] = [
                'status' => $status,
                'label' => dotship_status_label($status),
                'note' => 'Current logistics status update',
                'at' => $shipment['updated_at'] ?? $createdAt,
            ];
        }
    }

    usort($events, static function (array $left, array $right): int {
        $leftTime = $left['at'] instanceof UTCDateTime ? $left['at']->getMilliseconds() : strtotime((string) ($left['at'] ?? ''));
        $rightTime = $right['at'] instanceof UTCDateTime ? $right['at']->getMilliseconds() : strtotime((string) ($right['at'] ?? ''));

        return $leftTime <=> $rightTime;
    });

    $latestStatus = (string) ($shipment['status'] ?? 'booked');

    foreach ($events as $index => &$event) {
        $event['done'] = dotship_status_progress((string) $event['status']) <= dotship_status_progress($latestStatus);
        $event['active'] = $event['status'] === $latestStatus || $index === array_key_last($events);
        $event['icon'] = $event['done'] ? 'check2-circle' : 'clock-history';
    }

    return $events;
}

function dotship_generate_tracking_id(): string
{
    $collection = dotship_collection('shipments');

    do {
        $id = 'DS' . strtoupper(bin2hex(random_bytes(3))) . random_int(100, 999);
    } while ($collection->findOne(['tracking_id' => $id]) !== null);

    return $id;
}

function dotship_current_user(): ?array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $user = dotship_collection('users')->findOne(['_id' => new ObjectId((string) $_SESSION['user_id'])]);
        $cache = $user ? $user->getArrayCopy() : null;
        return $cache;
    } catch (Throwable) {
        return null;
    }
}

function dotship_is_logged_in(): bool
{
    return dotship_current_user() !== null;
}

function dotship_is_admin(): bool
{
    $user = dotship_current_user();
    return $user !== null && ($user['role'] ?? 'user') === 'admin';
}

function dotship_login_user(array $user): void
{
    $_SESSION['user_id'] = (string) $user['_id'];
    $_SESSION['user_role'] = (string) ($user['role'] ?? 'user');
    session_regenerate_id(true);
}

function dotship_authenticate(string $email, string $password, ?string $role = null): ?array
{
    $query = ['email' => strtolower(trim($email))];

    if ($role !== null) {
        $query['role'] = $role;
    }

    $user = dotship_collection('users')->findOne($query);

    if (!$user) {
        return null;
    }

    $userData = $user->getArrayCopy();

    if (!password_verify($password, (string) ($userData['password'] ?? ''))) {
        return null;
    }

    return $userData;
}

function dotship_logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function dotship_require_login(): void
{
    if (!dotship_is_logged_in()) {
        dotship_flash('warning', 'Please login to continue.');
        header('Location: ' . dotship_path('login.php'));
        exit;
    }
}

function dotship_require_admin(): void
{
    if (!dotship_is_admin()) {
        dotship_flash('danger', 'Admin access required.');
        header('Location: ' . dotship_path('admin/login.php'));
        exit;
    }
}

function dotship_seed_users(): void
{
    $users = dotship_collection('users');
    if ($users->countDocuments() > 0) {
        return;
    }

    $passwordHashAdmin = password_hash((string) dotship_config()['admin_password'], PASSWORD_DEFAULT);
    $passwordHashDemo = password_hash((string) dotship_config()['demo_password'], PASSWORD_DEFAULT);

    $users->insertMany([
        [
            'name' => 'Admin User',
            'email' => dotship_config()['admin_email'],
            'password' => $passwordHashAdmin,
            'role' => 'admin',
            'phone' => '+91 90000 00001',
            'created_at' => dotship_now(),
        ],
        [
            'name' => 'Demo Customer',
            'email' => dotship_config()['demo_email'],
            'password' => $passwordHashDemo,
            'role' => 'user',
            'phone' => '+91 90000 00002',
            'created_at' => dotship_now(),
        ],
    ]);
}

function dotship_seed_shipments(): void
{
    $shipments = dotship_collection('shipments');

    if ($shipments->countDocuments() > 0) {
        return;
    }

    $user = dotship_collection('users')->findOne(['email' => dotship_config()['demo_email']]);
    if (!$user) {
        return;
    }

    $seed = [
        [
            'tracking_id' => 'DSA1B2C101',
            'sender_name' => 'Demo Customer',
            'sender_phone' => '+91 90000 00002',
            'sender_address' => 'Bengaluru, Karnataka',
            'receiver_name' => 'Aarav Mehta',
            'receiver_phone' => '+91 98888 88888',
            'receiver_address' => 'Mumbai, Maharashtra',
            'parcel_description' => 'Documents',
            'parcel_weight' => '1.0',
            'parcel_type' => 'Documents',
            'status' => 'delivered',
        ],
        [
            'tracking_id' => 'DSD4E5F202',
            'sender_name' => 'Demo Customer',
            'sender_phone' => '+91 90000 00002',
            'sender_address' => 'Bengaluru, Karnataka',
            'receiver_name' => 'Sara Khan',
            'receiver_phone' => '+91 97777 77777',
            'receiver_address' => 'Delhi, NCR',
            'parcel_description' => 'Accessories',
            'parcel_weight' => '2.3',
            'parcel_type' => 'Parcel',
            'status' => 'transit',
        ],
        [
            'tracking_id' => 'DSG7H8I303',
            'sender_name' => 'Demo Customer',
            'sender_phone' => '+91 90000 00002',
            'sender_address' => 'Bengaluru, Karnataka',
            'receiver_name' => 'Neha Sharma',
            'receiver_phone' => '+91 96666 66666',
            'receiver_address' => 'Hyderabad, Telangana',
            'parcel_description' => 'Gift Box',
            'parcel_weight' => '3.5',
            'parcel_type' => 'Fragile',
            'status' => 'packed',
        ],
    ];

    foreach ($seed as $item) {
        $shipments->insertOne([
            ...$item,
            'user_id' => $user['_id'],
            'created_at' => dotship_now(),
            'updated_at' => dotship_now(),
            'history' => [
                ['status' => 'booked', 'label' => 'Booked', 'note' => 'Shipment booked on the DOT SHIP platform', 'at' => dotship_now()],
                ['status' => $item['status'], 'label' => dotship_status_label($item['status']), 'note' => 'Current logistics status update', 'at' => dotship_now()],
            ],
        ]);
    }
}

function dotship_render_head(string $title, string $bodyClass = ''): void
{
    $app = dotship_config();
    $safeTitle = dotship_escape($title . ' | ' . $app['app_name']);

    echo '<!doctype html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="theme-color" content="#0B1F3A">';
    echo '<title>' . $safeTitle . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">';
    echo '<link rel="stylesheet" href="' . dotship_asset('assets/css/style.css') . '">';
    echo '</head>';
    echo '<body class="' . dotship_escape($bodyClass) . '">';
}

function dotship_render_navbar(string $active = 'home'): void
{
    $app = dotship_config();
    $links = [
        'home' => ['label' => 'Home', 'href' => dotship_path('index.php')],
        'track' => ['label' => 'Track', 'href' => dotship_path('track.php')],
        'login' => ['label' => 'Login', 'href' => dotship_path('login.php')],
    ];

    echo '<nav class="navbar navbar-expand-lg navbar-dark navbar-glass fixed-top py-3">';
    echo '<div class="container">';
    echo '<a class="navbar-brand brand-mark" href="' . dotship_path('index.php') . '"><span class="brand-icon"><i class="bi bi-truck-front-fill"></i></span><span class="brand-text">' . dotship_escape($app['app_name']) . '</span></a>';
    echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav">';
    echo '<span class="navbar-toggler-icon"></span>';
    echo '</button>';
    echo '<div class="collapse navbar-collapse" id="siteNav">';
    echo '<ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">';
    foreach ($links as $key => $link) {
        $activeClass = $active === $key ? ' active' : '';
        echo '<li class="nav-item"><a class="nav-link' . $activeClass . '" href="' . $link['href'] . '">' . dotship_escape($link['label']) . '</a></li>';
    }
    if (dotship_is_logged_in()) {
        echo '<li class="nav-item"><a class="btn btn-sm btn-primary-gradient ms-lg-2" href="' . dotship_path('dashboard.php') . '">Dashboard</a></li>';
    }
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    echo '</nav>';
}

function dotship_app_nav_items(string $role = 'user'): array
{
    if ($role === 'admin') {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid-1x2', 'href' => dotship_path('admin/index.php')],
            ['key' => 'shipments', 'label' => 'Shipments', 'icon' => 'box-seam', 'href' => dotship_path('admin/shipments.php')],
            ['key' => 'logout', 'label' => 'Logout', 'icon' => 'box-arrow-right', 'href' => dotship_path('logout.php')],
        ];
    }

        return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid-1x2', 'href' => dotship_path('dashboard.php')],
        ['key' => 'book', 'label' => 'Book Shipment', 'icon' => 'plus-circle', 'href' => dotship_path('book.php')],
        ['key' => 'shipments', 'label' => 'My Shipments', 'icon' => 'box-seam', 'href' => dotship_path('shipments.php')],
        ['key' => 'track', 'label' => 'Track', 'icon' => 'geo-alt', 'href' => dotship_path('track.php')],
        ['key' => 'logout', 'label' => 'Logout', 'icon' => 'box-arrow-right', 'href' => dotship_path('logout.php')],
    ];
}

function dotship_render_app_shell_start(string $title, string $active = 'dashboard', string $role = 'user'): void
{
    $user = dotship_current_user();
    $navItems = dotship_app_nav_items($role);
    $initial = strtoupper(substr((string) ($user['name'] ?? 'D'), 0, 1));

    echo '<div class="dashboard-shell">';
    echo '<aside class="sidebar p-4">';
    echo '<div class="d-flex align-items-center justify-content-between mb-4">';
    echo '<a href="' . ($role === 'admin' ? dotship_path('admin/index.php') : dotship_path('dashboard.php')) . '" class="brand-mark sidebar-brand"><span class="brand-icon"><i class="bi bi-truck-front-fill"></i></span><span class="brand-text">DOT SHIP</span></a>';
    echo '<span class="badge rounded-pill text-bg-light text-dark">' . dotship_escape($role === 'admin' ? 'Admin' : 'User') . '</span>';
    echo '</div>';
    echo '<div class="glass-card bg-white bg-opacity-10 border-0 text-white p-3 mb-4">';
    echo '<div class="d-flex align-items-center gap-3">';
    echo '<div class="stat-icon bg-white text-dark fw-bold">' . dotship_escape($initial) . '</div>';
    echo '<div><div class="fw-semibold">' . dotship_escape((string) ($user['name'] ?? 'Member')) . '</div><div class="small text-white-50">' . dotship_escape((string) ($user['email'] ?? '')) . '</div></div>';
    echo '</div>';
    echo '</div>';
    echo '<nav class="nav flex-column gap-1">';
    foreach ($navItems as $item) {
        $activeClass = $active === $item['key'] ? ' active' : '';
        echo '<a class="nav-link' . $activeClass . '" href="' . $item['href'] . '"><i class="bi bi-' . $item['icon'] . ' me-2"></i>' . dotship_escape($item['label']) . '</a>';
    }
    echo '</nav>';
    echo '<div class="mt-4 pt-4 border-top border-white border-opacity-10 small text-white-50">Fast logistics control center built for the DOT SHIP project.</div>';
    echo '</aside>';
    echo '<div class="dashboard-main">';
    echo '<header class="topbar sticky-top py-3">';
    echo '<div class="container-fluid px-4">';
    echo '<div class="d-flex align-items-center justify-content-between gap-3">';
    echo '<div><h4 class="mb-1 fw-bold text-navy">' . dotship_escape($title) . '</h4><div class="small-muted">' . dotship_escape(dotship_config()['app_tagline']) . '</div></div>';
    echo '<div class="d-flex align-items-center gap-2">';
    echo '<button class="btn btn-light shadow-soft d-lg-none" data-sidebar-toggle><i class="bi bi-list"></i></button>';
    echo '<a class="btn btn-outline-dark d-none d-md-inline-flex" href="' . ($role === 'admin' ? dotship_path('admin/index.php') : dotship_path('dashboard.php')) . '"><i class="bi bi-speedometer2 me-2"></i>Refresh</a>';
    echo '<a class="btn btn-primary-gradient btn-ripple" href="' . dotship_path('logout.php') . '"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</header>';
    echo '<main class="content-pad">';
}

function dotship_render_app_shell_end(): void
{
    echo '</main></div></div>';
}

function dotship_render_flash(): void
{
    $flash = dotship_flash('');
    if (!$flash) {
        return;
    }

    $type = dotship_escape($flash['type'] ?? 'info');
    $message = dotship_escape($flash['message'] ?? '');

    echo '<div class="container mt-4"><div class="alert alert-' . $type . ' shadow-soft alert-dismissible fade show" role="alert">' . $message . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>';
}

function dotship_render_assets(): void
{
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>';
    echo '<script src="' . dotship_asset('assets/js/app.js') . '"></script>';
}

function dotship_notify(array $shipment, string $type = 'status_update', string $message = ''): void
{
    try {
        $payload = [
            'shipment_id' => $shipment['_id'] ?? null,
            'tracking_id' => $shipment['tracking_id'] ?? null,
            'type' => $type,
            'message' => $message,
            'created_at' => dotship_now(),
        ];

        dotship_collection('notifications')->insertOne($payload);

        // also append to a local log for easy debugging
        $logLine = date('c') . ' | ' . ($payload['tracking_id'] ?? '(no-id)') . ' | ' . $type . ' | ' . $message . PHP_EOL;
        @file_put_contents(dirname(__DIR__) . '/storage/notifications.log', $logLine, FILE_APPEND | LOCK_EX);
    } catch (Throwable) {
        // silently ignore notification failures
    }
}

function dotship_generate_otp(int $length = 6): string
{
    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;
    return (string) random_int($min, $max);
}

function dotship_create_otp(string $trackingId, string $contact, string $method = 'email', int $ttlSeconds = 300): array
{
    $code = dotship_generate_otp();
    $hashed = password_hash($code, PASSWORD_DEFAULT);
    $now = dotship_now();
    $expires = new MongoDB\BSON\UTCDateTime((int) round((microtime(true) + $ttlSeconds) * 1000));

    $doc = [
        'tracking_id' => $trackingId,
        'contact' => $contact,
        'method' => $method,
        'code_hash' => $hashed,
        'used' => false,
        'created_at' => $now,
        'expires_at' => $expires,
    ];

    try {
        dotship_collection('otps')->insertOne($doc);
    } catch (Throwable) {
        // ignore
    }

    return ['code' => $code, 'doc' => $doc];
}

function dotship_send_otp(string $contact, string $code, string $method = 'email', string $trackingId = ''): bool
{
    $message = "Your DOT SHIP verification code is: $code";

    // Try to use mail() for email; otherwise fall back to logging
    if ($method === 'email' && filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        $subject = 'DOT SHIP verification code';
        $headers = "From: no-reply@dotship.local\r\n";
        // suppress warnings
        @$sent = mail($contact, $subject, $message, $headers);
        if ($sent) {
            return true;
        }
    }

    // For SMS or fallback, write to notifications log and notifications collection
    try {
        dotship_collection('notifications')->insertOne([
            'tracking_id' => $trackingId,
            'to' => $contact,
            'method' => $method,
            'type' => 'otp_sent',
            'message' => $message,
            'created_at' => dotship_now(),
        ]);
    } catch (Throwable) {
        // ignore
    }

    @file_put_contents(dirname(__DIR__) . '/storage/notifications.log', date('c') . " | OTP to $contact | $message\n", FILE_APPEND | LOCK_EX);
    return true;
}

function dotship_verify_otp(string $trackingId, string $code): bool
{
    $cursor = dotship_collection('otps')->find(['tracking_id' => $trackingId, 'used' => false], ['sort' => ['created_at' => -1], 'limit' => 5]);

    foreach ($cursor as $docObj) {
        $doc = $docObj->getArrayCopy();
        $expires = $doc['expires_at'] ?? null;
        if ($expires instanceof MongoDB\BSON\UTCDateTime) {
            $expiresMs = $expires->toDateTime()->format('U') * 1000 + (int) (($expires->toDateTime()->format('u')) / 1000);
            if ($expiresMs < (int) round(microtime(true) * 1000)) {
                continue;
            }
        }

        if (isset($doc['code_hash']) && password_verify($code, (string) $doc['code_hash'])) {
            try {
                dotship_collection('otps')->updateOne(['_id' => $doc['_id']], ['$set' => ['used' => true, 'used_at' => dotship_now()]]);
            } catch (Throwable) {
            }
            return true;
        }
    }

    return false;
}

function dotship_render_flash_toast(string $title = 'DOT SHIP'): void
{
    $flash = dotship_flash('');
    if (!$flash) {
        return;
    }

    $payload = [
        'type' => $flash['type'] ?? 'success',
        'title' => $title,
        'message' => $flash['message'] ?? '',
    ];

    echo '<div data-flash-toast="' . dotship_escape(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"></div>';
}

function dotship_render_footer(): void
{
    $year = date('Y');
    echo '<footer class="site-footer mt-auto">';
    echo '<div class="container py-4 py-lg-5">';
    echo '<div class="row g-4 align-items-start">';
    echo '<div class="col-lg-4">';
    echo '<a href="' . dotship_path('index.php') . '" class="brand-mark brand-mark-footer mb-3 d-inline-flex"><span class="brand-icon"><i class="bi bi-truck-front-fill"></i></span><span class="brand-text">DOT SHIP</span></a>';
    echo '<p class="text-white-50 mb-0">Fast. Smart. Reliable. Premium courier and parcel management built for modern logistics workflows.</p>';
    echo '</div>';
    echo '<div class="col-lg-2 col-6"><h6 class="footer-title">Quick Links</h6><ul class="footer-links">';
    echo '<li><a href="' . dotship_path('track.php') . '">Track</a></li><li><a href="' . dotship_path('login.php') . '">Login</a></li><li><a href="' . dotship_path('register.php') . '">Register</a></li>';
    echo '</ul></div>';
    echo '<div class="col-lg-3 col-6"><h6 class="footer-title">Contact</h6><ul class="footer-links">';
    echo '<li><a href="mailto:support@dotship.local">support@dotship.local</a></li><li><a href="tel:+919000000001">+91 90000 00001</a></li><li><span>India</span></li>';
    echo '</ul></div>';
    echo '<div class="col-lg-3"><h6 class="footer-title">Follow</h6><div class="social-links">';
    echo '<a href="#"><i class="bi bi-linkedin"></i></a><a href="#"><i class="bi bi-instagram"></i></a><a href="#"><i class="bi bi-twitter-x"></i></a>';
    echo '</div></div>';
    echo '</div><hr class="footer-divider"><div class="d-flex flex-column flex-md-row justify-content-between gap-2 small text-white-50"><span>© ' . $year . ' DOT SHIP</span><span>College minor project with startup-grade UI.</span></div>';
    echo '</div></footer>';
    dotship_render_assets();
    echo '</body></html>';
}
