<?php
// aplicaciones/otros/comercial/estadisticas/index.php - Estadísticas del asesor
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
// AÑOS DISPONIBLES
// ============================================
$años = [];
for ($i = 2026; $i <= intval(date('Y')); $i++) {
    $años[] = $i;
}

// ============================================
// MES VENCIDO (por defecto)
// ============================================
$mes_vencido = date('n') - 1;
$anio_vencido = date('Y');
if ($mes_vencido < 1) {
    $mes_vencido = 12;
    $anio_vencido = date('Y') - 1;
}

// ============================================
// TIPOS DE COMISIONES DISPONIBLES
// ============================================
$tipos_comisiones = [
    'todos' => 'Todos',
    'matriculas' => 'Matrículas',
    'accesorios' => 'Accesorios',
    'usados' => 'Usados',
    'financieras' => 'Financieras',
    'incentivos' => 'Incentivos'
];

// ============================================
// TABLAS DISPONIBLES (solo las que existen)
// ============================================
$tablas_disponibles = [
    'matriculas' => 'com_matriculas',
    'usados' => 'com_usados',
    'accesorios' => 'com_accesorios',
    'incentivos' => 'com_incentivos',
    'financieras' => 'com_financieras',
];

// ============================================
// COLUMNAS DISPONIBLES POR TABLA
// ============================================
$columnas_por_tabla = [
    'com_matriculas' => ['comision_real', 'bono', 'valor_venta', 'modelo'],
    'com_usados' => ['comision_real', 'bono', 'valor_venta', 'modelo'],
    'com_accesorios' => ['comision_real', 'base', 'modelo'],
    'com_incentivos' => ['comision_real'],
    'com_financieras' => ['comision_real', 'vehiculos_vendidos']
];

// ============================================
// FILTROS
// ============================================
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_anio = isset($_GET['anio']) ? intval($_GET['anio']) : 0;
$filtro_mes = isset($_GET['mes']) ? intval($_GET['mes']) : -1;

// Verificar si se hizo búsqueda
$busqueda_realizada = (!empty($filtro_tipo) && $filtro_anio > 0 && $filtro_mes >= 0);

// Validar tipo
if ($busqueda_realizada && !array_key_exists($filtro_tipo, $tipos_comisiones)) {
    $filtro_tipo = '';
    $busqueda_realizada = false;
}

// Validar año
if ($busqueda_realizada && ($filtro_anio < 2026 || $filtro_anio > intval(date('Y')))) {
    $filtro_anio = 0;
    $busqueda_realizada = false;
}

// Si el mes es 0, significa "todo el año"
$ver_todo_anio = ($filtro_mes === 0);

// ============================================
// INICIALIZAR VARIABLES
// ============================================
$total_ventas_mes = 0;
$total_comisiones_mes = 0;
$total_bonos_mes = 0;
$total_general_mes = 0;
$ranking = 1;
$total_asesores = 0;
$texto_comparacion = '';
$porcentaje_comisiones = 0;
$porcentaje_ventas = 0;
$datos_comisiones = array_fill(1, 12, 0);
$datos_ventas = array_fill(1, 12, 0);
$top_modelos = [];
$max_ventas_modelo = 0;
$tiene_bono_seleccionado = true; // Bandera para saber si mostrar bonos

// ============================================
// DETERMINAR TABLAS A CONSULTAR
// ============================================
if ($busqueda_realizada) {
    
    if ($filtro_tipo === 'todos') {
        $tablas_a_usar = array_values($tablas_disponibles);
    } else {
        if (isset($tablas_disponibles[$filtro_tipo])) {
            $tablas_a_usar = [$tablas_disponibles[$filtro_tipo]];
        } else {
            $tablas_a_usar = [];
        }
    }
    
    if (empty($tablas_a_usar)) {
        $busqueda_realizada = false;
    }
    
    // Detectar si ALGUNA de las tablas usadas tiene bono
    $tiene_bono_seleccionado = false;
    foreach ($tablas_a_usar as $t) {
        if (in_array('bono', $columnas_por_tabla[$t] ?? [])) {
            $tiene_bono_seleccionado = true;
            break;
        }
    }
}

