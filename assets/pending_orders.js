/* Phase 4B: dormant unless server-side migration AND deployment flag are enabled. */
(() => {
  let attempt = null, busy = false, key = null, enabled = false, initializing = true, reading = false;
  window.pendingOrderActionLocked = () => initializing || busy || reading || !!attempt;
  const api = 'components/pending_api.php';
  const escape = value => inventoryEscape(value ?? '');
  const notify = error => showToast(error.message || String(error), 'error');
  function buttons() {
    document.querySelectorAll('[data-pending-action]').forEach(b => {
      b.disabled = busy || reading || (!!attempt && b.dataset.pendingAction !== 'retry');
      b.textContent = busy && b.dataset.pendingAction === 'save' && attempt?.body.operation === 'create_pending'
        ? 'Saving…' : busy && b.dataset.pendingAction === 'pay' ? 'Confirming…'
        : reading && b.dataset.pendingAction === 'list' ? 'Loading…' : b.dataset.pendingLabel;
      if (b.dataset.pendingAction === 'retry') b.hidden = !attempt;
    });
    const modalAction = document.getElementById('modal-action-btn');
    if (modalAction) modalAction.disabled = busy;
  }
  async function read(query) {
    const response = await fetch(api + '?' + query, {cache:'no-store'});
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.error || 'Pending order unavailable.');
    return data;
  }
  async function send(body) {
    if (busy || !enabled) return;
    await checkoutIdentity();
    if (busy) return;
    if (key !== checkoutStorageKey.replace('gabolCheckout:','gabolPending:')) throw new Error('Account changed. Reload before retrying.');
    if (checkoutAttempt && attempt?.body.operation === 'pay_pending' &&
        checkoutAttempt.body.requestID === attempt.body.requestID && checkoutAttempt.result) {
      sessionStorage.removeItem(key); attempt = null; buttons();
      await showCheckoutReceipt(); return;
    }
    if (checkoutBusy || checkoutAttempt) throw new Error('Finish the existing checkout first.');
    if (!attempt) {
      const next = {body:{...body,requestID:checkoutUUID()}};
      sessionStorage.setItem(key,JSON.stringify(next)); // Never send without durable retry identity.
      attempt = next;
    }
    busy = true; buttons();
    try {
      const response = await fetch(api,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':securityCsrfToken()},body:JSON.stringify(attempt.body)});
      const result = await response.json();
      if (!response.ok || !result.success) {
        if (response.status >= 400 && response.status < 500 && ![401,403].includes(response.status) && !result.retryable) {
          sessionStorage.removeItem(key); attempt = null;
        }
        throw new Error(result.error || 'Result uncertain. Retry the same Pending action.');
      }
      const operation = attempt.body.operation;
      if (operation === 'pay_pending') {
        // Store receipt recovery in the existing Phase 3 workflow BEFORE dropping the action.
        checkoutAttempt = {body:{requestID:attempt.body.requestID},result};
        try { persistCheckout(); }
        catch (error) { checkoutAttempt = null; throw error; }
      }
      sessionStorage.removeItem(key); attempt = null;
      if (operation === 'create_pending') {
        cart = []; attachedCustomer = null; renderCart(); renderCustomerStrip();
      }
      if (operation === 'pay_pending') {
        await showCheckoutReceipt(); checkoutButtons(false);
      } else {
        const message = operation === 'cancel_pending' ? 'Pending order cancelled. No stock was deducted.'
          : operation === 'replace_pending' ? 'Replacement Pending order saved. No payment or stock deduction.'
          : 'Pending order saved. No payment or inventory movement.';
        showToast(message, 'success');
        await list();
      }
    } finally { busy = false; buttons(); }
    // Refresh availability after committed operations without replacing the verified receipt.
    loadProducts();
    if (document.getElementById('nav-inventory')) loadInventory();
  }
  // Presentation only: display saved values; never recompute a quote or payment.
  function money(value) {
    const text = String(value ?? '');
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(text);
    return match ? '₱' + match[1].replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + (match[2] || '').padEnd(2, '0') : escape(text);
  }
  function timestamp(value) {
    const text = String(value ?? '');
    // Only offset-bearing server timestamps are formatted; ambiguous input stays visible.
    const date = /(?:Z|[+-]\d{2}:\d{2})$/.test(text) ? new Date(text) : null;
    if (!date || !Number.isFinite(date.getTime())) return escape(text || 'Time unavailable');
    return escape(new Intl.DateTimeFormat('en-PH', {timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'}).format(date));
  }
  function line(order) {
    const status = String(order.status ?? 'pending');
    const badge = ['pending','completed','cancelled'].includes(status) ? status : 'pending';
    return '<header class="pending-order-header"><div><h4>Order #' + Number(order.orderID) +
      '</h4><p class="pending-time">' + timestamp(order.createdAt) + '</p></div><div class="pending-statuses">' +
      '<span class="ui-badge ui-badge-' + (badge === 'completed' ? 'paid' : badge) + '">' + escape(status.charAt(0).toUpperCase() + status.slice(1)) +
      '</span><span class="ui-badge ui-badge-unpaid">Unpaid</span></div></header>' +
      '<div class="pending-items" role="table" aria-label="Saved order items"><div class="pending-item-head" role="row">' +
      '<span role="columnheader">Product / size</span><span role="columnheader">Qty</span><span role="columnheader">Unit price</span><span role="columnheader">Line total</span></div>' +
      order.items.map(i => '<div class="pending-item-row" role="row"><div class="pending-item-name" role="cell"><strong>' + escape(i.name) +
        '</strong><span>' + escape(i.size || 'Standard') + '</span></div><div class="ui-money" role="cell"><span class="pending-mobile-label" aria-hidden="true">Qty</span>' + Number(i.quantity) +
        '</div><div class="ui-money" role="cell"><span class="pending-mobile-label" aria-hidden="true">Unit price</span>' + money(i.unitPrice) +
        '</div><div class="ui-money" role="cell"><span class="pending-mobile-label" aria-hidden="true">Line total</span>' + money(i.subtotal) + '</div></div>').join('') + '</div>' +
      '<dl class="ui-summary pending-summary" aria-label="Saved order totals"><div><dt>Subtotal</dt><dd>' + money(order.subtotal) +
      '</dd></div><div><dt>Tax</dt><dd>' + money(order.tax) + '</dd></div><div class="ui-total"><dt>Total</dt><dd>' + money(order.total) + '</dd></div></dl>';
  }
  function actionButton(parent,label,handler,kind = 'manage') {
    const b = document.createElement('button'); b.type = 'button'; b.textContent = label; b.className = 'pending-action-btn ui-button';
    if (kind === 'pay') b.className += ' ui-button-primary';
    if (label === 'Cancel Pending') b.className += ' ui-button-danger';
    b.dataset.pendingAction = kind; b.dataset.pendingLabel = label;
    b.addEventListener('click',() => { if (b.disabled) return; return Promise.resolve().then(handler).catch(notify); });
    parent.appendChild(b);
  }
  async function detail(order) {
    showModal('Pending Order','<div class="ui-scope pending-view pending-management"><section class="ui-card pending-order">'+line(order) + '</section><section class="pending-management-fields" aria-label="Manage Pending order"><p class="ui-note">Unpaid order. Inventory is rechecked when Cash payment is confirmed.</p>' +
      '<label class="pending-field ui-field">Cash received <input class="modal-input" inputmode="decimal" id="pending-cash" type="number" min="0" step="0.01"></label>' +
      '<label class="pending-field ui-field">Cancellation / replacement reason <input class="modal-input" id="pending-reason" maxlength="255"></label>' +
      '<div id="pending-detail-actions" class="pending-detail-actions"></div></section></div>','Close',closeModal);
    const actions = document.getElementById('pending-detail-actions');
    actionButton(actions,'Pay Cash',async () => {
      if (cart.length) throw new Error('Save or clear your current cart before paying an existing Pending order.');
      await send({operation:'pay_pending',orderID:order.orderID,quoteHash:order.quoteHash,
        cashReceived:document.getElementById('pending-cash').value,paymentMethod:'Cash'});
    },'pay');
    actionButton(actions,'Cancel Pending',() => send({operation:'cancel_pending',orderID:order.orderID,
      reason:document.getElementById('pending-reason').value}));
    actionButton(actions,'Review changed quote',async () => {
      const reason = document.getElementById('pending-reason').value;
      const preview = await read('action=preview&orderID='+Number(order.orderID));
      if (!preview.outdated) { showToast('The saved quote is current.','success'); return; }
      showModal('Explicit replacement confirmation','<div class="ui-scope pending-view pending-replacement"><h4 class="pending-section-title">Original quote</h4><section class="ui-card pending-order">'+line({...order,...preview.original})+'</section>'+
        '<h4 class="pending-section-title">Replacement quote</h4><section class="ui-card pending-order">'+line({...order,...preview.replacement})+'</section>'+
        '<p class="ui-note">Prices or ingredient configuration changed. Confirming cancels the original and saves a new unpaid order. No payment or stock deduction occurs.</p></div>',
        'Confirm replacement',() => send({operation:'replace_pending',orderID:order.orderID,
          quoteHash:preview.replacement.quoteHash,reason}).catch(notify));

    });
  }
  async function list(before) {
    if (reading) return;
    reading = true; buttons();
    try {
    const result = await read('action=list'+(before?'&before='+Number(before):''));
    showModal('Pending Orders','<div class="ui-scope pending-view pending-list-view"><p class="pending-intro">Saved orders awaiting payment. No payment or inventory deduction has been recorded.</p><div id="pending-list" class="pending-list" aria-live="polite"></div></div>','Close',closeModal);
    const container = document.getElementById('pending-list');
    for (const order of result.orders) {
      const section = document.createElement('section'); section.className = 'ui-card pending-order'; section.innerHTML = line(order);
      const footer = document.createElement('footer'); footer.className = 'pending-card-actions';
      actionButton(footer,'View / manage',() => detail(order)); section.appendChild(footer); container.appendChild(section);
    }
    if (!result.orders.length) container.innerHTML = '<div class="ui-empty"><strong>No Pending orders</strong><span>Orders saved as Pending will appear here until paid or cancelled.</span></div>';
    if (result.nextCursor) actionButton(container,'Older Pending orders',() => list(result.nextCursor));
    } finally { reading = false; buttons(); }
  }
  document.addEventListener('DOMContentLoaded',async () => {
    try {
      await checkoutIdentity();
      key = checkoutStorageKey.replace('gabolCheckout:','gabolPending:');
      attempt = JSON.parse(sessionStorage.getItem(key) || 'null');
      if (!(await read('action=capabilities')).enabled) {
        if (attempt) throw new Error('Pending access is disabled. Ask Admin to verify the unfinished action before collecting payment.');
        return;
      }
      enabled = true;
      const target = document.querySelector('.checkout-btn')?.parentElement;
      if (!target) return;
      const actions = document.createElement('div'); actions.className = 'pending-actions';
      const add = (label,kind,handler) => {
        actionButton(actions,label,handler);
        actions.lastChild.dataset.pendingAction = kind;
      };
      add('Save as Pending','save',async () => {
        if (checkoutLocked()) return;
        if (!cart.length) throw new Error('Cart is empty.');
        if (selectedReward) throw new Error('Deselect the reward before saving Pending.');
        await send({operation:'create_pending',items:cart.map(i => ({productID:i.id,quantity:i.qty})),
          customerID:attachedCustomer?.CustomerID ?? null});
      });
      add('Pending Orders','list',() => list());
      add('Retry Pending action','retry',() => send());
      target.appendChild(actions); buttons();
      if (attempt) showToast('An unfinished Pending action was restored. Retry it before starting another payment.','error');
    } catch (error) { notify(error); }
    finally { initializing = false; }
  });
})();
