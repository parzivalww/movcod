<?php
/**
 * Configurações do app (WhatsApp, IDs de afiliado, chave da IA).
 * A chave do Gemini fica guardada aqui no servidor e NUNCA é devolvida
 * para o navegador — só gravamos, nunca lemos de volta pro front.
 */
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/../data/config.json';

$defaults = [
    'whatsapp'     => '',   // número com DDI, ex: 5513991386968
    'amazonTag'    => '',    // tag Amazon Associates, ex: seunome-20
    'shopeeBase'   => '',    // link base de afiliado Shopee (opcional)
    'mlBase'       => '',     // link base de afiliado Mercado Livre (opcional)
    'storeName'    => 'Afilias',
    'geminiKey'    => '',     // chave Gemini — write-only
];

function load(string $file, array $defaults): array {
    if (!is_file($file)) return $defaults;
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

function san(mixed $v): string {
    return trim(strip_tags((string)($v ?? '')));
}

$cfg = load($file, $defaults);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];

    $cfg['whatsapp']  = preg_replace('/\D/', '', san($in['whatsapp']  ?? $cfg['whatsapp']));
    $cfg['amazonTag'] = san($in['amazonTag'] ?? $cfg['amazonTag']);
    $cfg['shopeeBase']= san($in['shopeeBase']?? $cfg['shopeeBase']);
    $cfg['mlBase']    = san($in['mlBase']    ?? $cfg['mlBase']);
    $cfg['storeName'] = san($in['storeName'] ?? $cfg['storeName']) ?: 'Afilias';

    // Só atualiza a chave se veio uma nova e não-vazia
    $newKey = san($in['geminiKey'] ?? '');
    if ($newKey !== '') {
        $cfg['geminiKey'] = $newKey;
    }

    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Resposta para o navegador — chave da IA é mascarada
$out = $cfg;
$out['hasGeminiKey'] = $cfg['geminiKey'] !== '';
unset($out['geminiKey']);

echo json_encode($out, JSON_UNESCAPED_UNICODE);
