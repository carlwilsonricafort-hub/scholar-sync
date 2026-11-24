<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/curl_helper.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$sessionUser = $_SESSION['user'];
$userId      = (int)$sessionUser['db_id'];

$profileResult = curlRequest(API_BASE . '/users/' . $userId);
$user          = $profileResult['success'] ? ($profileResult['raw']['data'] ?? null) : null;
$profileError  = $profileResult['success'] ? null : ($profileResult['message'] ?? 'Could not fetch profile.');

$paymentsResult = curlRequest(API_BASE . '/payments?user_id=' . $userId);
$payments       = $paymentsResult['success'] ? ($paymentsResult['raw']['data'] ?? []) : [];

$paymentMap = [];
foreach ($payments as $p) {
    $paymentMap[$p['due_type']] = $p;
}

$displayName  = ($user['first_name'] ?? $sessionUser['first_name']) . ' ' . ($user['last_name'] ?? $sessionUser['last_name']);
$displayEmail = $user['email_address'] ?? $sessionUser['email'];
$profilePic   = $user['image_url']     ?? $sessionUser['picture'] ?? '';
$initials     = strtoupper(substr($user['first_name'] ?? $sessionUser['first_name'], 0, 1));

/**
 * Returns a colored badge for payment status (Paid, Pending, Failed, Refunded).
 */
