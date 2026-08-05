<?php
// ============================================
// estado_carpeta.php - CORREGIDO
// ============================================

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ============================================
// 1. VERIFICAR SESIÓN
// ============================================
if (!estaLogueado()) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];

if (!$usuario_id) {
    echo json_encode(['error' => 'No se pudo obtener el ID del usuario']);
    exit();
}

$categoria_id = $_GET['categoria_id'] ?? null;

if (!$categoria_id) {
    echo json_encode(['error' => 'Categoría no especificada']);
    exit();
}

try {
    // Total de documentos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM documentos_gestion_humana 
        WHERE categoria_id = ? AND activo = 1
    ");
    $stmt->execute([$categoria_id]);
    $total_docs = $stmt->fetchColumn();
    
    // Documentos vistos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as vistos 
        FROM progreso_gestion_humana p
        JOIN documentos_gestion_humana d ON d.id = p.documento_id
        WHERE p.usuario_id = ? AND d.categoria_id = ? AND p.visto = 1 AND d.activo = 1
    ");
    $stmt->execute([$usuario_id, $categoria_id]);
    $vistos = $stmt->fetchColumn();
    
    // Quiz completado
    $stmt = $pdo->prepare("
        SELECT completado 
        FROM quiz_gestion_humana 
        WHERE usuario_id = ? AND categoria_id = ? AND completado = 1
    ");
    $stmt->execute([$usuario_id, $categoria_id]);
    $quiz_completado = $stmt->fetch() ? true : false;
    
    // Quiz URL
    $stmt = $pdo->prepare("SELECT quiz_url FROM categorias_gestion_humana WHERE id = ?");
    $stmt->execute([$categoria_id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    $tiene_quiz_url = !empty($categoria['quiz_url']);
    $quiz_url = $categoria['quiz_url'] ?? null;
    
    $progreso = $total_docs > 0 ? round(($vistos / $total_docs) * 100) : 0;
    $boton_quiz_desbloqueado = ($progreso == 100 && !$quiz_completado && $tiene_quiz_url);
    
    echo json_encode([
        'success' => true,
        'total_docs' => (int)$total_docs,
        'vistos' => (int)$vistos,
        'progreso' => (int)$progreso,
        'quiz_completado' => $quiz_completado,
        'boton_quiz_desbloqueado' => $boton_quiz_desbloqueado,
        'tiene_quiz_url' => $tiene_quiz_url,
        'quiz_url' => $quiz_url
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>