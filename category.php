<?php
session_start();
include_once ('includes/config.php');

$categories = [
    't-shirts' => 'T-shirts',
    'shirts' => 'Shirts',
    'polos' => 'Polos',
    'hoodies' => 'Hoodies',
    'sweaters' => 'Sweaters',
    'pants' => 'Pants',
    'jeans' => 'Jeans',
    'chinos' => 'Chinos',
    'shorts' => 'Shorts',
    'outerwear' => 'Outerwear',
];

$category_slug = $_GET['category'] ?? '';
if (!is_string($category_slug) || !isset($categories[$category_slug])) {
    http_response_code(404);
    exit('Category not found.');
}

$category_name = $categories[$category_slug];
$stmt = mysqli_prepare($conn,
    "SELECT p.product_id, p.name, p.price, c.name AS category,
            pc.product_color_id AS first_color_id,
            (SELECT COUNT(*) FROM product_colors WHERE product_id = p.product_id) AS color_count
     FROM products p
     JOIN categories c ON c.category_id = p.category_id
     LEFT JOIN product_colors pc
       ON pc.product_color_id = (
         SELECT MIN(product_color_id)
         FROM product_colors
         WHERE product_id = p.product_id
       )
     WHERE c.name = ?
     ORDER BY p.product_id DESC"
);
mysqli_stmt_bind_param($stmt, 's', $category_name);
mysqli_stmt_execute($stmt);
$products = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$page_title = $category_name . ' | Soigné';
$extra_css = 'department.css';
include_once ('includes/header.php');
?>

<main class="dept-page">
  <div class="dept-head">
    <h1><?php echo htmlspecialchars($category_name); ?></h1>
    <p class="dept-count"><?php echo count($products); ?> product<?php echo count($products) === 1 ? '' : 's'; ?></p>
  </div>

  <?php
  $empty_message = 'No products in this category yet. Please check back soon.';
  include("includes/product_grid.php");
  ?>
</main>

<?php include_once ('includes/footer.php'); ?>
