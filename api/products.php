<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$data_file = __DIR__ . '/../data/products.json';

function load_products(string $file): array {
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function save_products(string $file, array $products): void {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function sanitize(mixed $val): string {
    return htmlspecialchars(strip_tags((string)($val ?? '')), ENT_QUOTES, 'UTF-8');
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode(load_products($data_file), JSON_UNESCAPED_UNICODE);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['title'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Título obrigatório']);
            exit;
        }

        $product = [
            'id'           => $input['id'] ?? uniqid('p', true),
            'title'        => sanitize($input['title']),
            'price'        => sanitize($input['price'] ?? ''),
            'imageUrl'     => filter_var($input['imageUrl'] ?? '', FILTER_VALIDATE_URL) ? $input['imageUrl'] : '',
            'description'  => sanitize($input['description'] ?? ''),
            'originalUrl'  => filter_var($input['originalUrl'] ?? '', FILTER_VALIDATE_URL) ? $input['originalUrl'] : '',
            'affiliateUrl' => filter_var($input['affiliateUrl'] ?? '', FILTER_VALIDATE_URL) ? $input['affiliateUrl'] : ($input['originalUrl'] ?? ''),
            'platform'     => sanitize($input['platform'] ?? 'outro'),
            'platformName' => sanitize($input['platformName'] ?? 'Outro'),
            'platformIcon' => sanitize($input['platformIcon'] ?? '🔗'),
            'createdAt'    => date('Y-m-d H:i:s'),
        ];

        $products = load_products($data_file);

        // Evita duplicatas pelo mesmo ID
        $products = array_filter($products, fn($p) => $p['id'] !== $product['id']);
        array_unshift($products, $product);

        save_products($data_file, $products);
        http_response_code(201);
        echo json_encode($product, JSON_UNESCAPED_UNICODE);
        break;

    case 'DELETE':
        $id = sanitize($_GET['id'] ?? '');
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID obrigatório']); exit; }

        $products = load_products($data_file);
        $filtered = array_filter($products, fn($p) => $p['id'] !== $id);

        if (count($filtered) === count($products)) {
            http_response_code(404);
            echo json_encode(['error' => 'Produto não encontrado']);
            exit;
        }

        save_products($data_file, $filtered);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
}
