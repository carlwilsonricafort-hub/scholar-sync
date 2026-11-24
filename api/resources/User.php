<?php
/**
 * User Resource
 * Handles all user operations using MySQLi prepared statements.
 * Table: `user`
 */
class User
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // ── Public endpoints ──────────────────────────────────────────

    /** GET /api/users */
    public function getAll(): void
    {
        $result = $this->db->query('SELECT * FROM user ORDER BY id ASC');
        $users  = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $this->formatRow($row);
        }

        $this->respond(200, ['success' => true, 'count' => count($users), 'data' => $users]);
    }

    /** GET /api/users/{id} */
    public function get(int $id): void
    {
        $stmt = $this->db->prepare('SELECT * FROM user WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $this->respond(404, ['success' => false, 'message' => 'User not found']);
            return;
        }

        $this->respond(200, ['success' => true, 'data' => $this->formatRow($row)]);
    }

    /** GET /api/users?email=x */
    public function getByEmail(string $email): void
    {
        $stmt = $this->db->prepare('SELECT * FROM user WHERE email_address = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $this->respond(404, ['success' => false, 'message' => 'User not found']);
            return;
        }

        $this->respond(200, ['success' => true, 'data' => $this->formatRow($row)]);
    }

    /** POST /api/users */
    public function create(array $data): void
    {
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email_address'])) {
            $this->respond(400, ['success' => false, 'message' => 'first_name, last_name, and email_address are required']);
            return;
        }

        if (!filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
            $this->respond(400, ['success' => false, 'message' => 'Invalid email address format']);
            return;
        }

        // Duplicate email check
        $stmt = $this->db->prepare('SELECT id FROM user WHERE email_address = ?');
        $stmt->bind_param('s', $data['email_address']);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $this->respond(409, ['success' => false, 'message' => 'An account with this email already exists']);
            return;
        }

        $firstName     = htmlspecialchars(strip_tags($data['first_name']));
        $lastName      = htmlspecialchars(strip_tags($data['last_name']));
        $email         = htmlspecialchars(strip_tags($data['email_address']));
        $password      = $data['password']                                                                ?? null;
        $imageUrl      = !empty($data['image_url'])      ? htmlspecialchars(strip_tags($data['image_url']))      : null;
        $contactNumber = !empty($data['contact_number']) ? htmlspecialchars(strip_tags($data['contact_number'])) : null;
        $nickname      = !empty($data['nickname'])       ? htmlspecialchars(strip_tags($data['nickname']))       : null;
        $sex           = !empty($data['sex'])            ? htmlspecialchars(strip_tags($data['sex']))            : null;
        $birthDate     = !empty($data['birth_date'])     ? $data['birth_date']                                   : null;
        $birthPlace    = !empty($data['birth_place'])    ? htmlspecialchars(strip_tags($data['birth_place']))    : null;
        $address       = !empty($data['address'])        ? htmlspecialchars(strip_tags($data['address']))        : null;

        $stmt = $this->db->prepare(
            'INSERT INTO user
             (first_name, last_name, email_address, password, image_url,
              contact_number, nickname, sex, birth_date, birth_place, address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        // 11 columns → 11 bind params
        $stmt->bind_param(
            'sssssssssss',
            $firstName, $lastName, $email, $password, $imageUrl,
            $contactNumber, $nickname, $sex, $birthDate, $birthPlace, $address
        );

        if (!$stmt->execute()) {
            $stmt->close();
            $this->respond(500, ['success' => false, 'message' => 'Could not create user.']);
            return;
        }

        $newId = $stmt->insert_id;
        $stmt->close();

        $this->respond(201, ['success' => true, 'message' => 'User created successfully', 'id' => $newId]);
    }

    /** PUT /api/users/{id} */
    public function update(int $id, array $data): void
    {
        $allowed = ['first_name', 'last_name', 'contact_number', 'nickname',
                    'sex', 'birth_date', 'birth_place', 'address', 'image_url'];

        $sets   = [];
        $types  = '';
        $values = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $val      = htmlspecialchars(strip_tags($data[$field] ?? ''));
                $sets[]   = "$field = ?";
                $types   .= 's';
                $values[] = ($val === '') ? null : $val;
            }
        }

        if (empty($sets)) {
            $this->respond(400, ['success' => false, 'message' => 'No valid fields to update']);
            return;
        }

        $types   .= 'i';
        $values[] = $id;

        $sql  = 'UPDATE user SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);

        if (!$stmt->execute()) {
            $stmt->close();
            $this->respond(500, ['success' => false, 'message' => 'Could not update user.']);
            return;
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            // Could mean user not found OR data was identical — still a success
            $this->respond(200, ['success' => true, 'message' => 'User updated successfully']);
            return;
        }

        $this->respond(200, ['success' => true, 'message' => 'User updated successfully']);
    }

    /** PUT /api/users/{id}/password */
    public function changePassword(int $id, array $data): void
    {
        $currentPassword = $data['current_password'] ?? '';
        $newPassword     = $data['new_password']     ?? '';
        $isSettingNew    = !empty($data['set_new']);

        if (empty($newPassword)) {
            $this->respond(400, ['success' => false, 'message' => 'new_password is required']);
            return;
        }

        if (!$isSettingNew && empty($currentPassword)) {
            $this->respond(400, ['success' => false, 'message' => 'current_password is required']);
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->respond(400, ['success' => false, 'message' => 'New password must be at least 8 characters']);
            return;
        }

        $stmt = $this->db->prepare('SELECT password FROM user WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $this->respond(404, ['success' => false, 'message' => 'User not found']);
            return;
        }

        $storedHash = $row['password'] ?? '';

        if (empty($storedHash)) {
            if (!$isSettingNew) {
                $this->respond(400, ['success' => false, 'message' => 'This account uses Google login. Use Set Password instead.']);
                return;
            }
        } else {
            if (!$isSettingNew && !password_verify($currentPassword, $storedHash)) {
                $this->respond(401, ['success' => false, 'message' => 'Current password is incorrect']);
                return;
            }
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt   = $this->db->prepare('UPDATE user SET password = ? WHERE id = ?');
        $stmt->bind_param('si', $hashed, $id);
        $stmt->execute();
        $stmt->close();

        $this->respond(200, ['success' => true, 'message' => 'Password changed successfully']);
    }

    /** POST /api/users/login */
public function login(array $data): void
{
    $email    = trim($data['email']    ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        $this->respond(400, ['success' => false, 'message' => 'Email and password are required']);
        return;
    }

    // ── Rate limiting: max 5 attempts per email in 15 minutes ────
    $window  = 15 * 60; // 15 minutes in seconds
    $maxTries = 5;
    $since   = date('Y-m-d H:i:s', time() - $window);

    $stmt = $this->db->prepare(
        'SELECT COUNT(*) FROM login_attempt WHERE email = ? AND attempted_at >= ?'
    );
    $stmt->bind_param('ss', $email, $since);
    $stmt->execute();
    $stmt->bind_result($attemptCount);
    $stmt->fetch();
    $stmt->close();

    if ($attemptCount >= $maxTries) {
        $this->respond(429, [
            'success' => false,
            'message' => 'Too many login attempts. Please wait 15 minutes and try again.',
        ]);
        return;
    }

    // ── Look up user ──────────────────────────────────────────────
    $stmt = $this->db->prepare('SELECT * FROM user WHERE email_address = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        // Record failed attempt even for unknown emails (prevents enumeration)
        $this->recordAttempt($email);
        $this->respond(404, ['success' => false, 'message' => 'No account found with that email']);
        return;
    }

    if (empty($user['password'])) {
        $this->respond(400, ['success' => false, 'message' => 'This account uses Google login. Please sign in with Google.']);
        return;
    }

    if (!password_verify($password, $user['password'])) {
        // Record failed attempt only on wrong password
        $this->recordAttempt($email);
        $this->respond(401, ['success' => false, 'message' => 'Incorrect password']);
        return;
    }

    // ── Success: clear attempts for this email ────────────────────
    $stmt = $this->db->prepare('DELETE FROM login_attempt WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();

    $this->respond(200, ['success' => true, 'message' => 'Login successful', 'data' => $this->formatRow($user)]);
}

/** Insert one failed attempt row */
private function recordAttempt(string $email): void
{
    $stmt = $this->db->prepare('INSERT INTO login_attempt (email) VALUES (?)');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

    /** DELETE /api/users/{id} */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM user WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $this->respond(404, ['success' => false, 'message' => 'User not found']);
            return;
        }

        $this->respond(200, ['success' => true, 'message' => 'User deleted successfully']);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function formatRow(array $row): array
    {
        return [
            'id'             => (int)$row['id'],
            'first_name'     => $row['first_name'],
            'last_name'      => $row['last_name'],
            'image_url'      => $row['image_url']      ?? null,
            'email_address'  => $row['email_address'],
            'contact_number' => $row['contact_number'] ?? null,
            'nickname'       => $row['nickname']       ?? null,
            'sex'            => $row['sex']            ?? null,
            'birth_date'     => $row['birth_date']     ?? null,
            'birth_place'    => $row['birth_place']    ?? null,
            'address'        => $row['address']        ?? null,
            'created_at'     => $row['created_at']     ?? null,
            'updated_at'     => $row['updated_at']     ?? null,
            'has_password'   => !empty($row['password']),
        ];
    }

    private function respond(int $code, array $body): void
    {
        http_response_code($code);
        echo json_encode($body);
    }
}