<?php
require_once '../../includes/db.php';
require_once '../../includes/config.php';
require_once '../../includes/facebook_pixel.php';

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

// Se status for 'Aprovado', processa
if ($status === 'Completed') {

    // Busca a transação
    $stmt = $pdo->prepare("SELECT bet_usuario, bet_valor, bet_origem, bet_ip, bet_fbc, bet_fbp FROM bet_transacoes WHERE bet_id_transacao = :id_transacao AND bet_status != 'Aprovado' LIMIT 1");
    $stmt->execute([':id_transacao' => $id_transacao]);
    $transacao = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($transacao) {
        $usuario_id = $transacao['bet_usuario'];
        $valor = $transacao['bet_valor'];

        // Busca saldo atual do usuário
        $stmtSaldo = $pdo->prepare("SELECT bet_saldo, bet_email FROM bet_usuarios WHERE id = :usuario_id LIMIT 1");
        $stmtSaldo->execute([':usuario_id' => $usuario_id]);
        $usuario = $stmtSaldo->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $email = $usuario['bet_email'];
            $saldo_atual = $usuario['bet_saldo'];
            $novo_saldo = $saldo_atual + $valor;

            // Atualiza saldo do usuário
            $updateSaldo = $pdo->prepare("UPDATE bet_usuarios SET bet_saldo = :novo_saldo WHERE id = :usuario_id");
            $updateSaldo->execute([':novo_saldo' => $novo_saldo, ':usuario_id' => $usuario_id]);

            // Atualiza status da transação
            $updateTransacao = $pdo->prepare("UPDATE bet_transacoes SET bet_status = 'Aprovado' WHERE bet_id_transacao = :id_transacao");
            $updateTransacao->execute([':id_transacao' => $id_transacao]);

// Caso esteja usando ads do facebook
if (!empty($transacao['bet_origem']) && $transacao['bet_origem'] === 'facebook' && !empty($FacePixel) && !empty($FaceToken)) {
    $ipUsuario = $transacao['bet_ip'];
    $fbc = $transacao['bet_fbc'];
    $fbp = $transacao['bet_fbp'];

    $resultado_facebook = enviarEventoFacebookPurchase($email, $valor, $FacePixel, $FaceToken, $ipUsuario, $fbc, $fbp);
}

        }
    }
}

// Resposta de sucesso
http_response_code(200);
echo json_encode(['status' => 'success']);
exit;