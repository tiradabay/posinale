<?php
require_once __DIR__ . '/../components/auth_check.php';
$role = get_authenticated_role();
if ($role !== null) {
    header('Location: ' . get_auth_redirect_urls()[$role]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">

<!-- Font Awesome for clean eye icon -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<title>Login</title>
</head>

<body>

<div class="card">
  <div class="card2">

    <form class="form" id="loginForm">

      <div class="logo">
        <img src="../assets/logo.png" alt="Logo">
      </div>

      <p id="heading">Login</p>

      <div class="field">
        <input 
          type="text" 
          id="name" 
          class="input-field"
          placeholder="Email or Username"
          autocomplete="off"
        >
      </div>

      <!-- PASSWORD FIELD -->
      <div class="field password-field">
        <input 
          type="password" 
          id="password"
          class="input-field"
          placeholder="Password"
        >

        <!-- EYE ICON (Facebook style) -->
        <span onclick="togglePassword()" class="eye-icon">
          <i id="eyeIcon" class="fa-solid fa-eye-slash"></i>
        </span>
      </div>

      <div class="btn">
        <button type="submit" class="button1">
          Log In
        </button>
      </div>

      <p id="msg"></p>

    </form>

  </div>
</div>

<!-- STYLE FOR EYE -->
<style>
.password-field{
    position:relative;
}

.eye-icon{
    position:absolute;
    right:12px;
    top:50%;
    transform:translateY(-50%);
    cursor:pointer;
    color:#6d3916;
    font-size:14px;
}
.eye-icon:hover{
    opacity:0.7;
}
</style>

<script>

// TOGGLE PASSWORD
function togglePassword(){
    const pass = document.getElementById("password");
    const icon = document.getElementById("eyeIcon");

    if(pass.type === "password"){
        pass.type = "text";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    } else {
        pass.type = "password";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    }
}

// LOGIN
document.getElementById("loginForm").addEventListener("submit", function(e){
    e.preventDefault();

    const name = document.getElementById("name").value.trim();
    const password = document.getElementById("password").value;

    if(name === "" || password === ""){
        alert("⚠️ Please input username and password");
        return;
    }

    fetch("login.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({ name, password })
    })
    .then(res => res.json())
    .then(data => {

        if(!data.success){
            alert("❌ Invalid username or password");
            return;
        }

        alert("✅ Login Successful!");

        if(data.role === "admin"){
            window.location.href = "../admin_dash.php";
        } else {
            window.location.href = "../cashier_dash.php";
        }

    })
    .catch(() => {
        alert("❌ Server error");
    });
});

</script>

</body>
</html>
