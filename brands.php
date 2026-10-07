<?php
// Brands page:  brands.php  -> list of brands
//               brands.php?id=3  -> products of that brand
include_once ('includes/config.php');

$brand_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$brand    = null;
$brands   = [];
$products = [];

if ($brand_id > 0) {
    // --- One brand and its products ---
    $stmt = mysqli_prepare($conn, "SELECT supplier_id, name, image FROM suppliers WHERE supplier_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $brand_id);
    mysqli_stmt_execute($stmt);
    $brand = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$brand) {
        http_response_code(404);
        exit('Brand not found.');
    }

    $q = "SELECT p.product_id, p.name, p.price, p.category, pc.image,
                 (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
          FROM products p
          LEFT JOIN product_colors pc
            ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
          WHERE p.supplier_id = ?
          ORDER BY p.product_id DESC";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, 'i', $brand_id);
    mysqli_stmt_execute($stmt);
    $products = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    $page_title = $brand['name'] . " | Soigné";
} else {
    // --- All brands that have at least one product ---
    $result = mysqli_query($conn,
        "SELECT s.supplier_id, s.name, s.image, COUNT(p.product_id) AS product_count
         FROM suppliers s
         JOIN products p ON p.supplier_id = s.supplier_id
         GROUP BY s.supplier_id, s.name, s.image
         ORDER BY s.name");
    $brands = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $page_title = "Brands | Soigné";
}

$extra_css  = "department.css";
$nav_active = "brands";
include_once ('includes/header.php');
?>

<main class="dept-page">

<?php if ($brand) { ?>

  <p class="brand-back"><a href="<?php echo BASE_URL; ?>brands.php">&larr; All brands</a></p>

  <div class="brand-head">
    <?php if (!empty($brand['image'])) { ?>
      <img class="brand-head-logo"
           src="<?php echo BASE_URL; ?>images/brands/<?php echo htmlspecialchars($brand['image']); ?>"
           alt="<?php echo htmlspecialchars($brand['name']); ?>">
    <?php } ?>
    <h1><?php echo htmlspecialchars($brand['name']); ?></h1>
    <p class="dept-count"><?php echo count($products); ?> product<?php echo count($products) === 1 ? '' : 's'; ?></p>
  </div>

  <?php
  $empty_message = 'This brand has no products yet.';
  include ('includes/product_grid.php');
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
            <?php if (!empty($b['image'])) { ?>
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

<?php include_once ('includes/footer.php'); ?>