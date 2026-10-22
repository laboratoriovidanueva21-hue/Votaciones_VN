
<?php

require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/conexion.php';

if (!empty($_SESSION['admin_id'])) {
    redirigir('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

    $stmt = $pdo->prepare(
        'SELECT id, usuario, password_hash
         FROM administradores
         WHERE usuario = ?
         LIMIT 1'
    );

    $stmt->execute([$usuario]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($clave, $admin['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_usuario'] = (string) $admin['usuario'];

        redirigir('index.php');
    }

    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Votaciones Vida Nueva | Acceso administrador</title>
    <link rel="icon" type="image/webp" href="../imagenes/OIP.webp">

    <link rel="stylesheet" href="../assets/estilos.css">

    <style>
        :root {
            --verde: #176b3a;
            --verde-oscuro: #104d2b;
            --verde-claro: #eaf5e9;
            --amarillo: #f2cd36;
            --blanco: #ffffff;
            --fondo: #f5f8f2;
            --texto: #193326;
            --texto-suave: #66776c;
            --borde: #dce7dc;
            --sombra: 0 12px 35px rgba(16, 77, 43, 0.09);
        }

        * {
            box-sizing: border-box;
        }

        body.fondo {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top right, #e7f3d9 0, transparent 30%),
                var(--fondo);
            color: var(--texto);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .barra {
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 15px max(5%, calc((100% - 1120px) / 2));
            background: var(--blanco);
            border-bottom: 1px solid var(--borde);
            box-shadow: 0 3px 15px rgba(16, 77, 43, 0.04);
        }

        .marca {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--verde-oscuro);
            font-size: 17px;
            font-weight: 800;
            text-decoration: none;
        }

        .marca-icono {
            width: 47px;
            height: 47px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 14px;
            background: var(--verde);
            color: var(--amarillo);
            font-size: 24px;
            font-weight: 900;
            box-shadow: 0 5px 12px rgba(23, 107, 58, 0.2);
        }

        .marca small {
            display: block;
            margin-top: 4px;
            color: var(--texto-suave);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
        }

        .enlace-admin {
            padding: 10px 15px;
            border: 1px solid var(--borde);
            border-radius: 11px;
            background: #f7faf6;
            color: var(--verde-oscuro);
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .enlace-admin:hover {
            background: var(--verde-claro);
        }

        .pantalla-centro {
            min-height: calc(100vh - 82px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 16px;
        }

        .panel-admin {
            width: 100%;
            max-width: 450px;
            padding: 32px;
            border: 1px solid var(--borde);
            border-radius: 23px;
            background: var(--blanco);
            box-shadow: var(--sombra);
            animation: aparecer 0.45s ease both;
        }

        .icono-grande {
            width: 75px;
            height: 75px;
            display: grid;
            place-items: center;
            margin: 0 auto 20px;
            border: 5px solid #d8edd4;
            border-radius: 21px;
            background: var(--verde);
            color: var(--amarillo);
            font-size: 32px;
            box-shadow: 0 7px 17px rgba(23, 107, 58, 0.13);
        }

        .panel-admin h1 {
            margin: 0;
            color: var(--verde-oscuro);
            font-size: 30px;
            font-weight: 900;
            letter-spacing: -0.7px;
            text-align: center;
        }

        .muted {
            margin: 11px 0 25px;
            color: var(--texto-suave);
            font-size: 14px;
            line-height: 1.7;
            text-align: center;
        }

        .alerta {
            margin-bottom: 18px;
            padding: 13px 15px;
            border: 1px solid #efc6bd;
            border-radius: 12px;
            background: #fff0ed;
            color: #923c2e;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.6;
        }

        .panel-admin form {
            display: flex;
            flex-direction: column;
        }

        .panel-admin label {
            margin: 0 0 8px;
            color: var(--verde-oscuro);
            font-size: 13px;
            font-weight: 800;
        }

        .panel-admin input:not([type="hidden"]) {
            width: 100%;
            min-height: 48px;
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #ccdccc;
            border-radius: 11px;
            outline: none;
            background: #fbfdf9;
            color: var(--texto);
            font: inherit;
            font-size: 14px;
            transition: border-color 0.2s ease,
                        box-shadow 0.2s ease,
                        background 0.2s ease;
        }

        .panel-admin input:focus {
            border-color: var(--verde);
            background: white;
            box-shadow: 0 0 0 3px rgba(23, 107, 58, 0.11);
        }

        .boton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 49px;
            padding: 13px 19px;
            border: 0;
            border-radius: 12px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.5px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s ease,
                        background 0.2s ease,
                        box-shadow 0.2s ease;
        }

        .boton-principal {
            background: var(--verde);
            color: white;
            box-shadow: 0 5px 13px rgba(23, 107, 58, 0.2);
        }

        .boton-principal:hover {
            transform: translateY(-2px);
            background: var(--verde-oscuro);
            box-shadow: 0 8px 18px rgba(23, 107, 58, 0.24);
        }

        .boton-ancho {
            width: 100%;
        }

        .nota {
            margin: 22px 0 0;
            padding: 13px;
            border: 1px solid #eadb92;
            border-radius: 12px;
            background: #fff9dc;
            color: #62551a;
            font-size: 12px;
            line-height: 1.7;
        }

        .nota code {
            padding: 2px 5px;
            border-radius: 5px;
            background: rgba(242, 205, 54, 0.25);
            color: var(--verde-oscuro);
            overflow-wrap: anywhere;
        }

        @keyframes aparecer {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 560px) {
            .barra {
                min-height: 70px;
                padding: 12px 16px;
            }

            .marca {
                gap: 9px;
                font-size: 13px;
            }

            .marca-icono {
                width: 40px;
                height: 40px;
                border-radius: 12px;
                font-size: 21px;
            }

            .marca small {
                font-size: 8px;
                letter-spacing: 0.6px;
            }

            .enlace-admin {
                padding: 9px 10px;
                font-size: 11px;
                white-space: nowrap;
            }

            .pantalla-centro {
                min-height: calc(100vh - 70px);
                padding: 25px 16px;
            }

            .panel-admin {
                padding: 25px 20px;
                border-radius: 19px;
            }

            .panel-admin h1 {
                font-size: 27px;
            }

            .icono-grande {
                width: 66px;
                height: 66px;
                border-radius: 18px;
                font-size: 28px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }
        }
        .escudo-colegio {
    width: 55px;
    height: 55px;
    object-fit: contain;
}
    </style>
</head>

<body class="fondo">

<header class="barra">
    <a class="marca" href="../index.php">
        <img src="../imagenes/OIP.webp" alt="Escudo del colegio">
        <span>
            <?= e(NOMBRE_COLEGIO) ?>
            <small>TECNICA CRISTIANA VIDA NUEVA ⚙️</small>
        </span>
    </a>

    <a class="enlace-admin" href="../index.php">
        ← Volver a votar
    </a>
</header>

<main class="pantalla-centro">

    <section class="panel panel-admin">

        <div class="icono-grande" aria-hidden="true">🔐</div>

        <h1>Administración</h1>

        <p class="muted">
            Inicia sesión para consultar los resultados.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alerta" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <label for="usuario">Usuario</label>
            <input
                id="usuario"
                name="usuario"
                required
                autocomplete="username"
                autofocus
            >

            <label for="clave">Contraseña</label>
            <input
                id="clave"
                name="clave"
                type="password"
                required
                autocomplete="current-password"
            >

            <button
                class="boton boton-principal boton-ancho"
                type="submit"
            >
                INICIAR SESIÓN →
            </button>
        </form>

        <p class="nota">
            <strong>¿Todavía no tienes una cuenta?</strong><br>
            Si aún no has creado la cuenta, abre
            <code>instalar_admin.php</code> una sola vez.
        </p>

    </section>

</main>

</body>
</html>
