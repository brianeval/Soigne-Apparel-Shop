<?php
session_start();
include_once('includes/config.php');
require_once('includes/product_images.php');

$search_term = trim($_GET['q'] ?? '');
$products = [];
$brands = [];

if ($search_term !== '') {
    $like = '%' . mysqli_real_escape_string($conn, $search_term) . '%';

    $products = mysqli_fetch_all(mysqli_query($conn,
        "SELECT p.product_id, p.name, p.price, c.name AS category,
                pc.product_color_id AS first_color_id,
                (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
         FROM products p
         LEFT JOIN categories c ON c.category_id = p.category_id
         LEFT JOIN product_colors pc
           ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
         WHERE p.name LIKE '$like' OR c.name LIKE '$like'
         ORDER BY p.product_id DESC"), MYSQLI_ASSOC);

    $brands = mysqli_fetch_all(mysqli_query($conn,
        "SELECT supplier_id, name, image FROM suppliers
         WHERE name LIKE '$like' ORDER BY name"), MYSQLI_ASSOC);
}

$result_count = count($products) + count($brands);
$page_title = $search_term !== '' ? 'Search results for "' . $search_term . '" | Soigné' : 'Search | Soigné';
$extra_css = 'department.css';
include_once('includes/header.php');
?>

<main class="dept-page">
  <div class="dept-head">
    <h1>Search results</h1>
    <?php if ($search_term !== '') { ?>
      <p class="dept-count">
        <?php echo $result_count; ?> result<?php echo $result_count === 1 ? '' : 's'; ?> for "<?php echo htmlspecialchars($search_term); ?>"
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
      <?php $empty_message = 'No products matched your search.'; include('includes/product_grid.php'); ?>
    <?php } ?>

    <?php if (!$brands && !$products) { ?>
      <p class="dept-empty">No products or brands matched your search.</p>
    <?php } ?>
  <?php } ?>
</main>

<?php include_once('includes/footer.php'); ?>
