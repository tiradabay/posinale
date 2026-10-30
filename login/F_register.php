<?php
require_once __DIR__ . '/../components/auth_check.php';
require_auth(['admin']);
require_once __DIR__ . '/../components/security.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<title>Add Staff</title>
<?php render_session_history_guard(); ?>
</head>
<body>
<div class="card"><div class="card2">
<form class="form" id="staffForm" method="post" action="b_register.php">
  <div class="logo"><img src="../assets/logo.png" alt="Logo"></div>
  <p id="heading">Add Staff</p>
  <input type="hidden" name="csrf_token" value="<?php echo security_escape(security_csrf_token()); ?>">
  <div class="field"><input type="text" name="name" class="input-field" placeholder="Staff name" aria-label="Staff name" required maxlength="100" autocomplete="off"></div>
  <div class="field"><input type="password" name="password" class="input-field" placeholder="Password" aria-label="Password" required autocomplete="new-password"></div>
  <div class="field"><select name="role" class="input-field" aria-label="Staff role" required><option value="cashier">Cashier</option><option value="admin">Admin</option></select></div>
  <div class="btn"><button type="submit" class="button1">Create Staff</button></div>
  <p id="msg" role="status" aria-live="polite"></p>
  <a href="../components/manage_user.php" style="color:inherit">Back to Manage Users</a>
</form>
</div></div>
<script>
document.getElementById('staffForm').addEventListener('submit', async function(event) {
  event.preventDefault();
  const button = this.querySelector('button[type="submit"]');
  const message = document.getElementById('msg');
  button.disabled = true;
  try {
    const response = await fetch(this.action, {method: 'POST', body: new FormData(this), credentials: 'same-origin'});
    const data = await response.json();
    message.textContent = data.success ? data.message : (data.error || 'Unable to create staff account.');
    if (data.success) this.reset();
  } catch (_) { message.textContent = 'Unable to create staff account. Please try again.'; }
  finally { button.disabled = false; }
});
</script>
</body>
</html>