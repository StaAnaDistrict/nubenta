<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header("Location: login.php");
  exit();
}

$conn = new mysqli("localhost", "root", "", "nubenta_db");

$result = $conn->query("SELECT * FROM users");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin - Manage Users</title>
  <script src="admin_script.js" defer></script>
</head>
<body>
  <h2>Admin Panel - Manage Users</h2>

  <input type="text" id="searchInput" placeholder="Search by name or email..." style="margin-bottom: 15px; padding: 5px; width: 300px;">
  <table id="userTable" border="1" cellpadding="10">
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Email</th>
      <th>Role</th>
      <th>Last Login</th>
      <th>Status</th>
      <th>Actions</th>


    </tr>
    <?php while ($user = $result->fetch_assoc()) : ?>
  <tr>
    <form class="admin-update-form">
      <td><?php echo $user['id']; ?></td>
      <td><input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>"></td>
      <td><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"></td>
      <td>
        <select name="role">
          <option value="user" <?php if ($user['role'] === 'user') echo 'selected'; ?>>user</option>
          <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>admin</option>
        </select>
      </td>
      <td><?php echo $user['last_login'] ? $user['last_login'] : 'Never'; ?></td>
      <td>
        <?php
          if ($user['suspended_until'] && strtotime($user['suspended_until']) > time()) {
            echo "<span style='color: red;'>Suspended</span>";
          } else {
            echo "<span style='color: green;'>Active</span>";
          }
        ?>
      </td>
      <td>
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        <?php if ($user['suspended_until'] && strtotime($user['suspended_until']) > time()): ?>
          <button type="button" onclick="liftSuspension(<?php echo $user['id']; ?>)">Lift Suspension</button>
        <?php else: ?>
          <button type="button" onclick="suspendUser(<?php echo $user['id']; ?>)">Suspend</button>
        <?php endif; ?>

        <button type="submit">Update</button>
        <button type="button" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</button>
      </td>
    </form>
  </tr>
<?php endwhile; ?>

  </table>

  <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>
