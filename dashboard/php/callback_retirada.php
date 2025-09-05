<?php
require_once '../../includes/db.php';
require_once '../../includes/config.php';

// Simple file logger for callbacks
function log_callback($type, $message, $context = []) {
    try {
        $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $file = $logDir . DIRECTORY_SEPARATOR . 'callback.log';
        $entry = [
            'ts' => date('c'),
            'type' => $type,
            'message' => $message,
            'context' => $context
        ];
        @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Avoid throwing from logger
    }
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_callback('callback_retirada', 'Invalid method', ['method' => $_SERVER['REQUEST_METHOD'] ?? '']);
    http_response_code(405);
    exit;
}

$payload = file_get_contents('php://input'); // corpo bruto
log_callback('callback_retirada', 'Request received', [
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'signature' => $_SERVER['HTTP_LOTUSPAY_SIGNATURE'] ?? '',
    'len' => strlen($payload)
]);

// Verificar assinatura HMAC (substitui validação por token)
$receivedSignature = $_SERVER['HTTP_LOTUSPAY_SIGNATURE'] ?? '';
$expectedSignature = hash_hmac('sha256', $payload, $TokenLotusPay);

if (!$receivedSignature || !hash_equals($expectedSignature, $receivedSignature)) {
    log_callback('callback_retirada', 'Signature mismatch', []);
    http_response_code(401);
    echo json_encode(['status' => 'error', 'msg' => 'Assinatura inválida']);
    exit;
}

// Ler JSON
$data = json_decode($payload, true);

// Verificar parâmetros obrigatórios
if (!isset($data['id'], $data['status'])) {
    log_callback('callback_retirada', 'Missing required fields', ['keys' => array_keys((array)$data)]);
    http_response_code(400);
    exit;
}

$id_transacao = $data['id'];
$status = $data['status'];

// Atualizar status se for Aprovado
if ($status === 'Completed') {
    log_callback('callback_retirada', 'Processing approved transaction', ['id' => $id_transacao]);
    $update = $pdo->prepare("UPDATE bet_transacoes SET bet_status = 'Aprovado' WHERE bet_id_transacao = :id_transacao");
    $update->execute([':id_transacao' => $id_transacao]);
    log_callback('callback_retirada', 'Transaction marked as approved', ['id' => $id_transacao]);
}

// Resposta de sucesso
log_callback('callback_retirada', 'Success response sent', []);
http_response_code(200);
echo json_encode(['status' => 'success']);
exit;