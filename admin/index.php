<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/conexion.php';
admin_requerido();

function normalizar_campo_padron(string $campo): string {
    $campo = strtolower(trim($campo, " \t\n\r\0\x0B\xEF\xBB\xBF"));
    $campo = strtr($campo, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    return str_replace([' ', '_', '-'], '', $campo);
}

function es_campo_cedula(string $campo): bool {
    return in_array(normalizar_campo_padron($campo), [
        'cedula',
        'ceduladeidentidad',
        'identificacion',
        'numeroidentificacion',
        'documento',
        'numerodocumento',
        'numerodecedula',
        'numcedula',
    ], true);
}

function guardar_foto_lista(string $lista, array $archivo): string {
    $formatos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $directorio = __DIR__ . '/../imagenes';

    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo recibir la imagen. Revisa su tamaño.');
    }
    if (($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen no debe superar 5 MB.');
    }
    if (!is_uploaded_file((string)($archivo['tmp_name'] ?? ''))) {
        throw new RuntimeException('El archivo de imagen no es válido.');
    }

    $informacion = getimagesize((string)$archivo['tmp_name']);
    $tipo = $informacion['mime'] ?? '';
    if ($informacion === false || !isset($formatos[$tipo])) {
        throw new RuntimeException('Usa una imagen JPG, PNG o WEBP válida.');
    }

    $extension = $formatos[$tipo];
    $destino = $directorio . '/' . $lista . '.' . $extension;
    if (!move_uploaded_file((string)$archivo['tmp_name'], $destino)) {
        throw new RuntimeException('No se pudo guardar la imagen. Comprueba los permisos de la carpeta imagenes.');
    }

    foreach (['jpg', 'png', 'webp'] as $otraExtension) {
        $otraRuta = $directorio . '/' . $lista . '.' . $otraExtension;
        if ($otraExtension !== $extension && is_file($otraRuta) && !unlink($otraRuta)) {
            error_log('No se pudo retirar la foto anterior de ' . $lista . ': ' . $otraRuta);
        }
    }

    return $extension;
}

function foto_lista_url(string $lista): ?string {
    foreach (['jpg', 'png', 'webp'] as $extension) {
        $ruta = __DIR__ . '/../imagenes/' . $lista . '.' . $extension;
        if (is_file($ruta)) {
            return '../imagenes/' . $lista . '.' . $extension . '?v=' . filemtime($ruta);
        }
    }
    return null;
}

function validar_filas_padron(array $filas): array {
    $cedulas = [];
    $indiceCedula = null;
    $filaNumero = 0;

    foreach ($filas as $fila) {
        $filaNumero++;
        $fila = array_map(static fn($valor): string => trim((string)$valor), $fila);
        if (count(array_filter($fila, static fn(string $valor): bool => $valor !== '')) === 0) {
            continue;
        }

        if ($indiceCedula === null) {
            foreach ($fila as $indice => $valor) {
                if (es_campo_cedula($valor)) {
                    $indiceCedula = $indice;
                    continue 2;
                }
            }
            if (count($fila) === 1 && preg_match('/^[0-9]{10}$/', $fila[0])) {
                $indiceCedula = 0;
            } else {
                throw new RuntimeException('No se identificó una columna de cédula. Usa un encabezado como cedula, identificacion o documento, o carga solo una columna.');
            }
        } elseif ($filaNumero === 1 && es_campo_cedula($fila[$indiceCedula] ?? '')) {
            continue;
        }

        $cedula = $fila[$indiceCedula] ?? '';
        if (!preg_match('/^[0-9]{10}$/', $cedula)) {
            throw new RuntimeException('La columna de cédula contiene un valor inválido en la fila ' . $filaNumero . '. No se modificó el padrón.');
        }
        $cedulas[] = $cedula;
        if (count($cedulas) > 100000) {
            throw new RuntimeException('El archivo supera el máximo de 100.000 estudiantes.');
        }
    }

    return array_values(array_unique($cedulas));
}

function leer_csv_padron(string $ruta): array {
    $handle = fopen($ruta, 'rb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo leer el archivo.');
    }

    $primeraLinea = fgets($handle);
    if ($primeraLinea === false) {
        fclose($handle);
        throw new RuntimeException('El archivo está vacío.');
    }

    $delimitador = ',';
    $mayorConteo = 0;
    foreach ([',', ';', "\t"] as $candidato) {
        $conteo = substr_count($primeraLinea, $candidato);
        if ($conteo > $mayorConteo) {
            $mayorConteo = $conteo;
            $delimitador = $candidato;
        }
    }
    rewind($handle);

    $filas = [];
    while (($fila = fgetcsv($handle, 0, $delimitador)) !== false) {
        $filas[] = $fila;
        if (count($filas) > 100001) {
            fclose($handle);
            throw new RuntimeException('El archivo supera el máximo de 100.000 estudiantes.');
        }
    }
    fclose($handle);
    return validar_filas_padron($filas);
}

