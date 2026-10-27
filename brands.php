<?php
// Brands page:  brands.php  -> list of brands
//               brands.php?id=3  -> products of that brand
include_once ('includes/config.php');

$brand_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$brand_name = isset($_GET['brand']) && is_string($_GET['brand']) ? trim($_GET['brand']) : '';
$brand    = null;
$brands   = [];
$products = [];

function brand_logo_filename(string $name, ?string $stored_image): ?string
{
    $brand_name = mb_strtolower(trim($name));

    if ($brand_name === 'h&m') {
        return 'h-m-logo.png';
    }
    if ($brand_name === 'nike') {
        return 'nike-logo.jpg';
    }
    if ($brand_name === 'penshoppe') {
        return 'penshoppe-logo.jpeg';
    }

    return $stored_image !== null && $stored_image !== '' ? $stored_image : null;
}

if ($brand_name !== '') {
    $brand_stmt = mysqli_prepare($conn, "SELECT supplier_id, name, image FROM suppliers WHERE name = ?");
    mysqli_stmt_bind_param($brand_stmt, 's', $brand_name);
    mysqli_stmt_execute($brand_stmt);
    $brand = mysqli_fetch_assoc(mysqli_stmt_get_result($brand_stmt));

    if (!$brand) {
        http_response_code(404);
        exit('Brand not found.');
    }

    $brand_id = (int)$brand['supplier_id'];
}

if ($brand_id > 0) {
    // --- One brand and its products ---
    // This query loads the selected brand so the page can show its name and logo.
    $brand_stmt = mysqli_prepare($conn, "SELECT supplier_id, name, image FROM suppliers WHERE supplier_id = ?");
    mysqli_stmt_bind_param($brand_stmt, 'i', $brand_id);
    mysqli_stmt_execute($brand_stmt);
    $brand = mysqli_fetch_assoc(mysqli_stmt_get_result($brand_stmt));

    if (!$brand) {
        http_response_code(404);
        exit('Brand not found.');
    }

    // This query gets every product from the selected brand, along with a preview image and color count.
    $products_sql = "SELECT p.product_id, p.name, p.price, c.name AS category,
                 pc.product_color_id AS first_color_id,
                 (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
          FROM products p
          LEFT JOIN categories c ON c.category_id = p.category_id
          LEFT JOIN product_colors pc
            ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
          WHERE p.supplier_id = ?
          ORDER BY p.product_id DESC";
    $products_stmt = mysqli_prepare($conn, $products_sql);
    mysqli_stmt_bind_param($products_stmt, 'i', $brand_id);
    mysqli_stmt_execute($products_stmt);
    $products = mysqli_fetch_all(mysqli_stmt_get_result($products_stmt), MYSQLI_ASSOC);

    $page_title = $brand['name'] . " | Soigné";
} else {
    // --- All brands that have at least one product ---
    // This query lists each brand with a count of how many products it has.
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
    <?php $brand_logo = brand_logo_filename($brand['name'], $brand['image'] ?? null); ?>
    <?php if ($brand_logo !== null) { ?>
      <img class="brand-head-logo"
           src="<?php echo BASE_URL; ?>images/brands/<?php echo htmlspecialchars($brand_logo); ?>"
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
        <?php $brand_logo = brand_logo_filename($b['name'], $b['image'] ?? null); ?>
        <a class="brand-card" href="<?php echo BASE_URL; ?>brands.php?id=<?php echo (int)$b['supplier_id']; ?>">
          <div class="brand-logo-box">
            <?php if ($brand_logo !== null) { ?>
              <img src="<?php echo BASE_URL; ?>images/brands/<?php echo htmlspecialchars($brand_logo); ?>"
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