<?php
// admin/pqrs/estadisticas_export.php - Exportar estadísticas a Excel (XLSX)

require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/pqrs_helpers.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para exportar estadísticas";
    header('Location: ../../index.php');
    exit();
}

$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

// 🔓 Permitir concatenar chats largos sin cortes (se usa en Timeline si se necesita)
$pdo->exec("SET SESSION group_concat_max_len = 1000000");

// ============================================
// TIPOS DE SOLICITUD
// ============================================
$tipos_solicitud = [
    'PETICION'     => 'Petición',
    'QUEJA'        => 'Queja',
    'RECLAMO'      => 'Reclamo',
    'SUGERENCIA'   => 'Sugerencia',
    'FELICITACION' => 'Felicitación'
];

// ============================================
// ESTADOS
// ============================================
$estados = [
    'abierto'                  => 'Abierto',
    'en_proceso'               => 'En Proceso',
    'analisis_servicio_cliente'=> 'Análisis Serv. Cliente',
    'cerrado'                  => 'Cerrado'
];

// ============================================
// PRIORIDADES
// ============================================
$prioridades = [
    'alta'  => 'Alta',
    'media' => 'Media',
    'baja'  => 'Baja'
];

// ============================================
// FILTROS (mismos que estadisticas.php)
// ============================================
$filtro_fecha      = $_GET['fecha']      ?? 'todo';
$filtro_fecha_desde= $_GET['fecha_desde']?? '';
$filtro_fecha_hasta= $_GET['fecha_hasta']?? '';
$filtro_mes        = $_GET['mes']        ?? '';
$filtro_ano        = $_GET['ano']        ?? '';
$filtro_tipo       = $_GET['tipo']       ?? '';
$filtro_estado     = $_GET['estado']     ?? '';
$filtro_prioridad  = $_GET['prioridad']  ?? '';
$filtro_gestor     = $_GET['gestor']     ?? '';
$filtro_sede       = $_GET['sede']       ?? '';
$filtro_marca_vh   = $_GET['marca_vh']   ?? '';
$filtro_buscar     = trim($_GET['buscar']?? '');

$where = [];
$params = [];
$campo_fecha = "COALESCE(p.marca_temporal, p.creado_el)";

switch ($filtro_fecha) {
    case 'hoy':
        $where[] = "DATE($campo_fecha) = CURDATE()";
        break;
    case 'semana':
        $where[] = "YEARWEEK($campo_fecha, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'mes_actual':
        $where[] = "YEAR($campo_fecha) = YEAR(CURDATE()) AND MONTH($campo_fecha) = MONTH(CURDATE())";
        break;
    case 'año_actual':
        $where[] = "YEAR($campo_fecha) = YEAR(CURDATE())";
        break;
    case 'personalizado':
        if (!empty($filtro_fecha_desde)) {
            $where[] = "DATE($campo_fecha) >= ?";
            $params[] = $filtro_fecha_desde;
        }
        if (!empty($filtro_fecha_hasta)) {
            $where[] = "DATE($campo_fecha) <= ?";
            $params[] = $filtro_fecha_hasta;
        }
        break;
    case 'mes_especifico':
        if (!empty($filtro_mes) && !empty($filtro_ano)) {
            $where[] = "YEAR($campo_fecha) = ? AND MONTH($campo_fecha) = ?";
            $params[] = intval($filtro_ano);
            $params[] = intval($filtro_mes);
        }
        break;
}

