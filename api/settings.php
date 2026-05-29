<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$settings_file = __DIR__ . '/../data/settings.json';

$defaults = [
    'amazonTag'          => '',
    'shopeeAffiliateLink'=> '',
    'mlAffiliateLink'    => '',
    'storeName'          => 'Minhas Promoções',
    'storeDesc'          => 'As melhores ofertas selecionadas para você!',
];

function load_settings(string $file, array $defaults): array {
    if (!file_exists($file)) return $defaults;
    $raw = file_get_contents($file);
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

function sanitize(mixed $val): string {
    return htmlspecialchars(strip_tags((string)($val ?? '')), ENT_QUOTES, 'UTF-8');
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode(load_settings($settings_file, $defaults), JSON_UNESCAPED_UNICODE);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados inválidos']);
            exit;
        }

        $settings = [
            'amazonTag'           => sanitize($input['amazonTag'] ?? ''),
            'shopeeAffiliateLink' => filter_var($input['shopeeAffiliateLink'] ?? '', FILTER_VALIDATE_URL)
                                        ? $input['shopeeAffiliateLink'] : '',
            'mlAffiliateLink'     => filter_var($input['mlAffiliateLink'] ?? '', FILTER_VALIDATE_URL)
                                        ? $input['mlAffiliateLink'] : '',
            'storeName'           => sanitize($input['storeName'] ?? '') ?: 'Minhas Promoções',
            'storeDesc'           => sanitize($input['storeDesc'] ?? ''),
        ];

        $dir = dirname($settings_file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
}
