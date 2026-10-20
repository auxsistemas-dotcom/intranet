<?php
// admin/operacion_vehicular/index.php - Dashboard del módulo
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Admin (1) y Supervisor (2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para acceder a Operación Vehicular";
    header('Location: ../index.php');
    exit();
}

// ============================================
// ✅ PROCESAR ELIMINAR
// ============================================
if (isset($_GET['eliminar_conductor']) && is_numeric($_GET['eliminar_conductor'])) {
    $id = (int) $_GET['eliminar_conductor'];
    
    $stmt = $pdo->prepare("DELETE FROM ope_conductores WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Conductor eliminado correctamente";
    header('Location: index.php');
    exit();
}

if (isset($_GET['eliminar_vehiculo']) && is_numeric($_GET['eliminar_vehiculo'])) {
    $id = (int) $_GET['eliminar_vehiculo'];
    
    $stmt = $pdo->prepare("DELETE FROM ope_vehiculos WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Vehículo eliminado correctamente";
    header('Location: index.php');
    exit();
}

$mensaje = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

// ============================================
// ✅ BÚSQUEDAS (GET)
// ============================================
$buscar_conductor = isset($_GET['buscar_conductor']) ? trim($_GET['buscar_conductor']) : '';
$buscar_vehiculo = isset($_GET['buscar_vehiculo']) ? trim($_GET['buscar_vehiculo']) : '';

// ============================================
// ✅ KPIs
// ============================================
$stmt = $pdo->query("SELECT COUNT(*) FROM ope_conductores WHERE activo = 1");
$total_conductores = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM ope_vehiculos WHERE activo = 1");
$total_vehiculos = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM ope_licencias 
    WHERE activo = 1 
      AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$total_licencias_alerta = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM ope_documentos_vh 
    WHERE activo = 1 
      AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$total_documentos_alerta = $stmt->fetchColumn();

// ============================================
// ✅ CONSULTAS: Conductores (con búsqueda por nombre)
// ============================================
if (!empty($buscar_conductor)) {
    $sql = "
        SELECT c.id, c.usuario_id, u.nombre_completo, u.usuario, u.cargo, u.sede
        FROM ope_conductores c
        INNER JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.activo = 1
          AND u.nombre_completo LIKE :busqueda
        ORDER BY u.nombre_completo ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['busqueda' => '%' . $buscar_conductor . '%']);
} else {
    $stmt = $pdo->query("
        SELECT c.id, c.usuario_id, u.nombre_completo, u.usuario, u.cargo, u.sede
        FROM ope_conductores c
        INNER JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.activo = 1
        ORDER BY u.nombre_completo ASC
    ");
}
$conductores_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adjuntar las licencias a cada conductor
$conductores = [];
foreach ($conductores_raw as $c) {
    $stmt2 = $pdo->prepare("
        SELECT categoria, fecha_vencimiento
        FROM ope_licencias
        WHERE conductor_id = ? AND activo = 1
        ORDER BY fecha_vencimiento ASC
    ");
    $stmt2->execute([$c['id']]);
    $c['licencias'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $conductores[] = $c;
}

// ============================================
// ✅ CONSULTAS: Vehículos (con búsqueda por placa)
// ============================================
if (!empty($buscar_vehiculo)) {
    $sql = "
        SELECT v.id, v.placa, v.marca, v.modelo, v.referencia
        FROM ope_vehiculos v
        WHERE v.activo = 1
          AND v.placa LIKE :busqueda
        ORDER BY v.placa ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['busqueda' => '%' . strtoupper($buscar_vehiculo) . '%']);
} else {
    $stmt = $pdo->query("
        SELECT v.id, v.placa, v.marca, v.modelo, v.referencia
        FROM ope_vehiculos v
        WHERE v.activo = 1
        ORDER BY v.placa ASC
    ");
}
$vehiculos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$vehiculos = [];
foreach ($vehiculos_raw as $v) {
    $stmt2 = $pdo->prepare("
        SELECT tipo_documento, fecha_vencimiento
        FROM ope_documentos_vh
        WHERE vehiculo_id = ? AND activo = 1
        ORDER BY fecha_vencimiento ASC
    ");
    $stmt2->execute([$v['id']]);
    $v['documentos'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $vehiculos[] = $v;
}

// ============================================
// ✅ FUNCIÓN: Calcular estado (con categorías agrupadas)
// ============================================
function calcularEstadoVencimiento($documentos, $campo_categoria = 'categoria') {
    if (empty($documentos)) {
        return ['clase' => 'sin-datos', 'texto' => '⚪ Sin documentos'];
    }
    
    $hoy = new DateTime();
    $hoy->setTime(0, 0, 0);
    
    $vencidos = [];
    $por_vencer = [];
    $dias_mas_antiguo_vencido = null;
    $dias_menor_por_vencer = null;
    
    foreach ($documentos as $doc) {
        $vence = new DateTime($doc['fecha_vencimiento']);
        $vence->setTime(0, 0, 0);
        
        $dias = (int) $hoy->diff($vence)->format('%r%a');
        $categoria = $doc[$campo_categoria];
        
        if ($dias < 0) {
            $vencidos[] = $categoria;
            $dias_abs = abs($dias);
            if ($dias_mas_antiguo_vencido === null || $dias_abs > $dias_mas_antiguo_vencido) {
                $dias_mas_antiguo_vencido = $dias_abs;
            }
        } elseif ($dias <= 30) {
            $por_vencer[] = $categoria;
            if ($dias_menor_por_vencer === null || $dias < $dias_menor_por_vencer) {
                $dias_menor_por_vencer = $dias;
            }
        }
    }
    
    if (!empty($vencidos)) {
        $cats = implode(' - ', $vencidos);
        $texto = $cats . ' vencida' . (count($vencidos) > 1 ? 's' : '') 
               . ' hace ' . $dias_mas_antiguo_vencido . ' días';
        return ['clase' => 'vencido', 'texto' => '🔴 ' . $texto];
    }
    
    if (!empty($por_vencer)) {
        $cats = implode(' - ', $por_vencer);
        $texto = $cats . ' vence' . (count($por_vencer) > 1 ? 'n' : '') 
               . ' en ' . $dias_menor_por_vencer . ' días';
        return ['clase' => 'por-vencer', 'texto' => '🟡 ' . $texto];
    }
    
    $primera = $documentos[0];
    return [
        'clase' => 'vigente', 
        'texto' => '🟢 Vence el ' . date('d/m/Y', strtotime($primera['fecha_vencimiento']))
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operación Vehicular | INTRANET</title>
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
        
        .btn-header.btn-rutas {
            background: #3498db;
            color: white;
        }
        
        .btn-header.btn-rutas:hover {
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
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .columna-header .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-buscar-toggle {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #ecf0f1;
            color: #2c3e50;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-buscar-toggle:hover {
            background: #173742;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-buscar-toggle.activo {
            background: #173742;
            color: white;
        }
        
        .buscador-wrapper {
            margin-bottom: 15px;
            display: none;
            animation: slideDown 0.2s ease;
        }
        
        .buscador-wrapper.abierto {
            display: block;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .buscador-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .buscador-input-wrapper {
            position: relative;
            flex: 1;
        }
        
        .buscador-input-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 14px;
            pointer-events: none;
        }
        
        .buscador-input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 2px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .buscador-input:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        
        .btn-buscar-accion {
            padding: 10px 16px;
            border-radius: 8px;
            background: #173742;
            color: white;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-buscar-accion:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .btn-limpiar-busqueda {
            padding: 10px 14px;
            border-radius: 8px;
            background: #e74c3c;
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-limpiar-busqueda:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .info-busqueda {
            background: #e8f4f8;
            border-left: 3px solid #3498db;
            color: #1a5276;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        
        .info-busqueda strong { color: #12232b; }
        
        .btn-nuevo {
            background: #173742;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .btn-nuevo:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .lista-cards {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .lista-cards::-webkit-scrollbar { width: 6px; }
        .lista-cards::-webkit-scrollbar-thumb { background: #d3d3d3; border-radius: 3px; }
        .lista-cards::-webkit-scrollbar-thumb:hover { background: #b0b0b0; }
        
        .item-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #173742;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .item-card:hover {
            background: #f0f2f4;
            transform: translateX(3px);
        }
        
        .item-card.vigente { border-left-color: #27ae60; }
        .item-card.por-vencer { border-left-color: #f39c12; }
        .item-card.vencido { border-left-color: #e74c3c; }
        .item-card.sin-datos { border-left-color: #bdc3c7; }
        
        .item-info { flex: 1; min-width: 0; }
        
        .item-titulo {
            font-weight: 600;
            color: #12232b;
            font-size: 15px;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .item-subtitulo {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .item-estado {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .item-estado.vigente { background: #d5f5e3; color: #1a7a3a; }
        .item-estado.por-vencer { background: #fdebd0; color: #a04000; }
        .item-estado.vencido { background: #fadbd8; color: #922b21; }
        .item-estado.sin-datos { background: #ecf0f1; color: #7f8c8d; }
        
        .item-acciones {
            display: flex;
            gap: 6px;
            margin-left: 10px;
        }
        
        .btn-mini {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            color: white;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-mini.ver { background: #3498db; }
        .btn-mini.editar { background: #f39c12; }
        .btn-mini.eliminar { background: #e74c3c; }
        .btn-mini:hover { transform: translateY(-2px); opacity: 0.9; }
        
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
        
        .empty-state p { font-size: 13px; margin-bottom: 15px; }
        
        /* ========== MODAL ========== */
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
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }
        
        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fadbd8;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
            50% { box-shadow: 0 0 0 15px rgba(231, 76, 60, 0); }
        }
        
        .modal-icon i { font-size: 38px; color: #e74c3c; }
        
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
            margin-bottom: 10px;
        }
        
        .modal-message strong { color: #12232b; font-weight: 600; }
        
        .modal-warning {
            background: #fef3c7;
            color: #92400e;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
        }
        
        .modal-warning i { color: #f39c12; font-size: 16px; flex-shrink: 0; }
        
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
            text-decoration: none;
            min-width: 130px;
            justify-content: center;
        }
        
        .btn-modal-cancelar { background: #ecf0f1; color: #2c3e50; }
        .btn-modal-cancelar:hover { background: #d3d3d3; transform: translateY(-2px); }
        
        .btn-modal-eliminar { background: #e74c3c; color: white; }
        .btn-modal-eliminar:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">Operación Vehicular</div>
        </div>
        <div class="header-botones">
            <a href="rutas/rutas.php" class="btn-header btn-rutas">
                <i class="fas fa-route"></i> Ver Rutas
            </a>
            <a href="../index.php" class="btn-header btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </div>
</header>

<div class="container">
    
    <?php if ($mensaje): ?>
        <div class="mensaje-exito" id="msgExito">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>
    
    <!-- ===== KPIs ===== -->
    <div class="kpis-grid">
        <div class="kpi-card">
            <div class="kpi-icon success">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $total_conductores; ?></h3>
                <p>Conductores activos</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon success">
                <i class="fas fa-car"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $total_vehiculos; ?></h3>
                <p>Vehículos activos</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon danger">
                <i class="fas fa-id-card"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $total_licencias_alerta; ?></h3>
                <p>Licencias por vencer</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon danger">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $total_documentos_alerta; ?></h3>
                <p>Documentos VH por vencer</p>
            </div>
        </div>
    </div>
    
    <!-- ===== DASHBOARD ===== -->
    <div class="dashboard-grid">
        
        <!-- ============ CONDUCTORES ============ -->
        <div class="columna">
            <div class="columna-header">
                <div class="header-left">
                    <h2>
                        <i class="fas fa-user-tie"></i> 
                        Conductores
                    </h2>
                    <span class="badge-total"><?php echo count($conductores); ?></span>
                </div>
                <div class="header-right">
                    <button 
                        type="button" 
                        class="btn-buscar-toggle <?php echo !empty($buscar_conductor) ? 'activo' : ''; ?>" 
                        onclick="toggleBusqueda('conductor')"
                        title="Buscar conductores"
                    >
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <!-- Barra de búsqueda conductor -->
            <div class="buscador-wrapper <?php echo !empty($buscar_conductor) ? 'abierto' : ''; ?>" id="buscadorConductor">
                <form method="GET" action="" class="buscador-form">
                    <div class="buscador-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input 
                            type="text" 
                            name="buscar_conductor" 
                            class="buscador-input" 
                            placeholder="Buscar por nombre..."
                            value="<?php echo htmlspecialchars($buscar_conductor); ?>"
                            autocomplete="off"
                        >
                    </div>
                    <button type="submit" class="btn-buscar-accion">
                        Buscar
                    </button>
                    <?php if (!empty($buscar_conductor)): ?>
                        <a href="index.php" class="btn-limpiar-busqueda">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if (!empty($buscar_conductor)): ?>
                <div class="info-busqueda">
                    <span>
                        <i class="fas fa-search"></i> 
                        Resultados para: <strong>"<?php echo htmlspecialchars($buscar_conductor); ?>"</strong>
                    </span>
                    <a href="index.php" style="color: #3498db; text-decoration: none; font-weight: 500;">
                        <i class="fas fa-times-circle"></i> Limpiar
                    </a>
                </div>
            <?php endif; ?>
            
            <a href="conductores/agregar.php" class="btn-nuevo">
                <i class="fas fa-plus"></i> Añadir Conductor
            </a>
            
            <?php if (count($conductores) > 0): ?>
                <div class="lista-cards">
                    <?php foreach ($conductores as $c): 
                        $estado = calcularEstadoVencimiento($c['licencias'], 'categoria');
                    ?>
                        <div class="item-card <?php echo $estado['clase']; ?>">
                            <div class="item-info">
                                <div class="item-titulo">
                                    <?php echo htmlspecialchars($c['nombre_completo']); ?>
                                </div>
                                <div class="item-subtitulo">
                                    <?php echo htmlspecialchars($c['cargo'] ?? 'Sin cargo'); ?>
                                    <?php if (!empty($c['sede'])): ?>
                                        • <?php echo htmlspecialchars($c['sede']); ?>
                                    <?php endif; ?>
                                </div>
                                <span class="item-estado <?php echo $estado['clase']; ?>">
                                    <?php echo htmlspecialchars($estado['texto']); ?>
                                </span>
                            </div>
                            <div class="item-acciones">
                                <a href="conductores/ver.php?id=<?php echo $c['id']; ?>" class="btn-mini ver" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="conductores/editar.php?id=<?php echo $c['id']; ?>" class="btn-mini editar" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button 
                                    type="button" 
                                    class="btn-mini eliminar" 
                                    title="Eliminar conductor"
                                    onclick="abrirModal(
                                        'conductor',
                                        <?php echo $c['id']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($c['nombre_completo'])); ?>'
                                    )"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <?php if (!empty($buscar_conductor)): ?>
                        <p>No se encontraron conductores que coincidan con:</p>
                        <p><strong>"<?php echo htmlspecialchars($buscar_conductor); ?>"</strong></p>
                        <a href="index.php" class="btn-nuevo" style="margin-top: 10px;">
                            <i class="fas fa-times"></i> Limpiar búsqueda
                        </a>
                    <?php else: ?>
                        <p>No hay conductores registrados</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ============ VEHÍCULOS ============ -->
        <div class="columna">
            <div class="columna-header">
                <div class="header-left">
                    <h2>
                        <i class="fas fa-car"></i> 
                        Vehículos
                    </h2>
                    <span class="badge-total"><?php echo count($vehiculos); ?></span>
                </div>
                <div class="header-right">
                    <button 
                        type="button" 
                        class="btn-buscar-toggle <?php echo !empty($buscar_vehiculo) ? 'activo' : ''; ?>" 
                        onclick="toggleBusqueda('vehiculo')"
                        title="Buscar vehículos"
                    >
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <!-- Barra de búsqueda vehículo -->
            <div class="buscador-wrapper <?php echo !empty($buscar_vehiculo) ? 'abierto' : ''; ?>" id="buscadorVehiculo">
                <form method="GET" action="" class="buscador-form">
                    <div class="buscador-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input 
                            type="text" 
                            name="buscar_vehiculo" 
                            class="buscador-input" 
                            placeholder="Buscar por placa..."
                            value="<?php echo htmlspecialchars($buscar_vehiculo); ?>"
                            autocomplete="off"
                            style="text-transform: uppercase;"
                        >
                    </div>
                    <button type="submit" class="btn-buscar-accion">
                        Buscar
                    </button>
                    <?php if (!empty($buscar_vehiculo)): ?>
                        <a href="index.php" class="btn-limpiar-busqueda">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if (!empty($buscar_vehiculo)): ?>
                <div class="info-busqueda">
                    <span>
                        <i class="fas fa-search"></i> 
                        Resultados para: <strong>"<?php echo htmlspecialchars($buscar_vehiculo); ?>"</strong>
                    </span>
                    <a href="index.php" style="color: #3498db; text-decoration: none; font-weight: 500;">
                        <i class="fas fa-times-circle"></i> Limpiar
                    </a>
                </div>
            <?php endif; ?>
            
            <a href="vehiculos/agregar.php" class="btn-nuevo">
                <i class="fas fa-plus"></i> Añadir Vehículo
            </a>
            
            <?php if (count($vehiculos) > 0): ?>
                <div class="lista-cards">
                    <?php foreach ($vehiculos as $v): 
                        $estado = calcularEstadoVencimiento($v['documentos'], 'tipo_documento');
                    ?>
                        <div class="item-card <?php echo $estado['clase']; ?>">
                            <div class="item-info">
                                <div class="item-titulo">
                                    <?php echo htmlspecialchars($v['placa']); ?>
                                </div>
                                <div class="item-subtitulo">
                                    <?php echo htmlspecialchars($v['marca'] ?? ''); ?>
                                    <?php if (!empty($v['modelo'])): ?>
                                        <?php echo htmlspecialchars($v['modelo']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($v['referencia'])): ?>
                                        • <?php echo htmlspecialchars($v['referencia']); ?>
                                    <?php endif; ?>
                                </div>
                                <span class="item-estado <?php echo $estado['clase']; ?>">
                                    <?php echo htmlspecialchars($estado['texto']); ?>
                                </span>
                            </div>
                            <div class="item-acciones">
                                <a href="vehiculos/ver.php?id=<?php echo $v['id']; ?>" class="btn-mini ver" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="vehiculos/editar.php?id=<?php echo $v['id']; ?>" class="btn-mini editar" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button 
                                    type="button" 
                                    class="btn-mini eliminar" 
                                    title="Eliminar vehículo"
                                    onclick="abrirModal(
                                        'vehiculo',
                                        <?php echo $v['id']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($v['placa'])); ?>'
                                    )"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-car-side"></i>
                    <?php if (!empty($buscar_vehiculo)): ?>
                        <p>No se encontraron vehículos que coincidan con:</p>
                        <p><strong>"<?php echo htmlspecialchars($buscar_vehiculo); ?>"</strong></p>
                        <a href="index.php" class="btn-nuevo" style="margin-top: 10px;">
                            <i class="fas fa-times"></i> Limpiar búsqueda
                        </a>
                    <?php else: ?>
                        <p>No hay vehículos registrados</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalEliminar">
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        
        <h2 class="modal-title" id="modalTitulo">¿Eliminar?</h2>
        
        <p class="modal-message">
            Vas a eliminar <strong id="modalNombre"></strong>. Esta acción no se puede deshacer.
        </p>
        
        <div class="modal-warning" id="modalAdvertencia">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="modalTextoAdvertencia"></span>
        </div>
        
        <div class="modal-acciones">
            <button type="button" class="btn-modal btn-modal-cancelar" onclick="cerrarModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <a href="#" id="modalBtnEliminar" class="btn-modal btn-modal-eliminar">
                <i class="fas fa-trash"></i> Eliminar
            </a>
        </div>
    </div>
</div>

<script>
// ============================================
// TOGGLE BÚSQUEDA
// ============================================
function toggleBusqueda(tipo) {
    if (tipo === 'conductor') {
        const wrapper = document.getElementById('buscadorConductor');
        const btn = document.querySelector('.btn-buscar-toggle');
        
        if (wrapper.classList.contains('abierto')) {
            const input = wrapper.querySelector('input');
            if (input.value.trim() === '') {
                wrapper.classList.remove('abierto');
                btn.classList.remove('activo');
            } else {
                wrapper.classList.remove('abierto');
                btn.classList.remove('activo');
            }
        } else {
            wrapper.classList.add('abierto');
            btn.classList.add('activo');
            setTimeout(() => wrapper.querySelector('input').focus(), 100);
        }
    } else if (tipo === 'vehiculo') {
        const wrapper = document.getElementById('buscadorVehiculo');
        const btn = wrapper.closest('.columna').querySelector('.btn-buscar-toggle');
        
        if (wrapper.classList.contains('abierto')) {
            wrapper.classList.remove('abierto');
            btn.classList.remove('activo');
        } else {
            wrapper.classList.add('abierto');
            btn.classList.add('activo');
            setTimeout(() => wrapper.querySelector('input').focus(), 100);
        }
    }
}

// ============================================
// MODAL DE ELIMINAR
// ============================================
function abrirModal(tipo, id, nombre) {
    const modal = document.getElementById('modalEliminar');
    const modalTitulo = document.getElementById('modalTitulo');
    const modalNombre = document.getElementById('modalNombre');
    const modalTextoAdvertencia = document.getElementById('modalTextoAdvertencia');
    const modalBtnEliminar = document.getElementById('modalBtnEliminar');
    
    if (tipo === 'conductor') {
        modalTitulo.textContent = '¿Eliminar conductor?';
        modalNombre.textContent = nombre;
        modalTextoAdvertencia.textContent = 'También se eliminarán todas sus licencias asociadas.';
        modalBtnEliminar.href = '?eliminar_conductor=' + id;
    } else if (tipo === 'vehiculo') {
        modalTitulo.textContent = '¿Eliminar vehículo?';
        modalNombre.textContent = nombre;
        modalTextoAdvertencia.textContent = 'También se eliminarán todos sus documentos (SOAT, tecnomecánica, etc).';
        modalBtnEliminar.href = '?eliminar_vehiculo=' + id;
    }
    
    modal.classList.add('active');
}

function cerrarModal() {
    document.getElementById('modalEliminar').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModal();
});

document.addEventListener('click', function(e) {
    const modal = document.getElementById('modalEliminar');
    if (e.target === modal) cerrarModal();
});

// Auto-ocultar mensaje de éxito
document.addEventListener('DOMContentLoaded', function() {
    const msgExito = document.getElementById('msgExito');
    if (msgExito) {
        setTimeout(function() {
            msgExito.style.opacity = '0';
            setTimeout(function() { msgExito.style.display = 'none'; }, 500);
        }, 2500);
    }
});
</script>

</body>
</html>