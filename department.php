<?php
// Department page: department.php?dept=men | women | kids
session_start();
include_once ('includes/config.php');

$dept = $_GET['dept'] ?? '';

$allowed = ['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'];
if (!isset($allowed[$dept])) {
    http_response_code(404);
    exit('Department not found.');
}
$dept_label = $allowed[$dept];

// Products in this department, each with the image of its first color.
// This query gets all products in the chosen department and attaches the first color image for each one.
$products_sql = "SELECT p.product_id, p.name, p.price, c.name AS category,
             pc.product_color_id AS first_color_id,
             (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
      FROM products p
      LEFT JOIN categories c ON c.category_id = p.category_id
      LEFT JOIN product_colors pc
        ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
      WHERE p.department = ?
      ORDER BY p.product_id DESC";
$products_stmt = mysqli_prepare($conn, $products_sql);
mysqli_stmt_bind_param($products_stmt, 's', $dept);
mysqli_stmt_execute($products_stmt);
$products = mysqli_fetch_all(mysqli_stmt_get_result($products_stmt), MYSQLI_ASSOC);

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
  include("includes/product_grid.php");
  ?>
</main>

<?php include_once ('includes/footer.php'); ?>