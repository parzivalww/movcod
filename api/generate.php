<?php
/**
 * Gera uma chamada criativa e engraçada para o produto usando Google Gemini.
 * A chave da API é lida do servidor (data/config.json), nunca do navegador.
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$cfgFile = __DIR__ . '/../data/config.json';
$cfg = is_file($cfgFile) ? json_decode((string)file_get_contents($cfgFile), true) : [];
$apiKey = trim($cfg['geminiKey'] ?? '');

if ($apiKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Chave da IA não configurada. Vá em ⚙️ Configurações.']);
    exit;
}

$in     = json_decode((string)file_get_contents('php://input'), true) ?: [];
$title  = trim(strip_tags((string)($in['title'] ?? '')));
$store  = trim(strip_tags((string)($in['store'] ?? 'loja')));
$oldP   = trim(strip_tags((string)($in['old'] ?? '')));
$newP   = trim(strip_tags((string)($in['new'] ?? '')));
$tone   = trim(strip_tags((string)($in['tone'] ?? 'engraçado')));

if ($title === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Informe o título do produto']);
    exit;
}

$precoInfo = ($oldP && $newP) ? "Estava {$oldP}, agora {$newP}." : ($newP ? "Preço: {$newP}." : '');

$prompt = <<<TXT
Você é um copywriter brasileiro genial, criativo e MUITO bem-humorado, especialista em divulgação de ofertas de afiliados para WhatsApp e Instagram.

Crie uma CHAMADA (headline) curta, original e que prenda a atenção para o produto abaixo. Use humor brasileiro, trocadilhos, memes ou referências culturais SEMPRE que combinar com o produto. Seja específico ao produto — nada genérico.

Exemplos do estilo desejado:
- Para um câmbio/marcha do jogo Euro Truck Simulator 2: "Não é uma cilada, Bino!"
- Para um fone com cancelamento de ruído: "Modo 'não tô em casa pra ninguém' ativado"
- Para uma air fryer: "Fritou sem óleo? Tá explicado o sucesso."

Produto: {$title}
Loja: {$store}
{$precoInfo}
Tom desejado: {$tone}

Responda SOMENTE com um JSON válido, sem markdown, exatamente assim:
{
  "headline": "a chamada criativa, MAIÚSCULAS ou não, curta (máx 6 palavras)",
  "descricao": "uma frase curta de apoio, divertida, máx 120 caracteres",
  "alternativas": ["outra chamada", "mais uma chamada", "mais uma"]
}
TXT;

$body = json_encode([
    'contents'         => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 1.0, 'maxOutputTokens' => 600],
], JSON_UNESCAPED_UNICODE);

$model = 'gemini-2.0-flash';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$resp || $code !== 200) {
    $err = json_decode((string)$resp, true);
    http_response_code(502);
    echo json_encode(['error' => $err['error']['message'] ?? 'Erro ao chamar a IA (verifique a chave).']);
    exit;
}

$data = json_decode($resp, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
$text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));

$content = json_decode($text, true);
if (!$content || empty($content['headline'])) {
    echo json_encode(['error' => 'A IA respondeu em formato inesperado.', 'raw' => $text]);
    exit;
}

echo json_encode($content, JSON_UNESCAPED_UNICODE);
