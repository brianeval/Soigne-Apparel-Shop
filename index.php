<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Soigné | Men</title>
  <link rel="stylesheet" href="homepage.css">
</head>
<body>

  <header class="site-header">
    <a href="#" class="logo">Soigné</a>
    <nav class="main-nav" aria-label="Main">
      <a href="#" class="active">Men</a>
      <a href="#">Women</a>
      <a href="#">Kids</a>
      <a href="#">Sale</a>
    </nav>
    <form class="search" role="search" onsubmit="return false">
      <input type="search" placeholder="Search products" aria-label="Search products">
    </form>
    <div class="icons">
      <a href="#">Account</a>
      <a href="#">Wishlist</a>
      <a href="#">Cart (0)</a>
    </div>
  </header>

  <nav class="sub-nav" aria-label="Men categories">
    <a href="#">New arrivals</a>
    <a href="#">T-shirts</a>
    <a href="#">Shirts</a>
    <a href="#">Pants</a>
    <a href="#">Shorts</a>
    <a href="#">Outerwear</a>
    <a href="#">Innerwear</a>
    <a href="#">Accessories</a>
  </nav>

  <main>
    <section class="hero">
      <div class="hero-text">
        <h1>Clothes made to be worn every day</h1>
        <p>Clean cuts and breathable fabrics for the Manila heat. The new season is here.</p>
        <a href="#" class="btn">Shop new arrivals</a>
      </div>
      <div class="hero-img" role="img" aria-label="Model wearing a linen shirt"></div>
    </section>

    <section class="section">
      <h2>Shop by category</h2>
      <div class="cat-grid">
        <a href="#" class="cat c1"><span>T-shirts</span></a>
        <a href="#" class="cat c2"><span>Shirts</span></a>
        <a href="#" class="cat c3"><span>Pants</span></a>
        <a href="#" class="cat c4"><span>Outerwear</span></a>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <h2>Best sellers</h2>
        <a href="#">View all</a>
      </div>
      <div class="product-row">
        <a href="#" class="product">
          <div class="thumb p1"></div>
          <p class="name">Airy Cotton Crew Neck T-Shirt</p>
          <p class="price">₱590</p>
        </a>
        <a href="#" class="product">
          <div class="thumb p2"></div>
          <p class="name">Premium Linen Long Sleeve Shirt</p>
          <p class="price">₱1,490</p>
        </a>
        <a href="#" class="product">
          <div class="thumb p3"></div>
          <p class="name">Relaxed Ankle Chino Pants</p>
          <p class="price">₱1,290</p>
        </a>
        <a href="#" class="product">
          <div class="thumb p4"></div>
          <p class="name">Lightweight Packable Jacket</p>
          <p class="price sale">₱1,790 <s>₱2,290</s></p>
        </a>
        <a href="#" class="product">
          <div class="thumb p5"></div>
          <p class="name">Quick-Dry Sports Shorts</p>
          <p class="price">₱790</p>
        </a>
      </div>
    </section>

    <section class="feature">
      <div class="feature-img" role="img" aria-label="Folded basics"></div>
      <div class="feature-text">
        <h2>Basics that last</h2>
        <p>Plain colors, honest fabrics, and fits that work with everything already in your closet.</p>
        <a href="#" class="btn btn-outline">Shop the basics</a>
      </div>
    </section>

    <section class="perks">
      <div><h3>Free returns</h3><p>Return within 30 days, no questions.</p></div>
      <div><h3>Pick up in store</h3><p>Order online, collect the same day.</p></div>
      <div><h3>Member rewards</h3><p>Earn points on every purchase.</p></div>
    </section>

    <section class="newsletter">
      <h2>Get new arrivals in your inbox</h2>
      <form onsubmit="return false">
        <input type="email" placeholder="Email address" aria-label="Email address">
        <button class="btn" type="submit">Subscribe</button>
      </form>
    </section>
  </main>

  <footer class="site-footer">
    <div class="cols">
      <div><h4>Shop</h4><a href="#">Men</a><a href="#">Women</a><a href="#">Kids</a><a href="#">Sale</a></div>
      <div><h4>Help</h4><a href="#">Size guide</a><a href="#">Shipping</a><a href="#">Returns</a><a href="#">Contact us</a></div>
      <div><h4>About</h4><a href="#">Our story</a><a href="#">Stores</a><a href="#">Careers</a></div>
    </div>
    <p class="copy">© 2026 Soigné Apparel Shop. All rights reserved.</p>
  </footer>

</body>
</html>