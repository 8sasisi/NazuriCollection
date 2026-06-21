<footer class="bg-dark text-white py-5 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <h5 class="text-uppercase" style="font-family: 'Playfair Display', serif;"><?php echo htmlspecialchars($shop_name ?? 'Grant Fashions'); ?></h5>
                <p class="small text-white-50"><?php echo t('footer_description'); ?></p>
            </div>
            <div class="col-md-4 mb-3">
                <h5><?php echo t('contact_information'); ?></h5>
                <ul class="list-unstyled small text-white-50">
                    <li><i class="bi bi-geo-alt me-2"></i> <?php echo htmlspecialchars($site_settings['address'] ?? 'Dar es Salaam, Tanzania'); ?></li>
                    <li><i class="bi bi-whatsapp me-2"></i> <?php echo htmlspecialchars($shop_phone ?? '0767557234'); ?></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary">
        <p class="text-center small text-white-50 mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($shop_name ?? 'Grant Fashions'); ?>. <?php echo t('all_rights_reserved'); ?></p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Scroll to Top Button -->
<a href="#" id="scrollToTopBtn" class="btn btn-dark rounded-circle shadow-lg" title="<?php echo t('back_to_top'); ?>">
    <i class="bi bi-arrow-up"></i>
</a>

<script>
    // Scroll to Top Button Logic
    const scrollToTopBtn = document.getElementById("scrollToTopBtn");

    window.onscroll = function() {
        if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
            scrollToTopBtn.style.display = "block";
        } else {
            scrollToTopBtn.style.display = "none";
        }
    };

    scrollToTopBtn.addEventListener("click", function(e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
</script>
</body>
</html>