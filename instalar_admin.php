<?php
declare(strict_types=1);
require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/conexion.php';

$count = (int)$pdo->query('SELECT COUNT(*) FROM administradores')->fetchColumn();
$error = '';
$hecho = false;

if ($count === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');
    $confirmar = (string)($_POST['confirmar'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_.-]{4,50}$/', $usuario)) {
        $error = 'El usuario debe tener entre 4 y 50 caracteres: letras, números, punto, guion o guion bajo.';
    } elseif (strlen($clave) < 10) {
        $error = 'La contraseña debe tener al menos 10 caracteres.';
    } elseif ($clave !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO administradores (usuario, password_hash) VALUES (?, ?)');
        $stmt->execute([$usuario, password_hash($clave, PASSWORD_DEFAULT)]);
        $hecho = true;
        $count = 1;
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Votaciones Vida Nueva | Crear administrador</title><link rel="icon" type="image/webp" href="imagenes/OIP.webp"><link rel="stylesheet" href="assets/estilos.css"></head>
<body class="fondo"><main class="pantalla-centro"><section class="panel panel-admin">
<h1>Configuración inicial</h1>
<?php if ($count > 0): ?>
  <div class="alerta-neutra">Ya existe un administrador o la cuenta se creó correctamente. Por seguridad, elimina el archivo <code>instalar_admin.php</code> de la carpeta del proyecto.</div>
  <a class="boton boton-principal boton-ancho" href="admin/login.php">Ir al inicio de administración</a>
<?php else: ?>
  <p class="muted">Crea la cuenta que protegerá los resultados de la elección.</p>
  <?php if ($error !== ''): ?><div class="alerta"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label for="usuario">Usuario de administrador</label><input id="usuario" name="usuario" required minlength="4" maxlength="50" autocomplete="username">
    <label for="clave">Contraseña (mínimo 10 caracteres)</label><input id="clave" name="clave" type="password" required minlength="10" autocomplete="new-password">
    <label for="confirmar">Repite la contraseña</label><input id="confirmar" name="confirmar" type="password" required minlength="10" autocomplete="new-password">
    <button class="boton boton-principal boton-ancho" type="submit">CREAR ADMINISTRADOR</button>
  </form>
<?php endif; ?>
</section></main></body></html>
