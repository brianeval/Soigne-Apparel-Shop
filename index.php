<?php
  session_start();
  include_once("includes/header.php");
  include_once("includes/config.php");
  require_once("includes/product_images.php");

  // Show the newest products on the homepage, including items that are currently sold out.
  $q = "SELECT p.*, pc.product_color_id AS first_color_id
      FROM products p
      LEFT JOIN product_colors pc
        ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
      ORDER BY p.product_id DESC
      LIMIT 5";
  $products = mysqli_fetch_all(mysqli_query($conn, $q), MYSQLI_ASSOC);
  $product_image_map = product_color_image_paths($conn, array_map('intval', array_column($products, 'first_color_id')));
?>
<script>
(function () {
  if ('IntersectionObserver' in window &&
      !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('has-scroll-reveal');
  }
})();
</script>
  <main>
    <section class="hero" aria-label="Featured collections">
      <div class="hero-slide is-active" data-hero-slide aria-hidden="false">
        <div class="hero-text">
          <h1>Clothes made to be worn every day</h1>
          <p>Clean cuts and breathable fabrics for the Manila heat. The new season is here.</p>
          <a href="product_view.php" class="btn">Shop new arrivals</a>
        </div>
        <div class="hero-img" role="img" aria-label="Model wearing a linen shirt"></div>
      </div>
      <div class="hero-slide hero-slide-nike" data-hero-slide aria-hidden="true">
        <div class="hero-text">
          <h1>Made to move. Styled by you.</h1>
          <p>Discover Nike favorites made for every day, every workout, and everywhere in between.</p>
          <a href="<?php echo BASE_URL; ?>brands.php?brand=Nike" class="btn">Shop Nike products</a>
        </div>
        <div class="hero-img" role="img" aria-label="Nike apparel collection"></div>
      </div>
      <div class="hero-slide hero-slide-hm" data-hero-slide aria-hidden="true">
        <div class="hero-text">
          <h1>Everyday style, made effortless.</h1>
          <p>Explore modern essentials and easy-to-wear looks from H&amp;M.</p>
          <a href="<?php echo BASE_URL; ?>brands.php?brand=H%26M" class="btn">Shop H&amp;M products</a>
        </div>
        <div class="hero-img" role="img" aria-label="H&amp;M apparel collection"></div>
      </div>
      <div class="hero-slide hero-slide-penshoppe" data-hero-slide aria-hidden="true">
        <div class="hero-text">
          <h1>Make room for more style.</h1>
          <p>Find fresh everyday favorites and express your style with PENSHOPPE.</p>
          <a href="<?php echo BASE_URL; ?>brands.php?brand=PENSHOPPE" class="btn">Shop PENSHOPPE products</a>
        </div>
        <div class="hero-img" role="img" aria-label="PENSHOPPE apparel collection"></div>
      </div>
      <div class="hero-controls" role="group" aria-label="Featured slide controls">
        <button class="hero-arrow" type="button" data-hero-prev aria-label="Previous slide">&lsaquo;</button>
        <div class="hero-dots">
          <button class="hero-dot is-active" type="button" data-hero-dot="0" aria-label="Show slide 1 of 4" aria-pressed="true"></button>
          <button class="hero-dot" type="button" data-hero-dot="1" aria-label="Show slide 2 of 4" aria-pressed="false"></button>
          <button class="hero-dot" type="button" data-hero-dot="2" aria-label="Show slide 3 of 4" aria-pressed="false"></button>
          <button class="hero-dot" type="button" data-hero-dot="3" aria-label="Show slide 4 of 4" aria-pressed="false"></button>
        </div>
        <button class="hero-arrow" type="button" data-hero-next aria-label="Next slide">&rsaquo;</button>
      </div>
    </section>

    <section class="section">
      <h2>Shop by category</h2>
      <div class="cat-grid">
        <a href="<?php echo BASE_URL; ?>category.php?category=t-shirts" class="cat c1"><img src="<?php echo BASE_URL; ?>images/whiteshirt.jpg" alt=""><span>T-shirts</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=shirts" class="cat c2"><img src="<?php echo BASE_URL; ?>images/shirt-category-removebg-preview.png" alt="White button-up shirt"><span>Shirts</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=polos" class="cat c3"><img src="<?php echo BASE_URL; ?>images/polo-removebg-preview.png" alt="Polo shirt"><span>Polos</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=hoodies" class="cat c4"><img src="<?php echo BASE_URL; ?>images/hoodies-removebg-preview.png" alt="Hoodie"><span>Hoodies</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=sweaters" class="cat c5"><img src="<?php echo BASE_URL; ?>images/sweatshirt-removebg-preview.png" alt="Sweater"><span>Sweaters</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=pants" class="cat c6"><img src="<?php echo BASE_URL; ?>images/pants-removebg-preview.png" alt="Pants"><span>Pants</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=jeans" class="cat c7"><img src="<?php echo BASE_URL; ?>images/jeans-removebg-preview.png" alt="Jeans"><span>Jeans</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=chinos" class="cat c8"><img src="<?php echo BASE_URL; ?>images/chinos-removebg-preview.png" alt="Chinos"><span>Chinos</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=shorts" class="cat c9"><img src="<?php echo BASE_URL; ?>images/shorts-removebg-preview.png" alt="Khaki shorts"><span>Shorts</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=outerwear" class="cat c10"><img src="<?php echo BASE_URL; ?>images/outerwear-removebg-preview.png" alt="Outerwear jacket"><span>Outerwear</span></a>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <h2>All Products</h2>
        <a href="product_view.php">View all</a>
      </div>
      <div class="product-row">
        <?php foreach ($products as $p) {
          $card_images = $product_image_map[(int)($p['first_color_id'] ?? 0)] ?? [];
          ?>

          <article class="product">
            <div class="thumb">
              <?php if ($card_images) { $image_path = $card_images[0]; ?>
                <a class="product-image-tile" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$p['product_id']; ?>"
                   aria-label="View <?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">
                  <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>"
                       alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                </a>
              <?php } ?>
            </div>
            <a class="product-card-link" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$p['product_id']; ?>">
              <p class="name"><?php echo htmlspecialchars($p['name']); ?></p>
              <p class="price"><?php echo store_product_price_markup((float)$p['price']); ?></p>
            </a>
          </article>

        <?php } ?>
      </div>
    </section>

    <section class="feature">
    </section>

  </main>

