<?php
// Include config file
require_once 'config/config.php';


// Define variables and initialize with empty values
$username = $password = $confirm_password = "";

$username_err = $password_err = $confirm_password_err = "";

// Process submitted form data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// Check if username is empty
	$raw_username = isset($_POST['username']) ? $_POST['username'] : null;
	if (empty(trim($raw_username))) {
		$username_err = "Please enter a username.";
	} else {
		$param_username = sanitize_input($raw_username);

		// Basic username format validation (alphanumeric + underscore, 3-50 chars)
		if (!is_safe_input($param_username) || !preg_match('/^[A-Za-z0-9_]{3,50}$/', $param_username)) {
			$username_err = 'Invalid username format.';
		} else {
			// Use transaction to avoid race conditions when checking/inserting
			try {
				$pdo->beginTransaction();

				// Lock the row if exists to prevent race conditions
				$sql = 'SELECT id FROM users WHERE username = ? FOR UPDATE';
				$stmt = $pdo->prepare($sql);
				$stmt->execute([$param_username]);

				if ($stmt->rowCount() == 1) {
					$username_err = 'This username is already taken.';
					// Leave transaction open for rollback later when needed
				} else {
					$username = $param_username;
				}
			} catch (Exception $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				$username_err = 'Something went wrong. Please try again later.';
			}
		}
	}

	// Validate password
	if (empty(trim($_POST["password"]))) {
		$password_err = "Please enter a password.";
	} elseif (strlen(trim($_POST["password"])) < 6) {
		$password_err = "Password must have atleast 6 characters.";
	} else {
		$password = trim($_POST["password"]);
	}

	// Validate confirm password
	if (empty(trim($_POST["confirm_password"]))) {
		$confirm_password_err = "Please confirm password.";
	} else {
		$confirm_password = trim($_POST["confirm_password"]);
		if (empty($password_err) && ($password != $confirm_password)) {
			$confirm_password_err = "Password did not match.";
		}
	}

	// Check input error before inserting into database
	if (empty($username_err) && empty($password_err) && empty($confirm_password_err)) {
		try {
			// Ensure transaction started earlier during username check; if not, start one
			if (!$pdo->inTransaction()) {
				$pdo->beginTransaction();
			}

			// Prepare insert statement
			$sql = 'INSERT INTO users (username, password) VALUES (?,?)';
			$stmt = $pdo->prepare($sql);

			// Set parameters
			$param_password = password_hash($password, PASSWORD_DEFAULT);

			// Attempt to execute
			if ($stmt->execute([$username, $param_password])) {
				$pdo->commit();
				header('location: ./login.php');
				exit;
			} else {
				// Execution failed
				$pdo->rollBack();
				echo "Something went wrong. Try signing in again.";
			}
		} catch (Exception $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			echo "Something went wrong. Try signing in again.";
		}
	} else {
		// Validation failed — rollback any open transaction from username check
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
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
			<h2 class="display-4 pt-3">Sign Up</h2>
			<p class="text-center">Please fill this form to create an account.</p>
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

				<div class="form-group <?php (!empty($confirm_password_err)) ? 'has_error' : ''; ?>">
					<label for="confirm_password">Confirm Password</label>
					<input type="password" name="confirm_password" id="confirm_password" class="form-control" value="<?php echo $confirm_password; ?>">
					<span class="help-block"><?php echo $confirm_password_err; ?></span>
				</div>

				<div class="form-group">
					<input type="submit" class="btn btn-block btn-outline-success" value="Submit">
					<input type="reset" class="btn btn-block btn-outline-primary" value="Reset">
				</div>
				<p>Already have an account? <a href="login.php">Login here</a>.</p>
			</form>
		</section>
	</main>
</body>

</html>