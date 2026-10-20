<?php
// admin/operacion_vehicular/rutas.php - Admin ve todas las rutas
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Admin (1) y Supervisor (2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para acceder a Operación Vehicular";
    header('Location: ../../index.php');
    exit();
}

// ============================================
// ✅ MENSAJES FLASH
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
// ✅ FILTROS (GET)
// ============================================
$filtro_estado = $_GET['estado'] ?? '';
$filtro_conductor = isset($_GET['conductor']) ? (int) $_GET['conductor'] : 0;
$filtro_vehiculo = isset($_GET['vehiculo']) ? (int) $_GET['vehiculo'] : 0;
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// ============================================
// ✅ PAGINACIÓN
// ============================================
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$registros_por_pagina = 10;
$offset = ($pagina - 1) * $registros_por_pagina;

// ============================================
// ✅ CONSTRUIR WHERE
// ============================================
$where = [];
$params = [];

// Filtro por estado
if (!empty($filtro_estado)) {
    $estados_validos = ['EN_CURSO', 'FINALIZADO', 'CANCELADO'];
    if (in_array($filtro_estado, $estados_validos)) {
        $where[] = "r.estado = ?";
        $params[] = $filtro_estado;
    }
}

// Filtro por conductor
if ($filtro_conductor > 0) {
    $where[] = "r.conductor_id = ?";
    $params[] = $filtro_conductor;
}

// Filtro por vehículo
if ($filtro_vehiculo > 0) {
    $where[] = "r.vehiculo_id = ?";
    $params[] = $filtro_vehiculo;
}

// Filtro por fecha desde
if (!empty($filtro_fecha_desde)) {
    $where[] = "DATE(r.fecha_salida) >= ?";
    $params[] = $filtro_fecha_desde;
}

