<?php 
require_once 'config/db_connect.php';
include 'includes/header.php'; 

// Vuta sliders zinazoonekana (active) kutoka kwenye database
// Support both numeric and textual status values (security: avoid relying on client input for schema)
$stmt = $conn->query("SELECT * FROM sliders WHERE status IN ('active', 1, '1') ORDER BY sort_order ASC");
$sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Hero Section -->
<div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="6000">
    <div class="carousel-inner">
        <?php if (!empty($sliders)): ?>
            <?php foreach ($sliders as $index => $slider): ?>
            <?php
                $video_path = !empty($slider['video_path']) ? 'uploads/' . $slider['video_path'] : '';
                $has_video = $video_path && file_exists(__DIR__ . '/' . $video_path);
                $fallback_image = (!empty($shop_logo) && file_exists(__DIR__ . '/uploads/' . $shop_logo)) ? ('uploads/' . $shop_logo) : ('https://via.placeholder.com/1600x900?text=' . urlencode($slider['title'] ?? $shop_name));
            ?>
            <div class="carousel-item <?php echo ($index == 0) ? 'active' : ''; ?> hero-slide">
                <?php
                    // Prepare optimized video sources if available
                    $video_webm = '';
                    $video_mp4 = '';
                    if ($has_video) {
                        $base = pathinfo($video_path, PATHINFO_FILENAME);
                        $dir = dirname($video_path);
                        $maybe_webm = $dir . '/' . $base . '.webm';
                        $maybe_mp4 = $dir . '/' . $base . '.mp4';
                        if (file_exists(__DIR__ . '/' . $maybe_webm)) $video_webm = $maybe_webm;
                        if (file_exists(__DIR__ . '/' . $maybe_mp4)) $video_mp4 = $maybe_mp4;
                        // If original file exists and no specific variant found, use it as mp4
                        if (empty($video_webm) && empty($video_mp4) && file_exists(__DIR__ . '/' . $video_path)) {
                            $video_mp4 = $video_path;
                        }
                    }
                ?>

                <?php if ($has_video && ($video_webm || $video_mp4)): ?>
                    <!-- Deferred video: sources injected by JS on suitable connections/devices -->
                    <video muted loop playsinline class="deferred-video d-block w-100 h-100" style="object-fit: cover;" preload="none" poster="<?php echo htmlspecialchars($fallback_image, ENT_QUOTES, 'UTF-8'); ?>" data-webm="<?php echo htmlspecialchars($video_webm, ENT_QUOTES, 'UTF-8'); ?>" data-mp4="<?php echo htmlspecialchars($video_mp4, ENT_QUOTES, 'UTF-8'); ?>">
                        <p class="visually-hidden">Your browser does not support the video tag.</p>
                    </video>
                    <noscript>
                        <?php if ($video_webm): ?>
                            <video autoplay muted loop playsinline class="d-block w-100 h-100" style="object-fit: cover;" poster="<?php echo htmlspecialchars($fallback_image, ENT_QUOTES, 'UTF-8'); ?>">
                                <source src="<?php echo htmlspecialchars($video_webm, ENT_QUOTES, 'UTF-8'); ?>" type="video/webm">
                                <?php if ($video_mp4): ?><source src="<?php echo htmlspecialchars($video_mp4, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4"><?php endif; ?>
                            </video>
                        <?php else: ?>
                            <video autoplay muted loop playsinline class="d-block w-100 h-100" style="object-fit: cover;" poster="<?php echo htmlspecialchars($fallback_image, ENT_QUOTES, 'UTF-8'); ?>">
                                <source src="<?php echo htmlspecialchars($video_mp4, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                            </video>
                        <?php endif; ?>
                    </noscript>
                <?php else: ?>
                    <img src="<?php echo $fallback_image; ?>" class="d-block w-100 h-100" style="object-fit: cover;" alt="<?php echo htmlspecialchars($slider['title']); ?>">
                <?php endif; ?>

                <div class="position-absolute top-0 start-0 w-100 h-100 bg-black opacity-50"></div>
                <div class="carousel-caption d-flex flex-column align-items-center justify-content-center top-0 bottom-0 px-3">
                    <h1 class="display-3 fw-bold mb-3"><?php echo htmlspecialchars($slider['title']); ?></h1>
                    <p class="lead mb-4"><?php echo htmlspecialchars($slider['subtitle']); ?></p>
                    <a href="<?php echo htmlspecialchars($slider['button_link']); ?>" class="btn btn-primary btn-lg px-5 rounded-pill"><?php echo htmlspecialchars($slider['button_text']); ?></a>
                </div>
            </div>

            <?php /* Video loader script: defers heavy sources on slow/limited connections and mobile */ ?>
            <script>
            (function(){
                function shouldLoadVideo(){
                    try{
                        if (window.matchMedia('(max-width: 768px)').matches) return false; // avoid autoplay on small screens
                        var nav = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
                        if (nav) {
                            if (nav.saveData) return false; // user opted to save data
                            var slow = ['slow-2g','2g','3g'];
                            if (nav.effectiveType && slow.indexOf(nav.effectiveType) !== -1) return false;
                        }
                    }catch(e){}
                    return true;
                }

                document.addEventListener('DOMContentLoaded', function(){
                    if (!shouldLoadVideo()) return;
                    var videos = document.querySelectorAll('.deferred-video');
                    videos.forEach(function(v){
                        var webm = v.getAttribute('data-webm');
                        var mp4 = v.getAttribute('data-mp4');
                        if (webm){ var s = document.createElement('source'); s.src = webm; s.type = 'video/webm'; v.appendChild(s); }
                        if (mp4){ var s2 = document.createElement('source'); s2.src = mp4; s2.type = 'video/mp4'; v.appendChild(s2); }
                        try{ v.load(); v.play().catch(function(){}); }catch(e){}
                    });
                });
            })();
            </script>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Fallback ikiwa hakuna slider kwenye database -->
            <div class="carousel-item active hero-slide" style="background-color: #333;">
                 <div class="carousel-caption d-flex flex-column align-items-center justify-content-center top-0 bottom-0">
                    <h1 class="display-3 fw-bold mb-3"><?php echo t('welcome_nazuri_collections'); ?></h1>
                    <a href="shop.php" class="btn btn-primary btn-lg px-5 rounded-pill"><?php echo t('go_to_shop'); ?></a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Controls (Vitufe vya kusogeza) -->
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>

