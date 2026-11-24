<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/curl_helper.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$userId    = (int)$_SESSION['user']['db_id'];
$paymentId = (int)($_GET['payment_id'] ?? 0);
$justPaid  = isset($_GET['success']);

if (!$paymentId) {
    header('Location: ../dashboard.php');
    exit();
}

$payResult = curlRequest(API_BASE . '/payments/' . $paymentId);

if (!$payResult['success']) {
    header('Location: ../error.php?code=404&msg=' . urlencode('Payment record not found.'));
    exit();
}

$payment = $payResult['raw']['data'];

if ((int)$payment['user_id'] !== $userId) {
    header('Location: ../error.php?code=403&msg=' . urlencode('Access denied.'));
    exit();
}

if ($payment['payment_status'] !== 'Paid') {
    header('Location: ../dashboard.php');
    exit();
}

$userResult = curlRequest(API_BASE . '/users/' . $userId);
$user       = $userResult['success'] ? ($userResult['raw']['data'] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt — Student Payment Portal</title>
    <link rel="stylesheet" href="../assets/css/loader.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div id="loading-overlay">
    <div class="loader-spinner"></div>
    <span id="loading-label" class="loader-label">Please wait...</span>
</div>

<header class="site-header no-print">
    <div class="site-header__inner">
        <span class="site-header__title">Student Payment Portal</span>
        <nav>
            <ul class="site-header__nav">
                <li><a href="../dashboard.php" class="nav-link" data-label="Loading dashboard...">Dashboard</a></li>
                <li><span class="sep">|</span></li>
                <li><a href="../logout.php" id="logout-link">Sign Out</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="page-wrapper">

    <?php if ($justPaid): ?>
    <div class="alert alert--success" style="max-width:600px;margin:0 auto 16px;">
        ✓ Payment Successful!
    </div>
    <?php endif; ?>

    <div class="receipt-card">
        <div class="receipt-card__header">
            <div class="receipt-card__portal-name">Student Payment Portal</div>
            <div class="receipt-card__title">Official Receipt</div>
        </div>

        <div class="receipt-card__body">

            <div style="margin-bottom:20px;">
                <div class="receipt-row">
                    <span class="receipt-row__label">Student Name</span>
                    <span class="receipt-row__value">
                        <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                    </span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row__label">Email</span>
                    <span class="receipt-row__value"><?= htmlspecialchars($user['email_address'] ?? '') ?></span>
                </div>
            </div>

            <div style="background:var(--gray-100);border-radius:var(--radius);padding:16px;margin-bottom:16px;">
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--gray-400);font-weight:700;margin-bottom:10px;">
                    Payment Details
                </div>
                <div class="receipt-row">
                    <span class="receipt-row__label">Due Type</span>
                    <span class="receipt-row__value"><?= htmlspecialchars($payment['due_type']) ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row__label">Amount Paid</span>
                    <span class="receipt-row__value" style="font-size:1.1rem;">
                        ₱<?= number_format((float)$payment['amount'], 2) ?>
                    </span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row__label">Status</span>
                    <span class="receipt-row__value receipt-row__value--paid">
                        <?= htmlspecialchars($payment['payment_status']) ?>
                    </span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row__label">Date Paid</span>
                    <span class="receipt-row__value"><?= htmlspecialchars($payment['paid_date'] ?? '—') ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row__label">Due Date</span>
                    <span class="receipt-row__value"><?= htmlspecialchars($payment['due_date']) ?></span>
                </div>
            </div>

            <div style="font-size:.78rem;color:var(--gray-400);">
                <div class="receipt-row">
                    <span class="receipt-row__label">Transaction ID</span>
                    <span class="receipt-row__value"><?= htmlspecialchars($payment['transaction_id'] ?? '—') ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row__label">PayPal Order ID</span>
                    <span class="receipt-row__value"><?= htmlspecialchars($payment['paypal_payment_id'] ?? '—') ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row__label">PayPal Payer ID</span>
                    <span class="receipt-row__value"><?= htmlspecialchars($payment['paypal_payer_id'] ?? '—') ?></span>
                </div>
            </div>

            <p style="font-size:.72rem;color:var(--gray-400);margin-top:16px;">
                Generated on: <?= date('Y-m-d H:i:s') ?>
            </p>

        </div>
    </div>

    <div style="max-width:600px;margin:16px auto;display:flex;gap:12px;" class="no-print">
        <a href="../dashboard.php" class="btn btn--outline nav-link" data-label="Loading dashboard...">
            ← Back to Dashboard
        </a>
        <button class="btn btn--primary" onclick="window.print()">
            🖨 Print Receipt
        </button>
    </div>

</div>

<footer class="site-footer no-print">
    Student Payment Portal &copy; <?= date('Y') ?>
</footer>

<script src="../assets/js/loader.js"></script>
<script>
    document.querySelectorAll('a.nav-link').forEach(link => {
        link.addEventListener('click', function () {
            Loader.show(this.dataset.label || 'Please wait...');
        });
    });
    const logoutLink = document.getElementById('logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', () => Loader.show('Signing out...'));
    }
</script>
</body>
</html>