<?php
declare(strict_types=1);

require_once __DIR__ . '/configuracion.php';

$host = 'localhost';
$dbname = 'colegio_votaciones';
$user = 'root';
$password = ''; // Configuración habitual de XAMPP. Si tu MySQL tiene contraseña, cámbiala aquí.

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit(
        '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Error de conexión | Votaciones Vida Nueva</title><link rel="icon" type="image/webp" href="imagenes/OIP.webp"><body style="font-family:Arial,sans-serif;padding:30px;max-width:700px;margin:auto">'
        . '<h1>No se pudo conectar con MySQL</h1><p>Comprueba que MySQL esté iniciado en XAMPP y que hayas importado <code>database/colegio_votaciones.sql</code>.</p>'
        . '<p>Si configuraste una contraseña para MySQL, edita <code>config/conexion.php</code>.</p></body></html>'
    );
}
