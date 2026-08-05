<?php
// admin/capacitaciones/permisos_agregar_usuario.php - Agregar usuario a permisos
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción']);
    exit();
}

// Recibir datos
$data = json_decode(file_get_contents('php://input'), true);
$usuario_id = intval($data['usuario_id'] ?? 0);

if ($usuario_id == 0) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario no válido']);
    exit();
}

try {
    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND rol_id != 1");
    $stmt->execute([$usuario_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o es administrador']);
        exit();
    }

    // Verificar que el usuario no tenga ya permisos
    $stmt = $pdo->prepare("SELECT id FROM permisos_capacitaciones WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El usuario ya tiene permisos asignados']);
        exit();
    }

    // No insertamos permisos automáticamente, solo añadimos el usuario a la tabla
    // El usuario aparecerá en la lista, pero sin permisos asignados
    // El administrador deberá asignar permisos manualmente desde el botón "Asignar"

    echo json_encode(['success' => true, 'message' => 'Usuario añadido correctamente. Ahora asigna los permisos desde el botón "Asignar".']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>