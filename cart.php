<?php
require_once 'config/session_bootstrap.php';
require_once 'config/db_connect.php';
require_once 'config/app_runtime.php';
require_once 'config/mailer.php';
require_once 'config/encryption.php';
require_once 'includes/functions.php';

// Fetch site settings for cart processing
$cart_settings_stmt = $conn->query("SELECT * FROM site_settings");
$cart_settings_rows = $cart_settings_stmt->fetchAll(PDO::FETCH_ASSOC);
$cart_site_settings = [];
foreach ($cart_settings_rows as $row) {
    $cart_site_settings[$row['setting_key']] = $row['setting_value'];
}
$cart_max_qty = !empty($cart_site_settings['max_order_qty']) ? (int)$cart_site_settings['max_order_qty'] : 3;
$cart_currency = 'Tsh';

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['token'] ?? '';
    $session_token = $_SESSION['token'] ?? '';

    if (!is_string($posted_token) || !is_string($session_token) || !hash_equals($session_token, $posted_token)) {
        die("Invalid CSRF token");
    }
}

// 1. Kushughulikia Ongezeko la Bidhaa (Add to Cart)
if (isset($_POST['add_to_cart'])) {
    ensure_product_code_column($conn);
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $size = trim((string)($_POST['size'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    if ($product_id <= 0 || $size === '' || $color === '') {
        header("Location: shop.php");
        exit();
    }

    if ($quantity < 1) {
        $quantity = 1;
    } elseif ($quantity > $cart_max_qty) {
        $quantity = $cart_max_qty;
    }

    // Chukua taarifa halisi za bidhaa kutoka database (usiamini POST price/name)
    $stmt = $conn->prepare("SELECT id, name, price, image, discount_price, coupon_code, product_code, status FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product_data || !in_array($product_data['status'], ['active', 'preorder'], true)) {
        header("Location: shop.php");
        exit();
    }

    $product_name = $product_data['name'];
    $price = (float)$product_data['price'];
    $product_img = $product_data['image'];

    // Tengeneza ID ya kipekee kwa kila mchanganyiko (ID_Size_Color)
    $cart_id = $product_id . '_' . $size . '_' . $color;

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Ikiwa bidhaa ipo, ongeza idadi (kwa kuzingatia ukomo wa 2)
    if (isset($_SESSION['cart'][$cart_id])) {
        $new_qty = $_SESSION['cart'][$cart_id]['quantity'] + $quantity;
        if ($new_qty > 2) $new_qty = 2; // Ukomo uliowekwa awali
        $_SESSION['cart'][$cart_id]['quantity'] = $new_qty;
    } else {
        // Ikiwa haipo, iweke mpya
        $_SESSION['cart'][$cart_id] = [
            'id' => $product_id,
            'name' => $product_name,
            'price' => $price,
            'size' => $size,
            'color' => $color,
            'quantity' => $quantity,
            'image' => $product_img,
            'discount_price' => $product_data['discount_price'],
            'coupon_code' => $product_data['coupon_code'],
            'product_code' => $product_data['product_code'] ?? ''
        ];
    }
    
    // Zuia resubmission kwa kuredirect
    header("Location: cart.php");
    exit();
}

// 2. Kushughulikia Matendo ya Cart (POST + CSRF)
if (isset($_POST['cart_action'])) {
    $cart_action = (string)$_POST['cart_action'];

    if ($cart_action === 'remove' && isset($_POST['item_id'])) {
        $remove_id = (string)$_POST['item_id'];
        unset($_SESSION['cart'][$remove_id]);
        header("Location: cart.php");
        exit();
    }

    if ($cart_action === 'clear') {
        unset($_SESSION['cart']);
        header("Location: cart.php");
        exit();
    }

    if ($cart_action === 'remove_coupon') {
        unset($_SESSION['applied_coupon']);
        header("Location: cart.php");
        exit();
    }
}

// 2.1 Kushughulikia Kuponi (Apply Coupon)
if (isset($_POST['apply_coupon'])) {
    $_SESSION['applied_coupon'] = trim($_POST['coupon_code']);
    header("Location: cart.php");
    exit();
}

// 3. Kushughulikia Kuweka Oda (Direct Order)
if (isset($_POST['place_order'])) {
    // Sanitization (Kusafisha data - Kuzuia Malicious Code)
    $name = trim(strip_tags($_POST['customer_name'] ?? ''));
    $email = filter_var(trim($_POST['customer_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $phone_raw = trim(strip_tags($_POST['customer_phone'] ?? ''));
    $payer_raw = trim(strip_tags($_POST['payer_phone'] ?? ''));

    // Hakikisha hakuna herufi kwenye namba ya simu kabla ya kusafisha
    if (preg_match('/[a-zA-Z]/', $phone_raw) || preg_match('/[a-zA-Z]/', $payer_raw)) {
        echo "<script>alert('" . addslashes(t('invalid_phone_number')) . "'); window.history.back();</script>";
        exit();
    }

    // Ondoa herufi zote kwenye simu isipokuwa namba na alama ya +
    $phone = preg_replace('/[^0-9+]/', '', $phone_raw);
    $payer_phone = preg_replace('/[^0-9+]/', '', $payer_raw);
    $payment_method = trim(strip_tags($_POST['payment_method'] ?? 'cash_on_delivery'));

    // Validation (Uhakiki - Kuhakikisha data imejazwa na ni sahihi)
    if (empty($name) || !$email || empty($phone) || empty($payer_phone)) {
        echo "<script>alert('" . addslashes(t('fill_all_checkout_fields')) . "'); window.history.back();</script>";
        exit();
    }

    // Hakiki namba ya simu (Format ya Tanzania: 0xxxxxxxxx au +255xxxxxxxxx) na namba za kimataifa
    if (!preg_match('/^(\+?[1-9]\d{8,14}|0[1-9]\d{8})$/', $phone) || !preg_match('/^(\+?[1-9]\d{8,14}|0[1-9]\d{8})$/', $payer_phone)) {
        echo "<script>alert('" . addslashes(t('invalid_phone_format')) . "'); window.history.back();</script>";
        exit();
    }

    $total_amount = 0;

    // Kokotoa jumla
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $item_price = $item['price'];
            // Check discount
            if (isset($_SESSION['applied_coupon']) && !empty($item['coupon_code'])) {
                if (strcasecmp($_SESSION['applied_coupon'], $item['coupon_code']) == 0) {
                    if ($item['discount_price'] > 0) {
                        $item_price = $item['discount_price'];
                    }
                }
            }
            $total_amount += $item_price * $item['quantity'];
        }
    }

    if ($total_amount > 0) {
        try {
            // Ensure orders table has payer_phone and customer_email columns (best-effort)
            ensure_payer_phone_column($conn);
            ensure_customer_email_column($conn);

            $conn->beginTransaction();

            // Generate a unique public ID for the order receipt
            $public_id = bin2hex(random_bytes(16));

            // Ingiza kwenye orders (including payer_phone, customer_email) — PII encrypted with AES-256
            $stmt = $conn->prepare("INSERT INTO orders (public_id, customer_name, customer_email, customer_phone, payer_phone, total_amount, payment_method, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$public_id, $name, encrypt_data($email), encrypt_data($phone), $payer_phone, $total_amount, $payment_method]);
            $order_id = $conn->lastInsertId();

            // Ingiza kwenye order_items
            $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, size, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            // Statement ya kuingiza kwenye pre_orders (kwa bidhaa zinazokuja)
            $stmt_preorder = $conn->prepare("INSERT INTO pre_orders (customer_name, customer_phone, product_name, quantity, size, color) VALUES (?, ?, ?, ?, ?, ?)");
            
            if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $item) {
                    // Calculate final price for this item
                    $final_price = $item['price'];
                    if (isset($_SESSION['applied_coupon']) && !empty($item['coupon_code'])) {
                        if (strcasecmp($_SESSION['applied_coupon'], $item['coupon_code']) == 0) {
                            if ($item['discount_price'] > 0) {
                                $final_price = $item['discount_price'];
                            }
                        }
                    }

                    $stmt_item->execute([
                        $order_id, 
                        $item['id'], 
                        $item['name'], 
                        $item['quantity'], 
                        $final_price, 
                        $item['size'], 
                        $item['color']
                    ]);
                    
                    // Angalia kama bidhaa ni ya Pre-Order
                    $stmt_check = $conn->prepare("SELECT status FROM products WHERE id = ?");
                    $stmt_check->execute([$item['id']]);
                    $p_status = $stmt_check->fetchColumn();
                    
                    if ($p_status == 'preorder') {
                        $stmt_preorder->execute([
                            $name,
                            encrypt_data($phone),
                            $item['name'],
                            $item['quantity'],
                            $item['size'],
                            $item['color']
                        ]);
                    }
                }
            }

            $conn->commit();
            
            app_log_event(
                $conn,
                'order_created',
                'Order #' . $order_id . ' created by ' . $name . ' via ' . $payment_method . ' total Tsh ' . number_format($total_amount, 2, '.', '') . ' payer: ' . $payer_phone
            );

            // Send order confirmation emails (best-effort)
            $stmtItems = $conn->prepare("SELECT oi.product_name, oi.quantity, oi.price, oi.size, oi.color, p.product_code FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $stmtItems->execute([$order_id]);
            $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            $orderData = [
                'id' => $order_id,
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'total_amount' => number_format($total_amount, 2, '.', ''),
                'items' => $orderItems,
            ];
            sendOrderReceivedCustomer($orderData);
            $adminEmails = getAdminEmails();
            foreach ($adminEmails as $adminEmail) {
                sendNewOrderAdmin($orderData, $adminEmail);
            }

            $_SESSION['order_notice'] = t('order_received_notice');

            // Futa kapu
            $_SESSION['last_order_id'] = $public_id;
            unset($_SESSION['cart']);
            
            // Redirect na ujumbe
            header("Location: order_success.php?id=" . $public_id);
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            echo "<script>alert('Kosa: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

include 'includes/header.php';
?>

<style>
    .payment-option { cursor: pointer; transition: all 0.2s; }
    .payment-selector:checked + .payment-option {
        border-color: #212529 !important;
        background-color: #fff;
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    .payment-selector { display: none; } /* Hide default radio */
    .sticky-summary { position: sticky; top: 20px; }
</style>

<div class="container py-5">
    <h1 class="display-5 fw-bold mb-5" style="font-family: 'Playfair Display', serif;"><?php echo t('shopping_cart'); ?></h1>
<?php if (!empty($_SESSION['order_notice'])): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars((string)$_SESSION['order_notice']); ?></div>
                        <?php unset($_SESSION['order_notice']); ?>
                    <?php endif; ?>

    <?php if (empty($_SESSION['cart'])): ?>
        <div class="text-center py-5 bg-light rounded-3">
            <i class="bi bi-cart-x display-1 text-muted"></i>
            <p class="lead mt-3 fw-bold text-muted"><?php echo t('cart_is_empty'); ?></p>
            <a href="shop.php" class="btn btn-dark rounded-pill px-5 py-3 mt-2 text-uppercase tracking-wide"><?php echo t('start_shopping'); ?></a>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Orodha ya Bidhaa -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-uppercase small fw-bold text-muted">
                                <tr>
                                    <th class="ps-4 py-3"><?php echo t('product'); ?></th>
                                    <th class="py-3"><?php echo t('price'); ?></th>
                                    <th class="py-3 text-center"><?php echo t('quantity_label'); ?></th>
                                    <th class="py-3"><?php echo t('total_label'); ?></th>
                                    <th class="pe-4 py-3 text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($_SESSION['cart'] as $key => $item): 
                                    $item_price = $item['price'];
                                    $has_discount = false;
                                    // Check Coupon
                                    if (isset($_SESSION['applied_coupon']) && !empty($item['coupon_code'])) {
                                        if (strcasecmp($_SESSION['applied_coupon'], $item['coupon_code']) == 0) {
                                            if ($item['discount_price'] > 0) {
                                                $item_price = $item['discount_price'];
                                                $has_discount = true;
                                            }
                                        }
                                    }
                                    $subtotal = $item_price * $item['quantity'];
                                    $total += $subtotal;
                                ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="rounded-3 shadow-sm" 
                                                 style="width: 80px; height: 100px; object-fit: cover;"
                                                 loading="lazy">
                                            <div class="ms-3">
                                                <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <div class="small text-muted">
                                                    <span class="me-2"><?php echo t('size_label'); ?>: <strong><?php echo $item['size']; ?></strong></span>
                                                    <span class="me-2"><?php echo t('color_label'); ?>: <strong><?php echo $item['color']; ?></strong></span>
                                                    <?php if (!empty($item['product_code'])): ?><span><?php echo t('product_code_label'); ?>: <strong><?php echo htmlspecialchars($item['product_code'], ENT_QUOTES, 'UTF-8'); ?></strong></span><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 text-muted">
                                        <?php if($has_discount): ?>
                                            <span class="text-decoration-line-through small text-danger">Tsh <?php echo number_format($item['price']); ?></span><br>
                                            <span class="fw-bold text-success">Tsh <?php echo number_format($item_price); ?></span>
                                        <?php else: ?>
                                            Tsh <?php echo number_format($item['price']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-center">
                                        <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm"><?php echo $item['quantity']; ?></span>
                                    </td>
                                    <td class="py-3 fw-bold text-dark">Tsh <?php echo number_format($subtotal); ?></td>
                                    <td class="pe-4 py-3 text-end">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo t('remove_item_confirm'); ?>');">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="cart_action" value="remove">
                                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle" title="<?php echo t('remove_item'); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <a href="shop.php" class="btn btn-outline-dark rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i> <?php echo t('continue_shopping'); ?></a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo t('clear_cart_confirm'); ?>');">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="cart_action" value="clear">
                        <button type="submit" class="btn btn-outline-danger rounded-pill px-4"><i class="bi bi-trash me-2"></i> <?php echo t('clear_cart'); ?></button>
                    </form>
                </div>
            </div>

            <!-- Muhtasari wa Malipo -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm bg-light rounded-4 sticky-summary">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4" style="font-family: 'Playfair Display', serif;"><?php echo t('order_summary'); ?></h5>
                        
                        <!-- Coupon Form -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted"><?php echo t('coupon_code_label'); ?></label>
                            <?php if(isset($_SESSION['applied_coupon'])): ?>
                                <div class="alert alert-success d-flex justify-content-between align-items-center p-2 small mb-0">
                                    <span><i class="bi bi-tag-fill me-1"></i> <?php echo htmlspecialchars($_SESSION['applied_coupon']); ?></span>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="cart_action" value="remove_coupon">
                                        <button type="submit" class="btn-close btn-close-white small"></button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <form method="POST" class="input-group">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="text" name="coupon_code" class="form-control" placeholder="<?php echo t('coupon_code_placeholder'); ?>">
                                    <button class="btn btn-dark" type="submit" name="apply_coupon"><?php echo t('apply'); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between mb-3 text-muted">
                            <span><?php echo t('sub_total'); ?></span>
                            <span>Tsh <?php echo number_format($total); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 text-muted">
                            <span><?php echo t('shipping'); ?></span>
                            <span class="small"><?php echo t('paid_on_delivery'); ?></span>
                        </div>
                        <hr class="my-3">
                        <div class="d-flex justify-content-between mb-4">
                            <span class="fs-5 fw-bold text-dark"><?php echo t('grand_total'); ?></span>
                            <span class="fs-5 fw-bold text-primary">Tsh <?php echo number_format($total); ?></span>
                        </div>
                        
                        <!-- WhatsApp Checkout Link Construction -->
                        <?php
                            $wa_raw_phone = !empty($cart_site_settings['phone']) ? $cart_site_settings['phone'] : '0767557234';
                            $wa_clean = preg_replace('/[^0-9]/', '', $wa_raw_phone);
                            if (substr($wa_clean, 0, 1) === '0') {
                                $wa_clean = '255' . substr($wa_clean, 1);
                            }
                            $wa_message = t('whatsapp_cart_intro') . "\n";
                            foreach ($_SESSION['cart'] as $item) {
                                $item_label = $item['name'];
                                if (!empty($item['product_code'])) {
                                    $item_label .= " (" . t('product_code_label') . ": " . $item['product_code'] . ")";
                                }
                                $wa_message .= "▪ " . $item_label . " (" . t('size_label') . ": " . $item['size'] . ", " . t('color_label') . ": " . $item['color'] . ") x" . $item['quantity'] . " @ " . number_format($item['price']) . "\n";
                            }
                            $wa_message .= "\n*" . t('whatsapp_cart_total') . ": Tsh " . number_format($total) . "*\n";
                            $wa_link = "https://wa.me/" . $wa_clean . "?text=" . urlencode($wa_message);
                            $wa_message = t('whatsapp_order_intent') . "\n\n";
                            foreach ($_SESSION['cart'] as $item) {
                                $safeName = trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)($item['name'] ?? t('product')))));
                                $safeCode = trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)($item['product_code'] ?? ''))));
                                $safeSize = trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)($item['size'] ?? ''))));
                                $safeColor = trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)($item['color'] ?? ''))));
                                $safeQty = max(1, (int)($item['quantity'] ?? 1));
                                $safePrice = (float)($item['price'] ?? 0);
 
                                $wa_message .= t('whatsapp_product') . ": {$safeName}";
                                if ($safeCode !== '') {
                                    $wa_message .= " (" . t('product_code_label') . ": {$safeCode})";
                                }
                                $wa_message .= "\n";
                                $wa_message .= t('whatsapp_price') . ": Tsh " . number_format($safePrice) . "\n";
                                $wa_message .= t('whatsapp_size') . ": {$safeSize}\n";
                                $wa_message .= t('whatsapp_color') . ": {$safeColor}\n";
                                $wa_message .= t('whatsapp_quantity') . ": {$safeQty}\n\n";
                            }
                            $wa_link = "https://wa.me/" . $wa_clean . "?text=" . urlencode(trim($wa_message));
                        ?>

                        <input type="hidden" name="payment_method" value="cash_on_delivery">