function leer_excel_padron(string $ruta): array {
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('La extensión ZIP de PHP no está habilitada para leer Excel.');
    }

    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) {
        throw new RuntimeException('El archivo Excel no es válido.');
    }
    $sheetIndex = $zip->locateName('xl/worksheets/sheet1.xml');
    if ($sheetIndex === false || $zip->statIndex($sheetIndex)['size'] > 20 * 1024 * 1024) {
        $zip->close();
        throw new RuntimeException('No se encontró una primera hoja válida en el archivo Excel.');
    }

    $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $sharedStrings = [];
    $sharedIndex = $zip->locateName('xl/sharedStrings.xml');
    if ($sharedIndex !== false) {
        if ($zip->statIndex($sharedIndex)['size'] > 10 * 1024 * 1024) {
            $zip->close();
            throw new RuntimeException('El contenido del Excel es demasiado grande.');
        }
        $xmlStrings = simplexml_load_string((string)$zip->getFromIndex($sharedIndex), 'SimpleXMLElement', LIBXML_NONET);
        if ($xmlStrings === false) {
            $zip->close();
            throw new RuntimeException('No se pudo leer el Excel.');
        }
        $xmlStrings->registerXPathNamespace('x', $namespace);
        foreach ($xmlStrings->xpath('//x:si') ?: [] as $item) {
            $item->registerXPathNamespace('x', $namespace);
            $textos = $item->xpath('.//x:t') ?: [];
            $sharedStrings[] = implode('', array_map(static fn($texto): string => (string)$texto, $textos));
        }
    }

    $xml = simplexml_load_string((string)$zip->getFromIndex($sheetIndex), 'SimpleXMLElement', LIBXML_NONET);
    $zip->close();
    if ($xml === false) {
        throw new RuntimeException('No se pudo leer la primera hoja del Excel.');
    }

    $filas = [];
    $xml->registerXPathNamespace('x', $namespace);
    foreach ($xml->xpath('//x:sheetData/x:row') ?: [] as $filaXml) {
        $filaXml->registerXPathNamespace('x', $namespace);
        $fila = [];
        foreach ($filaXml->xpath('./x:c') ?: [] as $celda) {
            $celda->registerXPathNamespace('x', $namespace);
            if (!preg_match('/^([A-Z]+)/', (string)$celda['r'], $coincidencia)) {
                continue;
            }
            $indice = 0;
            foreach (str_split($coincidencia[1]) as $letra) {
                $indice = $indice * 26 + ord($letra) - 64;
            }
            $indice--;
            $tipo = (string)$celda['t'];
            if ($tipo === 'inlineStr') {
                $textos = $celda->xpath('./x:is//x:t') ?: [];
                $valor = implode('', array_map(static fn($texto): string => (string)$texto, $textos));
            } else {
                $valores = $celda->xpath('./x:v') ?: [];
                $valor = isset($valores[0]) ? (string)$valores[0] : '';
                if ($tipo === 's') {
                    $valor = $sharedStrings[(int)$valor] ?? '';
                }
            }
            $fila[$indice] = $valor;
        }
        if ($fila !== []) {
            $filaCompleta = array_fill(0, max(array_keys($fila)) + 1, '');
            foreach ($fila as $indice => $valor) {
                $filaCompleta[$indice] = $valor;
            }
            $filas[] = $filaCompleta;
        }
        if (count($filas) > 100001) {
            throw new RuntimeException('El archivo supera el máximo de 100.000 estudiantes.');
        }
    }
    return validar_filas_padron($filas);
}

