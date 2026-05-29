/* ===== ESTADO GLOBAL ===== */
let state = {
  products: [],
  settings: {
    amazonTag: '',
    shopeeAffiliateLink: '',
    mlAffiliateLink: '',
    storeName: 'Minhas Promoções',
    storeDesc: 'As melhores ofertas selecionadas para você!',
    storeSlug: 'promocoes',
    adminPass: ''
  },
  currentPlatform: null,
  currentAffiliateUrl: ''
};

/* ===== PERSISTÊNCIA ===== */
function saveToStorage() {
  localStorage.setItem('divulga_products', JSON.stringify(state.products));
  localStorage.setItem('divulga_settings', JSON.stringify(state.settings));
}

function loadFromStorage() {
  const p = localStorage.getItem('divulga_products');
  const s = localStorage.getItem('divulga_settings');
  if (p) { try { state.products = JSON.parse(p); } catch {} }
  if (s) { try { state.settings = { ...state.settings, ...JSON.parse(s) }; } catch {} }
}

/* ===== NAVEGAÇÃO ===== */
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-link, .mobile-nav a').forEach(l => {
    l.classList.toggle('active', l.dataset.page === id);
  });
  document.getElementById('page-' + id)?.classList.add('active');
  if (id === 'produtos') renderProducts();
  if (id === 'catalogo') renderCatalogPreview();
  if (id === 'config') loadSettingsForm();
}

/* ===== CONVERSOR ===== */
function handleConvert() {
  const url = document.getElementById('link-input').value.trim();
  if (!url) return;

  const { platform, affiliateUrl } = Affiliate.convert(url, state.settings);
  state.currentPlatform = platform;
  state.currentAffiliateUrl = affiliateUrl;

  // Mostra resultado do link
  document.getElementById('detected-platform').innerHTML =
    `<span class="badge ${platform.badge}">${platform.icon} ${platform.name}</span>`;

  document.getElementById('affiliate-link-text').textContent = affiliateUrl;
  document.getElementById('result-affiliate').classList.add('visible');

  // Aviso se não tem tag configurada
  const warn = document.getElementById('no-tag-warning');
  if (platform.key === 'amazon' && !state.settings.amazonTag) {
    warn.style.display = 'block';
    warn.textContent = '⚠️ Tag Amazon não configurada. Vá em Configurações para adicionar.';
  } else if ((platform.key === 'shopee' || platform.key === 'mercadolivre') && affiliateUrl === url) {
    warn.style.display = 'block';
    warn.textContent = '⚠️ Configure seu link de afiliado ' + platform.name + ' em Configurações para ativar a conversão automática.';
  } else {
    warn.style.display = 'none';
  }

  // Mostra formulário de produto
  document.getElementById('product-form-section').classList.add('visible');
  generateContent();
}

/* ===== GERAÇÃO DE CONTEÚDO ===== */
function generateContent() {
  const title = document.getElementById('prod-title').value.trim();
  const price = document.getElementById('prod-price').value.trim();
  const description = document.getElementById('prod-desc').value.trim();
  const platform = state.currentPlatform?.name || 'Loja';
  const affiliateUrl = state.currentAffiliateUrl;

  if (!title && !price) return;

  const priceStr = price ? `💰 Por apenas R$ ${price}` : '';
  const titleLine = title || 'Oferta Imperdível';
  const descLine = description ? `\n\n${description}` : '';

  // WhatsApp
  const whatsapp = `🔥 *${titleLine}*${descLine}\n\n${priceStr}\n\n🛒 Compre agora: ${affiliateUrl}\n\n⏳ Oferta por tempo limitado!`;

  // Telegram
  const telegram = `🎯 **${titleLine}**${descLine}\n\n${priceStr}\n\n➡️ ${affiliateUrl}`;

  // Story / legenda
  const story = `🔥 ${titleLine}${price ? ` por R$ ${price}` : ''}\n${descLine ? descLine + '\n' : ''}\n👆 Link na bio!\n\n#oferta #promoção #${platform.toLowerCase()} #desconto #compraonline`;

  // Título para anúncio
  const adTitle = title ? title.substring(0, 70) + (title.length > 70 ? '...' : '') : 'Oferta Especial';

  setResult('result-whatsapp', whatsapp);
  setResult('result-telegram', telegram);
  setResult('result-story', story);
  setResult('result-adtitle', adTitle);

  document.getElementById('results-content').style.display = 'block';
}

