
<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew Logic Coffee Ordering System</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>

html{
    scroll-behavior:smooth;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:#ece4d3;
    color:#4b2d16;
}

/* HEADER */
header{
    width:100%;
    background:rgba(221, 211, 191, 0.95);
    backdrop-filter: blur(8px);
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 40px;
    position:sticky;
    top:0;
    z-index:1000;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

/* LOGO */
.logo{
    display:flex;
    align-items:center;
    gap:10px;
}

.logo img{
    width:85px;
    height:85px;
    object-fit:cover;
    border-radius:15px;   /* soft corners pa rin */
    border:none;          /* removed border */
    background:transparent;
    padding:0;
}
/* NAV */
nav ul{
    display:flex;
    list-style:none;
    gap:20px;
    align-items:center;
}

nav ul li a{
    text-decoration:none;
    color:#3b2412;
    font-size:14px;
    font-weight:600;
    padding:8px 12px;
    border-radius:8px;
    transition:.3s;
}

nav ul li a:hover{
    background:#cbb89a;
    color:#000;
    transform:translateY(-2px);
}

/* HERO SECTION */

.hero{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:50px 70px;
    background:#ece4d3;
}

.hero-container{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:40px;
    width:100%;
    flex-wrap:wrap;
}

.hero-image{
    flex:1;
    display:flex;
    justify-content:flex-start;
}

.hero-image img{
    width:700px;
    max-width:100%;
    height:auto;
    border-radius:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
}

.hero-content{
    flex:1;
    max-width:550px;
}

.hero-content h1{
    font-size:50px;
    color:#5f3313;
    margin-bottom:20px;
    line-height:1.3;
}

.hero-content p{
    font-size:18px;
    color:#6f6255;
    line-height:1.8;
    margin-bottom:30px;
}

.hero-content button{
    padding:14px 35px;
    border:none;
    border-radius:30px;
    background:#6d3916;
    color:white;
    font-size:16px;
    font-weight:bold;
    cursor:pointer;
    transition:.3s;
}

.hero-content button:hover{
    background:#8b4d1f;
    transform:scale(1.05);
}

/* ABOUT */

.about{
    text-align:center;
    padding:80px 20px 50px;
}

.about h1{
    font-size:40px;
    color:#6b3a18;
    margin-bottom:20px;
}

.line{
    width:75%;
    height:2px;
    background:#bca98d;
    margin:20px auto;
}

.about h2{
    font-size:28px;
    margin-bottom:20px;
}

.about p{
    max-width:850px;
    margin:auto;
    line-height:1.9;
    color:#6b5a49;
    font-size:16px;
}

/* FEATURES */

.features{
    display:flex;
    justify-content:center;
    gap:30px;
    flex-wrap:wrap;
    padding:50px 20px;
}

.card{
    width:250px;
    background:#f3eadb;
    border-radius:18px;
    padding:30px 20px;
    text-align:center;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
    transition:.4s;
}

.card:hover{
    transform:translateY(-10px);
}

.card i{
    font-size:45px;
    color:#7a4720;
    margin-bottom:20px;
}

.card h3{
    font-size:22px;
    margin-bottom:15px;
    color:#5f3313;
}

.card p{
    font-size:15px;
    color:#6f6255;
    line-height:1.7;
}

/* PROMOTION */

.system{
    width:85%;
    margin:40px auto;
    background:#6d3916;
    color:white;
    padding:45px;
    border-radius:20px;
    text-align:center;
}

.system h1{
    font-size:40px;
    margin-bottom:20px;
}

.system p{
    font-size:18px;
    line-height:1.8;
}

/* CONTACT */

.contact{
    padding:70px 20px;
    text-align:center;
}

.contact h2{
    margin-bottom:40px;
    font-size:32px;
}

.contact-box{
    display:flex;
    justify-content:center;
    gap:50px;
    flex-wrap:wrap;
}

.info{
    background:#f3eadb;
    padding:30px;
    width:220px;
    border-radius:15px;
    box-shadow:0 4px 10px rgba(0,0,0,0.1);
    transition:.3s;
}

.info:hover{
    transform:translateY(-8px);
}

.info i{
    font-size:25px;
    color:#8b4d1f;
    margin-bottom:15px;
}

.info p{
    color:#5f5145;
    line-height:1.7;
}

/* FOOTER */

footer{
    background:#ddd3bf;
    text-align:center;
    padding:18px;
    font-size:13px;
}

/* RESPONSIVE */

@media(max-width:768px){

    header{
        flex-direction:column;
        gap:20px;
        padding:20px;
    }

    nav ul{
        gap:20px;
        flex-wrap:wrap;
        justify-content:center;
    }

    .hero{
        padding:40px 20px;
    }

    .hero-container{
        flex-direction:column;
        text-align:center;
    }

    .hero-image{
        justify-content:center;
    }

    .hero-image img{
        width:100%;
    }

    .hero-content h1{
        font-size:35px;
    }

    .hero-content p{
        font-size:16px;
    }

    .about h1{
        font-size:30px;
    }

    .about h2{
        font-size:22px;
    }

    .system h1{
        font-size:28px;
    }

}

</style>
</head>
<body>

<!-- HEADER -->

<header>

    <div class="logo">

        <!-- LOGO IMAGE -->
          <img src="assets/logo.png" alt="Logo">

    </div>

    <nav>
        <ul>
            <li><a href="#home">HOME</a></li>
            <li><a href="#features">FEATURES</a></li>
            <li><a href="#about">ABOUT</a></li>
            <li><a href="#contact">CONTACT</a></li>
        </ul>
    </nav>

</header>

<!-- HOME -->

<section class="hero" id="home">

    <div class="hero-container">

        <!-- LEFT SIDE IMAGE -->

        <div class="hero-image">
            <img src="https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?q=80&w=1200&auto=format&fit=crop">
        </div>

        <!-- RIGHT SIDE CONTENT -->

        <div class="hero-content">

            <h1>
                Point-of-Sale System Web Application with Inventory,
                Sales and Loyalty Program
            </h1>

            <p>
                Modern and smart coffee shop management system designed
                to improve transactions, customer service, inventory tracking,
                and sales monitoring in one platform.
            </p>

           <button onclick="window.location.href='login/F_login.php'">
             Get Started
         </button>
        </div>

    </div>

</section>

<!-- ABOUT -->

<section class="about" id="about">

    <h1>Modern Solution for Coffee Shops</h1>

    <div class="line"></div>

    <h2>Simple, Fast, and Convenient</h2>

    <p>
        Brew Logic Coffee Ordering System helps coffee shops improve
        customer service by providing an easy and organized way of ordering.
        The system reduces waiting time, manages orders efficiently,
        and creates a better experience for customers and staff.
    </p>

</section>

<!-- FEATURES -->

<section class="features" id="features">

    <div class="card">

        <i class="fa-solid fa-table-columns"></i>

        <h3>Dashboard</h3>

        <p>
            Monitor orders, transactions, and daily activities
            through a simple and organized dashboard interface.
        </p>

    </div>

    <div class="card">

        <i class="fa-solid fa-boxes-stacked"></i>

        <h3>Inventory Management</h3>

        <p>
            Track coffee ingredients and supplies easily
            to avoid shortages and manage stocks efficiently.
        </p>

    </div>

    <div class="card">

        <i class="fa-solid fa-gift"></i>

        <h3>Customer Loyalty</h3>

        <p>
            Reward customers with points and special perks
            to encourage repeat orders and customer satisfaction.
        </p>

    </div>

    <div class="card">

        <i class="fa-solid fa-chart-line"></i>

        <h3>Sales Analytics</h3>

        <p>
            View sales reports and business performance
            to help improve decision-making and shop operations.
        </p>

    </div>

</section>

<!-- PROMOTION -->

<section class="system">

    <h1>Upgrade Your Coffee Shop Experience</h1>

    <p>
        Brew Logic Coffee Ordering System is designed to provide
        convenience, speed, and efficiency in every transaction.
        With smart features like dashboard monitoring, inventory
        management, loyalty rewards, and sales analytics,
        businesses can improve customer experience and daily operations.
    </p>

</section>

<!-- CONTACT -->

<section class="contact" id="contact">

    <h2>Get In Touch With Us</h2>

    <div class="contact-box">

        <div class="info">
            <i class="fa-solid fa-location-dot"></i>
            <p>Address: KCC Mall</p>
        </div>

        <div class="info">
            <i class="fa-solid fa-phone"></i>
            <p>+639876534563</p>
        </div>

        <div class="info">
            <i class="fa-solid fa-envelope"></i>
            <p>brewlogic@gmail.com</p>
        </div>

    </div>

</section>

<!-- FOOTER -->

<footer>
    © 2026 Brew Logic Coffee Ordering System | All Rights Reserved
</footer>

</body>
</html>