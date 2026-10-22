
<?php

require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/conexion.php';

if (
    empty($_SESSION['pending_hash']) ||
    empty($_SESSION['pending_at']) ||
    time() - (int) $_SESSION['pending_at'] > DURACION_PENDIENTE_SEGUNDOS
) {
    unset(
        $_SESSION['pending_hash'],
        $_SESSION['pending_at'],
        $_SESSION['seleccion_voto']
    );

    $_SESSION['error_inicio'] = 'Tu sesión venció. Ingresa tu cédula nuevamente.';
    redirigir('index.php');
}

$fotoLista1 = null;
$fotoLista2 = null;
$fotoNulo = null;
foreach (['jpg', 'png', 'webp'] as $extensionFoto) {
    $rutaFoto1 = __DIR__ . '/imagenes/lista1.' . $extensionFoto;
    if ($fotoLista1 === null && is_file($rutaFoto1)) {
        $fotoLista1 = 'imagenes/lista1.' . $extensionFoto . '?v=' . filemtime($rutaFoto1);
    }

    $rutaFoto2 = __DIR__ . '/imagenes/lista2.' . $extensionFoto;
    if ($fotoLista2 === null && is_file($rutaFoto2)) {
        $fotoLista2 = 'imagenes/lista2.' . $extensionFoto . '?v=' . filemtime($rutaFoto2);
    }

    $rutaFotoNulo = __DIR__ . '/imagenes/nulo.' . $extensionFoto;
    if ($fotoNulo === null && is_file($rutaFotoNulo)) {
        $fotoNulo = 'imagenes/nulo.' . $extensionFoto . '?v=' . filemtime($rutaFotoNulo);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votaciones Vida Nueva | Seleccionar voto</title>
    <link rel="icon" type="image/webp" href="imagenes/OIP.webp">

    <link rel="stylesheet" href="assets/estilos.css">

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
                linear-gradient(rgba(5, 10, 15, 0.45), rgba(5, 10, 15, 0.58)),
                url("imagenes/Captura.PNG") center / cover fixed,
                #101820;
            color: var(--blanco);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        body.fondo .contenido {
            color: var(--blanco);
        }

        .barra {
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 15px max(5%, calc((100% - 1120px) / 2));
            background: rgba(8, 12, 16, 0.55);
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.18);
            backdrop-filter: blur(8px);
        }

        .marca {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--blanco);
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
            color: rgba(255, 255, 255, 0.72);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
        }

        .paso {
            padding: 9px 14px;
            border: 1px solid rgba(242, 205, 54, 0.55);
            border-radius: 30px;
            background: rgba(242, 205, 54, 0.16);
            color: #fff4bb;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.6px;
            white-space: nowrap;
        }

        .contenido {
            width: min(100% - 32px, 790px);
            margin: 0 auto;
            padding: 52px 0 45px;
        }

        .encabezado-pagina {
            margin-bottom: 28px;
            text-align: center;
        }

        .etiqueta {
            display: inline-block;
            margin-bottom: 13px;
            padding: 8px 13px;
            border-radius: 30px;
            background: rgba(23, 107, 58, 0.38);
            color: #c8f2d1;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 1.5px;
        }

        .encabezado-pagina h1 {
            margin: 0;
            color: var(--blanco);
            font-size: clamp(27px, 5vw, 38px);
            font-weight: 900;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .encabezado-pagina p {
            max-width: 510px;
            margin: 13px auto 0;
            color: rgba(255, 255, 255, 0.84);
            font-size: 15px;
            line-height: 1.7;
        }

        .opciones-form {
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 23px;
            background: rgba(16, 23, 32, 0.42);
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.24);
            backdrop-filter: blur(8px);
        }

        .opciones {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .opcion {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            min-height: 265px;
            padding: 18px 16px 48px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.28);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.13);
            cursor: pointer;
            text-align: center;
            transition: border-color 0.2s ease,
                        background 0.2s ease,
                        transform 0.2s ease,
                        box-shadow 0.2s ease;
        }

        .opcion:hover {
            transform: translateY(-2px);
            border-color: rgba(242, 205, 54, 0.8);
            box-shadow: 0 7px 18px rgba(16, 77, 43, 0.07);
        }

        .opcion input[type="radio"] {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .opcion:has(input:checked) {
            border-color: #9bdbad;
            background: rgba(23, 107, 58, 0.48);
            box-shadow: 0 0 0 3px rgba(23, 107, 58, 0.22);
        }

        .opcion-foto {
            width: min(100%, 220px);
            height: 112px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.92);
            object-fit: contain;
        }

        .opcion-foto-vacia {
            color: var(--verde-oscuro);
            font-size: 12px;
            font-weight: 700;
        }

        .opcion:has(input:focus-visible) {
            outline: 3px solid var(--amarillo);
            outline-offset: 3px;
        }

        .opcion-simbolo {
            display: grid;
            width: 53px;
            height: 53px;
            flex-shrink: 0;
            place-items: center;
            border-radius: 15px;
            background: rgba(23, 107, 58, 0.88);
            color: white;
            font-size: 23px;
            font-weight: 900;
        }

        .opcion-violeta .opcion-simbolo {
            background: rgba(39, 120, 74, 0.9);
            color: var(--amarillo);
        }

        .opcion-gris .opcion-simbolo {
            background: rgba(255, 255, 255, 0.85);
            color: var(--blanco);
        }

        .opcion-texto {
            display: block;
            min-width: 0;
        }

        .opcion-texto b {
            display: block;
            margin-bottom: 6px;
            color: var(--blanco);
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .opcion-texto small {
            display: block;
            color: rgba(255, 255, 255, 0.76);
            font-size: 11px;
        }

        .radio-visual {
            position: absolute;
            right: 16px;
            bottom: 15px;
            width: 23px;
            height: 23px;
            border: 2px solid rgba(255, 255, 255, 0.68);
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transition: 0.2s ease;
        }

        .opcion:has(input:checked) .radio-visual {
            border: 7px solid var(--verde);
        }

        .acciones {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 25px;
        }

        .boton {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            min-height: 49px;
            padding: 13px 19px;
            border: 0;
            border-radius: 12px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.4px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s ease,
                        box-shadow 0.2s ease,
                        background 0.2s ease;
        }

        .boton:hover {
            transform: translateY(-2px);
        }

        .boton-secundario {
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.16);
            color: var(--blanco);
        }

        .boton-secundario:hover {
            background: rgba(23, 107, 58, 0.45);
        }

        .boton-principal {
            background: var(--amarillo);
            color: var(--verde-oscuro);
            box-shadow: 0 5px 13px rgba(23, 107, 58, 0.2);
        }

        .boton-principal:hover {
            background: var(--amarillo-hover);
            box-shadow: 0 8px 18px rgba(23, 107, 58, 0.24);
        }

        .aviso-secreto {
            margin: 22px 0 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 12px;
            line-height: 1.7;
            text-align: center;
        }

        .alerta {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid #efc6bd;
            border-radius: 12px;
            background: #fff0ed;
            color: #923c2e;
            font-size: 14px;
            font-weight: 700;
        }

        @media (max-width: 560px) {
            .barra {
                min-height: 70px;
                padding: 12px 16px;
            }

            .marca {
                gap: 9px;
                font-size: 14px;
            }

            .marca-icono {
                width: 40px;
                height: 40px;
                border-radius: 12px;
                font-size: 21px;
            }

            .marca small {
                font-size: 8px;
                letter-spacing: 0.7px;
            }

            .paso {
                padding: 8px 10px;
                font-size: 10px;
            }

            .contenido {
                padding-top: 35px;
            }

            .opciones-form {
                padding: 15px;
                border-radius: 18px;
            }

            .opciones {
                grid-template-columns: 1fr;
            }

            .opcion {
                gap: 12px;
                min-height: 235px;
                padding: 14px 13px 45px;
            }

            .opcion-foto {
                height: 95px;
            }

            .opcion-simbolo {
                width: 45px;
                height: 45px;
                border-radius: 12px;
                font-size: 20px;
            }

            .opcion-texto b {
                font-size: 14px;
            }

            .opcion-texto small {
                font-size: 12px;
            }

            .acciones {
                flex-direction: column-reverse;
            }

            .boton {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
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
    <a class="marca" href="index.php">
        <img src="imagenes/OIP.webp"
     alt="Escudo del colegio"
     class="escudo-colegio">
        <span>
            <?= e(NOMBRE_COLEGIO) ?>
            <small>TECNICA CRISTIANA VIDA NUEVA ⚙️</small>
        </span>
    </a>

    <span class="paso">PASO 2 DE 2</span>
</header>

<main class="contenido">

    <div class="encabezado-pagina">
        <span class="etiqueta">TU DECISIÓN</span>
        <h1>¿Por quién deseas votar?</h1>
        <p>Selecciona una sola opción. El voto se registrará inmediatamente y no podrá modificarse.</p>
    </div>

    <form method="post" action="registrar_voto.php" class="opciones-form">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <div class="opciones">

            <label class="opcion opcion-azul">
                <input
                    type="radio"
                    name="opcion"
                    value="lista1"
                    required
                >
                <?php if ($fotoLista1 !== null): ?>
                    <img class="opcion-foto" src="<?= e($fotoLista1) ?>" alt="Foto de <?= e(NOMBRE_LISTA_1) ?>">
                <?php else: ?>
                    <span class="opcion-foto opcion-foto-vacia">Foto de <?= e(NOMBRE_LISTA_1) ?></span>
                <?php endif; ?>
                <span class="opcion-simbolo">1</span>

                <span class="opcion-texto">
                    <b><?= e(NOMBRE_LISTA_1) ?></b>
                    <small>Votar por esta lista</small>
                </span>

                <span class="radio-visual"></span>
            </label>

            <label class="opcion opcion-violeta">
                <input
                    type="radio"
                    name="opcion"
                    value="lista2"
                    required
                >
                <?php if ($fotoLista2 !== null): ?>
                    <img class="opcion-foto" src="<?= e($fotoLista2) ?>" alt="Foto de <?= e(NOMBRE_LISTA_2) ?>">
                <?php else: ?>
                    <span class="opcion-foto opcion-foto-vacia">Foto de <?= e(NOMBRE_LISTA_2) ?></span>
                <?php endif; ?>
                <span class="opcion-simbolo">2</span>

                <span class="opcion-texto">
                    <b><?= e(NOMBRE_LISTA_2) ?></b>
                    <small>Votar por esta lista</small>
                </span>

                <span class="radio-visual"></span>
            </label>

            <label class="opcion opcion-gris">
                <input
                    type="radio"
                    name="opcion"
                    value="nulo"
                    required
                >
                <?php if ($fotoNulo !== null): ?>
                    <img class="opcion-foto" src="<?= e($fotoNulo) ?>" alt="Imagen de voto nulo">
                <?php else: ?>
                    <span class="opcion-foto opcion-foto-vacia">Imagen de voto nulo</span>
                <?php endif; ?>
                <span class="opcion-simbolo">Ø</span>

                <span class="opcion-texto">
                    <b>Voto nulo</b>
                    <small>Registrar un voto nulo</small>
                </span>

                <span class="radio-visual"></span>
            </label>

        </div>

        <div class="acciones">
            <button class="boton boton-principal" type="submit">
                EMITIR VOTO →
            </button>
        </div>
    </form>

    <p class="aviso-secreto">
        🔒 Tu elección se almacena por separado de la huella de tu cédula.
    </p>

</main>

</body>
</html>