function setResult(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

/* ===== SALVAR PRODUTO ===== */
function saveProduct() {
  const title = document.getElementById('prod-title').value.trim();
  const price = document.getElementById('prod-price').value.trim();
  const imageUrl = document.getElementById('prod-image').value.trim();
  const description = document.getElementById('prod-desc').value.trim();
  const originalUrl = document.getElementById('link-input').value.trim();

  if (!title) {
    alert('Por favor, informe o título do produto.');
    return;
  }

  const product = {
    id: Date.now().toString(),
    title,
    price,
    imageUrl,
    description,
    originalUrl,
    affiliateUrl: state.currentAffiliateUrl,
    platform: state.currentPlatform?.key || 'outro',
    platformName: state.currentPlatform?.name || 'Outro',
    platformIcon: state.currentPlatform?.icon || '🔗',
    createdAt: new Date().toISOString()
  };

  state.products.unshift(product);
  saveToStorage();

  // Tenta salvar via API PHP se disponível
  saveProductToAPI(product);

  // Reseta formulário
  resetConverterForm();
  showFeedback('✅ Produto salvo no catálogo!');
}

async function saveProductToAPI(product) {
  try {
    await fetch('api/products.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(product)
    });
  } catch {
    // API não disponível, usa localStorage apenas
  }
}

function resetConverterForm() {
  document.getElementById('link-input').value = '';
  document.getElementById('prod-title').value = '';
  document.getElementById('prod-price').value = '';
  document.getElementById('prod-image').value = '';
  document.getElementById('prod-desc').value = '';
  document.getElementById('result-affiliate').classList.remove('visible');
  document.getElementById('results-content').style.display = 'none';
  document.getElementById('product-form-section').classList.remove('visible');
  document.getElementById('no-tag-warning').style.display = 'none';
  state.currentPlatform = null;
  state.currentAffiliateUrl = '';
}

/* ===== RENDER PRODUTOS ===== */
function renderProducts() {
  const grid = document.getElementById('products-grid');
  const count = document.getElementById('product-count');
  const statsCount = document.getElementById('stat-products');

  if (statsCount) statsCount.textContent = state.products.length;
  if (count) count.textContent = state.products.length;

  if (state.products.length === 0) {
    grid.innerHTML = `
      <div class="empty-state" style="grid-column:1/-1">
        <div class="icon">📦</div>
        <h3>Nenhum produto salvo</h3>
        <p>Converta um link de afiliado e salve no catálogo.</p>
      </div>`;
    return;
  }

  grid.innerHTML = state.products.map(p => productCardHTML(p)).join('');
}

