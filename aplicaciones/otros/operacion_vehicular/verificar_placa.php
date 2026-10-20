<?php
// aplicaciones/otros/operacion_vehicular/verificar_placa.php
// Endpoint AJAX para validar placa en tiempo real

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada']);
    exit();
}

// ✅ VALIDAR PLACA
$placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['placa'] ?? ''));

if (empty($placa)) {
    echo json_encode(['ok' => false, 'estado' => 'vacio', 'mensaje' => 'Ingresa una placa']);
    exit();
}

if (strlen($placa) < 4) {
    echo json_encode(['ok' => false, 'estado' => 'vacio', 'mensaje' => '']);
    exit();
}

try {
    // 1. Buscar vehículo en BD
    $stmt = $pdo->prepare("
        SELECT id, placa, marca, modelo, activo
        FROM ope_vehiculos
        WHERE placa = ?
    ");
    $stmt->execute([$placa]);
    $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehiculo) {
        echo json_encode([
            'ok' => false,
            'estado' => 'no_existe',
            'mensaje' => "❌ La placa $placa no existe en el sistema"
        ]);
        exit();
    }
    
    if (!$vehiculo['activo']) {
        echo json_encode([
            'ok' => false,
            'estado' => 'inactivo',
            'mensaje' => "❌ El vehículo {$vehiculo['placa']} está inactivo"
        ]);
        exit();
    }
    
    // 2. Verificar si tiene ruta EN_CURSO
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM ope_ruta 
        WHERE vehiculo_id = ? AND estado = 'EN_CURSO'
    ");
    $stmt->execute([$vehiculo['id']]);
    $en_ruta = $stmt->fetchColumn();
    
    if ($en_ruta > 0) {
        echo json_encode([
            'ok' => false,
            'estado' => 'en_ruta',
            'mensaje' => "🚫 El vehículo {$vehiculo['placa']} se encuentra en movilización"
        ]);
        exit();
    }
    
    // 3. Verificar documentos vencidos
    $stmt = $pdo->prepare("
        SELECT tipo_documento, fecha_vencimiento
        FROM ope_documentos_vh
        WHERE vehiculo_id = ? AND activo = 1
    ");
    $stmt->execute([$vehiculo['id']]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hoy = new DateTime();
    $hoy->setTime(0, 0, 0);
    $docs_vencidos = [];
    
    foreach ($documentos as $doc) {
        $vence = new DateTime($doc['fecha_vencimiento']);
        $vence->setTime(0, 0, 0);
        if ($vence < $hoy) {
            $docs_vencidos[] = $doc['tipo_documento'];
        }
    }
    
    if (!empty($docs_vencidos)) {
        echo json_encode([
            'ok' => false,
            'estado' => 'docs_vencidos',
            'mensaje' => "❌ El vehículo {$vehiculo['placa']} tiene documentos vencidos: " 
                       . implode(', ', $docs_vencidos)
        ]);
        exit();
    }
    
    // ✅ TODO OK
    echo json_encode([
        'ok' => true,
        'estado' => 'disponible',
        'vehiculo' => [
            'placa' => $vehiculo['placa'],
            'marca' => $vehiculo['marca'],
            'modelo' => $vehiculo['modelo']
        ],
        'mensaje' => "✅ Vehículo {$vehiculo['placa']} disponible"
    ]);
    
} catch (PDOException $e) {
    error_log("Error verificando placa: " . $e->getMessage());
    echo json_encode(['ok' => false, 'estado' => 'error', 'mensaje' => 'Error al verificar la placa']);
}