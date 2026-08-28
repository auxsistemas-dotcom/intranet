<?php
// aplicaciones/otros/solicitud_permisos/eliminar.php - Eliminar solicitud
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];

// ============================================
// OBTENER ID
// ============================================
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    $_SESSION['error'] = "❌ ID de solicitud no válido";
    header('Location: index.php');
    exit();
}

// ============================================
// VERIFICAR SOLICITUD
// ============================================
$stmt = $pdo->prepare("
    SELECT sp.*, u.rol_id 
    FROM solicitudes_permisos sp
    JOIN usuarios u ON sp.usuario_id = u.id
    WHERE sp.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    $_SESSION['error'] = "❌ La solicitud no existe";
    header('Location: index.php');
    exit();
}

// ✅ VERIFICAR PERMISOS
$es_propietario = ($solicitud['usuario_id'] == $usuario_id);
$es_admin = ($usuario['rol'] == 1);

if (!$es_propietario && !$es_admin) {
    $_SESSION['error'] = "❌ No tienes permiso para eliminar esta solicitud";
    header('Location: index.php');
    exit();
}

// ✅ VERIFICAR ESTADO
if ($solicitud['estado'] != 'pendiente') {
    $_SESSION['error'] = "❌ No puedes eliminar una solicitud que ya fue " . $solicitud['estado'];
    header('Location: index.php');
    exit();
}

// ============================================
// ELIMINAR
// ============================================
try {
    // Eliminar archivo si existe
    if (!empty($solicitud['archivo'])) {
        $ruta_archivo = '../../../uploads/permisos/' . $solicitud['archivo'];
        if (file_exists($ruta_archivo)) {
            unlink($ruta_archivo);
        }
    }
    
    // Eliminar registro
    $stmt = $pdo->prepare("DELETE FROM solicitudes_permisos WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Solicitud eliminada correctamente";
    
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Error al eliminar la solicitud: " . $e->getMessage();
}

header('Location: index.php');
exit();
?>