<?php
// Department page: department.php?dept=men | women | kids
include_once ('includes/config.php');

$dept = $_GET['dept'] ?? '';

$allowed = ['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'];
if (!isset($allowed[$dept])) {
    http_response_code(404);
    exit('Department not found.');
}
$dept_label = $allowed[$dept];

// Products in this department, each with the image of its first color
$q = "SELECT p.product_id, p.name, p.price, p.category, pc.image,
             (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
      FROM products p
      LEFT JOIN product_colors pc
        ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
      WHERE p.department = ?
      ORDER BY p.product_id DESC";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, 's', $dept);
mysqli_stmt_execute($stmt);
$products = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$page_title = $dept_label . " | Soigné";
$extra_css  = "department.css";
include_once ('includes/header.php');
?>

<main class="dept-page">
  <div class="dept-head">
    <h1><?php echo htmlspecialchars($dept_label); ?></h1>
    <p class="dept-count"><?php echo count($products); ?> product<?php echo count($products) === 1 ? '' : 's'; ?></p>
  </div>

  <?php
  $empty_message = 'No products in this department yet. Please check back soon.';
  include __DIR__ . '/includes/product_grid.php';
  ?>
</main>

<?php include_once ('includes/footer.php'); ?>