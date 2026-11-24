<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/curl_helper.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$userId    = (int)$_SESSION['user']['db_id'];
$paymentId = (int)($_GET['payment_id'] ?? 0);
$token     = $_GET['token']   ?? '';
$payerId   = $_GET['PayerID'] ?? '';

if (!$paymentId || !$token) {
    header('Location: ../dashboard.php');
    exit();
}

// ── Fetch payment record ──────────────────────────────────────────
$payResult = curlRequest(API_BASE . '/payments/' . $paymentId);

if (!$payResult['success']) {
    header('Location: ../error.php?code=404&msg=' . urlencode('Payment record not found.'));
    exit();
}

$payment = $payResult['raw']['data'];

// Security check
if ((int)$payment['user_id'] !== $userId) {
    header('Location: ../error.php?code=403&msg=' . urlencode('Access denied.'));
    exit();
}

// Prevent double-capture
if ($payment['payment_status'] === 'Paid') {
    header('Location: receipt.php?payment_id=' . $paymentId);
    exit();
}

// ── Get PayPal access token ────────────────────────────────────────
$accessToken = getPayPalAccessToken();

if (!$accessToken) {
    curlRequest(API_BASE . '/payments/' . $paymentId, 'PUT', ['payment_status' => 'Failed']);
    curlRequest(API_BASE . '/payment-logs', 'POST', [
        'payment_id'   => $paymentId,
        'user_id'      => $userId,
        'action'       => 'Failed',
        'status_before'=> 'Pending',
        'status_after' => 'Failed',
    ]);
    header('Location: ../error.php?code=500&msg=' . urlencode('Could not connect to PayPal to confirm payment.') . '&retry=' . urlencode('payment/initiate.php?payment_id=' . $paymentId));
    exit();
}

// ── Capture the PayPal Order ──────────────────────────────────────
$capture = capturePayPalOrder($accessToken, $token);
$now     = date('Y-m-d H:i:s');

if ($capture && isset($capture['status']) && $capture['status'] === 'COMPLETED') {
    $captureUnit   = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];
    $transactionId = $captureUnit['id'] ?? $token;

    curlRequest(API_BASE . '/payments/' . $paymentId, 'PUT', [
        'payment_status'    => 'Paid',
        'transaction_id'    => $transactionId,
        'paypal_payer_id'   => $payerId,
        'paypal_payment_id' => $token,
        'paid_date'         => $now,
        'payment_date'      => $now,
    ]);

    curlRequest(API_BASE . '/payment-logs', 'POST', [
        'payment_id'      => $paymentId,
        'user_id'         => $userId,
        'action'          => 'Completed',
        'status_before'   => 'Pending',
        'status_after'    => 'Paid',
        'paypal_response' => json_encode([
            'id'     => $capture['id']     ?? '',
            'status' => $capture['status'] ?? '',
        ]),
    ]);

    header('Location: receipt.php?payment_id=' . $paymentId . '&success=1');
    exit();

} else {
    curlRequest(API_BASE . '/payments/' . $paymentId, 'PUT', ['payment_status' => 'Failed']);
    curlRequest(API_BASE . '/payment-logs', 'POST', [
        'payment_id'      => $paymentId,
        'user_id'         => $userId,
        'action'          => 'Failed',
        'status_before'   => 'Pending',
        'status_after'    => 'Failed',
        'paypal_response' => json_encode($capture ?? ['error' => 'capture failed']),
    ]);

    // Redirect to dashboard with retry option handled there via ?payment_error=1&payment_id=X
    header('Location: ../dashboard.php?payment_error=1&payment_id=' . $paymentId);
    exit();
}