function leer_sqlite_padron(string $ruta, string $tabla, string $campo): array {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('La extensión PDO_SQLite de PHP no está habilitada.');
    }

    $db = new PDO('sqlite:' . $ruta, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA query_only = ON');
    $tablas = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
        ->fetchAll(PDO::FETCH_COLUMN);

    if ($tabla !== '' && !in_array($tabla, $tablas, true)) {
        throw new RuntimeException('La tabla indicada no existe en la base SQLite.');
    }

    if ($tabla === '' || $campo === '') {
        $candidatos = [];
        $tablasBuscar = $tabla === '' ? $tablas : [$tabla];
        foreach ($tablasBuscar as $nombreTabla) {
            $identificadorTabla = '"' . str_replace('"', '""', (string)$nombreTabla) . '"';
            foreach ($db->query('PRAGMA table_info(' . $identificadorTabla . ')')->fetchAll() as $columna) {
                if (($campo === '' && es_campo_cedula((string)$columna['name'])) ||
                    ($campo !== '' && (string)$columna['name'] === $campo)) {
                    $candidatos[] = [(string)$nombreTabla, (string)$columna['name']];
                }
            }
        }
        if (count($candidatos) !== 1) {
            throw new RuntimeException('La base SQLite contiene varias coincidencias posibles. Indica el nombre exacto de la tabla y el campo de cédula.');
        }
        [$tabla, $campo] = $candidatos[0];
    }
    $identificadorTabla = '"' . str_replace('"', '""', $tabla) . '"';
    $columnas = $db->query('PRAGMA table_info(' . $identificadorTabla . ')')->fetchAll();
    $nombresColumnas = array_column($columnas, 'name');
    if (!in_array($campo, $nombresColumnas, true)) {
        throw new RuntimeException('El campo indicado no existe en la tabla seleccionada.');
    }

    $identificadorCampo = '"' . str_replace('"', '""', $campo) . '"';
    $valores = $db->query('SELECT ' . $identificadorCampo . ' FROM ' . $identificadorTabla . ' LIMIT 100001')
        ->fetchAll(PDO::FETCH_COLUMN);
    if (count($valores) > 100000) {
        throw new RuntimeException('La tabla supera el máximo de 100.000 estudiantes.');
    }
    return validar_filas_padron(array_map(static fn($valor): array => [(string)$valor], $valores));
}

function iterar_sentencias_sql($handle): Generator {
    $sentencia = '';
    $comilla = '';
    $escapado = false;
    $comentarioLinea = false;
    $comentarioBloque = false;
    $anterior = '';
    $tamanioMaximoSentencia = 128 * 1024 * 1024;

    while (($linea = fgets($handle)) !== false) {
        $longitud = strlen($linea);
        for ($i = 0; $i < $longitud; $i++) {
            $caracter = $linea[$i];
            $siguiente = $i + 1 < $longitud ? $linea[$i + 1] : '';

            if ($comentarioLinea) {
                if ($caracter === "\n") {
                    $comentarioLinea = false;
                    $sentencia .= "\n";
                }
                continue;
            }
            if ($comentarioBloque) {
                if ($anterior === '*' && $caracter === '/') {
                    $comentarioBloque = false;
                    $anterior = '';
                } else {
                    $anterior = $caracter;
                }
                continue;
            }
            if ($comilla !== '') {
                $sentencia .= $caracter;
                if ($escapado) {
                    $escapado = false;
                } elseif ($caracter === '\\' && $comilla !== '`') {
                    $escapado = true;
                } elseif ($caracter === $comilla) {
                    if ($siguiente === $comilla) {
                        $sentencia .= $siguiente;
                        $i++;
                    } else {
                        $comilla = '';
                    }
                }
            } elseif (($caracter === '-' && $siguiente === '-' &&
                    ($i + 2 >= $longitud || ctype_space($linea[$i + 2]))) ||
                $caracter === '#') {
                $comentarioLinea = true;
                if ($caracter === '-') {
                    $i++;
                }
            } elseif ($caracter === '/' && $siguiente === '*') {
                $comentarioBloque = true;
                $anterior = '';
                $i++;
            } elseif ($caracter === "'" || $caracter === '"' || $caracter === '`') {
                $comilla = $caracter;
                $sentencia .= $caracter;
            } elseif ($caracter === ';') {
                if (trim($sentencia) !== '') {
                    yield $sentencia;
                }
                $sentencia = '';
            } else {
                $sentencia .= $caracter;
            }

            if (strlen($sentencia) > $tamanioMaximoSentencia) {
                throw new RuntimeException('Una sentencia del SQL supera 128 MB. Exporta la tabla con bloques de inserciones más pequeños.');
            }
        }
    }

    if (trim($sentencia) !== '') {
        yield $sentencia;
    }
}

