<?php
// admin/comercial/comisiones/guardar.php - Procesar subida de comisiones
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

// Admin: acceso total
if ($rol_usuario == 1) {
    // Tiene acceso
} 
// Supervisor: verificar permiso
elseif ($rol_usuario == 2) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as tiene_permiso 
        FROM permisos_usuarios pu
        JOIN modulos m ON pu.modulo_id = m.id
        WHERE pu.usuario_id = ? AND m.nombre = 'comercial' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para subir comisiones";
        header('Location: ../../index.php');
        exit();
    }
} 
// Usuario normal: sin acceso
else {
    header('Location: ../../index.php');
    exit();
}

// ============================================
// CARGAR PHPSPREADSHEET
// ============================================
require_once '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ============================================
// VERIFICAR QUE ES POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "❌ Método no permitido";
    header('Location: index.php');
    exit();
}

// ============================================
// CONFIGURACIÓN DE ARCHIVOS
// ============================================
$extensiones_permitidas = ['xls', 'xlsx'];
$max_size = 6 * 1024 * 1024; // 6MB

// ============================================
// MESES
// ============================================
$meses = [
    1 => 'enero',
    2 => 'febrero',
    3 => 'marzo',
    4 => 'abril',
    5 => 'mayo',
    6 => 'junio',
    7 => 'julio',
    8 => 'agosto',
    9 => 'septiembre',
    10 => 'octubre',
    11 => 'noviembre',
    12 => 'diciembre'
];

// ============================================
// TIPOS DE COMISIONES PERMITIDOS
// ============================================
$tipos_permitidos = ['matriculas', 'usados', 'financieras', 'accesorios', 'incentivos'];

// ============================================
// CAPTURAR DATOS DEL POST
// ============================================
$tipo = trim($_POST['tipo'] ?? '');
$anio = intval($_POST['año'] ?? 0);
$mes = intval($_POST['mes'] ?? 0);

// ============================================
// VALIDACIONES
// ============================================

// 1. Validar tipo
if (empty($tipo) || !in_array($tipo, $tipos_permitidos)) {
    $_SESSION['error'] = "❌ Tipo de comisión no válido";
    header('Location: index.php');
    exit();
}

// 2. Validar año
if ($anio < 2026 || $anio > intval(date('Y'))) {
    $_SESSION['error'] = "❌ Año no válido";
    header('Location: index.php');
    exit();
}

// 3. Validar mes
if ($mes < 1 || $mes > 12) {
    $_SESSION['error'] = "❌ Mes no válido";
    header('Location: index.php');
    exit();
}

// 4. Validar archivo
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "❌ Debes seleccionar un archivo Excel";
    header('Location: index.php');
    exit();
}

$archivo = $_FILES['archivo'];
$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$tamano = $archivo['size'];

// 5. Validar extensión
if (!in_array($extension, $extensiones_permitidas)) {
    $_SESSION['error'] = "❌ Solo se permiten archivos XLS o XLSX";
    header('Location: index.php');
    exit();
}

// 6. Validar tamaño
if ($tamano > $max_size) {
    $_SESSION['error'] = "❌ El archivo no puede superar los 6MB";
    header('Location: index.php');
    exit();
}

// ============================================
// CREAR CARPETA DE DESTINO
// ============================================
$nombre_mes = $meses[$mes];
$carpeta_destino = '../../../uploads/comisiones/' . $anio . '/' . $nombre_mes . '/';

if (!file_exists($carpeta_destino)) {
    mkdir($carpeta_destino, 0777, true);
}

// ============================================
// GENERAR NOMBRE ÚNICO DEL ARCHIVO
// ============================================
$nombre_archivo = $tipo . '_' . $anio . '_' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '_' . time() . '.' . $extension;
$ruta_destino = $carpeta_destino . $nombre_archivo;

// ============================================
// MOVER ARCHIVO
// ============================================
if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
    $_SESSION['error'] = "❌ Error al subir el archivo";
    header('Location: index.php');
    exit();
}

