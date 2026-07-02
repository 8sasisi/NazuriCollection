<?php 
include 'includes/header.php'; 

// Fallback for translation function if not defined in header
if (!function_exists('t')) {
    function t($key) {
        global $lang;
        if (!isset($lang) && file_exists('languages/sw.php')) {
            include 'languages/sw.php';
        }
        return isset($lang[$key]) ? $lang[$key] : $key;
    }
}
?>

<div class="container py-5">
    <!-- Intro Section -->
    <div class="row align-items-center mb-5 g-5">
        <div class="col-lg-6 col-xl-5">
            <h1 class="display-4 fw-bold mb-4" style="font-family: 'Playfair Display', serif;"><?php echo t('about'); ?> <?php echo htmlspecialchars($shop_name ?? 'Grant Fashions'); ?></h1>
            <p class="lead text-body-secondary mb-4">
                <?php echo t('about_us_intro'); ?>
            </p>
            <p>
                <?php echo t('about_us_goal'); ?>
            </p>
            <a href="shop.php" class="btn btn-primary mt-3"><?php echo t('all_products'); ?></a>
        </div>
        <div class="col-lg-6 col-xl-7 mt-4 mt-lg-0">
            <img src="<?php echo !empty($shop_logo) ? 'uploads/' . htmlspecialchars($shop_logo) : 'uploads/default-about.jpg'; ?>" alt="<?php echo htmlspecialchars($shop_name ?? 'Grant Fashions'); ?>" class="img-fluid rounded shadow-sm w-100" loading="lazy" style="max-height: 500px; object-fit: cover;">
        </div>
    </div>

    <!-- Location & Contact Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="bg-body-tertiary p-5 rounded-4 shadow-sm border">
                <div class="row text-center text-md-start g-4">
                    <!-- Location -->
                    <div class="col-md-6 col-xl-4 mb-4 mb-md-0">
                        <h3 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-danger"></i> <?php echo t('location'); ?></h3>
                        <h5 class="fw-bold text-body"><?php echo htmlspecialchars($site_settings['address'] ?? 'Dar es Salaam, Tanzania'); ?></h5>
                        <p class="mb-0 fs-5 text-body"><?php echo t('office_street'); ?></p>
                        <small class="text-body-secondary"><?php echo t('office_visit_message'); ?></small>
                    </div>
                    
                    <!-- Social Media -->
                    <div class="col-md-6 col-xl-4 mb-4 mb-md-0">
                        <h3 class="fw-bold mb-3"><i class="bi bi-share-fill text-primary"></i> <?php echo t('social_media'); ?></h3>
                        <p class="mb-3 text-body-secondary"><?php echo t('follow_for_updates'); ?></p>
                        <div class="d-flex gap-3 justify-content-center justify-content-md-start">
                            <a href="<?php echo htmlspecialchars($site_settings['whatsapp'] ?? '#'); ?>" class="btn btn-success rounded-circle p-3" target="_blank" rel="noopener"><i class="bi bi-whatsapp fs-5"></i></a>
                            <a href="<?php echo htmlspecialchars($site_settings['facebook'] ?? '#'); ?>" class="btn btn-primary rounded-circle p-3" target="_blank" rel="noopener"><i class="bi bi-facebook fs-5"></i></a>
                            <a href="<?php echo htmlspecialchars($site_settings['instagram'] ?? '#'); ?>" class="btn btn-danger rounded-circle p-3" style="background: #E1306C; border: none;" target="_blank" rel="noopener"><i class="bi bi-instagram fs-5"></i></a>
                            <a href="<?php echo htmlspecialchars($site_settings['twitter'] ?? '#'); ?>" class="btn btn-dark rounded-circle p-3" target="_blank" rel="noopener"><i class="bi bi-twitter-x fs-5"></i></a>
                        </div>
                    </div>

                    <!-- Business Hours -->
                    <div class="col-md-6 col-xl-4">
                        <h3 class="fw-bold mb-3"><i class="bi bi-clock-fill text-primary"></i> <?php echo t('business_hours'); ?></h3>
                        <ul class="list-unstyled text-body-secondary">
                            <li class="mb-2"><?php echo t('mon_sat'); ?></li>
                            <li class="mb-2"><?php echo t('sunday_hours'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
