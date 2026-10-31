<?php
session_start();
include_once('../includes/config.php');
require_once('../includes/product_images.php');

$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
if (!is_string($redirect) || strpos($redirect, '/') !== 0 || strpos($redirect, '//') === 0) {
    $redirect = BASE_URL . 'index.php';
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $redirect);
    exit;
}

$errors = [];
$name = '';
$username = '';
$email = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $errors[] = 'Please enter your full name.';
    }
    if ($username === '') {
        $errors[] = 'Please enter a username.';
    }
    if ($email === '') {
        $errors[] = 'Please enter your email address.';
    }
    if ($password === '') {
        $errors[] = 'Please enter a password.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $username_sql = mysqli_real_escape_string($conn, $username);
        $email_sql = mysqli_real_escape_string($conn, $email);

        $taken = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT username, email FROM users WHERE username = '$username_sql' OR email = '$email_sql'"));
        if ($taken) {
            $errors[] = strcasecmp($taken['username'], $username) === 0
                ? 'That username is already taken.'
                : 'That email is already registered.';
        }
    }

    if (!$errors) {
        $name_sql = mysqli_real_escape_string($conn, $name);
        $phone_sql = mysqli_real_escape_string($conn, $phone);
        $address_sql = mysqli_real_escape_string($conn, $address);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        mysqli_query($conn,
            "INSERT INTO users (username, email, password) VALUES ('$username_sql', '$email_sql', '$password_hash')");
        $user_id = mysqli_insert_id($conn);

        mysqli_query($conn,
            "INSERT INTO customers (user_id, customer_name, phone, address)
             VALUES ($user_id, '$name_sql', '$phone_sql', '$address_sql')");

        if (!empty($_FILES['profile_photo']['name'])) {
            save_image_upload($_FILES['profile_photo'], 'profiles', 'profile-' . $user_id);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'customer';
        header('Location: ' . $redirect);
        exit;
    }
}

$page_title = 'Create account | Soigné';
$extra_css = 'login.css';
include_once('../includes/header.php');
?>

<main class="login-page">
  <div class="login-card">
    <h1>Create account</h1>
    <p class="login-sub">Sign up to add items to your cart and track your orders.</p>

    <?php if ($errors) { ?>
      <div class="login-error" role="alert">
        <ul>
          <?php foreach ($errors as $err) { ?>
            <li><?php echo htmlspecialchars($err); ?></li>
          <?php } ?>
        </ul>
      </div>
    <?php } ?>

    <form method="post" action="register.php" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

      <label for="customer_name">Full name</label>
      <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($name); ?>" autofocus>

      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">

      <label for="email">Email</label>
      <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">

      <label for="phone">Phone number</label>
      <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">

      <label for="address">Address</label>
      <textarea id="address" name="address"><?php echo htmlspecialchars($address); ?></textarea>

      <label for="profile_photo">Profile image (optional)</label>
      <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp">
      <small id="profile-photo-help" class="register-photo-help">JPEG, PNG, or WebP - 5 MB max</small>

      <label for="password">Password</label>
      <input type="password" id="password" name="password">

      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password">

      <button class="btn" type="submit">Create account</button>
    </form>

    <p class="login-alt">
      Already have an account?
      <a href="login.php?redirect=<?php echo urlencode($redirect); ?>">Sign in</a>
    </p>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>