<!-- Promotional Banner Section -->
<section class="container-fluid px-0 my-5">
    <div class="position-relative" style="height: 400px;">
        <!-- Background Image with Dark Overlay -->
        <?php
            $promo_rel = 'uploads/promo_banner.jpg';
            $promo_path = (file_exists(__DIR__ . '/' . $promo_rel)) ? $promo_rel : 'https://via.placeholder.com/1600x400?text=' . urlencode($shop_name . ' Promo');
        ?>
        <div class="position-absolute top-0 start-0 w-100 h-100" 
             style="background: url('<?php echo $promo_path; ?>') no-repeat center center; background-size: cover; filter: brightness(0.4);">
        </div>

        
        <!-- Maneno ya Kuvutia -->
        <div class="position-absolute top-50 start-50 translate-middle text-center text-white w-100 px-3">
            <h2 class="display-4 fw-bold mb-3" style="font-family: 'Playfair Display', serif;"><?php echo t('promo_headline'); ?></h2>
            <p class="lead mb-4 fs-4"><?php echo t('promo_description'); ?></p>
            <a href="shop.php" class="btn btn-outline-light btn-lg rounded-pill px-5"><?php echo t('view_more'); ?></a>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold"><?php echo t('new_arrivals'); ?></h2>
            <a href="shop.php" class="text-decoration-none text-dark fw-bold"><?php echo t('view_all'); ?> <i class="bi bi-arrow-right"></i></a>
        </div>
        
        <div class="row">
            <?php
            // Vuta bidhaa 4 za mwisho
            $stmt = $conn->prepare("SELECT * FROM products WHERE status = 'active' ORDER BY created_at DESC LIMIT 4");
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($products):
                foreach($products as $product):
            ?>
            <div class="col-md-6 col-lg-3 col-xxl-2 mb-4">
                <div class="card h-100 shadow-sm">
                    <!-- Responsive image with WebP generation -->
                    <?php require_once __DIR__ . '/includes/image_helper.php'; echo responsive_picture('uploads/' . $product['image'], $product['name'], ['class'=>'card-img-top','sizes'=>'(max-width: 768px) 100vw, 25vw']); ?>
                    <div class="card-body text-center">
                        <h5 class="card-title fs-6"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <?php if($product['discount_price'] > 0): ?>
                            <?php 
                                $percentage = ($product['price'] > 0) ? (($product['price'] - $product['discount_price']) / $product['price']) * 100 : 0;
                                $price_color = ($percentage >= 50) ? 'text-success' : 'text-danger';
                            ?>
                            <p class="card-text mb-2">
                                <span class="text-decoration-line-through text-muted small me-1">Tsh <?php echo number_format($product['price']); ?></span>
                                <span class="fw-bold <?php echo $price_color; ?>">Tsh <?php echo number_format($product['discount_price']); ?></span>
                            </p>
                        <?php else: ?>
                            <p class="card-text text-muted fw-bold">Tsh <?php echo number_format($product['price']); ?></p>
                        <?php endif; ?>
                        <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-dark btn-sm stretched-link"><?php echo t('view'); ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
                <p class="text-center"><?php echo t('no_products_available'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Pre-Order Collections Section -->
<section class="py-5 bg-light border-top" id="preorder">
    <div class="container">
        <div class="text-center mb-5">
                <h2 class="fw-bold display-6" style="font-family: 'Playfair Display', serif;"><?php echo t('preorder_collections'); ?></h2>
                <p class="text-muted"><?php echo t('preorder_description'); ?></p>
        </div>

        <?php
        // Makundi yanayohitajika kwenye Pre-Order
        $preorder_categories = [
            'abaya' => t('abaya') . ' ' . t('collection'),
            'gown' => t('gowns') . ' ' . t('collection'),
            'two_pieces' => t('two_pieces') . ' ' . t('collection')
        ];

        $is_first_category = true;
        foreach($preorder_categories as $cat_key => $cat_name):
            // Vuta bidhaa za preorder kwa kila kundi
            $stmt = $conn->prepare("SELECT * FROM products WHERE status = 'preorder' AND category = :cat ORDER BY created_at DESC LIMIT 4");
            $stmt->execute([':cat' => $cat_key]);
            $pre_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($pre_products):
                // Weka mstari wa kutenganisha kama sio kundi la kwanza
                if (!$is_first_category) {
                    echo '<hr class="my-5 border-secondary opacity-25">';
                }
        ?>
        <div class="mb-5">
            <div class="d-flex align-items-center mb-4">
                <h3 class="fw-bold fs-4 text-dark mb-0 me-3"><?php echo htmlspecialchars($cat_name); ?></h3>
                <span class="badge bg-warning text-dark rounded-pill small"><?php echo t('coming_soon'); ?></span>
            </div>
            <div class="row">
                <?php foreach($pre_products as $product): ?>
                <div class="col-md-6 col-lg-3 col-xxl-2 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="position-relative">
                            <?php require_once __DIR__ . '/includes/image_helper.php'; echo responsive_picture('uploads/' . $product['image'], $product['name'], ['class'=>'card-img-top','sizes'=>'(max-width: 768px) 100vw, 25vw']); ?>
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-dark text-white shadow-sm">Pre-Order</span>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title fs-6 fw-bold"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <?php if($product['discount_price'] > 0): ?>
                                <?php 
                                    $percentage = ($product['price'] > 0) ? (($product['price'] - $product['discount_price']) / $product['price']) * 100 : 0;
                                    $price_color = ($percentage >= 50) ? 'text-success' : 'text-danger';
                                ?>
                                <p class="card-text mb-2">
                                    <span class="text-decoration-line-through text-muted small me-1">Tsh <?php echo number_format($product['price']); ?></span>
                                    <span class="fw-bold <?php echo $price_color; ?>">Tsh <?php echo number_format($product['discount_price']); ?></span>
                                </p>
                            <?php else: ?>
                                <p class="card-text text-primary fw-bold mb-2">Tsh <?php echo number_format($product['price']); ?></p>
                            <?php endif; ?>
                            <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-dark btn-sm rounded-pill px-4"><?php echo t('place_order'); ?></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
            $is_first_category = false;
            endif; 
        endforeach; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>