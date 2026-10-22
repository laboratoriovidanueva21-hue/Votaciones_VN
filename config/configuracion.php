<?php
declare(strict_types=1);

/*
 * CONFIGURACIÓN DEL SISTEMA
 * Cambia estos datos antes de usarlo en una elección real.
 */
const NOMBRE_COLEGIO = 'Votaciones Vida Nueva';
const NOMBRE_LISTA_1 = 'Lista A';
const NOMBRE_LISTA_2 = 'Lista B';

/*
 * Clave privada para generar huellas de cédulas.
 * Debe permanecer igual durante toda la elección.
 * Si la cambias después de registrar votos, las cédulas usadas anteriormente
 * podrían no reconocerse como repetidas.
 */
const HASH_SECRET = 'CAMBIA-ESTA-CLAVE-POR-UNA-LARGA-Y-UNICA-ANTES-DE-USAR-EL-SISTEMA-2026';
const DURACION_PENDIENTE_SEGUNDOS = 900; // 15 minutos
const TAMANO_MAXIMO_PADRON_BYTES = 1000 * 1024 * 1024; // 1000 MB
