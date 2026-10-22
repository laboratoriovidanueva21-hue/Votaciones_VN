
<?php

require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/conexion.php';

$error = $_SESSION['error_inicio'] ?? '';
unset($_SESSION['error_inicio']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $cedula = trim((string)($_POST['cedula'] ?? ''));

    // Validar que la cédula tenga exactamente 10 dígitos.
    if (!preg_match('/^[0-9]{10}$/', $cedula)) {
        $error = 'Ingresa una cédula válida de 10 números.';
    } else {
        $hash = huella_cedula($cedula);

        $stmt = $pdo->prepare(
            'SELECT id FROM estudiantes_autorizados
             WHERE hash_cedula = ? LIMIT 1'
        );
        $stmt->execute([$hash]);

        if (!$stmt->fetch()) {
            $error = 'Esta cédula no está autorizada para votar.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id FROM cedulas_votantes
                 WHERE hash_cedula = ? LIMIT 1'
            );
            $stmt->execute([$hash]);

            if ($stmt->fetch()) {
                $error = 'Esta cédula ya registró su voto. No es posible votar dos veces.';
            } else {
                // No guardar la cédula original en la sesión.
                $_SESSION['pending_hash'] = $hash;
                $_SESSION['pending_at'] = time();

                unset($_SESSION['seleccion_voto']);

                redirigir('seleccionar.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Votaciones Vida Nueva</title>
<link rel="icon" type="image/webp" href="imagenes/OIP.webp">

<style>
:root {
    --verde: #176b3a;
    --verde-oscuro: #104d2b;
    --verde-claro: #eaf5e9;
    --amarillo: #f2cd36;
    --amarillo-hover: #e4bb20;
    --blanco: #ffffff;
    --fondo: #f5f8f1;
    --texto: #20352a;
    --secundario: #68776b;
    --borde: #dce7d9;
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    color: var(--texto);
    line-height: 1.6;
}

.fondo {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background:
        linear-gradient(rgba(5, 10, 15, 0.45), rgba(5, 10, 15, 0.58)),
        url("imagenes/Captura.PNG") center / cover fixed,
        #101820;
}

/* ENCABEZADO */
.barra {
    min-height: 76px;
    padding: 15px 7%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: rgba(8, 12, 16, 0.55);
    border-bottom: 1px solid rgba(255, 255, 255, 0.13);
    box-shadow: 0 3px 16px rgba(0, 0, 0, 0.18);
    backdrop-filter: blur(12px);
}

.marca {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--blanco);
    font-size: 17px;
    font-weight: 800;
    line-height: 1.3;
    text-decoration: none;
}

.escudo-colegio {
    width: 55px;
    height: 55px;
    object-fit: contain;
    flex-shrink: 0;
}

.marca small {
    display: block;
    margin-top: 3px;
    color: rgba(255, 255, 255, 0.78);
    font-size: 9px;
    letter-spacing: 1.4px;
}

/* BOTÓN ADMINISTRACIÓN */
.enlace-admin {
    padding: 9px 16px;
    color: #ffffff;
    background: rgba(10, 17, 24, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.2s, border-color 0.2s;
}

.enlace-admin:hover {
    background: rgba(234, 245, 233, 0.2);
    border-color: var(--amarillo);
}

/* CONTENIDO CENTRADO */
.hero {
    width: 100%;
    max-width: 1160px;
    flex: 1;
    margin: 0 auto;
    padding: 45px 28px;
    display: grid;
    grid-template-columns: 1fr;
    align-items: center;
    justify-items: center;
}

/* TARJETA DE VOTACIÓN */
.panel {
    width: 100%;
    padding: 32px;
    background: rgba(16, 23, 32, 0.36);
    border: 1px solid rgba(255, 255, 255, 0.42);
    border-top: 4px solid var(--verde);
    border-radius: 18px;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.24);
    backdrop-filter: blur(5px);
}

.panel-votar {
    width: 100%;
    max-width: 430px;
    justify-self: center;
    margin: auto;
}

.icono-grande {
    width: 59px;
    height: 59px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    background: rgba(234, 245, 233, 0.28);
    border-radius: 12px;
    font-size: 30px;
}

.panel h2 {
    margin: 0 0 8px;
    color: #ffffff;
    font-size: 24px;
    line-height: 1.35;
}

.muted {
    margin: 0 0 24px;
    color: rgba(255, 255, 255, 0.88);
    font-size: 13px;
}

/* FORMULARIO */
.panel form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.panel label {
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
}

.panel input[type="text"] {
    width: 100%;
    min-height: 47px;
    padding: 12px 14px;
    color: var(--texto);
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(255, 255, 255, 0.72);
    border-radius: 8px;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.panel input[type="text"]:focus {
    border-color: var(--verde);
    box-shadow: 0 0 0 3px rgba(23, 107, 58, 0.2);
}

.panel input::placeholder {
    color: #9aa69a;
}

/* BOTONES */
.boton {
    min-height: 46px;
    padding: 12px 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 800;
    transition: background 0.2s, transform 0.2s;
}

.boton-principal {
    color: var(--verde-oscuro);
    background: var(--amarillo);
}

.boton-principal:hover {
    background: var(--amarillo-hover);
    transform: translateY(-1px);
}

.boton-ancho {
    width: 100%;
    margin-top: 8px;
}

.boton:focus-visible,
.enlace-admin:focus-visible {
    outline: 3px solid var(--amarillo);
    outline-offset: 3px;
}

/* MENSAJES DE ERROR */
.alerta {
    margin-bottom: 18px;
    padding: 12px 14px;
    color: #922018;
    background: #fff0ed;
    border: 1px solid #f1c4bd;
    border-radius: 8px;
    font-size: 13px;
}

/* NOTA */
.nota {
    margin: 18px 0 0;
    padding-top: 15px;
    color: rgba(255, 255, 255, 0.88);
    border-top: 1px solid rgba(255, 255, 255, 0.25);
    font-size: 11px;
    line-height: 1.7;
}

/* PIE DE PÁGINA */
.pie {
    width: 100%;
    padding: 20px;
    color: rgba(255, 255, 255, 0.86);
    background: rgba(8, 12, 16, 0.45);
    border-top: 1px solid rgba(255, 255, 255, 0.18);
    text-align: center;
    font-size: 12px;
}

/* TABLETAS */
@media (max-width: 800px) {
    .barra {
        padding: 14px 5%;
    }

    .hero {
        padding: 35px 22px;
    }

    .panel-votar {
        max-width: 480px;
    }
}

/* CELULARES */
@media (max-width: 480px) {
    .barra {
        gap: 10px;
        padding: 12px 4%;
    }

    .marca {
        gap: 8px;
        font-size: 13px;
    }

    .escudo-colegio {
        width: 45px;
        height: 45px;
    }

    .marca small {
        font-size: 8px;
        letter-spacing: 0.5px;
    }

    .enlace-admin {
        padding: 8px 10px;
        font-size: 11px;
        white-space: nowrap;
    }

    .hero {
        padding: 28px 17px;
    }

    .panel {
        padding: 23px 19px;
        border-radius: 12px;
    }

    .panel h2 {
        font-size: 22px;
    }

    .pie {
        padding: 17px 12px;
    }
}
</style>
</head>

<body class="fondo">

<header class="barra">

    <a class="marca" href="index.php">

        <img
            src="imagenes/OIP.webp"
            alt="Escudo del colegio"
            class="escudo-colegio"
        >

        <span>
            <?= e(NOMBRE_COLEGIO) ?>
            <small>TECNICA CRISTIANA VIDA NUEVA ⚙️</small>
        </span>

    </a>


</header>

<main class="hero">

    <section class="panel panel-votar">

        <div class="icono-grande">🗳️</div>

        <h2>Ingresa para votar</h2>

        <p class="muted">
            Escribe tu número de cédula para continuar.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alerta">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="index.php"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <label for="cedula">
                Número de cédula
            </label>

            <input
                id="cedula"
                name="cedula"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{10}"
                maxlength="10"
                minlength="10"
                placeholder="Ej. 1725473649"
                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
                required
            >

            <button
                class="boton boton-principal boton-ancho"
                type="submit"
            >
                CONTINUAR <span>→</span>
            </button>

        </form>

        <p class="nota">
            Solo pueden votar los estudiantes incluidos en el padrón autorizado.
        </p>

    </section>

</main>

<footer class="pie">
    Sistema de votación estudiantil · <?= date('Y') ?>
</footer>

</body>
</html>
