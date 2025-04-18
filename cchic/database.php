<?php
// Configuration de la base de données
$host = 'localhost';
$dbname = 'cchic';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] Erreur de connexion à la base de données: ' . $e->getMessage() . "\n", 3, 'db_errors.log');
    die(json_encode(['status' => 'error', 'message' => 'Échec de la connexion à la base de données']));
}
?>