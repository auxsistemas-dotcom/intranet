<?php
// documentacion/capacitaciones/marcar_visto_capacitacion.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

session_start();

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$documento_id = intval($data['documento_id'] ?? 0);
$usuario_id = $_SESSION['usuario_id'];

if ($documento_id == 0) {
    echo json_encode(['success' => false, 'error' => 'Documento no válido']);
    exit();
}

try {
    // ✅ CORREGIDO: Usar progreso_usuarios_capacitaciones
    // Verificar si ya está registrado
    $stmt = $pdo->prepare("SELECT id FROM progreso_usuarios_capacitaciones WHERE usuario_id = ? AND documento_id = ?");
    $stmt->execute([$usuario_id, $documento_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Ya registrado']);
        exit();
    }
    
    // Registrar vista
    $stmt = $pdo->prepare("INSERT INTO progreso_usuarios_capacitaciones (usuario_id, documento_id, visto, fecha_visto) VALUES (?, ?, 1, CURRENT_TIMESTAMP)");
    $stmt->execute([$usuario_id, $documento_id]);
    
    echo json_encode(['success' => true, 'message' => 'Visto registrado']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>