<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/autoload.php';
$mail_config = require __DIR__ . '/mail_config.php';

function getMailer(): PHPMailer {
    global $mail_config;
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $mail_config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mail_config['username'];
    $mail->Password = $mail_config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $mail_config['port'];
    $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
    $mail->CharSet = 'UTF-8';
    return $mail;
}

function sendOrderReceivedCustomer(array $order) {
    try {
        $mail = getMailer();
        $mail->addAddress($order['customer_email'], $order['customer_name']);
        $mail->Subject = 'Oda Yako Imepokelewa - Nazuri Collections';
        $items = '';
        if (!empty($order['items'])) {
            $items = "\n\nBidhaa ulizoagiza:\n";
            foreach ($order['items'] as $item) {
                $code = !empty($item['product_code']) ? ' [' . $item['product_code'] . ']' : '';
                $items .= "- {$item['product_name']}$code | Idadi: {$item['quantity']} | Bei: Tsh {$item['price']}";
                if (!empty($item['size'])) $items .= ' | Size: ' . $item['size'];
                if (!empty($item['color'])) $items .= ' | Rangi: ' . $item['color'];
                $items .= "\n";
            }
        }
        $body = "Habari {$order['customer_name']},\n\nAsante kwa kuweka oda katika Nazuri Collections.\n\nOda yako #{$order['id']} imepokelewa na tutaichakata hivi karibuni.$items\n\nJumla: Tsh {$order['total_amount']}\n\nUtapata taarifa zaidi pindi oda yako itakapothibitishwa.\n\nAhsante,\nNazuri Collections Team";
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error (customer): '.$e->getMessage());
        return false;
    }
}

function sendNewOrderAdmin(array $order, string $adminEmail) {
    try {
        $mail = getMailer();
        $mail->addAddress($adminEmail);
        $mail->Subject = 'Oda Mpya Imepokelewa - Nazuri Collections';
        $items = '';
        if (!empty($order['items'])) {
            $items = "\n\nBidhaa:\n";
            foreach ($order['items'] as $item) {
                $code = !empty($item['product_code']) ? ' [' . $item['product_code'] . ']' : '';
                $items .= "- {$item['product_name']}$code | Idadi: {$item['quantity']}";
                if (!empty($item['size'])) $items .= ' | Size: ' . $item['size'];
                if (!empty($item['color'])) $items .= ' | Rangi: ' . $item['color'];
                $items .= "\n";
            }
        }
        $body = "Oda mpya #{$order['id']}\nMteja: {$order['customer_name']}\nEmail: {$order['customer_email']}\nSimu: {$order['customer_phone']}\nJumla: Tsh {$order['total_amount']}$items";
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error (admin): '.$e->getMessage());
        return false;
    }
}

function sendOrderStatusUpdateCustomer(array $order, string $newStatus) {
    try {
        $mail = getMailer();
        $mail->addAddress($order['customer_email'], $order['customer_name']);
        $statusText = $newStatus === 'confirmed' ? 'imethibitishwa na itasafirishwa hivi karibuni' : ($newStatus === 'cancelled' ? 'imeghairiwa' : 'imesasishwa');
        $mail->Subject = "Oda #{$order['id']} - " . ucfirst($newStatus);
        $body = "Habari {$order['customer_name']},\n\nOda yako #{$order['id']} $statusText.\n\nAhsante kwa kununua Nazuri Collections.\n\nNazuri Collections Team";
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error (status): '.$e->getMessage());
        return false;
    }
}

function sendAdminPasswordReset(array $admin, string $link) {
    try {
        $mail = getMailer();
        $mail->addAddress($admin['email'], $admin['name']);
        $mail->Subject = 'Weka Nenosiri Jipya - Nazuri Admin';
        $mail->Body = "Habari {$admin['name']},\n\nBonyeza kiungo hiki kuweka nenosiri jipya:\n$link\n\nKiungo kitatumika kwa saa 1 pekee.\n\nKama hukuituma ombi hili, puuza ujumbe huu.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error (password reset): '.$e->getMessage());
        return false;
    }
}

function getAdminEmails(): array {
    $raw = getenv('ADMIN_EMAILS') ?: '';
    if ($raw === '') {
        return [];
    }
    $emails = array_map('trim', explode(',', $raw));
    return array_filter($emails, function($e) {
        return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
    });
}
