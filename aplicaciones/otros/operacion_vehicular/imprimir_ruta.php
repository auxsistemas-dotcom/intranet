<?php
// aplicaciones/otros/operacion_vehicular/imprimir_ruta.php
// Genera el PDF resumido (1 página) del detalle de una ruta con TCPDF

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR SESIÓN
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['rol'] ?? 3;

// ============================================
// ✅ VALIDAR ID DE RUTA
// ============================================
$ruta_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($ruta_id <= 0) {
    die('Ruta no especificada');
}

// ============================================
// ✅ OBTENER LA RUTA
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        v.placa, v.marca, v.modelo, v.referencia, 
        v.tipo_vehiculo AS tipo_vh,
        v.modalidad_vehiculo AS modalidad_vh,
        c.id AS conductor_id,
        c.usuario_id AS conductor_usuario_id,
        u.nombre_completo AS conductor_nombre,
        u.usuario AS conductor_usuario,
        u.cargo AS conductor_cargo,
        u.sede AS conductor_sede
    FROM ope_ruta r
    LEFT JOIN ope_vehiculos v ON r.vehiculo_id = v.id
    LEFT JOIN ope_conductores c ON r.conductor_id = c.id
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    WHERE r.id = ?
");
$stmt->execute([$ruta_id]);
$ruta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ruta) {
    die('Ruta no encontrada');
}

// ============================================
// ✅ VALIDAR PERMISO
// - Admin (1) y Supervisor (2) pueden ver todas
// - Conductor (3) solo puede ver las suyas
// ============================================
if ($rol_usuario == 3) {
    if ((int)$ruta['conductor_usuario_id'] !== (int)$usuario_id) {
        http_response_code(403);
        die('No tienes permiso para ver esta ruta');
    }
}

