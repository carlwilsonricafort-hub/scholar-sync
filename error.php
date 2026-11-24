<?php
require_once __DIR__ . '/includes/config.php';

$allowedCodes = [400, 403, 404, 500];
$code         = (int)($_GET['code'] ?? 500);
$code         = in_array($code, $allowedCodes) ? $code : 500;

$msg = htmlspecialchars(strip_tags($_GET['msg'] ?? ''));

$rawRetry = $_GET['retry'] ?? '';
$retry    = '';
if ($rawRetry) {
    $decoded = urldecode($rawRetry);
    if (!preg_match('/^(https?:|javascript:|\/\/)/i', $decoded)) {
        $retry = htmlspecialchars($decoded);
    }
}

$titles = [
    400 => '400 — Bad Request',
    403 => '403 — Access Denied',
    404 => '404 — Not Found',
    500 => '500 — Server Error',
];

$descriptions = [
    400 => 'The request could not be understood.',
    403 => 'You do not have permission to access this page.',
    404 => 'The page or record you are looking for does not exist.',
    500 => 'Something went wrong on our end. Please try again.',
];

http_response_code($code);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titles[$code] ?> — Student Payment Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="error-page">
    <div class="error-page__code"><?= $code ?></div>
    <div class="error-page__title"><?= $titles[$code] ?></div>
    <div class="error-page__msg"><?= $msg ?: $descriptions[$code] ?></div>
    <div class="error-page__actions">
        <?php if ($retry): ?>
            <a href="<?= $retry ?>" class="btn btn--primary">Try Again</a>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn--outline">Back to Dashboard</a>
    </div>
</div>

</body>
</html>