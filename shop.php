<?php 
require_once 'config/db_connect.php';
include 'includes/header.php'; 

// Angalia kama kuna category imechaguliwa
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$allowed_categories = ['abaya', 'gown', 'two_pieces', 'guberi'];
if (!in_array($category, $allowed_categories, true)) {
    $category = '';
}

$allowed_sort_orders = ['newest', 'price_asc', 'price_desc'];
if (!in_array($sort_order, $allowed_sort_orders, true)) {
    $sort_order = 'newest';
}

$whereClauses = [];
$params = [];

if ($category) {
    $whereClauses[] = "category = :category";
    $params[':category'] = $category;
}

if ($search_term) {
    $whereClauses[] = "(name LIKE :search OR description LIKE :search OR keywords LIKE :search)";
    $params[':search'] = "%" . $search_term . "%";
}

$whereClauses[] = "status = 'active'";

$whereClause = "WHERE " . implode(' AND ', $whereClauses);

// --- Sorting Logic ---
$orderByClause = "ORDER BY created_at DESC"; // Default
switch ($sort_order) {
    case 'price_asc':
        $orderByClause = "ORDER BY price ASC";
        break;
    case 'price_desc':
        $orderByClause = "ORDER BY price DESC";
        break;
    case 'newest':
    default:
        $orderByClause = "ORDER BY created_at DESC";
        break;
}
// --- Pagination Logic ---
$products_per_page = 8; // Idadi ya bidhaa kwa kila ukurasa

// Pata jumla ya bidhaa zote zinazoendana na vigezo
$count_sql = "SELECT COUNT(*) FROM products $whereClause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();

// Kokotoa jumla ya kurasa
$total_pages = ceil($total_products / $products_per_page);

// Pata ukurasa wa sasa
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page > $total_pages && $total_pages > 0) { $current_page = $total_pages; }
if ($current_page < 1) { $current_page = 1; }

$category_escaped = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
$search_term_escaped = htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8');
$category_title_escaped = htmlspecialchars(ucfirst($category), ENT_QUOTES, 'UTF-8');

// Kokotoa offset
$offset = ($current_page - 1) * $products_per_page;
// --- End Pagination Logic ---

// Vuta bidhaa
$sql = "SELECT p.*, 
               (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count
        FROM products p 
        $whereClause $orderByClause LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);