// ============================================
// ✅ OBTENER ACOMPAÑANTES
// ============================================
$acompanantes = [];
try {
    $stmt_acomp = $pdo->prepare("
        SELECT nombre_acompanante, cargo_acompanante, sede_acompanante
        FROM ope_ruta_acompanantes
        WHERE ruta_id = ?
        ORDER BY id
    ");
    $stmt_acomp->execute([$ruta_id]);
    $acompanantes = $stmt_acomp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error consultando acompañantes: " . $e->getMessage());
}

// ============================================
// ✅ HELPERS
// ============================================
$mapa_tipo = ['CARRO' => 'CARRO', 'MOTO' => 'MOTO', 'CAMION' => 'CAMIÓN'];
$mapa_modalidad = ['NUEVO' => 'NUEVO', 'USADO' => 'USADO', 'TEST_DRIVE' => 'TEST DRIVE', 'PROPIO' => 'PROPIO'];
$mapa_estado = ['EN_CURSO' => 'EN CURSO', 'FINALIZADO' => 'FINALIZADA', 'CANCELADO' => 'CANCELADA'];
$mapa_medio = [
    'PROPIO' => 'Vehículo propio',
    'MOTO' => 'Motocicleta',
    'ORGANIZACION' => 'Vehículo de la organización',
    'NODRIZA' => 'Vehículo nodriza / niñera'
];

$km_salida = !empty($ruta['km_salida']) ? (int)$ruta['km_salida'] : null;
$km_llegada = !empty($ruta['km_llegada']) ? (int)$ruta['km_llegada'] : null;
$km_recorridos = ($km_salida !== null && $km_llegada !== null) ? ($km_llegada - $km_salida) : null;

// Duración
$duracion = null;
if (!empty($ruta['fecha_salida']) && !empty($ruta['fecha_finalizacion'])) {
    $salida_dt = new DateTime($ruta['fecha_salida']);
    $fin_dt = new DateTime($ruta['fecha_finalizacion']);
    $diff = $salida_dt->diff($fin_dt);
    $partes = [];
    if ($diff->days > 0) $partes[] = $diff->days . ' día' . ($diff->days != 1 ? 's' : '');
    if ($diff->h > 0) $partes[] = $diff->h . ' hora' . ($diff->h != 1 ? 's' : '');
    if ($diff->i > 0) $partes[] = $diff->i . ' min';
    $duracion = empty($partes) ? 'Menos de 1 min' : implode(', ', $partes);
}

// ============================================
// ✅ GENERAR PDF CON TCPDF (1 SOLA PÁGINA)
// ============================================
if (!file_exists('../../../vendor/autoload.php')) {
    die('❌ Error: TCPDF no está instalado.');
}

require_once '../../../vendor/autoload.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('INTRANET ARMOTOR');
$pdf->SetAuthor('ARMOTOR');
$pdf->SetTitle('Ruta #' . $ruta['id'] . ' - ' . ($ruta['placa'] ?? 'SINPLACA'));
$pdf->SetSubject('Detalle de ruta');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(14, 12, 14);
$pdf->SetAutoPageBreak(false); // Garantiza una única página
$pdf->AddPage();

// ============================================
// ENCABEZADO
// ============================================
$pdf->SetFillColor(23, 55, 66); // #173742
$pdf->Rect(0, 0, 210, 26, 'F');

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetY(6);
$pdf->Cell(0, 7, 'ARMOTOR', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(138, 168, 184); // #8aa8b8
$pdf->Cell(0, 5, 'Operación Vehicular | Detalle de Ruta', 0, 1, 'C');

// Banda amarilla
$pdf->SetFillColor(255, 193, 7); // #ffc107
$pdf->Rect(0, 24, 210, 2, 'F');

$pdf->SetY(31);

// ============================================
// TÍTULO DE LA RUTA
// ============================================
$pdf->SetTextColor(18, 35, 43); // #12232b
$pdf->SetFont('helvetica', 'B', 17);
$pdf->Cell(0, 8, 'RUTA #' . $ruta['id'], 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(127, 140, 141); // #7f8c8d
$pdf->Cell(0, 4.5, 'Generado el ' . date('d/m/Y H:i') . ' por ' . ($_SESSION['nombre_completo'] ?? 'Sistema'), 0, 1, 'C');

$pdf->Ln(3);

// ============================================
// BANNER RUTA (origen -> destino)
// ============================================
if (!empty($ruta['ciudad_origen']) || !empty($ruta['ciudad_destino'])) {
    $pdf->SetFillColor(213, 245, 227); // verde claro
    $pdf->SetDrawColor(39, 174, 96);
    $pdf->SetTextColor(26, 122, 58);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 9, ' ' . ($ruta['ciudad_origen'] ?? '-') . '   ->   ' . ($ruta['ciudad_destino'] ?? '-'), 1, 1, 'C', true);
    $pdf->Ln(3.5);
}

// ============================================
// FUNCIONES AUXILIARES PARA SECCIONES
// ============================================
function tituloSeccion($pdf, $titulo) {
    $pdf->SetFillColor(23, 55, 66);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6.5, ' ' . $titulo, 0, 1, 'L', true);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Ln(1.5);
}

function filaDato($pdf, $label, $valor, $ancho_label = 42) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(127, 140, 141);
    $pdf->Cell($ancho_label, 5.5, $label . ':', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9.5);
    $pdf->SetTextColor(18, 35, 43);
    $pdf->MultiCell(0, 5.5, $valor, 0, 'L');
}

// ============================================
// DATOS DEL VIAJE
// ============================================
tituloSeccion($pdf, 'DATOS DEL VIAJE');

filaDato($pdf, 'Estado', $mapa_estado[$ruta['estado']] ?? $ruta['estado']);
filaDato($pdf, 'Motivo', $ruta['motivo_viaje'] ?: 'No especificado');
filaDato($pdf, 'Fecha salida', !empty($ruta['fecha_salida']) ? date('d/m/Y H:i', strtotime($ruta['fecha_salida'])) : '—');
filaDato($pdf, 'Regreso estimado', !empty($ruta['fecha_regreso']) ? date('d/m/Y H:i', strtotime($ruta['fecha_regreso'])) : 'No definido');
filaDato($pdf, 'Fecha finalización', !empty($ruta['fecha_finalizacion']) ? date('d/m/Y H:i', strtotime($ruta['fecha_finalizacion'])) : 'Aún en curso');
if ($duracion) {
    filaDato($pdf, 'Duración', $duracion);
}

$pdf->Ln(3);

// ============================================
// CONDUCTOR
// ============================================
tituloSeccion($pdf, 'CONDUCTOR');

filaDato($pdf, 'Nombre', $ruta['conductor_nombre'] ?? '—');
filaDato($pdf, 'Documento', $ruta['documento_conductor'] ?: 'No registrado');
filaDato($pdf, 'Cargo', $ruta['conductor_cargo'] ?: '—');
filaDato($pdf, 'Sede', $ruta['conductor_sede'] ?: '—');

