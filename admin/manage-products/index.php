<?php
require_once("../includes/bootstrap.php");

$errors = [];
$catalog_view = $_POST['catalog_view'] ?? $_GET['catalog_view'] ?? 'products';
if (!in_array($catalog_view, ['products', 'create', 'category', 'brand'])) {
    $catalog_view = 'products';
}
$product_size_guide = require("../../includes/product_size_guide.php");

$categories = mysqli_fetch_all(mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name"), MYSQLI_ASSOC);
$category_names = array_column($categories, 'name', 'category_id');
$suppliers = mysqli_fetch_all(mysqli_query($conn, "SELECT supplier_id, name FROM suppliers ORDER BY name"), MYSQLI_ASSOC);


$selected_department = $_POST['department'] ?? 'men';
if (!in_array($selected_department, ['men', 'women', 'kids'])) {
    $selected_department = 'men';
}
$selected_category_id = (int)($_POST['category_id'] ?? 0);
$selected_category_name = $category_names[$selected_category_id] ?? null;
$size_group = null;
if ($selected_category_name) {
    $size_group = $product_size_guide['category_groups'][strtolower($selected_category_name)] ?? null;
}
if ($size_group) {
    $visible_size_options = $product_size_guide['sizes'][$selected_department][$size_group];
} else {

    $visible_size_options = array_unique(array_merge(
        $product_size_guide['sizes'][$selected_department]['top'],
        $product_size_guide['sizes'][$selected_department]['bottom'] 
    ));
}

include('products_actions.php');


$product_search = trim($_GET['search'] ?? '');
$product_department = $_GET['department'] ?? '';
if (!in_array($product_department, ['men', 'women', 'kids'])) {
    $product_department = '';
}
$product_supplier_id = (int)($_GET['supplier_id'] ?? 0);

$product_where = "WHERE 1 = 1";
if ($product_search != '') {
    $product_where .= " AND p.name LIKE '%" . mysqli_real_escape_string($conn, $product_search) . "%'";
}
if ($product_department != '') {
    $product_where .= " AND p.department = '$product_department'";
}
if ($product_supplier_id > 0) {
    $product_where .= " AND p.supplier_id = $product_supplier_id";
}
$products = mysqli_fetch_all(mysqli_query($conn,
    "SELECT p.product_id, p.name, c.name AS category, p.department, p.price, s.name AS supplier_name,
            COUNT(DISTINCT pc.product_color_id) AS color_count,
            (SELECT COUNT(*)
             FROM order_items oi
             JOIN product_variants order_variant ON order_variant.variant_id = oi.variant_id
             JOIN product_colors order_color ON order_color.product_color_id = order_variant.product_color_id
             WHERE order_color.product_id = p.product_id) AS order_count,
            COALESCE(SUM(v.stock), 0) AS stock_count
     FROM products p
     JOIN suppliers s ON s.supplier_id = p.supplier_id
     LEFT JOIN categories c ON c.category_id = p.category_id
     LEFT JOIN product_colors pc ON pc.product_id = p.product_id
     LEFT JOIN product_variants v ON v.product_color_id = pc.product_color_id
     $product_where
     GROUP BY p.product_id, p.name, c.name, p.department, p.price, s.name
     ORDER BY p.product_id DESC"
), MYSQLI_ASSOC);
$product_total = count($products);

$admin_page_title = 'Manage Products';
$admin_active_page = 'products';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CATALOG</p><h1>Manage Products</h1><p>Create products, update their details, and manage available variants.</p></div>
</section>
<?php include("../../includes/alert.php"); ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error); ?></li><?php } ?></ul></div><?php } ?>

