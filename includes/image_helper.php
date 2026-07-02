<?php
// Responsive image helper with on-demand WebP generation (security by design)
// Usage: echo responsive_picture('uploads/image.png', 'Alt text', ['class'=>'img-fluid','sizes'=>'(max-width:768px) 100vw, 33vw']);

function responsive_picture($relativePath, $alt = '', $attrs = [], $widths = [320, 640, 1024, 1600]) {
    // Sanitize inputs
    $relativePath = ltrim($relativePath, '/\\');
    $uploadsRoot = realpath(__DIR__ . '/../'); // project root
    $srcPath = $uploadsRoot . DIRECTORY_SEPARATOR . $relativePath;
    if (!file_exists($srcPath)) {
        // Return a placeholder image tag
        $placeholder = 'uploads/no-image.png';
        $classAttr = isset($attrs['class']) ? ' class="' . htmlspecialchars($attrs['class'], ENT_QUOTES, 'UTF-8') . '"' : '';
        return '<img src="' . $placeholder . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" loading="lazy"' . $classAttr . ' />';
    }

    // Prepare directory for webp derivatives
    $webpDir = $uploadsRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'webp';
    if (!is_dir($webpDir)) @mkdir($webpDir, 0755, true);

    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    $basename = pathinfo($srcPath, PATHINFO_BASENAME);
    $nameOnly = pathinfo($srcPath, PATHINFO_FILENAME);

    $srcsetWebp = [];
    $srcsetOrig = [];

    foreach ($widths as $w) {
        $destWebp = $webpDir . DIRECTORY_SEPARATOR . $w . '_' . $nameOnly . '.webp';
        $relWebp = 'uploads/webp/' . $w . '_' . $nameOnly . '.webp';

        if (!file_exists($destWebp)) {
            // Try to generate using GD
            if (extension_loaded('gd')) {
                try {
                    // Load source
                    if (in_array($ext, ['jpg','jpeg'])) {
                        $srcImg = imagecreatefromjpeg($srcPath);
                    } elseif ($ext === 'png') {
                        $srcImg = imagecreatefrompng($srcPath);
                    } elseif ($ext === 'gif') {
                        $srcImg = imagecreatefromgif($srcPath);
                    } else {
                        $srcImg = false;
                    }

                    if ($srcImg) {
                        $origW = imagesx($srcImg);
                        $origH = imagesy($srcImg);
                        $h = (int) round($w * $origH / $origW);
                        $dst = imagecreatetruecolor($w, $h);

                        // Preserve PNG transparency
                        if ($ext === 'png') {
                            imagealphablending($dst, false);
                            imagesavealpha($dst, true);
                            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                            imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
                        }

                        imagecopyresampled($dst, $srcImg, 0,0,0,0, $w, $h, $origW, $origH);
                        // Quality 80
                        imagewebp($dst, $destWebp, 80);
                        imagedestroy($dst);
                        imagedestroy($srcImg);
                    }
                } catch (Exception $e) {
                    // silently fail; fallback to original
                }
            }
        }

        if (file_exists($destWebp)) {
            $srcsetWebp[] = $relWebp . ' ' . $w . 'w';
        }
        // fallback to original for srcset too; original URL relative to project root
        $relOrig = $relativePath;
        $srcsetOrig[] = $relOrig . ' ' . $w . 'w';
    }

    // Build attributes
    $classAttr = isset($attrs['class']) ? ' class="' . htmlspecialchars($attrs['class'], ENT_QUOTES, 'UTF-8') . '"' : '';
    $sizesAttr = isset($attrs['sizes']) ? ' sizes="' . htmlspecialchars($attrs['sizes'], ENT_QUOTES, 'UTF-8') . '"' : '';
    $altAttr = ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';

    // Choose largest existing file as default src
    $defaultSrc = $relativePath;

    // Build picture HTML
    $html = '<picture>';
    if (!empty($srcsetWebp)) {
        $html .= '<source type="image/webp" srcset="' . implode(', ', $srcsetWebp) . '"' . $sizesAttr . '>'; 
    }
    $html .= '<img src="' . $defaultSrc . '" srcset="' . implode(', ', $srcsetOrig) . '"' . $sizesAttr . $classAttr . $altAttr . ' loading="lazy">';
    $html .= '</picture>';

    return $html;
}
