<?php
// admin/comercial/estadisticas/index.php - Estadísticas Globales
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

if ($rol_usuario == 1) {
    // Admin: acceso total
} elseif ($rol_usuario == 2) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as tiene_permiso 
        FROM permisos_usuarios pu
        JOIN modulos m ON pu.modulo_id = m.id
        WHERE pu.usuario_id = ? AND m.nombre = 'comercial' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso";
        header('Location: ../../index.php');
        exit();
    }
} else {
    header('Location: ../../index.php');
    exit();
}

$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

// ============================================
// MESES
// ============================================
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// ============================================
// AÑOS
// ============================================
$años = [];
for ($i = 2026; $i <= intval(date('Y')); $i++) {
    $años[] = $i;
}

// ============================================
// MES VENCIDO
// ============================================
$mes_vencido = date('n') - 1;
$anio_vencido = date('Y');
if ($mes_vencido < 1) {
    $mes_vencido = 12;
    $anio_vencido = date('Y') - 1;
}

// ============================================
// TIPOS DE COMISIONES
// ============================================
$tipos_comisiones = [
    'todos' => 'Todos',
    'matriculas' => 'Matrículas',
    'accesorios' => 'Accesorios',
    'usados' => 'Usados',
    'financieras' => 'Financieras',
    'incentivos' => 'Incentivos'
];

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
// SEDES
// ============================================
$sedes_disponibles = ['Manizales', 'Pereira', 'Armenia', 'Cartago', 'La Dorada'];

// ============================================
// FILTROS
// ============================================
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_anio = isset($_GET['anio']) ? intval($_GET['anio']) : 0;
$filtro_mes = isset($_GET['mes']) ? intval($_GET['mes']) : -1;
$filtro_sede = isset($_GET['sede']) ? $_GET['sede'] : '';

$busqueda_realizada = (!empty($filtro_tipo) && $filtro_anio > 0 && $filtro_mes >= 0 && !empty($filtro_sede));

if ($busqueda_realizada && !array_key_exists($filtro_tipo, $tipos_comisiones)) {
    $busqueda_realizada = false;
}
if ($busqueda_realizada && ($filtro_anio < 2026 || $filtro_anio > intval(date('Y')))) {
    $busqueda_realizada = false;
}
if ($busqueda_realizada && $filtro_sede !== 'todas' && !in_array($filtro_sede, $sedes_disponibles)) {
    $busqueda_realizada = false;
}

$ver_todo_anio = ($filtro_mes === 0);

// ============================================
// INICIALIZAR VARIABLES
// ============================================
$total_ventas = 0;
$total_comisiones = 0;
$total_bonos = 0;
$total_general = 0;
$total_asesores = 0;
$promedio_asesor = 0;
$mejor_asesor = null;
$ranking_completo = [];
$top_modelos = [];
$max_ventas_modelo = 0;
$datos_comisiones_mes = array_fill(1, 12, 0);
$datos_ventas_mes = array_fill(1, 12, 0);
$resumen_sede = [];
$max_comisiones_sede = 0;

if ($busqueda_realizada) {
    if ($filtro_tipo === 'todos') {
        $tablas_a_usar = array_values($tablas_disponibles);
    } else {
        $tablas_a_usar = isset($tablas_disponibles[$filtro_tipo]) ? [$tablas_disponibles[$filtro_tipo]] : [];
    }
    if (empty($tablas_a_usar)) $busqueda_realizada = false;
}

