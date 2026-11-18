<?php
// aplicaciones/otros/operacion_vehicular/finalizar_ruta.php
// Finalizar ruta (marcar como FINALIZADO + guardar km_llegada + actualizar km del vehículo)
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

// ============================================
// ✅ RECOGER KM LLEGADA
// ✅ Solo bloquea si NO viene el campo o es negativo
// ✅ El valor 0 es válido (vehículo nuevo que no recorrió km)
// ============================================
if (!isset($_POST['km_llegada']) || $_POST['km_llegada'] === '') {
    $_SESSION['error'] = "❌ Debes ingresar el kilometraje de llegada.";
    header('Location: index.php');
    exit();
}

$km_llegada = (int) $_POST['km_llegada'];

if ($km_llegada < 0) {
    $_SESSION['error'] = "❌ El kilometraje de llegada no puede ser negativo.";
    header('Location: index.php');
    exit();
}

// ✅ RECOGER OBSERVACIONES (opcional)
$observaciones_cierre = '';
if (isset($_POST['observaciones_cierre'])) {
    $observaciones_cierre = trim($_POST['observaciones_cierre']);
    $observaciones_cierre = strtoupper($observaciones_cierre);
    
    if (strlen($observaciones_cierre) > 1000) {
        $observaciones_cierre = substr($observaciones_cierre, 0, 1000);
    }
}

// ✅ VERIFICAR QUE LA RUTA EXISTA, SEA DEL CONDUCTOR Y ESTÉ EN_CURSO
$stmt = $pdo->prepare("
    SELECT id, vehiculo_id, estado, fecha_salida, fecha_regreso, km_salida
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

// ============================================
// ✅ VALIDAR QUE KM_LLEGADA >= KM_SALIDA
// ✅ Si km_salida = 0 y km_llegada = 0 → 0 >= 0 → válido ✅
// ============================================
$km_salida = (int) ($ruta['km_salida'] ?? 0);

if ($km_llegada < $km_salida) {
    $km_salida_fmt = number_format($km_salida, 0, ',', '.');
    $km_llegada_fmt = number_format($km_llegada, 0, ',', '.');
    $_SESSION['error'] = "❌ El kilometraje de llegada ({$km_llegada_fmt} km) no puede ser menor al de salida ({$km_salida_fmt} km).";
    header('Location: index.php');
    exit();
}

// ✅ FINALIZAR LA RUTA Y ACTUALIZAR VEHÍCULO EN UNA TRANSACCIÓN
try {
    $pdo->beginTransaction();
    
    // 1. Actualizar la ruta con el km_llegada
    $stmt = $pdo->prepare("
        UPDATE ope_ruta 
        SET estado = 'FINALIZADO', 
            fecha_finalizacion = NOW(),
            km_llegada = ?,
            observaciones_cierre = ?
        WHERE id = ? AND conductor_id = ?
    ");
    $stmt->execute([$km_llegada, $observaciones_cierre, $ruta_id, $conductor_id]);
    
    // 2. Actualizar el kilometraje del vehículo (SOLO si tiene vehiculo_id)
    //    ✅ Si km_llegada = 0, se guarda 0 (válido para vehículo nuevo)
    if (!empty($ruta['vehiculo_id'])) {
        $stmt = $pdo->prepare("
            UPDATE ope_vehiculos 
            SET kilometraje = ?,
                actualizado_por = ?,
                actualizado_el = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$km_llegada, $usuario_id, $ruta['vehiculo_id']]);
    }
    
    $pdo->commit();
    
    // Calcular km recorridos para el mensaje
    $km_recorridos = $km_llegada - $km_salida;
    $km_recorridos_fmt = number_format($km_recorridos, 0, ',', '.');
    
    // Mensaje según el caso
    if ($km_salida === 0 && $km_llegada === 0) {
        // Vehículo nuevo sin km recorridos
        $_SESSION['mensaje'] = "✅ Ruta finalizada el " . date('d/m/Y') . " a las " . date('H:i') 
                             . ". Vehículo nuevo (0 km en salida y llegada). ¡Bienvenido de vuelta!";
    } else {
        $_SESSION['mensaje'] = "✅ Ruta finalizada el " . date('d/m/Y') . " a las " . date('H:i') 
                             . ". Km recorridos: " . $km_recorridos_fmt . " km. ¡Bienvenido de vuelta!";
    }
    header('Location: index.php');
    exit();
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al finalizar ruta: " . $e->getMessage());
    $_SESSION['error'] = "❌ Error al finalizar la ruta. Intenta de nuevo.";
    header('Location: index.php');
    exit();
}