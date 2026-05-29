/**
 * Detecta a plataforma a partir de uma URL e converte em link de afiliado.
 */
const Affiliate = (() => {

  const PLATFORMS = {
    amazon: {
      name: 'Amazon',
      icon: '🛒',
      badge: 'badge-amazon',
      detect: url => /amazon\.com\.br|amzn\.to|a\.co\//.test(url),
      convert(url, settings) {
        const tag = settings.amazonTag;
        if (!tag) return url;
        try {
          const u = new URL(url);
          u.searchParams.set('tag', tag);
          // Remove parâmetros de rastreamento desnecessários
          ['ref', 'ref_', 'linkCode', 'linkId', 'pf_rd_i', 'pf_rd_r'].forEach(p => {
            if (u.searchParams.has(p)) u.searchParams.delete(p);
          });
          return u.toString();
        } catch {
          return url + (url.includes('?') ? '&' : '?') + 'tag=' + tag;
        }
      }
    },

    shopee: {
      name: 'Shopee',
      icon: '🟠',
      badge: 'badge-shopee',
      detect: url => /shopee\.com\.br/.test(url),
      convert(url, settings) {
        // Shopee affiliate links são gerados pela plataforma deles.
        // Se o usuário forneceu um link base de afiliado Shopee, usamos ele.
        // Caso contrário, retornamos o link original para o usuário gerar no painel.
        if (settings.shopeeAffiliateLink) {
          return settings.shopeeAffiliateLink;
        }
        return url;
      }
    },

    mercadolivre: {
      name: 'Mercado Livre',
      icon: '🛍️',
      badge: 'badge-ml',
      detect: url => /mercadolivre\.com\.br|mercadopago\.com\.br|bndes\.mercadopago/.test(url),
      convert(url, settings) {
        if (settings.mlAffiliateLink) {
          return settings.mlAffiliateLink;
        }
        return url;
      }
    }
  };

  function detect(url) {
    for (const [key, platform] of Object.entries(PLATFORMS)) {
      if (platform.detect(url)) return { key, ...platform };
    }
    return { key: 'outro', name: 'Outro', icon: '🔗', badge: 'badge-outro',
      convert: (url) => url };
  }

  function convert(url, settings) {
    const platform = detect(url);
    return {
      platform,
      affiliateUrl: platform.convert(url, settings)
    };
  }

  function extractProductId(url) {
    // Amazon ASIN
    const asinMatch = url.match(/\/dp\/([A-Z0-9]{10})/);
    if (asinMatch) return { type: 'asin', id: asinMatch[1] };

    // Shopee item ID
    const shopeeMatch = url.match(/i\.(\d+)\.(\d+)/);
    if (shopeeMatch) return { type: 'shopee', sellerId: shopeeMatch[1], itemId: shopeeMatch[2] };

    // Mercado Livre MLB ID
    const mlMatch = url.match(/MLB-?(\d+)/i);
    if (mlMatch) return { type: 'ml', id: mlMatch[1] };

    return null;
  }

  return { detect, convert, extractProductId, PLATFORMS };
})();