<div class="d-grid gap-2">
                             <a href="<?php echo htmlspecialchars($wa_link, ENT_QUOTES, 'UTF-8'); ?>" id="wa-checkout-btn" target="_blank" rel="noopener noreferrer" class="btn btn-success py-3 rounded-pill text-uppercase tracking-wide fw-bold shadow-sm">
                                 <i class="bi bi-whatsapp me-2"></i> <?php echo t('checkout_with_whatsapp'); ?>
                             </a>
                            <button type="button" id="directOrderBtn" class="btn btn-dark py-3 rounded-pill text-uppercase tracking-wide fw-bold shadow-sm">
                                <i class="bi bi-bag-check-fill me-2"></i> <?php echo t('place_order_here'); ?>
                            </button>
                        </div>

                        <p class="text-center small text-muted mt-3 mb-0">
                            <i class="bi bi-shield-lock"></i> <?php echo t('payment_secure'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Order Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><?php echo t('place_order_title'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('full_name'); ?></label>
                        <input type="text" name="customer_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('Email'); ?></label>
                        <input type="email" name="customer_email" class="form-control" required placeholder="<?php echo t('email_example'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('phone_number'); ?></label>
                        <input type="tel" name="customer_phone" class="form-control" required placeholder="<?php echo t('phone_example'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('payer_phone_label'); ?></label>
                        <input type="tel" name="payer_phone" class="form-control" required placeholder="<?php echo t('phone_example'); ?>">
                    </div>
                    <input type="hidden" name="payment_method" id="modal_payment_method" value="cash_on_delivery">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('close'); ?></button>
                    <button type="submit" name="place_order" class="btn btn-dark"><?php echo t('confirm_order'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const directOrderBtn = document.getElementById('directOrderBtn');

    directOrderBtn.addEventListener('click', function() {
        // Payment is simplified to Cash on Delivery; no selection required
        document.getElementById('modal_payment_method').value = 'cash_on_delivery';
        var myModal = new bootstrap.Modal(document.getElementById('orderModal'));
        myModal.show();
    });
});
</script>

<?php include 'includes/footer.php'; ?>
