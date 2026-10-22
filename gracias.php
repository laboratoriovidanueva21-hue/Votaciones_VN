
<?php


require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/configuracion.php';

if (empty($_SESSION['voto_completado'])) {
    redirigir('index.php');
}

unset($_SESSION['voto_completado']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="5;url=index.php">

    <title>Votaciones Vida Nueva | Voto registrado</title>
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

        .barra {
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
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

        .pantalla-centro {
            min-height: calc(100vh - 82px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 16px;
        }

        .panel-exito {
            width: 100%;
            max-width: 560px;
            padding: 38px 32px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            background: rgba(16, 23, 32, 0.42);
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.24);
            backdrop-filter: blur(8px);
            text-align: center;
            animation: aparecer 0.55s ease both;
        }

        .exito-icono {
            width: 94px;
            height: 94px;
            display: grid;
            place-items: center;
            margin: 0 auto 23px;
            border: 7px solid rgba(216, 237, 212, 0.55);
            border-radius: 50%;
            background: rgba(23, 107, 58, 0.88);
            color: var(--amarillo);
            font-size: 49px;
            font-weight: 900;
            box-shadow: 0 8px 20px rgba(23, 107, 58, 0.16);
            animation: aparecerIcono 0.65s ease both;
        }

        .etiqueta {
            display: inline-block;
            margin-bottom: 15px;
            padding: 9px 15px;
            border: 1px solid rgba(242, 205, 54, 0.55);
            border-radius: 30px;
            background: rgba(242, 205, 54, 0.16);
            color: #fff4bb;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 1.3px;
        }

        .panel-exito h1 {
            margin: 0;
            color: var(--blanco);
            font-size: clamp(27px, 5vw, 36px);
            font-weight: 900;
            letter-spacing: -0.8px;
            line-height: 1.2;
        }

        .muted {
            max-width: 410px;
            margin: 16px auto 26px;
            color: rgba(255, 255, 255, 0.84);
            font-size: 15px;
            line-height: 1.8;
        }

        .mensaje-final {
            margin-bottom: 25px;
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 13px;
            background: rgba(255, 255, 255, 0.12);
            color: var(--blanco);
            font-size: 13px;
            line-height: 1.7;
        }

        .boton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 50px;
            padding: 14px 22px;
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
            background: var(--amarillo);
            color: var(--verde-oscuro);
            box-shadow: 0 5px 13px rgba(23, 107, 58, 0.2);
        }

        .boton-principal:hover {
            transform: translateY(-2px);
            background: var(--amarillo-hover);
            box-shadow: 0 8px 18px rgba(23, 107, 58, 0.24);
        }

        .boton-ancho {
            width: 100%;
        }

        @keyframes aparecer {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes aparecerIcono {
            from {
                opacity: 0;
                transform: scale(0.75);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
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

            .pantalla-centro {
                min-height: calc(100vh - 70px);
                padding: 25px 16px;
            }

            .panel-exito {
                padding: 30px 20px;
                border-radius: 20px;
            }

            .exito-icono {
                width: 78px;
                height: 78px;
                font-size: 40px;
            }

            .muted {
                font-size: 14px;
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
    <a class="marca" href="index.php">
        <img src="imagenes/OIP.webp"
     alt="Escudo del colegio"
     class="escudo-colegio">
        <span>
            <?= e(NOMBRE_COLEGIO) ?>
            <small>TECNICA CRISTIANA VIDA NUEVA ⚙️</small>
        </span>
    </a>
</header>

<main class="pantalla-centro">

    <section class="panel panel-exito">

        <div class="exito-icono" aria-hidden="true">✓</div>

        <span class="etiqueta">VOTO REGISTRADO</span>

        <h1>¡Gracias por participar!</h1>

        <p class="muted">
            Tu voto se registró correctamente.
            Tu cédula no podrá utilizarse para votar nuevamente.
        </p>

        <div class="mensaje-final">
            <strong>¡Tu participación cuenta!</strong><br>
            Gracias por formar parte de las elecciones estudiantiles.
        </div>

        <p class="muted">Volverás al inicio automáticamente en 5 segundos.</p>

        <a
            class="boton boton-principal boton-ancho"
            href="index.php"
        >
            VOLVER AL INICIO →
        </a>

    </section>

</main>

</body>
</html>
