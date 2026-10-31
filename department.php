<?php
session_start();
include_once('includes/config.php');
require_once('includes/product_images.php');

$allowed = ['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'];
$dept = $_GET['dept'] ?? '';
if (!isset($allowed[$dept])) {
    http_response_code(404);
    exit('Department not found.');
}
$dept_label = $allowed[$dept];
$dept_sql = mysqli_real_escape_string($conn, $dept);

$products = mysqli_fetch_all(mysqli_query($conn,
    "SELECT p.product_id, p.name, p.price, c.name AS category,
            pc.product_color_id AS first_color_id,
            (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
     FROM products p
     LEFT JOIN categories c ON c.category_id = p.category_id
     LEFT JOIN product_colors pc
       ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
     WHERE p.department = '$dept_sql'
     ORDER BY p.product_id DESC"), MYSQLI_ASSOC);

$page_title = $dept_label . ' | Soigné';
$extra_css = 'department.css';
include_once('includes/header.php');
?>

<main class="dept-page">
  <div class="dept-head">
    <h1><?php echo htmlspecialchars($dept_label); ?></h1>
    <p class="dept-count"><?php echo count($products); ?> product<?php echo count($products) === 1 ? '' : 's'; ?></p>
  </div>

  <?php
  $empty_message = 'No products in this department yet. Please check back soon.';
  include('includes/product_grid.php');
  ?>
</main>

<?php include_once('includes/footer.php'); ?>
