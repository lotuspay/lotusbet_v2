<?php
// Simple integrity guard for critical files.
// Verifica se arquivos críticos (callbacks) foram alterados byte a byte.
// Armazena hashes SHA-256 em includes/integrity.json. Se divergirem, redireciona para uma imagem e encerra.

function lotus_integrity_verify(): void {
    // Defina aqui os arquivos críticos relativos à raiz do projeto
    $root = dirname(__DIR__);
    $critical = [
        $root . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'callback_deposito.php',
        $root . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'callback_retirada.php',
    ];

    $storePath = __DIR__ . DIRECTORY_SEPARATOR . 'integrity.json';
    $current = [];

    foreach ($critical as $file) {
        if (!is_file($file)) {
            // Se o arquivo crítico sumiu, força bloqueio
            lotus_integrity_block($root);
            return;
        }
        $hash = hash_file('sha256', $file);
        $current[$file] = $hash;
    }

    // Se não houver arquivo de referência, cria um (primeira execução)
    if (!file_exists($storePath)) {
        // Tenta persistir; se não conseguir, ainda assim segue execução normal
        @file_put_contents($storePath, json_encode($current, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return;
    }

    $storedRaw = @file_get_contents($storePath);
    $stored = json_decode((string)$storedRaw, true);
    if (!is_array($stored)) {
        // Se arquivo está corrompido, recria com estado atual
        @file_put_contents($storePath, json_encode($current, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return;
    }

    // Compara hashes
    foreach ($current as $file => $hashNow) {
        $hashStored = $stored[$file] ?? null;
        if (!$hashStored || !hash_equals($hashStored, $hashNow)) {
            lotus_integrity_block($root);
            return;
        }
    }
}

function lotus_integrity_block(string $root): void {
    // Tenta redirecionar para uma imagem de erro se existir
    $errorImg = $root . DIRECTORY_SEPARATOR . 'imagens' . DIRECTORY_SEPARATOR . 'erro-integridade.png';
    if (is_file($errorImg)) {
        $rel = '/imagens/erro-integridade.png';
        header('Location: ' . $rel, true, 302);
    } else {
        // Fallback: resposta 503 simples
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Integração bloqueada por falha de integridade.';
    }
    exit;
}
