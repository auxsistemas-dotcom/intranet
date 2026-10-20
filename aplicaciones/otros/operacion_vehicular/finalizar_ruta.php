<?php
// aplicaciones/otros/operacion_vehicular/finalizar_ruta.php
// Finalizar ruta (marcar como FINALIZADO + guardar reporte de cierre)
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

// ✅ RECOGER OBSERVACIONES (opcional)
$observaciones_cierre = '';
if (isset($_POST['observaciones_cierre'])) {
    $observaciones_cierre = trim($_POST['observaciones_cierre']);
    $observaciones_cierre = strtoupper($observaciones_cierre);
    
    // Limitar longitud
    if (strlen($observaciones_cierre) > 1000) {
        $observaciones_cierre = substr($observaciones_cierre, 0, 1000);
    }
}

// ✅ VERIFICAR QUE LA RUTA EXISTA, SEA DEL CONDUCTOR Y ESTÉ EN_CURSO
$stmt = $pdo->prepare("
    SELECT id, vehiculo_id, estado, fecha_salida, fecha_regreso
    FROM ope_ruta
    WHERE id = ? AND conductor_id = ? AND estado = 'EN_CURSO'
");
$stmt->execute([$ruta_id, $conductor_id]);
$ruta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ruta) {
    $_SESSION['error'] = "❌ Ruta no encontrada o ya fue finalizada.";
    header('Location: index.php');
    exit();
}

// ✅ FINALIZAR LA RUTA
try {
    $stmt = $pdo->prepare("
        UPDATE ope_ruta 
        SET estado = 'FINALIZADO', 
            fecha_finalizacion = NOW(),
            observaciones_cierre = ?
        WHERE id = ? AND conductor_id = ?
    ");
    $stmt->execute([$observaciones_cierre, $ruta_id, $conductor_id]);
    
    $_SESSION['mensaje'] = "✅ Ruta finalizada correctamente el " . date('d/m/Y') . " a las " . date('H:i') . ". ¡Bienvenido de vuelta!";
    header('Location: index.php');
    exit();
    
} catch (PDOException $e) {
    error_log("Error al finalizar ruta: " . $e->getMessage());
    $_SESSION['error'] = "❌ Error al finalizar la ruta. Intenta de nuevo.";
    header('Location: index.php');
    exit();
}
?>