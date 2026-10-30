// Security helpers preserve the existing presentation and action functions.
function securityCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }
function securityImagePath(value) {
  return typeof value === 'string' && /^(?:assets|uploads)\/(?:[A-Za-z0-9_-]+\/)*[A-Za-z0-9_. -]+\.(?:png|jpe?g|gif|webp)$/i.test(value) ? inventoryEscape(value) : 'assets/logo.png';
}
document.addEventListener('click', event => {
  const button = event.target instanceof Element ? event.target.closest('[data-security-action]') : null;
  if (!button) return;
  event.preventDefault();
  const data = button.dataset;
  const id = Number(data.id);
  switch (data.securityAction) {
    case 'variant': window.openVariantModal(data.slug); break;
    case 'quantity': updateQty(id, Number(data.delta)); break;
    case 'restock-product': quickRestock(id, data.name); break;
    case 'edit-ingredient': window.editIngredient(id); break;
    case 'restock-ingredient': window.quickRestockRaw(id, data.ingredientName); break;
    case 'view-recipe': window.viewRecipe(id); break;
    case 'edit-recipe': window.openRecipeForm(id); break;
    case 'edit-product': window.editProduct(id); break;
    case 'delete-product': window.deleteProduct(id, data.name); break;
    case 'attach-customer': attachCustomer(id, data.name, data.contact, Number(data.points)); break;
  }
});


document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("adminBtn");
    const dropdown = document.getElementById("adminDropdown");

    if (btn && dropdown) {
        btn.addEventListener("click", (e) => {
            e.stopPropagation();
            dropdown.classList.toggle("show");
        });
    }

    document.addEventListener("click", () => {
        if (dropdown) dropdown.classList.remove("show");
    });

    // Tawagin ang initial loads dito
    if (document.getElementById("nav-inventory") && typeof loadInventory === "function") loadInventory();
    if (typeof loadProducts === "function") loadProducts();
});

// ==========================================
// 2. GLOBAL FUNCTIONS (Accessible by onclick)
// ==========================================

/**
 * Ipinapakita ang ingredients ng isang produkto
 */
//Mabilisang pag-update ng stock para sa Raw Materials

window.quickRestockRaw = function(rawMatID, name) {
    showModal(
        "Restock: " + name,
        `<label style="font-size:13px; display:block; margin-bottom:5px;">Quantity to add:</label>
         <input type="number" id="qty" class="modal-input" value="10" min="0.01" step="0.01" max="99999999.99">`,
        "Add Stock",
        () => {
            const qtyInput = document.getElementById("qty");
            const qty = qtyInput.value;

            if (!/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/.test(qty) || Number(qty) <= 0) {
                showToast("Please enter a valid quantity", "error");
                return;
            }

            post("components/update_stock.php", {
                rawMatID,
                qty
            }).then(res => {
                if (res?.success === true) {
                    closeModal();
                    if (document.getElementById("nav-inventory") && typeof loadInventory === "function") loadInventory();
                    showToast("Stock updated!", "success");
                } else {
                    showToast(res?.error || "Update failed", "error");
                }
            }).catch(err => showToast("Restock result could not be confirmed. Refresh inventory before retrying. " + err.message, "error"));
        }
    );
};


let cart = [];
let paymentMethod = "Cash";
let attachedCustomer = null;

let selectedReward = null;

const rewards = [
  {
    id: 1,
    name: "Free Coffee",
    points: 100,
    type: "free_item"
  },
  {
    id: 2,
    name: "Free Pastry",
    points: 150,
    type: "free_item"
  },
  {
    id: 3,
    name: "10% Discount",
    points: 200,
    type: "discount_percent",
    value: 10
  },
  {
    id: 4,
    name: "Free Upsize",
    points: 50,
    type: "upsize"
  }
];

const POINTS_PER_PESO = 1 / 20;



// Map various size indicators to Small / Medium / Large
function mapSizeLabel(size) {
  if (!size) return "Medium";

  const s = size.toString().trim().toLowerCase();

  if (["s", "small"].includes(s)) return "Small";
  if (["m", "medium", "med"].includes(s)) return "Medium";
  if (["l", "large"].includes(s)) return "Large";

  // fallback safety
  if (s.includes("small")) return "Small";
  if (s.includes("large")) return "Large";
  if (s.includes("med")) return "Medium";

  return "Medium";
}

// ---- NAVIGATION ----
function showPage(page) {
  document
    .querySelectorAll(".page")
    .forEach((p) => p.classList.remove("active"));
  document
    .querySelectorAll(".nav-btn")
    .forEach((b) => b.classList.remove("active"));
  document.getElementById("page-" + page).classList.add("active");
  document.getElementById("nav-" + page).classList.add("active");

  const rightPanel = document.querySelector(".right-panel");
  rightPanel.classList.toggle("hidden", page !== "pos");

  if (page === "pos") loadProducts();
  if (page === "orders") loadOrders();
  if (page === "inventory") loadInventory();
  if (page === "loyalty") loadCustomers();
}

// ---- HELPERS ----
function getJSON(url) {
  return fetch(url).then((res) => {
    if (!res.ok)
      throw new Error("Server error: " + res.status + " " + res.statusText);
    return res.text().then((text) => {
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error("PHP error:\n" + text.substring(0, 300));
      }
    });
  });
}

function post(url, data) {
  return fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": securityCsrfToken() },
    body: JSON.stringify(data),
  }).then((res) =>
    res.text().then((text) => {
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error("PHP error:\n" + text.substring(0, 300));
      }
    }),
  );
}

function showError(elementID, msg) {
  document.getElementById(elementID).innerHTML =
    `<div style="padding:20px;color:#c0392b;background:#fdecea;border-radius:8px;font-size:13px;">
      <strong>❌ Error loading data:</strong><br>
      <pre style="margin-top:8px;white-space:pre-wrap;">${inventoryEscape(msg)}</pre>
      <br><strong>Checklist:</strong><br>
      1. Are you opening this via <code>http://localhost/...</code>? (not file://)<br>
      2. Is XAMPP/WAMP running? (Apache + MySQL both green)<br>
      3. Did you import <code>database.sql</code> in phpMyAdmin?<br>
      4. Is the database name <code>brew_logic_pos</code>?
    </div>`;
}