if (!empty($filtro_tipo) && array_key_exists($filtro_tipo, $tipos_solicitud)) {
    $where[] = "p.tipo_solicitud = ?";
    $params[] = $filtro_tipo;
}
if (!empty($filtro_estado) && array_key_exists($filtro_estado, $estados)) {
    $where[] = "p.estado = ?";
    $params[] = $filtro_estado;
}
if (!empty($filtro_prioridad) && array_key_exists($filtro_prioridad, $prioridades)) {
    $where[] = "p.prioridad = ?";
    $params[] = $filtro_prioridad;
}
if (!empty($filtro_gestor) && is_numeric($filtro_gestor)) {
    $where[] = "p.gestor_principal_id = ?";
    $params[] = intval($filtro_gestor);
}
if (!empty($filtro_sede)) {
    $where[] = "p.ciudad_concesionario LIKE ?";
    $params[] = '%' . $filtro_sede . '%';
}
if (!empty($filtro_marca_vh)) {
    $where[] = "p.marca_vh = ?";
    $params[] = $filtro_marca_vh;
}
if (!empty($filtro_buscar)) {
    $where[] = "(p.codigo LIKE ? OR p.asunto LIKE ? OR p.nombre_completo LIKE ?)";
    $like = '%' . $filtro_buscar . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ============================================
// CREAR SPREADSHEET
// ============================================
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Intranet Armotor')
    ->setTitle('Estadísticas PQRS')
    ->setSubject('Reporte PQRS')
    ->setDescription('Exportación de estadísticas de PQRS generado automáticamente');

// Estilos comunes
$estiloHeader = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '173742'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC'],
        ],
    ],
];

$estiloTitulo = [
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => '12232B'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

$estiloSubtitulo = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'color' => ['rgb' => '445960'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
];

$estiloCelda = [
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'E0E0E0'],
        ],
    ],
];

// ============================================
// HOJA 1: RESUMEN
// ============================================
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Resumen');

$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'ESTADÍSTICAS PQRS');
$sheet->getStyle('A1')->applyFromArray($estiloTitulo);
$sheet->getRowDimension(1)->setRowHeight(30);

$sheet->mergeCells('A2:D2');
$sheet->setCellValue('A2', 'Generado el ' . date('d/m/Y H:i') . ' por ' . $nombre_usuario);
$sheet->getStyle('A2')->applyFromArray($estiloSubtitulo);
$sheet->getRowDimension(2)->setRowHeight(20);

$sheet->mergeCells('A3:D3');
$sheet->setCellValue('A3', 'Filtros aplicados: ' . resumenFiltros());
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getRowDimension(4)->setRowHeight(10);

$sheet->setCellValue('A5', 'INDICADOR');
$sheet->setCellValue('B5', 'VALOR');
$sheet->mergeCells('B5:C5');
$sheet->getStyle('A5:C5')->applyFromArray($estiloHeader);

$sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN p.estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
            SUM(CASE WHEN p.estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN p.estado = 'analisis_servicio_cliente' THEN 1 ELSE 0 END) as analisis,
            SUM(CASE WHEN p.estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
            SUM(CASE WHEN p.gestor_principal_id IS NULL OR p.gestor_principal_id = 0 THEN 1 ELSE 0 END) as sin_gestor
        FROM pqrs p
        $where_clause";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

$sqlProm = "SELECT AVG(DATEDIFF(p.fecha_cierre, COALESCE(p.marca_temporal, p.creado_el))) as promedio
            FROM pqrs p
            $where_clause" . (empty($where_clause) ? " WHERE" : " AND") . " p.estado = 'cerrado' AND p.fecha_cierre IS NOT NULL";
$stmt = $pdo->prepare($sqlProm);
$stmt->execute($params);
$tiempo_promedio = round($stmt->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0, 1);

$tasa_resolucion = ($kpi['total'] ?? 0) > 0 ? round(($kpi['cerrados'] / $kpi['total']) * 100, 1) : 0;

$kpis = [
    ['Total PQRS',                       $kpi['total'] ?? 0],
    ['Abiertas',                         $kpi['abiertos'] ?? 0],
    ['En Proceso',                       $kpi['en_proceso'] ?? 0],
    ['Análisis Serv. Cliente',           $kpi['analisis'] ?? 0],
    ['Cerradas',                         $kpi['cerrados'] ?? 0],
    ['Sin gestor asignado',              $kpi['sin_gestor'] ?? 0],
    ['Tiempo promedio de cierre (días)', $tiempo_promedio],
    ['Tasa de resolución (%)',           $tasa_resolucion],
];

$row = 6;
foreach ($kpis as $k) {
    $sheet->setCellValue('A' . $row, $k[0]);
    $sheet->setCellValue('B' . $row, $k[1]);
    $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray($estiloCelda);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
}

$sheet->getColumnDimension('A')->setWidth(35);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(20);

// ============================================
// HOJA 2: DETALLE (SIN columna Chat / Timeline)
// ============================================
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Detalle');

$sql = "SELECT 
            p.codigo,
            p.tipo_solicitud,
            p.estado,
            p.prioridad,
            p.nombre_completo,
            p.documento,
            p.email_contacto,
            p.celular,
            p.ciudad_cliente,
            p.ciudad_concesionario,
            p.marca_vh,
            p.placa,
            p.asunto,
            p.descripcion,
            u.nombre_completo AS gestor_nombre,
            COALESCE(p.marca_temporal, p.creado_el) AS fecha_creacion,
            p.fecha_cierre,
            DATEDIFF(p.fecha_cierre, COALESCE(p.marca_temporal, p.creado_el)) AS duracion_dias
        FROM pqrs p
        LEFT JOIN usuarios u ON p.gestor_principal_id = u.id
        $where_clause
        ORDER BY p.creado_el DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = [
    'Código', 'Tipo', 'Estado', 'Prioridad', 'Cliente', 'Documento',
    'Email', 'Celular', 'Ciudad cliente', 'Ciudad concesionario',
    'Marca VH', 'Placa', 'Asunto', 'Descripción', 'Gestor principal',
    'Fecha creación', 'Fecha cierre', 'Duración (días)'
];

$col = 'A';
foreach ($headers as $h) {
    $sheet2->setCellValue($col . '1', $h);
    $col++;
}
$sheet2->getStyle('A1:R1')->applyFromArray($estiloHeader);
$sheet2->getRowDimension(1)->setRowHeight(25);

$row = 2;
foreach ($detalle as $d) {
    $sheet2->setCellValue('A' . $row, $d['codigo']);
    $sheet2->setCellValue('B' . $row, $tipos_solicitud[$d['tipo_solicitud']] ?? $d['tipo_solicitud']);
    $sheet2->setCellValue('C' . $row, $estados[$d['estado']] ?? $d['estado']);
    $sheet2->setCellValue('D' . $row, $prioridades[$d['prioridad']] ?? $d['prioridad']);
    $sheet2->setCellValue('E' . $row, $d['nombre_completo']);
    $sheet2->setCellValue('F' . $row, $d['documento']);
    $sheet2->setCellValue('G' . $row, $d['email_contacto']);
    $sheet2->setCellValue('H' . $row, $d['celular']);
    $sheet2->setCellValue('I' . $row, $d['ciudad_cliente']);
    $sheet2->setCellValue('J' . $row, $d['ciudad_concesionario']);
    $sheet2->setCellValue('K' . $row, $d['marca_vh']);
    $sheet2->setCellValue('L' . $row, $d['placa']);
    $sheet2->setCellValue('M' . $row, $d['asunto']);
    $sheet2->setCellValue('N' . $row, $d['descripcion']);
    $sheet2->setCellValue('O' . $row, $d['gestor_nombre'] ?? '—');
    $sheet2->setCellValue('P' . $row, $d['fecha_creacion'] ? date('d/m/Y H:i', strtotime($d['fecha_creacion'])) : '');
    $sheet2->setCellValue('Q' . $row, $d['fecha_cierre'] ? date('d/m/Y H:i', strtotime($d['fecha_cierre'])) : '');
    $sheet2->setCellValue('R' . $row, $d['duracion_dias'] ?? '');

    $sheet2->getStyle('A' . $row . ':R' . $row)->applyFromArray($estiloCelda);
    $row++;
}

$anchos = [
    'A' => 18, 'B' => 15, 'C' => 20, 'D' => 12, 'E' => 25,
    'F' => 15, 'G' => 28, 'H' => 15, 'I' => 18, 'J' => 25,
    'K' => 12, 'L' => 10, 'M' => 35, 'N' => 50, 'O' => 25,
    'P' => 18, 'Q' => 18, 'R' => 15,
];
foreach ($anchos as $c => $w) {
    $sheet2->getColumnDimension($c)->setWidth($w);
}

$sheet2->freezePane('A2');
$sheet2->setAutoFilter('A1:R' . ($row - 1));

// ============================================
// HOJA 3: TIMELINE (todos los mensajes / eventos)
// ============================================
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Timeline');

$sql = "SELECT 
            p.codigo AS pqrs_codigo,
            p.asunto AS pqrs_asunto,
            p.nombre_completo AS cliente,
            m.creado_el AS fecha_evento,
            m.tipo AS tipo_evento,
            m.accion,
            m.valor_anterior,
            m.valor_nuevo,
            m.mensaje,
            m.archivo_nombre_original,
            u.nombre_completo AS autor,
            u.cargo AS autor_cargo
        FROM pqrs_mensajes m
        INNER JOIN pqrs p ON m.pqrs_id = p.id
        LEFT JOIN usuarios u ON m.usuario_id = u.id
        $where_clause
        ORDER BY p.codigo ASC, m.creado_el ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers3 = [
    'Código PQRS', 'Asunto', 'Cliente', 'Fecha/Hora',
    'Tipo', 'Acción', 'Detalle', 'Autor', 'Cargo', 'Adjunto'
];

$col = 'A';
foreach ($headers3 as $h) {
    $sheet3->setCellValue($col . '1', $h);
    $col++;
}
$sheet3->getStyle('A1:J1')->applyFromArray($estiloHeader);
$sheet3->getRowDimension(1)->setRowHeight(25);

$row = 2;
foreach ($timeline as $t) {
    $detalle_evento = $t['mensaje'];
    if ($t['tipo_evento'] === 'sistema') {
        $extras = [];
        if (!empty($t['valor_anterior'])) $extras[] = 'Antes: ' . $t['valor_anterior'];
        if (!empty($t['valor_nuevo']))    $extras[] = 'Después: ' . $t['valor_nuevo'];
        if (!empty($extras)) $detalle_evento .= ' (' . implode(' | ', $extras) . ')';
    }

    $sheet3->setCellValue('A' . $row, $t['pqrs_codigo']);
    $sheet3->setCellValue('B' . $row, $t['pqrs_asunto']);
    $sheet3->setCellValue('C' . $row, $t['cliente']);
    $sheet3->setCellValue('D' . $row, $t['fecha_evento'] ? date('d/m/Y H:i', strtotime($t['fecha_evento'])) : '');
    $sheet3->setCellValue('E' . $row, $t['tipo_evento'] === 'sistema' ? 'Sistema' : 'Comentario');
    $sheet3->setCellValue('F' . $row, $t['accion'] ?? '—');
    $sheet3->setCellValue('G' . $row, $detalle_evento);
    $sheet3->setCellValue('H' . $row, $t['autor'] ?? 'Sistema');
    $sheet3->setCellValue('I' . $row, $t['autor_cargo'] ?? '—');
    $sheet3->setCellValue('J' . $row, $t['archivo_nombre_original'] ?? '');

    $sheet3->getStyle('A' . $row . ':J' . $row)->applyFromArray($estiloCelda);
    $sheet3->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet3->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet3->getStyle('G' . $row)->getAlignment()->setWrapText(true);

    if ($t['tipo_evento'] === 'sistema') {
        $sheet3->getStyle('A' . $row . ':J' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F0F2F5');
    }

    $row++;
}

$anchos3 = [
    'A' => 18, 'B' => 30, 'C' => 25, 'D' => 18, 'E' => 14,
    'F' => 18, 'G' => 60, 'H' => 25, 'I' => 20, 'J' => 30,
];
foreach ($anchos3 as $c => $w) {
    $sheet3->getColumnDimension($c)->setWidth($w);
}
$sheet3->freezePane('A2');
$sheet3->setAutoFilter('A1:J' . ($row - 1));

// ============================================
// HOJA 4: POR GESTOR
// ============================================
$sheet4 = $spreadsheet->createSheet();
$sheet4->setTitle('Por Gestor');

$sql = "SELECT 
            u.nombre_completo AS gestor,
            u.cargo,
            COUNT(p.id) AS total,
            SUM(CASE WHEN p.estado = 'cerrado' THEN 1 ELSE 0 END) AS cerradas,
            SUM(CASE WHEN p.estado IN ('abierto','en_proceso') THEN 1 ELSE 0 END) AS activas,
            ROUND(AVG(CASE WHEN p.estado = 'cerrado' THEN DATEDIFF(p.fecha_cierre, COALESCE(p.marca_temporal, p.creado_el)) END), 1) AS tiempo_promedio
        FROM usuarios u
        INNER JOIN pqrs p ON p.gestor_principal_id = u.id
        $where_clause
        GROUP BY u.id, u.nombre_completo, u.cargo
        ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$porGestor = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers4 = ['Gestor', 'Cargo', 'Total asignadas', 'Cerradas', 'Activas', 'Tiempo promedio (días)'];
$col = 'A';
foreach ($headers4 as $h) {
    $sheet4->setCellValue($col . '1', $h);
    $col++;
}
$sheet4->getStyle('A1:F1')->applyFromArray($estiloHeader);
$sheet4->getRowDimension(1)->setRowHeight(25);

$row = 2;
foreach ($porGestor as $g) {
    $sheet4->setCellValue('A' . $row, $g['gestor']);
    $sheet4->setCellValue('B' . $row, $g['cargo']);
    $sheet4->setCellValue('C' . $row, $g['total']);
    $sheet4->setCellValue('D' . $row, $g['cerradas']);
    $sheet4->setCellValue('E' . $row, $g['activas']);
    $sheet4->setCellValue('F' . $row, $g['tiempo_promedio'] ?? '—');
    $sheet4->getStyle('A' . $row . ':F' . $row)->applyFromArray($estiloCelda);
    $sheet4->getStyle('C' . $row . ':F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
}

$sheet4->getColumnDimension('A')->setWidth(30);
$sheet4->getColumnDimension('B')->setWidth(25);
$sheet4->getColumnDimension('C')->setWidth(18);
$sheet4->getColumnDimension('D')->setWidth(15);
$sheet4->getColumnDimension('E')->setWidth(15);
$sheet4->getColumnDimension('F')->setWidth(25);

// ============================================
// HOJA 5: POR TIPO
// ============================================
$sheet5 = $spreadsheet->createSheet();
$sheet5->setTitle('Por Tipo');

$sql = "SELECT 
            p.tipo_solicitud,
            COUNT(*) AS total,
            SUM(CASE WHEN p.estado = 'cerrado' THEN 1 ELSE 0 END) AS cerradas,
            ROUND(AVG(CASE WHEN p.estado = 'cerrado' THEN DATEDIFF(p.fecha_cierre, COALESCE(p.marca_temporal, p.creado_el)) END), 1) AS tiempo_promedio
        FROM pqrs p
        $where_clause
        GROUP BY p.tipo_solicitud
        ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$porTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers5 = ['Tipo', 'Total', 'Cerradas', 'Tiempo promedio (días)', '% del total'];
$col = 'A';
foreach ($headers5 as $h) {
    $sheet5->setCellValue($col . '1', $h);
    $col++;
}
$sheet5->getStyle('A1:E1')->applyFromArray($estiloHeader);
$sheet5->getRowDimension(1)->setRowHeight(25);

$totalGeneral = array_sum(array_column($porTipo, 'total'));

$row = 2;
foreach ($porTipo as $t) {
    $porcentaje = $totalGeneral > 0 ? round(($t['total'] / $totalGeneral) * 100, 1) : 0;
    $sheet5->setCellValue('A' . $row, $tipos_solicitud[$t['tipo_solicitud']] ?? $t['tipo_solicitud']);
    $sheet5->setCellValue('B' . $row, $t['total']);
    $sheet5->setCellValue('C' . $row, $t['cerradas']);
    $sheet5->setCellValue('D' . $row, $t['tiempo_promedio'] ?? '—');
    $sheet5->setCellValue('E' . $row, $porcentaje . '%');
    $sheet5->getStyle('A' . $row . ':E' . $row)->applyFromArray($estiloCelda);
    $sheet5->getStyle('B' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
}

$sheet5->setCellValue('A' . $row, 'TOTAL');
$sheet5->setCellValue('B' . $row, $totalGeneral);
$sheet5->getStyle('A' . $row . ':E' . $row)->applyFromArray($estiloHeader);

$sheet5->getColumnDimension('A')->setWidth(20);
$sheet5->getColumnDimension('B')->setWidth(12);
$sheet5->getColumnDimension('C')->setWidth(12);
$sheet5->getColumnDimension('D')->setWidth(25);
$sheet5->getColumnDimension('E')->setWidth(15);

// ============================================
// HOJA 6: POR MES
// ============================================
$sheet6 = $spreadsheet->createSheet();
$sheet6->setTitle('Por Mes');

$sql = "SELECT 
            DATE_FORMAT(COALESCE(p.marca_temporal, p.creado_el), '%Y-%m') AS mes,
            DATE_FORMAT(COALESCE(p.marca_temporal, p.creado_el), '%b %Y') AS mes_label,
            COUNT(*) AS total,
            SUM(CASE WHEN p.estado = 'cerrado' THEN 1 ELSE 0 END) AS cerradas,
            SUM(CASE WHEN p.estado IN ('abierto','en_proceso') THEN 1 ELSE 0 END) AS activas
        FROM pqrs p
        WHERE COALESCE(p.marca_temporal, p.creado_el) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY 
            DATE_FORMAT(COALESCE(p.marca_temporal, p.creado_el), '%Y-%m'),
            DATE_FORMAT(COALESCE(p.marca_temporal, p.creado_el), '%b %Y')
        ORDER BY mes ASC";
$stmt = $pdo->query($sql);
$porMes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers6 = ['Mes', 'Total', 'Cerradas', 'Activas'];
$col = 'A';
foreach ($headers6 as $h) {
    $sheet6->setCellValue($col . '1', $h);
    $col++;
}
$sheet6->getStyle('A1:D1')->applyFromArray($estiloHeader);
$sheet6->getRowDimension(1)->setRowHeight(25);

$row = 2;
foreach ($porMes as $m) {
    $sheet6->setCellValue('A' . $row, $m['mes_label']);
    $sheet6->setCellValue('B' . $row, $m['total']);
    $sheet6->setCellValue('C' . $row, $m['cerradas']);
    $sheet6->setCellValue('D' . $row, $m['activas']);
    $sheet6->getStyle('A' . $row . ':D' . $row)->applyFromArray($estiloCelda);
    $sheet6->getStyle('B' . $row . ':D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
}

$sheet6->getColumnDimension('A')->setWidth(20);
$sheet6->getColumnDimension('B')->setWidth(12);
$sheet6->getColumnDimension('C')->setWidth(12);
$sheet6->getColumnDimension('D')->setWidth(12);

// ============================================
// HOJA 7: POR SEDE
// ============================================
$sheet7 = $spreadsheet->createSheet();
$sheet7->setTitle('Por Sede');

$sql = "SELECT 
            p.ciudad_concesionario AS sede,
            COUNT(*) AS total,
            SUM(CASE WHEN p.estado = 'cerrado' THEN 1 ELSE 0 END) AS cerradas
        FROM pqrs p
        $where_clause
        " . (empty($where_clause) ? "WHERE" : "AND") . " p.ciudad_concesionario IS NOT NULL AND p.ciudad_concesionario != ''
        GROUP BY p.ciudad_concesionario
        ORDER BY total DESC
        LIMIT 30";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$porSede = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers7 = ['Sede', 'Total', 'Cerradas', '% del total'];
$col = 'A';
foreach ($headers7 as $h) {
    $sheet7->setCellValue($col . '1', $h);
    $col++;
}
$sheet7->getStyle('A1:D1')->applyFromArray($estiloHeader);
$sheet7->getRowDimension(1)->setRowHeight(25);

$row = 2;
foreach ($porSede as $s) {
    $porcentaje = $totalGeneral > 0 ? round(($s['total'] / $totalGeneral) * 100, 1) : 0;
    $sheet7->setCellValue('A' . $row, $s['sede']);
    $sheet7->setCellValue('B' . $row, $s['total']);
    $sheet7->setCellValue('C' . $row, $s['cerradas']);
    $sheet7->setCellValue('D' . $row, $porcentaje . '%');
    $sheet7->getStyle('A' . $row . ':D' . $row)->applyFromArray($estiloCelda);
    $sheet7->getStyle('B' . $row . ':D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
}

$sheet7->getColumnDimension('A')->setWidth(30);
$sheet7->getColumnDimension('B')->setWidth(12);
$sheet7->getColumnDimension('C')->setWidth(12);
$sheet7->getColumnDimension('D')->setWidth(15);

// ============================================
// SELECCIONAR PRIMERA HOJA
// ============================================
$spreadsheet->setActiveSheetIndex(0);

// ============================================
// ENVIAR AL NAVEGADOR
// ============================================
if ($filtro_fecha === 'personalizado' && $filtro_fecha_desde && $filtro_fecha_hasta) {
    $nombreArchivo = 'PQRS_' . $filtro_fecha_desde . '_a_' . $filtro_fecha_hasta . '_' . date('Hi') . '.xlsx';
} else {
    $nombreArchivo = 'PQRS_estadisticas_' . date('Y-m-d_Hi') . '.xlsx';
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();

// ============================================
// HELPER: RESUMEN DE FILTROS
// ============================================
function resumenFiltros() {
    global $filtro_fecha, $filtro_fecha_desde, $filtro_fecha_hasta,
           $filtro_mes, $filtro_ano, $filtro_tipo, $filtro_estado,
           $filtro_prioridad, $filtro_gestor, $filtro_sede,
           $filtro_marca_vh, $filtro_buscar, $tipos_solicitud, $estados, $prioridades;

    $partes = [];

    $fechas_labels = [
        'todo' => 'Todo el tiempo',
        'hoy' => 'Hoy',
        'semana' => 'Esta semana',
        'mes_actual' => 'Este mes',
        'año_actual' => 'Este año',
        'mes_especifico' => 'Mes: ' . ($filtro_mes ?: '?') . ' / Año: ' . ($filtro_ano ?: '?'),
        'personalizado' => 'Desde ' . ($filtro_fecha_desde ?: '?') . ' hasta ' . ($filtro_fecha_hasta ?: '?')
    ];
    if ($filtro_fecha !== 'todo') {
        $partes[] = 'Período: ' . ($fechas_labels[$filtro_fecha] ?? $filtro_fecha);
    }

    if (!empty($filtro_tipo))       $partes[] = 'Tipo: ' . ($tipos_solicitud[$filtro_tipo] ?? $filtro_tipo);
    if (!empty($filtro_estado))     $partes[] = 'Estado: ' . ($estados[$filtro_estado] ?? $filtro_estado);
    if (!empty($filtro_prioridad))  $partes[] = 'Prioridad: ' . ($prioridades[$filtro_prioridad] ?? $filtro_prioridad);
    if (!empty($filtro_gestor))     $partes[] = 'Gestor ID: ' . $filtro_gestor;
    if (!empty($filtro_sede))       $partes[] = 'Sede: ' . $filtro_sede;
    if (!empty($filtro_marca_vh))   $partes[] = 'Marca: ' . $filtro_marca_vh;
    if (!empty($filtro_buscar))     $partes[] = 'Buscar: "' . $filtro_buscar . '"';

    return empty($partes) ? 'Sin filtros (todo)' : implode(' · ', $partes);
}