function leer_literal_sql(string $valor): string {
    $valor = trim($valor);
    if (strlen($valor) >= 2 && $valor[0] === "'" && substr($valor, -1) === "'") {
        $contenido = substr($valor, 1, -1);
        return strtr(str_replace(["''", '\'', '"'], ["'", "'", '"'], $contenido), [
            '\\0' => "\0",
            '\\b' => "\x08",
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\Z' => "\x1a",
            '\\\\' => '\\',
        ]);
    }
    if (strcasecmp($valor, 'NULL') === 0) {
        return '';
    }
    return trim($valor, '`');
}

function analizar_tupla_sql(string $texto, int &$posicion): array {
    $longitud = strlen($texto);
    while ($posicion < $longitud && ctype_space($texto[$posicion])) {
        $posicion++;
    }
    if ($posicion >= $longitud || $texto[$posicion] !== '(') {
        throw new RuntimeException('El SQL no tiene un formato de INSERT compatible.');
    }
    $posicion++;
    $campos = [];
    $valor = '';
    $comilla = '';
    $escapado = false;
    $profundidad = 0;

    while ($posicion < $longitud) {
        $caracter = $texto[$posicion++];
        if ($comilla !== '') {
            $valor .= $caracter;
            if ($escapado) {
                $escapado = false;
            } elseif ($caracter === '\\' && $comilla !== '`') {
                $escapado = true;
            } elseif ($caracter === $comilla) {
                if ($posicion < $longitud && $texto[$posicion] === $comilla) {
                    $valor .= $texto[$posicion++];
                } else {
                    $comilla = '';
                }
            }
            continue;
        }
        if ($caracter === "'" || $caracter === '"' || $caracter === '`') {
            $comilla = $caracter;
            $valor .= $caracter;
        } elseif ($caracter === '(') {
            $profundidad++;
            $valor .= $caracter;
        } elseif ($caracter === ')' && $profundidad > 0) {
            $profundidad--;
            $valor .= $caracter;
        } elseif ($caracter === ',' && $profundidad === 0) {
            $campos[] = trim($valor);
            $valor = '';
        } elseif ($caracter === ')' && $profundidad === 0) {
            $campos[] = trim($valor);
            return $campos;
        } else {
            $valor .= $caracter;
        }
    }
    throw new RuntimeException('El SQL contiene una fila INSERT incompleta.');
}

