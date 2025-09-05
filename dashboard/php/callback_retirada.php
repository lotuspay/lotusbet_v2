<?php
require_once '../../includes/db.php';
require_once '../../includes/config.php';

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = file_get_contents('php://input'); // corpo bruto

// Verificar assinatura HMAC (substitui validação por token)
$receivedSignature = $_SERVER['HTTP_LOTUSPAY_SIGNATURE'] ?? '';
$expectedSignature = hash_hmac('sha256', $payload, $TokenLotusPay);

if (!$receivedSignature || !hash_equals($expectedSignature, $receivedSignature)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'msg' => 'Assinatura inválida']);
    exit;
}

// Ler JSON
$data = json_decode($payload, true);

// Verificar parâmetros obrigatórios
if (!isset($data['id'], $data['status'])) {
    http_response_code(400);
    exit;
}

$id_transacao = $data['id'];
$status = $data['status'];

// Atualizar status se for Aprovado
if ($status === 'Completed') {
    $update = $pdo->prepare("UPDATE bet_transacoes SET bet_status = 'Aprovado' WHERE bet_id_transacao = :id_transacao");
    $update->execute([':id_transacao' => $id_transacao]);
}

// Resposta de sucesso
http_response_code(200);
echo json_encode(['status' => 'success']);
exit;