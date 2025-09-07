<?php
$host = '186.194.55.166'; 
$dbname = 'lotusbet_v2';
$user = 'lotusbet_v2';
$pass = '5FT6iZBdWtwbL72m';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    // Define o modo de erro do PDO para exceções
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Em produção, você pode registrar isso em um log ao invés de exibir diretamente
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}