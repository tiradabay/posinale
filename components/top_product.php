<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/security.php';
require_auth(['admin']);
include '../connection.php';

$sql = "
SELECT 
    p.ProductID,
    p.Name,
    p.ImagePath,
    SUM(od.Quantity) AS TotalSold,
    SUM(od.Subtotal) AS TotalRevenue
FROM orderdetails od
JOIN products p ON od.ProductID = p.ProductID
JOIN orders o ON o.OrderID = od.OrderID
JOIN payments pay ON pay.OrderID = o.OrderID
WHERE o.Status = 'completed' AND pay.Amount = o.TotalAmount
GROUP BY od.ProductID
ORDER BY TotalSold DESC
";

$result = $conn->query($sql);

/* TOTALS */
$totalSales = 0;
$totalOrders = 0;
$totalProducts = 0;

$getTotals = $conn->query("
SELECT 
    SUM(pay.Amount) AS sales,
    COUNT(o.OrderID) AS orders
FROM orders o
JOIN payments pay ON pay.OrderID = o.OrderID
WHERE o.Status = 'completed' AND pay.Amount = o.TotalAmount
");

if($totals = $getTotals->fetch_assoc()){
    $totalSales = $totals['sales'] ?? 0;
    $totalOrders = $totals['orders'] ?? 0;
}

$getProducts = $conn->query("
SELECT SUM(od.Quantity) AS totalProducts
FROM orderdetails od
JOIN orders o ON o.OrderID = od.OrderID
JOIN payments pay ON pay.OrderID = o.OrderID
WHERE o.Status = 'completed' AND pay.Amount = o.TotalAmount
");

if($prod = $getProducts->fetch_assoc()){
    $totalProducts = $prod['totalProducts'] ?? 0;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Analytics Dashboard</title>

<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap");

:root {
  --espresso: #1a0f00;
  --medium-roast: #5c3317;
  --caramel: #c97d30;
  --cream: #fdf6ee;
  --milk: #fff9f3;
  --radius: 10px;
}

/* RESET */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: "Inter", sans-serif;
  background: var(--cream);
  color: var(--espresso);
}

/* NAVBAR */
.topbar {
  background: var(--milk);
  padding: 0 24px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(201,125,48,0.2);
  position: sticky;
  top: 0;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.brand-icon img {
  width: 45px;
  height: 45px;
  object-fit: contain;
}

.brand-text h1 {
  font-size: 16px;
  font-weight: 700;
}

.brand-text span {
  font-size: 11px;
  color: var(--medium-roast);
}

/* DASHBOARD */
.dashboard {
  padding: 30px;
}

/* TITLE */
.page-title {
  margin-bottom: 20px;
}

.page-title h2 {
  font-size: 28px;
}

/* STATS */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 25px;
}

/* CARD (NO WHITE — FIXED) */
.stat-card {
  background: var(--milk);
  border: 1px solid rgba(201,125,48,0.15);
  border-radius: var(--radius);
  padding: 20px;
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-top h3 {
  font-size: 14px;
  color: var(--medium-roast);
}

.stat-card h2 {
  margin: 18px 0;
  font-size: 28px;
}

/* ICONS */
.icon {
  width: 45px;
  height: 45px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.blue { background: #e5e8ff; }
.pink { background: #ffe5ef; }
.green { background: #e3fff1; }
.orange { background: #fff0dd; }

/* ANALYTICS */
.analytics-card {
  background: var(--milk);
  border: 1px solid rgba(201,125,48,0.15);
  border-radius: 12px;
  padding: 25px;
}

.analytics-header h2 {
  font-size: 20px;
  margin-bottom: 20px;
}

/* PRODUCTS GRID */
.container {
  display: grid;
  grid-template-columns: repeat(auto-fill,minmax(220px,1fr));
  gap: 15px;
}

/* PRODUCT CARD (NO WHITE — FIXED) */
.card {
  background: var(--cream);
  border: 1px solid rgba(201,125,48,0.12);
  border-radius: 12px;
  padding: 12px;
  text-align: center;
}

.card img {
  width: 100%;
  height: 150px;
  object-fit: cover;
  border-radius: 10px;
}

.title {
  margin-top: 10px;
  font-weight: 600;
}

.sold {
  font-size: 13px;
  color: #777;
}

.revenue {
  margin-top: 5px;
  color: var(--caramel);
  font-weight: 600;
}

/* TOP SELLER */
.top {
  border: 2px solid var(--caramel);
}

.badge {
  position: absolute;
  background: var(--caramel);
  color: white;
  font-size: 10px;
  padding: 4px 8px;
  border-radius: 6px;
}
.back-btn {
  background: rgba(201, 125, 48, 0.1);
  border: 1px solid rgba(201, 125, 48, 0.3);
  color: var(--espresso);
  padding: 8px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-family: "Inter", sans-serif;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: 0.2s ease;
  text-decoration: none; /* IMPORTANT para hindi blue link */
}

.back-btn:hover {
  background: black;
  color: var(--cream);
}

/* RESPONSIVE */
@media(max-width:900px){
  .stats-grid {
    grid-template-columns: repeat(2,1fr);
  }
}

@media(max-width:600px){
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
<?php render_session_history_guard(); ?>
</head>

<body>

<!-- NAVBAR -->
<header class="topbar">

  <div class="brand">
    <div class="brand-icon">
      <img src="../assets/brandLogo.svg">
    </div>
    <div class="brand-text">
      <h1>Brew Logic Squad</h1>
      <span>POS System</span>
    </div>
  </div>
<a href="../admin_dash.php" class="back-btn">
  ← Back
</a>
</header>

<div class="dashboard">

  <div class="page-title">
    <h2>Analytics Dashboard</h2>
  </div>

  <!-- STATS -->
  <div class="stats-grid">

    <div class="stat-card">
      <div class="card-top">
        <h3>Total Sales</h3>
        <div class="icon blue">💰</div>
      </div>
      <h2>₱<?= number_format($totalSales,2) ?></h2>
    </div>

    <div class="stat-card">
      <div class="card-top">
        <h3>Orders</h3>
        <div class="icon pink">🛒</div>
      </div>
      <h2><?= $totalOrders ?></h2>
    </div>

    <div class="stat-card">
      <div class="card-top">
        <h3>Products Sold</h3>
        <div class="icon green">📦</div>
      </div>
      <h2><?= $totalProducts ?></h2>
    </div>

    <div class="stat-card">
      <div class="card-top">
        <h3>Revenue</h3>
        <div class="icon orange">📈</div>
      </div>
      <h2>₱<?= number_format($totalSales,2) ?></h2>
    </div>

  </div>

  <!-- PRODUCTS -->
  <div class="analytics-card">
    <div class="analytics-header">
      <h2>🔥 Top Selling Products</h2>
    </div>

    <div class="container">

    <?php $rank=0; while($row=$result->fetch_assoc()): $rank++; ?>

      <div class="card <?= ($rank==1)?'top':'' ?>">

        <img src="../<?= security_escape(security_image_path($row['ImagePath'])) ?>" onerror="this.src='../assets/no-image.png'">

        <div class="title"><?= security_escape($row['Name']) ?></div>
        <div class="sold">Sold: <?= (int)$row['TotalSold'] ?></div>
        <div class="revenue">Before tax: ₱<?= number_format($row['TotalRevenue'],2) ?></div>

      </div>

    <?php endwhile; ?>

    </div>
  </div>

</div>

</body>
</html>
