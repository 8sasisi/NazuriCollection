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
            $regex_parts = array_map(function($word) {
                return implode('[\W_]*', array_map('preg_quote', str_split($word)));
            }, $bad_words);
            $pattern = '/(' . implode('|', $regex_parts) . ')/i';

            if (preg_match($pattern, $comment)) {
                $_SESSION['review_bad_word_error'] = "Maoni yako yana maneno yasiyofaa au viungo (links) visivyoruhusiwa. Tafadhali rekebisha.";
                header("Location: product_details.php?id=$id");
                exit;
            }
        }
        
        if ($rating >= 1 && $rating <= 5 && !empty($name) && mb_strlen($name) <= $name_max_length && mb_strlen($comment) <= $max_length) {
            $stmt = $conn->prepare("INSERT INTO reviews (product_id, customer_name, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $name, $rating, $comment]);
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
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Nyumbani</a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none text-muted">Duka</a></li>
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
        <i class="bi bi-check-circle-fill me-2"></i> Asante kwa maoni yako! 
        Review yako yanasubiri uhakiki kabla ya kuonekana.
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

    <div class="row mt-4">
        <!-- Sehemu ya Picha -->
        <div class="col-md-6 mb-4">
            <style>
                .zoom-container { overflow: hidden; cursor: crosshair; }
                .zoom-container img { transition: transform 0.3s ease-out; }
                /* Wakati wa hover, punguza muda wa transition ili iwe responsive kwenye mouse movement */
                .zoom-container:hover img { transition: transform 0.05s linear; }
            </style>
            <div class="card border-0 shadow-sm zoom-container">
                <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                     id="mainImage"
                     class="card-img-top rounded" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     style="object-fit: cover; height: 600px;" 
                     onerror="this.src='uploads/no-image.png'"
                     loading="lazy">
            </div>
            
            <?php if(count($gallery_images) > 0): ?>
            <div class="d-flex gap-2 mt-3 overflow-auto pb-2">
                <!-- Picha Kuu (Thumbnail) -->
                <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                     class="rounded border gallery-thumb" 
                     style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;"
                     onclick="changeImage(this.src)">
                     
                <!-- Picha za Gallery -->
                <?php foreach($gallery_images as $g_img): ?>
                <img src="uploads/<?php echo htmlspecialchars($g_img['image']); ?>" 
                     class="rounded border gallery-thumb" 
                     style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;"
                     onclick="changeImage(this.src)">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sehemu ya Maelezo na Chaguzi -->
        <div class="col-md-6">
            <h1 class="display-5 fw-bold" style="font-family: 'Playfair Display', serif;">
                <?php echo htmlspecialchars($product['name']); ?>
                <?php if(isset($product['offer_badge']) && $product['offer_badge'] == 1): ?>
                    <?php if(isset($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                        <span class="badge bg-danger fs-6 align-middle ms-2"><?php echo $product['discount_percentage']; ?>% OFF</span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6 align-middle ms-2">OFA</span>
                    <?php endif; ?>
                <?php endif; ?>
            </h1>
            <div class="mb-3">
                <span class="badge bg-dark text-uppercase tracking-wide"><?php echo htmlspecialchars($product['category']); ?></span>
            </div>
            
            <!-- Star Rating Display -->
            <div class="mb-3 text-warning">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="bi <?php echo ($i <= $avg_rating) ? 'bi-star-fill' : (($i - 0.5 <= $avg_rating) ? 'bi-star-half' : 'bi-star'); ?>"></i>
                <?php endfor; ?>
                <span class="text-muted small ms-2">(<?php echo $review_count; ?> reviews)</span>
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
                        <span class="d-block fw-bold fs-4 text-dark" id="days">00</span><small class="text-muted" style="font-size: 10px;">SIKU</small>
                    </div>
                    <div class="text-center bg-white p-2 rounded shadow-sm border" style="min-width: 60px;">
                        <span class="d-block fw-bold fs-4 text-dark" id="hours">00</span><small class="text-muted" style="font-size: 10px;">SAA</small>
                    </div>
                    <div class="text-center bg-white p-2 rounded shadow-sm border" style="min-width: 60px;">
                        <span class="d-block fw-bold fs-4 text-dark" id="minutes">00</span><small class="text-muted" style="font-size: 10px;">DAKIKA</small>
                    </div>
                    <div class="text-center bg-white p-2 rounded shadow-sm border" style="min-width: 60px;">
                        <span class="d-block fw-bold fs-4 text-danger" id="seconds">00</span><small class="text-muted" style="font-size: 10px;">SEKUNDE</small>
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

                <!-- 1. Chagua Size -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-uppercase small">Chagua Size</label>
                    <div class="d-flex gap-2">
                        <?php 
                        // Vuta saizi kutoka database na uzitenganishe
                        $sizes = !empty($product['available_sizes']) ? array_filter(array_map('trim', explode(',', $product['available_sizes']))) : [];
                        if (count($sizes) > 0):
                            foreach($sizes as $size): 
                        ?>
                        <input type="radio" class="btn-check" name="size" id="size-<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <label class="btn btn-outline-secondary rounded-0 px-3 py-2" for="size-<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                        <?php 
                            endforeach;
                        else:
                            // No sizes defined: provide a default hidden value so cart validation passes
                        ?>
                        <input type="hidden" name="size" value="Standard">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Chagua Rangi -->
                <div class="mb-4">
                    <label for="color" class="form-label fw-bold text-uppercase small">Chagua Rangi</label>
                    <?php
                    // Vuta rangi kutoka database na uzitenganishe
                    $colors = !empty($product['available_colors']) ? array_filter(array_map('trim', explode(',', $product['available_colors']))) : [];
                    if (count($colors) > 0):
                    ?>
                    <select class="form-select rounded-0 w-75" name="color" id="color" required>
                        <option value="" selected disabled>-- Chagua Rangi --</option>
                        <?php foreach($colors as $color): ?>
                            <option value="<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                        <input type="hidden" name="color" value="Default">
                    <?php endif; ?>
                </div>

                <!-- 3. Chagua Idadi -->
                <div class="mb-4">
                    <label for="quantity" class="form-label fw-bold text-uppercase small">Idadi</label>
                    <div class="input-group w-50">
                        <button class="btn btn-outline-secondary rounded-0" type="button" onclick="this.parentNode.querySelector('input[type=number]').stepDown()">-</button>
                        <input type="number" class="form-control text-center rounded-0 border-secondary" name="quantity" id="quantity" value="1" min="1" max="3" onchange="validateQuantity(this)">
                        <button class="btn btn-outline-secondary rounded-0" type="button" onclick="incrementQuantity()">+</button>
                    </div>
                </div>

                <!-- Vitufe vya Kununua -->
                <div class="d-grid gap-3 mt-5">
                    <button type="submit" name="add_to_cart" class="btn btn-dark btn-lg rounded-0 text-uppercase tracking-wide py-3">
                        Ongeza kwenye Kapu
                    </button>
                    <!-- WhatsApp Button (Njia ya haraka) -->
                    <a href="https://wa.me/255767557234?text=Habari,%20nahitaji%20<?php echo urlencode($product['name']); ?>" class="btn btn-success btn-lg rounded-0 text-uppercase tracking-wide py-3">
                        <i class="bi bi-whatsapp"></i> Nunua kwa WhatsApp
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h3 class="fw-bold border-bottom pb-3 mb-4">Maoni ya Wateja</h3>
        </div>
        <div class="col-md-6">
            <?php if($review_count > 0): ?>
                <?php foreach($reviews as $review): ?>
                <div class="card border-0 shadow-sm mb-3">
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
                <p class="text-muted">Hakuna maoni bado. Kuwa wa kwanza kutoa maoni!</p>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <div class="card bg-light border-0 rounded-4 p-4">
                <h5 class="fw-bold mb-3">Andika Maoni Yako</h5>
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label class="form-label">Jina Lako</label>
                        <input type="text" name="reviewer_name" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select" required>
                            <option value="5">5 - Bora Sana</option>
                            <option value="4">4 - Nzuri</option>
                            <option value="3">3 - Wastani</option>
                            <option value="2">2 - Mbaya</option>
                            <option value="1">1 - Mbaya Sana</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between">
                            Maoni 
                            <small class="text-muted"><span id="charCount">0</span>/500</small>
                        </label>
                        <textarea name="comment" id="reviewComment" class="form-control" rows="3" required maxlength="500"></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn btn-dark rounded-pill px-4">Tuma Maoni</button>
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

<?php include 'includes/footer.php'; ?>