$pdf->Ln(3);

// ============================================
// VEHÍCULO
// ============================================
tituloSeccion($pdf, 'VEHÍCULO');

if (!empty($ruta['placa'])) {
    filaDato($pdf, 'Placa', $ruta['placa']);
    filaDato($pdf, 'Marca/Modelo', trim(($ruta['marca'] ?? '') . ' ' . ($ruta['modelo'] ?? '')));
    filaDato($pdf, 'Referencia', $ruta['referencia'] ?: '—');
    filaDato($pdf, 'Tipo', $mapa_tipo[$ruta['tipo_vh']] ?? ($ruta['tipo_vh'] ?? '—'));
    filaDato($pdf, 'Modalidad', $mapa_modalidad[$ruta['modalidad_vh']] ?? ($ruta['modalidad_vh'] ?? '—'));
    filaDato($pdf, 'Medio de transporte', $mapa_medio[$ruta['medio_transporte']] ?? $ruta['medio_transporte']);
} else {
    $pdf->SetFont('helvetica', 'I', 9.5);
    $pdf->Cell(0, 5.5, 'No se utilizó un vehículo en esta ruta', 0, 1, 'C');
}

$pdf->Ln(3);

// ============================================
// KILOMETRAJE
// ============================================
tituloSeccion($pdf, 'KILOMETRAJE');

filaDato($pdf, 'Km salida', $km_salida !== null ? number_format($km_salida, 0, ',', '.') . ' km' : 'No registrado');
filaDato($pdf, 'Km llegada', $km_llegada !== null ? number_format($km_llegada, 0, ',', '.') . ' km' : 'No registrado');
filaDato($pdf, 'Km recorridos', $km_recorridos !== null ? number_format($km_recorridos, 0, ',', '.') . ' km' : '—');

$pdf->Ln(3);

// ============================================
// ACOMPAÑANTES
// ============================================
tituloSeccion($pdf, 'ACOMPAÑANTES (' . count($acompanantes) . ')');

if (!empty($acompanantes)) {
    foreach ($acompanantes as $i => $a) {
        $nombre = $a['nombre_acompanante'] ?? '';
        $detalle = trim(($a['cargo_acompanante'] ?? '') . (!empty($a['sede_acompanante']) ? ' · ' . $a['sede_acompanante'] : ''));
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(7, 5.5, ($i + 1) . '.', 0, 0, 'L');
        $pdf->SetTextColor(18, 35, 43);
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->Cell(0, 5.5, $nombre . ($detalle ? ' (' . $detalle . ')' : ''), 0, 1, 'L');
    }
} else {
    $pdf->SetFont('helvetica', 'I', 9.5);
    $pdf->SetTextColor(127, 140, 141);
    $pdf->Cell(0, 5.5, 'Sin acompañantes', 0, 1, 'L');
}

$pdf->Ln(3);

// ============================================
// OBSERVACIONES
// ============================================
if (!empty($ruta['observaciones_cierre'])) {
    tituloSeccion($pdf, 'OBSERVACIONES DE CIERRE');
    $pdf->SetFont('helvetica', '', 9.5);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->MultiCell(0, 5.5, $ruta['observaciones_cierre'], 0, 'L');
}

// ============================================
// PIE DE PÁGINA (1 SOLA PÁGINA)
// ============================================
$pdf->SetY(282);
$pdf->SetDrawColor(200, 206, 210);
$pdf->Line(14, 280, 196, 280);
$pdf->SetFont('helvetica', '', 7.5);
$pdf->SetTextColor(127, 140, 141);
$pdf->Cell(0, 4, 'ARMOTOR - Sistema Intranet | Generado automáticamente el ' . date('d/m/Y H:i'), 0, 0, 'L');
$pdf->Cell(0, 4, 'Página 1 de 1', 0, 0, 'R');

// ============================================
// SALIDA
// ============================================
$nombre_archivo = 'Ruta_' . $ruta['id'] . '_' . preg_replace('/[^A-Za-z0-9]/', '', $ruta['placa'] ?? 'SINPLACA') . '_' . date('Ymd') . '.pdf';
$pdf->Output($nombre_archivo, 'D');
exit();