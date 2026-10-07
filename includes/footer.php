    <footer class="site-footer">
        <div class="cols">
        <div><h4>Shop</h4>
            <a href="<?php echo BASE_URL; ?>department.php?dept=men"   class="<?php echo (($dept ?? '') === 'men')   ? 'active' : ''; ?>">Men</a>
            <a href="<?php echo BASE_URL; ?>department.php?dept=women" class="<?php echo (($dept ?? '') === 'women') ? 'active' : ''; ?>">Women</a>
            <a href="<?php echo BASE_URL; ?>department.php?dept=kids"  class="<?php echo (($dept ?? '') === 'kids')  ? 'active' : ''; ?>">Kids</a>
            <a href="<?php echo BASE_URL; ?>brands.php" class="<?php echo (($nav_active ?? '') === 'brands') ? 'active' : ''; ?>">Brands</a>
        </div>
        <div><h4>Help</h4><a href="#">Size guide</a><a href="#">Shipping</a><a href="#">Returns</a><a href="#">Contact us</a></div>
        <div><h4>About</h4><a href="#">Our story</a><a href="#">Stores</a><a href="#">Careers</a></div>
        </div>
        <p class="copy">© 2026 Soigné Apparel Shop. All rights reserved.</p>
    </footer>

</body>
</html>