// ---- PRODUCTS ----
function loadProducts() {
  const search = document.getElementById("search-input").value;
  document.getElementById("products-grid").innerHTML =
    '<div class="loading"><div class="spinner"></div> Loading...</div>';

  getJSON("components/get_products.php?search=" + encodeURIComponent(search))
    .then((products) => {

       console.log("PRODUCTS FROM PHP:", products);
       console.log("PRODUCTS FROM PHP:", products);

      if (!products.length) {
        document.getElementById("products-grid").innerHTML =
          '<div class="loading">No products found</div>';
        return;
      }
    

      const groups = Object.create(null);
      products.forEach((p) => {
  const size = mapSizeLabel(p.Size || p._sizeLabel || "Medium");

  p._sizeLabel = size;

  const base = p.Name; // or p.Name.split(" ")[0] kung may pattern ka

  p._baseName = base;

  const available = parseInt(p.AvailableStock ?? p.QtyStock ?? 0);

  if (!groups[base]) groups[base] = [];
  groups[base].push(p);
});
      function slugify(s) {
        return s
          .replace(/\s+/g, "_")
          .replace(/[^a-zA-Z0-9_]/g, "")
          .toLowerCase();
      }

      // expose variants map for modal selection
      window.PRODUCT_VARIANTS = Object.create(null);

      const html = Object.keys(groups)
        .map((name) => {
          const variants = groups[name];
          const first = variants[0];
          const slug = slugify(name);

          // save variants for modal usage
          window.PRODUCT_VARIANTS[slug] = variants;

          // compute total and availability across variants
      
          let totalAvailable = variants.reduce((sum, v) => {
  const stock = parseInt(v.AvailableStock ?? v.QtyStock ?? 0);
  return sum + (isNaN(stock) ? 0 : stock);
}, 0);     const anyAvailable = totalAvailable > 0;

          // Each variant's warning uses its configured server-side threshold.
          const lowStock = variants.some(v => v.stockStatus === "Low Stock");
          const overlayText = !anyAvailable
            ? "Out of Stock"
            : lowStock
              ? totalAvailable + " left"
              : "";
          const overlayClass = overlayText ? " show" : "";
          const img = first.ImagePath
  ? `<div class="product-img-wrap"><img class="product-img" src="${securityImagePath(first.ImagePath)}" alt="${inventoryEscape(first.Name)}" loading="lazy"><div class="img-overlay${overlayClass}">${overlayText}</div></div>`
  : `<div class="product-img-wrap"><div class="product-img-placeholder">🛒</div><div class="img-overlay${overlayClass}">${overlayText}</div></div>`;

          // render single Add button (modal will let user pick variant)
          // show total available in badge
          const badgeHtml = totalAvailable === 0
              ? '<span class="stock-badge out">Out</span>'
              : `<span class="stock-badge ${lowStock ? 'low' : 'ok'}">${totalAvailable} left</span>`;

          return `
          <div class="product-card" id="prod-${slug}">
            ${img}
            <div class="product-body">
              <div class="product-name">${inventoryEscape(name)}</div>
              <div class="product-sizewrap"> ${inventoryEscape(first._sizeLabel ? first._sizeLabel : "—")}</div>
              <div class="product-price">₱${parseFloat(first.Price).toFixed(2)}</div>
               
              <div class="product-badge">${badgeHtml}</div>
          <button class="add-btn"
  data-security-action="variant" data-slug="${inventoryEscape(slug)}"
  ${totalAvailable <= 0 ? 'disabled style="opacity:.5;cursor:not-allowed"' : ''}>
  ${totalAvailable <= 0 ? 'Out of Stock' : '+ Add'}
</button>
            </div>
          </div>`;
        })
        .join("");
      // open variant modal for product selection
      window.openVariantModal = function (slug) {
  const variants = window.PRODUCT_VARIANTS[slug] || [];
  if (!variants.length) {
    showToast("No variants available", "error");
    return;
  }

  const desiredSizes = ["Small", "Medium", "Large"];
  let firstEnabledSlot = -1;

  // 🔥 CLEAN: only use QtyStock (NO recipe, NO availableQty)
  desiredSizes.forEach((label, si) => {
    const found = variants.find(
      vv => mapSizeLabel(vv.Size || vv._sizeLabel) === label
    );

    if (found) {
      const stock = parseInt(found.AvailableStock ?? found.QtyStock ?? 0);

      if (firstEnabledSlot === -1 && stock > 0) {
        firstEnabledSlot = si;
      }
    }
  });

  const optionsHtml = desiredSizes
    .map((label, si) => {
      const found = variants.find(
        vv => mapSizeLabel(vv.Size || vv._sizeLabel) === label
      );

      if (found) {
        const stock = parseInt(found.AvailableStock ?? found.QtyStock ?? 0);
        const disabled = stock <= 0 ? "disabled" : "";

        const checked =
          (firstEnabledSlot === -1
            ? label === "Medium"
            : si === firstEnabledSlot)
            ? "checked"
            : "";

        return `
          <label style="display:flex;align-items:center;gap:10px;margin-bottom:8px;opacity:${stock <= 0 ? ".5" : "1"}">
            <input type="radio" name="variant-select-${slug}" value="${found.ProductID}" ${checked} ${disabled}>
            
            <div style="flex:1">
              <div style="font-weight:700">
                ${label}
                <span style="color:var(--caramel);font-weight:700;margin-left:8px">
                  ₱${parseFloat(found.Price).toFixed(2)}
                </span>
              </div>

              <div style="font-size:12px;color:#666">
                ${stock <= 0 ? "Out of Stock" : stock === 1 ? "1 serving" : stock + " servings"}
              </div>
            </div>
          </label>`;
      }

      return `
        <label style="display:flex;align-items:center;gap:10px;margin-bottom:8px;opacity:.5">
          <input type="radio" disabled>
          <div style="flex:1">
            <div style="font-weight:700">${label} <span style="color:#999;margin-left:8px">N/A</span></div>
            <div style="font-size:12px;color:#666">Not available</div>
          </div>
        </label>`;
    })
    .join("");

  showModal(
    "Choose Size",
    `<div style="max-height:320px;overflow:auto;padding-right:6px">${optionsHtml}</div>`,
    "Add to Cart",
    function () {
      const radios = document.getElementsByName("variant-select-" + slug);

      let chosen = null;
      for (let r of radios) {
        if (r.checked) {
          chosen = parseInt(r.value);
          break;
        }
      }

      if (chosen === null) {
        showToast("Please select a size", "error");
        return;
      }

      const v = variants.find(x => parseInt(x.ProductID) === chosen);
    const stock = parseInt(v.AvailableStock ?? v.QtyStock ?? 0);

      if (stock <= 0) {
        showToast("Out of stock", "error");
        return;
      }

      const displayName =
        v._baseName + (v._sizeLabel ? " • " + mapSizeLabel(v._sizeLabel) : "");

      addToCart(
        parseInt(v.ProductID),
        displayName,
        parseFloat(v.Price),
        stock
      );

      closeModal();
    }
  );
};
      document.getElementById("products-grid").innerHTML = html;

      // Inline size buttons removed — size selection happens in the modal only.
    })
    .catch((err) => showError("products-grid", err.message));
}

// ---- CART ----
function addToCart(id, name, price, stock) {
  if (checkoutLocked()) return;
  const existing = cart.find(i => i.id === id);

  if (existing) {
    if (existing.qty + 1 > stock) {
      showToast("Only " + stock + " in stock!", "error");
      return;
    }
    existing.qty++;
  } else {
    if (stock <= 0) {
      showToast("Out of stock", "error");
      return;
    }
    cart.push({ id, name, price: parseFloat(price), qty: 1, stock });
  }

  renderCart();
  showToast(name + " added!", "success");
}
function updateQty(id, delta) {
  if (checkoutLocked()) return;
  const item = cart.find((i) => i.id === id);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) cart.splice(cart.indexOf(item), 1);
  renderCart();
}

function clearCart() {
  if (checkoutLocked()) return;
  cart = [];
  renderCart();
}