// ============================================
// LEER EXCEL Y GUARDAR DATOS
// ============================================
try {
    $spreadsheet = IOFactory::load($ruta_destino);
    $hoja = $spreadsheet->getActiveSheet();
    $filas = $hoja->toArray();
    
    // La primera fila son los títulos
    $titulos = array_shift($filas);
    
    // Mapear las columnas del Excel
    $columnas = [];
    foreach ($titulos as $index => $titulo) {
        $columnas[strtolower(trim($titulo))] = $index;
    }
    
    // ============================================
    // CONFIGURACIÓN POR TIPO DE COMISIÓN
    // ============================================
    if ($tipo == 'matriculas') {
        $tabla = 'com_matriculas';
        $columnas_requeridas = [
            'fecha_comision',
            'cod_vendedor',
            'cliente',
            'matriculados',
            'modelo',
            'vin',
            'valor_venta',
            'comision_real',
            'bono'
        ];
    } elseif ($tipo == 'usados') {
        $tabla = 'com_usados';
        $columnas_requeridas = [
            'fecha_comision',
            'cod_vendedor',
            'cliente',
            'vin',
            'modelo',
            'placa',
            'valor_venta',
            'comision_real',
            'bono'
        ];
    } elseif ($tipo == 'accesorios') {
        $tabla = 'com_accesorios';
        $columnas_requeridas = [
            'fecha_comision',
            'cod_vendedor',
            'modelo',
            'cliente',
            'base',
            'comision'
        ];
    } elseif ($tipo == 'incentivos') {
        $tabla = 'com_incentivos';
        $columnas_requeridas = [
            'fecha_comision',
            'cod_vendedor',
            'observacion',
            'comision_real'
        ];
    } elseif ($tipo == 'financieras') {
        $tabla = 'com_financieras';
        $columnas_requeridas = [
            'fecha_comision',
            'cod_vendedor',
            'concepto',
            'vehiculos_vendidos',
            'comision_real'
        ];
    } else {
        throw new Exception("Tipo de comisión no configurado: $tipo");
    }
    
    // Verificar que las columnas necesarias existan
    foreach ($columnas_requeridas as $col) {
        if (!isset($columnas[$col])) {
            throw new Exception("La columna '$col' no se encontró en el Excel. Columnas encontradas: " . implode(', ', array_keys($columnas)));
        }
    }
    
    // ============================================
    // PREPARAR INSERT SEGÚN EL TIPO
    // ============================================
    if ($tipo == 'matriculas') {
        $stmt = $pdo->prepare("
            INSERT INTO com_matriculas 
            (fecha_comision, cod_vendedor, cliente, matriculados, modelo, vin, valor_venta, comision_real, bono, archivo_origen) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    } elseif ($tipo == 'usados') {
        $stmt = $pdo->prepare("
            INSERT INTO com_usados 
            (fecha_comision, cod_vendedor, cliente, vin, modelo, placa, valor_venta, comision_real, bono, archivo_origen) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    } elseif ($tipo == 'accesorios') {
        $stmt = $pdo->prepare("
            INSERT INTO com_accesorios 
            (fecha_comision, cod_vendedor, modelo, cliente, base, comision_real, archivo_origen) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
    } elseif ($tipo == 'incentivos') {
        $stmt = $pdo->prepare("
            INSERT INTO com_incentivos 
            (fecha_comision, cod_vendedor, observacion, comision_real, archivo_origen) 
            VALUES (?, ?, ?, ?, ?)
        ");
    } elseif ($tipo == 'financieras') {
        $stmt = $pdo->prepare("
            INSERT INTO com_financieras 
            (fecha_comision, cod_vendedor, concepto, vehiculos_vendidos, comision_real, archivo_origen) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
    }
    
    // Contar registros insertados
    $insertados = 0;
    $errores = 0;
    
    // Recorrer filas
    foreach ($filas as $fila) {
        // Saltar filas vacías
        if (empty($fila[$columnas['cod_vendedor']])) {
            continue;
        }
        
        try {
            // Procesar fecha (puede venir como número serial de Excel o como texto)
            $fecha_valor = $fila[$columnas['fecha_comision']];
            if (is_numeric($fecha_valor)) {
                // Convertir número serial de Excel a fecha
                $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fecha_valor);
                $fecha_comision = $fecha->format('Y-m-d');
            } else {
                // Intentar parsear como texto
                $fecha_comision = date('Y-m-d', strtotime($fecha_valor));
            }
            
            // ============================================
            // PROCESAR VALORES SEGÚN EL TIPO
            // ============================================
            if ($tipo == 'accesorios') {
                $base = limpiarNumero($fila[$columnas['base']] ?? 0);
                $comision_real = limpiarNumero($fila[$columnas['comision']] ?? 0);
            } elseif ($tipo == 'incentivos') {
                $comision_real = limpiarNumero($fila[$columnas['comision_real']] ?? 0);
            } elseif ($tipo == 'financieras') {
                $vehiculos_vendidos = intval($fila[$columnas['vehiculos_vendidos']] ?? 0);
                $comision_real = limpiarNumero($fila[$columnas['comision_real']] ?? 0);
            } else {
                $valor_venta = limpiarNumero($fila[$columnas['valor_venta']] ?? 0);
                $comision_real = limpiarNumero($fila[$columnas['comision_real']] ?? 0);
                $bono = limpiarNumero($fila[$columnas['bono']] ?? 0);
            }
            
            // ============================================
            // INSERTAR SEGÚN EL TIPO
            // ============================================
            if ($tipo == 'matriculas') {
                $stmt->execute([
                    $fecha_comision,
                    trim($fila[$columnas['cod_vendedor']]),
                    trim($fila[$columnas['cliente']] ?? ''),
                    trim($fila[$columnas['matriculados']] ?? ''),
                    trim($fila[$columnas['modelo']] ?? ''),
                    trim($fila[$columnas['vin']] ?? ''),
                    $valor_venta,
                    $comision_real,
                    $bono,
                    $nombre_archivo
                ]);
            } elseif ($tipo == 'usados') {
                $stmt->execute([
                    $fecha_comision,
                    trim($fila[$columnas['cod_vendedor']]),
                    trim($fila[$columnas['cliente']] ?? ''),
                    trim($fila[$columnas['vin']] ?? ''),
                    trim($fila[$columnas['modelo']] ?? ''),
                    trim($fila[$columnas['placa']] ?? ''),
                    $valor_venta,
                    $comision_real,
                    $bono,
                    $nombre_archivo
                ]);
            } elseif ($tipo == 'accesorios') {
                $stmt->execute([
                    $fecha_comision,
                    trim($fila[$columnas['cod_vendedor']]),
                    trim($fila[$columnas['modelo']] ?? ''),
                    trim($fila[$columnas['cliente']] ?? ''),
                    $base,
                    $comision_real,
                    $nombre_archivo
                ]);
            } elseif ($tipo == 'incentivos') {
                $stmt->execute([
                    $fecha_comision,
                    trim($fila[$columnas['cod_vendedor']]),
                    trim($fila[$columnas['observacion']] ?? ''),
                    $comision_real,
                    $nombre_archivo
                ]);
            } elseif ($tipo == 'financieras') {
                $stmt->execute([
                    $fecha_comision,
                    trim($fila[$columnas['cod_vendedor']]),
                    trim($fila[$columnas['concepto']] ?? ''),
                    $vehiculos_vendidos,
                    $comision_real,
                    $nombre_archivo
                ]);
            }
            
            $insertados++;
            
        } catch (Exception $e) {
            $errores++;
            error_log("Error al insertar fila: " . $e->getMessage());
        }
    }
    
    $_SESSION['mensaje'] = "✅ Archivo subido correctamente. Se insertaron <strong>$insertados</strong> registros.";
    if ($errores > 0) {
        $_SESSION['mensaje'] .= " ($errores filas con error)";
    }
    
} catch (Exception $e) {
    // Si hay error, eliminar el archivo subido
    if (file_exists($ruta_destino)) {
        unlink($ruta_destino);
    }
    
    $_SESSION['error'] = "❌ Error al procesar el Excel: " . $e->getMessage();
}

// ============================================
// FUNCIÓN PARA LIMPIAR NÚMEROS
// ============================================
function limpiarNumero($valor) {
    if ($valor === null || $valor === '') return 0;
    
    if (is_int($valor) || is_float($valor)) {
        return floatval($valor);
    }
    
    $valor = trim((string)$valor);
    $valor = str_replace(['$', ' ', "\xc2\xa0", 'COP', 'cop'], '', $valor);
    
    if ($valor === '') return 0;
    
    $tiene_punto = strpos($valor, '.') !== false;
    $tiene_coma = strpos($valor, ',') !== false;
    
    if ($tiene_punto && $tiene_coma) {
        // Formato colombiano: 1.500.000,50
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } 
    elseif ($tiene_coma) {
        $partes = explode(',', $valor);
        if (count($partes) > 2) {
            $valor = str_replace(',', '', $valor);
        } elseif (count($partes) == 2 && strlen($partes[1]) === 2) {
            $valor = str_replace(',', '.', $valor);
        } elseif (count($partes) == 2 && strlen($partes[1]) === 3) {
            $valor = str_replace(',', '', $valor);
        } else {
            $valor = str_replace(',', '', $valor);
        }
    }
    elseif ($tiene_punto) {
        $partes = explode('.', $valor);
        $ultima = end($partes);
        
        if (count($partes) > 2) {
            $valor = str_replace('.', '', $valor);
        } elseif (count($partes) == 2 && strlen($ultima) === 3) {
            $valor = str_replace('.', '', $valor);
        } elseif (count($partes) == 2 && (strlen($ultima) === 1 || strlen($ultima) === 2)) {
            // Dejar como está
        } else {
            $valor = str_replace('.', '', $valor);
        }
    }
    
    return floatval($valor);
}

header('Location: index.php');
exit();
?>