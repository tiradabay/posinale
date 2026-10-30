<?php
require_once __DIR__ . '/components/auth_check.php';
require_once __DIR__ . '/components/security.php';
require_auth(['cashier']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?php echo security_escape(security_csrf_token()); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Brew Logic Squad</title>
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="assets/ui_foundation.css?v=<?php echo filemtime(__DIR__ . '/assets/ui_foundation.css'); ?>">
  <link rel="stylesheet" href="assets/pending_orders.css?v=<?php echo filemtime(__DIR__ . '/assets/pending_orders.css'); ?>">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script defer src="script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>
<?php render_session_history_guard(); ?>
<script defer src="assets/pending_orders.js?v=<?php echo filemtime(__DIR__ . '/assets/pending_orders.js'); ?>"></script>
</head>
<body>

<!-- NAVBAR -->
<header class="topbar">
  <div class="brand">
    <div class="brand-icon">
      <img src="assets/brandLogo.svg" alt="logo">
    </div>
    <div class="brand-text">
      <h1>Brew Logic Squad</h1>
      <span>Point of Sale System</span>
    </div>
  </div>
  <nav class="topbar-nav">
    <button class="nav-btn active" id="nav-pos" onclick="showPage('pos')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>
      Menu
    </button>
    <button class="nav-btn" id="nav-orders" onclick="showPage('orders')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      History
    </button>
    <button class="nav-btn" id="nav-loyalty" onclick="showPage('loyalty')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
      Loyalty
    </button>


<form method="post" action="login/logout.php" onsubmit="return confirm('Are you sure you want to logout?')"><input type="hidden" name="logout_token" value="<?php echo logout_token(); ?>"><button type="submit" class="nav-btn">
  Logout
</button></form>
  </nav>
</header>

<div class="main-layout">

  <!-- LEFT PANEL -->
  <div class="left-panel">

    <!-- POS PAGE -->
    <div class="page active" id="page-pos">
      <div class="search-bar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" class="search-input" id="search-input" placeholder="Search products...">
      </div>
      <div class="products-grid" id="products-grid">
        <div class="loading"><div class="spinner"></div> Loading...</div>
      </div>
    </div>

    <!-- ORDER HISTORY PAGE -->
    <div class="page" id="page-orders">
      <div class="orders-section">
        <h2>Order History</h2>
        <div id="orders-list"></div>
      </div>
    </div>

    <!-- INVENTORY PAGE -->
    <div class="page" id="page-inventory">
      <div class="alerts-row">
        <div class="alert-card out-of-stock">
          <div class="alert-title">⚠ Out of Stock <span class="alert-badge" id="out-count">0</span></div>
          <div id="out-of-stock-list"></div>
        </div>
        <div class="alert-card low-stock">
          <div class="alert-title">⚠ Low Stock <span class="alert-badge" id="low-count">0</span></div>
          <div id="low-stock-list"></div>
        </div>
      </div>
      <div class="inventory-section">
        <h2>Inventory Management</h2>
        <div class="inventory-grid">
          <div class="inventory-panel">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
              <h3 style="margin:0">Raw Materials</h3>
              <button class="restock-btn" style="padding:6px 10px" onclick="openAddIngredientModal()">+ Add Ingredient</button>
            </div>
            <table class="inventory-table">
              <thead>
                <tr><th>Material</th><th>Available</th><th>Unit</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody id="rawmaterials-tbody"></tbody>
            </table>
          </div>

          <div class="inventory-panel">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
              <h3 style="margin:0">Finished Goods</h3>
              <button class="restock-btn" style="padding:6px 10px" onclick="openAddProductModal()">+ Add Product</button>
            </div>
            <table class="inventory-table">
              <thead>
                <tr><th>Drink</th><th>Size</th><th>Price</th><th>Servings</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody id="inventory-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="page" id="page-loyalty">
  <div class="loy-header">
    <h2>
      <div class="loy-header-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="1" y="4" width="22" height="16" rx="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
      </div>
      Customer Loyalty
    </h2>

    <select class="loy-period" id="loy-period" onchange="loadCustomers()">
      <option value="all">All-time</option>
      <option value="month">This Month</option>
      <option value="week">This Week</option>
    </select>
  </div>

  <!-- STATS -->
  <div class="loy-stats">
    <div class="loy-stat">
      <div class="loy-stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
        </svg>
      </div>
      <div>
        <div class="loy-stat-label">Total Customers</div>
        <div class="loy-stat-value" id="stat-total">0</div>
      </div>
    </div>

    <div class="loy-stat">
      <div class="loy-stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
      </div>
      <div>
        <div class="loy-stat-label">Total Points</div>
        <div class="loy-stat-value" id="stat-points">0</div>
      </div>
    </div>

    <div class="loy-stat">
      <div class="loy-stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="20" x2="18" y2="10"/>
          <line x1="12" y1="20" x2="12" y2="4"/>
          <line x1="6" y1="20" x2="6" y2="14"/>
        </svg>
      </div>
      <div>
        <div class="loy-stat-label">Avg Points</div>
        <div class="loy-stat-value" id="stat-avg">0</div>
      </div>
    </div>

    <div class="loy-stat">
      <div class="loy-stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </div>
      <div>
        <div class="loy-stat-label">Premium Members</div>
        <div class="loy-stat-value" id="stat-premium">0</div>
      </div>
    </div>
  </div>

  <!-- REWARDS -->
<div class="loy-rewards">
  <div class="loy-rewards-title">
    🎁 Available Loyalty Rewards
  </div>

  <div class="loy-rewards-grid">

    <div class="loy-reward-card" onclick="redeemReward(1)">
      <div>
        <div class="loy-reward-name">Free Coffee</div>
        <div class="loy-reward-desc">Get free drinks!!</div>
      </div>
      <span class="loy-reward-pts">100pts</span>
    </div>

    <div class="loy-reward-card" onclick="redeemReward(2)">
      <div>
        <div class="loy-reward-name">Free Pastry</div>
        <div class="loy-reward-desc">Any pastry item</div>
      </div>
      <span class="loy-reward-pts">150pts</span>
    </div>

    <div class="loy-reward-card" onclick="redeemReward(3)">
      <div>
        <div class="loy-reward-name">10% Discount</div>
        <div class="loy-reward-desc">On entire order</div>
      </div>
      <span class="loy-reward-pts">200pts</span>
    </div>

    <div class="loy-reward-card" onclick="redeemReward(4)">
      <div>
        <div class="loy-reward-name">Free Upsize</div>
        <div class="loy-reward-desc">Upgrade any drink</div>
      </div>
      <span class="loy-reward-pts">50pts</span>
    </div>

  </div>
</div>

  <!-- CUSTOMER DIRECTORY (FULL WIDTH) -->
  <div class="loy-bottom">
    <div class="loy-panel">

      <!-- HEADER WITH ADD BUTTON -->
      <div class="loy-add-header">
        <div class="loy-panel-title">Customer Directory</div>
        <button class="loy-add-btn" onclick="openAddCustomerModal()">+ Add</button>
      </div>

      <!-- SEARCH -->
      <div class="loy-dir-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="loy-search-icon">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text"
               class="loy-search"
               id="loyalty-search"
               placeholder="Search by customer or phone..."
               oninput="loadCustomers()">
      </div>

      <!-- TABLE -->
      <table class="loy-dir-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Points</th>
          </tr>
        </thead>
        <tbody id="customers-tbody">
          <tr>
            <td colspan="4">
              <div class="loading">
                <div class="spinner"></div>
                Loading...
              </div>
            </td>
          </tr>
        </tbody>
      </table>

    </div>
  </div>
</div>
    <!-- END LOYALTY PAGE -->

  </div>
  <!-- END LEFT PANEL -->

  <!-- CART -->
  <aside class="right-panel">
    <div class="cart-header">
      <h2>Current Order <span class="cart-count" id="cart-count">0</span></h2>
    </div>

    <!-- CUSTOMER ATTACHMENT STRIP -->
    <div class="cart-customer-strip">
      <div class="customer-strip-info" id="customer-strip-info">
        <div class="customer-avatar" id="customer-avatar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div>
          <div class="customer-strip-name" id="customer-strip-name">No customer attached</div>
          <div class="customer-strip-pts" id="customer-strip-pts" style="display:none"></div>
        </div>
      </div>
      <button class="attach-btn" id="attach-btn" onclick="openAttachCustomer()">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add
      </button>
    </div>

    <div class="cart-items" id="cart-items">
      <div class="empty-cart">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:52px;height:52px;opacity:.35">
          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        <p>Cart is empty</p>
        <span>Add Coffee to get started</span>
      </div>
    </div>

    <div class="cart-footer">
      <div class="subtotal-row"><span>Subtotal</span><span id="subtotal">₱0.00</span></div>
      <div class="tax-row" style="color:#aaa;font-size:12px"><span></span><span id="tax">₱0.00</span></div>
      <div class="total-row"><span>Total</span><span id="total">₱0.00</span></div>
      <!-- Points earn preview -->
      <div class="points-preview" id="points-preview" style="display:none">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        <span id="points-preview-text">Will earn 0 pts</span>
      </div>


      <div id="selected-reward-display"
     style="
      margin:10px 0;
      padding:10px;
      border-radius:10px;
      background:#f6f1ea;
      font-size:13px;
      display:none;
">
</div>

<div class="cash-section">
  <label style="font-size:12px;color:#aaa;">Cash Received</label>

  <input
  type="number"
  id="cash-input"
  placeholder="Enter cash amount"
  oninput="updateChange()"
  style="
    width:100%;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
    background:#fff;
    color:#000;
    margin-top:5px;
  "
/>

  <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:13px;">
    <span style="color:#aaa;">Change</span>
    <span id="change">₱0.00</span>
  </div>
</div>
<br>
      <div class="payment-methods">
        <button class="pay-method-btn selected" data-method="Cash"  onclick="selectPayment('Cash')">Cash</button>
         <button class="pay-method-btn" data-method="Card" onclick="selectPayment('Card')" disabled style="opacity:0.3; pointer-events:none;">  Card </button>
        <button class="pay-method-btn" data-method="GCash" onclick="selectPayment('GCash')" disabled style="opacity:0.3; pointer-events:none;">  GCash </button>
        <button class="pay-method-btn" data-method="Maya" onclick="selectPayment('Maya')" disabled style="opacity:0.3; pointer-events:none;">  Maya </button>
      </div>
      <button class="checkout-btn" onclick="checkout()">Complete Order</button>
      <button class="clear-btn" onclick="clearCart()">Clear Cart</button>
    </div>
  </aside>

</div>

<!-- ATTACH CUSTOMER MODAL -->
<div class="modal-overlay" id="attach-modal-overlay" onclick="if(event.target===this)closeAttachModal()">
  <div class="modal">
    <h3>Add Customer</h3>
    <p style="font-size:13px;color:#999;margin:-8px 0 14px">Search alias or phone number.</p>
    <div class="attach-search-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="attach-search-icon">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" class="attach-search-input" id="attach-search-input"
        placeholder="e.g. 2025-0001..." oninput="searchAttachCustomers()">
    </div>
    <div id="attach-results" class="attach-results">
      <div class="attach-empty">Start typing to find a customer</div>
    </div>
    <div class="modal-actions" style="margin-top:12px">
      <button class="modal-btn secondary" onclick="closeAttachModal()">Cancel</button>
      <button class="modal-btn secondary" id="detach-btn"
        style="display:none;color:var(--red-alert);border-color:rgba(192,57,43,.3)"
        onclick="detachCustomer()">Remove Customer</button>
    </div>
  </div>
</div>

<!-- LOYALTY REDEEM MODAL -->
<div id="redeem-modal-overlay" class="modal-overlay">

  <div class="modal">

    <div class="modal-header">
      <h3>Redeem Loyalty Points</h3>
      <button onclick="closeRedeemModal()">✕</button>
    </div>

    <div class="modal-body">

      <!-- CUSTOMER INFO -->
      <div class="redeem-customer">
        <div class="redeem-avatar" id="redeem-avatar">A</div>

        <div class="redeem-info">
          <div class="redeem-name" id="redeem-name">No customer</div>
          <div class="redeem-points">
            Available Points: <strong id="redeem-points">0</strong>
          </div>
        </div>
      </div>

      <!-- SLIDER -->
      <div class="redeem-section">

        <label>Points to use</label>

        <input 
          type="range" 
          id="points-slider" 
          min="0" 
          max="0" 
          value="0"
          oninput="updateRedeemPreview()"
        />

        <div class="redeem-values">
          <span id="points-selected">0 pts</span>
          <span id="discount-preview">₱0.00 discount</span>
        </div>

      </div>

      <!-- INFO -->
      <div class="redeem-note">
        💡 10 points = ₱1 discount
      </div>

    </div>

    <!-- ACTIONS -->
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeRedeemModal()">
        Cancel
      </button>

      <button class="btn-primary" onclick="applyRedeemPoints()">
        Apply Discount
      </button>
    </div>

  </div>
</div>

<!-- GENERAL MODAL -->
<div class="modal-overlay" id="modal-overlay" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <h3 id="modal-title"></h3>
    <div id="modal-body"></div>
    <div class="modal-actions">
      <button class="modal-btn secondary" id="modal-cancel-btn" onclick="closeModal()">Cancel</button>
      <button class="modal-btn primary" id="modal-action-btn">OK</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>


 <!-- Admin Manage-->
</body>
</html>
