<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/security.php';
require_auth(['admin']);
include "../connection.php";

$result = mysqli_query($conn, "SELECT UserID, Name, Role, CreatedAt FROM users ORDER BY UserID DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users</title>

<style>

:root{
  --espresso:#1a0f00;
  --dark-roast:#2d1a0a;
  --medium-roast:#5c3317;
  --caramel:#c97d30;
  --cream:#fdf6ee;
  --milk:#fff9f3;
  --green:#27ae60;
  --yellow:#d68910;
  --red:#c0392b;
}

*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:Arial, sans-serif;
}

body{
  background:var(--cream);
  padding:30px;
  color:var(--espresso);
}

/* TOP BAR */
.top-bar{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:25px;
}

.top-bar h1{
  font-size:28px;
}

.top-actions{
  display:flex;
  gap:10px;
  align-items:center;
}

/* ADD BUTTON */
.add-btn{
  text-decoration:none;
  background:var(--caramel);
  color:white;
  padding:10px 18px;
  border-radius:8px;
  font-size:14px;
  font-weight:600;
  transition:.2s;
}

.add-btn:hover{
  background:var(--medium-roast);
}

/* BACK BUTTON */
.back-btn{
  text-decoration:none;
  background:var(--espresso);
  color:white;
  padding:10px 18px;
  border-radius:8px;
  font-size:14px;
  transition:.2s;
}

.back-btn:hover{
  background:var(--medium-roast);
}

/* TABLE */
.table-container{
  background:var(--milk);
  border-radius:14px;
  overflow:hidden;
  border:1px solid rgba(201,125,48,.15);
}

table{
  width:100%;
  border-collapse:collapse;
}

thead{
  background:var(--espresso);
}

th{
  padding:16px;
  text-align:left;
  color:white;
  font-size:14px;
}

td{
  padding:16px;
  border-bottom:1px solid rgba(201,125,48,.1);
  font-size:14px;
}

tbody tr:hover{
  background:rgba(201,125,48,.05);
}

/* ROLE */
.role-admin{
  background:#eafaf1;
  color:var(--green);
  padding:5px 12px;
  border-radius:20px;
  font-size:12px;
  font-weight:bold;
}

.role-cashier{
  background:#fef9e7;
  color:var(--yellow);
  padding:5px 12px;
  border-radius:20px;
  font-size:12px;
  font-weight:bold;
}

/* PASSWORD */
.password{
  max-width:180px;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
  font-size:12px;
  font-family:monospace;
}

/* ACTIONS */
.actions{
  display:flex;
  gap:8px;
}

.delete-btn{
  text-decoration:none;
  background:var(--red);
  color:white;
  padding:8px 14px;
  border-radius:8px;
  font-size:13px;
}

.delete-btn:hover{
  opacity:.9;
}

/* RESPONSIVE */
@media(max-width:768px){

  body{
    padding:15px;
  }

  .table-container{
    overflow-x:auto;
  }

  table{
    min-width:700px;
  }

}

</style>
<?php render_session_history_guard(); ?>
</head>

<body>

<!-- TOP -->
<div class="top-bar">

  <h1>Manage Users</h1>

  <div class="top-actions">

    <a href="../login/F_register.php" class="add-btn">
      + Add User
    </a>

    <a href="../admin_dash.php" class="back-btn">
      ← Back
    </a>

  </div>

</div>

<!-- TABLE -->
<div class="table-container">

<table>

<thead>
<tr>
  <th>ID</th>
  <th>Name</th>
  <th>Role</th>
  <th>Password</th>
  <th>Created</th>
  <th>Actions</th>
</tr>
</thead>

<tbody>

<?php while($row = mysqli_fetch_assoc($result)) { ?>

<tr>

<td>
  <?php echo (int)$row['UserID']; ?>
</td>

<td>
  <?php echo security_escape($row['Name']); ?>
</td>

<td>

<?php if($row['Role'] == 'admin') { ?>

  <span class="role-admin">
    Admin
  </span>

<?php } else { ?>

  <span class="role-cashier">
    Cashier
  </span>

<?php } ?>

</td>

<td class="password">
  <span aria-label="Password hidden">Hidden</span>
</td>

<td>
  <?php echo security_escape($row['CreatedAt']); ?>
</td>

<td>

<div class="actions">

<a 
href="delete_user.php?id=<?php echo (int)$row['UserID']; ?>"
class="delete-btn"
onclick="return confirm('Delete this user?')"
>
Delete
</a>

</div>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</body>
</html>