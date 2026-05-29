/* Página pública do catálogo */
document.addEventListener('DOMContentLoaded', () => {
  let allProducts = [];

  function loadData() {
    // Tenta carregar do servidor via API
    fetch('api/products.php')
      .then(r => r.json())
      .then(data => {
        if (Array.isArray(data) && data.length > 0) {
          allProducts = data;
        } else {
          loadFromLocalStorage();
        }
        loadSettings();
        renderAll();
      })
      .catch(() => {
        loadFromLocalStorage();
        loadSettings();
        renderAll();
      });
  }

  function loadFromLocalStorage() {
    try {
      const p = localStorage.getItem('divulga_products');
      if (p) allProducts = JSON.parse(p) || [];
    } catch {}
  }

  function loadSettings() {
    fetch('api/settings.php')
      .then(r => r.json())
      .then(s => applySettings(s))
      .catch(() => {
        try {
          const s = localStorage.getItem('divulga_settings');
          if (s) applySettings(JSON.parse(s));
        } catch {}
      });
  }

  function applySettings(s) {
    if (!s) return;
    if (s.storeName) {
      document.getElementById('cat-store-name').textContent = s.storeName;
      document.title = s.storeName + ' — Catálogo';
    }
    if (s.storeDesc) {
      document.getElementById('cat-store-desc').textContent = s.storeDesc;
    }
  }

  function renderAll(filter = 'all', search = '') {
    const grid = document.getElementById('catalog-grid');
    let products = allProducts;

    if (filter !== 'all') {
      products = products.filter(p => p.platform === filter);
    }

    if (search) {
      const q = search.toLowerCase();
      products = products.filter(p =>
        (p.title || '').toLowerCase().includes(q) ||
        (p.description || '').toLowerCase().includes(q)
      );
    }

    document.getElementById('catalog-count').textContent = products.length + ' produto' + (products.length !== 1 ? 's' : '');

    if (products.length === 0) {
      grid.innerHTML = `
        <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#64748B">
          <div style="font-size:3rem;margin-bottom:12px">🔍</div>
          <h3 style="margin-bottom:6px">Nenhum produto encontrado</h3>
          <p style="font-size:.875rem">Tente outro termo de busca.</p>
        </div>`;
      return;
    }

    grid.innerHTML = products.map(p => cardHTML(p)).join('');
  }

  function cardHTML(p) {
    const img = p.imageUrl
      ? `<img src="${esc(p.imageUrl)}" alt="${esc(p.title)}" loading="lazy"
             style="width:100%;height:180px;object-fit:contain;background:#F8FAFC;padding:10px"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
      : '';
    const placeholder = `<div style="display:${p.imageUrl ? 'none' : 'flex'};width:100%;height:180px;background:#F1F5F9;align-items:center;justify-content:center;font-size:3rem">${p.platformIcon || '🛒'}</div>`;

    const priceHTML = p.price ? `<div style="font-size:1.2rem;font-weight:700;color:#7C3AED;margin-bottom:12px">R$ ${esc(p.price)}</div>` : '';

    const badgeMap = { amazon: '#FFF3CC:#92400E', shopee: '#FEE2E2:#991B1B', mercadolivre: '#FEF9C3:#713F12' };
    const [bg, color] = (badgeMap[p.platform] || '#F1F5F9:#64748B').split(':');

    return `
      <div style="background:#fff;border-radius:12px;border:1px solid #E2E8F0;overflow:hidden;transition:box-shadow .2s"
           onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
           onmouseout="this.style.boxShadow='none'">
        ${img}${placeholder}
        <div style="padding:14px">
          <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;background:${bg};color:${color};margin-bottom:8px">
            ${p.platformIcon} ${p.platformName}
          </span>
          <div style="font-weight:600;font-size:.9rem;line-height:1.4;margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
            ${esc(p.title)}
          </div>
          ${priceHTML}
          <a href="${esc(p.affiliateUrl)}" target="_blank" rel="noopener"
             style="display:block;text-align:center;background:linear-gradient(135deg,#7C3AED,#6D28D9);color:white;padding:10px;border-radius:8px;font-weight:600;font-size:.875rem;text-decoration:none;transition:opacity .15s"
             onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            🛒 Ver Oferta
          </a>
        </div>
      </div>`;
  }

  function esc(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // Filtros
  let activeFilter = 'all';
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeFilter = btn.dataset.filter;
      renderAll(activeFilter, document.getElementById('search-input').value);
    });
  });

  document.getElementById('search-input')?.addEventListener('input', e => {
    renderAll(activeFilter, e.target.value.trim());
  });

  loadData();
});