function renderCart() {
  document.getElementById("cart-count").textContent = cart.reduce(
    (sum, i) => sum + i.qty,
    0,
  );

  if (!cart.length) {
    document.getElementById("cart-items").innerHTML = `
      <div class="empty-cart">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:52px;height:52px;opacity:.35">
          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        <p>Your cart is empty</p>
        <span>Add items to get started</span>
      </div>`;
    updateTotals(0);
    return;
  }

  document.getElementById("cart-items").innerHTML = cart
    .map(
      (item) => `
    <div class="cart-item">
      <div>
        <div class="cart-item-name">${inventoryEscape(item.name)}</div>
        <div class="cart-item-price">₱${(item.price * item.qty).toFixed(2)}</div>
      </div>
      <div class="qty-controls">
        <button class="qty-btn" data-security-action="quantity" data-id="${Number(item.id)}" data-delta="-1">−</button>
        <span class="qty-num">${item.qty}</span>
        <button class="qty-btn" data-security-action="quantity" data-id="${Number(item.id)}" data-delta="1">+</button>
      </div>
    </div>`,
    )
    .join("");

  updateTotals(cart.reduce((sum, i) => sum + i.price * i.qty, 0));
}

function updateTotals(subtotal) {
  subtotal = Math.round(subtotal * 100) / 100;
  const tax = Math.round(Math.round(subtotal * 100) * 12 / 100) / 100;
  const total = subtotal + tax;
  const cash = parseFloat(document.getElementById("cash-input")?.value) || 0;
const change = cash - total;
  document.getElementById("subtotal").textContent = "₱" + subtotal.toFixed(2);
  document.getElementById("tax").textContent = "₱" + tax.toFixed(2);
  document.getElementById("total").textContent = "₱" + total.toFixed(2);

  // Update points preview
  const preview = document.getElementById("points-preview");
  if (attachedCustomer && subtotal > 0) {
    const pts = Math.floor(subtotal * POINTS_PER_PESO);
    document.getElementById("points-preview-text").textContent =
      `Will earn ${pts} pt${pts !== 1 ? "s" : ""} for ${attachedCustomer.Name.split(" ")[0]}`;
    preview.style.display = "flex";
  } else {
    preview.style.display = "none";
  }
}

// ---- PAYMENT ----
function selectPayment(method) {
  if (checkoutLocked()) return;
  paymentMethod = method;
  document
    .querySelectorAll(".pay-method-btn")
    .forEach((b) => b.classList.remove("selected"));
  document.querySelector(`[data-method="${method}"]`).classList.add("selected");
}

