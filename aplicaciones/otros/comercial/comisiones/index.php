<?php
// aplicaciones/otros/comercial/comisiones/index.php - Vista de comisiones del asesor
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../../login.php');
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];
$nombre_usuario = $usuario['nombre_completo'];

// ✅ OBTENER cod_asesor DIRECTAMENTE
$stmt = $pdo->prepare("SELECT cod_asesor FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$cod_asesor = $stmt->fetchColumn();

// ============================================
// SI NO TIENE CÓDIGO DE ASESOR
// ============================================
if (empty($cod_asesor)) {
    $_SESSION['error'] = "❌ No tienes código de asesor asignado";
    header('Location: ../index.php');
    exit();
}

// ============================================
// MESES EN ESPAÑOL
// ============================================
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// ============================================
// TIPOS DE COMISIONES DISPONIBLES
// ============================================
$tipos_comisiones = [
    'matriculas' => 'Matrículas',
    'usados' => 'Usados',
    'accesorios' => 'Accesorios',
    'incentivos' => 'Incentivos',
    'financieras' => 'Financieras',
];

// ============================================
// TABLAS POR TIPO
// ============================================
$tablas_por_tipo = [
    'matriculas' => 'com_matriculas',
    'usados' => 'com_usados',
    'accesorios' => 'com_accesorios',
    'incentivos' => 'com_incentivos',
    'financieras' => 'com_financieras'
];

// ============================================
// COLUMNAS DISPONIBLES POR TIPO
// ============================================
$columnas_por_tipo = [
    'matriculas' => ['matriculados', 'modelo', 'vin', 'valor_venta', 'comision_real', 'bono'],
    'usados' => ['placa', 'modelo', 'vin', 'valor_venta', 'comision_real', 'bono'],
    'accesorios' => ['modelo', 'base', 'comision_real'],
    'incentivos' => ['observacion', 'comision_real'],
    'financieras' => ['concepto', 'vehiculos_vendidos', 'comision_real']
];

// ============================================
// AÑOS DISPONIBLES
// ============================================
$años = [];
for ($i = 2026; $i <= intval(date('Y')); $i++) {
    $años[] = $i;
}

// ============================================
// FILTROS (por GET)
// ============================================
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_anio = isset($_GET['anio']) ? intval($_GET['anio']) : 0;
$filtro_mes = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 10;
$offset = ($pagina - 1) * $registros_por_pagina;

// ============================================
// VERIFICAR SI SE HIZO BÚSQUEDA
// ============================================
$busqueda_realizada = !empty($filtro_tipo) && $filtro_anio > 0 && $filtro_mes > 0;

// ============================================
// VALIDAR FILTROS (solo si hay búsqueda)
// ============================================
if ($busqueda_realizada) {
    if (!array_key_exists($filtro_tipo, $tipos_comisiones)) {
        $filtro_tipo = '';
        $busqueda_realizada = false;
    }
    if ($filtro_mes < 1 || $filtro_mes > 12) {
        $filtro_mes = 0;
        $busqueda_realizada = false;
    }
    if ($filtro_anio < 2026 || $filtro_anio > intval(date('Y'))) {
        $filtro_anio = 0;
        $busqueda_realizada = false;
    }
}

// ============================================
// INICIALIZAR VARIABLES
// ============================================
$total_ventas = 0;
$total_valor_ventas = 0;
$total_comisiones = 0;
$total_bonos = 0;
$total_general = 0;
$total_registros = 0;
$total_paginas = 0;
$comisiones = [];
$nombre_mes_filtro = '';
$nombre_tipo_filtro = '';
$tiene_bono = false;

// ============================================
// SOLO SI SE HIZO BÚSQUEDA
// ============================================
if ($busqueda_realizada) {
    
    // ============================================
    // TABLA SEGÚN EL TIPO
    // ============================================
    $tabla = isset($tablas_por_tipo[$filtro_tipo]) ? $tablas_por_tipo[$filtro_tipo] : 'com_matriculas';
    
    // ============================================
    // DETECTAR COLUMNAS DISPONIBLES
    // ============================================
    $tiene_bono = in_array('bono', $columnas_por_tipo[$filtro_tipo] ?? []);
    $tiene_valor_venta = in_array('valor_venta', $columnas_por_tipo[$filtro_tipo] ?? []);
    
    $sum_bono = $tiene_bono ? "COALESCE(SUM(bono), 0)" : "0";
    $sum_valor_venta = $tiene_valor_venta ? "COALESCE(SUM(valor_venta), 0)" : "0";
    
    // ============================================
    // ESTADÍSTICAS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            $sum_valor_venta as total_valor_ventas,
            COALESCE(SUM(comision_real), 0) as total_comisiones,
            $sum_bono as total_bonos
        FROM $tabla 
        WHERE cod_vendedor = ? 
        AND MONTH(fecha_comision) = ? 
        AND YEAR(fecha_comision) = ?
    ");
    $stmt->execute([$cod_asesor, $filtro_mes, $filtro_anio]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_ventas = $stats['total_ventas'] ?? 0;
    $total_valor_ventas = $stats['total_valor_ventas'] ?? 0;
    $total_comisiones = $stats['total_comisiones'] ?? 0;
    $total_bonos = $stats['total_bonos'] ?? 0;
    $total_general = $total_comisiones + $total_bonos;

    // ============================================
    // CONTAR REGISTROS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM $tabla 
        WHERE cod_vendedor = ? 
        AND MONTH(fecha_comision) = ? 
        AND YEAR(fecha_comision) = ?
    ");
    $stmt->execute([$cod_asesor, $filtro_mes, $filtro_anio]);
    $total_registros = $stmt->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // ============================================
    // OBTENER REGISTROS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT * FROM $tabla 
        WHERE cod_vendedor = ? 
        AND MONTH(fecha_comision) = ? 
        AND YEAR(fecha_comision) = ?
        ORDER BY fecha_comision DESC
        LIMIT ?, ?
    ");
    $stmt->bindValue(1, $cod_asesor);
    $stmt->bindValue(2, $filtro_mes, PDO::PARAM_INT);
    $stmt->bindValue(3, $filtro_anio, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->bindValue(5, $registros_por_pagina, PDO::PARAM_INT);
    $stmt->execute();
    $comisiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $nombre_mes_filtro = $meses_espanol[$filtro_mes];
    $nombre_tipo_filtro = $tipos_comisiones[$filtro_tipo];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Comisiones | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER ===== */
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
        .logo h1 { color: white; font-size: 24px; }
        .logo span { color: #445960; }
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            color: white;
        }
        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .card h2 {
            font-size: 18px;
            color: #12232b;
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== FILTROS ===== */
        .filtros-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filtro-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filtro-group label {
            font-size: 13px;
            font-weight: 500;
            color: #2c3e50;
        }
        .filtro-group select {
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            background: white;
            min-width: 150px;
            transition: all 0.3s ease;
        }
        .filtro-group select:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        .btn-buscar {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-buscar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }

        /* ===== RESUMEN ===== */
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .resumen-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border-left: 4px solid #173742;
        }
        .resumen-item:hover {
            background: #e8f0fe;
            transform: translateY(-3px);
        }
        .resumen-item i {
            font-size: 28px;
            margin-bottom: 10px;
            display: block;
        }
        .resumen-item .icon-money { color: #27ae60; }
        .resumen-item .icon-bono { color: #f39c12; }
        .resumen-item .icon-ventas { color: #3498db; }
        .resumen-item .icon-total { color: #8e44ad; }
        .resumen-item .numero {
            font-size: 22px;
            font-weight: 700;
            color: #12232b;
            margin-bottom: 4px;
        }
        .resumen-item .label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
        }

        /* ===== TABLA ===== */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th {
            background: #12232b;
            color: white;
            font-weight: 600;
            font-size: 12px;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover { background: #f8f9fa; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .money { font-weight: 600; color: #27ae60; }
        .money-bono { font-weight: 600; color: #f39c12; }
        .money-venta { font-weight: 600; color: #2c3e50; }

        /* Badge de vehículos */
        .badge-vehiculos {
            display: inline-block;
            background: #e8f0fe;
            color: #173742;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        /* ===== PAGINACIÓN ===== */
        .paginacion-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        .paginacion-info {
            font-size: 13px;
            color: #7f8c8d;
        }
        .paginacion-info strong {
            color: #12232b;
        }
        .paginacion {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .paginacion a, .paginacion .pagina-actual {
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

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 60px;
            color: #d3d3d3;
            margin-bottom: 15px;
            display: block;
        }
        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 18px;
        }
        .empty-state p {
            font-size: 14px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .filtros-container { flex-direction: column; align-items: stretch; }
            .filtro-group select { width: 100%; }
            .btn-buscar { width: 100%; justify-content: center; }
            table { font-size: 11px; }
            th, td { padding: 8px 6px; }
            .paginacion-container { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Mis Comisiones</small>
            </div>
            <div class="user-info">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- ===== FILTROS ===== -->
        <div class="card">
            <h2><i class="fas fa-filter"></i> Filtrar comisiones</h2>
            
            <form method="GET" action="" class="filtros-container">
                
                <!-- Selector de Tipo -->
                <div class="filtro-group">
                    <label>Tipo</label>
                    <select name="tipo" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($tipos_comisiones as $key => $nombre): ?>
                            <option value="<?php echo $key; ?>" <?php echo $key == $filtro_tipo ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Selector de Año -->
                <div class="filtro-group">
                    <label>Año</label>
                    <select name="anio" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($años as $a): ?>
                            <option value="<?php echo $a; ?>" <?php echo $a == $filtro_anio ? 'selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Selector de Mes -->
                <div class="filtro-group">
                    <label>Mes</label>
                    <select name="mes" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($meses_espanol as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo $num == $filtro_mes ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-buscar">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </form>
        </div>

        <?php if (!$busqueda_realizada): ?>
            <!-- ===== MENSAJE DE SELECCIÓN ===== -->
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Selecciona los filtros</h3>
                    <p>Escoge el tipo, año y mes para ver tus comisiones</p>
                </div>
            </div>
        <?php else: ?>

            <!-- ===== RESUMEN ===== -->
            <div class="card">
                <h2>
                    <i class="fas fa-chart-pie"></i> 
                    Resumen de <?php echo $nombre_tipo_filtro . ' - ' . $nombre_mes_filtro . ' ' . $filtro_anio; ?>
                </h2>
                
                <div class="resumen-grid">
                    <div class="resumen-item">
                        <i class="fas fa-money-bill-wave icon-money"></i>
                        <div class="numero">$<?php echo number_format($total_comisiones, 0, ',', '.'); ?></div>
                        <div class="label">Total Comisiones</div>
                    </div>
                    
                    <?php if ($tiene_bono): ?>
                    <div class="resumen-item">
                        <i class="fas fa-gift icon-bono"></i>
                        <div class="numero">$<?php echo number_format($total_bonos, 0, ',', '.'); ?></div>
                        <div class="label">Total Bonos</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="resumen-item">
                        <i class="fas fa-shopping-cart icon-ventas"></i>
                        <div class="numero"><?php echo number_format($total_ventas, 0, ',', '.'); ?></div>
                        <div class="label">Registros</div>
                    </div>
                    
                    <div class="resumen-item">
                        <i class="fas fa-hand-holding-usd icon-total"></i>
                        <div class="numero">$<?php echo number_format($total_general, 0, ',', '.'); ?></div>
                        <div class="label">Total General</div>
                    </div>
                </div>
            </div>

            <!-- ===== TABLA DE COMISIONES ===== -->
            <div class="card">
                <h2>
                    <i class="fas fa-list"></i> 
                    Detalle de comisiones
                    <span style="font-size: 13px; font-weight: 400; color: #7f8c8d;">
                        (<?php echo $total_registros; ?> registros)
                    </span>
                </h2>
                
                <?php if (empty($comisiones)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No hay comisiones registradas</h3>
                        <p>No tienes comisiones de <?php echo $nombre_tipo_filtro; ?> para <?php echo $nombre_mes_filtro . ' ' . $filtro_anio; ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <?php if ($filtro_tipo == 'incentivos'): ?>
                                        <th>Observación</th>
                                    <?php elseif ($filtro_tipo == 'financieras'): ?>
                                        <th>Concepto</th>
                                        <th class="text-center">Vehículos</th>
                                    <?php else: ?>
                                        <th>Cliente</th>
                                        <?php if ($filtro_tipo == 'matriculas'): ?>
                                            <th>Matriculados</th>
                                        <?php elseif ($filtro_tipo == 'usados'): ?>
                                            <th>Placa</th>
                                        <?php elseif ($filtro_tipo == 'accesorios'): ?>
                                            <th>Modelo</th>
                                        <?php endif; ?>
                                        <?php if ($filtro_tipo != 'accesorios'): ?>
                                            <th>Modelo</th>
                                            <th>VIN</th>
                                        <?php endif; ?>
                                        <?php if ($filtro_tipo == 'accesorios'): ?>
                                            <th class="text-right">Base</th>
                                        <?php else: ?>
                                            <th class="text-right">Valor Venta</th>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <th class="text-right">Comisión</th>
                                    <?php if ($tiene_bono): ?>
                                        <th class="text-right">Bono</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comisiones as $com): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($com['fecha_comision'])); ?></td>
                                        
                                        <?php if ($filtro_tipo == 'incentivos'): ?>
                                            <td><?php echo htmlspecialchars($com['observacion'] ?? ''); ?></td>
                                        <?php elseif ($filtro_tipo == 'financieras'): ?>
                                            <td><?php echo htmlspecialchars($com['concepto'] ?? ''); ?></td>
                                            <td class="text-center">
                                                <span class="badge-vehiculos">
                                                    <?php echo intval($com['vehiculos_vendidos'] ?? 0); ?>
                                                </span>
                                            </td>
                                        <?php else: ?>
                                            <td><?php echo htmlspecialchars($com['cliente'] ?? ''); ?></td>
                                            
                                            <?php if ($filtro_tipo == 'matriculas'): ?>
                                                <td><?php echo htmlspecialchars($com['matriculados'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($com['modelo']); ?></td>
                                                <td style="font-size: 11px; color: #7f8c8d;"><?php echo htmlspecialchars($com['vin']); ?></td>
                                                <td class="text-right money-venta">$<?php echo number_format($com['valor_venta'], 0, ',', '.'); ?></td>
                                            <?php elseif ($filtro_tipo == 'usados'): ?>
                                                <td><?php echo htmlspecialchars($com['placa'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($com['modelo']); ?></td>
                                                <td style="font-size: 11px; color: #7f8c8d;"><?php echo htmlspecialchars($com['vin']); ?></td>
                                                <td class="text-right money-venta">$<?php echo number_format($com['valor_venta'], 0, ',', '.'); ?></td>
                                            <?php elseif ($filtro_tipo == 'accesorios'): ?>
                                                <td><?php echo htmlspecialchars($com['modelo']); ?></td>
                                                <td class="text-right money-venta">$<?php echo number_format($com['base'], 0, ',', '.'); ?></td>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <td class="text-right money">$<?php echo number_format($com['comision_real'], 0, ',', '.'); ?></td>
                                        
                                        <?php if ($tiene_bono): ?>
                                            <td class="text-right money-bono">$<?php echo number_format($com['bono'], 0, ',', '.'); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- ===== PAGINACIÓN ===== -->
                    <?php if ($total_paginas > 1): ?>
                    <div class="paginacion-container">
                        <div class="paginacion-info">
                            <i class="fas fa-info-circle"></i> 
                            Mostrando <strong><?php echo count($comisiones); ?></strong> de <strong><?php echo $total_registros; ?></strong> registros
                        </div>

                        <div class="paginacion">
                            <!-- Primera página -->
                            <?php if ($pagina > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>" title="Primera página">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" title="Página anterior">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Páginas -->
                            <?php
                            $rango = 2;
                            $inicio = max(1, $pagina - $rango);
                            $fin = min($total_paginas, $pagina + $rango);

                            if ($inicio > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">1</a>
                                <?php if ($inicio > 2): ?>
                                    <span class="pagina-puntos">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                                <?php if ($i == $pagina): ?>
                                    <span class="pagina-actual"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($fin < $total_paginas): ?>
                                <?php if ($fin < $total_paginas - 1): ?>
                                    <span class="pagina-puntos">…</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>"><?php echo $total_paginas; ?></a>
                            <?php endif; ?>

                            <!-- Siguiente -->
                            <?php if ($pagina < $total_paginas): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" title="Página siguiente">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>" title="Última página">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>