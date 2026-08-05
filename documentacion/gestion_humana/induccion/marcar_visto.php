<?php
// ============================================
// marcar_visto.php - CORREGIDO
// ============================================

// Incluir config y auth
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ============================================
// 1. VERIFICAR SESIÓN - USANDO usuario_id
// ============================================
if (!estaLogueado()) {
    echo json_encode([
        'success' => false, 
        'error' => 'No autorizado - Sesión no iniciada'
    ]);
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];

if (!$usuario_id) {
    echo json_encode([
        'success' => false, 
        'error' => 'No se pudo obtener el ID del usuario'
    ]);
    exit();
}

// ============================================
// 2. OBTENER DATOS DEL POST
// ============================================
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$documento_id = $data['documento_id'] ?? null;

if (!$documento_id) {
    echo json_encode([
        'success' => false, 
        'error' => 'ID de documento no proporcionado'
    ]);
    exit();
}

// ============================================
// 3. VERIFICAR QUE EL DOCUMENTO EXISTE
// ============================================
try {
    $stmt = $pdo->prepare("SELECT id, titulo, categoria_id FROM documentos_gestion_humana WHERE id = ? AND activo = 1");
    $stmt->execute([$documento_id]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$documento) {
        echo json_encode([
            'success' => false,
            'error' => 'Documento no encontrado o inactivo'
        ]);
        exit();
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar documento: ' . $e->getMessage()
    ]);
    exit();
}

// ============================================
// 4. VERIFICAR SI YA ESTÁ MARCADO COMO VISTO
// ============================================
try {
    $stmt = $pdo->prepare("SELECT id FROM progreso_gestion_humana WHERE usuario_id = ? AND documento_id = ?");
    $stmt->execute([$usuario_id, $documento_id]);
    $existe = $stmt->fetch();
    
    if ($existe) {
        echo json_encode([
            'success' => true, 
            'message' => 'Ya visto anteriormente',
            'already_viewed' => true
        ]);
        exit();
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar progreso: ' . $e->getMessage()
    ]);
    exit();
}

// ============================================
// 5. INSERTAR NUEVO REGISTRO DE PROGRESO
// ============================================
try {
    $stmt = $pdo->prepare("
        INSERT INTO progreso_gestion_humana 
        (usuario_id, documento_id, visto, fecha_visto) 
        VALUES (?, ?, 1, NOW())
    ");
    $stmt->execute([$usuario_id, $documento_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento marcado como visto'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al insertar progreso: ' . $e->getMessage()
    ]);
    exit();
}
?>