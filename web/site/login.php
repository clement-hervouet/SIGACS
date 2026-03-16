<?php
// Initialize sessions
session_start();

// Check if the user is already logged in, if yes then redirect him to welcome page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
  header("location: welcome.php");
  exit;
}

// Include config file
require_once "config/config.php";

// Define variables and initialize with empty values
$username = $password = '';
$username_err = $password_err = '';

// Process submitted form data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Check if username is empty
  if (empty(trim($_POST['username']))) {
    $username_err = 'Please enter username.';
  } else {
    $raw_username = $_POST['username'];
    $username = sanitize_input($raw_username);

    if (!is_safe_input($username) || !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
      $username_err = 'Invalid username format.';
    }
  }

  // Check if password is empty
  if (empty(trim($_POST['password']))) {
    $password_err = 'Please enter your password.';
  } else {
    $password = trim($_POST['password']);
  }

  // Validate credentials
  if (empty($username_err) && empty($password_err)) {
    // Prepare a select statement using PDO
    $sql = 'SELECT id, username, password FROM users WHERE username = ?';
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$username])) {
      $row = $stmt->fetch();
      if ($row) {
        $id = $row['id'];
        $username = $row['username'];
        $hashed_password = $row['password'];

        if (password_verify($password, $hashed_password)) {
          // Store data in session
          $_SESSION['loggedin'] = true;
          $_SESSION['id'] = $id;
          $_SESSION['username'] = $username;

          // Redirect to user page
          header('location: welcome.php');
          exit;
        } else {
          // Display an error for password mismatch
          $password_err = 'Invalid password';
        }
      } else {
        $username_err = "Username does not exists.";
      }
    } else {
      echo "Oops! Something went wrong please try again";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Sign in</title>
  <link href="https://stackpath.bootstrapcdn.com/bootswatch/4.4.1/cosmo/bootstrap.min.css" rel="stylesheet" integrity="sha384-qdQEsAI45WFCO5QwXBelBe1rR9Nwiss4rGEqiszC+9olH1ScrLrMQr1KmDR964uZ" crossorigin="anonymous">
  <style>
    .wrapper {
      width: 500px;
      padding: 20px;
    }

    .wrapper h2 {
      text-align: center
    }

    .wrapper form .form-group span {
      color: red;
    }
  </style>
</head>

<body>
  <main>
    <section class="container wrapper">
      <h2 class="display-4 pt-3">Login</h2>
      <p class="text-center">Please fill in your credentials.</p>
      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <div class="form-group <?php (!empty($username_err)) ? 'has_error' : ''; ?>">
          <label for="username">Username</label>
          <input type="text" name="username" id="username" class="form-control" value="<?php echo $username ?>">
          <span class="help-block"><?php echo $username_err; ?></span>
        </div>

        <div class="form-group <?php (!empty($password_err)) ? 'has_error' : ''; ?>">
          <label for="password">Password</label>
          <input type="password" name="password" id="password" class="form-control" value="<?php echo $password ?>">
          <span class="help-block"><?php echo $password_err; ?></span>
        </div>

        <div class="form-group">
          <input type="submit" class="btn btn-block btn-outline-primary" value="login">
        </div>
        <p>Don't have an account? <a href="register.php">Sign in</a>.</p>
      </form>
    </section>
  </main>
</body>

</html>