function leer_sql_padron(string $ruta, string $tabla, string $campo): array {
    if ($tabla === '' || $campo === '') {
        throw new RuntimeException('Para leer un SQL indica el nombre exacto de la tabla de estudiantes y su campo de cédula.');
    }

    $handle = fopen($ruta, 'rb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo leer el archivo SQL.');
    }

    $tablaPatron = '(?:`[^`]+`|"[^"]+"|[a-zA-Z0-9_$]+)';
    $tablaSql = preg_quote($tabla, '/');
    $identificadorTabla = '(?:' . $tablaPatron . '\s*\.\s*)?' .
        '(?:`' . $tablaSql . '`|"' . $tablaSql . '"|' . $tablaSql . ')';
    $patron = '/^\s*INSERT\s+(?:IGNORE\s+)?INTO\s+' . $identificadorTabla .
        '\s*\((.*?)\)\s*VALUES\s*(.*)$/is';
    $insertEncontrado = false;
    $cedulas = [];

    try {
        foreach (iterar_sentencias_sql($handle) as $sentencia) {
            if (!preg_match($patron, $sentencia, $partes)) {
                continue;
            }
            $columnas = array_map(
                static fn(string $columna): string => trim($columna, " \t\n\r\0\x0B`\""),
                str_getcsv($partes[1], ',', '"', '\\')
            );
            $indiceCedula = array_search($campo, $columnas, true);
            if ($indiceCedula === false) {
                continue;
            }

            $insertEncontrado = true;
            $valores = $partes[2];
            $posicion = 0;
            $longitud = strlen($valores);
            while ($posicion < $longitud) {
                while ($posicion < $longitud &&
                    (ctype_space($valores[$posicion]) || $valores[$posicion] === ',')) {
                    $posicion++;
                }
                if ($posicion >= $longitud) {
                    break;
                }
                $fila = analizar_tupla_sql($valores, $posicion);
                if (!array_key_exists($indiceCedula, $fila)) {
                    throw new RuntimeException('La fila SQL no coincide con las columnas declaradas.');
                }
                $cedula = leer_literal_sql($fila[$indiceCedula]);
                if (!preg_match('/^[0-9]{10}$/', $cedula)) {
                    throw new RuntimeException('La columna SQL indicada contiene una cédula inválida. No se modificó el padrón.');
                }
                $cedulas[] = $cedula;
                if (count($cedulas) > 100000) {
                    throw new RuntimeException('El SQL supera el máximo de 100.000 estudiantes.');
                }
            }
        }
    } finally {
        fclose($handle);
    }

    if (!$insertEncontrado) {
        throw new RuntimeException('No se encontró un INSERT compatible. Indica la tabla y columna exactas, y verifica que el respaldo incluya INSERT INTO con nombres de columnas.');
    }
    return array_values(array_unique($cedulas));
}