<div class="admin-panel-tabs catalog-tabs" role="radiogroup" aria-label="Product panels">
  <input type="radio" name="catalog_view" id="catalog-view-products" value="products" <?php echo $catalog_view === 'products' ? 'checked' : ''; ?>>
  <label for="catalog-view-products">Product Catalog</label>
  <input type="radio" name="catalog_view" id="catalog-view-create" value="create" <?php echo $catalog_view === 'create' ? 'checked' : ''; ?>>
  <label for="catalog-view-create">Add Product</label>
  <input type="radio" name="catalog_view" id="catalog-view-category" value="category" <?php echo $catalog_view === 'category' ? 'checked' : ''; ?>>
  <label for="catalog-view-category">Add Category</label>
  <input type="radio" name="catalog_view" id="catalog-view-brand" value="brand" <?php echo $catalog_view === 'brand' ? 'checked' : ''; ?>>
  <label for="catalog-view-brand">Add Brand</label>

  <section class="manage-panel catalog-category-panel">
    <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Add Category</h2></div></div>
    <form method="post" class="manage-form catalog-simple-form" novalidate>
      <input type="hidden" name="catalog_view" value="category">
      <input type="hidden" name="action" value="add_category">
      <label>Category name<input name="category_name" value="<?php echo htmlspecialchars(($_POST['action'] ?? '') === 'add_category' ? ($_POST['category_name'] ?? '') : ''); ?>"></label>
      <div class="manage-form-buttons"><button class="admin-button" type="submit">Add Category</button></div>
    </form>
  </section>

  <section class="manage-panel catalog-brand-panel">
    <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Add Brand</h2></div></div>
    <form method="post" enctype="multipart/form-data" class="manage-form catalog-simple-form" novalidate>
      <input type="hidden" name="catalog_view" value="brand">
      <input type="hidden" name="action" value="add_brand">
      <label>Brand name<input name="brand_name" value="<?php echo htmlspecialchars(($_POST['action'] ?? '') === 'add_brand' ? ($_POST['brand_name'] ?? '') : ''); ?>"></label>
      <label>Brand logo (optional)
        <input type="file" name="brand_logo" accept="image/jpeg,image/png,image/webp"><small class="manage-secondary">JPEG, PNG, or WebP · 5 MB max</small>
      </label>
      <div class="manage-form-buttons"><button class="admin-button" type="submit">Add Brand</button></div>
    </form>
  </section>

  <section class="manage-panel catalog-create-panel" aria-labelledby="add-product-title">
    <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2 id="add-product-title">Create a product</h2></div></div>
