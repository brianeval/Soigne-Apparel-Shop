<?php
session_start();
include_once ('includes/config.php');

$search_term = trim($_GET['q'] ?? '');
$products = [];
$brands = [];

if ($search_term !== '') {
    $like = '%' . $search_term . '%';

    // This query finds matching products by name or category.
    $product_sql = "SELECT p.product_id, p.name, p.price, c.name AS category,
                         pc.product_color_id AS first_color_id,
                         (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
                    FROM products p
                    LEFT JOIN categories c ON c.category_id = p.category_id
                    LEFT JOIN product_colors pc
                      ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
                    WHERE LOWER(p.name) LIKE LOWER(?)
                       OR LOWER(COALESCE(c.name, '')) LIKE LOWER(?)
                    ORDER BY p.product_id DESC";
    $stmt = mysqli_prepare($conn, $product_sql);
    mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
    mysqli_stmt_execute($stmt);
    $products = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // This query finds matching brands by name.
    $brand_sql = "SELECT supplier_id, name, image
                  FROM suppliers
                  WHERE LOWER(name) LIKE LOWER(?)
                  ORDER BY name";
    $stmt = mysqli_prepare($conn, $brand_sql);
    mysqli_stmt_bind_param($stmt, 's', $like);
    mysqli_stmt_execute($stmt);
    $brands = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

$page_title = $search_term !== '' ? 'Search results for "' . $search_term . '" | Soigné' : 'Search | Soigné';
$extra_css = 'department.css';
include_once ('includes/header.php');
?>

<main class="dept-page">
  <div class="dept-head">
    <h1>Search results</h1>
    <?php if ($search_term !== '') { ?>
      <p class="dept-count">
        <?php echo (count($products) + count($brands)); ?> result<?php echo (count($products) + count($brands)) === 1 ? '' : 's'; ?> for "<?php echo htmlspecialchars($search_term); ?>"
      </p>
    <?php } else { ?>
      <p class="dept-count">Enter a product or brand name</p>
    <?php } ?>
  </div>

  <?php if ($search_term === '') { ?>
    <p class="dept-empty">Use the search bar to find products or brands.</p>
  <?php } else { ?>
    <?php if ($brands) { ?>
      <div class="brand-grid">
        <?php foreach ($brands as $b) { ?>
          <a class="brand-card" href="<?php echo BASE_URL; ?>brands.php?id=<?php echo (int)$b['supplier_id']; ?>">
            <div class="brand-logo-box">
              <?php if (!empty($b['image'])) { ?>
                <img src="<?php echo BASE_URL; ?>images/brands/<?php echo htmlspecialchars($b['image']); ?>"
                     alt="<?php echo htmlspecialchars($b['name']); ?>" loading="lazy">
              <?php } else { ?>
                <span class="brand-initial"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($b['name'], 0, 1))); ?></span>
              <?php } ?>
            </div>
            <p class="dept-name"><?php echo htmlspecialchars($b['name']); ?></p>
          </a>
        <?php } ?>
      </div>
    <?php } ?>

    <?php if ($products) { ?>
      <h2>Products</h2>
      <?php $empty_message = 'No products matched your search.'; include("includes/product_grid.php"); ?>
    <?php } ?>

    <?php if (!$brands && !$products) { ?>
      <p class="dept-empty">No products or brands matched your search.</p>
    <?php } ?>
  <?php } ?>
</main>

<?php include_once ('includes/footer.php'); ?>
