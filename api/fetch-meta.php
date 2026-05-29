<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);

if (!$url || !preg_match('/^https?:\/\//i', $url)) {
    http_response_code(400);
    echo json_encode(['error' => 'URL inválida']);
    exit;
}

// Bloqueia IPs internos
$host = parse_url($url, PHP_URL_HOST);
$ip = gethostbyname($host);
if (
    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
) {
    http_response_code(403);
    echo json_encode(['error' => 'URL não permitida']);
    exit;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 4,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Accept-Language: pt-BR,pt;q=0.9'],
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$html || $httpCode >= 400) {
    echo json_encode(['error' => 'Não foi possível acessar a página']);
    exit;
}

// Limita o HTML para evitar processamento excessivo
$html = substr($html, 0, 100000);

function getMeta(string $html, string $property): string {
    // og: e name=
    if (preg_match('/<meta[^>]+(?:property|name)=["\']' . preg_quote($property, '/') . '["\'][^>]+content=["\'](.*?)["\']/is', $html, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('/<meta[^>]+content=["\'](.*?)["\'"][^>]+(?:property|name)=["\']' . preg_quote($property, '/') . '["\'][^>]*>/is', $html, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function getTitle(string $html): string {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function extractPrice(string $html, string $url): string {
    // Amazon Brasil
    if (str_contains($url, 'amazon.com.br')) {
        if (preg_match('/class="[^"]*a-price-whole[^"]*"[^>]*>\s*([\d.,]+)/i', $html, $m)) {
            return $m[1];
        }
    }
    // Shopee
    if (str_contains($url, 'shopee.com.br')) {
        if (preg_match('/\"price\":(\d+)/i', $html, $m)) {
            $price = (int)$m[1] / 100000;
            return number_format($price, 2, ',', '.');
        }
    }
    // Mercado Livre
    if (str_contains($url, 'mercadolivre.com.br')) {
        if (preg_match('/class="[^"]*andes-money-amount__fraction[^"]*"[^>]*>(\d[\d.,]*)/i', $html, $m)) {
            return $m[1];
        }
    }
    // Genérico: busca meta og:price ou price meta tag
    $generic = getMeta($html, 'og:price:amount') ?: getMeta($html, 'product:price:amount');
    return $generic;
}

$ogImage       = getMeta($html, 'og:image');
$ogTitle       = getMeta($html, 'og:title') ?: getTitle($html);
$ogDescription = getMeta($html, 'og:description');
$price         = extractPrice($html, $url);

// Limpa o título (remove " | Amazon.com.br", " - Shopee", etc.)
$ogTitle = preg_replace('/\s*[\|\-–]\s*(Amazon|Shopee|Mercado Livre|Magazine Luiza|Magalu).*$/ui', '', $ogTitle);
$ogTitle = trim($ogTitle);

echo json_encode([
    'title'       => $ogTitle,
    'image'       => $ogImage,
    'description' => $ogDescription,
    'price'       => $price,
], JSON_UNESCAPED_UNICODE);