<?php if (!$suppliers) { ?>
  <section class="manage-panel product-create-panel">
    <p class="manage-empty">Create a brand first before adding a product.</p>
  </section>
<?php } else { ?>
  <?php

  $form_colors = $_POST['colors'] ?? [0 => ['name' => '', 'sizes' => []]];
  $form_color_count = 1;
  foreach ($form_colors as $color_index => $form_color) {
      if (($form_color['name'] ?? '') != '' || !empty($form_color['sizes'])) {
          $form_color_count = $color_index + 1;
      }
  }
  ?>
  <form method="post" enctype="multipart/form-data" class="manage-form manage-product-create create-product-form" novalidate>
    <input type="hidden" name="catalog_view" value="create">
    <section class="manage-panel product-create-panel">
      <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Product details</h2></div></div>
      <div class="manage-product-fields">
        <label class="product-field-wide">Product name<input name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"></label>
        <label>Price (₱)<input type="text" inputmode="decimal" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"></label>
        <label>Department<select id="product-department" name="department"><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $department_value => $department_label) { ?><option value="<?php echo $department_value; ?>" <?php echo $selected_department === $department_value ? 'selected' : ''; ?>><?php echo $department_label; ?></option><?php } ?></select></label>
        <label>Brand<select name="supplier_id"><option value="">Choose a brand</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo (int)($_POST['supplier_id'] ?? 0) === (int)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($supplier['name']); ?></option><?php } ?></select></label>
        <label>Category<select id="product-category" name="category_id"><?php foreach ($categories as $category_option) { ?><option value="<?php echo (int)$category_option['category_id']; ?>" <?php echo $selected_category_id === (int)$category_option['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category_option['name']); ?></option><?php } ?></select></label>
        <label class="product-field-wide">Description<textarea name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea></label>
      </div>
    </section>

    <section class="manage-panel product-create-panel product-options-panel">
      <div class="manage-panel-heading">
        <div><p class="admin-eyebrow">PRODUCT OPTIONS</p><h2>Colors, images &amp; sizes</h2></div>
        <button class="product-size-refresh-button" type="submit" name="action" value="refresh_size_options" aria-label="Refresh available sizes" title="Refresh available sizes">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"></path><path d="M5.6 9a7 7 0 0 1 11.6-2L20 12M4 12l2.8 5a7 7 0 0 0 11.6-2"></path></svg>
        </button>
      </div>
      <p class="manage-secondary">Select department and category, then refresh sizes here. Refreshing or removing a color clears selected images, so choose images after setting up the colors.</p>
      <div class="product-color-list" id="product-color-list">
        <?php for ($color_index = 0; $color_index < 20; $color_index++) {
          $posted_color = $form_colors[$color_index] ?? [];
          $color_name_value = $posted_color['name'] ?? '';
          $selected_sizes = $posted_color['sizes'] ?? [];
          if ($color_index > 0) { ?>
            <details class="product-color-disclosure"<?php echo $color_index < $form_color_count ? ' open' : ''; ?>>
              <summary><span class="add-color-label">Add another color</span><span class="existing-color-label">Color option <?php echo $color_index + 1; ?></span></summary>
          <?php } ?>
          <div class="product-color-row">
            <div class="product-color-row-heading">
              <strong>Color option <?php echo $color_index + 1; ?></strong>
              <button class="product-color-remove" type="submit" name="remove_color" value="<?php echo $color_index; ?>" aria-label="Remove color option <?php echo $color_index + 1; ?>" title="Remove color option">×</button>
            </div>
            <label>Color name<input name="colors[<?php echo $color_index; ?>][name]" placeholder="e.g. Navy" value="<?php echo htmlspecialchars($color_name_value); ?>"></label>
            <div class="product-color-controls">
              <div class="product-size-picker">
                <details>
                  <summary>Select sizes</summary>
                  <fieldset class="product-size-options">
                    <legend>Sizes for <?php echo htmlspecialchars($selected_department); ?> / <?php echo htmlspecialchars($selected_category_name ?? 'uncategorized'); ?></legend>
                  <?php if (!$visible_size_options) { ?>
                    <p class="manage-secondary">No sizes available for this department and category.</p>
                  <?php } ?>
                  <?php foreach ($visible_size_options as $size_option) { ?>
                    <label><input type="checkbox" name="colors[<?php echo $color_index; ?>][sizes][]" value="<?php echo htmlspecialchars($size_option); ?>" <?php echo in_array($size_option, $selected_sizes, true) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($size_option); ?></label>
                  <?php } ?>
                  </fieldset>
                </details>
              </div>
              <div class="product-color-image">
                <div class="product-image-picker-actions">
                  <label>Choose images<input id="color-image-<?php echo $color_index; ?>" type="file" name="color_images[<?php echo $color_index; ?>][]" accept="image/jpeg,image/png,image/webp" multiple></label>
                  <small class="manage-secondary">Select up to 10 JPEG, PNG, or WebP images at once · 5 MB each.</small>
                </div>
              </div>
            </div>
          </div>
        <?php } ?>
        <?php for ($close_color_index = 1; $close_color_index < 20; $close_color_index++) { ?></details><?php } ?>
      </div>
      <p class="manage-secondary" id="product-size-help">Choose one or more sizes from the dropdown for each color.</p>
      <div class="product-options-actions">
        <button class="admin-button" type="submit" name="action" value="create">Create product</button>
      </div>
    </section>
  </form>
<?php } ?>
  </section>

<section class="manage-panel manage-list-panel catalog-products-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT CATALOG</p><h2>Products <span class="manage-count"><?php echo $product_total; ?></span></h2></div></div>
  <form method="get" class="inventory-filters product-catalog-filters" aria-label="Filter product catalog">
    <input type="hidden" name="catalog_view" value="products">
    <label class="inventory-search">Product name<input type="search" name="search" value="<?php echo htmlspecialchars($product_search); ?>" placeholder="Search product name"></label>
    <label>Department<select name="department"><option value="">All departments</option><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $department_value => $department_label) { ?><option value="<?php echo $department_value; ?>" <?php echo $product_department === $department_value ? 'selected' : ''; ?>><?php echo $department_label; ?></option><?php } ?></select></label>
    <label>Brand<select name="supplier_id"><option value="">All brands</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo $product_supplier_id === (int)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($supplier['name']); ?></option><?php } ?></select></label>
    <button class="admin-button" type="submit">Apply filters</button>
    <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-products/?catalog_view=products">Clear</a>
  </form>
  <?php if ($products) { ?>
    <div class="admin-table-wrap"><table class="admin-table">
      <thead><tr><th>Product</th><th>Brand</th><th>Department</th><th>Price</th><th>Colors</th><th>Stock</th><th>Actions</th></tr></thead>
      <tbody><?php foreach ($products as $product) { ?>
        <tr>
          <td class="order-number"><?php echo htmlspecialchars($product['name']); ?><small class="manage-secondary"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></small></td>
          <td><?php echo htmlspecialchars($product['supplier_name']); ?></td>
          <td><?php echo htmlspecialchars(ucfirst($product['department'])); ?></td>
          <td><?php echo '₱' . number_format($product['price'], 2); ?></td>
          <td><?php echo (int)$product['color_count']; ?></td>
          <td><?php echo (int)$product['stock_count']; ?></td>
          <td><div class="manage-row-actions">
            <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-products/edit.php?id=<?php echo (int)$product['product_id']; ?>">Edit</a>
            <?php if ((int)$product['order_count'] === 0) { ?>
              <form method="post" novalidate>
                <input type="hidden" name="catalog_view" value="products">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">
                <button class="manage-small-button is-danger" type="submit">Delete</button>
              </form>
            <?php } else { ?>
              <span class="manage-secondary">Has orders</span>
            <?php } ?>
          </div></td>
        </tr>
      <?php } ?></tbody>
    </table></div>
  <?php } else { ?><p class="manage-empty">No products yet. Use the Add Product panel to add your first product.</p><?php } ?>
</section>
</div>
<?php include("../includes/footer.php"); ?>
