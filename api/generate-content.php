<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$title       = strip_tags((string)($input['title'] ?? ''));
$price       = strip_tags((string)($input['price'] ?? ''));
$description = strip_tags((string)($input['description'] ?? ''));
$platform    = strip_tags((string)($input['platform'] ?? 'loja'));
$apiKey      = strip_tags((string)($input['apiKey'] ?? ''));

if (!$title || !$apiKey) {
    http_response_code(400);
    echo json_encode(['error' => 'Título e chave API são obrigatórios']);
    exit;
}

$priceInfo = $price ? "Preço: R$ {$price}" : "Preço não informado";

$prompt = <<<PROMPT
Você é um especialista em marketing de afiliados brasileiro, criativo, engraçado e antenado na cultura pop e memes do Brasil.

Crie textos de divulgação para o produto abaixo. Os textos devem ser:
- Específicos ao produto (não genéricos)
- Com humor brasileiro quando fizer sentido (memes, referências culturais, gírias)
- Autênticos, como se um amigo estivesse recomendando
- Com emojis relevantes ao produto

Produto: {$title}
{$priceInfo}
Plataforma: {$platform}
{$description}

Retorne SOMENTE um JSON válido neste formato exato (sem markdown, sem explicação):
{
  "whatsapp": "texto completo para WhatsApp com emojis e link substituído por [LINK]",
  "story": "legenda curta para story/Instagram com hashtags",
  "telegram": "texto para Telegram",
  "titulo": "título criativo para anúncio (máx 80 caracteres)",
  "hook": "frase de gancho criativa e engraçada (1 linha)"
}
PROMPT;

$body = json_encode([
    'contents' => [[
        'parts' => [['text' => $prompt]]
    ]],
    'generationConfig' => [
        'temperature'     => 0.9,
        'maxOutputTokens' => 1024,
    ]
], JSON_UNESCAPED_UNICODE);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    http_response_code(502);
    $err = json_decode($response, true);
    echo json_encode(['error' => $err['error']['message'] ?? 'Erro ao chamar a API Gemini']);
    exit;
}

$data = json_decode($response, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Remove possível markdown ```json ... ```
$text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
$text = preg_replace('/\s*```$/', '', $text);

$content = json_decode($text, true);

if (!$content || !isset($content['whatsapp'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Resposta inesperada da IA', 'raw' => $text]);
    exit;
}

echo json_encode($content, JSON_UNESCAPED_UNICODE);
