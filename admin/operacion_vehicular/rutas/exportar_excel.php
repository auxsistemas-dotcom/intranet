<?php
// admin/operacion_vehicular/rutas/exportar_excel.php
// Exportar rutas a Excel (XLSX) con rango de fechas

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
if ($rol_usuario != 1 && $rol_usuario != 2) {
    http_response_code(403);
    die('Acceso denegado');
}

// ============================================
// ✅ VALIDAR FECHAS
// ============================================
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin    = $_GET['fecha_fin']    ?? '';

$formato_valido = function($f) {
    if (empty($f)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return $d && $d->format('Y-m-d') === $f;
};

if (!$formato_valido($fecha_inicio) || !$formato_valido($fecha_fin)) {
    http_response_code(400);
    die('Fechas inválidas. Formato esperado: YYYY-MM-DD');
}

if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
    http_response_code(400);
    die('La fecha de inicio no puede ser mayor a la fecha fin');
}

// ============================================
// ✅ CONSULTAR RUTAS DEL RANGO
// ============================================
$sql = "
    SELECT 
        r.id,
        r.fecha_salida,
        r.fecha_regreso,
        r.fecha_finalizacion,
        r.ciudad_origen,
        r.ciudad_destino,
        r.motivo_viaje,
        r.estado,
        r.km_salida,
        r.km_llegada,
        v.placa,
        v.marca,
        v.modelo,
        v.tipo_vehiculo AS tipo_vh,
        v.modalidad_vehiculo AS modalidad_vh,
        u.nombre_completo AS conductor_nombre,
        r.documento_conductor AS conductor_documento,
        u.cargo AS conductor_cargo,
        u.sede AS conductor_sede
    FROM ope_ruta r
    LEFT JOIN ope_vehiculos v ON r.vehiculo_id = v.id
    LEFT JOIN ope_conductores c ON r.conductor_id = c.id
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    WHERE DATE(r.fecha_salida) >= ?
      AND DATE(r.fecha_salida) <= ?
    ORDER BY r.fecha_salida DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$fecha_inicio, $fecha_fin]);
$rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ OBTENER ACOMPAÑANTES
// ============================================
$acompanantes_por_ruta = [];
if (!empty($rutas)) {
    $ids_rutas = array_column($rutas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids_rutas), '?'));
    
    try {
        $stmt_a = $pdo->prepare("
            SELECT ruta_id, nombre_acompanante, cargo_acompanante
            FROM ope_ruta_acompanantes
            WHERE ruta_id IN ($placeholders)
            ORDER BY ruta_id, id
        ");
        $stmt_a->execute($ids_rutas);
        $rows = $stmt_a->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $nombre = $row['nombre_acompanante'] ?? '';
            $cargo = $row['cargo_acompanante'] ?? '';
            $texto = $nombre . ($cargo ? ' (' . $cargo . ')' : '');
            $acompanantes_por_ruta[$row['ruta_id']][] = $texto;
        }
    } catch (PDOException $e) {
        error_log("Error consultando acompañantes: " . $e->getMessage());
    }
}

// ============================================
// ✅ CREAR SPREADSHEET
// ============================================
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Intranet Armotor')
    ->setTitle('Rutas de Operación Vehicular')
    ->setSubject('Reporte de rutas');

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rutas');

// ============================================
// ✅ ENCABEZADOS
// ============================================
$headers = [
    'ID Ruta', 'Placa', 'Marca', 'Modelo', 'Tipo vehículo', 'Modalidad',
    'Conductor', 'Documento', 'Cargo', 'Sede',
    'Ciudad origen', 'Ciudad destino', 'Motivo del viaje',
    'Fecha salida', 'Fecha regreso', 'Fecha finalización',
    'Estado', 'Km salida', 'Km llegada', 'Km recorridos', 'Acompañantes'
];

$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '1', $h);
    $col++;
}

// ============================================
// ✅ ESTILOS
// ============================================
$ultima_col = 'U';

$estiloHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '173742']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
];

$estiloCelda = [
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
];

$sheet->getStyle('A1:' . $ultima_col . '1')->applyFromArray($estiloHeader);
$sheet->getRowDimension(1)->setRowHeight(28);

// ============================================
// ✅ LLENAR FILAS
// ============================================
$mapa_tipo = ['CARRO' => 'Carro', 'MOTO' => 'Moto', 'CAMION' => 'Camión'];
$mapa_modalidad = ['NUEVO' => 'NUEVO', 'USADO' => 'USADO', 'TEST_DRIVE' => 'TEST DRIVE', 'PROPIO' => 'PROPIO'];
$mapa_estado = ['EN_CURSO' => 'En Curso', 'FINALIZADO' => 'Finalizada', 'CANCELADO' => 'Cancelada'];