// ============================================
// SOLO SI HAY BÚSQUEDA
// ============================================
if ($busqueda_realizada) {
    
    // ============================================
    // 1. ESTADÍSTICAS DEL PERÍODO
    // ============================================
    foreach ($tablas_a_usar as $tabla) {
        
        $tiene_bono = in_array('bono', $columnas_por_tabla[$tabla] ?? []);
        $tiene_valor_venta = in_array('valor_venta', $columnas_por_tabla[$tabla] ?? []);
        
        $sum_bono = $tiene_bono ? "COALESCE(SUM(bono), 0)" : "0";
        $sum_valor_venta = $tiene_valor_venta ? "COALESCE(SUM(valor_venta), 0)" : "0";
        
        if ($ver_todo_anio) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_ventas,
                    $sum_valor_venta as total_valor_ventas,
                    COALESCE(SUM(comision_real), 0) as total_comisiones,
                    $sum_bono as total_bonos
                FROM $tabla 
                WHERE cod_vendedor = ? 
                AND YEAR(fecha_comision) = ?
            ");
            $stmt->execute([$cod_asesor, $filtro_anio]);
        } else {
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
        }
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_ventas_mes += $stats['total_ventas'] ?? 0;
        $total_comisiones_mes += $stats['total_comisiones'] ?? 0;
        $total_bonos_mes += $stats['total_bonos'] ?? 0;
    }

    // ============================================
    // 2. RANKING DEL PERÍODO (usando UNION)
    // ============================================
    $union_queries = [];
    foreach ($tablas_a_usar as $tabla) {
        if ($ver_todo_anio) {
            $union_queries[] = "SELECT cod_vendedor, comision_real FROM $tabla WHERE YEAR(fecha_comision) = " . intval($filtro_anio);
        } else {
            $union_queries[] = "SELECT cod_vendedor, comision_real FROM $tabla WHERE MONTH(fecha_comision) = " . intval($filtro_mes) . " AND YEAR(fecha_comision) = " . intval($filtro_anio);
        }
    }
    $union_sql = implode(" UNION ALL ", $union_queries);
    
    // Ranking
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as ranking
        FROM (
            SELECT cod_vendedor, SUM(comision_real) as total
            FROM ($union_sql) as union_temp
            GROUP BY cod_vendedor
            HAVING total > ?
        ) as ranking_temp
    ");
    $stmt->execute([$total_comisiones_mes]);
    $ranking = $stmt->fetchColumn() ?? 1;

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT cod_vendedor) 
        FROM ($union_sql) as union_temp
    ");
    $stmt->execute();
    $total_asesores = $stmt->fetchColumn() ?? 0;

    // ============================================
    // 3. COMPARACIÓN CON PERÍODO ANTERIOR
    // ============================================
    $total_comisiones_anterior = 0;
    $total_ventas_anterior = 0;
    
    if ($ver_todo_anio) {
        $anio_anterior = $filtro_anio - 1;
        
        foreach ($tablas_a_usar as $tabla) {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(comision_real), 0) as total_comisiones,
                    COUNT(*) as total_ventas
                FROM $tabla 
                WHERE cod_vendedor = ? 
                AND YEAR(fecha_comision) = ?
            ");
            $stmt->execute([$cod_asesor, $anio_anterior]);
            $stats_ant = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_comisiones_anterior += $stats_ant['total_comisiones'] ?? 0;
            $total_ventas_anterior += $stats_ant['total_ventas'] ?? 0;
        }
        
        $texto_comparacion = "vs año " . $anio_anterior;
    } else {
        $mes_anterior = $filtro_mes - 1;
        $anio_anterior = $filtro_anio;
        if ($mes_anterior < 1) {
            $mes_anterior = 12;
            $anio_anterior = $filtro_anio - 1;
        }
        
        foreach ($tablas_a_usar as $tabla) {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(comision_real), 0) as total_comisiones,
                    COUNT(*) as total_ventas
                FROM $tabla 
                WHERE cod_vendedor = ? 
                AND MONTH(fecha_comision) = ? 
                AND YEAR(fecha_comision) = ?
            ");
            $stmt->execute([$cod_asesor, $mes_anterior, $anio_anterior]);
            $stats_ant = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_comisiones_anterior += $stats_ant['total_comisiones'] ?? 0;
            $total_ventas_anterior += $stats_ant['total_ventas'] ?? 0;
        }
        
        $texto_comparacion = "vs " . $meses_espanol[$mes_anterior] . " " . $anio_anterior;
    }

    if ($total_comisiones_anterior > 0) {
        $porcentaje_comisiones = (($total_comisiones_mes - $total_comisiones_anterior) / $total_comisiones_anterior) * 100;
    }

    if ($total_ventas_anterior > 0) {
        $porcentaje_ventas = (($total_ventas_mes - $total_ventas_anterior) / $total_ventas_anterior) * 100;
    }

    // ============================================
    // 4. EVOLUCIÓN MENSUAL
    // ============================================
    foreach ($tablas_a_usar as $tabla) {
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(fecha_comision) as mes,
                COALESCE(SUM(comision_real), 0) as total_comisiones,
                COUNT(*) as total_ventas
            FROM $tabla 
            WHERE cod_vendedor = ? 
            AND YEAR(fecha_comision) = ?
            GROUP BY MONTH(fecha_comision)
            ORDER BY MONTH(fecha_comision)
        ");
        $stmt->execute([$cod_asesor, $filtro_anio]);
        $evolucion = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($evolucion as $e) {
            $datos_comisiones[$e['mes']] += floatval($e['total_comisiones']);
            $datos_ventas[$e['mes']] += intval($e['total_ventas']);
        }
    }

    // ============================================
    // 5. TOP 5 MODELOS (union de tablas con modelo)
    // ============================================
    $tablas_con_modelo = [];
    foreach ($tablas_a_usar as $t) {
        if (in_array('modelo', $columnas_por_tabla[$t] ?? [])) {
            $tablas_con_modelo[] = $t;
        }
    }
    
    if (!empty($tablas_con_modelo)) {
        $union_modelos = [];
        foreach ($tablas_con_modelo as $tabla) {
            $union_modelos[] = "
                SELECT modelo 
                FROM $tabla 
                WHERE cod_vendedor = " . $pdo->quote($cod_asesor) . "
                AND YEAR(fecha_comision) = " . intval($filtro_anio) . "
                AND modelo IS NOT NULL
                AND modelo != ''
            ";
        }
        
        $union_sql_modelos = implode(" UNION ALL ", $union_modelos);
        
        $stmt = $pdo->prepare("
            SELECT modelo, COUNT(*) as total
            FROM ($union_sql_modelos) as modelos_temp
            GROUP BY modelo
            ORDER BY total DESC
            LIMIT 5
        ");
        $stmt->execute();
        $top_modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($top_modelos as $m) {
            if ($m['total'] > $max_ventas_modelo) {
                $max_ventas_modelo = $m['total'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Estadísticas | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            margin-bottom: 20px;
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
        .resumen-item .icon-ranking { color: #e74c3c; }
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

        /* ===== COMPARACIÓN ===== */
        .comparacion-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .comparacion-item {
            padding: 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid;
        }
        .comparacion-item.up {
            background: #d5f5e3;
            border-color: #27ae60;
        }
        .comparacion-item.down {
            background: #fadbd8;
            border-color: #e74c3c;
        }
        .comparacion-item .icon {
            font-size: 28px;
        }
        .comparacion-item.up .icon { color: #27ae60; }
        .comparacion-item.down .icon { color: #e74c3c; }
        .comparacion-item .info .valor {
            font-size: 20px;
            font-weight: 700;
            color: #12232b;
        }
        .comparacion-item .info .label {
            font-size: 12px;
            color: #7f8c8d;
        }

        /* ===== GRÁFICA ===== */
        .grafica-container {
            position: relative;
            height: 350px;
        }

        /* ===== TOP MODELOS ===== */
        .top-modelos {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .modelo-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .modelo-rank {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #445960);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        .modelo-item:nth-child(1) .modelo-rank { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .modelo-item:nth-child(2) .modelo-rank { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
        .modelo-item:nth-child(3) .modelo-rank { background: linear-gradient(135deg, #cd7f32, #a05a2c); }
        .modelo-info {
            flex: 1;
        }
        .modelo-info .nombre {
            font-weight: 600;
            color: #12232b;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .modelo-barra {
            height: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }
        .modelo-barra-fill {
            height: 100%;
            background: linear-gradient(90deg, #173742, #445960);
            border-radius: 5px;
            transition: width 0.8s ease;
        }
        .modelo-item:nth-child(1) .modelo-barra-fill { background: linear-gradient(90deg, #f39c12, #e67e22); }
        .modelo-item:nth-child(2) .modelo-barra-fill { background: linear-gradient(90deg, #95a5a6, #7f8c8d); }
        .modelo-item:nth-child(3) .modelo-barra-fill { background: linear-gradient(90deg, #cd7f32, #a05a2c); }
        .modelo-cantidad {
            font-weight: 700;
            color: #173742;
            font-size: 16px;
            min-width: 40px;
            text-align: right;
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
            .grafica-container { height: 250px; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Mis Estadísticas</small>
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
            <h2><i class="fas fa-filter"></i> Filtrar estadísticas</h2>
            
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

                <div class="filtro-group">
                    <label>Mes</label>
                    <select name="mes" required>
                        <option value="">Seleccionar</option>
                        <option value="0" <?php echo ($busqueda_realizada && $filtro_mes === 0) ? 'selected' : ''; ?>>Todo el año</option>
                        <?php foreach ($meses_espanol as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo $num == $filtro_mes ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-buscar">
                    <i class="fas fa-search"></i> Ver
                </button>
            </form>
        </div>

        <?php if (!$busqueda_realizada): ?>
            <!-- ===== MENSAJE DE SELECCIÓN ===== -->
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Selecciona los filtros</h3>
                    <p>Escoge el tipo, año y mes para ver tus estadísticas</p>
                </div>
            </div>
        <?php else: ?>

            <!-- ===== RENDIMIENTO DEL PERÍODO ===== -->
            <div class="card">
                <h2>
                    <i class="fas fa-chart-line"></i> 
                    <?php if ($ver_todo_anio): ?>
                        Rendimiento del año <?php echo $filtro_anio; ?>
                    <?php else: ?>
                        Rendimiento de <?php echo $meses_espanol[$filtro_mes] . ' ' . $filtro_anio; ?>
                        <?php if ($filtro_mes == $mes_vencido && $filtro_anio == $anio_vencido): ?>
                            <span style="background: #fef9e7; color: #7f6000; padding: 2px 10px; border-radius: 12px; font-size: 11px; border: 1px solid #f39c12;">
                                Mes vencido
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </h2>
                
                <div class="resumen-grid">
                    <div class="resumen-item">
                        <i class="fas fa-money-bill-wave icon-money"></i>
                        <div class="numero">$<?php echo number_format($total_comisiones_mes, 0, ',', '.'); ?></div>
                        <div class="label">Total Comisiones</div>
                    </div>
                    
                    <?php if ($tiene_bono_seleccionado): ?>
                    <div class="resumen-item">
                        <i class="fas fa-gift icon-bono"></i>
                        <div class="numero">$<?php echo number_format($total_bonos_mes, 0, ',', '.'); ?></div>
                        <div class="label">Total Bonos</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="resumen-item">
                        <i class="fas fa-shopping-cart icon-ventas"></i>
                        <div class="numero"><?php echo number_format($total_ventas_mes, 0, ',', '.'); ?></div>
                        <div class="label">Ventas</div>
                    </div>
                    
                    <div class="resumen-item">
                        <i class="fas fa-trophy icon-ranking"></i>
                        <div class="numero">#<?php echo $ranking; ?></div>
                        <div class="label">
                            <?php if ($ver_todo_anio): ?>
                                Ranking del Año <?php echo $total_asesores > 0 ? "(de $total_asesores)" : ''; ?>
                            <?php else: ?>
                                Ranking de <?php echo $meses_espanol[$filtro_mes]; ?> <?php echo $total_asesores > 0 ? "(de $total_asesores)" : ''; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ===== COMPARACIÓN ===== -->
                <h3 style="font-size: 14px; color: #7f8c8d; margin: 20px 0 10px 0;">
                    <i class="fas fa-exchange-alt"></i> Comparación <?php echo $texto_comparacion; ?>
                </h3>
                
                <div class="comparacion-grid">
                    <div class="comparacion-item <?php echo $porcentaje_comisiones >= 0 ? 'up' : 'down'; ?>">
                        <i class="fas <?php echo $porcentaje_comisiones >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> icon"></i>
                        <div class="info">
                            <div class="valor"><?php echo ($porcentaje_comisiones >= 0 ? '+' : '') . number_format($porcentaje_comisiones, 1, ',', '.'); ?>%</div>
                            <div class="label">Comisiones <?php echo $texto_comparacion; ?></div>
                        </div>
                    </div>
                    
                    <div class="comparacion-item <?php echo $porcentaje_ventas >= 0 ? 'up' : 'down'; ?>">
                        <i class="fas <?php echo $porcentaje_ventas >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> icon"></i>
                        <div class="info">
                            <div class="valor"><?php echo ($porcentaje_ventas >= 0 ? '+' : '') . number_format($porcentaje_ventas, 1, ',', '.'); ?>%</div>
                            <div class="label">Ventas <?php echo $texto_comparacion; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== EVOLUCIÓN MENSUAL ===== -->
            <div class="card">
                <h2>
                    <i class="fas fa-chart-bar"></i> 
                    Evolución mensual <?php echo $filtro_anio; ?>
                </h2>
                
                <div class="grafica-container">
                    <canvas id="graficaEvolucion"></canvas>
                </div>
            </div>

            <!-- ===== TOP 5 MODELOS ===== -->
            <div class="card">
                <h2>
                    <i class="fas fa-trophy"></i> 
                    Top 5 - Modelos más vendidos <?php echo $filtro_anio; ?>
                </h2>
                
                <?php if (empty($top_modelos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-car"></i>
                        <h3>Sin datos de modelos</h3>
                        <p>No hay ventas registradas para este año</p>
                    </div>
                <?php else: ?>
                    <div class="top-modelos">
                        <?php foreach ($top_modelos as $index => $m): ?>
                            <div class="modelo-item">
                                <div class="modelo-rank"><?php echo $index + 1; ?></div>
                                <div class="modelo-info">
                                    <div class="nombre"><?php echo htmlspecialchars($m['modelo']); ?></div>
                                    <div class="modelo-barra">
                                        <div class="modelo-barra-fill" style="width: <?php echo ($m['total'] / $max_ventas_modelo) * 100; ?>%;"></div>
                                    </div>
                                </div>
                                <div class="modelo-cantidad"><?php echo $m['total']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== SCRIPT PARA LA GRÁFICA ===== -->
            <script>
                const mesesLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                const datosComisiones = <?php echo json_encode(array_values($datos_comisiones)); ?>;
                const datosVentas = <?php echo json_encode(array_values($datos_ventas)); ?>;

                const ctx = document.getElementById('graficaEvolucion').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: mesesLabels,
                        datasets: [
                            {
                                label: 'Comisiones ($)',
                                data: datosComisiones,
                                backgroundColor: 'rgba(23, 55, 66, 0.8)',
                                borderColor: 'rgba(23, 55, 66, 1)',
                                borderWidth: 2,
                                borderRadius: 8,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Ventas',
                                data: datosVentas,
                                type: 'line',
                                borderColor: '#f39c12',
                                backgroundColor: 'rgba(243, 156, 18, 0.1)',
                                borderWidth: 3,
                                pointBackgroundColor: '#f39c12',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                tension: 0.4,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: { family: 'Poppins', size: 13 },
                                    padding: 15,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                backgroundColor: '#12232b',
                                titleFont: { family: 'Poppins', size: 14 },
                                bodyFont: { family: 'Poppins', size: 13 },
                                padding: 12,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.dataset.label === 'Comisiones ($)') {
                                            label += '$' + context.parsed.y.toLocaleString('es-CO');
                                        } else {
                                            label += context.parsed.y;
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                beginAtZero: true,
                                ticks: {
                                    font: { family: 'Poppins', size: 11 },
                                    callback: function(value) {
                                        return '$' + (value / 1000000).toFixed(1) + 'M';
                                    }
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                beginAtZero: true,
                                ticks: {
                                    font: { family: 'Poppins', size: 11 },
                                    stepSize: 1
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            },
                            x: {
                                ticks: {
                                    font: { family: 'Poppins', size: 12 }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            </script>

        <?php endif; ?>
    </div>
</body>
</html>