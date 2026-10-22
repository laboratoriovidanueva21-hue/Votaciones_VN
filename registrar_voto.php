<?php
declare(strict_types=1);
require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php');
}
validar_csrf();

$hash = $_SESSION['pending_hash'] ?? '';
$creado = (int)($_SESSION['pending_at'] ?? 0);
$opcion = (string)($_POST['opcion'] ?? '');

if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash) ||
    $creado <= 0 || time() - $creado > DURACION_PENDIENTE_SEGUNDOS ||
    !in_array($opcion, ['lista1', 'lista2', 'nulo'], true)) {
    unset($_SESSION['pending_hash'], $_SESSION['pending_at'], $_SESSION['seleccion_voto']);
    $_SESSION['error_inicio'] = 'La sesión venció o la selección no es válida. Inténtalo nuevamente.';
    redirigir('index.php');
}

try {
    $pdo->beginTransaction();

    // El índice UNIQUE es la protección definitiva contra solicitudes simultáneas/repetidas.
    $stmt = $pdo->prepare('INSERT INTO cedulas_votantes (hash_cedula) VALUES (?)');
    $stmt->execute([$hash]);

    // El voto no contiene una referencia a la tabla de huellas de cédula.
    $stmt = $pdo->prepare('INSERT INTO votos (opcion) VALUES (?)');
    $stmt->execute([$opcion]);

    $pdo->commit();

    unset($_SESSION['pending_hash'], $_SESSION['pending_at'], $_SESSION['seleccion_voto']);
    $_SESSION['voto_completado'] = true;
    redirigir('gracias.php');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Error 23000 normalmente indica que esa huella ya existe (voto duplicado).
    if (($e->errorInfo[0] ?? '') === '23000') {
        unset($_SESSION['pending_hash'], $_SESSION['pending_at'], $_SESSION['seleccion_voto']);
        $_SESSION['error_inicio'] = 'Esta cédula ya registró su voto. No es posible votar dos veces.';
        redirigir('index.php');
    }

    error_log('Error registrando voto: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo registrar el voto. Revisa la conexión a MySQL e inténtalo nuevamente.');
}
