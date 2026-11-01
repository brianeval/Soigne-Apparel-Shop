<?php
session_start();
include_once('../includes/config.php');

$login_redirect = BASE_URL . 'users/profile.php';
include("../includes/require_customer.php");

$user_id = (int)$_SESSION['user_id'];
$errors = [];

// Profile details
$profile_result = mysqli_execute_query($conn, "SELECT u.username, u.email, c.customer_name, c.phone, c.address
                               FROM users u
                               LEFT JOIN customers c ON c.user_id = u.user_id
                               WHERE u.user_id = ?", [$user_id]);
$profile = mysqli_fetch_assoc($profile_result);

$name = $profile['customer_name'] ?? '';
$phone = $profile['phone'] ?? '';
$address = $profile['address'] ?? '';
$username = $profile['username'];
$email = $profile['email'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['form_action'] ?? '';

    if ($form_action == 'update_photo') {
        // Change the profile picture
        $upload = $_FILES['profile_photo'];
        $upload_folder = 'profiles';
        $upload_prefix = 'profile-' . $user_id . '-';
        include("../includes/upload_image.php");

        if ($upload_error != '') {
            $errors[] = $upload_error;
        } elseif ($saved_image == null) {
            $errors[] = 'Please choose a profile picture to upload.';
        } else {
            // Delete the old pictures of this user
            foreach (glob(APP_ROOT . '/images/profiles/profile-' . $user_id . '-*') as $old_file) {
                if (basename($old_file) != $saved_image) {
                    unlink($old_file);
                }
            }
            $_SESSION['success'] = 'Your profile picture has been updated.';
            header('Location: ' . BASE_URL . 'users/profile.php');
            exit;
        }
    } elseif ($form_action == 'update_details') {
        // Change the personal information
        $name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Basic checks
        if ($name == '') {
            $errors[] = 'Please enter your name.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3-50 characters: letters, numbers and underscores only.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($password != '' || $confirm_password != '') {
            if (strlen($password) < 8) {
                $errors[] = 'A new password must be at least 8 characters.';
            }
            if ($password != $confirm_password) {
                $errors[] = 'The new password and confirmation do not match.';
            }
        }

        if (!$errors) {
            try {
                mysqli_begin_transaction($conn);

                // Update account details
                $account_update_result = mysqli_execute_query($conn, "UPDATE users SET username = ?, email = ? WHERE user_id = ?", [$username, $email, $user_id]);

                // Save customer details
                $customer_details_result = mysqli_execute_query($conn, "INSERT INTO customers (user_id, customer_name, phone, address)
                                               VALUES (?, ?, ?, ?)
                                               ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name),
                                                                       phone = VALUES(phone),
                                                                       address = VALUES(address)", [$user_id, $name, $phone, $address]);

                // Update password (only when a new one was typed)
                if ($password != '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $password_update_result = mysqli_execute_query($conn, "UPDATE users SET password = ? WHERE user_id = ?", [$password_hash, $user_id]);
                }

                mysqli_commit($conn);
                $_SESSION['username'] = $username;
                $_SESSION['success'] = 'Your profile has been updated.';
                header('Location: ' . BASE_URL . 'users/profile.php');
                exit;
            } catch (mysqli_sql_exception $e) {
                mysqli_rollback($conn);
                if ($e->getCode() == 1062) {
                    $errors[] = 'That username or email is already registered.';
                } else {
                    $errors[] = 'Could not update your profile. Please try again.';
                }
            }
        }
    }
}

$page_title = 'My profile | Soigné';
$extra_css = 'profile.css';
include_once('../includes/header.php');
?>

<main class="profile-page">
  <section class="profile-card" aria-labelledby="profile-title">
    <div class="profile-heading">
      <div>
        <h1 id="profile-title">My profile</h1>
        <p>View and update your account details.</p>
      </div>
    </div>

    <?php include("../includes/alert.php"); ?>

    <?php if ($errors) { ?>
      <div class="profile-message is-error" role="alert">
        <ul>
          <?php foreach ($errors as $error) { ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php } ?>
        </ul>
      </div>
    <?php } ?>

    <div class="profile-panels">
      <section class="profile-panel profile-picture-panel" aria-labelledby="profile-picture-title">
        <div class="profile-panel-heading">
          <h2 id="profile-picture-title">Profile picture</h2>
        </div>
        <form method="post" action="<?php echo BASE_URL; ?>users/profile.php" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="form_action" value="update_photo">
          <img class="profile-photo-preview" id="profile-photo-preview"
               src="<?php echo $profile_image_url; ?>"
               alt="Your profile picture">
          <div class="profile-photo-picker">
            <label for="profile_photo">Choose a new picture</label>
            <div class="profile-file-picker">
              <label class="profile-file-button" for="profile_photo">Choose file</label>
              <span class="profile-file-name" id="profile-file-name">No file chosen</span>
              <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp">
            </div>
            <small>JPEG, PNG, or WebP; max 5 MB.</small>
          </div>
          <div class="profile-photo-actions">
            <button class="btn" type="submit">Update picture</button>
          </div>
        </form>
      </section>

      <section class="profile-panel profile-details-panel" aria-labelledby="profile-details-title">
        <div class="profile-panel-heading">
          <h2 id="profile-details-title">Personal information</h2>
        </div>
        <form method="post" action="<?php echo BASE_URL; ?>users/profile.php" id="profile-form" novalidate>
          <input type="hidden" name="form_action" value="update_details">
          <div class="profile-field">
            <label for="customer_name">Name</label>
            <input type="text" id="customer_name" name="customer_name"
                   value="<?php echo htmlspecialchars($name); ?>">
          </div>

          <div class="profile-field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?php echo htmlspecialchars($username); ?>">
          </div>

          <div class="profile-field">
            <label for="email">Email</label>
            <input type="text" id="email" name="email"
                   value="<?php echo htmlspecialchars($email); ?>">
          </div>

          <div class="profile-field">
            <label for="phone">Phone number</label>
            <input type="tel" id="phone" name="phone"
                   value="<?php echo htmlspecialchars($phone); ?>">
          </div>

          <div class="profile-field">
            <label for="address">Address</label>
            <textarea id="address" name="address"><?php echo htmlspecialchars($address); ?></textarea>
          </div>

          <div class="profile-password-fields">
            <p>Leave these blank if you don't want to change your password.</p>
            <div class="profile-field">
              <label for="new_password">New password</label>
              <input type="password" id="new_password" name="new_password"
                     autocomplete="new-password">
            </div>
            <div class="profile-field">
              <label for="confirm_password">Confirm new password</label>
              <input type="password" id="confirm_password" name="confirm_password"
                     autocomplete="new-password">
            </div>
          </div>

          <div class="profile-actions">
            <button class="btn" type="submit">Save changes</button>
          </div>
        </form>
      </section>
    </div>
  </section>
</main>

<?php include_once('../includes/footer.php'); ?>