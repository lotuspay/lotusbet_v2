<?php
// Impede acesso direto via navegador (GET)
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    header("Location: /painel/dashboard/");
    exit;
}

session_name('adm_session');
session_start();

require_once '../../../includes/db.php';

// Autenticação AJAX (verifica se o admin está logado)
require_once 'auth_ajax_adm.php';

function valida_token_csrf($form_name) {
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION["csrf_token_$form_name"]) && hash_equals($_SESSION["csrf_token_$form_name"], $token);
}

header('Content-Type: application/json');

try {
    if (!valida_token_csrf('lotuspay')) {
        echo json_encode([
            'status' => 'alertanao',
            'message' => "<p class='alertanao'>Falha. Por favor, tente novamente! <span><i class='fas fa-times'></i></span></p>"
        ]);
        exit;
    }

    $tokenLotusPay        = isset($_POST['tokenlotuspay']) ? trim((string)$_POST['tokenlotuspay']) : '';
    $tokenLotusPayWebhook = isset($_POST['tokenlotuspaywebhook']) ? trim((string)$_POST['tokenlotuspaywebhook']) : '';

    // Limites básicos de tamanho para evitar payloads absurdos
    if (strlen($tokenLotusPay) > 255 || strlen($tokenLotusPayWebhook) > 255) {
        echo json_encode([
            'status' => 'alertanao',
            'message' => "<p class='alertanao'>Tokens muito longos! <span><i class='fas fa-times'></i></span></p>"
        ]);
        exit;
    }

    // Atualiza configurações na tabela bet_adm_config (linha id=1)
    $sql = "UPDATE bet_adm_config 
            SET bet_lotuspay = :t1, bet_lotuspay_webhook = :t2 
            WHERE id = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':t1', $tokenLotusPay, PDO::PARAM_STR);
    $stmt->bindValue(':t2', $tokenLotusPayWebhook, PDO::PARAM_STR);
    $stmt->execute();

    // Regenera token CSRF
    $_SESSION['csrf_token_lotuspay'] = bin2hex(random_bytes(32));

    echo json_encode([
        'status' => 'alertasim',
        'message' => "<p class='alertasim'>Credenciais atualizadas com sucesso! <span><i class='fas fa-check'></i></span></p>"
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'alertanao',
        'message' => "<p class='alertanao'>Erro ao atualizar: " . htmlspecialchars($e->getMessage()) . " <span><i class='fas fa-times'></i></span></p>"
    ]);
    exit;
}
