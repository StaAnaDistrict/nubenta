<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user && password_verify($password, $user['password'])) {
    // Update only last_login timestamp, not updated_at
    $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    // Refresh user data to include updated last_login
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
<html>
<head>
  <title>Login - Nubenta</title>
</head>
<body>
  <h1>Login</h1>

  <?php if (!empty($error)): ?>
    <div style="color:red;"><?php echo $error; ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>Password:</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Login</button>
  </form>
</body>
</html>
