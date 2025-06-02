<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';
require_once 'vendor/autoload.php';
require_once 'config/google.php';

$google_login_url = $client->createAuthUrl(); // Important line for Google login

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user && password_verify($password, $user['password'])) {
    $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $_SESSION['user'] = $user;
    header("Location: dashboard.php");
    exit();
  } else {
    $error = "Invalid email or password.";
  }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nubenta | Login and Registration Form</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel="stylesheet">
  <link rel="stylesheet" href="nubentaloginstyles.css">
</head>

<body>

  <div class="container">
    <div class="form-box login">
      <form method="POST" action="login.php">
        <h1>Login</h1>

        <?php if (!empty($error)): ?>
          <div style="color:red;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="input-box">
          <input type="email" name="email" placeholder="Email" required>
          <i class='bx bxs-user'></i>
        </div>
        <div class="input-box">
          <input type="password" name="password" placeholder="Password" required>
          <i class='bx bxs-lock-alt'></i>
        </div>
        <div class="forgot-link">
          <a href="#">Forgot password?</a>
        </div>
        <button type="submit" class="btn">Login</button>
        <p>or login with your social platforms</p>
        <div class="social-icons">
        <a href="google-login.php"><i class='bx bxl-google'></i></a>
          <a href="#"><i class='bx bxl-microsoft'></i></a>
          <a href="facebook-login.php"><i class='bx bxl-facebook'></i></a>
          <a href="#"><i class='bx bxl-twitter'></i></a>
        </div>
      </form>
    </div>

    <div class="form-box register">
      <form action="">
        <h1>Registration</h1>
        <div class="input-box">
          <input type="text" placeholder="Username" required>
          <i class='bx bxs-user'></i>
        </div>
        <div class="input-box">
          <input type="email" placeholder="Email" required>
          <i class='bx bxs-envelope'></i>
        </div>
        <div class="input-box">
          <input type="password" placeholder="Password" required>
          <i class='bx bxs-lock-alt'></i>
        </div>
        <button type="submit" class="btn">Register</button>
        <p>or register with your social platforms</p>
        <div class="social-icons">
        <a href="google-login.php"><i class='bx bxl-google'></i></a>
          <a href="#"><i class='bx bxl-microsoft'></i></a>
          <a href="facebook-login.php"><i class='bx bxl-facebook'></i></a>
          <a href="#"><i class='bx bxl-twitter'></i></a>
        </div>
      </form>
    </div>

    <div class="toggle-box">
      <div class="toggle-panel toggle-left">
        <h1>Welcome to 90!</h1>
        <p>Don't have an account yet?</p>
        <button class="btn register-btn">Register</button>
      </div>
      <div class="toggle-panel toggle-right">
        <h1>Got a 90 account?</h1></br>
        <button class="btn login-btn">Login</button>
        <p>Click here instead</p>
      </div>
    </div>
  </div>

  <script src="loginscripts.js"></script>
</body>

</html>
