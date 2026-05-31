<?php
/**
 * Extrai imagem, título, preço e descrição de uma página de produto.
 * Roda no servidor (sem CORS, mais confiável que APIs de terceiros).
 */
header('Content-Type: application/json; charset=utf-8');

$url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
if (!$url || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    echo json_encode(['error' => 'URL inválida']);
    exit;
}

// Bloqueia acesso a IPs internos (SSRF)
$host = parse_url($url, PHP_URL_HOST);
$ip   = gethostbyname($host);
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Endereço não permitido']);
    exit;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => ['Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'],
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$html || $code >= 400) {
    echo json_encode(['error' => 'Não consegui acessar a página', 'image' => '', 'title' => '']);
    exit;
}

$html = substr($html, 0, 200000);

function meta(string $html, string $key): string {
    $patterns = [
        '/<meta[^>]+(?:property|name)=["\']' . preg_quote($key, '/') . '["\'][^>]+content=["\'](.*?)["\']/is',
        '/<meta[^>]+content=["\'](.*?)["\'][^>]+(?:property|name)=["\']' . preg_quote($key, '/') . '["\']/is',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
    }
    return '';
}

function pageTitle(string $html): string {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function price(string $html, string $url): string {
    if (str_contains($url, 'amazon.')) {
        if (preg_match('/a-price-whole[^>]*>\s*([\d.]+)/i', $html, $m)) {
            $cents = '';
            if (preg_match('/a-price-fraction[^>]*>\s*(\d{2})/i', $html, $f)) $cents = $f[1];
            return $m[1] . ($cents ? ",$cents" : '');
        }
    }
    if (str_contains($url, 'mercadolivre.') || str_contains($url, 'mercadolibre.')) {
        if (preg_match('/andes-money-amount__fraction[^>]*>(\d[\d.]*)/i', $html, $m)) return $m[1];
    }
    // og:price genérico
    $g = meta($html, 'product:price:amount') ?: meta($html, 'og:price:amount');
    return $g;
}

$image = meta($html, 'og:image:secure_url') ?: meta($html, 'og:image') ?: meta($html, 'twitter:image');
$title = meta($html, 'og:title') ?: pageTitle($html);
$desc  = meta($html, 'og:description');

// Limpa sufixos de loja do título
$title = preg_replace('/\s*[\|\-–—]\s*(Amazon[^"]*|Shopee[^"]*|Mercado\s*Livre[^"]*|AliExpress[^"]*|Magazine\s*Luiza[^"]*|Magalu[^"]*)$/ui', '', $title);
$title = trim(preg_replace('/^\s*compre\s+/i', '', $title));

echo json_encode([
    'image'       => $image,
    'title'       => $title,
    'description' => $desc,
    'price'       => price($html, $url),
], JSON_UNESCAPED_UNICODE);
