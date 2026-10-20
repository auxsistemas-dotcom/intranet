<?php
// aplicaciones/otros/operacion_vehicular/index.php - Portal del Conductor
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// ============================================
// ✅ VERIFICAR QUE SEA CONDUCTOR
// ============================================
$stmt = $pdo->prepare("
    SELECT c.id, c.activo
    FROM ope_conductores c
    WHERE c.usuario_id = ? AND c.activo = 1
");
$stmt->execute([$usuario_id]);
$conductor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conductor) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado | Operación Vehicular</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Poppins', sans-serif; background: #d3d3d3; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
            .error-card { background: white; border-radius: 16px; padding: 50px 40px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
            .error-icon { width: 100px; height: 100px; border-radius: 50%; background: #fadbd8; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; }
            .error-icon i { font-size: 48px; color: #e74c3c; }
            .error-title { font-size: 26px; color: #12232b; font-weight: 700; margin-bottom: 15px; }
            .error-message { font-size: 15px; color: #7f8c8d; line-height: 1.7; margin-bottom: 30px; }
            .btn-volver { background: #173742; color: white; padding: 14px 30px; text-decoration: none; border-radius: 10px; font-size: 15px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
            .btn-volver:hover { background: #445960; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(23,55,66,0.3); }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><i class="fas fa-user-slash"></i></div>
            <h1 class="error-title">Acceso Denegado</h1>
            <p class="error-message">No tienes permisos para acceder a este portal.<br>Solo los conductores registrados pueden ingresar aquí.</p>
            <a href="../../../index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$conductor_id = $conductor['id'];

// ============================================
// ✅ OBTENER DATOS DEL CONDUCTOR
// ============================================
$stmt = $pdo->prepare("
    SELECT u.nombre_completo, u.usuario, u.cargo, u.sede, u.perfil_usuario, u.email, u.telefono
    FROM usuarios u
    WHERE u.id = ?
");
$stmt->execute([$usuario_id]);
$datos_usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// ============================================
// ✅ VERIFICAR SI TIENE RUTA EN CURSO
// ============================================
$stmt = $pdo->prepare("
    SELECT r.id, r.fecha_salida, r.ciudad_origen, r.ciudad_destino, r.motivo_viaje,
           v.placa, v.marca, v.modelo, v.tipo AS tipo_vh
    FROM ope_ruta r
    LEFT JOIN ope_vehiculos v ON r.vehiculo_id = v.id
    WHERE r.conductor_id = ? AND r.estado = 'EN_CURSO'
    ORDER BY r.fecha_salida DESC
    LIMIT 1
");
$stmt->execute([$conductor_id]);
$ruta_activa = $stmt->fetch(PDO::FETCH_ASSOC);

// ============================================
// ✅ MENSAJES
// ============================================
$mensaje = '';
$error = '';

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ============================================
// ✅ KPIs
// ============================================

// 1. Total licencias activas
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM ope_licencias 
    WHERE conductor_id = ? AND activo = 1
");
$stmt->execute([$conductor_id]);
$total_licencias = $stmt->fetchColumn();

// 2. Total rutas del conductor
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ope_ruta WHERE conductor_id = ?");
$stmt->execute([$conductor_id]);
$total_rutas = $stmt->fetchColumn();

// 3. Rutas en curso
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM ope_ruta 
    WHERE conductor_id = ? AND estado = 'EN_CURSO'
");
$stmt->execute([$conductor_id]);
$total_rutas_en_curso = $stmt->fetchColumn();

// 4. Licencias por vencer (≤30 días)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM ope_licencias 
    WHERE conductor_id = ? AND activo = 1
      AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$conductor_id]);
$total_licencias_alerta = $stmt->fetchColumn();

// ============================================
// ✅ LICENCIAS DEL CONDUCTOR
// ============================================
$stmt = $pdo->prepare("
    SELECT id, categoria, fecha_vencimiento, activo
    FROM ope_licencias
    WHERE conductor_id = ? AND activo = 1
    ORDER BY 
        CASE categoria
            WHEN 'A1' THEN 1
            WHEN 'A2' THEN 2
            WHEN 'B1' THEN 3
            WHEN 'B2' THEN 4
            WHEN 'B3' THEN 5
            WHEN 'C1' THEN 6
            WHEN 'C2' THEN 7
            WHEN 'C3' THEN 8
            ELSE 99
        END ASC
");
$stmt->execute([$conductor_id]);
$licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ ÚLTIMAS 5 RUTAS (sin la activa)
// ============================================
$stmt = $pdo->prepare("
    SELECT r.id, r.fecha_salida, r.fecha_regreso, r.fecha_finalizacion, r.estado,
           r.ciudad_origen, r.ciudad_destino, r.observaciones_cierre,
           v.placa, v.marca, v.modelo, v.tipo AS tipo_vh
    FROM ope_ruta r
    LEFT JOIN ope_vehiculos v ON r.vehiculo_id = v.id
    WHERE r.conductor_id = ? AND r.estado != 'EN_CURSO'
    ORDER BY r.fecha_salida DESC
    LIMIT 5
");
$stmt->execute([$conductor_id]);
$rutas_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ FUNCIÓN: Calcular estado de vencimiento
// ============================================
function calcularEstadoVencimiento($fecha_vencimiento) {
    if (empty($fecha_vencimiento)) {
        return ['clase' => 'sin-datos', 'texto' => '⚪ Sin datos'];
    }
    
    $hoy = new DateTime();
    $hoy->setTime(0, 0, 0);
    $vence = new DateTime($fecha_vencimiento);
    $dias = (int) $hoy->diff($vence)->format('%r%a');
    
    if ($dias < 0) {
        return ['clase' => 'vencido', 'texto' => 'Vencida hace ' . abs($dias) . ' días'];
    } elseif ($dias <= 30) {
        return ['clase' => 'por-vencer', 'texto' => "Vence en $dias días"];
    } else {
        return ['clase' => 'vigente', 'texto' => 'Vence el ' . date('d/m/Y', strtotime($fecha_vencimiento))];
    }
}

// ============================================
// ✅ FUNCIÓN: Calcular duración real
// ============================================
function calcularDuracion($fecha_salida, $fecha_finalizacion) {
    if (empty($fecha_salida) || empty($fecha_finalizacion)) {
        return null;
    }
    
    $salida = new DateTime($fecha_salida);
    $fin = new DateTime($fecha_finalizacion);
    $diff = $salida->diff($fin);
    
    $partes = [];
    if ($diff->days > 0) {
        $partes[] = $diff->days . 'd';
    }
    if ($diff->h > 0) {
        $partes[] = $diff->h . 'h';
    }
    if ($diff->i > 0) {
        $partes[] = $diff->i . 'min';
    }
    
    return empty($partes) ? 'Menos de 1 min' : implode(' ', $partes);
}

// ============================================
// ✅ INICIALES PARA EL AVATAR
// ============================================
$partes = explode(' ', trim($datos_usuario['nombre_completo']));
if (count($partes) >= 2) {
    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
} else {
    $iniciales = strtoupper(substr($datos_usuario['nombre_completo'], 0, 2));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Conductor | Operación Vehicular</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #d3d3d3;
            color: #2c3e50;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo { display: flex; flex-direction: column; gap: 5px; }
        .logo img { display: block; width: 130px; }
        
        .logo-titulo {
            color: #8aa8b8;
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0.5px;
            padding-left: 2px;
        }
        
        /* ========== BOTONES HEADER ========== */
        .header-botones {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-header {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        
        .btn-header.btn-historial {
            background: #3498db;
            color: white;
        }
        
        .btn-header.btn-historial:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52,152,219,0.3);
        }
        
        .btn-header.btn-volver {
            background: #445960;
            color: white;
        }
        
        .btn-header.btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        /* ========== MENSAJES ========== */
        .mensaje-exito {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: opacity 0.5s ease;
        }
        
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* ========== BANNER RUTA EN CURSO ========== */
        .banner-ruta-activa {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(243,156,18,0.4);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            animation: pulseBanner 2s infinite;
        }
        
        @keyframes pulseBanner {
            0%, 100% { box-shadow: 0 4px 20px rgba(243,156,18,0.4); }
            50% { box-shadow: 0 4px 30px rgba(243,156,18,0.7); }
        }
        
        .banner-ruta-activa-info {
            flex: 1;
            min-width: 280px;
        }
        
        .banner-ruta-activa-titulo {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .banner-ruta-activa-titulo i {
            animation: rotateCar 1.5s ease infinite;
        }
        
        @keyframes rotateCar {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(5px); }
        }
        
        .banner-ruta-activa-detalle {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
        }
        
        .banner-ruta-activa-detalle span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .btn-finalizar-ruta {
            background: #27ae60;
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(39,174,96,0.5);
            white-space: nowrap;
        }
        
        .btn-finalizar-ruta:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(39,174,96,0.7);
        }
        
        .btn-finalizar-ruta i {
            font-size: 18px;
        }
        
        /* ========== BIENVENIDA ========== */
        .bienvenida-card {
            background: linear-gradient(135deg, #173742 0%, #445960 100%);
            border-radius: 12px;
            padding: 30px 35px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
            box-shadow: 0 4px 15px rgba(23,55,66,0.2);
        }
        
        .bienvenida-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 700;
            flex-shrink: 0;
            border: 3px solid rgba(255,255,255,0.2);
        }
        
        .bienvenida-info {
            flex: 1;
            min-width: 0;
            color: white;
        }
        
        .bienvenida-titulo {
            font-size: 14px;
            color: #a8c4d1;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .bienvenida-nombre {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        
        .bienvenida-meta {
            font-size: 13px;
            color: #a8c4d1;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .bienvenida-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* ========== KPIs ========== */
        .kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #173742;
        }
        
        .kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            background: #173742;
        }
        
        .kpi-icon.success { background: #27ae60; }
        .kpi-icon.danger { background: #e74c3c; }
        .kpi-icon.warning { background: #f39c12; }
        .kpi-icon.info { background: #3498db; }
        
        .kpi-info h3 {
            font-size: 26px;
            color: #12232b;
            font-weight: 700;
        }
        
        .kpi-info p {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* ========== DASHBOARD 2 COLUMNAS ========== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 992px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        
        .columna {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .columna-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .columna-header h2 {
            font-size: 18px;
            color: #12232b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .columna-header h2 i { color: #173742; }
        
        .columna-header .badge-total {
            background: #173742;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .btn-accion {
            background: #173742;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
            border: none;
            cursor: pointer;
        }
        
        .btn-accion:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .btn-accion.primary {
            background: #27ae60;
        }
        
        .btn-accion.primary:hover {
            background: #219a52;
        }
        
        .btn-accion.disabled {
            background: #95a5a6;
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
        
        /* ========== LISTA LICENCIAS ========== */
        .licencias-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .licencia-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #173742;
            transition: all 0.2s ease;
        }
        
        .licencia-item:hover { background: #f0f2f4; }
        
        .licencia-item.vigente { border-left-color: #27ae60; }
        .licencia-item.por-vencer { border-left-color: #f39c12; }
        .licencia-item.vencido { border-left-color: #e74c3c; }
        
        .licencia-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
            min-width: 0;
            flex-wrap: wrap;
        }
        
        .licencia-categoria {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            padding: 8px 14px;
            background: #173742;
            color: white;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.5px;
        }
        
        .licencia-fecha {
            font-size: 14px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .licencia-fecha i {
            color: #7f8c8d;
            font-size: 12px;
        }
        
        .licencia-estado {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .licencia-estado.vigente { background: #d5f5e3; color: #1a7a3a; }
        .licencia-estado.por-vencer { background: #fdebd0; color: #a04000; }
        .licencia-estado.vencido { background: #fadbd8; color: #922b21; }
        
        /* ========== LISTA RUTAS ========== */
        .rutas-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .ruta-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 18px;
            border-left: 4px solid #173742;
            transition: all 0.2s ease;
        }
        
        .ruta-item:hover { background: #f0f2f4; }
        
        .ruta-item.finalizado { border-left-color: #27ae60; }
        .ruta-item.cancelado { border-left-color: #e74c3c; }
        
        .ruta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .ruta-placa {
            font-weight: 700;
            color: #12232b;
            font-size: 15px;
            letter-spacing: 0.5px;
        }
        
        .ruta-tipo {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            background: #173742;
            color: white;
            margin-left: 6px;
        }
        
        .ruta-tipo.test { background: #8e44ad; }
        .ruta-tipo.nuevo { background: #27ae60; }
        .ruta-tipo.usado { background: #3498db; }
        
        .ruta-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 12px;
            color: #7f8c8d;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        
        .ruta-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .ruta-meta span strong {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .ruta-duracion {
            background: #e8f4f8;
            color: #1a5276;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .ruta-observaciones {
            background: #fef3c7;
            border-left: 3px solid #f39c12;
            color: #92400e;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 8px;
            line-height: 1.5;
        }
        
        .ruta-observaciones strong {
            font-weight: 700;
        }
        
        .ruta-estado {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .ruta-estado.finalizado { background: #d5f5e3; color: #1a7a3a; }
        .ruta-estado.cancelado { background: #fadbd8; color: #922b21; }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 42px;
            color: #d3d3d3;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        /* ========== ACCIONES FINALES ========== */
        .acciones-finales {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .btn-grande {
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        .btn-ver-rutas {
            background: #3498db;
            color: white;
        }
        
        .btn-ver-rutas:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52,152,219,0.3);
        }
        
        .btn-crear-ruta {
            background: #27ae60;
            color: white;
        }
        
        .btn-crear-ruta:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39,174,96,0.3);
        }
        
        .btn-crear-ruta.disabled {
            background: #95a5a6;
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
        
        /* ========== MODAL FINALIZAR ========== */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(18, 35, 43, 0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
            animation: fadeIn 0.2s ease;
        }
        
        .modal-overlay.active { display: flex; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 35px 40px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #d5f5e3;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .modal-icon i { font-size: 38px; color: #27ae60; }
        
        .modal-title {
            font-size: 22px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .modal-message {
            font-size: 15px;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .modal-message strong { color: #12232b; font-weight: 600; }
        
        .modal-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 13px;
            color: #2c3e50;
        }
        
        .modal-info div {
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-info div:last-child { margin-bottom: 0; }
        
        .modal-info i { color: #7f8c8d; width: 16px; }
        
        /* ========== CAMPO OBSERVACIONES MODAL ========== */
        .modal-campo {
            text-align: left;
            margin-bottom: 20px;
        }
        
        .modal-campo label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 8px;
        }
        
        .modal-campo textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #d3d3d3;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            transition: all 0.3s ease;
        }
        
        .modal-campo textarea:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39,174,96,0.15);
        }
        
        .modal-campo small {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 5px;
            display: block;
        }
        
        .modal-acciones {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .btn-modal {
            padding: 12px 28px;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            min-width: 130px;
            justify-content: center;
        }
        
        .btn-modal-cancelar { background: #ecf0f1; color: #2c3e50; }
        .btn-modal-cancelar:hover { background: #d3d3d3; transform: translateY(-2px); }
        
        .btn-modal-finalizar { background: #27ae60; color: white; }
        .btn-modal-finalizar:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39,174,96,0.4);
        }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">Portal del Conductor</div>
        </div>
        <div class="header-botones">
            <a href="historial.php" class="btn-header btn-historial">
                <i class="fas fa-route"></i> Mi historial
            </a>
            <a href="../index.php" class="btn-header btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al inicio
            </a>
        </div>
    </div>
</header>

<div class="container">
    
    <!-- ===== MENSAJES ===== -->
    <?php if ($mensaje): ?>
        <div class="mensaje-exito" id="msgExito">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="mensaje-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- ===== BANNER RUTA EN CURSO ===== -->
    <?php if ($ruta_activa): ?>
        <div class="banner-ruta-activa">
            <div class="banner-ruta-activa-info">
                <div class="banner-ruta-activa-titulo">
                    <i class="fas fa-car-side"></i>
                    ¡TIENES UNA RUTA EN CURSO!
                </div>
                <div class="banner-ruta-activa-detalle">
                    <span>
                        <i class="fas fa-hashtag"></i>
                        <?php echo htmlspecialchars($ruta_activa['placa']); ?>
                    </span>
                    <?php if (!empty($ruta_activa['ciudad_destino'])): ?>
                        <span>
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($ruta_activa['ciudad_origen']); ?> 
                            → 
                            <?php echo htmlspecialchars($ruta_activa['ciudad_destino']); ?>
                        </span>
                    <?php endif; ?>
                    <span>
                        <i class="fas fa-clock"></i>
                        <?php echo date('d/m/Y H:i', strtotime($ruta_activa['fecha_salida'])); ?>
                    </span>
                </div>
            </div>
            <button type="button" class="btn-finalizar-ruta" onclick="abrirModalFinalizar()">
                <i class="fas fa-check-circle"></i>
                Finalizar ruta
            </button>
        </div>
    <?php endif; ?>
    
    <!-- ===== BIENVENIDA ===== -->
    <div class="bienvenida-card">
        <div class="bienvenida-avatar">
            <?php echo $iniciales; ?>
        </div>
        <div class="bienvenida-info">
            <div class="bienvenida-titulo">Bienvenido a tu portal</div>
            <div class="bienvenida-nombre"><?php echo htmlspecialchars($datos_usuario['nombre_completo']); ?></div>
            <div class="bienvenida-meta">
                <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($datos_usuario['cargo'] ?: 'Sin cargo'); ?></span>
                <?php if (!empty($datos_usuario['sede'])): ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($datos_usuario['sede']); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ===== KPIs ===== -->
    <div class="kpis-grid">
        <div class="kpi-card">
            <div class="kpi-icon info">
                <i class="fas fa-id-card"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $total_licencias; ?></h3>
                <p>Mis licencias</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon success">
                <i class="fas fa-route"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $total_rutas; ?></h3>
                <p>Mis rutas totales</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon warning">
                <i class="fas fa-car-side"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $total_rutas_en_curso; ?></h3>
                <p>Rutas en curso</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $total_licencias_alerta; ?></h3>
                <p>Licencias por vencer</p>
            </div>
        </div>
    </div>
    
    <!-- ===== DASHBOARD ===== -->
    <div class="dashboard-grid">
        
        <!-- ============ MIS LICENCIAS ============ -->
        <div class="columna">
            <div class="columna-header">
                <h2>
                    <i class="fas fa-id-card"></i> 
                    Mis licencias
                </h2>
                <span class="badge-total"><?php echo count($licencias); ?></span>
            </div>
            
            <?php if (count($licencias) > 0): ?>
                <div class="licencias-lista">
                    <?php foreach ($licencias as $lic): 
                        $estado = calcularEstadoVencimiento($lic['fecha_vencimiento']);
                    ?>
                        <div class="licencia-item <?php echo $estado['clase']; ?>">
                            <div class="licencia-info">
                                <div class="licencia-categoria">
                                    <?php echo htmlspecialchars($lic['categoria']); ?>
                                </div>
                                <div class="licencia-fecha">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('d/m/Y', strtotime($lic['fecha_vencimiento'])); ?>
                                </div>
                                <span class="licencia-estado <?php echo $estado['clase']; ?>">
                                    <?php echo $estado['texto']; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-id-card"></i>
                    <p>No tienes licencias registradas</p>
                    <p style="font-size: 12px; color: #95a5a6;">Contacta al administrador para registrar tus licencias</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ============ MIS RUTAS RECIENTES ============ -->
        <div class="columna">
            <div class="columna-header">
                <h2>
                    <i class="fas fa-route"></i> 
                    Mis últimas rutas
                </h2>
                <span class="badge-total"><?php echo count($rutas_recientes); ?></span>
            </div>
            
            <a href="rutas.php" class="btn-accion primary <?php echo $ruta_activa ? 'disabled' : ''; ?>" <?php echo $ruta_activa ? 'title="Debes finalizar la ruta actual primero"' : ''; ?>>
                <i class="fas fa-plus"></i> Crear nueva ruta
            </a>
            
            <?php if (count($rutas_recientes) > 0): ?>
                <div class="rutas-lista">
                    <?php foreach ($rutas_recientes as $ruta): 
                        $clase_estado = strtolower(str_replace('_', '-', $ruta['estado']));
                        $texto_estado = [
                            'EN_CURSO' => '🟡 En curso',
                            'FINALIZADO' => '🟢 Finalizada',
                            'CANCELADO' => '🔴 Cancelada'
                        ][$ruta['estado']] ?? $ruta['estado'];
                        
                        $clase_tipo = strtolower(str_replace('_', '', $ruta['tipo_vh']));
                        if ($clase_tipo === 'testdrive') $clase_tipo = 'test';
                        $texto_tipo = [
                            'NUEVO' => 'NUEVO',
                            'TEST_DRIVE' => 'TEST DRIVE',
                            'USADO' => 'USADO'
                        ][$ruta['tipo_vh']] ?? $ruta['tipo_vh'];
                        
                        // Calcular duración
                        $duracion = null;
                        if ($ruta['estado'] === 'FINALIZADO' && !empty($ruta['fecha_finalizacion'])) {
                            $duracion = calcularDuracion($ruta['fecha_salida'], $ruta['fecha_finalizacion']);
                        }
                    ?>
                        <div class="ruta-item <?php echo $clase_estado; ?>">
                            <div class="ruta-header">
                                <div>
                                    <span class="ruta-placa"><?php echo htmlspecialchars($ruta['placa']); ?></span>
                                    <span class="ruta-tipo <?php echo $clase_tipo; ?>"><?php echo $texto_tipo; ?></span>
                                </div>
                                <span class="ruta-estado <?php echo $clase_estado; ?>">
                                    <?php echo $texto_estado; ?>
                                </span>
                            </div>
                            <div class="ruta-meta">
                                <span>
                                    <i class="fas fa-sign-out-alt"></i>
                                    <strong>Salida:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_salida'])); ?>
                                </span>
                                <?php if ($ruta['estado'] === 'FINALIZADO' && !empty($ruta['fecha_finalizacion'])): ?>
                                    <span>
                                        <i class="fas fa-check-circle"></i>
                                        <strong>Finalizada:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_finalizacion'])); ?>
                                    </span>
                                    <?php if ($duracion): ?>
                                        <span class="ruta-duracion">
                                            <i class="fas fa-hourglass-half"></i>
                                            <?php echo $duracion; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php elseif (!empty($ruta['fecha_regreso'])): ?>
                                    <span>
                                        <i class="fas fa-sign-in-alt"></i>
                                        <strong>Regreso estimado:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_regreso'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($ruta['observaciones_cierre'])): ?>
                                <div class="ruta-observaciones">
                                    <strong>📝 Observaciones:</strong> <?php echo htmlspecialchars($ruta['observaciones_cierre']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-route"></i>
                    <p>No tienes rutas registradas</p>
                    <p style="font-size: 12px; color: #95a5a6;">Crea tu primera ruta con el botón de arriba</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========== MODAL FINALIZAR RUTA ========== -->
<?php if ($ruta_activa): ?>
<div class="modal-overlay" id="modalFinalizar">
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h2 class="modal-title">¿Finalizar ruta?</h2>
        
        <p class="modal-message">
            Vas a marcar esta ruta como <strong>FINALIZADA</strong>.
            La ruta se moverá al histórico y podrás crear nuevas.
        </p>
        
        <div class="modal-info">
            <div>
                <i class="fas fa-hashtag"></i>
                <strong>Placa:</strong> <?php echo htmlspecialchars($ruta_activa['placa']); ?>
            </div>
            <?php if (!empty($ruta_activa['ciudad_destino'])): ?>
            <div>
                <i class="fas fa-map-marker-alt"></i>
                <strong>Destino:</strong> <?php echo htmlspecialchars($ruta_activa['ciudad_destino']); ?>
            </div>
            <?php endif; ?>
            <div>
                <i class="fas fa-clock"></i>
                <strong>Salida:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta_activa['fecha_salida'])); ?>
            </div>
        </div>
        
        <form method="POST" action="finalizar_ruta.php">
            <input type="hidden" name="ruta_id" value="<?php echo $ruta_activa['id']; ?>">
            
            <div class="modal-campo">
                <label for="observaciones_cierre">
                    <i class="fas fa-comment"></i> Observaciones (opcional)
                </label>
                <textarea 
                    name="observaciones_cierre" 
                    id="observaciones_cierre"
                    placeholder="Ej: LLEGUÉ SIN NOVEDAD, HUBO TRÁFICO PESADO..."
                    maxlength="1000"
                    rows="3"
                    style="text-transform: uppercase;"
                ></textarea>
                <small>Puedes dejar este campo vacío si no hay novedades</small>
            </div>
            
            <div class="modal-acciones">
                <button type="button" class="btn-modal btn-modal-cancelar" onclick="cerrarModalFinalizar()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn-modal btn-modal-finalizar">
                    <i class="fas fa-check"></i> Sí, finalizar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ============================================
// AUTO-OCULTAR MENSAJE DE ÉXITO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const msgExito = document.getElementById('msgExito');
    if (msgExito) {
        setTimeout(function() {
            msgExito.style.opacity = '0';
            setTimeout(function() { msgExito.style.display = 'none'; }, 500);
        }, 2500);
    }
});

// ============================================
// MODAL FINALIZAR
// ============================================
function abrirModalFinalizar() {
    const modal = document.getElementById('modalFinalizar');
    if (modal) modal.classList.add('active');
}

function cerrarModalFinalizar() {
    const modal = document.getElementById('modalFinalizar');
    if (modal) modal.classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModalFinalizar();
});

document.addEventListener('click', function(e) {
    const modal = document.getElementById('modalFinalizar');
    if (e.target === modal) cerrarModalFinalizar();
});
</script>

</body>
</html>