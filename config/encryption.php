<?php
function encrypt_data(string $plaintext, ?string $key = null): string {
    if ($plaintext === '') {
        return '';
    }
    $key = $key ?: getenv('APP_KEY');
    if (!$key) {
        throw new RuntimeException('APP_KEY not set in environment');
    }
    $encryptionKey = hash('sha256', $key, true);
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $encryptionKey, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed: ' . openssl_error_string());
    }
    return base64_encode($iv . $ciphertext);
}

function decrypt_data(string $encoded, ?string $key = null): string {
    if ($encoded === '') {
        return '';
    }
    $key = $key ?: getenv('APP_KEY');
    if (!$key) {
        throw new RuntimeException('APP_KEY not set in environment');
    }
    $encryptionKey = hash('sha256', $key, true);
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 16) {
        return '';
    }
    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $encryptionKey, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        return '';
    }
    return $decrypted;
}

function decrypt_order_pii(array &$order): void {
    if (isset($order['customer_email'])) {
        $order['customer_email'] = decrypt_data($order['customer_email']);
    }
    if (isset($order['customer_phone'])) {
        $order['customer_phone'] = decrypt_data($order['customer_phone']);
    }
    if (isset($order['payer_phone'])) {
        $decrypted = decrypt_data($order['payer_phone']);
        if ($decrypted !== '') {
            $order['payer_phone'] = $decrypted;
        }
    }
}
