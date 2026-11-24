<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/curl_helper.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fn     = trim(filter_input(INPUT_POST, 'first_name',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $ln     = trim(filter_input(INPUT_POST, 'last_name',      FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email  = trim(filter_input(INPUT_POST, 'email_address',  FILTER_SANITIZE_EMAIL)         ?? '');
    $pass   = $_POST['password']         ?? '';
    $conf   = $_POST['confirm_password'] ?? '';
    $nick   = trim(filter_input(INPUT_POST, 'nickname',       FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $phone  = trim(filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $sex    = trim($_POST['sex']         ?? '');
    $bdate  = trim($_POST['birth_date']  ?? '');
    $bplace = trim(filter_input(INPUT_POST, 'birth_place',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $addr   = trim(filter_input(INPUT_POST, 'address',        FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    // Server-side validation (defence in depth — client-side already ran)
    if (!$fn || !$ln || !$email || !$pass || !$conf) {
        $error = 'First name, last name, email, and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address format.';
    } elseif ($pass !== $conf) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($phone && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $error = 'Invalid phone number format.';
    } elseif ($sex && !in_array($sex, ['M', 'F'])) {
        $error = 'Invalid sex value.';
    } else {
        // Hash password before sending to API
        $hashedPass = password_hash($pass, PASSWORD_BCRYPT);

        $payload = [
            'first_name'     => $fn,
            'last_name'      => $ln,
            'email_address'  => $email,
            'password'       => $hashedPass,
            'nickname'       => $nick    ?: null,
            'contact_number' => $phone   ?: null,
            'sex'            => $sex     ?: null,
            'birth_date'     => $bdate   ?: null,
            'birth_place'    => $bplace  ?: null,
            'address'        => $addr    ?: null,
        ];

        $result = curlRequest(API_BASE . '/users', 'POST', $payload);

        if ($result['success'] && $result['httpCode'] === 201) {
            $newUserId = $result['raw']['id'] ?? null;

            // Auto-create Prelim / Midterm / Finals payment records for the new user
            if ($newUserId) {
                foreach (DUES as $dueType => $due) {
                    curlRequest(API_BASE . '/payments', 'POST', [
                        'user_id'  => $newUserId,
                        'due_type' => $dueType,
                        'amount'   => $due['amount'],
                        'due_date' => $due['due_date'],
                    ]);
                }
            }

            $success = 'Registration successful! Click here to log in.';

        } else {
            $error = $result['message'] ?: 'Registration failed. Please try again.';
        }
    }
}

/** Return old POST value safely escaped for HTML output. */
function old(string $key): string
{
    return htmlspecialchars($_POST[$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — Student Payment Portal</title>
    <link rel="stylesheet" href="assets/css/loader.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Success link inside alert */
        .alert--success-link {
            display: block;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 600;
            color: #065f46;
        }
        .alert--success-link:hover {
            text-decoration: underline;
            color: #065f46;
        }
    </style>
</head>
<body>

<!-- ── Full-page loading overlay ──────────────────────────────── -->
<div id="loading-overlay">
    <div class="loader-spinner"></div>
    <span id="loading-label" class="loader-label">Please wait...</span>
</div>

<div class="auth-wrapper">

    <div class="auth-card auth-card--wide">
        <div class="auth-card__header">
            <div class="auth-card__title">
                <?= $success ? 'Student Portal' : 'Create Account' ?>
            </div>
            <div class="auth-card__subtitle">
                <?= $success ? 'Create your account' : 'Student Payment Portal' ?>
            </div>
        </div>

        <div class="auth-card__body">

            <?php if ($success): ?>

            <!-- ── SUCCESS STATE ──────────────────────────────── -->
            <div class="alert alert--success" style="text-align:center; padding: 16px 14px;">
                <a href="login.php" class="alert--success-link">
                    ✓ <?= htmlspecialchars($success) ?>
                </a>
            </div>

            <?php else: ?>

            <?php if ($error): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!--
                novalidate disables the browser's native bubbles so our
                custom Validator messages show instead.
            -->
            <form id="register-form" method="POST" autocomplete="on" novalidate>

                <fieldset class="form-section">
                    <legend class="form-section__title">Personal Information</legend>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name *</label>
                            <input class="form-control" type="text" id="first_name" name="first_name"
                                   value="<?= old('first_name') ?>" autocomplete="given-name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name *</label>
                            <input class="form-control" type="text" id="last_name" name="last_name"
                                   value="<?= old('last_name') ?>" autocomplete="family-name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="nickname">Nickname</label>
                            <input class="form-control" type="text" id="nickname" name="nickname"
                                   value="<?= old('nickname') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="contact_number">Contact Number</label>
                            <input class="form-control" type="tel" id="contact_number" name="contact_number"
                                   placeholder="e.g. 09171234567" value="<?= old('contact_number') ?>"
                                   autocomplete="tel">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="sex">Sex</label>
                            <select class="form-control" id="sex" name="sex">
                                <option value="">-- Select --</option>
                                <option value="M" <?= old('sex') === 'M' ? 'selected' : '' ?>>Male</option>
                                <option value="F" <?= old('sex') === 'F' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="birth_date">Birth Date</label>
                            <input class="form-control" type="date" id="birth_date" name="birth_date"
                                   value="<?= old('birth_date') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="birth_place">Birth Place</label>
                        <input class="form-control" type="text" id="birth_place" name="birth_place"
                               value="<?= old('birth_place') ?>">
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control" id="address" name="address"><?= old('address') ?></textarea>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section__title">Account Credentials</legend>

                    <div class="form-group">
                        <label class="form-label" for="email_address">Email Address *</label>
                        <input class="form-control" type="email" id="email_address" name="email_address"
                               value="<?= old('email_address') ?>" autocomplete="email" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group mb-0">
                            <label class="form-label" for="password">
                                Password *
                                <span style="font-weight:400;text-transform:none;letter-spacing:0;">(min. 8 characters)</span>
                            </label>
                            <input class="form-control" type="password" id="password" name="password"
                                   autocomplete="new-password" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label" for="confirm_password">Confirm Password *</label>
                            <input class="form-control" type="password" id="confirm_password" name="confirm_password"
                                   autocomplete="new-password" required>
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn--primary btn--block">
                    Create Account
                </button>

            </form>

            <?php endif; ?>

        </div>

        <div class="auth-footer">
            <?php if ($success): ?>
                Already have an account? <a href="login.php">Log in here</a>
            <?php else: ?>
                Already have an account? <a href="login.php">Sign in</a>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Scripts: loader first, then validate -->
<script src="assets/js/loader.js"></script>
<script src="assets/js/validate.js"></script>
<?php if (!$success): ?>
<script>
    // Inline validation rules for the registration form
    Validator.init('register-form', [
        { field: 'first_name',      rules: ['required'],               label: 'First Name' },
        { field: 'last_name',       rules: ['required'],               label: 'Last Name' },
        { field: 'contact_number',  rules: ['phone'],                  label: 'Contact Number' },
        { field: 'birth_date',      rules: ['date'],                   label: 'Birth Date' },
        { field: 'email_address',   rules: ['required', 'email'],      label: 'Email' },
        { field: 'password',        rules: ['required', 'min:8'],      label: 'Password' },
        // match: selector must point to the password field's id
        { field: 'confirm_password', rules: ['required', 'match:#password'], label: 'Confirm Password' },
    ]);

    Loader.attachToForms({ 'register-form': 'Creating your account...' });
</script>
<?php endif; ?>
</body>
</html>