// ---- CHECKOUT ----
let checkoutBusy = false;
let checkoutAttempt = null;
let checkoutStorageKey = null;
function checkoutLocked() {
  if (window.pendingOrderActionLocked?.() || checkoutBusy || checkoutAttempt) {
    showToast('Finish or retry the current checkout before changing this order.', 'error');
    return true;
  }
  return false;
}
function checkoutButtons(busy) {
  document.querySelectorAll('.checkout-btn').forEach(button => {
    button.disabled = busy;
    button.textContent = busy ? 'Processing…' : checkoutAttempt ? (checkoutAttempt.result ? 'View Receipt' : 'Retry Checkout') : 'Complete Order';
  });
  const cashInput = document.getElementById('cash-input');
  if (cashInput) cashInput.disabled = busy || !!checkoutAttempt;
}
function persistCheckout() {
  sessionStorage.setItem(checkoutStorageKey, JSON.stringify(checkoutAttempt));
}
async function showCheckoutReceipt() {
  // Browser storage is retry state, never the source of an authoritative receipt.
  const response = await fetch('components/get_receipt.php?requestID=' + encodeURIComponent(checkoutAttempt.body.requestID), { cache: 'no-store' });
  const saved = await response.json();
  if (!response.ok || !saved.success) throw new Error(saved.error || 'Payment is saved, but receipt verification is unavailable. Use View Receipt to retry.');
  const res = saved.receipt;
  const timestamp = saved.timestamp;
  const lines = res.items.map(item => `<div class="receipt-row"><span>${inventoryEscape(item.name)}${item.size ? ' • ' + inventoryEscape(item.size) : ''} x${Number(item.quantity)} @ ₱${inventoryEscape(item.unitPrice)}</span><span>₱${inventoryEscape(item.subtotal)}</span></div>`).join('');
  showModal('Order Complete!', `<div class="receipt">
    <div class="receipt-title">☕ Brew Logic Squad</div><div class="receipt-divider"></div>
    <div class="receipt-row"><span>Order #</span><span>${Number(res.orderID)}</span></div>
    <div class="receipt-row"><span>Date</span><span>${inventoryEscape(timestamp.replace('T', ' '))} (Asia/Manila)</span></div>
    <div class="receipt-row"><span>Payment</span><span>${inventoryEscape(res.paymentMethod)}</span></div>
    ${res.customerName ? `<div class="receipt-row"><span>Customer</span><span>${inventoryEscape(res.customerName)}</span></div>` : ''}
    <div class="receipt-divider"></div>${lines}<div class="receipt-divider"></div>
    <div class="receipt-row"><span>Subtotal</span><span>₱${inventoryEscape(res.subtotal)}</span></div>
    <div class="receipt-row"><span>Tax</span><span>₱${inventoryEscape(res.tax)}</span></div>
    <div class="receipt-row" style="font-weight:700"><span>TOTAL</span><span>₱${inventoryEscape(res.total)}</span></div>
    <div class="receipt-row"><span>Cash</span><span>₱${inventoryEscape(res.cashReceived)}</span></div>
    <div class="receipt-row"><span>Change</span><span>₱${inventoryEscape(res.change)}</span></div>
    ${res.pointsEarned ? `<div class="receipt-row" style="color:#c97d30"><span>⭐ Points Earned</span><span>+${Number(res.pointsEarned)} pts</span></div>` : ''}
  </div>`, 'New Order', () => {
    sessionStorage.removeItem(checkoutStorageKey);
    checkoutAttempt = null;
    checkoutButtons(false);
    const cashInput = document.getElementById('cash-input');
    if (cashInput) cashInput.value = '';
    closeModal();
  });
}
async function checkoutIdentity() {
  const response = await fetch('login/check_session.php', { cache: 'no-store' });
  const session = await response.json();
  if (!response.ok || !session.loggedIn) throw new Error('Please sign in again before retrying checkout.');
  const key = 'gabolCheckout:' + session.user_id;
  if (checkoutStorageKey && checkoutStorageKey !== key) {
    checkoutAttempt = null;
    cart = [];
    attachedCustomer = null;
  }
  checkoutStorageKey = key;
  if (!checkoutAttempt) {
    const saved = sessionStorage.getItem(checkoutStorageKey);
    if (saved) checkoutAttempt = JSON.parse(saved);
  }
}
function checkoutUUID() {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  // getRandomValues also supports the POS on an HTTP LAN origin.
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  const hex = [...bytes].map(v => v.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
}
async function checkout() {
  if (checkoutBusy || window.pendingOrderActionLocked?.()) return;
  checkoutBusy = true;
  checkoutButtons(true);
  try {
    await checkoutIdentity();
    if (checkoutAttempt?.result) { await showCheckoutReceipt(); return; }
    if (!checkoutAttempt) {
      if (!cart.length) throw new Error('Cart is empty');
      if (paymentMethod !== 'Cash') throw new Error('Only confirmed Cash payments are currently available.');
      if (selectedReward) throw new Error('Reward fulfillment is pending Phase 7. Select the same reward again to deselect it; no points have been redeemed.');
      const cash = document.getElementById('cash-input')?.value || '';
      if (!/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/.test(cash)) throw new Error('Enter a valid cash amount with up to two decimal places.');
      const requestID = checkoutUUID();
      checkoutAttempt = { body: { requestID, items: cart.map(i => ({ productID: i.id, quantity: i.qty })), cashReceived: cash, paymentMethod: 'Cash', customerID: attachedCustomer?.CustomerID ?? null, rewardID: null }, cart: cart.map(i => ({ ...i })), customer: attachedCustomer ? { ...attachedCustomer } : null };
      // Persist BEFORE sending. If storage is unavailable, do not send payment.
      try { persistCheckout(); } catch (error) { checkoutAttempt = null; throw new Error('Browser storage is unavailable. Enable it before checkout.'); }
    }
    const response = await fetch('components/place_order.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': securityCsrfToken() }, body: JSON.stringify(checkoutAttempt.body)
    });
    const res = await response.json();
    if (!res.success) {
      if (response.status >= 400 && response.status < 500 && ![401,403].includes(response.status) && !res.retryable) {
        sessionStorage.removeItem(checkoutStorageKey);
        checkoutAttempt = null;
      }
      throw new Error(res.error || 'Checkout was not confirmed. Retry the same attempt.');
    }
    checkoutAttempt.result = res;
    persistCheckout();
    if (attachedCustomer && res.remainingPoints !== null) {
      attachedCustomer.LoyaltyPoints = res.remainingPoints;
      renderCustomerStrip();
    }
    cart = [];
    renderCart();
    await showCheckoutReceipt();
    loadProducts();
  } catch (error) {
    showToast(error.message + (checkoutAttempt && !checkoutAttempt.result ? ' Use Retry Checkout to check this same payment; do not start another order.' : ''), 'error');
  } finally {
    checkoutBusy = false;
    checkoutButtons(false);
  }
}
document.addEventListener('DOMContentLoaded', async () => {
  try {
    await checkoutIdentity();
    if (!checkoutAttempt) return;
    if (checkoutAttempt.result) await showCheckoutReceipt();
    else {
      cart = checkoutAttempt.cart;
      attachedCustomer = checkoutAttempt.customer;
      renderCart();
      if (attachedCustomer) renderCustomerStrip();
      document.getElementById('cash-input').value = checkoutAttempt.body.cashReceived;
      showToast('An unfinished checkout was restored. Use Retry Checkout to check its result.', 'error');
    }
    checkoutButtons(false);
  } catch (error) { showToast(error.message, 'error'); }
  finally { checkoutButtons(false); }
});
// ---- INVENTORY ----
function loadInventory() {
  getJSON("components/inventory.php")
    .then((data) => {
      const products = data.products || [];
      const rawMaterials = data.rawMaterials || [];

const outList = products.filter((p) => p.stockStatus === "Out of Stock");
const lowList = products.filter((p) => p.stockStatus === "Low Stock");

document.getElementById("out-count").textContent = outList.length;
document.getElementById("low-count").textContent = lowList.length;

// ======================
// OUT OF STOCK LIST
// ======================
document.getElementById("out-of-stock-list").innerHTML = outList.length
  ? outList
      .map((p) => {

        const name = `${inventoryEscape(p.Name)}${p.Size ? " • " + p.Size : ""}`;
        const action = (p.isRecipe != 1) ? `
          <button class="restock-btn"
            data-security-action="restock-product" data-id="${Number(p.ProductID)}" data-name="${inventoryEscape(name)}">
            Restock
          </button>
        ` : '';

        return `
          <div class="alert-item">
            <span class="alert-item-name">
              ${inventoryEscape(name)} (${Number(p.AvailableStock)} left)
            </span>
            ${action}
          </div>
        `;
      })
      .join("")
  : '<div style="font-size:13px;color:#aaa;padding:4px 0">All stock levels healthy ✓</div>';

// ======================
// LOW STOCK LIST
// ======================
document.getElementById("low-stock-list").innerHTML = lowList.length
  ? lowList
      .map((p) => {

        const name = `${inventoryEscape(p.Name)}${p.Size ? " • " + p.Size : ""}`;
        const action = (p.isRecipe != 1) ? `
          <button class="restock-btn"
            data-security-action="restock-product" data-id="${Number(p.ProductID)}" data-name="${inventoryEscape(name)}">
            Add Stock 
          </button>
        ` : '';

        return `
          <div class="alert-item">
            <span class="alert-item-name">
              ${inventoryEscape(name)} (${Number(p.AvailableStock)} left)
            </span>
            ${action}
          </div>
        `;
      })
      .join("")
  : '<div style="font-size:13px;color:#aaa;padding:4px 0">All stock levels healthy ✓</div>';


      document.getElementById("rawmaterials-tbody").innerHTML = rawMaterials
        .map((m) => {

          const statusClass = m.stockStatus === "Out of Stock" ? "out" : m.stockStatus === "Low Stock" ? "low" : "ok";

          const statusPill = m.stockStatus === "Out of Stock" ? "out-of-stock" : m.stockStatus === "Low Stock" ? "low-stock" : "in-stock";

          const statusText = m.stockStatus === "OK" ? "In Stock" : m.stockStatus;
                return `
          <tr>
            <td>${inventoryEscape(m.RawMaterial)}</td>
            <td><span class="stock-value ${statusClass}">${parseFloat(m.StockAvailable).toFixed(2)}</span></td>
            <td>${inventoryEscape(m.Unit)}</td>
      <td><span class="status-pill ${statusPill}"> ${statusText}</span></td>
            <td>
              <button class="restock-btn" data-security-action="edit-ingredient" data-id="${Number(m.RawMatID)}">Edit</button>
              <button class="restock-btn" data-ingredient-name="${inventoryEscape(m.RawMaterial)}" data-security-action="restock-ingredient" data-id="${Number(m.RawMatID)}">Add</button>
            </td>
          </tr>`;
        })
        .join("");

      document.getElementById("inventory-tbody").innerHTML = products
        .map((p) => {
          const available = parseInt(p.AvailableStock || 0);
          const sizeLabel = p.Size ? p.Size : "—";
          const statusClass =

            p.stockStatus === "Out of Stock" ? "out" : p.stockStatus === "Low Stock" ? "low" : "ok";
          const statusPill =
            p.stockStatus === "Out of Stock"
              ? "out-of-stock"
              : p.stockStatus === "Low Stock"
                ? "low-stock"
                : "in-stock";
          const image = p.ImagePath
           ? `<img class="product-thumb" src="${securityImagePath(p.ImagePath)}" alt="${inventoryEscape(p.Name)}">`
         : `<div class="product-thumb-placeholder">🛒</div>`;
          


         //RECIPEEE
  const recipeActions = (p.isRecipe == 1 || p.isRecipe == "1")
  ? `<button class="action-icon-btn" data-security-action="view-recipe" data-id="${Number(p.ProductID)}">👁</button>`
  : `<button class="action-icon-btn no-recipe-btn" onclick="alert('Wala pay recipe uy')">❌</button>`;
          return `

          <tr>
            <td><div class="td-product">${image}<span>${inventoryEscape(p.Name)}</span></div></td>
            <td>${inventoryEscape(sizeLabel)}</td>
            <td>₱${parseFloat(p.Price).toFixed(2)}</td>
            <td><span class="stock-value ${statusClass}">${available}</span></td>
            <td><span class="status-pill ${statusPill}">${inventoryEscape(p.stockStatus)}</span></td>
            <td>
              <div class="actions-cell">
                ${recipeActions}
                <button class="action-icon-btn" data-security-action="edit-recipe" data-id="${Number(p.ProductID)}">Edit Recipe</button>
                <button class="action-icon-btn" data-security-action="edit-product" data-id="${Number(p.ProductID)}">✏️</button>
                <button class="action-icon-btn danger" data-security-action="delete-product" data-id="${Number(p.ProductID)}" data-name="${inventoryEscape(p.Name)}">🗑️</button>
              </div>
            </td>
          </tr>`;
        })
        .join("");

        
    })
    .catch((err) => {
      document.getElementById("inventory-tbody").innerHTML =
        `<tr><td colspan="6">${inventoryEscape(err.message)}</td></tr>`;
    });

}

