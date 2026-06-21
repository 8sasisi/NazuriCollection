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
    <div class="row align-items-center mb-5">
        <div class="col-lg-6">
            <h1 class="display-4 fw-bold mb-4" style="font-family: 'Playfair Display', serif;"><?php echo t('about'); ?> Grant Fashions</h1>
            <p class="lead text-body-secondary mb-4">
                <?php echo t('about_us_intro'); ?>
            </p>
            <p>
                <?php echo t('about_us_goal'); ?>
            </p>
            <a href="shop.php" class="btn btn-primary mt-3"><?php echo t('all_products'); ?></a>
        </div>
        <div class="col-lg-6 mt-4 mt-lg-0">
            <!-- Placeholder image - Badilisha na picha halisi ya duka au bidhaa -->
            <img src="uploads/IMG-20251202-WA0053.jpg" alt="Duka la Grant Fashions" class="img-fluid rounded shadow-sm" loading="lazy">
        </div>
    </div>

    <!-- Location & Contact Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="bg-body-tertiary p-5 rounded-4 shadow-sm border">
                <div class="row text-center text-md-start">
                    <!-- Location -->
                    <div class="col-md-6 mb-4 mb-md-0 border-md-end">
                        <h3 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-danger"></i> <?php echo t('location'); ?></h3>
                        <h5 class="fw-bold text-body">Dar es Salaam, Kariakoo</h5>
                        <p class="mb-0 fs-5 text-body"><?php echo t('office_street'); ?></p>
                        <small class="text-body-secondary"><?php echo t('office_visit_message'); ?></small>
                    </div>
                    
                    <!-- Social Media -->
                    <div class="col-md-6 ps-md-5">
                        <h3 class="fw-bold mb-3"><i class="bi bi-share-fill text-primary"></i> <?php echo t('social_media'); ?></h3>
                        <p class="mb-3 text-body-secondary"><?php echo t('follow_for_updates'); ?></p>
                        <div class="d-flex gap-3 justify-content-center justify-content-md-start">
                            <a href="#" class="btn btn-success rounded-circle p-3"><i class="bi bi-whatsapp fs-5"></i></a>
                            <a href="#" class="btn btn-primary rounded-circle p-3"><i class="bi bi-facebook fs-5"></i></a>
                            <a href="#" class="btn btn-danger rounded-circle p-3" style="background: #E1306C; border: none;"><i class="bi bi-instagram fs-5"></i></a>
                            <a href="#" class="btn btn-dark rounded-circle p-3"><i class="bi bi-twitter-x fs-5"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
