<?php
// ============================================
// completar_quiz.php - Marcar quiz como completado
// ============================================

// Siempre devolver JSON
header('Content-Type: application/json');

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// Verificar sesión
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado - Sesión no iniciada']);
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];

// Obtener categoria_id (GET o POST)
$categoria_id = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : 0;

if ($categoria_id == 0) {
    // Intentar desde POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $categoria_id = isset($data['categoria_id']) ? intval($data['categoria_id']) : 0;
}

if ($categoria_id == 0) {
    echo json_encode(['success' => false, 'error' => 'ID de categoría no proporcionado']);
    exit();
}

try {
    // Verificar si la categoría existe
    $stmt = $pdo->prepare("SELECT id, nombre FROM categorias_gestion_humana WHERE id = ? AND activo = 1");
    $stmt->execute([$categoria_id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$categoria) {
        echo json_encode(['success' => false, 'error' => 'Categoría no encontrada']);
        exit();
    }
    
    // Verificar si ya existe registro
    $stmt = $pdo->prepare("SELECT id, completado FROM quiz_gestion_humana WHERE usuario_id = ? AND categoria_id = ?");
    $stmt->execute([$usuario_id, $categoria_id]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existe) {
        if ($existe['completado'] == 1) {
            echo json_encode([
                'success' => true,
                'message' => 'Quiz ya estaba completado',
                'ya_completado' => true,
                'categoria_id' => $categoria_id
            ]);
            exit();
        }
        // Actualizar
        $stmt = $pdo->prepare("UPDATE quiz_gestion_humana SET completado = 1, fecha_completado = NOW() WHERE usuario_id = ? AND categoria_id = ?");
        $stmt->execute([$usuario_id, $categoria_id]);
    } else {
        // Insertar
        $stmt = $pdo->prepare("INSERT INTO quiz_gestion_humana (usuario_id, categoria_id, completado, fecha_completado) VALUES (?, ?, 1, NOW())");
        $stmt->execute([$usuario_id, $categoria_id]);
    }
    
    // Verificar que se guardó
    $stmt = $pdo->prepare("SELECT completado FROM quiz_gestion_humana WHERE usuario_id = ? AND categoria_id = ?");
    $stmt->execute([$usuario_id, $categoria_id]);
    $verificado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quiz completado correctamente',
        'categoria_id' => $categoria_id,
        'categoria_nombre' => $categoria['nombre'],
        'completado' => $verificado['completado'] ?? 0
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>