// Filtro por fecha hasta
if (!empty($filtro_fecha_hasta)) {
    $where[] = "DATE(r.fecha_salida) <= ?";
    $params[] = $filtro_fecha_hasta;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ============================================
// ✅ CONTAR TOTAL
// ============================================
$sql_count = "
    SELECT COUNT(*) as total
    FROM ope_ruta r
    $where_clause
";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// ============================================
// ✅ OBTENER RUTAS CON PAGINACIÓN
// ✅ CORREGIDO: Todo con parámetros POSICIONALES (?) para evitar HY093
// ============================================
$sql = "
    SELECT 
        r.id, r.fecha_salida, r.fecha_regreso, r.fecha_finalizacion,
        r.ciudad_origen, r.ciudad_destino, r.estado, r.motivo_viaje,
        r.observaciones_cierre,
        v.placa, v.marca, v.modelo, v.tipo AS tipo_vh,
        u.nombre_completo AS conductor_nombre,
        u.usuario AS conductor_usuario,
        u.cargo AS conductor_cargo,
        c.id AS conductor_id,
        v.id AS vehiculo_id
    FROM ope_ruta r
    LEFT JOIN ope_vehiculos v ON r.vehiculo_id = v.id
    LEFT JOIN ope_conductores c ON r.conductor_id = c.id
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    $where_clause
    ORDER BY r.fecha_salida DESC
    LIMIT ?, ?
";

// ✅ Agregar offset y límite como POSICIONALES al final
$params_con_limite = $params;
$params_con_limite[] = $offset;
$params_con_limite[] = $registros_por_pagina;

$stmt = $pdo->prepare($sql);
$stmt->execute($params_con_limite);
$rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ KPIs
// ============================================
$stmt = $pdo->query("SELECT COUNT(*) FROM ope_ruta");
$kpi_total = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM ope_ruta WHERE estado = 'EN_CURSO'");
$kpi_en_curso = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM ope_ruta WHERE estado = 'FINALIZADO'");
$kpi_finalizadas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM ope_ruta WHERE estado = 'CANCELADO'");
$kpi_canceladas = $stmt->fetchColumn();

// ============================================
// ✅ LISTAS PARA FILTROS
// ============================================
// Conductores
$stmt = $pdo->query("
    SELECT c.id, u.nombre_completo
    FROM ope_conductores c
    INNER JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.activo = 1
    ORDER BY u.nombre_completo ASC
");
$conductores_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vehículos
$stmt = $pdo->query("
    SELECT id, placa, marca, modelo
    FROM ope_vehiculos
    WHERE activo = 1
    ORDER BY placa ASC
");
$vehiculos_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ FUNCIÓN: Calcular duración
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
    
    return empty($partes) ? '< 1 min' : implode(' ', $partes);
}

// ============================================
// ✅ HELPERS PARA BADGES
// ============================================
function badgeEstado($estado) {
    $mapa = [
        'EN_CURSO' => ['clase' => 'en-curso', 'texto' => '🟡 En Curso'],
        'FINALIZADO' => ['clase' => 'finalizado', 'texto' => '🟢 Finalizada'],
        'CANCELADO' => ['clase' => 'cancelado', 'texto' => '🔴 Cancelada']
    ];
    return $mapa[$estado] ?? ['clase' => 'sin-datos', 'texto' => $estado];
}

function badgeTipo($tipo) {
    $mapa = [
        'NUEVO' => ['clase' => 'nuevo', 'texto' => 'NUEVO'],
        'TEST_DRIVE' => ['clase' => 'test', 'texto' => 'TEST DRIVE'],
        'USADO' => ['clase' => 'usado', 'texto' => 'USADO']
    ];
    return $mapa[$tipo] ?? ['clase' => 'sin-datos', 'texto' => $tipo];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rutas | Operación Vehicular</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #d3d3d3;
            color: #2c3e50;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ========== HEADER ========== */
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
        
        .header-botones {
            display: flex;
            gap: 10px;
        }
        
        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        .btn-nuevo {
            background: #27ae60;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-nuevo:hover {
            background: #219a52;
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
        
        /* ========== KPIs ========== */
        .kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid #173742;
        }
        
        .kpi-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            background: #173742;
        }
        
        .kpi-icon.info { background: #3498db; }
        .kpi-icon.warning { background: #f39c12; }
        .kpi-icon.success { background: #27ae60; }
        .kpi-icon.danger { background: #e74c3c; }
        
        .kpi-info h3 {
            font-size: 22px;
            color: #12232b;
            font-weight: 700;
        }
        
        .kpi-info p {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* ========== FILTROS ========== */
        .filtros-card {
            background: white;
            border-radius: 12px;
            padding: 20px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .filtros-titulo {
            font-size: 15px;
            color: #12232b;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filtros-titulo i { color: #173742; }
        
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .filtro-campo {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filtro-campo label {
            font-size: 11px;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filtro-campo select,
        .filtro-campo input[type="date"] {
            padding: 9px 12px;
            border: 2px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .filtro-campo select:focus,
        .filtro-campo input[type="date"]:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        
        .filtros-acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-filtrar {
            background: #173742;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-filtrar:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .btn-limpiar {
            background: #7f8c8d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-limpiar:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }
        
        /* ========== TABLA ========== */
        .tabla-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .tabla-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        thead {
            background: #12232b;
        }
        
        thead th {
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        tbody td {
            padding: 12px 10px;
            vertical-align: middle;
        }
        
        /* ========== CELDAS ESPECIALES ========== */
        .celda-placa {
            font-weight: 700;
            color: #12232b;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .celda-conductor {
            display: flex;
            flex-direction: column;
        }
        
        .celda-conductor .nombre {
            font-weight: 600;
            color: #12232b;
            font-size: 13px;
        }
        
        .celda-conductor .cargo {
            font-size: 11px;
            color: #7f8c8d;
        }
        
        .celda-ruta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
        }
        
        .celda-ruta .ciudad {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .celda-ruta i {
            color: #95a5a6;
            font-size: 10px;
        }
        
        .celda-fechas {
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 12px;
        }
        
        .celda-fechas .fecha {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #7f8c8d;
        }
        
        .celda-fechas .fecha strong {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .celda-fechas i {
            font-size: 10px;
            color: #95a5a6;
        }
        
        .duracion-badge {
            background: #e8f4f8;
            color: #1a5276;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 3px;
        }
        
        /* ========== BADGES ========== */
        .badge-estado {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-estado.en-curso { background: #fdebd0; color: #a04000; }
        .badge-estado.finalizado { background: #d5f5e3; color: #1a7a3a; }
        .badge-estado.cancelado { background: #fadbd8; color: #922b21; }
        .badge-estado.sin-datos { background: #ecf0f1; color: #7f8c8d; }
        
        .badge-tipo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 9px;
            font-weight: 700;
            margin-left: 6px;
            letter-spacing: 0.5px;
        }
        
        .badge-tipo.nuevo { background: #27ae60; color: white; }
        .badge-tipo.test { background: #8e44ad; color: white; }
        .badge-tipo.usado { background: #3498db; color: white; }
        .badge-tipo.sin-datos { background: #bdc3c7; color: white; }
        
        /* ========== BOTÓN VER ========== */
        .btn-ver {
            background: #3498db;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        
        .btn-ver:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #d3d3d3;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        /* ========== PAGINACIÓN ========== */
        .paginacion-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }
        
        .paginacion-info {
            font-size: 13px;
            color: #7f8c8d;
        }
        
        .paginacion-info strong { color: #12232b; }
        
        .paginacion {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .paginacion a,
        .paginacion .pagina-actual {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .paginacion a {
            background: #f0f0f0;
            color: #2c3e50;
        }
        
        .paginacion a:hover {
            background: #173742;
            color: white;
            transform: translateY(-2px);
        }
        
        .paginacion .pagina-actual {
            background: #173742;
            color: white;
        }
        
        .paginacion .pagina-puntos {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .filtros-grid { grid-template-columns: 1fr; }
            .kpis-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- ========== HEADER ========== -->
<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">Operación Vehicular | Rutas</div>
        </div>
        <div class="header-botones">
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al panel
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
    
    <!-- ===== KPIs ===== -->
    <div class="kpis-grid">
        <div class="kpi-card">
            <div class="kpi-icon info">
                <i class="fas fa-route"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $kpi_total; ?></h3>
                <p>Total rutas</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon warning">
                <i class="fas fa-car-side"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $kpi_en_curso; ?></h3>
                <p>En curso</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $kpi_finalizadas; ?></h3>
                <p>Finalizadas</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $kpi_canceladas; ?></h3>
                <p>Canceladas</p>
            </div>
        </div>
    </div>
    
    <!-- ===== FILTROS ===== -->
    <div class="filtros-card">
        <div class="filtros-titulo">
            <i class="fas fa-filter"></i>
            Filtros
        </div>
        
        <form method="GET" action="">
            <div class="filtros-grid">
                
                <div class="filtro-campo">
                    <label for="estado">Estado</label>
                    <select name="estado" id="estado">
                        <option value="">Todos</option>
                        <option value="EN_CURSO" <?php echo $filtro_estado === 'EN_CURSO' ? 'selected' : ''; ?>>🟡 En Curso</option>
                        <option value="FINALIZADO" <?php echo $filtro_estado === 'FINALIZADO' ? 'selected' : ''; ?>>🟢 Finalizada</option>
                        <option value="CANCELADO" <?php echo $filtro_estado === 'CANCELADO' ? 'selected' : ''; ?>>🔴 Cancelada</option>
                    </select>
                </div>
                
                <div class="filtro-campo">
                    <label for="conductor">Conductor</label>
                    <select name="conductor" id="conductor">
                        <option value="">Todos</option>
                        <?php foreach ($conductores_lista as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filtro_conductor == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-campo">
                    <label for="vehiculo">Vehículo</label>
                    <select name="vehiculo" id="vehiculo">
                        <option value="">Todos</option>
                        <?php foreach ($vehiculos_lista as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo $filtro_vehiculo == $v['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['placa']); ?> - <?php echo htmlspecialchars($v['marca']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-campo">
                    <label for="fecha_desde">Desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                </div>
                
                <div class="filtro-campo">
                    <label for="fecha_hasta">Hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                </div>
                
            </div>
            
            <div class="filtros-acciones">
                <button type="submit" class="btn-filtrar">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <?php if (!empty($filtro_estado) || $filtro_conductor > 0 || $filtro_vehiculo > 0 || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)): ?>
                    <a href="rutas.php" class="btn-limpiar">
                        <i class="fas fa-times"></i> Limpiar filtros
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- ===== TABLA ===== -->
    <div class="tabla-card">
        
        <?php if (count($rutas) > 0): ?>
            <div class="tabla-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Vehículo</th>
                            <th>Conductor</th>
                            <th>Ruta</th>
                            <th>Fechas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rutas as $ruta): 
                            $estado_info = badgeEstado($ruta['estado']);
                            $tipo_info = badgeTipo($ruta['tipo_vh'] ?? null);
                            $duracion = null;
                            if ($ruta['estado'] === 'FINALIZADO' && !empty($ruta['fecha_finalizacion'])) {
                                $duracion = calcularDuracion($ruta['fecha_salida'], $ruta['fecha_finalizacion']);
                            }
                        ?>
                            <tr>
                                <td>
                                    <span class="celda-placa"><?php echo htmlspecialchars($ruta['placa'] ?? '—'); ?></span>
                                    <span class="badge-tipo <?php echo $tipo_info['clase']; ?>">
                                        <?php echo $tipo_info['texto']; ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <div class="celda-conductor">
                                        <span class="nombre"><?php echo htmlspecialchars($ruta['conductor_nombre'] ?? '—'); ?></span>
                                        <?php if (!empty($ruta['conductor_cargo'])): ?>
                                            <span class="cargo"><?php echo htmlspecialchars($ruta['conductor_cargo']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <?php if (!empty($ruta['ciudad_origen']) || !empty($ruta['ciudad_destino'])): ?>
                                        <div class="celda-ruta">
                                            <span class="ciudad"><?php echo htmlspecialchars($ruta['ciudad_origen'] ?? '—'); ?></span>
                                            <i class="fas fa-arrow-right"></i>
                                            <span class="ciudad"><?php echo htmlspecialchars($ruta['ciudad_destino'] ?? '—'); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #95a5a6; font-size: 12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div class="celda-fechas">
                                        <span class="fecha">
                                            <i class="fas fa-sign-out-alt"></i>
                                            <strong>Salida:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_salida'])); ?>
                                        </span>
                                        <?php if ($ruta['estado'] === 'FINALIZADO' && !empty($ruta['fecha_finalizacion'])): ?>
                                            <span class="fecha">
                                                <i class="fas fa-check-circle"></i>
                                                <strong>Fin:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_finalizacion'])); ?>
                                            </span>
                                            <?php if ($duracion): ?>
                                                <span class="duracion-badge">
                                                    <i class="fas fa-hourglass-half"></i>
                                                    <?php echo $duracion; ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif (!empty($ruta['fecha_regreso'])): ?>
                                            <span class="fecha">
                                                <i class="fas fa-sign-in-alt"></i>
                                                <strong>Est.:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_regreso'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <span class="badge-estado <?php echo $estado_info['clase']; ?>">
                                        <?php echo $estado_info['texto']; ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <a href="ver.php?id=<?php echo $ruta['id']; ?>" class="btn-ver">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ===== PAGINACIÓN ===== -->
            <?php if ($total_paginas > 1): ?>
                <div class="paginacion-container">
                    <div class="paginacion-info">
                        Mostrando <strong><?php echo count($rutas); ?></strong> de <strong><?php echo $total_registros; ?></strong> rutas
                    </div>
                    
                    <div class="paginacion">
                        <?php
                        $query_params = $_GET;
                        
                        // Anterior
                        if ($pagina > 1):
                            $query_params['pagina'] = $pagina - 1;
                        ?>
                            <a href="?<?php echo http_build_query($query_params); ?>" title="Anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $rango = 2;
                        $inicio = max(1, $pagina - $rango);
                        $fin = min($total_paginas, $pagina + $rango);
                        
                        if ($inicio > 1):
                            $query_params['pagina'] = 1;
                        ?>
                            <a href="?<?php echo http_build_query($query_params); ?>">1</a>
                            <?php if ($inicio > 2): ?>
                                <span class="pagina-puntos">…</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $inicio; $i <= $fin; $i++): 
                            $query_params['pagina'] = $i;
                        ?>
                            <?php if ($i == $pagina): ?>
                                <span class="pagina-actual"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query($query_params); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($fin < $total_paginas):
                            $query_params['pagina'] = $total_paginas;
                        ?>
                            <?php if ($fin < $total_paginas - 1): ?>
                                <span class="pagina-puntos">…</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query($query_params); ?>"><?php echo $total_paginas; ?></a>
                        <?php endif; ?>
                        
                        <!-- Siguiente -->
                        <?php if ($pagina < $total_paginas):
                            $query_params['pagina'] = $pagina + 1;
                        ?>
                            <a href="?<?php echo http_build_query($query_params); ?>" title="Siguiente">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-route"></i>
                <h3>No hay rutas registradas</h3>
                <p>
                    <?php if (!empty($filtro_estado) || $filtro_conductor > 0 || $filtro_vehiculo > 0 || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)): ?>
                        No hay rutas que coincidan con los filtros aplicados.
                    <?php else: ?>
                        Aún no se han registrado rutas en el sistema.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        
    </div>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const msgExito = document.getElementById('msgExito');
    if (msgExito) {
        setTimeout(function() {
            msgExito.style.opacity = '0';
            setTimeout(function() { msgExito.style.display = 'none'; }, 500);
        }, 3000);
    }
});
</script>

</body>
</html>