window.viewRecipe = async function(productID) {
  try {
    const data = await getJSON(`components/get_recipe.php?ProductID=${productID}`);
    if (!Array.isArray(data) || !data.length) {
      showToast("No recipe configured. Use Edit Recipe to configure one.", "error");
      return;
    }
    const content = data.map(item => `<p>${inventoryEscape(item.IngredientName)}: <strong>${inventoryEscape(item.QtyUsed)} ${inventoryEscape(item.Unit)}</strong> per serving</p>`).join("");
    showModal("Recipe Ingredients", content, "Close", closeModal);
  } catch (error) { showToast(error.message, "error"); }
};
// ---- ADD PRODUCT (Inventory) ----
function openAddProductModal() {
  const html = `
    <label style="font-size:13px;font-weight:600">Name</label>
    <input id="addprod-name" class="modal-input" />

    <label style="font-size:13px;font-weight:600;margin-top:8px">Size (optional)</label>
    <select id="addprod-size" class="modal-input"><option value="Small">Small</option><option value="Medium" selected>Medium</option><option value="Large">Large</option></select>

    <label style="font-size:13px;font-weight:600;margin-top:8px">Price</label>
    <input id="addprod-price" type="number" step="0.01" class="modal-input" value="0" />

    <label style="font-size:13px;font-weight:600;margin-top:8px">Initial Stock</label>
    <input id="addprod-qty" type="number" class="modal-input" value="1" readonly>
    <label style="font-size:13px;font-weight:600;margin-top:8px">Image (optional)</label>
    <input id="addprod-imagefile" type="file" accept="image/*" class="modal-input" />

    <label style="font-size:13px;font-weight:600;margin-top:8px">Low stock threshold</label>
    <input id="addprod-threshold" type="number" class="modal-input" value="5" />
  `;

  showModal("Add New Product", html, "Create", () => {

    const payload = {
      name: document.getElementById("addprod-name").value.trim(),
      category: "Coffee",
      size: mapSizeLabel(document.getElementById("addprod-size").value),
      price: parseFloat(document.getElementById("addprod-price").value) || 0,
      qtyStock: parseInt(document.getElementById("addprod-qty").value) || 0,
      lowStockThreshold: parseInt(document.getElementById("addprod-threshold").value, 10),
    };

    const fileInput = document.getElementById("addprod-imagefile");

    
    // WITH IMAGE 
    
    if (fileInput && fileInput.files.length > 0) {

      const fd = new FormData();
      fd.append("name", payload.name);
      fd.append("category", payload.category);
      if (payload.size) fd.append("size", payload.size);
      fd.append("price", payload.price);
      fd.append("qtyStock", payload.qtyStock);
      fd.append("lowStockThreshold", payload.lowStockThreshold);
      fd.append("imageFile", fileInput.files[0]);

      fetch("components/add_product.php", {
        method: "POST",
        headers: {"X-CSRF-Token": securityCsrfToken()},
        body: fd
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {

            closeModal();
            showToast("Product created", "success");

            loadInventory();
            loadProducts();

            // 🔥 OPEN RECIPE FORM
            openRecipeForm(res.ProductID);

          } else {
            showToast(res.error || "unknown error", "error");
          }
        })
        .catch(err => showToast(err.message, "error"));

    }

    // =========================
    // WITHOUT IMAGE (JSON)
    // =========================
    else {

      post("components/add_product.php", payload)
        .then(res => {
          if (res.success) {

            closeModal();
            showToast("Product created", "success");

            loadInventory();
            loadProducts();

            // 🔥 OPEN RECIPE FORM
            openRecipeForm(res.ProductID);

          } else {
            showToast(res.error || "unknown error", "error");
          }
        })
        .catch(err => showToast(err.message, "error"));
    }

  });
}



function changeStock(productID, change) {
  return post("components/update_stock.php", { productID, change }).then(res => {
    if (res?.success !== true) throw new Error(res?.error || "Update failed");
    loadInventory();
    return res;
  }).catch(error => { showToast(error.message, "error"); });
}

function quickRestock(productID, name) {
  showModal(
    "Restock: " + name,
    `<label style="font-size:13px;font-weight:500;display:block;margin-bottom:8px">Quantity to add:</label>
     <input type="number" class="modal-input" id="restock-qty" value="20" min="1">`,
    "Confirm",
    () => {
      const qty = document.getElementById("restock-qty").value;
      if (!/^[1-9][0-9]*$/.test(qty) || Number(qty) > 2147483647) {
        showToast("Enter a positive whole product quantity.", "error");
        return;
      }
        post("components/update_stock.php", { productID, change: qty }).then(
          res => {
            if (res?.success !== true) {
              showToast(res?.error || "Update failed", "error");
              return;
            }
            closeModal();
            loadInventory();
            showToast("Stock updated!", "success");
          },
        ).catch(error => showToast("Restock result could not be confirmed. Refresh inventory before retrying. " + error.message, "error"));
    },
  );

}