$row = 2;
foreach ($rutas as $r) {
    $km_salida = !empty($r['km_salida']) ? (int)$r['km_salida'] : null;
    $km_llegada = !empty($r['km_llegada']) ? (int)$r['km_llegada'] : null;
    $km_recorridos = ($km_salida !== null && $km_llegada !== null) ? ($km_llegada - $km_salida) : null;
    
    $acompanantes_texto = isset($acompanantes_por_ruta[$r['id']]) 
        ? implode('; ', $acompanantes_por_ruta[$r['id']]) 
        : '';
    
    $sheet->setCellValue('A' . $row, (int)$r['id']);
    $sheet->setCellValue('B' . $row, $r['placa'] ?? '');
    $sheet->setCellValue('C' . $row, $r['marca'] ?? '');
    $sheet->setCellValue('D' . $row, $r['modelo'] ?? '');
    $sheet->setCellValue('E' . $row, $mapa_tipo[$r['tipo_vh']] ?? ($r['tipo_vh'] ?? ''));
    $sheet->setCellValue('F' . $row, $mapa_modalidad[$r['modalidad_vh']] ?? ($r['modalidad_vh'] ?? ''));
    $sheet->setCellValue('G' . $row, $r['conductor_nombre'] ?? '');
    $sheet->setCellValue('H' . $row, $r['conductor_documento'] ?? '');
    $sheet->setCellValue('I' . $row, $r['conductor_cargo'] ?? '');
    $sheet->setCellValue('J' . $row, $r['conductor_sede'] ?? '');
    $sheet->setCellValue('K' . $row, $r['ciudad_origen'] ?? '');
    $sheet->setCellValue('L' . $row, $r['ciudad_destino'] ?? '');
    $sheet->setCellValue('M' . $row, $r['motivo_viaje'] ?? '');
    
    // Fechas nativas de Excel
    if (!empty($r['fecha_salida'])) {
        $sheet->setCellValue('N' . $row, ExcelDate::PHPToExcel(strtotime($r['fecha_salida'])));
    }
    if (!empty($r['fecha_regreso'])) {
        $sheet->setCellValue('O' . $row, ExcelDate::PHPToExcel(strtotime($r['fecha_regreso'])));
    }
    if (!empty($r['fecha_finalizacion'])) {
        $sheet->setCellValue('P' . $row, ExcelDate::PHPToExcel(strtotime($r['fecha_finalizacion'])));
    }
    
    $sheet->setCellValue('Q' . $row, $mapa_estado[$r['estado']] ?? $r['estado']);
    $sheet->setCellValue('R' . $row, $km_salida);
    $sheet->setCellValue('S' . $row, $km_llegada);
    $sheet->setCellValue('T' . $row, $km_recorridos);
    $sheet->setCellValue('U' . $row, $acompanantes_texto);
    
    $row++;
}

$ultima_fila = $row - 1;

// ============================================
// ✅ APLICAR ESTILOS A LA DATA
// ============================================
if ($ultima_fila >= 2) {
    $sheet->getStyle('A2:' . $ultima_col . $ultima_fila)->applyFromArray($estiloCelda);
    
    // Formato de fechas
    $sheet->getStyle('N2:P' . $ultima_fila)
        ->getNumberFormat()
        ->setFormatCode('dd/mm/yyyy hh:mm');
    
    // Formato de números con separador de miles
    $sheet->getStyle('R2:T' . $ultima_fila)
        ->getNumberFormat()
        ->setFormatCode('#,##0');
    
    // Centrar ID y estado
    $sheet->getStyle('A2:A' . $ultima_fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('Q2:Q' . $ultima_fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Wrap text en motivo y acompañantes
    $sheet->getStyle('M2:M' . $ultima_fila)->getAlignment()->setWrapText(true);
    $sheet->getStyle('U2:U' . $ultima_fila)->getAlignment()->setWrapText(true);
}

// ============================================
// ✅ ANCHOS DE COLUMNA
// ============================================
$anchos = [
    'A' => 10, 'B' => 12, 'C' => 12, 'D' => 10, 'E' => 12, 'F' => 14,
    'G' => 25, 'H' => 15, 'I' => 20, 'J' => 18,
    'K' => 18, 'L' => 18, 'M' => 40,
    'N' => 18, 'O' => 18, 'P' => 18,
    'Q' => 14, 'R' => 12, 'S' => 12, 'T' => 14, 'U' => 35,
];

foreach ($anchos as $c => $w) {
    $sheet->getColumnDimension($c)->setWidth($w);
}

// ============================================
// ✅ CONGELAR Y AUTOFILTRO
// ============================================
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:' . $ultima_col . $ultima_fila);

// ============================================
// ✅ HOJA RESUMEN
// ============================================
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Resumen');

$sheet2->mergeCells('A1:B1');
$sheet2->setCellValue('A1', 'RESUMEN DEL REPORTE');
$sheet2->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '12232B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet2->getRowDimension(1)->setRowHeight(30);

$resumen = [
    ['Rango de fechas', date('d/m/Y', strtotime($fecha_inicio)) . ' — ' . date('d/m/Y', strtotime($fecha_fin))],
    ['Generado el', date('d/m/Y H:i')],
    ['Total de rutas', count($rutas)],
    ['En curso', count(array_filter($rutas, function($r) { return $r['estado'] === 'EN_CURSO'; }))],
    ['Finalizadas', count(array_filter($rutas, function($r) { return $r['estado'] === 'FINALIZADO'; }))],
    ['Canceladas', count(array_filter($rutas, function($r) { return $r['estado'] === 'CANCELADO'; }))],
];

$row_r = 3;
foreach ($resumen as $item) {
    $sheet2->setCellValue('A' . $row_r, $item[0]);
    $sheet2->setCellValue('B' . $row_r, $item[1]);
    $sheet2->getStyle('A' . $row_r)->getFont()->setBold(true);
    $sheet2->getStyle('A' . $row_r . ':B' . $row_r)->applyFromArray($estiloCelda);
    $row_r++;
}

$sheet2->getColumnDimension('A')->setWidth(25);
$sheet2->getColumnDimension('B')->setWidth(35);

$spreadsheet->setActiveSheetIndex(0);

// ============================================
// ✅ ENVIAR AL NAVEGADOR
// ============================================
$nombreArchivo = 'Rutas_' . $fecha_inicio . '_a_' . $fecha_fin . '_' . date('Hi') . '.xlsx';

if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();