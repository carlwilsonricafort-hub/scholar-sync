<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/curl_helper.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit();
}

$registered = isset($_GET['registered']) ? 'Account created! Please log in.' : '';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = 'Email and password are required.';
    } else {
        $result = curlRequest(API_BASE . '/users/login', 'POST', [
            'email'    => $email,
            'password' => $pass,
        ]);

        if (!$result['success']) {
            $msg = $result['message'] ?? '';
            if (str_contains($msg, 'Google')) {
                $error = 'This account uses Google login. Please use the Google sign-in option.';
            } elseif ($result['httpCode'] === 429) {
                $error = '__LOCKOUT__';
            } elseif ($result['httpCode'] === 401) {
                $error = 'Incorrect password. Please try again.';
            } elseif ($result['httpCode'] === 404) {
                $error = 'No account found with that email address.';
            } else {
                $error = $msg ?: 'Login failed. Please try again.';
            }
        } else {
            $user = $result['raw']['data'];
            $_SESSION['user'] = [
                'db_id'      => $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email_address'],
                'picture'    => $user['image_url'] ?? '',
            ];
            session_regenerate_id(true);
            header('Location: dashboard.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Student Payment Portal</title>
    <link rel="stylesheet" href="assets/css/loader.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ── Full-page loading overlay ──────────────────────────────── -->
<div id="loading-overlay">
    <div class="loader-spinner"></div>
    <span id="loading-label" class="loader-label">Please wait...</span>
</div>

<div class="auth-wrapper">

    <div class="auth-card">
        <div class="auth-card__header">
            <div class="auth-card__title">Student Payment Portal</div>
            <div class="auth-card__subtitle">Log in to your student account</div>
        </div>

        <div class="auth-card__body">

            <?php if ($registered): ?>
            <div class="alert alert--success"><?= htmlspecialchars($registered) ?></div>
            <?php endif; ?>

            <?php if ($error === '__LOCKOUT__'): ?>
            <div class="alert alert--error" id="lockout-alert">
                Too many failed login attempts. Please wait
                <span class="lockout-timer-badge"><span id="lockout-timer">30</span>s</span>
                before trying again.
                <div class="lockout-progress-track">
                    <div class="lockout-progress-bar" id="lockout-progress" style="width:100%;"></div>
                </div>
            </div>
            <?php elseif ($error): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form id="login-form" method="POST" autocomplete="on" novalidate>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-control"
                           type="email"
                           id="email"
                           name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="you@example.com"
                           autocomplete="email"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control"
                           type="password"
                           id="password"
                           name="password"
                           placeholder="••••••••"
                           autocomplete="current-password"
                           required>
                </div>

                <button type="submit" class="btn btn--primary btn--block mt-8">
                    Log In as Student
                </button>

            </form>
        </div>

        <div class="auth-footer">
            No account yet? <a href="register.php">Create one</a>
        </div>
    </div>

</div>

<!-- Scripts: loader first, then validate, then lockout timer -->
<script src="assets/js/loader.js"></script>
<script src="assets/js/validate.js"></script>
<script src="assets/js/lockout-timer.js"></script>
<script>
    // Attach inline validation rules for the login form
    Validator.init('login-form', [
        { field: 'email',    rules: ['required', 'email'], label: 'Email' },
        { field: 'password', rules: ['required', 'min:8'], label: 'Password' },
    ]);

    // Show loader only if client-side validation passes
    Loader.attachToForms({ 'login-form': 'Signing in...' });
</script>
</body>
</html>