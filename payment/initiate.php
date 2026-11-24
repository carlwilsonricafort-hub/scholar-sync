<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/curl_helper.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$userId    = (int)$_SESSION['user']['db_id'];
$paymentId = (int)($_GET['payment_id'] ?? 0);

if (!$paymentId) {
    header('Location: ../dashboard.php');
    exit();
}

// ── Fetch the payment record ───────────────────────────────────────
$payResult = curlRequest(API_BASE . '/payments/' . $paymentId);

if (!$payResult['success']) {
    header('Location: ../error.php?code=404&msg=' . urlencode('Payment record not found.'));
    exit();
}

$payment = $payResult['raw']['data'];

// ── Security: make sure this payment belongs to the logged-in user ─
if ((int)$payment['user_id'] !== $userId) {
    header('Location: ../error.php?code=403&msg=' . urlencode('Access denied.'));
    exit();
}

// ── Prevent paying an already-paid due ────────────────────────────
if ($payment['payment_status'] === 'Paid') {
    header('Location: ../dashboard.php');
    exit();
}

// ── Get PayPal access token ────────────────────────────────────────
$accessToken = getPayPalAccessToken();

if (!$accessToken) {
    header('Location: ../error.php?code=500&msg=' . urlencode('Could not connect to PayPal. Please try again later.') . '&retry=' . urlencode('payment/initiate.php?payment_id=' . $paymentId));
    exit();
}

// ── Log: Initiated ─────────────────────────────────────────────────
curlRequest(API_BASE . '/payment-logs', 'POST', [
    'payment_id'   => $paymentId,
    'user_id'      => $userId,
    'action'       => 'Initiated',
    'status_before'=> $payment['payment_status'],
    'status_after' => 'Pending',
]);

// ── Update payment status to Pending (in case it was Failed) ──────
curlRequest(API_BASE . '/payments/' . $paymentId, 'PUT', [
    'payment_status' => 'Pending',
    'payment_date'   => date('Y-m-d H:i:s'),
]);

// ── Create PayPal Order ────────────────────────────────────────────
$order = createPayPalOrder($accessToken, (float)$payment['amount'], $payment['due_type'], $paymentId);

if (!$order || empty($order['approve_url'])) {
    header('Location: ../error.php?code=500&msg=' . urlencode('Could not create PayPal order. Please check your PayPal credentials and try again.') . '&retry=' . urlencode('payment/initiate.php?payment_id=' . $paymentId));
    exit();
}

// Store the PayPal order ID in the payment record for later capture
curlRequest(API_BASE . '/payments/' . $paymentId, 'PUT', [
    'paypal_payment_id' => $order['id'],
]);

// ── Log: Pending ───────────────────────────────────────────────────
curlRequest(API_BASE . '/payment-logs', 'POST', [
    'payment_id'      => $paymentId,
    'user_id'         => $userId,
    'action'          => 'Pending',
    'status_before'   => 'Pending',
    'status_after'    => 'Pending',
    'paypal_response' => json_encode(['id' => $order['id']]),
]);

// ── Redirect to PayPal approval page ─────────────────────────────
header('Location: ' . $order['approve_url']);
exit();