    <footer class="site-footer">
        <div class="cols">
        <div><h4>Shop</h4>
            <a href="<?php echo BASE_URL; ?>department.php?dept=men"   class="<?php echo (($dept ?? '') === 'men')   ? 'active' : ''; ?>">Men</a>
            <a href="<?php echo BASE_URL; ?>department.php?dept=women" class="<?php echo (($dept ?? '') === 'women') ? 'active' : ''; ?>">Women</a>
            <a href="<?php echo BASE_URL; ?>department.php?dept=kids"  class="<?php echo (($dept ?? '') === 'kids')  ? 'active' : ''; ?>">Kids</a>
            <a href="<?php echo BASE_URL; ?>brands.php" class="<?php echo (($nav_active ?? '') === 'brands') ? 'active' : ''; ?>">Brands</a>
        </div>
        <div><h4>Help</h4><a href="<?php echo BASE_URL; ?>size_guide.php">Size guide</a>
        </div>
        <p class="copy">© 2026 Soigné Apparel Shop. All rights reserved.</p>
    </footer>

    <dialog class="product-image-viewer" id="product-image-viewer" aria-label="Product image preview">
        <button type="button" class="product-image-viewer-close" aria-label="Close image preview">&times;</button>
        <img id="product-image-viewer-image" src="" alt="">
    </dialog>
    <script>
    (function () {
      var viewer = document.getElementById('product-image-viewer');
      var viewerImage = document.getElementById('product-image-viewer-image');
      if (!viewer || !viewerImage) return;

      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-image-viewer]');
        if (trigger) {
          viewerImage.src = trigger.dataset.imageSrc || '';
          viewerImage.alt = trigger.dataset.imageAlt || '';
          viewer.showModal();
        }

        if (event.target === viewer || event.target.closest('.product-image-viewer-close')) {
          viewer.close();
          viewerImage.removeAttribute('src');
        }
      });
      viewer.addEventListener('close', function () {
        viewerImage.removeAttribute('src');
      });
    })();
    </script>
</body>
</html>