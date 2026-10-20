<?php
// aplicaciones/otros/operacion_vehicular/historial.php - Historial de rutas del conductor
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
    SELECT c.id, c.activo,
           u.nombre_completo, u.usuario, u.cargo, u.sede
    FROM ope_conductores c
    INNER JOIN usuarios u ON c.usuario_id = u.id
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
            .btn-volver:hover { background: #445960; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><i class="fas fa-user-slash"></i></div>
            <h1 class="error-title">Acceso Denegado</h1>
            <p class="error-message">No tienes permisos para acceder a este historial.<br>Solo los conductores registrados pueden ver esta sección.</p>
            <a href="index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al portal</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$conductor_id = $conductor['id'];

// ============================================
// ✅ VERIFICAR SI TIENE RUTA ACTIVA (para el botón crear)
// ============================================
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM ope_ruta 
    WHERE conductor_id = ? AND estado = 'EN_CURSO'
");
$stmt->execute([$conductor_id]);
$tiene_ruta_activa = $stmt->fetchColumn() > 0;

// ============================================
// ✅ FILTRO POR ESTADO
// ============================================
$filtro_estado = $_GET['estado'] ?? '';

// ============================================
// ✅ PAGINACIÓN
// ============================================
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$registros_por_pagina = 10;
$offset = ($pagina - 1) * $registros_por_pagina;

// ============================================
// ✅ CONSTRUIR WHERE
// ============================================
$where = ["r.conductor_id = ?"];
$params = [$conductor_id];

// Filtro por estado
if (!empty($filtro_estado)) {
    $estados_validos = ['EN_CURSO', 'FINALIZADO', 'CANCELADO'];
    if (in_array($filtro_estado, $estados_validos)) {
        $where[] = "r.estado = ?";
        $params[] = $filtro_estado;
    }
}

$where_clause = "WHERE " . implode(" AND ", $where);

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
// ============================================
$sql = "
    SELECT 
        r.id, r.fecha_salida, r.fecha_regreso, r.fecha_finalizacion,
        r.ciudad_origen, r.ciudad_destino, r.estado,
        r.observaciones_cierre,
        v.placa, v.marca, v.modelo, v.tipo AS tipo_vh
    FROM ope_ruta r
    LEFT JOIN ope_vehiculos v ON r.vehiculo_id = v.id
    $where_clause
    ORDER BY r.fecha_salida DESC
    LIMIT ?, ?
";

$params_con_limite = $params;
$params_con_limite[] = $offset;
$params_con_limite[] = $registros_por_pagina;

$stmt = $pdo->prepare($sql);
$stmt->execute($params_con_limite);
$rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ KPIs
// ============================================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ope_ruta WHERE conductor_id = ?");
$stmt->execute([$conductor_id]);
$kpi_total = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ope_ruta WHERE conductor_id = ? AND estado = 'EN_CURSO'");
$stmt->execute([$conductor_id]);
$kpi_en_curso = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ope_ruta WHERE conductor_id = ? AND estado = 'FINALIZADO'");
$stmt->execute([$conductor_id]);
$kpi_finalizadas = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ope_ruta WHERE conductor_id = ? AND estado = 'CANCELADO'");
$stmt->execute([$conductor_id]);
$kpi_canceladas = $stmt->fetchColumn();

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
    <title>Mi Historial de Rutas | Operación Vehicular</title>
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
        
        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
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
        
        /* ========== BARRA ACCIONES ========== */
        .acciones-card {
            background: white;
            border-radius: 12px;
            padding: 20px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filtros-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            flex: 1;
        }
        
        .filtro-campo {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 180px;
        }
        
        .filtro-campo label {
            font-size: 11px;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filtro-campo select {
            padding: 9px 12px;
            border: 2px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .filtro-campo select:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
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
            align-self: flex-end;
        }
        
        .btn-filtrar:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .btn-limpiar {
            background: #7f8c8d;
            color: white;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            align-self: flex-end;
        }
        
        .btn-limpiar:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }
        
        .btn-crear {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-crear:hover {
            background: #219a52;
            transform: translateY(-2px);
        }
        
        .btn-crear.disabled {
            background: #95a5a6;
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
        
        /* ========== CARD CONTENEDOR ========== */
        .lista-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .lista-titulo {
            font-size: 16px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .lista-titulo i { color: #173742; }
        
        .lista-titulo .badge-count {
            background: #173742;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* ========== RUTAS CARDS ========== */
        .rutas-lista {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .ruta-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 16px 18px;
            border-left: 4px solid #173742;
            transition: all 0.2s ease;
        }
        
        .ruta-item:hover {
            background: #f0f2f4;
            transform: translateX(3px);
        }
        
        .ruta-item.en-curso { border-left-color: #f39c12; }
        .ruta-item.finalizado { border-left-color: #27ae60; }
        .ruta-item.cancelado { border-left-color: #e74c3c; }
        
        .ruta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .ruta-placa-grupo {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .ruta-placa {
            font-weight: 700;
            color: #12232b;
            font-size: 16px;
            letter-spacing: 0.5px;
        }
        
        .badge-tipo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .badge-tipo.nuevo { background: #27ae60; color: white; }
        .badge-tipo.test { background: #8e44ad; color: white; }
        .badge-tipo.usado { background: #3498db; color: white; }
        .badge-tipo.sin-datos { background: #bdc3c7; color: white; }
        
        .badge-estado {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-estado.en-curso { background: #fdebd0; color: #a04000; }
        .badge-estado.finalizado { background: #d5f5e3; color: #1a7a3a; }
        .badge-estado.cancelado { background: #fadbd8; color: #922b21; }
        .badge-estado.sin-datos { background: #ecf0f1; color: #7f8c8d; }
        
        /* ========== RUTA INFO ========== */
        .ruta-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .ruta-info-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #7f8c8d;
        }
        
        .ruta-info-item i {
            font-size: 11px;
            color: #95a5a6;
        }
        
        .ruta-info-item strong {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .ruta-ciudad {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #2c3e50;
        }
        
        .ruta-ciudad .ciudad {
            font-weight: 600;
        }
        
        .ruta-ciudad i {
            color: #95a5a6;
            font-size: 10px;
        }
        
        .duracion-badge {
            background: #e8f4f8;
            color: #1a5276;
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ========== OBSERVACIONES ========== */
        .ruta-observaciones {
            background: #fef3c7;
            border-left: 3px solid #f39c12;
            color: #92400e;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 10px;
            line-height: 1.5;
        }
        
        .ruta-observaciones strong { font-weight: 700; }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 56px;
            color: #d3d3d3;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 13px;
            margin-bottom: 20px;
        }
        
        .btn-empty {
            background: #27ae60;
            color: white;
            padding: 10px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-empty:hover {
            background: #219a52;
            transform: translateY(-2px);
        }
        
        /* ========== PAGINACIÓN ========== */
        .paginacion-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
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
            .kpis-grid { grid-template-columns: 1fr 1fr; }
            .acciones-card { flex-direction: column; align-items: stretch; }
            .filtro-campo { min-width: auto; }
            .ruta-info { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ========== HEADER ========== -->
<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">Mi Historial de Rutas</div>
        </div>
        <a href="index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al portal
        </a>
    </div>
</header>

<div class="container">
    
    <!-- ===== KPIs ===== -->
    <div class="kpis-grid">
        <div class="kpi-card">
            <div class="kpi-icon info">
                <i class="fas fa-route"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo $kpi_total; ?></h3>
                <p>Mis rutas totales</p>
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
    
    <!-- ===== ACCIONES Y FILTROS ===== -->
    <div class="acciones-card">
        <form method="GET" action="" class="filtros-form">
            <div class="filtro-campo">
                <label for="estado">Filtrar por estado</label>
                <select name="estado" id="estado">
                    <option value="">Todas mis rutas</option>
                    <option value="EN_CURSO" <?php echo $filtro_estado === 'EN_CURSO' ? 'selected' : ''; ?>>🟡 En Curso</option>
                    <option value="FINALIZADO" <?php echo $filtro_estado === 'FINALIZADO' ? 'selected' : ''; ?>>🟢 Finalizadas</option>
                    <option value="CANCELADO" <?php echo $filtro_estado === 'CANCELADO' ? 'selected' : ''; ?>>🔴 Canceladas</option>
                </select>
            </div>
            
            <button type="submit" class="btn-filtrar">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            
            <?php if (!empty($filtro_estado)): ?>
                <a href="historial.php" class="btn-limpiar">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- ===== LISTA DE RUTAS ===== -->
    <div class="lista-card">
        <div class="lista-titulo">
            <span>
                <i class="fas fa-route"></i>
                Mis rutas
            </span>
            <span class="badge-count"><?php echo $total_registros; ?></span>
        </div>
        
        <?php if (count($rutas) > 0): ?>
            <div class="rutas-lista">
                <?php foreach ($rutas as $ruta): 
                    $estado_info = badgeEstado($ruta['estado']);
                    $tipo_info = badgeTipo($ruta['tipo_vh'] ?? null);
                    $clase_estado = strtolower(str_replace('_', '-', $ruta['estado']));
                    
                    $duracion = null;
                    if ($ruta['estado'] === 'FINALIZADO' && !empty($ruta['fecha_finalizacion'])) {
                        $duracion = calcularDuracion($ruta['fecha_salida'], $ruta['fecha_finalizacion']);
                    }
                ?>
                    <div class="ruta-item <?php echo $clase_estado; ?>">
                        
                        <div class="ruta-header">
                            <div class="ruta-placa-grupo">
                                <span class="ruta-placa">
                                    <?php echo htmlspecialchars($ruta['placa'] ?? 'Sin VH'); ?>
                                </span>
                                <?php if (!empty($ruta['tipo_vh'])): ?>
                                    <span class="badge-tipo <?php echo $tipo_info['clase']; ?>">
                                        <?php echo $tipo_info['texto']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="badge-estado <?php echo $estado_info['clase']; ?>">
                                <?php echo $estado_info['texto']; ?>
                            </span>
                        </div>
                        
                        <div class="ruta-info">
                            
                            <?php if (!empty($ruta['ciudad_origen']) || !empty($ruta['ciudad_destino'])): ?>
                                <div class="ruta-ciudad">
                                    <span class="ciudad"><?php echo htmlspecialchars($ruta['ciudad_origen'] ?? '—'); ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="ciudad"><?php echo htmlspecialchars($ruta['ciudad_destino'] ?? '—'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="ruta-info-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <strong>Salida:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_salida'])); ?>
                            </div>
                            
                            <?php if ($ruta['estado'] === 'FINALIZADO' && !empty($ruta['fecha_finalizacion'])): ?>
                                <div class="ruta-info-item">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>Fin:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_finalizacion'])); ?>
                                </div>
                            <?php elseif (!empty($ruta['fecha_regreso'])): ?>
                                <div class="ruta-info-item">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <strong>Est.:</strong> <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_regreso'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($duracion): ?>
                                <span class="duracion-badge">
                                    <i class="fas fa-hourglass-half"></i>
                                    <?php echo $duracion; ?>
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
            
            <!-- ===== PAGINACIÓN ===== -->
            <?php if ($total_paginas > 1): ?>
                <div class="paginacion-container">
                    <div class="paginacion-info">
                        Mostrando <strong><?php echo count($rutas); ?></strong> de <strong><?php echo $total_registros; ?></strong> rutas
                        <?php if (!empty($filtro_estado)): ?>
                            | Filtro: <strong><?php echo badgeEstado($filtro_estado)['texto']; ?></strong>
                        <?php endif; ?>
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
                <?php if (!empty($filtro_estado)): ?>
                    <h3>No hay rutas con este filtro</h3>
                    <p>No tienes rutas en estado <strong><?php echo badgeEstado($filtro_estado)['texto']; ?></strong></p>
                    <a href="historial.php" class="btn-empty">
                        <i class="fas fa-times"></i> Ver todas mis rutas
                    </a>
                <?php else: ?>
                    <h3>Aún no tienes rutas registradas</h3>
                    <p>Crea tu primera ruta para empezar</p>
                    <?php if (!$tiene_ruta_activa): ?>
                        <a href="ruta.php" class="btn-empty">
                            <i class="fas fa-plus-circle"></i> Crear mi primera ruta
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
    
</div>

</body>
</html>