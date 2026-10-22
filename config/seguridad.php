<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $valor): string {
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validar_csrf(): void {
    $recibido = $_POST['csrf_token'] ?? '';
    $guardado = $_SESSION['csrf_token'] ?? '';
    if (!is_string($recibido) || !is_string($guardado) || $guardado === '' || !hash_equals($guardado, $recibido)) {
        http_response_code(419);
        exit('La sesión del formulario venció. Regresa e inténtalo de nuevo.');
    }
}

function huella_cedula(string $cedula): string {
    return hash_hmac('sha256', $cedula, HASH_SECRET);
}

function redirigir(string $ruta): never {
    header('Location: ' . $ruta);
    exit;
}

function admin_requerido(): void {
    if (empty($_SESSION['admin_id'])) {
        redirigir('login.php');
    }
}
