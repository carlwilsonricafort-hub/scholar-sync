<?php
/**
 * PaymentLog Resource
 * Audit trail for every payment status transition.
 * Table: `payment_log`
 */
class PaymentLog
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // ── Public endpoints ──────────────────────────────────────────

    /** GET /api/payment-logs?user_id=X */
    public function getByUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payment_log WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $this->respond(200, ['success' => true, 'data' => $rows]);
    }

    /** POST /api/payment-logs */
    public function create(array $data): void
    {
        $required = ['payment_id', 'user_id', 'action'];
        foreach ($required as $f) {
            if (empty($data[$f])) {
                $this->respond(400, ['success' => false, 'message' => "$f is required"]);
                return;
            }
        }

        $paymentId      = (int)$data['payment_id'];
        $userId         = (int)$data['user_id'];
        $action         = htmlspecialchars(strip_tags($data['action']));
        $statusBefore   = $data['status_before']   ?? null;
        $statusAfter    = $data['status_after']    ?? null;
        $paypalResponse = $data['paypal_response'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO payment_log
             (payment_id, user_id, action, status_before, status_after, paypal_response)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iissss', $paymentId, $userId, $action, $statusBefore, $statusAfter, $paypalResponse);

        if (!$stmt->execute()) {
            $stmt->close();
            $this->respond(500, ['success' => false, 'message' => 'Could not create log entry.']);
            return;
        }

        $newId = $stmt->insert_id;
        $stmt->close();

        $this->respond(201, ['success' => true, 'message' => 'Log entry created', 'id' => $newId]);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function respond(int $code, array $body): void
    {
        http_response_code($code);
        echo json_encode($body);
    }
}