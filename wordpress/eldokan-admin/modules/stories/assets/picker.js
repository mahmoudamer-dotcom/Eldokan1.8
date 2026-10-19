/* global eldokanStoriesPicker */
(() => {
  'use strict';
  const root = document.getElementById('eldokan-story-picker');
  if (!root) return;
  const sellerInput = document.getElementById('eldokan-story-seller-search');
  const sellerId = document.getElementById('eldokan-story-seller-id');
  const productInput = document.getElementById('eldokan-story-product-search');
  const productId = document.getElementById('eldokan-story-product-id');
  const sellers = document.getElementById('eldokan-story-seller-results');
  const products = document.getElementById('eldokan-story-product-results');
  const preview = document.getElementById('eldokan-story-product-preview');
  const status = document.getElementById('eldokan-story-picker-status');
  const selectedSeller = document.getElementById('eldokan-story-selected-seller');
  const t = eldokanStoriesPicker.strings;
  const controllers = {};
  const timers = {};
  const revisions = { sellers: 0, products: 0 };
  const text = (tag, value) => { const node = document.createElement(tag); node.textContent = value; return node; };
  function cancel(kind) {
    revisions[kind] += 1;
    window.clearTimeout(timers[kind]);
    controllers[kind]?.abort();
  }
  function productCard(item) {
    const card = document.createElement('div');
    card.className = 'eldokan-story-product-card';
    if (item.image) {
      try {
        const url = new URL(item.image, window.location.origin);
        if (['https:', 'http:'].includes(url.protocol)) {
          const image = document.createElement('img'); image.src = url.href; image.alt = ''; image.loading = 'lazy'; card.append(image);
        }
      } catch { /* The details remain available without an invalid image. */ }
    }
    const details = document.createElement('div');
    details.append(text('strong', item.name));
    details.append(text('p', `${t.seller} ${item.seller?.name || t.unavailable}`));
    details.append(text('p', `${t.price} ${item.price || t.see} · ${t.stock} ${item.stock}${item.quantity === null ? '' : ` (${item.quantity})`}`));
    details.append(text('p', `${t.id} ${item.id}${item.sku ? ` · SKU: ${item.sku}` : ''}`));
    card.append(details); return card;
  }
  function clearProduct() {
    cancel('products'); productId.value = ''; productInput.value = ''; products.replaceChildren(); preview.replaceChildren();
  }
  function chooseSeller(item) {
    cancel('sellers'); clearProduct(); sellerId.value = String(item.id); sellerInput.value = item.name;
    selectedSeller.textContent = `${t.selectedSeller} ${item.name}`; sellers.replaceChildren(); productInput.disabled = false;
    productInput.focus(); search('products', '');
  }
  function chooseProduct(item) {
    productId.value = String(item.id); productInput.value = item.name; products.replaceChildren();
    preview.replaceChildren(text('h4', t.selectedProduct), productCard(item)); status.textContent = t.selected;
  }
  function search(kind, query) {
    cancel(kind);
    const revision = revisions[kind];
    if (kind === 'products' && !sellerId.value) return;
    timers[kind] = window.setTimeout(async () => {
      const controller = new AbortController(); controllers[kind] = controller;
      status.textContent = kind === 'sellers' ? t.searchSellers : t.searchProducts;
      const url = new URL(eldokanStoriesPicker.url, window.location.origin);
      url.searchParams.set('action', `eldokan_stories_${kind}`); url.searchParams.set('nonce', eldokanStoriesPicker.nonce);
      url.searchParams.set('q', query); if (kind === 'products') url.searchParams.set('seller_id', sellerId.value);
      try {
        const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
        const payload = await response.json();
        if (revision !== revisions[kind]) return;
        if (!response.ok || !payload.success) throw new Error(payload.data?.message || t.error);
        const list = kind === 'sellers' ? sellers : products; list.replaceChildren();
        const items = payload.data.items || [];
        for (const item of items) {
          const button = document.createElement('button'); button.type = 'button'; button.className = 'eldokan-story-result';
          if (kind === 'sellers') { button.append(text('strong', item.name)); button.addEventListener('click', () => chooseSeller(item)); }
          else { button.append(productCard(item)); button.addEventListener('click', () => chooseProduct(item)); }
          list.append(button);
        }
        status.textContent = items.length ? (kind === 'sellers' ? t.sellerCount : t.productCount).replace('%d', items.length) : kind === 'sellers' ? t.noSellers : t.noProducts;
      } catch (error) {
        if (error.name !== 'AbortError' && revision === revisions[kind]) status.textContent = error.message;
      }
    }, 250);
  }
  sellerInput.addEventListener('focus', () => search('sellers', sellerInput.value.trim()));
  sellerInput.addEventListener('input', () => {
    sellerId.value = ''; clearProduct(); productInput.disabled = true; selectedSeller.textContent = t.selectSeller;
    search('sellers', sellerInput.value.trim());
  });
  productInput.addEventListener('input', () => { productId.value = ''; preview.replaceChildren(); search('products', productInput.value.trim()); });
  root.addEventListener('keydown', event => { if (event.key === 'Escape') { cancel('sellers'); cancel('products'); sellers.replaceChildren(); products.replaceChildren(); } });
  document.getElementById('post')?.addEventListener('submit', event => {
    if (!sellerId.value || !productId.value) { event.preventDefault(); status.textContent = t.required; (sellerId.value ? productInput : sellerInput).focus(); }
  });
  try { const item = JSON.parse(root.dataset.product || 'null'); if (item) preview.replaceChildren(text('h4', t.selectedProduct), productCard(item)); } catch { /* Allow selecting a replacement. */ }
})();
