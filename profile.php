<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/curl_helper.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$userId  = (int)$_SESSION['user']['db_id'];
$apiBase = API_BASE . '/users/' . $userId;

$profileSuccess = $profileError = '';
$passSuccess    = $passError    = '';

/* ── Handle profile update ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fn     = trim(filter_input(INPUT_POST, 'first_name',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $ln     = trim(filter_input(INPUT_POST, 'last_name',      FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $nick   = trim(filter_input(INPUT_POST, 'nickname',       FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $phone  = trim(filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $sex    = trim($_POST['sex']         ?? '');
    $bdate  = trim($_POST['birth_date']  ?? '');
    $bplace = trim(filter_input(INPUT_POST, 'birth_place',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $addr   = trim(filter_input(INPUT_POST, 'address',        FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    if (!$fn || !$ln) {
        $profileError = 'First name and last name are required.';
    } else {
        $result = curlRequest($apiBase, 'PUT', [
            'first_name'     => $fn,
            'last_name'      => $ln,
            'nickname'       => $nick,
            'contact_number' => $phone,
            'sex'            => $sex,
            'birth_date'     => $bdate,
            'birth_place'    => $bplace,
            'address'        => $addr,
        ]);

        if ($result['success']) {
            $_SESSION['user']['first_name'] = $fn;
            $_SESSION['user']['last_name']  = $ln;
            header('Location: profile.php?updated=1');
            exit();
        } else {
            $profileError = $result['message'] ?: 'Could not update profile.';
        }
    }
}

if (isset($_GET['updated'])) {
    $profileSuccess = 'Profile updated successfully.';
}

/* ── Handle password change ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$newPass || !$confirm) {
        $passError = 'All password fields are required.';
    } elseif ($newPass !== $confirm) {
        $passError = 'New password and confirm password do not match.';
    } elseif (strlen($newPass) < 8) {
        $passError = 'New password must be at least 8 characters.';
    } elseif ($current === $newPass) {
        $passError = 'New password must be different from current password.';
    } else {
        $result = curlRequest($apiBase . '/password', 'PUT', [
            'current_password' => $current,
            'new_password'     => $newPass,
        ]);

        if ($result['success']) {
            $passSuccess = 'Password changed successfully.';
        } else {
            $passError = match((int)$result['httpCode']) {
                401     => 'Current password is incorrect.',
                400     => $result['message'],
                default => $result['message'] ?: 'Could not change password.',
            };
        }
    }
}

/* ── Handle set password (Google users) ──────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_password'])) {
    $newPass = $_POST['set_password'] ?? '';
    $confirm = $_POST['set_confirm']  ?? '';

    if (!$newPass || !$confirm) {
        $passError = 'Both password fields are required.';
    } elseif ($newPass !== $confirm) {
        $passError = 'Passwords do not match.';
    } elseif (strlen($newPass) < 8) {
        $passError = 'Password must be at least 8 characters.';
    } else {
        $result      = curlRequest($apiBase . '/password', 'PUT', [
            'new_password' => $newPass,
            'set_new'      => true,
        ]);
        $passSuccess = $result['success']
            ? 'Password set! You can now log in with email and password.'
            : ($result['message'] ?: 'Could not set password.');
        if (!$result['success']) $passError = $passSuccess;
    }
}

/* ── Fetch current profile from API ──────────────────────────── */
$profileResult = curlRequest($apiBase);
$user          = $profileResult['success'] ? ($profileResult['raw']['data'] ?? []) : [];
$hasPassword   = $user['has_password'] ?? false;

