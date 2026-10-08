<?php
// aplicaciones/otros/pqrs/index.php - Panel del Gestor PQRS

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/pqrs_helpers.php';

// ============================================
// 1. VERIFICAR SESIÓN
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id  = $_SESSION['usuario_id'];

// Admin y Supervisor no deberían entrar aquí (tienen su propia vista)
if ($rol_usuario == 1 || $rol_usuario == 2) {
    header('Location: ../../../admin/pqrs/index.php');
    exit();
}

// Solo gestores (rol 3)
$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

// 🔑 Aplicar vencimientos pendientes
aplicarVencimientosPendientes($pdo);

// ============================================
// 2. VERIFICAR QUE ES GESTOR ACTIVO
// ============================================
$stmt = $pdo->prepare("
    SELECT 1 FROM pqrs_gestores_usuarios 
    WHERE usuario_id = ? AND activo = 1
    LIMIT 1
");
$stmt->execute([$usuario_id]);
$es_gestor_activo = (bool) $stmt->fetch();

if (!$es_gestor_activo) {
    $_SESSION['error'] = "❌ No estás registrado como gestor de PQRS";
    header('Location: ../../../index.php');
    exit();
}

// ============================================
// 3. TIPOS DE SOLICITUD
// ============================================
$tipos_solicitud = [
    'PETICION'     => 'Petición',
    'QUEJA'        => 'Queja',
    'RECLAMO'      => 'Reclamo',
    'SUGERENCIA'   => 'Sugerencia',
    'FELICITACION' => 'Felicitación'
];

// ============================================
// 4. ESTADOS
// ============================================
$estados = [
    'abierto'                  => ['texto' => 'Abierto',                 'clase' => 'estado-abierto',   'icono' => 'fa-folder-open'],
    'en_proceso'               => ['texto' => 'En Proceso',              'clase' => 'estado-proceso',   'icono' => 'fa-spinner'],
    'analisis_servicio_cliente'=> ['texto' => 'Análisis Serv. Cliente',  'clase' => 'estado-analisis',  'icono' => 'fa-search'],
    'cerrado'                  => ['texto' => 'Cerrado',                 'clase' => 'estado-cerrado',   'icono' => 'fa-check-circle']
];

// ============================================
// 5. FILTROS
// ============================================
$filtro_estado      = $_GET['estado']      ?? '';
$filtro_tipo        = $_GET['tipo']        ?? '';
$filtro_fecha       = $_GET['fecha']       ?? 'todos';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_busqueda    = trim($_GET['q']      ?? '');
$filtro_buscar_codigo = trim($_GET['buscar_codigo'] ?? '');

// ============================================
// 6. CONSTRUIR WHERE
// ============================================
$where = [];
$params = [];

// 🔒 SOLO SUS PQRS ASIGNADAS (como principal O colaborador)
$where[] = "(
    p.gestor_principal_id = ? 
    OR p.id IN (SELECT pqrs_id FROM pqrs_gestores WHERE usuario_id = ?)
)";
$params[] = $usuario_id;
$params[] = $usuario_id;

// 🔒 SOLO ACTIVAS: excluir análisis y cerrado (esos van a Análisis Serv. Cliente)
$where[] = "p.estado IN ('abierto', 'en_proceso')";

// 🔍 Filtro por código PQRSF
if (!empty($filtro_buscar_codigo)) {
    $where[] = "LOWER(p.codigo) LIKE LOWER(?)";
    $params[] = '%' . $filtro_buscar_codigo . '%';
}

// Filtro por estado (solo si es abierto o en_proceso)
if (!empty($filtro_estado) && in_array($filtro_estado, ['abierto', 'en_proceso'])) {
    $where[] = "p.estado = ?";
    $params[] = $filtro_estado;
}

// Filtro por tipo
if (!empty($filtro_tipo) && array_key_exists($filtro_tipo, $tipos_solicitud)) {
    $where[] = "p.tipo_solicitud = ?";
    $params[] = $filtro_tipo;
}

// Filtro por fecha
$campo_fecha = "COALESCE(p.marca_temporal, p.creado_el)";
switch ($filtro_fecha) {
    case 'hoy':
        $where[] = "DATE($campo_fecha) = CURDATE()";
        break;
    case 'semana':
        $where[] = "YEARWEEK($campo_fecha, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'mes':
        $where[] = "YEAR($campo_fecha) = YEAR(CURDATE()) AND MONTH($campo_fecha) = MONTH(CURDATE())";
        break;
    case 'año':
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
}