<script>
(function () {
  var sections = document.querySelectorAll('main > .section');
  if (!sections.length || !document.documentElement.classList.contains('has-scroll-reveal')) return;

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0.01 });

  sections.forEach(function (section) {
    observer.observe(section);
  });
})();

(function () {
  var hero = document.querySelector('.hero');
  if (!hero) return;

  var slides = Array.prototype.slice.call(hero.querySelectorAll('[data-hero-slide]'));
  var dots = Array.prototype.slice.call(hero.querySelectorAll('[data-hero-dot]'));
  var currentSlide = 0;
  var timer;
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function showSlide(index) {
    currentSlide = (index + slides.length) % slides.length;
    slides.forEach(function (slide, slideIndex) {
      var active = slideIndex === currentSlide;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    dots.forEach(function (dot, dotIndex) {
      var active = dotIndex === currentSlide;
      dot.classList.toggle('is-active', active);
      dot.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function stopAutoplay() {
    window.clearInterval(timer);
  }

  function startAutoplay() {
    stopAutoplay();
    if (!reducedMotion && !hero.matches(':hover') && !hero.contains(document.activeElement)) {
      timer = window.setInterval(function () {
        showSlide(currentSlide + 1);
      }, 3000);
    }
  }

  hero.querySelector('[data-hero-prev]').addEventListener('click', function () {
    showSlide(currentSlide - 1);
    startAutoplay();
  });
  hero.querySelector('[data-hero-next]').addEventListener('click', function () {
    showSlide(currentSlide + 1);
    startAutoplay();
  });
  dots.forEach(function (dot) {
    dot.addEventListener('click', function () {
      showSlide(Number(dot.getAttribute('data-hero-dot')));
      startAutoplay();
    });
  });

  hero.addEventListener('mouseenter', stopAutoplay);
  hero.addEventListener('mouseleave', startAutoplay);
  hero.addEventListener('focusin', stopAutoplay);
  hero.addEventListener('focusout', function (event) {
    if (!hero.contains(event.relatedTarget)) startAutoplay();
  });

  function sizeHero() {
    var header = document.querySelector('.site-header');
    var subnav = document.querySelector('.sub-nav');
    var occupiedHeight = (header ? header.getBoundingClientRect().height : 0) +
      (subnav ? subnav.getBoundingClientRect().height : 0);
    hero.style.setProperty('--hero-height', Math.max(240, window.innerHeight - occupiedHeight) + 'px');
  }

  window.addEventListener('resize', sizeHero);
  sizeHero();
  startAutoplay();
})();
</script>

<?php
  include_once("includes/footer.php");
?>