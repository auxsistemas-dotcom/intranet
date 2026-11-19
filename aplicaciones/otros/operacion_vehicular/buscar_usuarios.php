<?php
// aplicaciones/otros/operacion_vehicular/buscar_usuarios.php
// Endpoint AJAX para buscar usuarios (acompañantes)

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada']);
    exit();
}

$usuario_id_actual = (int) $_SESSION['usuario_id'];

// ============================================
// ✅ SANITIZAR BÚSQUEDA
// ============================================
$busqueda = trim($_GET['q'] ?? '');

if (strlen($busqueda) < 2) {
    echo json_encode([
        'ok' => true,
        'sugerencias' => []
    ]);
    exit();
}

try {
    $like = '%' . $busqueda . '%';
    
    $stmt = $pdo->prepare("
        SELECT id, nombre_completo, usuario, cargo, sede
        FROM usuarios
        WHERE activo = 1
          AND id != ?  -- Excluir al conductor actual
          AND (
            nombre_completo LIKE ?
            OR usuario LIKE ?
            OR cargo LIKE ?
          )
        ORDER BY nombre_completo ASC
        LIMIT 10
    ");
    $stmt->execute([$usuario_id_actual, $like, $like, $like]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sugerencias = [];
    foreach ($usuarios as $u) {
        $sugerencias[] = [
            'id' => (int) $u['id'],
            'nombre' => $u['nombre_completo'],
            'usuario' => $u['usuario'],
            'cargo' => $u['cargo'] ?? '',
            'sede' => $u['sede'] ?? ''
        ];
    }

    echo json_encode([
        'ok' => true,
        'sugerencias' => $sugerencias
    ]);

} catch (PDOException $e) {
    error_log("Error buscando usuarios: " . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Error al buscar usuarios',
        'sugerencias' => []
    ]);
}