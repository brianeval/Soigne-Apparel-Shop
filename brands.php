<?php
session_start();
include_once('includes/config.php');
require_once('includes/product_images.php');

$brand_id = (int)($_GET['id'] ?? 0);
$brand = null;
$brands = [];
$products = [];

if (!empty($_GET['brand'])) {
    $brand_name = mysqli_real_escape_string($conn, trim($_GET['brand']));
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT supplier_id FROM suppliers WHERE name = '$brand_name'"));
    if ($row) {
        $brand_id = (int)$row['supplier_id'];
    }
}

if ($brand_id > 0) {
    $brand = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM suppliers WHERE supplier_id = $brand_id"));
    if (!$brand) {
        http_response_code(404);
        exit('Brand not found.');
    }

    $products = mysqli_fetch_all(mysqli_query($conn,
        "SELECT p.product_id, p.name, p.price, c.name AS category,
                pc.product_color_id AS first_color_id,
                (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
         FROM products p
         LEFT JOIN categories c ON c.category_id = p.category_id
         LEFT JOIN product_colors pc
           ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
         WHERE p.supplier_id = $brand_id
         ORDER BY p.product_id DESC"), MYSQLI_ASSOC);

    $page_title = $brand['name'] . ' | Soigné';
} else {
    $brands = mysqli_fetch_all(mysqli_query($conn,
        "SELECT s.supplier_id, s.name, s.image, COUNT(p.product_id) AS product_count
         FROM suppliers s
         LEFT JOIN products p ON p.supplier_id = s.supplier_id
         GROUP BY s.supplier_id, s.name, s.image
         ORDER BY s.name"), MYSQLI_ASSOC);

    $page_title = 'Brands | Soigné';
}

$extra_css = 'department.css';
$nav_active = 'brands';
include_once('includes/header.php');
?>

<main class="dept-page">

<?php if ($brand) { ?>

  <p class="brand-back"><a href="<?php echo BASE_URL; ?>brands.php">&larr; All brands</a></p>

  <div class="brand-head">
    <?php if ($brand['image'] !== null) { ?>
      <img class="brand-head-logo"
           src="<?php echo BASE_URL; ?>images/brands/<?php echo htmlspecialchars($brand['image']); ?>"
           alt="<?php echo htmlspecialchars($brand['name']); ?>">
    <?php } ?>
    <h1><?php echo htmlspecialchars($brand['name']); ?></h1>
    <p class="dept-count"><?php echo count($products); ?> product<?php echo count($products) === 1 ? '' : 's'; ?></p>
  </div>

  <?php
  $empty_message = 'This brand has no products yet.';
  include('includes/product_grid.php');
  ?>

<?php } else { ?>

  <div class="dept-head">
    <h1>Brands</h1>
    <p class="dept-count"><?php echo count($brands); ?> brand<?php echo count($brands) === 1 ? '' : 's'; ?></p>
  </div>

  <?php if (!$brands) { ?>
    <p class="dept-empty">No brands available yet.</p>
  <?php } else { ?>
    <div class="brand-grid">
      <?php foreach ($brands as $b) { ?>
        <a class="brand-card" href="<?php echo BASE_URL; ?>brands.php?id=<?php echo (int)$b['supplier_id']; ?>">
          <div class="brand-logo-box">
            <?php if ($b['image'] !== null) { ?>
              <img src="<?php echo BASE_URL; ?>images/brands/<?php echo htmlspecialchars($b['image']); ?>"
                   alt="<?php echo htmlspecialchars($b['name']); ?>" loading="lazy">
            <?php } else { ?>
              <span class="brand-initial"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($b['name'], 0, 1))); ?></span>
            <?php } ?>
          </div>
          <p class="dept-name"><?php echo htmlspecialchars($b['name']); ?></p>
          <p class="dept-meta"><?php echo (int)$b['product_count']; ?> product<?php echo (int)$b['product_count'] === 1 ? '' : 's'; ?></p>
        </a>
      <?php } ?>
    </div>
  <?php } ?>

<?php } ?>

</main>

<?php include_once('includes/footer.php'); ?>