// Filtro de búsqueda general
if (!empty($filtro_busqueda)) {
    $where[] = "(p.codigo LIKE ? OR p.asunto LIKE ? OR p.nombre_completo LIKE ? OR p.documento LIKE ?)";
    $busqueda_like = '%' . $filtro_busqueda . '%';
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ============================================
// 7. PAGINACIÓN
// ============================================
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;

$sql_count = "SELECT COUNT(*) AS total FROM pqrs p $where_clause";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_paginas = max(1, ceil($total_registros / $por_pagina));
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;
$offset = ($pagina_actual - 1) * $por_pagina;

// ============================================
// 8. ESTADÍSTICAS
// ============================================
$where_stats = "WHERE (
    p.gestor_principal_id = ? 
    OR p.id IN (SELECT pqrs_id FROM pqrs_gestores WHERE usuario_id = ?)
)";
$params_stats = [$usuario_id, $usuario_id];

$sql_stats = "SELECT 
                SUM(CASE WHEN p.estado IN ('abierto','en_proceso') THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN p.estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
                SUM(CASE WHEN p.estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN p.estado IN ('analisis_servicio_cliente','cerrado') THEN 1 ELSE 0 END) as en_analisis
               FROM pqrs p
               $where_stats";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute([$usuario_id, $usuario_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_pqrs = $stats['total'] ?? 0;
$abiertos = $stats['abiertos'] ?? 0;
$en_proceso = $stats['en_proceso'] ?? 0;
$en_analisis = $stats['en_analisis'] ?? 0;

// ============================================
// 9. OBTENER PQRS PAGINADAS
// ============================================
$sql = "SELECT 
            p.*,
            u.nombre_completo AS gestor_principal_nombre,
            (SELECT COUNT(*) FROM pqrs_mensajes WHERE pqrs_id = p.id) as total_mensajes,
            (SELECT COUNT(*) FROM pqrs_mensajes WHERE pqrs_id = p.id AND leido = 0 AND usuario_id != ?) as mensajes_nuevos,
            (SELECT mensaje FROM pqrs_mensajes WHERE pqrs_id = p.id ORDER BY creado_el DESC LIMIT 1) as ultimo_mensaje,
            (SELECT u2.nombre_completo FROM pqrs_mensajes rm 
             LEFT JOIN usuarios u2 ON rm.usuario_id = u2.id 
             WHERE rm.pqrs_id = p.id ORDER BY rm.creado_el DESC LIMIT 1) as ultimo_mensaje_autor
        FROM pqrs p
        LEFT JOIN usuarios u ON p.gestor_principal_id = u.id
        $where_clause
        ORDER BY 
            CASE p.estado 
                WHEN 'abierto' THEN 1 
                WHEN 'en_proceso' THEN 2 
                ELSE 3
            END,
            p.actualizado_el DESC, 
            p.creado_el DESC
        LIMIT $por_pagina OFFSET $offset";

$params_final = array_merge([$usuario_id], $params);

$stmt = $pdo->prepare($sql);
$stmt->execute($params_final);
$pqrs_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 10. HELPER: URL con página
// ============================================
function url_con_pagina($pagina) {
    $params = $_GET;
    $params['pagina'] = $pagina;
    return '?' . http_build_query($params);
}

// ============================================
// 11. RECUPERAR MENSAJES FLASH
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

$hay_filtros_activos = !empty($filtro_estado) || !empty($filtro_tipo) 
    || ($filtro_fecha !== 'todos' && !empty($filtro_fecha))
    || !empty($filtro_busqueda)
    || !empty($filtro_buscar_codigo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis PQRSF | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        /* ===== BIENVENIDA ===== */
        .bienvenida {
            background: linear-gradient(135deg, #12232b, #173742);
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 25px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            flex-wrap: wrap;
        }
        .bienvenida .icono-user {
            width: 60px;
            height: 60px;
            background: rgba(255,193,7,0.15);
            color: #ffc107;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
        .bienvenida h2 { font-size: 20px; margin-bottom: 3px; }
        .bienvenida p { font-size: 13px; color: #8aa8b8; }

        /* ===== MENSAJES FLASH ===== */
        .mensaje-exito, .mensaje-error {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .mensaje-exito { background: #d4edda; color: #155724; border-left: 4px solid #27ae60; }
        .mensaje-error { background: #f8d7da; color: #721c24; border-left: 4px solid #e74c3c; }

        /* ===== DASHBOARD CARDS ===== */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        @media (max-width: 992px) { .dashboard-cards { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .dashboard-cards { grid-template-columns: 1fr; } }

        .card-dashboard {
            background: white;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border-top: 4px solid;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .card-dashboard:hover { transform: translateY(-5px); }
        .card-dashboard i { font-size: 26px; margin-bottom: 6px; }
        .card-dashboard .numero { font-size: 24px; font-weight: 700; color: #12232b; }
        .card-dashboard .label { font-size: 11px; color: #7f8c8d; font-weight: 500; }

        .card-dashboard.total { border-color: #173742; }
        .card-dashboard.total i { color: #173742; }
        .card-dashboard.abierto { border-color: #f39c12; }
        .card-dashboard.abierto i { color: #f39c12; }
        .card-dashboard.proceso { border-color: #3498db; }
        .card-dashboard.proceso i { color: #3498db; }
        .card-dashboard.analisis { 
            border-color: #9b59b6;
            background: linear-gradient(135deg, #ffffff, #f8f0fc);
        }
        .card-dashboard.analisis i { color: #9b59b6; }
        .card-dashboard.analisis:hover {
            background: linear-gradient(135deg, #f8f0fc, #ebdef0);
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
            margin-bottom: 25px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        .filtros-container form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .filtros-fila {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filtro-grupo {
            flex: 1;
            min-width: 160px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filtro-grupo label {
            font-size: 12px;
            font-weight: 600;
            color: #445960;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filtro-grupo label i { color: #173742; font-size: 12px; }
        .filtro-grupo select,
        .filtro-grupo input[type="date"],
        .filtro-grupo input[type="text"] {
            padding: 10px 12px;
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
            padding-top: 5px;
        }
        .btn-filtrar {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            padding: 11px 24px;
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

        /* ===== CHIPS ===== */
        .chips-filtros-activos {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 3px solid #e67e22;
        }
        .chips-label {
            font-size: 12px;
            font-weight: 600;
            color: #445960;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .chips-label i { color: #e67e22; }
        .chip-filtro {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            color: #173742;
            padding: 5px 10px 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #d3d3d3;
            transition: all 0.25s ease;
        }
        .chip-filtro:hover { background: #173742; color: white; border-color: #173742; }
        .chip-filtro:hover .chip-close { color: #ffc107; }
        .chip-filtro i:first-child { color: #e67e22; font-size: 11px; }
        .chip-filtro:hover i:first-child { color: #ffc107; }
        .chip-close { font-size: 10px; color: #7f8c8d; transition: color 0.25s ease; margin-left: 2px; }

        /* ===== LISTA PQRS ===== */
        .pqrs-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .pqrs-item {
            background: white;
            border-radius: 10px;
            padding: 12px 16px;
            border-left: 4px solid #f39c12;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.25s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
        }
        .pqrs-item:hover {
            transform: translateX(4px);
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            background: #f8f9fa;
        }
        .pqrs-item.estado-abierto { border-left-color: #f39c12; }
        .pqrs-item.estado-en-proceso { border-left-color: #3498db; }

        .pqrs-info { flex: 1; min-width: 220px; }
        .pqrs-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            flex-wrap: wrap;
        }
        .pqrs-codigo {
            font-size: 11px;
            color: #173742;
            font-weight: 700;
            background: #e8f0fe;
            padding: 2px 8px;
            border-radius: 8px;
        }
        .pqrs-asunto {
            font-size: 13.5px;
            font-weight: 600;
            color: #12232b;
        }
        .pqrs-meta {
            display: flex;
            gap: 10px;
            font-size: 11px;
            color: #7f8c8d;
            flex-wrap: wrap;
            align-items: center;
        }
        .pqrs-meta i { margin-right: 3px; color: #173742; font-size: 10px; }
        .cliente-info {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #e8f0fe;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10.5px;
            color: #173742;
            font-weight: 600;
        }
        .rol-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }
        .rol-principal { background: #fef9e7; color: #7f6000; }
        .rol-colaborador { background: #f0f2f5; color: #445960; }
        .pqrs-ultimo-mensaje {
            font-size: 11.5px;
            color: #7f8c8d;
            margin-top: 4px;
            font-style: italic;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
        }

        .pqrs-estado {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }

        .badge-estado {
            padding: 4px 11px;
            border-radius: 16px;
            font-size: 10.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .estado-abierto { background: #fef9e7; color: #7f6000; border: 1px solid #f39c12; }
        .estado-proceso { background: #d6eaf8; color: #1a5276; border: 1px solid #3498db; }

        .badge-mensajes {
            background: #e74c3c;
            color: white;
            padding: 3px 9px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            animation: pulse 2s infinite;
        }
        .badge-total-mensajes {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 3px 9px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ===== PAGINACIÓN ===== */
        .paginacion {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        .pag-btn {
            min-width: 38px;
            height: 38px;
            padding: 0 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            color: #2c3e50;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .pag-btn:hover:not(.disabled):not(.activa) {
            background: #f8f9fa;
            border-color: #173742;
            color: #173742;
            transform: translateY(-2px);
        }
        .pag-btn.activa {
            background: linear-gradient(135deg, #173742, #1a4a55);
            border-color: #173742;
            color: white;
            cursor: default;
        }
        .pag-btn.disabled { opacity: 0.35; cursor: not-allowed; background: #f8f9fa; color: #b0b0b0; }
        .pag-btn.pag-nav { background: #f8f9fa; color: #445960; }
        .pag-btn.pag-nav:hover:not(.disabled) { background: #173742; color: white; border-color: #173742; }
        .pag-ellipsis { color: #b0b0b0; font-weight: 700; padding: 0 4px; }
        .pag-info { margin-left: 12px; font-size: 12.5px; color: #7f8c8d; }
        .pag-info strong { color: #173742; }

        /* ===== BADGE DE FECHA LÍMITE ===== */
        .badge-fecha {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-fecha.fecha-ok { background: #eafaf1; color: #196f3d; border: 1px solid #27ae60; }
        .badge-fecha.fecha-proxima { background: #fef9e7; color: #7f6000; border: 1px solid #f39c12; }
        .badge-fecha.fecha-hoy { background: #fadbd8; color: #922b21; border: 1px solid #e74c3c; animation: pulse 2s infinite; }
        .badge-fecha.fecha-vencida { background: #f5b7b1; color: #7b241c; border: 1px solid #c0392b; }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .bienvenida { flex-direction: column; text-align: center; }
            .pqrs-item { flex-direction: column; align-items: flex-start; }
            .pqrs-estado { align-items: flex-start; width: 100%; flex-direction: row; justify-content: space-between; }
            .filtros-fila { flex-direction: column; }
            .filtro-grupo { min-width: 100%; }
            .filtros-acciones { flex-direction: column; }
            .btn-filtrar, .btn-limpiar { width: 100%; justify-content: center; }
            .paginacion { gap: 4px; }
            .pag-btn { min-width: 34px; height: 34px; font-size: 12px; padding: 0 8px; }
            .pag-info { width: 100%; text-align: center; margin-left: 0; margin-top: 10px; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
                <p class="logo-subtitle">| Mis PQRSF</p>
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al panel
            </a>
        </div>
    </header>

    <div class="container">

        <!-- ===== BIENVENIDA ===== -->
        <div class="bienvenida">
            <div class="icono-user">
                <i class="fas fa-user-tie"></i>
            </div>
            <div>
                <h2>Hola, <?php echo htmlspecialchars($nombre_usuario); ?> 👋</h2>
                <p>Panel de gestión de PQRS asignadas a ti</p>
            </div>
        </div>

        <!-- ===== MENSAJES FLASH ===== -->
        <?php if ($mensaje): ?>
            <div class="mensaje-exito" id="mensajeExito">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mensaje-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ===== DASHBOARD ===== -->
        <div class="dashboard-cards">
            <a href="?" class="card-dashboard total">
                <i class="fas fa-clipboard-list"></i>
                <div class="numero"><?php echo $total_pqrs; ?></div>
                <div class="label">Mis PQRS Activas</div>
            </a>
            <a href="?estado=abierto" class="card-dashboard abierto">
                <i class="fas fa-folder-open"></i>
                <div class="numero"><?php echo $abiertos; ?></div>
                <div class="label">Abiertas</div>
            </a>
            <a href="?estado=en_proceso" class="card-dashboard proceso">
                <i class="fas fa-spinner"></i>
                <div class="numero"><?php echo $en_proceso; ?></div>
                <div class="label">En Proceso</div>
            </a>
            <a href="analisis.php" class="card-dashboard analisis">
                <i class="fas fa-search"></i>
                <div class="numero"><?php echo $en_analisis; ?></div>
                <div class="label">Análisis Serv. Cliente</div>
            </a>
        </div>

        <!-- ===== LISTA ===== -->
        <div class="card">
            <h2>
                <i class="fas fa-list"></i>
                <?php echo $hay_filtros_activos ? 'PQRS filtradas' : 'Mis PQRS activas'; ?>
                <span style="font-size: 13px; font-weight: 400; color: #7f8c8d;">
                    (<?php echo $total_registros; ?> en total
                    <?php if ($total_paginas > 1): ?>
                        · mostrando <?php echo $offset + 1; ?>–<?php echo min($offset + $por_pagina, $total_registros); ?>
                    <?php endif; ?>)
                </span>
            </h2>

            <!-- ===== FILTROS ===== -->
            <div class="filtros-container">
                <form method="GET" action="" id="formFiltros">

                    <!-- 🔍 Buscador por código -->
                    <div class="filtros-fila" style="margin-bottom: 15px;">
                        <div class="filtro-grupo" style="flex: 100%;">
                            <label><i class="fas fa-hashtag"></i> Buscar por código PQRSF</label>
                            <input type="text" 
                                   name="buscar_codigo" 
                                   value="<?php echo htmlspecialchars($filtro_buscar_codigo); ?>" 
                                   placeholder="Ej: Q-20260923-1"
                                   autocomplete="off">
                        </div>
                    </div>

                    <div class="filtros-fila">
                        <div class="filtro-grupo">
                            <label><i class="fas fa-search"></i> Buscar</label>
                            <input type="text" name="q" value="<?php echo htmlspecialchars($filtro_busqueda); ?>" placeholder="Código, asunto, cliente...">
                        </div>

                        <div class="filtro-grupo">
                            <label><i class="fas fa-tag"></i> Estado</label>
                            <select name="estado">
                                <option value="">Todos (activos)</option>
                                <option value="abierto" <?php echo $filtro_estado == 'abierto' ? 'selected' : ''; ?>>Abierto</option>
                                <option value="en_proceso" <?php echo $filtro_estado == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                            </select>
                        </div>

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
                    </div>

                    <div class="filtros-fila">
                        <div class="filtro-grupo">
                            <label><i class="fas fa-calendar-alt"></i> Período</label>
                            <select name="fecha" id="selectFecha">
                                <option value="todos" <?php echo $filtro_fecha === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="hoy" <?php echo $filtro_fecha === 'hoy' ? 'selected' : ''; ?>>Hoy</option>
                                <option value="semana" <?php echo $filtro_fecha === 'semana' ? 'selected' : ''; ?>>Esta semana</option>
                                <option value="mes" <?php echo $filtro_fecha === 'mes' ? 'selected' : ''; ?>>Este mes</option>
                                <option value="año" <?php echo $filtro_fecha === 'año' ? 'selected' : ''; ?>>Este año</option>
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
                    </div>

                    <div class="filtros-acciones">
                        <button type="submit" class="btn-filtrar">
                            <i class="fas fa-filter"></i> Aplicar filtros
                        </button>
                        <?php if ($hay_filtros_activos): ?>
                            <a href="index.php" class="btn-limpiar">
                                <i class="fas fa-times"></i> Limpiar filtros
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- ===== CHIPS ===== -->
            <?php 
            $chips_activos = [];

            if (!empty($filtro_buscar_codigo)) {
                $chips_activos[] = [
                    'icono' => 'fa-hashtag',
                    'label' => 'Código: "' . $filtro_buscar_codigo . '"',
                    'url'   => '?' . http_build_query(array_diff_key($_GET, ['buscar_codigo' => '', 'pagina' => '']))
                ];
            }

            if (!empty($filtro_busqueda)) {
                $chips_activos[] = [
                    'icono' => 'fa-search',
                    'label' => 'Buscar: "' . $filtro_busqueda . '"',
                    'url'   => '?' . http_build_query(array_diff_key($_GET, ['q' => '', 'pagina' => '']))
                ];
            }
            if (!empty($filtro_estado) && isset($estados[$filtro_estado])) {
                $chips_activos[] = [
                    'icono' => $estados[$filtro_estado]['icono'],
                    'label' => 'Estado: ' . $estados[$filtro_estado]['texto'],
                    'url'   => '?' . http_build_query(array_diff_key($_GET, ['estado' => '', 'pagina' => '']))
                ];
            }
            if (!empty($filtro_tipo) && isset($tipos_solicitud[$filtro_tipo])) {
                $chips_activos[] = [
                    'icono' => 'fa-tag',
                    'label' => 'Tipo: ' . $tipos_solicitud[$filtro_tipo],
                    'url'   => '?' . http_build_query(array_diff_key($_GET, ['tipo' => '', 'pagina' => '']))
                ];
            }
            if ($filtro_fecha !== 'todos' && !empty($filtro_fecha)) {
                $labels_fecha = [
                    'hoy' => 'Hoy',
                    'semana' => 'Esta semana',
                    'mes' => 'Este mes',
                    'año' => 'Este año',
                    'personalizado' => 'Desde ' . ($filtro_fecha_desde ?: '?') . ' hasta ' . ($filtro_fecha_hasta ?: '?')
                ];
                $chips_activos[] = [
                    'icono' => 'fa-calendar-alt',
                    'label' => 'Fecha: ' . ($labels_fecha[$filtro_fecha] ?? $filtro_fecha),
                    'url'   => '?' . http_build_query(array_diff_key($_GET, ['fecha' => '', 'fecha_desde' => '', 'fecha_hasta' => '', 'pagina' => '']))
                ];
            }
            ?>

            <?php if (!empty($chips_activos)): ?>
                <div class="chips-filtros-activos">
                    <span class="chips-label"><i class="fas fa-filter"></i> Filtros activos:</span>
                    <?php foreach ($chips_activos as $chip): ?>
                        <a href="<?php echo htmlspecialchars($chip['url']); ?>" class="chip-filtro" title="Quitar este filtro">
                            <i class="fas <?php echo $chip['icono']; ?>"></i>
                            <?php echo htmlspecialchars($chip['label']); ?>
                            <i class="fas fa-times chip-close"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ===== LISTA ===== -->
            <?php if (empty($pqrs_lista)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>
                        <?php if ($hay_filtros_activos): ?>
                            No hay PQRS con estos filtros
                        <?php else: ?>
                            No tienes PQRS activas
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?php if ($hay_filtros_activos): ?>
                            Prueba a quitar algún filtro.
                        <?php else: ?>
                            Cuando te asignen una PQRS activa aparecerá aquí.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="pqrs-lista">
                    <?php foreach ($pqrs_lista as $pqrs): 
                        $estado_info = $estados[$pqrs['estado']] ?? $estados['abierto'];
                        $estado_clase = str_replace('_', '-', $pqrs['estado']);
                        $tiene_nuevos = $pqrs['mensajes_nuevos'] > 0;
                        $soy_principal = ($pqrs['gestor_principal_id'] == $usuario_id);
                    ?>
                        <a href="ver.php?id=<?php echo $pqrs['id']; ?>" 
                           class="pqrs-item estado-<?php echo $estado_clase; ?> <?php echo $tiene_nuevos ? 'tiene-nuevos' : ''; ?>">
                            <div class="pqrs-info">
                                <div class="pqrs-header">
                                    <span class="pqrs-codigo"><?php echo htmlspecialchars($pqrs['codigo']); ?></span>
                                    <span class="pqrs-asunto"><?php echo htmlspecialchars($pqrs['asunto']); ?></span>
                                    <?php 
                                    $info_limite = infoFechaFimite($pqrs['fecha_limite'] ?? null);
                                    ?>
                                    <?php if ($info_limite): ?>
                                        <span class="badge-fecha <?php echo $info_limite['clase']; ?>">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $info_limite['texto']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="pqrs-meta">
                                    <span class="cliente-info">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($pqrs['nombre_completo']); ?>
                                    </span>
                                    <span class="rol-badge <?php echo $soy_principal ? 'rol-principal' : 'rol-colaborador'; ?>">
                                        <i class="fas fa-<?php echo $soy_principal ? 'star' : 'user-friends'; ?>"></i>
                                        <?php echo $soy_principal ? 'Principal' : 'Colaborador'; ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-tag"></i>
                                        <?php echo $tipos_solicitud[$pqrs['tipo_solicitud']] ?? $pqrs['tipo_solicitud']; ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($pqrs['marca_temporal'] ?? $pqrs['creado_el'])); ?>
                                    </span>
                                </div>
                                <?php if (!empty($pqrs['ultimo_mensaje'])): ?>
                                    <div class="pqrs-ultimo-mensaje">
                                        <i class="fas fa-comment-dots" style="color: #173742;"></i>
                                        <strong><?php echo htmlspecialchars($pqrs['ultimo_mensaje_autor'] ?? 'Sistema'); ?>:</strong>
                                        <?php echo htmlspecialchars(mb_strimwidth($pqrs['ultimo_mensaje'], 0, 70, '...')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="pqrs-estado">
                                <span class="badge-estado <?php echo $estado_info['clase']; ?>">
                                    <i class="fas <?php echo $estado_info['icono']; ?>"></i>
                                    <?php echo $estado_info['texto']; ?>
                                </span>
                                
                                <?php if ($tiene_nuevos): ?>
                                    <span class="badge-mensajes">
                                        <i class="fas fa-bell"></i>
                                        <?php echo $pqrs['mensajes_nuevos']; ?> nuevo<?php echo $pqrs['mensajes_nuevos'] > 1 ? 's' : ''; ?>
                                    </span>
                                <?php elseif ($pqrs['total_mensajes'] > 0): ?>
                                    <span class="badge-total-mensajes">
                                        <i class="fas fa-comment"></i>
                                        <?php echo $pqrs['total_mensajes']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- ===== PAGINACIÓN ===== -->
                <?php if ($total_paginas > 1): ?>
                    <div class="paginacion">
                        <?php if ($pagina_actual > 1): ?>
                            <a href="<?php echo url_con_pagina(1); ?>" class="pag-btn pag-nav" title="Primera">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="<?php echo url_con_pagina($pagina_actual - 1); ?>" class="pag-btn pag-nav" title="Anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="pag-btn pag-nav disabled"><i class="fas fa-angle-double-left"></i></span>
                            <span class="pag-btn pag-nav disabled"><i class="fas fa-angle-left"></i></span>
                        <?php endif; ?>

                        <?php
                        $rango = 2;
                        $inicio = max(1, $pagina_actual - $rango);
                        $fin = min($total_paginas, $pagina_actual + $rango);

                        if ($inicio > 1) {
                            echo '<a href="' . url_con_pagina(1) . '" class="pag-btn">1</a>';
                            if ($inicio > 2) echo '<span class="pag-ellipsis">...</span>';
                        }
                        for ($i = $inicio; $i <= $fin; $i++) {
                            $clase = $i == $pagina_actual ? 'pag-btn activa' : 'pag-btn';
                            echo '<a href="' . url_con_pagina($i) . '" class="' . $clase . '">' . $i . '</a>';
                        }
                        if ($fin < $total_paginas) {
                            if ($fin < $total_paginas - 1) echo '<span class="pag-ellipsis">...</span>';
                            echo '<a href="' . url_con_pagina($total_paginas) . '" class="pag-btn">' . $total_paginas . '</a>';
                        }
                        ?>

                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="<?php echo url_con_pagina($pagina_actual + 1); ?>" class="pag-btn pag-nav" title="Siguiente">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="<?php echo url_con_pagina($total_paginas); ?>" class="pag-btn pag-nav" title="Última">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pag-btn pag-nav disabled"><i class="fas fa-angle-right"></i></span>
                            <span class="pag-btn pag-nav disabled"><i class="fas fa-angle-double-right"></i></span>
                        <?php endif; ?>

                        <div class="pag-info">
                            Página <strong><?php echo $pagina_actual; ?></strong> de <strong><?php echo $total_paginas; ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const mensajeExito = document.getElementById('mensajeExito');
            if (mensajeExito) {
                setTimeout(() => {
                    mensajeExito.style.transition = 'opacity 0.5s ease';
                    mensajeExito.style.opacity = '0';
                    setTimeout(() => mensajeExito.remove(), 500);
                }, 4000);
            }

            const selectFecha = document.getElementById('selectFecha');
            const rangoFechas = document.getElementById('rangoFechas');
            const rangoFechasHasta = document.getElementById('rangoFechasHasta');

            if (selectFecha) {
                selectFecha.addEventListener('change', function () {
                    const esPersonalizado = this.value === 'personalizado';
                    if (rangoFechas) rangoFechas.style.display = esPersonalizado ? '' : 'none';
                    if (rangoFechasHasta) rangoFechasHasta.style.display = esPersonalizado ? '' : 'none';
                });
            }

            if (window.location.search.includes('pagina=')) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

        });
    </script>
</body>
</html>