<?php
/**
 * Payment Resource
 * Handles Prelim / Midterm / Finals payment records.
 * Table: `payment`
 */
class Payment
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // ── Public endpoints ──────────────────────────────────────────

    /** GET /api/payments?user_id=X */
    public function getByUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payment WHERE user_id = ? ORDER BY id ASC'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $this->respond(200, ['success' => true, 'data' => $rows]);
    }

    /** GET /api/payments/{id} */
    public function get(int $id): void
    {
        $stmt = $this->db->prepare('SELECT * FROM payment WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $this->respond(404, ['success' => false, 'message' => 'Payment not found']);
            return;
        }

        $this->respond(200, ['success' => true, 'data' => $row]);
    }

    /** POST /api/payments */
    public function create(array $data): void
    {
        $required = ['user_id', 'due_type', 'amount', 'due_date'];
        foreach ($required as $f) {
            if (empty($data[$f])) {
                $this->respond(400, ['success' => false, 'message' => "$f is required"]);
                return;
            }
        }

        $validDues = ['Prelim', 'Midterm', 'Finals'];
        if (!in_array($data['due_type'], $validDues)) {
            $this->respond(400, ['success' => false, 'message' => 'due_type must be Prelim, Midterm, or Finals']);
            return;
        }

        // Enforce unique (user_id + due_type) — mirrors DB UNIQUE KEY unique_user_due
        $stmt = $this->db->prepare(
            'SELECT id FROM payment WHERE user_id = ? AND due_type = ?'
        );
        $stmt->bind_param('is', $data['user_id'], $data['due_type']);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $this->respond(409, ['success' => false, 'message' => 'Payment record for this due type already exists']);
            return;
        }

        $userId  = (int)$data['user_id'];
        $dueType = $data['due_type'];
        $amount  = number_format((float)$data['amount'], 2, '.', '');
        $dueDate = $data['due_date'];
        $notes   = $data['notes'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO payment (user_id, due_type, amount, due_date, notes)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $userId, $dueType, $amount, $dueDate, $notes);

        if (!$stmt->execute()) {
            $stmt->close();
            $this->respond(500, ['success' => false, 'message' => 'Could not create payment record.']);
            return;
        }

        $newId = $stmt->insert_id;
        $stmt->close();

        $this->respond(201, ['success' => true, 'message' => 'Payment record created', 'id' => $newId]);
    }

    /** PUT /api/payments/{id} */
    public function update(int $id, array $data): void
    {
        $allowed = [
            'payment_status', 'transaction_id', 'paypal_payer_id',
            'paypal_payment_id', 'payment_date', 'paid_date', 'notes',
        ];

        $sets   = [];
        $types  = '';
        $values = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]   = "$field = ?";
                $types   .= 's';
                $values[] = $data[$field];
            }
        }

        if (empty($sets)) {
            $this->respond(400, ['success' => false, 'message' => 'No valid fields to update']);
            return;
        }

        $types   .= 'i';
        $values[] = $id;

        $sql  = 'UPDATE payment SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);

        if (!$stmt->execute()) {
            $stmt->close();
            $this->respond(500, ['success' => false, 'message' => 'Could not update payment.']);
            return;
        }

        $stmt->close();

        // Return the updated record so callers have fresh data
        $this->get($id);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function respond(int $code, array $body): void
    {
        http_response_code($code);
        echo json_encode($body);
    }
}