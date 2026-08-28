<?php
// admin/gestion_permisos/aprobar.php - Procesar aprobación/rechazo
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$usuario_actual = obtenerUsuario();
$usuario_id = $usuario_actual['id'];
$rol_usuario = $usuario_actual['rol'];

// ============================================
// OBTENER DATOS DEL POST
// ============================================
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($id <= 0) {
    $_SESSION['error'] = "❌ ID de solicitud no válido";
    header('Location: index.php');
    exit();
}

if (!in_array($action, ['aprobar', 'rechazar'])) {
    $_SESSION['error'] = "❌ Acción no válida";
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
$es_admin = ($rol_usuario == 1);
$es_jefe_asignado = ($solicitud['jefe_inmediato'] == $usuario_id);

if (!$es_admin && !$es_jefe_asignado) {
    $_SESSION['error'] = "❌ No tienes permiso para realizar esta acción";
    header('Location: index.php');
    exit();
}

// ✅ VERIFICAR ESTADO
if ($solicitud['estado'] != 'pendiente') {
    $_SESSION['error'] = "❌ Esta solicitud ya fue " . $solicitud['estado'];
    header('Location: index.php');
    exit();
}

// ============================================
// PROCESAR ACCIÓN
// ============================================
try {
    if ($action == 'aprobar') {
        $estado = 'aprobado';
        $mensaje = "✅ Solicitud aprobada correctamente";
    } else {
        $estado = 'rechazado';
        $mensaje = "❌ Solicitud rechazada";
    }
    
    // Actualizar la solicitud
    $stmt = $pdo->prepare("
        UPDATE solicitudes_permisos 
        SET estado = ?,
            aprobado_el = NOW(),
            aprobado_por = ?
        WHERE id = ?
    ");
    $stmt->execute([$estado, $usuario_id, $id]);
    
    $_SESSION['mensaje'] = $mensaje;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Error al procesar la solicitud: " . $e->getMessage();
}

header('Location: index.php');
exit();
?>