$error = '';
$mensaje = (string)($_SESSION['mensaje_padron'] ?? '');
unset($_SESSION['mensaje_padron']);
$entrada = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $accionFoto = in_array($_POST['accion'] ?? '', ['foto_lista1', 'foto_lista2', 'foto_nulo'], true);
    if ($accionFoto) {
        $lista = [
            'foto_lista1' => 'lista1',
            'foto_lista2' => 'lista2',
            'foto_nulo' => 'nulo',
        ][(string)$_POST['accion']];
        try {
            guardar_foto_lista($lista, $_FILES['foto_lista'] ?? []);
            $nombreFoto = [
                'lista1' => NOMBRE_LISTA_1,
                'lista2' => NOMBRE_LISTA_2,
                'nulo' => 'Voto nulo',
            ][$lista];
            $_SESSION['mensaje_padron'] = 'La foto de ' . $nombreFoto . ' se actualizó correctamente.';
            redirigir('index.php');
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }

    if (!$accionFoto) {
    if (($_POST['accion'] ?? '') === 'eliminar_padron') {
        try {
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM estudiantes_autorizados');
            $pdo->commit();
            $_SESSION['mensaje_padron'] = 'Se eliminó el padrón autorizado. Los votos ya registrados se conservaron.';
            redirigir('index.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error eliminando el padrón de estudiantes: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo eliminar el padrón de estudiantes.');
        }
    }

    $entrada = (string)($_POST['cedulas'] ?? '');
    $archivo = $_FILES['archivo_padron'] ?? null;
    $hayArchivo = is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $cedulas = [];

    if ($hayArchivo) {
        if (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $error = 'PHP/XAMPP rechazó el archivo por su tamaño. Aumenta upload_max_filesize y post_max_size en php.ini y reinicia Apache.';
        } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
            $error = 'No se pudo recibir el archivo. Revisa su tamaño e inténtalo nuevamente.';
        } elseif (($archivo['size'] ?? 0) > TAMANO_MAXIMO_PADRON_BYTES) {
            $error = 'El archivo supera el límite de 1000 MB.';
        } elseif (!in_array(strtolower(pathinfo((string)$archivo['name'], PATHINFO_EXTENSION)), ['csv', 'xlsx', 'db', 'sqlite', 'sql'], true)) {
            $error = 'Formato no compatible. Sube CSV, Excel .xlsx, SQLite .db/.sqlite o un respaldo .sql compatible.';
        } elseif (!is_uploaded_file((string)$archivo['tmp_name'])) {
            $error = 'El archivo cargado no es válido.';
        } else {
            try {
                $extension = strtolower(pathinfo((string)$archivo['name'], PATHINFO_EXTENSION));
                if ($extension === 'csv') {
                    $cedulas = leer_csv_padron((string)$archivo['tmp_name']);
                } elseif ($extension === 'xlsx') {
                    $cedulas = leer_excel_padron((string)$archivo['tmp_name']);
                } elseif ($extension === 'sql') {
                    $cedulas = leer_sql_padron(
                        (string)$archivo['tmp_name'],
                        trim((string)($_POST['tabla_sqlite'] ?? '')),
                        trim((string)($_POST['campo_sqlite'] ?? ''))
                    );
                } else {
                    $cedulas = leer_sqlite_padron(
                        (string)$archivo['tmp_name'],
                        trim((string)($_POST['tabla_sqlite'] ?? '')),
                        trim((string)($_POST['campo_sqlite'] ?? ''))
                    );
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            } catch (PDOException $e) {
                error_log('Error leyendo base SQLite del padrón: ' . $e->getMessage());
                $error = 'No se pudo leer la base SQLite. Verifica que el archivo y la tabla sean válidos.';
            }
        }
    } else {
        $cedulas = preg_split('/[\r\n,;]+/', $entrada, -1, PREG_SPLIT_NO_EMPTY);
        $cedulas = array_values(array_filter(
            array_map('trim', $cedulas),
            static fn(string $cedula): bool => $cedula !== ''
        ));

        foreach ($cedulas as $cedula) {
            if (!preg_match('/^[0-9]{10}$/', $cedula)) {
                $error = 'Todas las cédulas deben tener exactamente 10 dígitos. No se modificó el padrón.';
                break;
            }
        }
    }

    $cedulas = array_values(array_unique($cedulas));
    if ($error === '' && $cedulas === []) {
        $error = 'Pega cédulas o selecciona un CSV con al menos un estudiante.';
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM estudiantes_autorizados');
            $insertar = $pdo->prepare(
                'INSERT INTO estudiantes_autorizados (hash_cedula) VALUES (?)'
            );

            foreach ($cedulas as $cedula) {
                $insertar->execute([huella_cedula($cedula)]);
            }

            $pdo->commit();
            $_SESSION['mensaje_padron'] = 'Padrón actualizado: ' . count($cedulas) . ' estudiantes autorizados.';
            redirigir('index.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error actualizando el padrón de estudiantes: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar el padrón de estudiantes.');
        }
    }
    }
}

