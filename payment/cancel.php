<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/curl_helper.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$userId    = (int)$_SESSION['user']['db_id'];
$paymentId = (int)($_GET['payment_id'] ?? 0);

if ($paymentId) {
    $payResult = curlRequest(API_BASE . '/payments/' . $paymentId);
    $payment   = $payResult['success'] ? ($payResult['raw']['data'] ?? null) : null;

    if ($payment && (int)$payment['user_id'] === $userId && $payment['payment_status'] !== 'Paid') {
        // Treat user-initiated cancellation as Failed
        curlRequest(API_BASE . '/payments/' . $paymentId, 'PUT', [
            'payment_status' => 'Failed',
        ]);

        // Log the cancellation as a Failed transition
        curlRequest(API_BASE . '/payment-logs', 'POST', [
            'payment_id'      => $paymentId,
            'user_id'         => $userId,
            'action'          => 'Failed',
            'status_before'   => 'Pending',
            'status_after'    => 'Failed',
            'paypal_response' => 'User cancelled PayPal payment.',
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled — Student Payment Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="cancel-page">
    <div class="cancel-icon">✕</div>
    <div class="cancel-page__title">Payment Cancelled</div>
    <div class="cancel-page__msg">You cancelled the payment. No charges were made.</div>
    <div class="cancel-page__actions">
        <?php if ($paymentId): ?>
        <a href="initiate.php?payment_id=<?= $paymentId ?>" class="btn btn--primary">Try Again</a>
        <?php endif; ?>
        <a href="../dashboard.php" class="btn btn--outline">Back to Dashboard</a>
    </div>
</div>

</body>
</html>