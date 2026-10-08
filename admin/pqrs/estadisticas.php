<?php
// admin/pqrs/estadisticas.php - Dashboard de Estadísticas PQRS

require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/pqrs_helpers.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

// Admin y Supervisor: acceso total
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para ver estadísticas";
    header('Location: ../../index.php');
    exit();
}

$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

// ============================================
// MESES EN ESPAÑOL
// ============================================
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// ============================================
// TIPOS DE SOLICITUD
// ============================================
$tipos_solicitud = [
    'PETICION'     => 'Petición',
    'QUEJA'        => 'Queja',
    'RECLAMO'      => 'Reclamo',
    'SUGERENCIA'   => 'Sugerencia',
    'FELICITACION' => 'Felicitación'
];

// ============================================
// ESTADOS
// ============================================
$estados = [
    'abierto'                  => 'Abierto',
    'en_proceso'               => 'En Proceso',
    'analisis_servicio_cliente'=> 'Análisis Serv. Cliente',
    'cerrado'                  => 'Cerrado'
];

// ============================================
// PRIORIDADES
// ============================================
$prioridades = [
    'alta'  => 'Alta',
    'media' => 'Media',
    'baja'  => 'Baja'
];

// ============================================
// FILTROS
// ============================================
$filtro_fecha      = $_GET['fecha']      ?? 'todo';
$filtro_fecha_desde= $_GET['fecha_desde']?? '';
$filtro_fecha_hasta= $_GET['fecha_hasta']?? '';
$filtro_mes        = $_GET['mes']        ?? '';
$filtro_ano        = $_GET['ano']        ?? '';
$filtro_tipo       = $_GET['tipo']       ?? '';
$filtro_estado     = $_GET['estado']     ?? '';
$filtro_prioridad  = $_GET['prioridad']  ?? '';
$filtro_gestor     = $_GET['gestor']     ?? '';
$filtro_sede       = $_GET['sede']       ?? '';
$filtro_marca_vh   = $_GET['marca_vh']   ?? '';
$filtro_buscar     = trim($_GET['buscar']?? '');

$where = [];
$params = [];

// Filtro por período (atajos)
$campo_fecha = "COALESCE(p.marca_temporal, p.creado_el)";

switch ($filtro_fecha) {
    case 'hoy':
        $where[] = "DATE($campo_fecha) = CURDATE()";
        break;
    case 'semana':
        $where[] = "YEARWEEK($campo_fecha, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'mes_actual':
        $where[] = "YEAR($campo_fecha) = YEAR(CURDATE()) AND MONTH($campo_fecha) = MONTH(CURDATE())";
        break;
    case 'año_actual':
        $where[] = "YEAR($campo_fecha) = YEAR(CURDATE())";
        break;
    case 'personalizado':
        if (!empty($filtro_fecha_desde)) {
            $where[] = "DATE($campo_fecha) >= ?";
            $params[] = $filtro_fecha_desde;
        }
        if (!empty($filtro_fecha_hasta)) {
            $where[] = "DATE($campo_fecha) <= ?";
            $params[] = $filtro_fecha_hasta;
        }
        break;
    case 'mes_especifico':
        if (!empty($filtro_mes) && !empty($filtro_ano)) {
            $where[] = "YEAR($campo_fecha) = ? AND MONTH($campo_fecha) = ?";
            $params[] = intval($filtro_ano);
            $params[] = intval($filtro_mes);
        }
        break;
}

// Filtro por tipo
if (!empty($filtro_tipo) && array_key_exists($filtro_tipo, $tipos_solicitud)) {
    $where[] = "p.tipo_solicitud = ?";
    $params[] = $filtro_tipo;
}

// Filtro por estado
if (!empty($filtro_estado) && array_key_exists($filtro_estado, $estados)) {
    $where[] = "p.estado = ?";
    $params[] = $filtro_estado;
}

// Filtro por prioridad
if (!empty($filtro_prioridad) && array_key_exists($filtro_prioridad, $prioridades)) {
    $where[] = "p.prioridad = ?";
    $params[] = $filtro_prioridad;
}

// Filtro por gestor principal
if (!empty($filtro_gestor) && is_numeric($filtro_gestor)) {
    $where[] = "p.gestor_principal_id = ?";
    $params[] = intval($filtro_gestor);
}

