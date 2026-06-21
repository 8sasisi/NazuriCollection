<?php
require_once 'config/db_connect.php';
include 'includes/header.php';

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// Pata ID ya bidhaa kutoka kwenye URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id !== false && $id !== null && $id > 0) {
    
    // Vuta taarifa za bidhaa hiyo kutoka database
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vuta picha za gallery
    $stmt_gallery = $conn->prepare("SELECT * FROM product_gallery WHERE product_id = ?");
    $stmt_gallery->execute([$id]);
    $gallery_images = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);

    // Kama bidhaa haipo, rudisha kwenye duka
    if (!$product) {
        echo "<script>window.location.href='shop.php';</script>";
        exit;
    }

    // Handle Review Submission
    if (isset($_POST['submit_review'])) {
        $posted_token = $_POST['token'] ?? '';
        if (!is_string($posted_token) || !hash_equals($_SESSION['token'] ?? '', $posted_token)) {
            die("Invalid CSRF token");
        }

        // --- Honeypot Spam Protection ---
        // Ikiwa uwanja huu una data, inamaanisha ni bot ameuijaza
        if (!empty($_POST['email_confirm'])) {
            exit(); // Puuza kimya kimya bila kutoa maelezo yoyote kwa bot
        }

        // --- Rate Limiting Logic ---
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $max_reviews_per_hour = 3; 
        $window_seconds = 3600; // Saa 1
        $current_time = time();

        // 1. Safisha rekodi za zamani na kagua idadi ya maombi ya sasa
        $conn->prepare("DELETE FROM request_rate_limits WHERE request_time < ?")->execute([$current_time - $window_seconds]);
        
        $stmt_check_limit = $conn->prepare("SELECT COUNT(*) FROM request_rate_limits WHERE ip_address = ? AND action_name = 'submit_review' AND request_time > ?");
        $stmt_check_limit->execute([$client_ip, $current_time - $window_seconds]);
        $request_count = $stmt_check_limit->fetchColumn();

        if ($request_count >= $max_reviews_per_hour) {
            $_SESSION['review_bad_word_error'] = "Umefikia ukomo wa kutuma reviews kwa saa hii. Tafadhali jaribu tena baadaye.";
            header("Location: product_details.php?id=$id");
            exit;
        }
        // --- End Rate Limiting ---

        $name = trim(strip_tags($_POST['reviewer_name'] ?? ''));
        $rating = (int)$_POST['rating'];
        $comment = trim(strip_tags($_POST['comment'] ?? ''));
        $max_length = 500;
        $name_max_length = 100;

        // Orodha ya maneno yaliyopigwa marufuku (Bad words + International Spam keywords)
        $bad_words = [
            'fuck', 'shit', 'bitch', 'asshole', 'cunt', 'bastard', 'damn', 'pussy', 'dick', 
            'nigga', 'nigger', 'faggot', 'wanker', 'motherfucker', 'mf', 'minge', 'prick', 
            'punani', 'slut', 'whore',
            'crypto', 'casino', 'viagra', 'investment', 'poker', 'lottery', 'bitcoin', 'whatsapp',
            'http', 'https', 'www', '.com', '.net', '.org', 'click here', 'buy now', 'cheap'
        ];
        
        if (!empty($comment)) {
            // Tengeneza regex pattern itakayotambua maneno hata yakiwa na alama katikati (e.g., s.h.i.t, f u c k)
            $regex_parts = array_map(function($word) {
                return implode('[\W_]*', array_map('preg_quote', str_split($word)));
            }, $bad_words);
            $pattern = '/(' . implode('|', $regex_parts) . ')/i';

            if (preg_match($pattern, $comment)) {
                $_SESSION['review_bad_word_error'] = "Maoni yako yana maneno yasiyofaa au yamekaa katika muundo usioruhusiwa. Tafadhali rekebisha.";
                header("Location: product_details.php?id=$id");
                exit;
            }
        }
        
        if ($rating >= 1 && $rating <= 5 && !empty($name) && mb_strlen($name) <= $name_max_length && mb_strlen($comment) <= $max_length) {
            $stmt = $conn->prepare("INSERT INTO reviews (product_id, customer_name, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $name, $rating, $comment]);

            // Sajili jaribio lililofanikiwa kwenye rate limits
            $conn->prepare("INSERT INTO request_rate_limits (ip_address, action_name, request_time) VALUES (?, 'submit_review', ?)")->execute([$client_ip, $current_time]);

            $_SESSION['review_success'] = true;
            header("Location: product_details.php?id=$id");
            exit;
        } else if (mb_strlen($comment) > $max_length) {
            header("Location: product_details.php?id=$id&error=" . urlencode("Maoni yako ni marefu sana. Tafadhali punguza yafike herufi $max_length."));
            exit;
        }
    }

    // Fetch reviews
    $stmt_reviews = $conn->prepare("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC");
    $stmt_reviews->execute([$id]);
    $reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);

    // Calculate average
    $avg_rating = 0;
    $review_count = count($reviews);
    if ($review_count > 0) {
        $total_stars = array_sum(array_column($reviews, 'rating'));
        $avg_rating = round($total_stars / $review_count, 1);
    }
} else {
    echo "<script>window.location.href='shop.php';</script>";
    exit;
}
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><?php echo t('home'); ?></a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none text-muted"><?php echo t('shop'); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>

    <!-- Display Error Message if exists -->
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['review_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show alert-review-success" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo t('review_success_message'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['review_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['review_bad_word_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_SESSION['review_bad_word_error'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['review_bad_word_error']); ?>
    <?php endif; ?>

    <div class="row mt-4 g-5">
        <!-- Sehemu ya Picha -->
        <div class="col-md-6 col-xxl-7 mb-4">
            <style>
                .zoom-container { overflow: hidden; cursor: crosshair; }
                .zoom-container img { transition: transform 0.3s ease-out; }
                /* Wakati wa hover, punguza muda wa transition ili iwe responsive kwenye mouse movement */
                .zoom-container:hover img { transition: transform 0.05s linear; }
            </style>
            <div class="card border-0 shadow-sm zoom-container">
                <?php require_once __DIR__ . '/includes/image_helper.php'; echo responsive_picture('uploads/' . $product['image'], $product['name'], ['class'=>'card-img-top rounded','sizes'=>'(max-width: 768px) 100vw, 50vw']); ?>
            </div>
            
            <?php if(count($gallery_images) > 0): ?>
            <div class="d-flex gap-2 mt-3 overflow-auto pb-2">
                <!-- Picha Kuu (Thumbnail) -->
                <?php echo responsive_picture('uploads/' . $product['image'], $product['name'], ['class'=>'rounded border gallery-thumb','sizes'=>'80px']); ?>
                     
                <!-- Picha za Gallery -->
                <?php foreach($gallery_images as $g_img): ?>
                <?php echo responsive_picture('uploads/' . $g_img['image'], $product['name'], ['class'=>'rounded border gallery-thumb','sizes'=>'80px']); ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sehemu ya Maelezo na Chaguzi -->
        <div class="col-md-6 col-xxl-5">
            <h1 class="display-5 fw-bold" style="font-family: 'Playfair Display', serif;">
                <?php echo htmlspecialchars($product['name']); ?>
                <?php if(isset($product['offer_badge']) && $product['offer_badge'] == 1): ?>
                    <?php if(isset($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                        <span class="badge bg-danger fs-6 align-middle ms-2"><?php echo $product['discount_percentage']; ?>% <?php echo t('off'); ?></span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6 align-middle ms-2"><?php echo t('offer'); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </h1>
            <div class="mb-3">
                <span class="badge bg-dark text-uppercase tracking-wide"><?php echo htmlspecialchars($product['category']); ?></span>
                <?php if (!empty($product['product_code'])): ?>
                    <span class="badge bg-secondary text-uppercase tracking-wide ms-2"><?php echo t('product_code_label'); ?>: <?php echo htmlspecialchars($product['product_code']); ?></span>
                <?php endif; ?>
            </div>
             
            <?php if($product['discount_price'] > 0): ?>
                <?php 
                    $percentage = ($product['price'] > 0) ? (($product['price'] - $product['discount_price']) / $product['price']) * 100 : 0;
                    $price_color = ($percentage >= 50) ? 'text-success' : 'text-danger';
                ?>
                <h2 class="mb-4">
                    <span class="text-decoration-line-through text-muted fs-4 me-2">Tsh <?php echo number_format($product['price']); ?></span>
                    <span class="<?php echo $price_color; ?> fw-bold">Tsh <?php echo number_format($product['discount_price']); ?></span>
                </h2>
            <?php else: ?>
                <h2 class="text-primary fw-bold mb-4">Tsh <?php echo number_format($product['price']); ?></h2>
            <?php endif; ?>

            <!-- Countdown Timer -->
            <?php 
            $show_timer = false;
            if (isset($product['offer_expires_at']) && !empty($product['offer_expires_at'])) {
                $expiry_time = strtotime($product['offer_expires_at']);
                if ($expiry_time > time()) {
                    $show_timer = true;
                }
            }
            ?>
            <?php if($show_timer): ?>
            <div class="mb-4 p-3 bg-light rounded-3 border border-danger border-opacity-25">
                <p class="text-danger fw-bold mb-2 small text-uppercase"><i class="bi bi-stopwatch-fill me-1"></i> Ofa inaisha baada ya:</p>
                <div class="d-flex gap-2" id="countdown-timer">
                    <div class="text-center bg-white p-2 rounded shadow-sm border" style="min-width: 60px;">
                        <span class="d-block fw-bold fs-4 text-dark" id="days">00</span><small class="text-muted" style="font-size: 10px;"><?php echo t('days'); ?></small>
                    </div>
                    <div class="text-center bg-white p-2 rounded shadow-sm border" style="min-width: 60px;">
                        <span class="d-block fw-bold fs-4 text-dark" id="hours">00</span><small class="text-muted" style="font-size: 10px;"><?php echo t('hours'); ?></small>
                    </div>
                    <div class="text-center bg-white p-2 rounded shadow-sm border" style="min-width: 60px;">
                        <span class="d-block fw-bold fs-4 text-dark" id="minutes">00</span><small class="text-muted" style="font-size: 10px;"><?php echo t('minutes'); ?></small>
                    </div>
                    <div class="text-center bg-white p-2 rounded shadow-sm border" style="min-width: 60px;">
                        <span class="d-block fw-bold fs-4 text-danger" id="seconds">00</span><small class="text-muted" style="font-size: 10px;"><?php echo t('seconds'); ?></small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <p class="lead text-muted mb-4">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </p>

            <hr class="my-4">

            <!-- Fomu ya Kuchagua (Size, Rangi, Idadi) -->
            <form action="cart.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['name']); ?>">
                <input type="hidden" name="product_price" value="<?php echo ($product['discount_price'] > 0) ? $product['discount_price'] : $product['price']; ?>">
                <input type="hidden" name="product_code" value="<?php echo htmlspecialchars($product['product_code'] ?? ''); ?>">

                <!-- 1. Chagua Size -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-uppercase small"><?php echo t('choose_size'); ?></label>
                    <div class="d-flex gap-2">
                        <?php 
                        // Vuta saizi kutoka database na uzitenganishe
                        $sizes = !empty($product['available_sizes']) ? explode(',', $product['available_sizes']) : [];
                        foreach($sizes as $size): 
                        ?>
                        <input type="radio" class="btn-check" name="size" id="size-<?php echo trim($size); ?>" value="<?php echo trim($size); ?>" required>
                        <label class="btn btn-outline-secondary rounded-0 px-3 py-2" for="size-<?php echo trim($size); ?>">
                            <?php echo $size; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 2. Chagua Rangi -->
                <div class="mb-4">
                    <label for="color" class="form-label fw-bold text-uppercase small"><?php echo t('choose_color'); ?></label>
                    <select class="form-select rounded-0 w-75" name="color" id="color" required>
                        <option value="" selected disabled>-- <?php echo t('choose_color'); ?> --</option>
                        <?php
                        // Vuta rangi kutoka database na uzitenganishe
                        $colors = !empty($product['available_colors']) ? explode(',', $product['available_colors']) : [];
                        foreach($colors as $color):
                        ?>
                        <option value="<?php echo trim($color); ?>"><?php echo trim($color); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Chagua Idadi -->
                <div class="mb-4">
                    <label for="quantity" class="form-label fw-bold text-uppercase small"><?php echo t('quantity_label'); ?></label>
                    <div class="input-group w-50">
                        <button class="btn btn-outline-secondary rounded-0" type="button" onclick="this.parentNode.querySelector('input[type=number]').stepDown()">-</button>
                        <input type="number" class="form-control text-center rounded-0 border-secondary" name="quantity" id="quantity" value="1" min="1" max="3" onchange="validateQuantity(this)">
                        <button class="btn btn-outline-secondary rounded-0" type="button" onclick="incrementQuantity()">+</button>
                    </div>
                </div>

                <!-- Vitufe vya Kununua -->
                <div class="d-grid gap-3 mt-5">
                    <button type="submit" name="add_to_cart" class="btn btn-dark btn-lg rounded-0 text-uppercase tracking-wide py-3">
                        <?php echo t('add_to_cart'); ?>
                    </button>
                    <!-- WhatsApp Button (Njia ya haraka) -->
                    <?php
                        $whatsapp_text = t('whatsapp_order_greeting') . ' ' . $product['name'];
                        if (!empty($product['product_code'])) {
                            $whatsapp_text .= ' (' . t('product_code_label') . ': ' . $product['product_code'] . ')';
                        }
                        $whatsapp_href = 'https://wa.me/255767557234?text=' . urlencode($whatsapp_text);
                    ?>
                    <a href="<?php echo htmlspecialchars($whatsapp_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success btn-lg rounded-0 text-uppercase tracking-wide py-3">
                        <i class="bi bi-whatsapp"></i> <?php echo t('buy_with_whatsapp'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews Section -->
    <div class="row mt-5 justify-content-center">
        <div class="col-12">
            <h3 class="fw-bold border-bottom pb-3 mb-4"><?php echo t('customer_reviews'); ?></h3>
        </div>
        <div class="col-12 mb-4">
            <?php if($review_count > 0): ?>
                <?php foreach($reviews as $review): ?>
                <div class="card border-0 shadow-sm mb-3 review-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h6 class="fw-bold"><?php echo htmlspecialchars($review['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                            <small class="text-muted"><?php echo date('d M Y', strtotime($review['created_at'])); ?></small>
                        </div>
                        <div class="text-warning mb-2 small">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi <?php echo ($i <= $review['rating']) ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted"><?php echo t('no_reviews_yet'); ?></p>
            <?php endif; ?>
        </div>
        <div class="col-12">
            <div class="card border-0 rounded-4 p-4 review-form-card">
                <h5 class="fw-bold mb-3"><?php echo t('write_your_review'); ?></h5>
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                     
                    <!-- Honeypot Field (Hidden from humans) -->
                    <div style="display: none;" aria-hidden="true">
                        <input type="text" name="email_confirm" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo t('your_name'); ?></label>
                        <input type="text" name="reviewer_name" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('rating'); ?></label>
                        <select name="rating" class="form-select" required>
                            <option value="5">5 - <?php echo t('excellent'); ?></option>
                            <option value="4">4 - <?php echo t('good'); ?></option>
                            <option value="3">3 - <?php echo t('average'); ?></option>
                            <option value="2">2 - <?php echo t('poor'); ?></option>
                            <option value="1">1 - <?php echo t('very_poor'); ?></option>
                        </select>
                        <div class="text-center text-warning mt-2" id="ratingStars">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi <?php echo ($i <= $avg_rating) ? 'bi-star-fill' : (($i - 0.5 <= $avg_rating) ? 'bi-star-half' : 'bi-star'); ?> fs-4" data-star="<?php echo $i; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between">
                            <?php echo t('review'); ?>
                            <small class="text-muted"><span id="charCount">0</span>/500</small>
                        </label>
                        <textarea name="comment" id="reviewComment" class="form-control" rows="3" required maxlength="500"></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn btn-dark rounded-pill px-4"><?php echo t('submit_review'); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function changeImage(src) {
    document.getElementById('mainImage').src = src;
}

function validateQuantity(input) {
    if (parseInt(input.value) > 3) {
        alert("<?php echo htmlspecialchars(t('quantity_limit_message')); ?>");
        input.value = 3;
    }
}

function incrementQuantity() {
    const input = document.getElementById('quantity');
    if (parseInt(input.value) < 3) {
        input.stepUp();
    } else {
        alert("<?php echo htmlspecialchars(t('quantity_limit_message')); ?>");
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const reviewAlert = document.querySelector('.alert-review-success');
    if (reviewAlert) {
        setTimeout(() => {
            const alert = new bootstrap.Alert(reviewAlert);
            alert.close();
        }, 5000); // 5000ms = sekunde 5
    }

    const reviewTextarea = document.getElementById('reviewComment');
    const charCount = document.getElementById('charCount');
    if (reviewTextarea && charCount) {
        reviewTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }

    const ratingSelect = document.querySelector('select[name="rating"]');
    const ratingStars = document.getElementById('ratingStars');
    if (ratingSelect && ratingStars) {
        const updateStars = function(val) {
            val = parseInt(val);
            ratingStars.querySelectorAll('i').forEach(function(star) {
                var idx = parseInt(star.dataset.star);
                star.className = 'bi ' + (idx <= val ? 'bi-star-fill' : 'bi-star') + ' fs-4';
            });
        };
        ratingSelect.addEventListener('change', function() {
            updateStars(this.value);
        });
        updateStars(ratingSelect.value);
    }
});

<?php if($show_timer): ?>
// Countdown Timer Script
const countDownDate = new Date("<?php echo date('M d, Y H:i:s', strtotime($product['offer_expires_at'])); ?>").getTime();

const x = setInterval(function() {
  const now = new Date().getTime();
  const distance = countDownDate - now;

  const days = Math.floor(distance / (1000 * 60 * 60 * 24));
  const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((distance % (1000 * 60)) / 1000);

  document.getElementById("days").innerHTML = days < 10 ? "0" + days : days;
  document.getElementById("hours").innerHTML = hours < 10 ? "0" + hours : hours;
  document.getElementById("minutes").innerHTML = minutes < 10 ? "0" + minutes : minutes;
  document.getElementById("seconds").innerHTML = seconds < 10 ? "0" + seconds : seconds;

  if (distance < 0) {
    clearInterval(x);
    document.getElementById("countdown-timer").innerHTML = "<span class='text-danger fw-bold'>Ofa imekwisha!</span>";
  }
}, 1000);
<?php endif; ?>

// Image Zoom Script
document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.zoom-container');
    const img = document.getElementById('mainImage');

    if (container && img) {
        container.addEventListener('mousemove', (e) => {
            const { left, top, width, height } = container.getBoundingClientRect();
            const x = ((e.clientX - left) / width) * 100;
            const y = ((e.clientY - top) / height) * 100;
            img.style.transformOrigin = `${x}% ${y}%`;
            img.style.transform = 'scale(2)';
        });

        container.addEventListener('mouseleave', () => {
            img.style.transformOrigin = 'center center';
            img.style.transform = 'scale(1)';
        });
    }
});
</script>

<style>
    .review-card { background-color: #fff; }
    .review-form-card { background-color: #fff; }
    [data-bs-theme="dark"] .review-card,
    [data-bs-theme="dark"] .review-form-card { background-color: #2b3035; }
    [data-bs-theme="dark"] .review-form-card input,
    [data-bs-theme="dark"] .review-form-card select,
    [data-bs-theme="dark"] .review-form-card textarea { background-color: #1d1f23; color: #f8f9fa; border-color: #444; }
    [data-bs-theme="dark"] .border-bottom { border-color: #444 !important; }
</style>
<?php include 'includes/footer.php'; ?>