$datos = ['lista1' => 0, 'lista2' => 0, 'nulo' => 0];
$stmt = $pdo->query('SELECT opcion, COUNT(*) AS cantidad FROM votos GROUP BY opcion');
foreach ($stmt->fetchAll() as $fila) {
    $datos[$fila['opcion']] = (int)$fila['cantidad'];
}
$total = array_sum($datos);
$porcentaje = static fn(int $n): string => $total > 0 ? number_format(($n / $total) * 100, 1) . '%' : '0%';
$cantidadEstudiantes = (int)$pdo->query('SELECT COUNT(*) FROM estudiantes_autorizados')->fetchColumn();
$fotoLista1 = foto_lista_url('lista1');
$fotoLista2 = foto_lista_url('lista2');
$fotoNulo = foto_lista_url('nulo');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Votaciones Vida Nueva | Resultados</title><link rel="icon" type="image/webp" href="../imagenes/OIP.webp"><link rel="stylesheet" href="../assets/estilos.css"></head>
<body class="fondo">
<header class="barra"><a class="marca" href="../index.php"><img src="../imagenes/OIP.webp" alt="Escudo del colegio" style="width:55px;height:55px;object-fit:contain;flex-shrink:0"><span><?= e(NOMBRE_COLEGIO) ?><small>PANEL DE RESULTADOS</small></span></a><div class="barra-derecha"><span class="usuario-admin">👤 <?= e((string)($_SESSION['admin_usuario'] ?? 'Administrador')) ?></span><a class="enlace-admin" href="logout.php">Cerrar sesión</a></div></header>
<main class="contenido">
  <div class="titulo-resultados"><div><span class="etiqueta">RESUMEN ELECTORAL</span><h1>Resultados de la votación</h1><p class="muted">Los resultados se actualizan automáticamente cada 10 segundos.</p></div><a class="boton boton-secundario" href="index.php">↻ Actualizar ahora</a></div>
  <section class="tarjetas-resultados">
    <article class="tarjeta-resultado total"><span class="resultado-icono">🗳️</span><p>Total de votos emitidos</p><strong><?= $total ?></strong><small>Votos registrados</small></article>
    <article class="tarjeta-resultado azul"><span class="resultado-icono">1</span><p><?= e(NOMBRE_LISTA_1) ?></p><strong><?= $datos['lista1'] ?></strong><small><?= e($porcentaje($datos['lista1'])) ?> del total</small><div class="barra-progreso"><span style="width:<?= $total ? ($datos['lista1'] / $total * 100) : 0 ?>%"></span></div></article>
    <article class="tarjeta-resultado violeta"><span class="resultado-icono">2</span><p><?= e(NOMBRE_LISTA_2) ?></p><strong><?= $datos['lista2'] ?></strong><small><?= e($porcentaje($datos['lista2'])) ?> del total</small><div class="barra-progreso"><span style="width:<?= $total ? ($datos['lista2'] / $total * 100) : 0 ?>%"></span></div></article>
    <article class="tarjeta-resultado gris"><span class="resultado-icono">Ø</span><p>Votos nulos</p><strong><?= $datos['nulo'] ?></strong><small><?= e($porcentaje($datos['nulo'])) ?> del total</small><div class="barra-progreso"><span style="width:<?= $total ? ($datos['nulo'] / $total * 100) : 0 ?>%"></span></div></article>
  </section>
  <section class="panel tabla-panel"><div class="tabla-titulo"><div><h2>Detalle de resultados</h2><p class="muted">No se muestra ni se almacena la cédula original en este panel.</p></div><span class="estado-en-vivo"><i></i> EN VIVO</span></div>
    <div class="tabla-titulo"><div><h2>Fotos de las opciones</h2><p class="muted">Sube una imagen para Lista A, Lista B o voto nulo. Aparecerá encima de la opción al votar.</p></div></div>
    <?php foreach ([
        ['id' => 'lista1', 'nombre' => NOMBRE_LISTA_1, 'foto' => $fotoLista1],
        ['id' => 'lista2', 'nombre' => NOMBRE_LISTA_2, 'foto' => $fotoLista2],
        ['id' => 'nulo', 'nombre' => 'Voto nulo', 'foto' => $fotoNulo],
    ] as $listaFoto): ?>
      <div style="display:flex;align-items:center;gap:20px;padding:14px 0;border-bottom:1px solid #e4e9f3">
        <?php if ($listaFoto['foto'] !== null): ?>
          <img src="<?= e($listaFoto['foto']) ?>" alt="Foto de <?= e($listaFoto['nombre']) ?>" style="width:120px;height:90px;object-fit:contain;border-radius:10px;background:#f4f6fc">
        <?php else: ?>
          <div style="width:120px;height:90px;display:grid;place-items:center;border:1px dashed #aab5c5;border-radius:10px;color:#6f7b91;font-size:12px">Sin foto</div>
        <?php endif; ?>
        <div style="flex:1">
          <h3><?= e($listaFoto['nombre']) ?></h3>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="foto_<?= e($listaFoto['id']) ?>">
            <label for="foto_<?= e($listaFoto['id']) ?>">Imagen JPG, PNG o WEBP (máximo 5 MB)</label>
            <input id="foto_<?= e($listaFoto['id']) ?>" name="foto_lista" type="file" accept="image/jpeg,image/png,image/webp" required>
            <button class="boton boton-principal" type="submit">GUARDAR FOTO</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
  <section class="panel tabla-panel"><div class="tabla-titulo"><div><h2>Detalle de resultados</h2><p class="muted">No se muestra ni se almacena la cédula original en este panel.</p></div><span class="estado-en-vivo"><i></i> EN VIVO</span></div>
  <div class="tabla-contenedor"><table><thead><tr><th>Opción</th><th>Votos</th><th>Porcentaje</th></tr></thead><tbody>
  <tr><td><?= e(NOMBRE_LISTA_1) ?></td><td><?= $datos['lista1'] ?></td><td><?= e($porcentaje($datos['lista1'])) ?></td></tr>
  <tr><td><?= e(NOMBRE_LISTA_2) ?></td><td><?= $datos['lista2'] ?></td><td><?= e($porcentaje($datos['lista2'])) ?></td></tr>
  <tr><td>Voto nulo</td><td><?= $datos['nulo'] ?></td><td><?= e($porcentaje($datos['nulo'])) ?></td></tr>
  <tr class="fila-total"><td>Total</td><td><?= $total ?></td><td><?= $total > 0 ? '100%' : '0%' ?></td></tr>
  </tbody></table></div></section>
  <section class="panel tabla-panel">
    <div class="tabla-titulo"><div><h2>Padrón de estudiantes</h2><p class="muted"><?= $cantidadEstudiantes ?> estudiantes autorizados. Al cargar una lista, se reemplaza el padrón actual.</p></div></div>
    <?php if ($error !== ''): ?><div class="alerta" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($mensaje !== ''): ?><div class="alerta-neutra" role="status"><?= e($mensaje) ?></div><?php endif; ?>
    <form id="form-padron-sqlite" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="accion" value="actualizar_padron">
      <label for="cedulas">Cédulas autorizadas (una por línea; también se acepta coma o punto y coma)</label>
      <textarea id="cedulas" name="cedulas" rows="7" style="width:100%;padding:12px;border:1px solid #dce3f0;border-radius:11px;font:inherit" placeholder="1725473649&#10;0923456789"><?= e($entrada) ?></textarea>
      <p class="muted">CSV y Excel: usa una columna de cédulas o una tabla con un encabezado como cedula. SQLite: se puede detectar automáticamente. SQL: indica la tabla y el campo exactos; el respaldo debe incluir INSERT con nombres de columnas. Solo se extraerán cédulas; no se ejecutan comandos del archivo.</p>
      <label for="archivo_padron">CSV, Excel (.xlsx), SQLite (.db/.sqlite) o respaldo SQL (.sql), máximo 1000 MB</label>
      <input id="archivo_padron" name="archivo_padron" type="file" accept=".csv,.xlsx,.db,.sqlite,.sql,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.sqlite3">
      <label for="tabla_sqlite">Nombre de la tabla para SQLite o SQL</label>
      <input id="tabla_sqlite" name="tabla_sqlite" type="text" maxlength="128" placeholder="Ej. estudiantes">
      <label for="campo_sqlite">Nombre del campo de cédula para SQLite o SQL</label>
      <input id="campo_sqlite" name="campo_sqlite" type="text" maxlength="128" placeholder="Ej. cedula">
      <button class="boton boton-principal" type="submit">REEMPLAZAR PADRÓN</button>
    </form>
    <form method="post" onsubmit="return confirm('¿Eliminar todas las cédulas autorizadas? Los votos ya registrados se conservarán.');" style="margin-top:14px">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="accion" value="eliminar_padron">
      <button class="boton boton-secundario" type="submit">ELIMINAR PADRÓN</button>
    </form>
  </section>
  <p class="nota">El sistema almacena huellas protegidas de las cédulas autorizadas, no la cédula original. El padrón no se relaciona con la opción de voto.</p>
</main>
<script>
window.setInterval(function () {
  const padron = document.getElementById('cedulas');
  const formularioActivo = document.activeElement && document.activeElement.form;
  const hayArchivoSeleccionado = Array.from(document.querySelectorAll('input[type="file"]'))
    .some(function (input) { return input.files.length > 0; });
  if ((!padron || (document.activeElement !== padron && padron.value.trim() === '')) &&
      !formularioActivo &&
      !hayArchivoSeleccionado) {
    window.location.reload();
  }
}, 10000);
</script>
</body></html>
