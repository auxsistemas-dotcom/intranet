<?php
// admin/gestion_humana/regis_usuarios/ver_pdf.php - Ver certificado en el navegador
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores (rol_id = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

// Solo administradores pueden ver certificados de otros usuarios
if ($rol_usuario != 1) {
    header('Location: index.php?error=❌ No tienes permiso');
    exit();
}

$usuario_id_especifico = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;

if ($usuario_id_especifico == 0) {
    header('Location: index.php?error=❌ Usuario no especificado');
    exit();
}

// ============================================
// FUNCIONES PARA CERTIFICADO
// ============================================

function carpetaCompletadaVerPDF($usuario_id, $carpeta_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN p.visto = 1 THEN 1 ELSE 0 END) as vistos
        FROM documentos_gestion_humana d
        LEFT JOIN progreso_gestion_humana p ON p.documento_id = d.id AND p.usuario_id = ?
        WHERE d.categoria_id = ? AND d.activo = 1
    ");
    $stmt->execute([$usuario_id, $carpeta_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_docs = intval($result['total'] ?? 0);
    $vistos = intval($result['vistos'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT completado FROM quiz_gestion_humana 
        WHERE usuario_id = ? AND categoria_id = ? AND completado = 1
    ");
    $stmt->execute([$usuario_id, $carpeta_id]);
    $quiz_completado = $stmt->fetch() ? true : false;
    
    return $total_docs > 0 && $vistos == $total_docs && $quiz_completado;
}

function induccionCompletadaVerPDF($usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM categorias_gestion_humana WHERE tipo = 'area' AND activo = 1 ORDER BY orden ASC");
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($areas)) {
        return false;
    }
    
    foreach ($areas as $area_id) {
        $stmt = $pdo->prepare("SELECT id FROM categorias_gestion_humana WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1");
        $stmt->execute([$area_id]);
        $carpetas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($carpetas as $carpeta_id) {
            if (!carpetaCompletadaVerPDF($usuario_id, $carpeta_id)) {
                return false;
            }
        }
    }
    
    return true;
}

function obtenerProgresoVerPDF($usuario_id) {
    global $pdo;
    
    $total_areas = 0;
    $areas_completadas = 0;
    $detalle = [];
    
    $stmt = $pdo->prepare("SELECT id, nombre FROM categorias_gestion_humana WHERE tipo = 'area' AND activo = 1 ORDER BY orden ASC");
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($areas as $area) {
        $total_areas++;
        $area_completada = true;
        $carpetas_detalle = [];
        
        $stmt = $pdo->prepare("SELECT id, nombre FROM categorias_gestion_humana WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1 ORDER BY orden ASC");
        $stmt->execute([$area['id']]);
        $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($carpetas as $carpeta) {
            $completada = carpetaCompletadaVerPDF($usuario_id, $carpeta['id']);
            $carpetas_detalle[] = [
                'nombre' => $carpeta['nombre'],
                'completada' => $completada
            ];
            
            if (!$completada) {
                $area_completada = false;
            }
        }
        
        if ($area_completada) {
            $areas_completadas++;
        }
        
        $detalle[] = [
            'nombre' => $area['nombre'],
            'completada' => $area_completada,
            'carpetas' => $carpetas_detalle
        ];
    }
    
    $porcentaje = $total_areas > 0 ? round(($areas_completadas / $total_areas) * 100) : 0;
    
    return [
        'total_areas' => $total_areas,
        'areas_completadas' => $areas_completadas,
        'porcentaje' => $porcentaje,
        'completada' => ($total_areas > 0 && $areas_completadas == $total_areas),
        'detalle' => $detalle
    ];
}

// ============================================
// VERIFICAR USUARIO Y PROGRESO
// ============================================

// Obtener datos del usuario específico
$stmt = $pdo->prepare("SELECT id, nombre_completo, usuario FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id_especifico]);
$usuario_temp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario_temp) {
    header('Location: index.php?error=❌ Usuario no encontrado');
    exit();
}

$usuario_id = $usuario_temp['id'];
$nombre_completo = $usuario_temp['nombre_completo'];
$usuario_nombre = $usuario_temp['usuario'];

// Verificar que la inducción esté completada al 100%
if (!induccionCompletadaVerPDF($usuario_id)) {
    header('Location: index.php?error=❌ El usuario no ha completado la inducción al 100%');
    exit();
}

$progreso = obtenerProgresoVerPDF($usuario_id);

// ============================================
// GENERAR PDF CON TCPDF
// ============================================

// Verificar si TCPDF está instalado
if (!file_exists('../../../vendor/autoload.php')) {
    die('❌ Error: TCPDF no está instalado. Ejecuta: composer require tecnickcom/tcpdf');
}

require_once '../../../vendor/autoload.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('INTRANET ARMOTOR');
$pdf->SetAuthor('ARMOTOR');
$pdf->SetTitle('Certificado de Inducción');
$pdf->SetSubject('Certificado de Inducción');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Agregar página
$pdf->AddPage();

// ============================================
// DISEÑO DEL CERTIFICADO
// ============================================

// Borde exterior (doble)
$pdf->SetDrawColor(23, 55, 66);
$pdf->Rect(10, 10, 190, 277, 'D');

$pdf->SetDrawColor(243, 156, 18);
$pdf->Rect(14, 14, 182, 269, 'D');

// ============================================
// TÍTULO PRINCIPAL
// ============================================

// Espacio superior
$pdf->SetY(35);

// Logo / Título
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(23, 55, 66);
$pdf->Cell(0, 12, 'ARMOTOR', 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(23, 55, 66);
$pdf->Cell(0, 10, 'CERTIFICADO DE INDUCCIÓN', 0, 1, 'C');

// Línea decorativa
$pdf->SetDrawColor(243, 156, 18);
$pdf->Line(85, 58, 125, 58);

$pdf->Ln(12);

// ============================================
// TEXTO DEL CERTIFICADO
// ============================================

$pdf->SetFont('helvetica', '', 12);
$pdf->SetTextColor(44, 62, 80);
$pdf->Cell(0, 10, 'Se otorga el presente certificado a:', 0, 1, 'C');

$pdf->Ln(3);

// Nombre del usuario (centrado y destacado)
$pdf->SetFont('helvetica', 'B', 22);
$pdf->SetTextColor(23, 55, 66);
$pdf->Cell(0, 14, $nombre_completo, 0, 1, 'C');

$pdf->Ln(6);

// Texto descriptivo (con márgenes para que no toque bordes)
$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColor(44, 62, 80);
$pdf->SetLeftMargin(30);
$pdf->SetRightMargin(30);
$pdf->MultiCell(0, 6, 'Por haber completado exitosamente el proceso de inducción de ARMOTOR, demostrando conocimiento en los siguientes módulos:', 0, 'C');

$pdf->Ln(4);

// ============================================
// MÓDULOS COMPLETADOS
// ============================================

$pdf->SetLeftMargin(45);
$pdf->SetRightMargin(45);

foreach ($progreso['detalle'] as $area) {
    if ($area['completada']) {
        // Nombre del área
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(39, 174, 96);
        $pdf->Cell(0, 7, $area['nombre'], 0, 1, 'L');
        
        // Carpetas del área
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(44, 62, 80);
        foreach ($area['carpetas'] as $carpeta) {
            if ($carpeta['completada']) {
                $pdf->Cell(8, 5, '', 0, 0, 'L');
                $pdf->Cell(0, 5, '• ' . $carpeta['nombre'], 0, 1, 'L');
            }
        }
        $pdf->Ln(1);
    }
}

// Restaurar márgenes
$pdf->SetLeftMargin(15);
$pdf->SetRightMargin(15);

// ============================================
// FECHA
// ============================================

$pdf->Ln(6);
$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColor(44, 62, 80);
$pdf->Cell(0, 8, 'Fecha de finalización: ' . date('d/m/Y'), 0, 1, 'C');

// ============================================
// SELLO / PIE DE PÁGINA
// ============================================

// Posicionar al final de la página (sin crear otra)
$pdf->SetY(258);

// Línea decorativa
$pdf->SetDrawColor(23, 55, 66);
$pdf->Line(70, $pdf->GetY(), 140, $pdf->GetY());

$pdf->Ln(6);

// Sello ARMOTOR
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(23, 55, 66);
$pdf->Cell(0, 5, 'ARMOTOR', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 6);
$pdf->SetTextColor(127, 140, 141);
$pdf->Cell(0, 4, 'www.armotor.com', 0, 1, 'C');

// ============================================
// SALIDA DEL PDF (VER EN NAVEGADOR)
// ============================================

$pdf->Output('Certificado_Induccion_' . $usuario_nombre . '.pdf', 'I');

exit();
?>