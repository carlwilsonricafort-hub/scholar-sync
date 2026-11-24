<?php
/**
 * curl_helper.php
 * Reusable cURL wrapper for all API calls in the app.
 */

function curlRequest(string $url, string $method = 'GET', ?array $payload = null): array
{
    try {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL handle.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST]       = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($payload ?? []);
                break;

            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                $options[CURLOPT_POSTFIELDS]    = json_encode($payload ?? []);
                break;

            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($ch, $options);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('cURL error: ' . $curlErr);
        }

        if ($raw === false) {
            throw new RuntimeException('cURL returned no response.');
        }

        $decoded = json_decode($raw, true);

        if ($decoded === null) {
            throw new RuntimeException('Invalid JSON response from API.');
        }

        return [
            'success'  => $decoded['success']  ?? ($httpCode >= 200 && $httpCode < 300),
            'data'     => $decoded['data']      ?? ($decoded['id'] ?? null),
            'message'  => $decoded['message']   ?? '',
            'httpCode' => $httpCode,
            'raw'      => $decoded,
        ];

    } catch (RuntimeException $e) {
        return [
            'success'  => false,
            'data'     => null,
            'message'  => $e->getMessage(),
            'httpCode' => 0,
            'raw'      => [],
        ];
    }
}

// ── PayPal-specific helpers ───────────────────────────────────────

function getPayPalAccessToken(): ?string
{
    try {
        $ch = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('cURL error: ' . $curlErr);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException('PayPal token request failed with HTTP ' . $httpCode);
        }

        $data = json_decode($raw, true);
        return $data['access_token'] ?? null;

    } catch (RuntimeException $e) {
        error_log('[PayPal] getPayPalAccessToken: ' . $e->getMessage());
        return null;
    }
}

function createPayPalOrder(string $accessToken, float $amount, string $dueType, int $paymentId): ?array
{
    try {
        $payload = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'PAYMENT_' . $paymentId,
                'description'  => 'Student Payment Portal — ' . $dueType . ' Due',
                'amount'       => [
                    'currency_code' => 'PHP',
                    'value'         => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => APP_URL . '/payment/success.php?payment_id=' . $paymentId,
                'cancel_url' => APP_URL . '/payment/cancel.php?payment_id=' . $paymentId,
            ],
        ];

        $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders');

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('cURL error: ' . $curlErr);
        }

        if ($httpCode !== 201) {
            throw new RuntimeException('PayPal order creation failed with HTTP ' . $httpCode);
        }

        $data = json_decode($raw, true);

        if (empty($data['id'])) {
            throw new RuntimeException('PayPal order ID missing in response.');
        }

        $approveUrl = null;
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approveUrl = $link['href'];
                break;
            }
        }

        return ['id' => $data['id'], 'approve_url' => $approveUrl];

    } catch (RuntimeException $e) {
        error_log('[PayPal] createPayPalOrder: ' . $e->getMessage());
        return null;
    }
}

function capturePayPalOrder(string $accessToken, string $orderId): ?array
{
    try {
        $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders/' . $orderId . '/capture');

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('cURL error: ' . $curlErr);
        }

        if ($httpCode !== 201) {
            throw new RuntimeException('PayPal capture failed with HTTP ' . $httpCode);
        }

        return json_decode($raw, true);

    } catch (RuntimeException $e) {
        error_log('[PayPal] capturePayPalOrder: ' . $e->getMessage());
        return null;
    }
}