// Bind vigezo vya kawaida
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
// Bind vigezo vya pagination
$stmt->bindParam(':limit', $products_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .product-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    .card-img-top { transition: transform 0.5s ease; }
    .product-card:hover .card-img-top { transform: scale(1.05); }
    .img-wrapper { overflow: hidden; }
</style>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-lg-8 mx-auto text-center">
            <h2 class="fw-bold">
                <?php 
                if ($search_term) {
                    echo t('search_results_for') . ' "' . htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8') . '"';
                } else {
                    echo $category ? $category_title_escaped . ' ' . t('collection') : t('our_shop');
                }
                ?>
            </h2>
            <!-- Search Form -->
            <form action="shop.php" method="GET" class="mt-4">
                <div class="input-group input-group-lg shadow-sm">
                    <input type="text" name="search" class="form-control" placeholder="<?php echo t('search_product_name_placeholder'); ?>" value="<?php echo $search_term_escaped; ?>">
                    <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters and Sorting -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <p class="text-muted mb-0">
                <?php if ($total_products > 0): ?>
                    <?php echo sprintf(t('showing_products_range'), $offset + 1, min($offset + $products_per_page, $total_products), $total_products); ?>
                <?php else: ?>
                    <?php echo t('no_products_to_show'); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-md-6">
            <form action="shop.php" method="GET" id="sortForm" class="d-flex justify-content-end">
                <input type="hidden" name="category" value="<?php echo $category_escaped; ?>">
                <input type="hidden" name="search" value="<?php echo $search_term_escaped; ?>">
                <select name="sort" class="form-select w-auto" onchange="document.getElementById('sortForm').submit();">
                    <option value="newest" <?php if($sort_order == 'newest') echo 'selected'; ?>><?php echo t('sort_newest'); ?></option>
                    <option value="price_asc" <?php if($sort_order == 'price_asc') echo 'selected'; ?>><?php echo t('sort_price_asc'); ?></option>
                    <option value="price_desc" <?php if($sort_order == 'price_desc') echo 'selected'; ?>><?php echo t('sort_price_desc'); ?></option>
                </select>
            </form>
        </div>
    </div>
    <div class="row">
        <?php if($products): foreach($products as $product): ?>
        <div class="col-6 col-md-4 col-lg-3 col-xxl-2 mb-4">
            <div class="card h-100 border-0 shadow-sm product-card rounded-4 overflow-hidden">
                <div class="img-wrapper position-relative">
                    <?php if(isset($product['offer_badge']) && $product['offer_badge'] == 1): ?>
                        <?php if(isset($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                            <span class="badge bg-danger position-absolute top-0 start-0 m-3 shadow-sm" style="z-index: 10;"><?php echo $product['discount_percentage']; ?>% OFF</span>
                        <?php else: ?>
                            <span class="badge bg-danger position-absolute top-0 start-0 m-3 shadow-sm" style="z-index: 10;">OFA</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="product_details.php?id=<?php echo $product['id']; ?>">
                        <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             onerror="this.src='https://via.placeholder.com/300x400?text=No+Image'"
                             loading="lazy"
                             >
                    </a>
                </div>
                <div class="card-body text-center d-flex flex-column">
                    <span class="text-muted small text-uppercase mb-1"><?php echo ucfirst($product['category']); ?></span>
                    <h5 class="card-title fs-6 fw-bold mb-2 text-truncate">
                        <a href="product_details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark stretched-link">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </a>
                    </h5>

                    <!-- Star Rating Summary -->
                    <div class="mb-2 text-warning small">
                        <?php 
                        $p_avg = (float)($product['avg_rating'] ?? 0);
                        for($i=1; $i<=5; $i++): ?>
                            <i class="bi <?php echo ($i <= $p_avg) ? 'bi-star-fill' : (($i - 0.5 <= $p_avg) ? 'bi-star-half' : 'bi-star'); ?>"></i>
                        <?php endfor; ?>
                        <span class="text-muted ms-1">(<?php echo (int)$product['review_count']; ?>)</span>
                    </div>

                    <?php if($product['discount_price'] > 0): ?>
                        <?php 
                            $percentage = ($product['price'] > 0) ? (($product['price'] - $product['discount_price']) / $product['price']) * 100 : 0;
                            $price_color = ($percentage >= 50) ? 'text-success' : 'text-danger';
                        ?>
                        <p class="mb-3">
                            <span class="text-decoration-line-through text-muted small me-2">Tsh <?php echo number_format($product['price']); ?></span>
                            <span class="fw-bold <?php echo $price_color; ?>">Tsh <?php echo number_format($product['discount_price']); ?></span>
                        </p>
                    <?php else: ?>
                        <p class="fw-bold text-primary mb-3">Tsh <?php echo number_format($product['price']); ?></p>
                    <?php endif; ?>
                    <div class="mt-auto">
                        <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-dark rounded-pill w-100 btn-sm position-relative" style="z-index: 2;"><?php echo t('view'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; else: ?>
            <div class="col-12">
                <div class="text-center py-5 my-5 bg-light rounded-4">
                    <i class="bi bi-search-heart display-1 text-muted"></i>
                    <h4 class="fw-bold mt-4"><?php echo t('no_products_found'); ?></h4>
                    <p class="text-muted"><?php echo t('try_different_keywords'); ?></p>
                    <a href="shop.php" class="btn btn-dark rounded-pill px-4 mt-2"><?php echo t('clear_filters'); ?></a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination Links -->
    <?php if($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-5">
        <ul class="pagination justify-content-center">
            <?php
            $prev_query = htmlspecialchars(http_build_query([
                'page' => $current_page - 1,
                'category' => $category,
                'search' => $search_term,
                'sort' => $sort_order
            ]), ENT_QUOTES, 'UTF-8');

            $next_query = htmlspecialchars(http_build_query([
                'page' => $current_page + 1,
                'category' => $category,
                'search' => $search_term,
                'sort' => $sort_order
            ]), ENT_QUOTES, 'UTF-8');
            ?>
            <!-- Kitufe cha Nyuma (Previous) -->
            <li class="page-item <?php if($current_page <= 1){ echo 'disabled'; } ?>">
                <a class="page-link" href="?<?php echo $prev_query; ?>"><?php echo t('previous'); ?></a>
            </li>

            <!-- Namba za Kurasa -->
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <?php $page_query = htmlspecialchars(http_build_query(['page' => $i, 'category' => $category, 'search' => $search_term, 'sort' => $sort_order]), ENT_QUOTES, 'UTF-8'); ?>
            <li class="page-item <?php if($current_page == $i) {echo 'active'; } ?>">
                <a class="page-link" href="?<?php echo $page_query; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>

            <!-- Kitufe cha Mbele (Next) -->
            <li class="page-item <?php if($current_page >= $total_pages) {echo 'disabled'; } ?>">
                <a class="page-link" href="?<?php echo $next_query; ?>"><?php echo t('next'); ?></a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<!-- Sehemu ya Maoni ya Wateja (Recent Reviews Summary) -->
<section class="py-5 bg-light border-top mt-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold" style="font-family: 'Playfair Display', serif;"><?php echo t('customer_reviews'); ?></h2>
            <p class="text-muted"><?php echo t('review_section_intro'); ?></p>
        </div>
        <div class="row g-4">
            <?php
            // Vuta maoni 4 ya hivi karibuni yaliyothibitishwa
            $stmt_all_reviews = $conn->query("
                SELECT r.*, p.name as product_name 
                FROM reviews r 
                JOIN products p ON r.product_id = p.id 
                WHERE r.status = 'approved' 
                ORDER BY r.created_at DESC 
                LIMIT 4
            ");
            $all_recent_reviews = $stmt_all_reviews->fetchAll(PDO::FETCH_ASSOC);

            if ($all_recent_reviews):
                foreach ($all_recent_reviews as $review):
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-2">
                    <div class="card-body">
                        <div class="text-warning mb-2 small">
                            <?php for($i=1; $i<=5; $i++) echo ($i <= $review['rating']) ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>'; ?>
                        </div>
                        <p class="card-text text-muted mb-3 small" style="font-style: italic;">"<?php echo nl2br(htmlspecialchars(mb_strimwidth($review['comment'], 0, 110, "..."))); ?>"</p>
                        <h6 class="fw-bold mb-0 small"><?php echo htmlspecialchars($review['customer_name']); ?></h6>
                        <small class="text-primary" style="font-size: 0.7rem;"><?php echo htmlspecialchars($review['product_name']); ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted small"><?php echo t('no_reviews_yet'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