// Filtro por sede
if (!empty($filtro_sede)) {
    $where[] = "p.ciudad_concesionario LIKE ?";
    $params[] = '%' . $filtro_sede . '%';
}

// Filtro por marca VH
if (!empty($filtro_marca_vh)) {
    $where[] = "p.marca_vh = ?";
    $params[] = $filtro_marca_vh;
}

// Búsqueda por código/asunto/cliente
if (!empty($filtro_buscar)) {
    $where[] = "(p.codigo LIKE ? OR p.asunto LIKE ? OR p.nombre_completo LIKE ?)";
    $like = '%' . $filtro_buscar . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ============================================
// KPI 1: TOTAL Y CONTEO POR ESTADO
// ============================================
$sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN p.estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
            SUM(CASE WHEN p.estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN p.estado = 'analisis_servicio_cliente' THEN 1 ELSE 0 END) as analisis,
            SUM(CASE WHEN p.estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
            SUM(CASE WHEN p.gestor_principal_id IS NULL OR p.gestor_principal_id = 0 THEN 1 ELSE 0 END) as sin_gestor
        FROM pqrs p
        $where_clause";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

$total_pqrs = $kpi['total'] ?? 0;
$total_abiertos = $kpi['abiertos'] ?? 0;
$total_en_proceso = $kpi['en_proceso'] ?? 0;
$total_analisis = $kpi['analisis'] ?? 0;
$total_cerrados = $kpi['cerrados'] ?? 0;
$total_sin_gestor = $kpi['sin_gestor'] ?? 0;

// ============================================
// KPI 2: TIEMPO PROMEDIO DE RESPUESTA (días)
// ============================================
$sql = "SELECT AVG(DATEDIFF(p.fecha_cierre, COALESCE(p.marca_temporal, p.creado_el))) as promedio
        FROM pqrs p
        $where_clause
        AND p.estado = 'cerrado' AND p.fecha_cierre IS NOT NULL";

// Ajustar si no hay WHERE
if (empty($where_clause)) {
    $sql = "SELECT AVG(DATEDIFF(p.fecha_cierre, COALESCE(p.marca_temporal, p.creado_el))) as promedio
            FROM pqrs p
            WHERE p.estado = 'cerrado' AND p.fecha_cierre IS NOT NULL";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tiempo_promedio = round($stmt->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0, 1);

// ============================================
// KPI 3: TASA DE RESOLUCIÓN
// ============================================
$tasa_resolucion = $total_pqrs > 0 ? round(($total_cerrados / $total_pqrs) * 100, 1) : 0;

// ============================================
// KPI 4: TIPO MÁS FRECUENTE
// ============================================
$sql = "SELECT p.tipo_solicitud, COUNT(*) as total
        FROM pqrs p
        $where_clause
        GROUP BY p.tipo_solicitud
        ORDER BY total DESC
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tipo_top = $stmt->fetch(PDO::FETCH_ASSOC);
$tipo_mas_frecuente = $tipo_top['tipo_solicitud'] ?? '—';
$tipo_mas_frecuente_nombre = $tipos_solicitud[$tipo_mas_frecuente] ?? '—';
$tipo_mas_frecuente_cantidad = $tipo_top['total'] ?? 0;

// ============================================
// KPI 5: CIUDAD CON MÁS PQRS
// ============================================
$sql = "SELECT p.ciudad_cliente, COUNT(*) as total
        FROM pqrs p
        $where_clause
        AND p.ciudad_cliente IS NOT NULL AND p.ciudad_cliente != ''
        GROUP BY p.ciudad_cliente
        ORDER BY total DESC
        LIMIT 1";

if (empty($where_clause)) {
    $sql = "SELECT p.ciudad_cliente, COUNT(*) as total
            FROM pqrs p
            WHERE p.ciudad_cliente IS NOT NULL AND p.ciudad_cliente != ''
            GROUP BY p.ciudad_cliente
            ORDER BY total DESC
            LIMIT 1";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ciudad_top = $stmt->fetch(PDO::FETCH_ASSOC);
$ciudad_mas_pqrs = $ciudad_top['ciudad_cliente'] ?? '—';
$ciudad_mas_pqrs_cantidad = $ciudad_top['total'] ?? 0;

// ============================================
// DATOS PARA GRÁFICOS
// ============================================

// GRÁFICO 1: Distribución por Tipo
$sql = "SELECT p.tipo_solicitud, COUNT(*) as total
        FROM pqrs p
        $where_clause
        GROUP BY p.tipo_solicitud
        ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data_tipos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data_tipos = [];
foreach ($data_tipos_raw as $row) {
    $data_tipos[] = [
        'label' => $tipos_solicitud[$row['tipo_solicitud']] ?? $row['tipo_solicitud'],
        'valor' => intval($row['total'])
    ];
}

// GRÁFICO 2: Distribución por Estado
$data_estados = [
    ['label' => 'Abierto',                 'valor' => $total_abiertos],
    ['label' => 'En Proceso',              'valor' => $total_en_proceso],
    ['label' => 'Análisis Serv. Cliente',  'valor' => $total_analisis],
    ['label' => 'Cerrado',                 'valor' => $total_cerrados]
];

// GRÁFICO 3: Distribución por Prioridad
$sql = "SELECT p.prioridad, COUNT(*) as total
        FROM pqrs p
        $where_clause
        GROUP BY p.prioridad";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data_prioridades_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data_prioridades = [];
foreach ($data_prioridades_raw as $row) {
    $data_prioridades[] = [
        'label' => $prioridades[$row['prioridad']] ?? ucfirst($row['prioridad']),
        'valor' => intval($row['total'])
    ];
}

// GRÁFICO 4: Top 10 Ciudades
$sql = "SELECT p.ciudad_cliente as ciudad, COUNT(*) as total
        FROM pqrs p
        $where_clause
        AND p.ciudad_cliente IS NOT NULL AND p.ciudad_cliente != ''
        GROUP BY p.ciudad_cliente
        ORDER BY total DESC
        LIMIT 10";

if (empty($where_clause)) {
    $sql = "SELECT p.ciudad_cliente as ciudad, COUNT(*) as total
            FROM pqrs p
            WHERE p.ciudad_cliente IS NOT NULL AND p.ciudad_cliente != ''
            GROUP BY p.ciudad_cliente
            ORDER BY total DESC
            LIMIT 10";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data_ciudades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GRÁFICO 5: Distribución por Sede
$sql = "SELECT p.ciudad_concesionario as sede, COUNT(*) as total
        FROM pqrs p
        $where_clause
        AND p.ciudad_concesionario IS NOT NULL AND p.ciudad_concesionario != ''
        GROUP BY p.ciudad_concesionario
        ORDER BY total DESC
        LIMIT 10";

if (empty($where_clause)) {
    $sql = "SELECT p.ciudad_concesionario as sede, COUNT(*) as total
            FROM pqrs p
            WHERE p.ciudad_concesionario IS NOT NULL AND p.ciudad_concesionario != ''
            GROUP BY p.ciudad_concesionario
            ORDER BY total DESC
            LIMIT 10";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data_sedes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GRÁFICO 6: Evolución mensual (últimos 12 meses)
$sql = "SELECT 
            DATE_FORMAT(COALESCE(p.marca_temporal, p.creado_el), '%Y-%m') as mes,
            DATE_FORMAT(COALESCE(p.marca_temporal, p.creado_el), '%b %Y') as mes_label,
            COUNT(*) as total,
            SUM(CASE WHEN p.estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados
        FROM pqrs p
        WHERE COALESCE(p.marca_temporal, p.creado_el) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY 
            DATE_FORMAT(COALESCE(p.marca_temporal, p.creado_el), '%Y-%m'),
            DATE_FORMAT(COALESCE(p.marca_temporal, p.creado_el), '%b %Y')
        ORDER BY mes ASC";
$stmt = $pdo->query($sql);
$data_evolucion = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// LISTAS PARA FILTROS
// ============================================

// Gestores disponibles (solo los que tienen PQRS)
$sql = "SELECT u.id, u.nombre_completo, COUNT(p.id) as total
        FROM usuarios u
        INNER JOIN pqrs p ON p.gestor_principal_id = u.id
        GROUP BY u.id, u.nombre_completo
        HAVING total > 0
        ORDER BY u.nombre_completo ASC";
$gestores_lista = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Marcas VH únicas
$sql = "SELECT DISTINCT marca_vh 
        FROM pqrs 
        WHERE marca_vh IS NOT NULL AND marca_vh != ''
        ORDER BY marca_vh ASC";
$marcas_lista = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

// Sedes únicas (ciudades concesionario)
$sql = "SELECT DISTINCT ciudad_concesionario 
        FROM pqrs 
        WHERE ciudad_concesionario IS NOT NULL AND ciudad_concesionario != ''
        ORDER BY ciudad_concesionario ASC";
$sedes_lista = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

// Años disponibles
$sql = "SELECT DISTINCT YEAR(COALESCE(marca_temporal, creado_el)) as ano
        FROM pqrs
        ORDER BY ano DESC";
$anos_lista = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// CONSTRUIR QUERY STRING PARA EXPORTAR
// ============================================
$query_params = $_GET;
$query_export = http_build_query($query_params);

// ¿Hay filtros activos?
$hay_filtros = !empty($filtro_tipo) || !empty($filtro_estado) || !empty($filtro_prioridad)
    || !empty($filtro_gestor) || !empty($filtro_sede)
    || !empty($filtro_marca_vh) || !empty($filtro_buscar)
    || ($filtro_fecha !== 'todo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas PQRS | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .logo img { width: 130px; }
        .logo-subtitle { color: #8aa8b8; font-size: 14px; margin-top: -5px; }
        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-volver:hover { background: #ffc107; color: #12232b; transform: translateY(-2px); }

        /* ===== TÍTULO ===== */
        .titulo-pagina {
            background: linear-gradient(135deg, #12232b, #173742);
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 25px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .titulo-pagina .info {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .titulo-pagina .icono {
            width: 60px;
            height: 60px;
            background: rgba(255,193,7,0.15);
            color: #ffc107;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        .titulo-pagina h1 { font-size: 22px; font-weight: 600; margin-bottom: 4px; }
        .titulo-pagina p { font-size: 13px; color: #8aa8b8; }

        .btn-exportar {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 14px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-exportar:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(39,174,96,0.4);
        }

        /* ===== CARD GENÉRICA ===== */
        .card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .card h3 {
            font-size: 15px;
            color: #12232b;
            margin-bottom: 15px;
            border-left: 3px solid #e67e22;
            padding-left: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card h3 i { color: #e67e22; font-size: 14px; }

        /* ===== FILTROS ===== */
        .filtros-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .filtros-card h3 {
            font-size: 15px;
            color: #12232b;
            margin-bottom: 15px;
            border-left: 3px solid #173742;
            padding-left: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filtros-card h3 i { color: #173742; }

        .filtros-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .filtros-fila {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filtro-grupo {
            flex: 1;
            min-width: 150px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filtro-grupo label {
            font-size: 11px;
            font-weight: 600;
            color: #445960;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .filtro-grupo label i { color: #173742; font-size: 11px; }
        .filtro-grupo select,
        .filtro-grupo input[type="date"],
        .filtro-grupo input[type="text"],
        .filtro-grupo input[type="number"] {
            padding: 9px 11px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            background: white;
            transition: all 0.3s ease;
            color: #2c3e50;
        }
        .filtro-grupo select:focus,
        .filtro-grupo input:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }

        .filtros-acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }
        .btn-filtrar {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            padding: 11px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(23,55,66,0.2);
        }
        .btn-filtrar:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(23,55,66,0.3); }
        .btn-limpiar {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 11px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-limpiar:hover { background: #e74c3c; color: white; transform: translateY(-2px); }

        /* ===== KPIs ===== */
        .kpis-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        @media (max-width: 1200px) { .kpis-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) { .kpis-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .kpis-grid { grid-template-columns: 1fr; } }

        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-top: 4px solid;
            transition: all 0.3s ease;
        }
        .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .kpi-card .icono-kpi {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 20px;
            color: white;
        }
        .kpi-card .numero { font-size: 26px; font-weight: 700; color: #12232b; line-height: 1; margin-bottom: 4px; }
        .kpi-card .label { font-size: 11px; color: #7f8c8d; font-weight: 500; }
        .kpi-card .sublabel { font-size: 10px; color: #95a5a6; margin-top: 3px; }

        .kpi-total .icono-kpi { background: linear-gradient(135deg, #173742, #1a4a55); }
        .kpi-total { border-color: #173742; }
        .kpi-tiempo .icono-kpi { background: linear-gradient(135deg, #3498db, #5dade2); }
        .kpi-tiempo { border-color: #3498db; }
        .kpi-tasa .icono-kpi { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .kpi-tasa { border-color: #27ae60; }
        .kpi-sin .icono-kpi { background: linear-gradient(135deg, #e74c3c, #ec7063); }
        .kpi-sin { border-color: #e74c3c; }
        .kpi-tipo .icono-kpi { background: linear-gradient(135deg, #9b59b6, #b07cc6); }
        .kpi-tipo { border-color: #9b59b6; }
        .kpi-ciudad .icono-kpi { background: linear-gradient(135deg, #f39c12, #f8c471); }
        .kpi-ciudad { border-color: #f39c12; }

        /* ===== GRÁFICOS ===== */
        .graficos-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 992px) { .graficos-grid { grid-template-columns: 1fr; } }

        .grafico-card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .grafico-card h3 {
            font-size: 15px;
            color: #12232b;
            margin-bottom: 18px;
            border-left: 3px solid #173742;
            padding-left: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .grafico-card h3 i { color: #173742; font-size: 14px; }

        .grafico-container {
            position: relative;
            height: 280px;
            width: 100%;
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
            border: none;
            padding: 0;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .titulo-pagina { flex-direction: column; text-align: center; }
            .titulo-pagina .info { flex-direction: column; }
            .filtros-fila { flex-direction: column; }
            .filtro-grupo { min-width: 100%; }
            .filtros-acciones { flex-direction: column; }
            .btn-filtrar, .btn-limpiar { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
                <p class="logo-subtitle">| Estadísticas PQRSF</p>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al listado
            </a>
        </div>
    </header>

    <div class="container">

        <!-- ===== TÍTULO ===== -->
        <div class="titulo-pagina">
            <div class="info">
                <div class="icono">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h1>Estadísticas PQRSF</h1>
                    <p>Analiza el rendimiento y tendencias de las PQRS</p>
                </div>
            </div>
            <a href="estadisticas_export.php?<?php echo $query_export; ?>" class="btn-exportar">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </a>
        </div>

        <!-- ===== FILTROS ===== -->
        <div class="filtros-card">
            <h3><i class="fas fa-filter"></i> Filtros avanzados</h3>

            <form method="GET" action="" class="filtros-form">

                <!-- Fila 1: Período -->
                <div class="filtros-fila">
                    <div class="filtro-grupo">
                        <label><i class="fas fa-calendar-alt"></i> Período</label>
                        <select name="fecha" id="selectFecha">
                            <option value="todo" <?php echo $filtro_fecha === 'todo' ? 'selected' : ''; ?>>Todo</option>
                            <option value="hoy" <?php echo $filtro_fecha === 'hoy' ? 'selected' : ''; ?>>Hoy</option>
                            <option value="semana" <?php echo $filtro_fecha === 'semana' ? 'selected' : ''; ?>>Esta semana</option>
                            <option value="mes_actual" <?php echo $filtro_fecha === 'mes_actual' ? 'selected' : ''; ?>>Este mes</option>
                            <option value="año_actual" <?php echo $filtro_fecha === 'año_actual' ? 'selected' : ''; ?>>Este año</option>
                            <option value="mes_especifico" <?php echo $filtro_fecha === 'mes_especifico' ? 'selected' : ''; ?>>Mes específico</option>
                            <option value="personalizado" <?php echo $filtro_fecha === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                        </select>
                    </div>

                    <div class="filtro-grupo rango-fechas" id="rangoFechas" style="<?php echo $filtro_fecha === 'personalizado' ? '' : 'display:none;'; ?>">
                        <label><i class="fas fa-calendar-day"></i> Desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                    </div>

                    <div class="filtro-grupo rango-fechas" id="rangoFechasHasta" style="<?php echo $filtro_fecha === 'personalizado' ? '' : 'display:none;'; ?>">
                        <label><i class="fas fa-calendar-day"></i> Hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                    </div>

                    <div class="filtro-grupo mes-especifico" id="mesEspecifico" style="<?php echo $filtro_fecha === 'mes_especifico' ? '' : 'display:none;'; ?>">
                        <label><i class="fas fa-calendar-alt"></i> Mes</label>
                        <select name="mes">
                            <option value="">— Mes —</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $filtro_mes == $m ? 'selected' : ''; ?>>
                                    <?php echo $meses_espanol[$m] ?? date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo mes-especifico" id="anoEspecifico" style="<?php echo $filtro_fecha === 'mes_especifico' ? '' : 'display:none;'; ?>">
                        <label><i class="fas fa-calendar-alt"></i> Año</label>
                        <select name="ano">
                            <option value="">— Año —</option>
                            <?php foreach ($anos_lista as $ano): ?>
                                <option value="<?php echo $ano; ?>" <?php echo $filtro_ano == $ano ? 'selected' : ''; ?>>
                                    <?php echo $ano; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Fila 2: Tipo, Estado, Prioridad, Gestor -->
                <div class="filtros-fila">
                    <div class="filtro-grupo">
                        <label><i class="fas fa-list"></i> Tipo</label>
                        <select name="tipo">
                            <option value="">Todos</option>
                            <?php foreach ($tipos_solicitud as $key => $nombre): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filtro_tipo == $key ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label><i class="fas fa-tag"></i> Estado</label>
                        <select name="estado">
                            <option value="">Todos</option>
                            <?php foreach ($estados as $key => $nombre): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filtro_estado == $key ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label><i class="fas fa-bolt"></i> Prioridad</label>
                        <select name="prioridad">
                            <option value="">Todas</option>
                            <?php foreach ($prioridades as $key => $nombre): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filtro_prioridad == $key ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label><i class="fas fa-user-tie"></i> Gestor</label>
                        <select name="gestor">
                            <option value="">Todos</option>
                            <?php foreach ($gestores_lista as $g): ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $filtro_gestor == $g['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['nombre_completo']); ?> (<?php echo $g['total']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Fila 3: Sede, Marca, Buscar -->
                <div class="filtros-fila">
                    <div class="filtro-grupo">
                        <label><i class="fas fa-building"></i> Sede</label>
                        <select name="sede">
                            <option value="">Todas</option>
                            <?php foreach ($sedes_lista as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $filtro_sede == $s ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label><i class="fas fa-car"></i> Marca VH</label>
                        <select name="marca_vh">
                            <option value="">Todas</option>
                            <?php foreach ($marcas_lista as $m): ?>
                                <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $filtro_marca_vh == $m ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label><i class="fas fa-search"></i> Buscar</label>
                        <input type="text" name="buscar" value="<?php echo htmlspecialchars($filtro_buscar); ?>" placeholder="Código, asunto, cliente...">
                    </div>
                </div>

                <div class="filtros-acciones">
                    <button type="submit" class="btn-filtrar">
                        <i class="fas fa-filter"></i> Aplicar filtros
                    </button>
                    <?php if ($hay_filtros): ?>
                        <a href="estadisticas.php" class="btn-limpiar">
                            <i class="fas fa-times"></i> Limpiar filtros
                        </a>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <?php if ($total_pqrs == 0): ?>
            <!-- ===== EMPTY STATE ===== -->
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No hay datos para mostrar</h3>
                    <p>Prueba cambiando los filtros o verifica si hay PQRS en el rango seleccionado.</p>
                </div>
            </div>
        <?php else: ?>

            <!-- ===== KPIs ===== -->
            <div class="kpis-grid">
                <div class="kpi-card kpi-total">
                    <div class="icono-kpi"><i class="fas fa-clipboard-list"></i></div>
                    <div class="numero"><?php echo number_format($total_pqrs); ?></div>
                    <div class="label">Total PQRS</div>
                </div>

                <div class="kpi-card kpi-tiempo">
                    <div class="icono-kpi"><i class="fas fa-clock"></i></div>
                    <div class="numero"><?php echo $tiempo_promedio; ?></div>
                    <div class="label">Días promedio</div>
                    <div class="sublabel">para cerrar</div>
                </div>

                <div class="kpi-card kpi-tasa">
                    <div class="icono-kpi"><i class="fas fa-percentage"></i></div>
                    <div class="numero"><?php echo $tasa_resolucion; ?>%</div>
                    <div class="label">Tasa resolución</div>
                    <div class="sublabel"><?php echo $total_cerrados; ?> cerradas</div>
                </div>

                <div class="kpi-card kpi-sin">
                    <div class="icono-kpi"><i class="fas fa-user-slash"></i></div>
                    <div class="numero"><?php echo $total_sin_gestor; ?></div>
                    <div class="label">Sin gestor</div>
                    <div class="sublabel">pendientes de asignar</div>
                </div>

                <div class="kpi-card kpi-tipo">
                    <div class="icono-kpi"><i class="fas fa-tag"></i></div>
                    <div class="numero"><?php echo $tipo_mas_frecuente_cantidad; ?></div>
                    <div class="label"><?php echo $tipo_mas_frecuente_nombre; ?></div>
                    <div class="sublabel">tipo más frecuente</div>
                </div>

                <div class="kpi-card kpi-ciudad">
                    <div class="icono-kpi"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="numero"><?php echo $ciudad_mas_pqrs_cantidad; ?></div>
                    <div class="label"><?php echo htmlspecialchars($ciudad_mas_pqrs); ?></div>
                    <div class="sublabel">ciudad con más PQRS</div>
                </div>
            </div>

            <!-- ===== GRÁFICOS ===== -->
            <div class="graficos-grid">

                <!-- Gráfico 1: Distribución por Tipo -->
                <div class="grafico-card">
                    <h3><i class="fas fa-chart-pie"></i> Distribución por Tipo</h3>
                    <div class="grafico-container">
                        <canvas id="graficoTipos"></canvas>
                    </div>
                </div>

                <!-- Gráfico 2: Distribución por Estado -->
                <div class="grafico-card">
                    <h3><i class="fas fa-chart-bar"></i> Distribución por Estado</h3>
                    <div class="grafico-container">
                        <canvas id="graficoEstados"></canvas>
                    </div>
                </div>

                <!-- Gráfico 3: Evolución mensual -->
                <div class="grafico-card">
                    <h3><i class="fas fa-chart-line"></i> Evolución últimos 12 meses</h3>
                    <div class="grafico-container">
                        <canvas id="graficoEvolucion"></canvas>
                    </div>
                </div>

                <!-- Gráfico 4: Distribución por Prioridad -->
                <div class="grafico-card">
                    <h3><i class="fas fa-bolt"></i> Distribución por Prioridad</h3>
                    <div class="grafico-container">
                        <canvas id="graficoPrioridades"></canvas>
                    </div>
                </div>

                <!-- Gráfico 5: Top 10 Ciudades -->
                <div class="grafico-card">
                    <h3><i class="fas fa-city"></i> Top 10 Ciudades</h3>
                    <div class="grafico-container">
                        <canvas id="graficoCiudades"></canvas>
                    </div>
                </div>

                <!-- Gráfico 6: Distribución por Sede -->
                <div class="grafico-card">
                    <h3><i class="fas fa-building"></i> Distribución por Sede</h3>
                    <div class="grafico-container">
                        <canvas id="graficoSedes"></canvas>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>

    <!-- ===== DATOS PARA LOS GRÁFICOS ===== -->
    <script>
        // Datos desde PHP
        const dataTipos = <?php echo json_encode($data_tipos); ?>;
        const dataEstados = <?php echo json_encode($data_estados); ?>;
        const dataPrioridades = <?php echo json_encode($data_prioridades); ?>;
        const dataCiudades = <?php echo json_encode($data_ciudades); ?>;
        const dataSedes = <?php echo json_encode($data_sedes); ?>;
        const dataEvolucion = <?php echo json_encode($data_evolucion); ?>;
    </script>

    <!-- ===== SCRIPT DE GRÁFICOS ===== -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ============================================
            // CONFIGURACIÓN GLOBAL
            // ============================================
            Chart.defaults.font.family = "'Poppins', sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#2c3e50';

            const colorPalette = [
                '#e67e22', '#3498db', '#9b59b6', '#27ae60', '#e74c3c',
                '#f39c12', '#1abc9c', '#34495e', '#16a085', '#c0392b'
            ];

            // ============================================
            // GRÁFICO 1: Distribución por Tipo (Dona)
            // ============================================
            const ctxTipos = document.getElementById('graficoTipos');
            if (ctxTipos && dataTipos.length > 0) {
                new Chart(ctxTipos, {
                    type: 'doughnut',
                    data: {
                        labels: dataTipos.map(d => d.label),
                        datasets: [{
                            data: dataTipos.map(d => d.valor),
                            backgroundColor: colorPalette,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: { padding: 12, boxWidth: 12, font: { size: 11 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = ((context.parsed / total) * 100).toFixed(1);
                                        return `${context.label}: ${context.parsed} (${pct}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // ============================================
            // GRÁFICO 2: Distribución por Estado (Barras)
            // ============================================
            const ctxEstados = document.getElementById('graficoEstados');
            if (ctxEstados && dataEstados.length > 0) {
                const coloresEstados = ['#f39c12', '#3498db', '#9b59b6', '#27ae60'];
                new Chart(ctxEstados, {
                    type: 'bar',
                    data: {
                        labels: dataEstados.map(d => d.label),
                        datasets: [{
                            label: 'Cantidad',
                            data: dataEstados.map(d => d.valor),
                            backgroundColor: coloresEstados,
                            borderRadius: 8,
                            borderSkipped: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `PQRS: ${context.parsed.y}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1, precision: 0 }
                            }
                        }
                    }
                });
            }

            // ============================================
            // GRÁFICO 3: Evolución mensual (Línea)
            // ============================================
            const ctxEvolucion = document.getElementById('graficoEvolucion');
            if (ctxEvolucion && dataEvolucion.length > 0) {
                new Chart(ctxEvolucion, {
                    type: 'line',
                    data: {
                        labels: dataEvolucion.map(d => d.mes_label),
                        datasets: [
                            {
                                label: 'Total PQRS',
                                data: dataEvolucion.map(d => d.total),
                                borderColor: '#173742',
                                backgroundColor: 'rgba(23, 55, 66, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointBackgroundColor: '#173742'
                            },
                            {
                                label: 'Cerradas',
                                data: dataEvolucion.map(d => d.cerrados),
                                borderColor: '#27ae60',
                                backgroundColor: 'rgba(39, 174, 96, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointBackgroundColor: '#27ae60'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { padding: 15, usePointStyle: true, font: { size: 11 } }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1, precision: 0 }
                            }
                        }
                    }
                });
            }

            // ============================================
            // GRÁFICO 4: Distribución por Prioridad (Dona)
            // ============================================
            const ctxPrioridades = document.getElementById('graficoPrioridades');
            if (ctxPrioridades && dataPrioridades.length > 0) {
                const coloresPrio = { 'Alta': '#e74c3c', 'Media': '#f39c12', 'Baja': '#27ae60' };
                new Chart(ctxPrioridades, {
                    type: 'doughnut',
                    data: {
                        labels: dataPrioridades.map(d => d.label),
                        datasets: [{
                            data: dataPrioridades.map(d => d.valor),
                            backgroundColor: dataPrioridades.map(d => coloresPrio[d.label] || '#95a5a6'),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: { padding: 12, boxWidth: 12, font: { size: 11 } }
                            }
                        }
                    }
                });
            }

            // ============================================
            // GRÁFICO 5: Top 10 Ciudades (Barras horizontales)
            // ============================================
            const ctxCiudades = document.getElementById('graficoCiudades');
            if (ctxCiudades && dataCiudades.length > 0) {
                new Chart(ctxCiudades, {
                    type: 'bar',
                    data: {
                        labels: dataCiudades.map(d => d.ciudad),
                        datasets: [{
                            label: 'PQRS',
                            data: dataCiudades.map(d => d.total),
                            backgroundColor: '#3498db',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { stepSize: 1, precision: 0 }
                            }
                        }
                    }
                });
            }

            // ============================================
            // GRÁFICO 6: Distribución por Sede (Dona)
            // ============================================
            const ctxSedes = document.getElementById('graficoSedes');
            if (ctxSedes && dataSedes.length > 0) {
                new Chart(ctxSedes, {
                    type: 'pie',
                    data: {
                        labels: dataSedes.map(d => d.sede),
                        datasets: [{
                            data: dataSedes.map(d => d.total),
                            backgroundColor: colorPalette,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: { padding: 10, boxWidth: 10, font: { size: 10 } }
                            }
                        }
                    }
                });
            }

            // ============================================
            // TOGGLE DE CAMPOS DE FECHA
            // ============================================
            const selectFecha = document.getElementById('selectFecha');
            const rangoFechas = document.getElementById('rangoFechas');
            const rangoFechasHasta = document.getElementById('rangoFechasHasta');
            const mesEspecifico = document.getElementById('mesEspecifico');
            const anoEspecifico = document.getElementById('anoEspecifico');

            if (selectFecha) {
                selectFecha.addEventListener('change', function () {
                    const valor = this.value;
                    
                    if (rangoFechas) rangoFechas.style.display = valor === 'personalizado' ? '' : 'none';
                    if (rangoFechasHasta) rangoFechasHasta.style.display = valor === 'personalizado' ? '' : 'none';
                    if (mesEspecifico) mesEspecifico.style.display = valor === 'mes_especifico' ? '' : 'none';
                    if (anoEspecifico) anoEspecifico.style.display = valor === 'mes_especifico' ? '' : 'none';
                });
            }

        });
    </script>

</body>
</html>