// ============================================
// CONSULTAS
// ============================================
if ($busqueda_realizada) {
    
    $where_sede = "";
    $params_sede = [];
    
    if ($filtro_sede !== 'todas') {
        $where_sede = " AND u.sede = ?";
        $params_sede = [$filtro_sede];
    }
    
    // ============================================
    // 1. KPIs
    // ============================================
    foreach ($tablas_a_usar as $tabla) {
        
        $tiene_bono = in_array('bono', $columnas_por_tabla[$tabla] ?? []);
        $sum_bono = $tiene_bono ? "COALESCE(SUM(cm.bono), 0)" : "0";
        
        if ($ver_todo_anio) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_ventas,
                    COALESCE(SUM(cm.comision_real), 0) as total_comisiones,
                    $sum_bono as total_bonos,
                    COUNT(DISTINCT cm.cod_vendedor) as total_asesores
                FROM $tabla cm
                LEFT JOIN usuarios u ON cm.cod_vendedor = u.cod_asesor
                WHERE YEAR(cm.fecha_comision) = ?
                $where_sede
            ");
            $stmt->execute(array_merge([$filtro_anio], $params_sede));
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_ventas,
                    COALESCE(SUM(cm.comision_real), 0) as total_comisiones,
                    $sum_bono as total_bonos,
                    COUNT(DISTINCT cm.cod_vendedor) as total_asesores
                FROM $tabla cm
                LEFT JOIN usuarios u ON cm.cod_vendedor = u.cod_asesor
                WHERE MONTH(cm.fecha_comision) = ? 
                AND YEAR(cm.fecha_comision) = ?
                $where_sede
            ");
            $stmt->execute(array_merge([$filtro_mes, $filtro_anio], $params_sede));
        }
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_ventas += $stats['total_ventas'] ?? 0;
        $total_comisiones += $stats['total_comisiones'] ?? 0;
        $total_bonos += $stats['total_bonos'] ?? 0;
        $total_asesores = max($total_asesores, $stats['total_asesores'] ?? 0);
    }
    
    $total_general = $total_comisiones + $total_bonos;
    $promedio_asesor = $total_asesores > 0 ? ($total_comisiones / $total_asesores) : 0;
    
    // ============================================
    // 2. RANKING
    // ============================================
    $ranking_temp = [];
    
    foreach ($tablas_a_usar as $tabla) {
        
        $tiene_bono = in_array('bono', $columnas_por_tabla[$tabla] ?? []);
        $sum_bono = $tiene_bono ? "COALESCE(SUM(cm.bono), 0)" : "0";
        $sum_general = $tiene_bono ? "COALESCE(SUM(cm.comision_real + cm.bono), 0)" : "COALESCE(SUM(cm.comision_real), 0)";
        
        if ($ver_todo_anio) {
            $stmt = $pdo->prepare("
                SELECT 
                    cm.cod_vendedor,
                    u.nombre_completo as nombre_asesor,
                    u.sede as sede_asesor,
                    COUNT(*) as total_ventas,
                    COALESCE(SUM(cm.comision_real), 0) as total_comisiones,
                    $sum_bono as total_bonos,
                    $sum_general as total_general
                FROM $tabla cm
                LEFT JOIN usuarios u ON cm.cod_vendedor = u.cod_asesor
                WHERE YEAR(cm.fecha_comision) = ?
                $where_sede
                GROUP BY cm.cod_vendedor, u.nombre_completo, u.sede
            ");
            $stmt->execute(array_merge([$filtro_anio], $params_sede));
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    cm.cod_vendedor,
                    u.nombre_completo as nombre_asesor,
                    u.sede as sede_asesor,
                    COUNT(*) as total_ventas,
                    COALESCE(SUM(cm.comision_real), 0) as total_comisiones,
                    $sum_bono as total_bonos,
                    $sum_general as total_general
                FROM $tabla cm
                LEFT JOIN usuarios u ON cm.cod_vendedor = u.cod_asesor
                WHERE MONTH(cm.fecha_comision) = ? 
                AND YEAR(cm.fecha_comision) = ?
                $where_sede
                GROUP BY cm.cod_vendedor, u.nombre_completo, u.sede
            ");
            $stmt->execute(array_merge([$filtro_mes, $filtro_anio], $params_sede));
        }
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultados as $r) {
            $cod = $r['cod_vendedor'];
            if (!isset($ranking_temp[$cod])) {
                $ranking_temp[$cod] = [
                    'cod_vendedor' => $cod,
                    'nombre_asesor' => $r['nombre_asesor'],
                    'sede_asesor' => $r['sede_asesor'],
                    'total_ventas' => 0,
                    'total_comisiones' => 0,
                    'total_bonos' => 0,
                    'total_general' => 0
                ];
            }
            $ranking_temp[$cod]['total_ventas'] += $r['total_ventas'];
            $ranking_temp[$cod]['total_comisiones'] += $r['total_comisiones'];
            $ranking_temp[$cod]['total_bonos'] += $r['total_bonos'];
            $ranking_temp[$cod]['total_general'] += $r['total_general'];
        }
    }
    
    usort($ranking_temp, function($a, $b) {
        return $b['total_comisiones'] <=> $a['total_comisiones'];
    });
    
    $ranking_completo = array_values($ranking_temp);
    
    if (!empty($ranking_completo)) {
        $mejor_asesor = $ranking_completo[0];
    }
    
    // ============================================
    // 3. EVOLUCIÓN MENSUAL
    // ============================================
    foreach ($tablas_a_usar as $tabla) {
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(cm.fecha_comision) as mes,
                COALESCE(SUM(cm.comision_real), 0) as total_comisiones,
                COUNT(*) as total_ventas
            FROM $tabla cm
            LEFT JOIN usuarios u ON cm.cod_vendedor = u.cod_asesor
            WHERE YEAR(cm.fecha_comision) = ?
            $where_sede
            GROUP BY MONTH(cm.fecha_comision)
            ORDER BY MONTH(cm.fecha_comision)
        ");
        $stmt->execute(array_merge([$filtro_anio], $params_sede));
        $evolucion = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($evolucion as $e) {
            $datos_comisiones_mes[$e['mes']] += floatval($e['total_comisiones']);
            $datos_ventas_mes[$e['mes']] += intval($e['total_ventas']);
        }
    }
    
    // ============================================
    // 4. TOP 5 MODELOS (union de tablas con modelo)
    // ============================================
    $tablas_con_modelo = [];
    foreach ($tablas_a_usar as $t) {
        if (in_array('modelo', $columnas_por_tabla[$t] ?? [])) {
            $tablas_con_modelo[] = $t;
        }
    }

    if (!empty($tablas_con_modelo)) {
        $union_modelos = [];
        $params_modelos = [];
        
        foreach ($tablas_con_modelo as $tabla) {
            $union_modelos[] = "
                SELECT cm.modelo 
                FROM $tabla cm
                LEFT JOIN usuarios u ON cm.cod_vendedor = u.cod_asesor
                WHERE YEAR(cm.fecha_comision) = ?
                $where_sede
                AND cm.modelo IS NOT NULL
                AND cm.modelo != ''
            ";
            $params_modelos[] = $filtro_anio;
            foreach ($params_sede as $p) {
                $params_modelos[] = $p;
            }
        }
        
        $union_sql = implode(" UNION ALL ", $union_modelos);
        
        $stmt = $pdo->prepare("
            SELECT modelo, COUNT(*) as total
            FROM ($union_sql) as modelos_temp
            GROUP BY modelo
            ORDER BY total DESC
            LIMIT 5
        ");
        $stmt->execute($params_modelos);
        $top_modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($top_modelos as $m) {
            if ($m['total'] > $max_ventas_modelo) {
                $max_ventas_modelo = $m['total'];
            }
        }
    }
    
    // ============================================
    // 5. RESUMEN POR SEDE
    // ============================================
    foreach ($sedes_disponibles as $sede) {
        $asesores_sede = [];
        $ventas_sede = 0;
        $comisiones_sede = 0;
        
        foreach ($tablas_a_usar as $tabla) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT cm.cod_vendedor) as asesores,
                    COUNT(*) as ventas,
                    COALESCE(SUM(cm.comision_real), 0) as comisiones
                FROM $tabla cm
                LEFT JOIN usuarios u ON cm.cod_vendedor = u.cod_asesor
                WHERE u.sede = ?
                AND YEAR(cm.fecha_comision) = ?
            ");
            $stmt->execute([$sede, $filtro_anio]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $ventas_sede += $resultado['ventas'] ?? 0;
            $comisiones_sede += $resultado['comisiones'] ?? 0;
            
            // Para asesores únicos
            $stmt2 = $pdo->prepare("
                SELECT DISTINCT cm.cod_vendedor
                FROM $tabla cm
                LEFT JOIN usuarios u ON cm.cod_vendedor = u.cod_asesor
                WHERE u.sede = ?
                AND YEAR(cm.fecha_comision) = ?
            ");
            $stmt2->execute([$sede, $filtro_anio]);
            $codigos = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            foreach ($codigos as $c) {
                $asesores_sede[$c] = true;
            }
        }
        
        $total_asesores_sede = count($asesores_sede);
        
        if ($total_asesores_sede > 0) {
            $resumen_sede[$sede] = [
                'asesores' => $total_asesores_sede,
                'ventas' => $ventas_sede,
                'comisiones' => $comisiones_sede,
                'promedio' => $total_asesores_sede > 0 ? ($comisiones_sede / $total_asesores_sede) : 0
            ];
            
            if ($comisiones_sede > $max_comisiones_sede) {
                $max_comisiones_sede = $comisiones_sede;
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
    <title>Estadísticas Globales | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }

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

        /* ===== KPIs ===== */
        .kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .kpi-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-top: 4px solid;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.05;
            background: radial-gradient(circle at top right, currentColor, transparent 70%);
        }
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .kpi-card.money { border-color: #27ae60; color: #27ae60; }
        .kpi-card.ventas { border-color: #3498db; color: #3498db; }
        .kpi-card.bonos { border-color: #f39c12; color: #f39c12; }
        .kpi-card.asesores { border-color: #9b59b6; color: #9b59b6; }
        .kpi-card.promedio { border-color: #e74c3c; color: #e74c3c; }

        .kpi-card i {
            font-size: 32px;
            margin-bottom: 12px;
            display: block;
        }
        .kpi-card .numero {
            font-size: 26px;
            font-weight: 700;
            color: #12232b;
            margin-bottom: 6px;
            line-height: 1.2;
        }
        .kpi-card .label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== FILA 2 COLUMNAS ===== */
        .fila-2col {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        @media (max-width: 992px) {
            .fila-2col { grid-template-columns: 1fr; }
        }

        /* ===== GRÁFICA ===== */
        .grafica-container {
            position: relative;
            height: 350px;
        }

        /* ===== MEJOR ASESOR ===== */
        .mejor-asesor-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            border-radius: 16px;
            padding: 30px;
            color: white;
            box-shadow: 0 4px 20px rgba(243,156,18,0.3);
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            height: 95%;
        }
        .mejor-asesor-card .trofeo {
            font-size: 48px;
            margin-bottom: 15px;
            color: #fff;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }
        .mejor-asesor-card h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .mejor-asesor-card .nombre {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .mejor-asesor-card .sede {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        .mejor-asesor-card .monto {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 12px;
            display: inline-block;
        }
        .mejor-asesor-card .monto-label {
            font-size: 12px;
            opacity: 0.9;
        }

        /* ===== TABLA ===== */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
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

        .posicion {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 13px;
            background: #f0f0f0;
            color: #2c3e50;
        }
        .posicion.top-1 { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
        .posicion.top-2 { background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: white; }
        .posicion.top-3 { background: linear-gradient(135deg, #cd7f32, #a05a2c); color: white; }

        .sede-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #e8f0fe;
            color: #173742;
        }

        .money { color: #27ae60; font-weight: 600; }
        .money-bono { color: #f39c12; font-weight: 600; }
        .money-total { color: #173742; font-weight: 700; font-size: 14px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

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
        .paginacion-info { font-size: 13px; color: #7f8c8d; }
        .paginacion-info strong { color: #12232b; }
        .paginacion { display: flex; gap: 5px; flex-wrap: wrap; }
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
        .paginacion a { background: #f0f0f0; color: #2c3e50; }
        .paginacion a:hover { background: #173742; color: white; transform: translateY(-2px); }
        .paginacion .pagina-actual { background: #173742; color: white; }
        .paginacion .pagina-puntos {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            color: #7f8c8d;
        }

        /* ===== TOP MODELOS ===== */
        .top-modelos { display: flex; flex-direction: column; gap: 12px; }
        .modelo-item { display: flex; align-items: center; gap: 15px; }
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
        .modelo-info { flex: 1; }
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

        /* ===== RESUMEN POR SEDE ===== */
        .sedes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }
        .sede-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #173742;
            transition: all 0.3s ease;
        }
        .sede-card:hover {
            background: #e8f0fe;
            transform: translateY(-3px);
        }
        .sede-card .sede-nombre {
            font-size: 16px;
            font-weight: 700;
            color: #12232b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sede-card .sede-nombre i { color: #173742; }
        .sede-stats {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sede-stat {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .sede-stat .label { color: #7f8c8d; }
        .sede-stat .valor { font-weight: 600; color: #12232b; }
        .sede-barra {
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
        }
        .sede-barra-fill {
            height: 100%;
            background: linear-gradient(90deg, #173742, #445960);
            border-radius: 3px;
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
        .empty-state p { font-size: 14px; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .filtros-container { flex-direction: column; align-items: stretch; }
            .filtro-group select { width: 100%; }
            .btn-buscar { width: 100%; justify-content: center; }
            .grafica-container { height: 250px; }
            .kpi-card .numero { font-size: 20px; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Estadísticas Globales</small>
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
                
                <!-- Tipo -->
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

                <!-- Año -->
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

                <!-- Mes -->
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

                <!-- Sede -->
                <div class="filtro-group">
                    <label>Sede</label>
                    <select name="sede" required>
                        <option value="">Seleccionar</option>
                        <option value="todas" <?php echo $filtro_sede == 'todas' ? 'selected' : ''; ?>>Todas las sedes</option>
                        <?php foreach ($sedes_disponibles as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $s == $filtro_sede ? 'selected' : ''; ?>>
                                <?php echo $s; ?>
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
                    <i class="fas fa-chart-line"></i>
                    <h3>Selecciona los filtros</h3>
                    <p>Escoge el tipo, año, mes y sede para ver las estadísticas globales</p>
                </div>
            </div>
        <?php else: ?>

            <!-- ===== KPIs ===== -->
            <div class="kpis-grid">
                <div class="kpi-card money">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="numero">$<?php echo number_format($total_comisiones, 0, ',', '.'); ?></div>
                    <div class="label">Total Comisiones</div>
                </div>
                
                <div class="kpi-card ventas">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="numero"><?php echo number_format($total_ventas, 0, ',', '.'); ?></div>
                    <div class="label">Total Ventas</div>
                </div>
                
                <div class="kpi-card bonos">
                    <i class="fas fa-gift"></i>
                    <div class="numero">$<?php echo number_format($total_bonos, 0, ',', '.'); ?></div>
                    <div class="label">Total Bonos</div>
                </div>
                
                <div class="kpi-card asesores">
                    <i class="fas fa-users"></i>
                    <div class="numero"><?php echo $total_asesores; ?></div>
                    <div class="label">Asesores Activos</div>
                </div>
                
                <div class="kpi-card promedio">
                    <i class="fas fa-chart-line"></i>
                    <div class="numero">$<?php echo number_format($promedio_asesor, 0, ',', '.'); ?></div>
                    <div class="label">Promedio x Asesor</div>
                </div>
            </div>

            <!-- ===== GRÁFICA + MEJOR ASESOR ===== -->
            <div class="fila-2col">
                <!-- Gráfica de Evolución -->
                <div class="card">
                    <h2>
                        <i class="fas fa-chart-bar"></i> 
                        Evolución mensual <?php echo $filtro_anio; ?>
                    </h2>
                    <div class="grafica-container">
                        <canvas id="graficaEvolucion"></canvas>
                    </div>
                </div>

                <!-- Mejor Asesor -->
                <div class="mejor-asesor-card">
                    <div class="trofeo"><i class="fas fa-trophy"></i></div>
                    <h3>Mejor Asesor del Período</h3>
                    <?php if ($mejor_asesor): ?>
                        <div class="nombre"><?php echo htmlspecialchars($mejor_asesor['nombre_asesor'] ?? 'Sin nombre'); ?></div>
                        <div class="sede">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($mejor_asesor['sede_asesor'] ?? 'Sin sede'); ?>
                        </div>
                        <div class="monto">$<?php echo number_format($mejor_asesor['total_comisiones'], 0, ',', '.'); ?></div>
                        <div class="monto-label">en comisiones</div>
                    <?php else: ?>
                        <p>Sin datos</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== RANKING COMPLETO ===== -->
            <div class="card">
                <h2>
                    <i class="fas fa-trophy"></i> 
                    Ranking de Asesores
                    <span style="font-size: 13px; font-weight: 400; color: #7f8c8d;">
                        (<?php echo count($ranking_completo); ?> asesores)
                    </span>
                </h2>
                
                <?php if (empty($ranking_completo)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>Sin datos</h3>
                        <p>No hay asesores con comisiones en este período</p>
                    </div>
                <?php else: 
                    // Paginación del ranking
                    $por_pagina = 10;
                    $pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
                    if ($pagina_actual < 1) $pagina_actual = 1;
                    $total_ranking = count($ranking_completo);
                    $total_paginas = ceil($total_ranking / $por_pagina);
                    if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;
                    $offset = ($pagina_actual - 1) * $por_pagina;
                    $ranking_paginado = array_slice($ranking_completo, $offset, $por_pagina);
                ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 60px; text-align: center;">Pos.</th>
                                    <th>Código</th>
                                    <th>Asesor</th>
                                    <th>Sede</th>
                                    <th class="text-center">Ventas</th>
                                    <th class="text-right">Comisiones</th>
                                    <th class="text-right">Bonos</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ranking_paginado as $index => $r): 
                                    $posicion = $offset + $index + 1;
                                    $clase_pos = '';
                                    if ($posicion == 1) $clase_pos = 'top-1';
                                    elseif ($posicion == 2) $clase_pos = 'top-2';
                                    elseif ($posicion == 3) $clase_pos = 'top-3';
                                ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <span class="posicion <?php echo $clase_pos; ?>"><?php echo $posicion; ?></span>
                                        </td>
                                        <td>
                                            <code style="background: #f0f0f0; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                                <?php echo htmlspecialchars($r['cod_vendedor']); ?>
                                            </code>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($r['nombre_asesor'] ?? 'Sin asignar'); ?></strong></td>
                                        <td>
                                            <?php if (!empty($r['sede_asesor'])): ?>
                                                <span class="sede-badge">
                                                    <i class="fas fa-map-marker-alt"></i> 
                                                    <?php echo htmlspecialchars($r['sede_asesor']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #7f8c8d;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo number_format($r['total_ventas'], 0, ',', '.'); ?></td>
                                        <td class="text-right money">$<?php echo number_format($r['total_comisiones'], 0, ',', '.'); ?></td>
                                        <td class="text-right money-bono">$<?php echo number_format($r['total_bonos'], 0, ',', '.'); ?></td>
                                        <td class="text-right money-total">$<?php echo number_format($r['total_general'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                    <div class="paginacion-container">
                        <div class="paginacion-info">
                            <i class="fas fa-info-circle"></i> 
                            Mostrando <strong><?php echo count($ranking_paginado); ?></strong> de <strong><?php echo $total_ranking; ?></strong> asesores
                        </div>
                        <div class="paginacion">
                            <?php if ($pagina_actual > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])); ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $rango = 2;
                            $inicio = max(1, $pagina_actual - $rango);
                            $fin = min($total_paginas, $pagina_actual + $rango);
                            ?>

                            <?php if ($inicio > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">1</a>
                                <?php if ($inicio > 2): ?><span class="pagina-puntos">…</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                                <?php if ($i == $pagina_actual): ?>
                                    <span class="pagina-actual"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($fin < $total_paginas): ?>
                                <?php if ($fin < $total_paginas - 1): ?><span class="pagina-puntos">…</span><?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>"><?php echo $total_paginas; ?></a>
                            <?php endif; ?>

                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])); ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- ===== TOP 5 MODELOS + RESUMEN POR SEDE ===== -->
            <div class="fila-2col">
                <!-- Top 5 Modelos -->
                <div class="card">
                    <h2>
                        <i class="fas fa-car"></i> 
                        Top 5 Modelos más vendidos
                    </h2>
                    
                    <?php if (empty($top_modelos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-car"></i>
                            <h3>Sin datos</h3>
                            <p>No hay ventas registradas</p>
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

                <!-- Resumen por Sede -->
                <div class="card">
                    <h2>
                        <i class="fas fa-map-marked-alt"></i> 
                        Resumen por Sede
                    </h2>
                    
                    <?php if (empty($resumen_sede)): ?>
                        <div class="empty-state">
                            <i class="fas fa-map"></i>
                            <h3>Sin datos</h3>
                            <p>No hay sedes con actividad</p>
                        </div>
                    <?php else: ?>
                        <div class="sedes-grid">
                            <?php foreach ($resumen_sede as $sede => $data): ?>
                                <div class="sede-card">
                                    <div class="sede-nombre">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($sede); ?>
                                    </div>
                                    <div class="sede-stats">
                                        <div class="sede-stat">
                                            <span class="label">Asesores:</span>
                                            <span class="valor"><?php echo $data['asesores']; ?></span>
                                        </div>
                                        <div class="sede-stat">
                                            <span class="label">Ventas:</span>
                                            <span class="valor"><?php echo $data['ventas']; ?></span>
                                        </div>
                                        <div class="sede-stat">
                                            <span class="label">Comisiones:</span>
                                            <span class="valor">$<?php echo number_format($data['comisiones'], 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="sede-stat">
                                            <span class="label">Promedio:</span>
                                            <span class="valor">$<?php echo number_format($data['promedio'], 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                    <div class="sede-barra">
                                        <div class="sede-barra-fill" style="width: <?php echo ($data['comisiones'] / $max_comisiones_sede) * 100; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== SCRIPT PARA LA GRÁFICA ===== -->
            <script>
                const mesesLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                const datosComisiones = <?php echo json_encode(array_values($datos_comisiones_mes)); ?>;
                const datosVentas = <?php echo json_encode(array_values($datos_ventas_mes)); ?>;

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
                                        if (label) label += ': ';
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
                                grid: { color: 'rgba(0,0,0,0.05)' }
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
                                grid: { drawOnChartArea: false }
                            },
                            x: {
                                ticks: { font: { family: 'Poppins', size: 12 } },
                                grid: { display: false }
                            }
                        }
                    }
                });
            </script>

        <?php endif; ?>
    </div>
</body>
</html>