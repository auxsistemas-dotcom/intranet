<?php
// aplicaciones/otros/operacion_vehicular/cancelar_ruta.php
// Cancelar ruta (marcar como CANCELADO + guardar razón + NO actualizar km del vehículo)
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// ✅ VERIFICAR QUE SEA CONDUCTOR
$stmt = $pdo->prepare("
    SELECT c.id
    FROM ope_conductores c
    WHERE c.usuario_id = ? AND c.activo = 1
");
$stmt->execute([$usuario_id]);
$conductor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conductor) {
    $_SESSION['error'] = "❌ No estás registrado como conductor.";
    header('Location: index.php');
    exit();
}

$conductor_id = $conductor['id'];

// ✅ VALIDAR ID DE RUTA
$ruta_id = isset($_POST['ruta_id']) ? (int) $_POST['ruta_id'] : 0;

if ($ruta_id <= 0) {
    $_SESSION['error'] = "❌ Ruta no especificada.";
    header('Location: index.php');
    exit();
}

// ============================================
// ✅ RECOGER OBSERVACIONES (OBLIGATORIO en cancelación)
// ============================================
$observaciones_cierre = '';
if (isset($_POST['observaciones_cierre'])) {
    $observaciones_cierre = trim($_POST['observaciones_cierre']);
    $observaciones_cierre = strtoupper($observaciones_cierre);
    
    if (strlen($observaciones_cierre) > 1000) {
        $observaciones_cierre = substr($observaciones_cierre, 0, 1000);
    }
}

if (empty($observaciones_cierre)) {
    $_SESSION['error'] = "❌ Debes indicar la razón por la que no se pudo viajar.";
    header('Location: index.php');
    exit();
}

if (strlen($observaciones_cierre) < 5) {
    $_SESSION['error'] = "❌ La razón debe tener al menos 5 caracteres.";
    header('Location: index.php');
    exit();
}

// ✅ VERIFICAR QUE LA RUTA EXISTA, SEA DEL CONDUCTOR Y ESTÉ EN_CURSO
$stmt = $pdo->prepare("
    SELECT id, vehiculo_id, estado, fecha_salida, km_salida
    FROM ope_ruta
    WHERE id = ? AND conductor_id = ? AND estado = 'EN_CURSO'
");
$stmt->execute([$ruta_id, $conductor_id]);
$ruta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ruta) {
    $_SESSION['error'] = "❌ Ruta no encontrada o ya fue finalizada/cancelada.";
    header('Location: index.php');
    exit();
}

// ============================================
// ✅ CANCELAR LA RUTA
// ❌ NO se actualiza ope_vehiculos.kilometraje
// ✅ Solo se actualiza el estado de la ruta y sus datos de cierre
// ============================================
try {
    $pdo->beginTransaction();
    
    // 1. Actualizar la ruta como CANCELADA
    $stmt = $pdo->prepare("
        UPDATE ope_ruta 
        SET estado = 'CANCELADO', 
            fecha_finalizacion = NOW(),
            km_llegada = NULL,
            observaciones_cierre = ?
        WHERE id = ? AND conductor_id = ?
    ");
    $stmt->execute([$observaciones_cierre, $ruta_id, $conductor_id]);
    
    // 2. ❌ NO se actualiza el vehículo (el km queda igual)
    // El vehículo queda libre porque la ruta ya no está EN_CURSO
    // (la query de verificación de ruta en curso ya no lo detectará)
    
    $pdo->commit();
    
    $_SESSION['mensaje'] = "✅ Ruta cancelada correctamente el " . date('d/m/Y') . " a las " . date('H:i') 
                         . ". El vehículo ha quedado disponible.";
    header('Location: index.php');
    exit();
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al cancelar ruta: " . $e->getMessage());
    $_SESSION['error'] = "❌ Error al cancelar la ruta. Intenta de nuevo.";
    header('Location: index.php');
    exit();
}