function statusBadge(string $status): string
{
    $map = [
        'Paid'     => 'badge--paid',
        'Pending'  => 'badge--pending',
        'Failed'   => 'badge--failed',
        'Refunded' => 'badge--refunded',
    ];
    $cls = $map[$status] ?? '';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Returns a colored badge for payment log action types
 * (Initiated, Pending, Completed, Failed, Cancelled, Refunded).
 */
function actionBadge(string $action): string
{
    $map = [
        'Initiated' => 'badge--pending',
        'Pending'   => 'badge--pending',
        'Completed' => 'badge--paid',
        'Failed'    => 'badge--failed',
        'Cancelled' => 'badge--refunded',
        'Refunded'  => 'badge--refunded',
    ];
    $cls = $map[$action] ?? '';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars(strtoupper($action)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Student Payment Portal</title>
    <link rel="stylesheet" href="assets/css/loader.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ── Full-page loading overlay ──────────────────────────────── -->
<div id="loading-overlay">
    <div class="loader-spinner"></div>
    <span id="loading-label" class="loader-label">Please wait...</span>
</div>

<!-- ── Header Nav ─────────────────────────────────────────────── -->
<header class="site-header">
    <div class="site-header__inner">
        <span class="site-header__title">Student Payment Portal</span>
        <nav>
            <ul class="site-header__nav">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><span class="sep">|</span></li>
                <li><a href="profile.php">Profile</a></li>
                <li><span class="sep">|</span></li>
                <li><a href="logout.php" id="logout-link">Sign Out</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="page-wrapper">

    <?php if ($profileError): ?>
    <div class="alert alert--error"><?= htmlspecialchars($profileError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['payment_error'])): ?>
    <?php $retryId = (int)($_GET['payment_id'] ?? 0); ?>
    <div class="alert alert--error">
        <strong>Payment failed.</strong> Your payment could not be completed.
        <?php if ($retryId): ?>
            <a href="payment/initiate.php?payment_id=<?= $retryId ?>">Try Again</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Student Info Card ───────────────────────────────────── -->
    <div class="student-card">
        <?php if ($profilePic): ?>
        <img class="student-card__avatar"
             src="<?= htmlspecialchars($profilePic) ?>"
             alt="Profile Picture">
        <?php else: ?>
        <div class="student-card__avatar-placeholder"><?= $initials ?></div>
        <?php endif; ?>

        <div class="student-card__info">
            <div class="student-card__name"><?= htmlspecialchars(strtoupper($displayName)) ?></div>
            <div class="student-card__meta">
                <div class="student-card__meta-item">
                    <span class="student-card__meta-label">Email</span>
                    <span class="student-card__meta-value"><?= htmlspecialchars($displayEmail) ?></span>
                </div>
                <?php if (!empty($user['contact_number'])): ?>
                <div class="student-card__meta-item">
                    <span class="student-card__meta-label">Contact</span>
                    <span class="student-card__meta-value"><?= htmlspecialchars($user['contact_number']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($user['nickname'])): ?>
                <div class="student-card__meta-item">
                    <span class="student-card__meta-label">Nickname</span>
                    <span class="student-card__meta-value"><?= htmlspecialchars($user['nickname']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <a href="profile.php" class="btn btn--outline btn--sm nav-link" data-label="Loading profile...">
                Edit Profile
            </a>
        </div>
    </div>

    <!-- ── Payment Dues ───────────────────────────────────────── -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">Payment Dues</div>
        </div>

        <?php if (empty($payments)): ?>
        <div style="padding:20px;">
            <div class="alert alert--warning mb-0">No payment records found. Please contact the administrator.</div>
        </div>
        <?php else: ?>

        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Due Type</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date Paid</th>
                        <th>Transaction ID</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (['Prelim', 'Midterm', 'Finals'] as $dueType):
                    $p = $paymentMap[$dueType] ?? null;
                    if (!$p): ?>
                    <tr>
                        <td><strong><?= $dueType ?></strong></td>
                        <td colspan="5" style="color:var(--gray-400);">No record</td>
                        <td>—</td>
                    </tr>
                    <?php continue; endif;

                    $formattedAmount = '₱' . number_format((float)$p['amount'], 2);
                    $initiateUrl     = 'payment/initiate.php?payment_id=' . (int)$p['id'];
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['due_type']) ?></strong></td>
                        <td><?= htmlspecialchars($p['due_date']) ?></td>
                        <td><?= $formattedAmount ?></td>
                        <td><?= statusBadge($p['payment_status']) ?></td>
                        <td><?= $p['paid_date'] ? htmlspecialchars($p['paid_date']) : '—' ?></td>
                        <td style="font-size:.78rem;color:var(--gray-500);">
                            <?= $p['transaction_id'] ? htmlspecialchars($p['transaction_id']) : '—' ?>
                        </td>
                        <td>
                            <?php if (in_array($p['payment_status'], ['Pending', 'Failed'])): ?>
                                <a href="<?= $initiateUrl ?>"
                                   class="pay-link"
                                   data-href="<?= $initiateUrl ?>"
                                   data-due-type="<?= htmlspecialchars($p['due_type']) ?>"
                                   data-amount="<?= $formattedAmount ?>"
                                   data-label="Redirecting to PayPal...">
                                    <button class="btn btn--primary btn--sm">Pay Now</button>
                                </a>
                            <?php elseif ($p['payment_status'] === 'Paid'): ?>
                                <a href="payment/receipt.php?payment_id=<?= (int)$p['id'] ?>"
                                   class="nav-link" data-label="Loading receipt...">
                                    <button class="btn btn--outline btn--sm">Receipt</button>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $totalDue  = array_sum(array_column($payments, 'amount'));
        $totalPaid = array_sum(
            array_map(fn($p) => $p['payment_status'] === 'Paid' ? (float)$p['amount'] : 0, $payments)
        );
        $balance = $totalDue - $totalPaid;
        ?>

        <div class="summary-bar">
            <div class="summary-bar__item">
                <div class="summary-bar__label">Total Due</div>
                <div class="summary-bar__value">₱<?= number_format($totalDue, 2) ?></div>
            </div>
            <div class="summary-bar__item">
                <div class="summary-bar__label">Total Paid</div>
                <div class="summary-bar__value">₱<?= number_format($totalPaid, 2) ?></div>
            </div>
            <div class="summary-bar__item">
                <div class="summary-bar__label">Balance</div>
                <div class="summary-bar__value summary-bar__value--balance">₱<?= number_format($balance, 2) ?></div>
            </div>
        </div>

        <div class="disclaimer" style="padding:12px 20px;">
            <strong>DISCLAIMER:</strong> Amounts presented above may not be accurate. Kindly contact the
            <strong>Business and Finance Office</strong> if they are not accurate.
        </div>

        <?php endif; ?>
    </div>

    <!-- ── Payment History ────────────────────────────────────── -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">Payment History</div>
        </div>

        <?php
        $logsResult = curlRequest(API_BASE . '/payment-logs?user_id=' . $userId);
        $logs       = $logsResult['success'] ? ($logsResult['raw']['data'] ?? []) : [];
        ?>

        <?php if (empty($logs)): ?>
        <div style="padding:20px;">
            <p style="color:var(--gray-400);font-size:.88rem;margin:0;">No payment history yet.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Due Type</th>
                        <th>Action</th>
                        <th>Status Before</th>
                        <th>Status After</th>
                        <th>PayPal Response</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log):
                    // Find which due type this log entry belongs to
                    $dueLabel = '—';
                    foreach ($payments as $p) {
                        if ((int)$p['id'] === (int)$log['payment_id']) {
                            $dueLabel = $p['due_type'];
                            break;
                        }
                    }
                    // Resolve paypal_response:
                    // - Plain string (e.g. "User cancelled PayPal payment.") → show as-is
                    // - JSON with 'id' key → show the PayPal order/capture ID
                    $ppDisplay = '—';
                    if (!empty($log['paypal_response'])) {
                        $decoded = json_decode($log['paypal_response'], true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                            $ppDisplay = $decoded['id'];
                        } else {
                            $ppDisplay = $log['paypal_response'];
                        }
                    }
                ?>
                <tr>
                    <td style="font-size:.78rem;"><?= htmlspecialchars($log['created_at']) ?></td>
                    <td><?= htmlspecialchars($dueLabel) ?></td>
                    <td><?= actionBadge($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['status_before'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($log['status_after']  ?? '—') ?></td>
                    <td style="font-size:.78rem;color:var(--gray-500);">
                        <?= htmlspecialchars($ppDisplay) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.page-wrapper -->

<footer class="site-footer">
    Student Payment Portal &copy; <?= date('Y') ?>
</footer>

<!-- Scripts: loader first, then modal (depends on Loader) -->
<script src="assets/js/loader.js"></script>
<script src="assets/js/modal.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    // Initialise payment confirmation modal
    PayModal.init();

    // Show loader on standard nav links
    document.querySelectorAll('a.nav-link').forEach(link => {
        link.addEventListener('click', function () {
            Loader.show(this.dataset.label || 'Please wait...');
        });
    });

    // Show loader on sign-out
    const logoutLink = document.getElementById('logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', () => Loader.show('Signing out...'));
    }

});
</script>
</body>
</html>