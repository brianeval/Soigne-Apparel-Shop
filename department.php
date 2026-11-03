<?php

session_start();
include_once ('includes/config.php');

$dept = $_GET['dept'] ?? '';

$allowed = ['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'];
if (!isset($allowed[$dept])) {
    http_response_code(404);
    exit('Department not found.');
}
$dept_label = $allowed[$dept];


$products_sql = "SELECT p.product_id, p.name, p.price, c.name AS category,
             pc.product_color_id AS first_color_id,
             (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
      FROM products p
      LEFT JOIN categories c ON c.category_id = p.category_id
      LEFT JOIN product_colors pc
        ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
      WHERE p.department = ?
      ORDER BY p.product_id DESC";
$products_result = mysqli_execute_query($conn, $products_sql, [$dept]);
$products = mysqli_fetch_all($products_result, MYSQLI_ASSOC);

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