/** Return a field value safely escaped for use in HTML attributes / text. */
function val(array $user, string $key): string
{
    return htmlspecialchars($user[$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — Student Payment Portal</title>
    <link rel="stylesheet" href="assets/css/loader.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ── Full-page loading overlay ──────────────────────────────── -->
<div id="loading-overlay">
    <div class="loader-spinner"></div>
    <span id="loading-label" class="loader-label">Please wait...</span>
</div>

<header class="site-header">
    <div class="site-header__inner">
        <span class="site-header__title">Student Payment Portal</span>
        <nav>
            <ul class="site-header__nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><span class="sep">|</span></li>
                <li><a href="profile.php" class="active">Profile</a></li>
                <li><span class="sep">|</span></li>
                <li><a href="logout.php" id="logout-link">Sign Out</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="page-wrapper">

    <!-- ── Personal Information ───────────────────────────────── -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">Personal Information</div>
        </div>
        <div class="card__body">

            <?php if ($profileSuccess): ?>
            <div class="alert alert--success"><?= htmlspecialchars($profileSuccess) ?></div>
            <?php endif; ?>
            <?php if ($profileError): ?>
            <div class="alert alert--error"><?= htmlspecialchars($profileError) ?></div>
            <?php endif; ?>

            <form id="profile-form" method="POST" novalidate>
                <input type="hidden" name="update_profile" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name *</label>
                        <input class="form-control" type="text" id="first_name" name="first_name"
                               value="<?= val($user, 'first_name') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name *</label>
                        <input class="form-control" type="text" id="last_name" name="last_name"
                               value="<?= val($user, 'last_name') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="nickname">Nickname</label>
                        <input class="form-control" type="text" id="nickname" name="nickname"
                               value="<?= val($user, 'nickname') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_number">Contact Number</label>
                        <input class="form-control" type="tel" id="contact_number" name="contact_number"
                               value="<?= val($user, 'contact_number') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="sex">Sex</label>
                        <select class="form-control" id="sex" name="sex">
                            <option value="">-- Select --</option>
                            <option value="M" <?= ($user['sex'] ?? '') === 'M' ? 'selected' : '' ?>>Male</option>
                            <option value="F" <?= ($user['sex'] ?? '') === 'F' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="birth_date">Birth Date</label>
                        <input class="form-control" type="date" id="birth_date" name="birth_date"
                               value="<?= val($user, 'birth_date') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="birth_place">Birth Place</label>
                    <input class="form-control" type="text" id="birth_place" name="birth_place"
                           value="<?= val($user, 'birth_place') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Address</label>
                    <textarea class="form-control" id="address" name="address"><?= val($user, 'address') ?></textarea>
                </div>

                <button type="submit" class="btn btn--primary">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- ── Password Section ───────────────────────────────────── -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">
                <?= $hasPassword ? 'Change Password' : 'Set Password' ?>
            </div>
        </div>
        <div class="card__body">

            <?php if ($passSuccess): ?>
            <div class="alert alert--success"><?= htmlspecialchars($passSuccess) ?></div>
            <?php endif; ?>
            <?php if ($passError): ?>
            <div class="alert alert--error"><?= htmlspecialchars($passError) ?></div>
            <?php endif; ?>

            <?php if (!$hasPassword): ?>

            <div class="alert alert--warning">
                Your account uses Google login. You can set a password to also enable email login.
            </div>

            <form id="set-password-form" method="POST" novalidate>
                <input type="hidden" name="set_password" value="1">

                <div class="form-row">
                    <div class="form-group mb-0">
                        <label class="form-label" for="set_password_field">
                            New Password
                            <span style="font-weight:400;text-transform:none;">(min. 8 characters)</span>
                        </label>
                        <input class="form-control" type="password" id="set_password_field"
                               name="set_password" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label" for="set_confirm">Confirm Password</label>
                        <input class="form-control" type="password" id="set_confirm"
                               name="set_confirm" required>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary mt-16">Set Password</button>
            </form>

            <?php else: ?>

            <form id="change-password-form" method="POST" novalidate>
                <input type="hidden" name="change_password" value="1">

                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input class="form-control" type="password" id="current_password"
                           name="current_password" required>
                </div>

                <div class="form-row">
                    <div class="form-group mb-0">
                        <label class="form-label" for="new_password">
                            New Password
                            <span style="font-weight:400;text-transform:none;">(min. 8 characters)</span>
                        </label>
                        <input class="form-control" type="password" id="new_password"
                               name="new_password" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input class="form-control" type="password" id="confirm_password"
                               name="confirm_password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary mt-16">Update Password</button>
            </form>

            <?php endif; ?>

        </div>
    </div>

</div><!-- /.page-wrapper -->

<footer class="site-footer">
    Student Payment Portal &copy; <?= date('Y') ?>
</footer>

<!-- Scripts: loader first, then validate -->
<script src="assets/js/loader.js"></script>
<script src="assets/js/validate.js"></script>
<script>
    // ── Profile form inline validation ─────────────────────────
    Validator.init('profile-form', [
        { field: 'first_name',     rules: ['required'],  label: 'First Name' },
        { field: 'last_name',      rules: ['required'],  label: 'Last Name' },
        { field: 'contact_number', rules: ['phone'],     label: 'Contact Number' },
        { field: 'birth_date',     rules: ['date'],      label: 'Birth Date' },
    ]);

    <?php if (!$hasPassword): ?>
    // ── Set-password form inline validation ────────────────────
    Validator.init('set-password-form', [
        { field: 'set_password', rules: ['required', 'min:8'],                    label: 'Password' },
        { field: 'set_confirm',  rules: ['required', 'match:#set_password_field'], label: 'Confirm Password' },
    ]);
    <?php else: ?>
    // ── Change-password form inline validation ─────────────────
    Validator.init('change-password-form', [
        { field: 'current_password', rules: ['required'],                        label: 'Current Password' },
        { field: 'new_password',     rules: ['required', 'min:8'],               label: 'New Password' },
        { field: 'confirm_password', rules: ['required', 'match:#new_password'], label: 'Confirm Password' },
    ]);
    <?php endif; ?>

    // Show loader on form submits
    Loader.attachToForms({
        'profile-form'         : 'Saving profile...',
        'change-password-form' : 'Updating password...',
        'set-password-form'    : 'Setting password...',
    });

    // Show loader on sign-out
    const logoutLink = document.getElementById('logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', () => Loader.show('Signing out...'));
    }
</script>
</body>
</html>