function productCardHTML(p) {
  const imgHTML = p.imageUrl
    ? `<img src="${escapeHtml(p.imageUrl)}" alt="${escapeHtml(p.title)}" class="product-card-image" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
    : '';
  const placeholderStyle = p.imageUrl ? 'style="display:none"' : '';

  return `
  <div class="product-card" data-id="${p.id}">
    ${imgHTML}
    <div class="product-card-image-placeholder" ${placeholderStyle}>${p.platformIcon || '🛒'}</div>
    <div class="product-card-body">
      <div class="product-card-platform">
        <span class="badge badge-${p.platform}">${p.platformIcon} ${p.platformName}</span>
      </div>
      <div class="product-card-title">${escapeHtml(p.title)}</div>
      ${p.price ? `<div class="product-card-price">R$ ${escapeHtml(p.price)}</div>` : ''}
      <div class="product-card-actions">
        <button class="btn btn-secondary btn-sm" onclick="copyText('${escapeAttr(p.affiliateUrl)}')">🔗 Link</button>
        <button class="btn btn-secondary btn-sm" onclick="copyWhatsApp('${p.id}')">📱 WhatsApp</button>
        <button class="btn btn-danger btn-sm" onclick="deleteProduct('${p.id}')">🗑️</button>
      </div>
    </div>
  </div>`;
}

function deleteProduct(id) {
  if (!confirm('Excluir este produto?')) return;
  state.products = state.products.filter(p => p.id !== id);
  saveToStorage();

  // Tenta remover via API
  fetch(`api/products.php?id=${id}`, { method: 'DELETE' }).catch(() => {});

  renderProducts();
  showFeedback('🗑️ Produto excluído.');
}

function copyWhatsApp(id) {
  const p = state.products.find(x => x.id === id);
  if (!p) return;
  const priceStr = p.price ? `💰 Por apenas R$ ${p.price}` : '';
  const descLine = p.description ? `\n\n${p.description}` : '';
  const text = `🔥 *${p.title}*${descLine}\n\n${priceStr}\n\n🛒 Compre agora: ${p.affiliateUrl}\n\n⏳ Oferta por tempo limitado!`;
  copyText(text);
}

/* ===== RENDER CATÁLOGO (preview) ===== */
function renderCatalogPreview() {
  const preview = document.getElementById('catalog-preview');
  const catalogUrl = window.location.origin + window.location.pathname.replace('index.html', '') + 'catalogo.html';
  document.getElementById('catalog-url').textContent = catalogUrl;

  if (state.products.length === 0) {
    preview.innerHTML = `<div class="empty-state"><div class="icon">🏪</div><h3>Catálogo vazio</h3><p>Adicione produtos para ver o catálogo.</p></div>`;
    return;
  }

  preview.innerHTML = `
    <div class="products-grid">
      ${state.products.slice(0, 6).map(p => productCardHTML(p)).join('')}
    </div>
    ${state.products.length > 6 ? `<p style="text-align:center;margin-top:16px;color:var(--muted);font-size:.875rem;">+${state.products.length - 6} produtos no catálogo completo</p>` : ''}
  `;
}

/* ===== CONFIGURAÇÕES ===== */
function loadSettingsForm() {
  document.getElementById('cfg-amazon-tag').value = state.settings.amazonTag || '';
  document.getElementById('cfg-shopee-link').value = state.settings.shopeeAffiliateLink || '';
  document.getElementById('cfg-ml-link').value = state.settings.mlAffiliateLink || '';
  document.getElementById('cfg-store-name').value = state.settings.storeName || '';
  document.getElementById('cfg-store-desc').value = state.settings.storeDesc || '';
}

function saveSettings() {
  state.settings.amazonTag = document.getElementById('cfg-amazon-tag').value.trim();
  state.settings.shopeeAffiliateLink = document.getElementById('cfg-shopee-link').value.trim();
  state.settings.mlAffiliateLink = document.getElementById('cfg-ml-link').value.trim();
  state.settings.storeName = document.getElementById('cfg-store-name').value.trim() || 'Minhas Promoções';
  state.settings.storeDesc = document.getElementById('cfg-store-desc').value.trim();

  saveToStorage();

  // Tenta salvar via API
  fetch('api/settings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(state.settings)
  }).catch(() => {});

  showFeedback('✅ Configurações salvas!');
}

/* ===== UTILITÁRIOS ===== */
function copyText(text) {
  navigator.clipboard.writeText(text).then(() => {
    showFeedback('📋 Copiado!');
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
    showFeedback('📋 Copiado!');
  });
}

function showFeedback(msg) {
  const el = document.getElementById('copy-feedback');
  el.textContent = msg;
  el.style.display = 'block';
  clearTimeout(el._timer);
  el._timer = setTimeout(() => { el.style.display = 'none'; }, 2200);
}

function escapeHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function escapeAttr(str) {
  return String(str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function updateStats() {
  const statsEl = document.getElementById('stat-products');
  if (statsEl) statsEl.textContent = state.products.length;

  const platformCounts = {};
  state.products.forEach(p => {
    platformCounts[p.platform] = (platformCounts[p.platform] || 0) + 1;
  });

  const topEl = document.getElementById('stat-top-platform');
  if (topEl) {
    const top = Object.entries(platformCounts).sort((a, b) => b[1] - a[1])[0];
    topEl.textContent = top ? ({ amazon: 'Amazon', shopee: 'Shopee', mercadolivre: 'Mercado Livre' }[top[0]] || top[0]) : '—';
  }
}

/* ===== EXPORTAR PRODUTOS ===== */
function exportProducts() {
  const data = JSON.stringify({ settings: state.settings, products: state.products }, null, 2);
  const blob = new Blob([data], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'divulga-pro-backup.json';
  a.click();
  URL.revokeObjectURL(url);
}

function importProducts(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    try {
      const data = JSON.parse(e.target.result);
      if (data.products) { state.products = data.products; }
      if (data.settings) { state.settings = { ...state.settings, ...data.settings }; }
      saveToStorage();
      showFeedback('✅ Backup importado!');
      renderProducts();
    } catch {
      alert('Arquivo inválido.');
    }
  };
  reader.readAsText(file);
}

/* ===== INIT ===== */
document.addEventListener('DOMContentLoaded', () => {
  loadFromStorage();
  updateStats();
  showPage('converter');

  // Navegação
  document.querySelectorAll('[data-page]').forEach(el => {
    el.addEventListener('click', () => showPage(el.dataset.page));
  });

  // Converter on Enter
  document.getElementById('link-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') handleConvert();
  });

  // Atualizar conteúdo ao digitar
  ['prod-title', 'prod-price', 'prod-desc'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', generateContent);
  });
});