// ---- ADD INGREDIENT (Raw Material) ----
function inventoryEscape(value) {
  return String(value ?? "").replace(/[&<>"']/g, char => ({"&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;"}[char]));
}

function inventoryFormError(message) {
  const element = document.getElementById("inventory-form-error");
  if (element) element.textContent = message;
  showToast(message, "error");
}

function openAddIngredientModal() { openIngredientForm(); }

window.editIngredient = async function(id) {
  try {
    const response = await getJSON(`components/get_rawmaterial.php?RawMatID=${id}`);
    if (!response.success) throw new Error(response.error || "Unable to load ingredient");
    openIngredientForm(response.ingredient);
  } catch (error) { showToast(error.message, "error"); }
};

function openIngredientForm(ingredient = null) {
  const units = ["mg", "g", "kg", "ml", "l", "pcs"];
  const currentUnit = ingredient?.Unit || "g";
  const unitOptions = units.map(unit => `<option value="${unit}" ${unit === currentUnit ? "selected" : ""}>${unit}</option>`).join("");
  const unsupportedUnit = !units.includes(currentUnit) ? `<option selected value="">Choose a supported unit (current: ${inventoryEscape(currentUnit)})</option>` : "";
  const html = `
    <label for="ingredient-name">Ingredient name</label>
    <input id="ingredient-name" class="modal-input" required maxlength="100" value="${inventoryEscape(ingredient?.RawMaterial || "")}">
    <label for="ingredient-unit">Unit</label>
    <select id="ingredient-unit" class="modal-input" required>${unsupportedUnit}${unitOptions}</select>
    <p>Quantities use this unit. Unit changes are blocked while stock or recipe references exist.</p>
    <label for="ingredient-stock">${ingredient ? "Current stock" : "Initial stock"}</label>
    <input id="ingredient-stock" class="modal-input" type="number" required min="0" max="99999999.99" step="0.01" value="${inventoryEscape(ingredient?.StockAvailable ?? "0")}">
    <label for="ingredient-reorder">Reorder threshold</label>
    <input id="ingredient-reorder" class="modal-input" type="number" required min="0" max="99999999.99" step="0.01" value="${inventoryEscape(ingredient?.ReorderingPt ?? "0")}">
    <p id="inventory-form-error" role="alert" style="color:#a32323"></p>`;
  showModal(ingredient ? "Edit Ingredient" : "Add Ingredient", html, ingredient ? "Save Ingredient" : "Create Ingredient", async () => {
    const fields = ["ingredient-name", "ingredient-unit", "ingredient-stock", "ingredient-reorder"].map(id => document.getElementById(id));
    if (!fields.every(field => field.reportValidity())) return;
    const payload = {name: fields[0].value.trim(), unit: fields[1].value, stock: fields[2].value, reorder: fields[3].value};
    if (ingredient) Object.assign(payload, {RawMatID: ingredient.RawMatID, version: ingredient.version});
    const action = document.getElementById("modal-action-btn");
    action.disabled = true;
    try {
      const result = await post(ingredient ? "components/update_ingredient.php" : "components/add_ingredient.php", payload);
      if (!result.success) throw new Error(result.error || "Unable to save ingredient");
      closeModal();
      showToast(ingredient ? "Ingredient updated" : "Ingredient added", "success");
      loadInventory();
      loadProducts();
    } catch (error) { inventoryFormError(error.message); }
    finally { action.disabled = false; }
  });
}

// ---- ATTACH CUSTOMER ----
function openAttachCustomer() {
  document.getElementById("attach-search-input").value = "";
  document.getElementById("attach-results").innerHTML =
    '<div class="attach-empty">Start typing to find a customer</div>';
  document.getElementById("detach-btn").style.display = attachedCustomer
    ? ""
    : "none";
  document.getElementById("attach-modal-overlay").classList.add("open");
  setTimeout(() => document.getElementById("attach-search-input").focus(), 100);
}

function closeAttachModal() {
  document.getElementById("attach-modal-overlay").classList.remove("open");
}

function searchAttachCustomers() {
  const q = document.getElementById("attach-search-input").value.trim();
  const resultsEl = document.getElementById("attach-results");

  if (q.length < 1) {
    resultsEl.innerHTML =
      '<div class="attach-empty">Start typing to find a customer</div>';
    return;
  }

  resultsEl.innerHTML =
    '<div class="attach-empty"><div class="spinner" style="margin:0 auto"></div></div>';

  getJSON("components/get_customers.php?search=" + encodeURIComponent(q))
    .then((data) => {
      const customers = data.customers || [];
      if (!customers.length) {
        resultsEl.innerHTML =
          '<div class="attach-empty">No customers found</div>';
        return;
      }
      resultsEl.innerHTML = customers
        .map((c) => {
          const isAttached =
            attachedCustomer && attachedCustomer.CustomerID == c.CustomerID;
          return `
          <div class="attach-result-item ${isAttached ? "active" : ""}"
               data-security-action="attach-customer" data-id="${Number(c.CustomerID)}" data-name="${inventoryEscape(c.Name)}" data-contact="${inventoryEscape(c.Contact || "")}" data-points="${Number(c.LoyaltyPoints)}">
            <div class="attach-result-avatar">${inventoryEscape(c.Name.charAt(0).toUpperCase())}</div>
            <div class="attach-result-info">
              <div class="attach-result-name">${inventoryEscape(c.Name)}</div>
              <div class="attach-result-meta">${inventoryEscape(c.Contact || "No contact")} &nbsp;·&nbsp; ⭐ ${Number(c.LoyaltyPoints)} pts</div>
            </div>
            ${isAttached ? '<div class="attach-check">✓</div>' : ""}
          </div>`;
        })
        .join("");
    })
    .catch(() => {
      resultsEl.innerHTML =
        '<div class="attach-empty" style="color:var(--red-alert)">Error loading customers</div>';
    });
}

function attachCustomer(id, name, contact, points) {
  if (checkoutLocked()) return;
  attachedCustomer = {
    CustomerID: id,
    Name: name,
    Contact: contact,
    LoyaltyPoints: points,
  };
  renderCustomerStrip();
  closeAttachModal();
  showToast(name + " attached!", "success");
  // Refresh points preview
  const subtotal = cart.reduce((sum, i) => sum + i.price * i.qty, 0);
updateTotals(subtotal);
}

function detachCustomer() {
  if (checkoutLocked()) return;
  attachedCustomer = null;
  renderCustomerStrip();
  closeAttachModal();
  const subtotal = cart.reduce((sum, i) => sum + i.price * i.qty, 0);
  updateTotals(subtotal);
  showToast("Customer removed", "success");
}

function renderCustomerStrip() {
  const nameEl = document.getElementById("customer-strip-name");
  const ptsEl = document.getElementById("customer-strip-pts");
  const avatar = document.getElementById("customer-avatar");
  const btn = document.getElementById("attach-btn");
  const strip = document.querySelector(".cart-customer-strip");

  if (attachedCustomer) {
    nameEl.textContent = attachedCustomer.Name;
    ptsEl.textContent = "⭐ " + attachedCustomer.LoyaltyPoints + " pts";
    ptsEl.style.display = "block";
    avatar.textContent = attachedCustomer.Name.charAt(0).toUpperCase();
    avatar.classList.add("has-customer");
    btn.textContent = "Change";
    btn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Change`;
    strip.classList.add("has-customer");
  } else {
    nameEl.textContent = "No customer attached";
    ptsEl.style.display = "none";
    avatar.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;
    avatar.classList.remove("has-customer");
    btn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Attach`;
    strip.classList.remove("has-customer");
  }
}

// ---- LOYALTY PAGE ----
function loadCustomers() {
  const search = document.getElementById("loyalty-search")?.value || "";
  const period = document.getElementById("loy-period")?.value || "all";

  getJSON(
    `components/get_customers.php?search=${encodeURIComponent(search)}&period=${period}`,
  )
    .then((data) => {
      document.getElementById("stat-total").textContent = data.stats.total;
      document.getElementById("stat-points").textContent =
        data.stats.totalPoints;
      document.getElementById("stat-avg").textContent = data.stats.avgPoints;
      document.getElementById("stat-premium").textContent = data.stats.premium;

      const customers = data.customers;
      if (!customers.length) {
        document.getElementById("customers-tbody").innerHTML =
          '<tr><td colspan="4" style="text-align:center;color:#aaa;padding:20px">No customers found</td></tr>';
        return;
      }
      const rows = customers.map(customer => {
        const row = document.createElement("tr");
        for (const value of [customer.CustomerCode, customer.Name, customer.Contact || "—"]) {
          const cell = document.createElement("td");
          cell.textContent = String(value ?? "");
          row.appendChild(cell);
        }
        const pointsCell = document.createElement("td");
        const badge = document.createElement("span");
        badge.className = "points-badge";
        badge.textContent = "⭐ " + customer.LoyaltyPoints;
        pointsCell.appendChild(badge);
        row.appendChild(pointsCell);
        return row;
      });
      document.getElementById("customers-tbody").replaceChildren(...rows);
    })
    .catch((err) => showError("customers-tbody", err.message));
}

function submitAddCustomer() {
  const name = document.getElementById("new-name").value.trim();
  const contact = document.getElementById("new-contact").value.trim();
  const email = document.getElementById("new-email").value.trim();

  if (!name) {
    showToast("Name is required!", "error");
    return;
  }

  post("components/add_customer.php", { name, contact, email })
    .then((data) => {
      if (data.success) {
        document.getElementById("new-name").value = "";
        document.getElementById("new-contact").value = "";
        document.getElementById("new-email").value = "";
        loadCustomers();
        showToast(name + " added!", "success");
      } else {
        showToast(data.error || "Failed to add customer", "error");
      }
    })
    .catch((err) => showToast(err.message, "error"));
}

// ---- MODAL ----
function showModal(title, body, actionText, onAction) {
  document.getElementById("modal-title").textContent = title;
  document.getElementById("modal-body").innerHTML = body;
  document.getElementById("modal-cancel-btn").style.display = "";
  document.getElementById("modal-action-btn").textContent = actionText;
  document.getElementById("modal-action-btn").onclick = onAction;
  document.getElementById("modal-overlay").classList.add("open");
}

function openModal() {
  document.getElementById("modal-overlay").classList.add("open");
}
function closeModal() {
  document.getElementById("modal-overlay").classList.remove("open");
}

// ---- TOAST ----
function showToast(msg, type) {
  const toast = document.getElementById("toast");
  toast.textContent = (type === "success" ? "✓ " : "✕ ") + msg;
  toast.className = "toast " + type + " show";
  setTimeout(() => toast.classList.remove("show"), 2500);
}
function openAddCustomerModal() {
  showModal(
    "Add New Customer",
    `
    <label style="font-size:13px;font-weight:500;margin-bottom:6px;display:block;">Full Name *</label>
    <input type="text" class="modal-input" id="modal-name" placeholder="Enter full name">

    <label style="font-size:13px;font-weight:500;margin-bottom:6px;display:block;">Phone</label>
    <input type="text" class="modal-input" id="modal-contact" placeholder="Enter phone number">

    <label style="font-size:13px;font-weight:500;margin-bottom:6px;display:block;">Email</label>
    <input type="email" class="modal-input" id="modal-email" placeholder="Enter email">
    `,
    "Add Customer",
    function submitAddCustomerFromModal() {

      const name = document.getElementById("modal-name").value.trim();
      const contact = document.getElementById("modal-contact").value.trim();
      const email = document.getElementById("modal-email").value.trim();

      if (!name) {
        showToast("Name is required!", "error");
        return;
      }

      post("components/add_customer.php", {
        name,
        contact,
        email
      })
      .then((data) => {

        if (data.success) {

          closeModal();
          loadCustomers();

          showToast(name + " added!", "success");

        } else {

          showToast(data.error || "Failed to add customer", "error");

        }

      })
      .catch((err) => {

        console.log("REGISTER ERROR:", err);

        showToast(err.message, "error");

      });

    }
  );
}

function submitAddCustomerFromModal() {
  const name = document.getElementById("modal-name").value.trim();
  const contact = document.getElementById("modal-contact").value.trim();
  const email = document.getElementById("modal-email").value.trim();

  if (!name) {
    showToast("Name is required!", "error");
    return;
  }

  post("components/add_customer.php", { name, contact, email })
    .then((data) => {
      if (data.success) {
        closeModal();
        loadCustomers();
        showToast(name + " added!", "success");
      } else {
        showToast(data.error || "Failed to add customer", "error");
      }
    })
    .catch((err) => showToast(err.message, "error"));
}

window.openRecipeForm = async function(productID) {
  try {
    const [materials, recipe] = await Promise.all([
      getJSON("components/get_rawmaterial.php"),
      getJSON(`components/get_recipe.php?ProductID=${productID}&format=edit`)
    ]);
    if (!recipe.success) throw new Error(recipe.error || "Unable to load recipe");
    showModal(`Edit Recipe: ${recipe.product.Name}${recipe.product.Size ? " • " + recipe.product.Size : ""}`,
      `<p>Enter the quantity needed for one serving, in each ingredient's displayed unit. Changes apply to future preparation; saved order records are preserved.</p>
       <div id="recipe-container"></div>
       <button type="button" id="add-recipe-btn">+ Add Ingredient</button>
       <p id="inventory-form-error" role="alert" style="color:#a32323"></p>`,
      "Save Recipe", () => saveRecipe(productID, recipe.version));
    if (recipe.ingredients.length) recipe.ingredients.forEach(item => addRecipeRow(materials, item));
    else addRecipeRow(materials);
    document.getElementById("add-recipe-btn").onclick = () => addRecipeRow(materials);
  } catch (error) { showToast(error.message, "error"); }
};

function addRecipeRow(materials, ingredient = null) {
  const row = document.createElement("div");
  row.className = "recipe-row";
  row.style.cssText = "display:flex;gap:8px;align-items:center;margin-bottom:10px";
  row.innerHTML = `
    <select class="rm-select modal-input" aria-label="Ingredient" required style="flex:2">
      <option value="">Select ingredient</option>
      ${materials.map(material => `<option value="${material.RawMatID}" data-unit="${inventoryEscape(material.Unit)}" ${String(material.RawMatID) === String(ingredient?.RawMatID) ? "selected" : ""}>${inventoryEscape(material.RawMaterial)} (${inventoryEscape(material.Unit)})</option>`).join("")}
    </select>
    <input type="number" class="rm-qty modal-input" aria-label="Quantity per serving" required min="0.01" max="99999999.99" step="0.01" value="${inventoryEscape(ingredient?.QtyUsed ?? "")}" placeholder="Qty per serving" style="flex:1">
    <span class="rm-unit"></span>
    <button type="button" class="remove-recipe-row" aria-label="Remove ingredient">Remove</button>`;
  document.getElementById("recipe-container").appendChild(row);
  const select = row.querySelector(".rm-select");
  const showUnit = () => { row.querySelector(".rm-unit").textContent = select.selectedOptions[0]?.dataset.unit || ""; };
  select.onchange = showUnit;
  showUnit();
  row.querySelector(".remove-recipe-row").onclick = () => row.remove();
}

function redeemReward(id) {
  if (checkoutLocked()) return;
  if (selectedReward?.id === id) { selectedReward = null; showToast('Reward deselected', 'success'); return; }

  if (!attachedCustomer) {
    showToast("Attach customer first", "error");
    return;
  }

  const reward = rewards.find(r => r.id === id);

  if (!reward) return;

  if (attachedCustomer.LoyaltyPoints < reward.points) {
    showToast("Not enough points", "error");
    return;
  }

  selectedReward = reward;

  showToast(
    reward.name + " selected!",
    "success"
  );
}
// SAVE RECIPE
async function saveRecipe(productID, version) {
  const rows = [...document.querySelectorAll(".recipe-row")];
  if (!rows.length) { inventoryFormError("Add at least one ingredient."); return; }
  const seen = new Set();
  const ingredients = [];
  for (const row of rows) {
    const select = row.querySelector(".rm-select");
    const qty = row.querySelector(".rm-qty");
    if (!select.reportValidity() || !qty.reportValidity()) return;
    if (seen.has(select.value)) { inventoryFormError("Each ingredient may appear only once."); return; }
    seen.add(select.value);
    ingredients.push({RawMatID: Number(select.value), QtyUsed: qty.value, Unit: select.selectedOptions[0].dataset.unit});
  }
  const action = document.getElementById("modal-action-btn");
  action.disabled = true;
  try {
    const result = await post("components/save_recipe.php", {ProductID: productID, ingredients, version});
    if (!result.success) throw new Error(result.error || "Unable to save recipe");
    closeModal();
    showToast("Recipe saved", "success");
    loadInventory();
    loadProducts();
  } catch (error) { inventoryFormError(error.message); }
  finally { action.disabled = false; }
}
//EDIT button
window.editProduct = function(id) {

  post("components/get_products.php", {
    productID: id
  }).then(res => {

    if (!res.success) {
      showToast(res.error || "Product not found", "error");
      return;
    }

    const p = res.data;

    showModal(
      "Edit Product",
      `
        <label>Name</label>
        <input id="edit-name" class="modal-input" maxlength="150" value="${inventoryEscape(p.Name)}">

        <label>Price</label>
        <input id="edit-price" type="number" min="0" max="99999999.99" step="0.01" class="modal-input" value="${inventoryEscape(p.Price)}">
      `,
      "Update",
      () => {

        const newName = document.getElementById("edit-name").value.trim();
        const newPrice = document.getElementById("edit-price").value;
        const action = document.getElementById("modal-action-btn");
        if (action.disabled) return;
        action.disabled = true;
        if (!newName || [...newName].length > 150 || !/^(0|[1-9]\d{0,7})(\.\d{1,2})?$/.test(newPrice)) {
          action.disabled = false;
          showToast("Enter a name and a valid nonnegative price with up to two decimal places.", "error");
          return;
        }

        post("components/update_product.php", {
          productID: id,
          name: newName,
          price: newPrice
        }).then(r => {

          if (r.success === true) {
            closeModal();
            showToast("Updated!", "success");
            loadInventory();
            loadProducts();
          } else {
            showToast(r.error || "Update failed", "error");
          }

        }).catch(error => showToast(error.message || "Unable to confirm update. Refresh before retrying.", "error"))
          .finally(() => { action.disabled = false; });

      }
    );

  }).catch(error => showToast(error.message || "Unable to load product", "error"));

};

window.deleteProduct = function(id, name) {

  console.log("Deleting:", id);

  post("components/delete_product.php", {
    productID: Number(id)
  })
  .then(res => {

    console.log("RESPONSE:", res);

    if (res.success) {
      showToast(res.message || "Deleted!", "success");
      loadInventory();
      loadProducts();
    } else {
      showToast(res.error || "Delete failed", "error");
    }

  })
  .catch(err => {
    console.error("DELETE ERROR:", err);
    showToast("Request failed", "error");
  });

}


function loadOrders() {
    fetch("components/get_orders.php")
        .then(res => res.json())
        .then(data => {

            const container = document.getElementById("orders-list");
            if (!container) return;

            container.innerHTML = "";

            data.forEach(order => {

                const items = order.Items
                    ? order.Items.split(",").map(i => i.trim()).join(", ")
                    : "No items";

                container.innerHTML += `
                    <div class="order-card" style="
                        background:#fff;
                        padding:14px;
                        border-radius:12px;
                        margin-bottom:12px;
                        box-shadow:0 2px 8px rgba(0,0,0,0.06);
                    ">

                        <!-- HEADER -->
                        <div style="margin-bottom:10px;">
                            <div style="font-weight:700;font-size:14px;">
                                Order #${Number(order.OrderID)}
                            </div>
                            <div style="font-size:12px;color:#888;">
                                ${inventoryEscape(order.Date)}
                            </div>
                        </div>

                        <!-- BODY (ALL LEFT) -->
                        <div style="display:flex;flex-direction:column;gap:6px;font-size:13px;">

                            <div>Items: ${inventoryEscape(items)}</div>

                            <div>Payment: ${inventoryEscape(order.Status === "pending" ? "Pending (unpaid)" : order.Status === "cancelled" ? "Cancelled" : (order.Method || "Unpaid"))}</div>

                            <div style="
                                border-top:1px dashed #ddd;
                                padding-top:6px;
                                margin-top:4px;
                                font-weight:700;
                                color:#c97d30;
                            ">
                                Total: ₱${parseFloat(order.TotalAmount).toFixed(2)}
                            </div>

                        </div>

                    </div>
                `;
            });
        })
        .catch(err => console.error("LOAD ORDERS ERROR:", err));
}


function updateCart() {
    let total = 0;

    document.querySelectorAll(".cart-item").forEach(item => {
        total += parseFloat(item.dataset.price || 0);
    });

    const subtotalEl = document.getElementById("subtotal");
    const taxEl = document.getElementById("tax");
    const totalEl = document.getElementById("total");

    const tax = total * 0.12;
    const grandTotal = total + tax;

    subtotalEl.innerText = "₱" + total.toFixed(2);
    taxEl.innerText = "₱" + tax.toFixed(2);
    totalEl.innerText = "₱" + grandTotal.toFixed(2);

    // 🔥 RESET CASH INPUT EVERY TIME CART UPDATES
    const cashInput = document.getElementById("cash-input");
    if (cashInput) {
        cashInput.value = "";
    }

    // reset change display too
    const changeEl = document.getElementById("change");
    if (changeEl) {
        changeEl.innerText = "₱0.00";
        changeEl.style.color = "#aaa";
    }
}


function updateChange() {
    const cashInput = document.getElementById("cash-input");
    const totalEl = document.getElementById("total");
    const changeEl = document.getElementById("change");

    if (!cashInput || !totalEl || !changeEl) return;

    const total = parseFloat(totalEl.textContent.replace(/[^\d.]/g, "")) || 0;
    const cash = parseFloat(cashInput.value) || 0;

    const change = cash - total;

    changeEl.textContent = "₱" + change.toFixed(2);

    if (change < 0) {
        changeEl.style.color = "#ff4d4d";
    } else {
        changeEl.style.color = "#4caf50";
    }
}

function canCheckout() {
    const cash = parseFloat(document.getElementById("cash-input").value) || 0;
    const total = parseFloat(document.getElementById("total").textContent.replace(/[^\d.]/g, "")) || 0;

    return cash >= total;
}
