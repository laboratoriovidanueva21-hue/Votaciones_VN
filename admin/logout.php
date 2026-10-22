<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/seguridad.php';
unset($_SESSION['admin_id'], $_SESSION['admin_usuario']);
session_regenerate_id(true);
redirigir('login.php');
