<!DOCTYPE html>
<html>
<head>
  <title>Register - Nubenta</title>
  <script src="assets/js/script.js" defer></script>
</head>
<body>
  <h1>Register</h1>

  <div id="register-message" style="color:red;"></div>

  <form id="registerForm" method="POST">
  <input type="text" name="name" placeholder="Name" required>
  <input type="email" name="email" placeholder="Email" required>
  <input type="password" name="password" placeholder="Password" required>
  <button type="submit">Register</button>
  <p id="register-message"></p>
</form>

</body>
</html>
