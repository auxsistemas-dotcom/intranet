<?php
// aplicaciones/otros/pqrs/analisis.php - Análisis Servicio al Cliente (Vista del Gestor)

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

// Admin y Supervisor → redirigir a su vista
if ($rol_usuario == 1 || $rol_usuario == 2) {
    header('Location: ../../../admin/pqrs/index.php');
    exit();
}

// Solo gestores
$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

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
    'analisis_servicio_cliente'=> ['texto' => 'Análisis Serv. Cliente',  'clase' => 'estado-analisis',  'icono' => 'fa-search'],
    'cerrado'                  => ['texto' => 'Cerrado',                 'clase' => 'estado-cerrado',   'icono' => 'fa-check-circle']
];

// ============================================
// 5. FILTROS
// ============================================
$filtro_estado      = $_GET['estado']      ?? '';
$filtro_tipo        = $_GET['tipo']        ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
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

// 🔒 SOLO LOS QUE ESTÁN EN ANÁLISIS O CERRADOS (van al histórico del gestor)
$where[] = "p.estado IN ('analisis_servicio_cliente', 'cerrado')";

// 🔍 Filtro por código
if (!empty($filtro_buscar_codigo)) {
    $where[] = "LOWER(p.codigo) LIKE LOWER(?)";
    $params[] = '%' . $filtro_buscar_codigo . '%';
}

// Filtro por estado
if (!empty($filtro_estado) && in_array($filtro_estado, ['analisis_servicio_cliente', 'cerrado'])) {
    $where[] = "p.estado = ?";
    $params[] = $filtro_estado;
}

// Filtro por tipo
if (!empty($filtro_tipo) && array_key_exists($filtro_tipo, $tipos_solicitud)) {
    $where[] = "p.tipo_solicitud = ?";
    $params[] = $filtro_tipo;
}

// Filtro por fecha de creación
$campo_fecha = "COALESCE(p.marca_temporal, p.creado_el)";
if (!empty($filtro_fecha_desde)) {
    $where[] = "DATE($campo_fecha) >= ?";
    $params[] = $filtro_fecha_desde;
}
if (!empty($filtro_fecha_hasta)) {
    $where[] = "DATE($campo_fecha) <= ?";
    $params[] = $filtro_fecha_hasta;
}

$where_clause = "WHERE " . implode(" AND ", $where);

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
// 8. ESTADÍSTICAS (siempre sin filtros, solo mis PQRS)
// ============================================
$where_stats = "WHERE (
    p.gestor_principal_id = ? 
    OR p.id IN (SELECT pqrs_id FROM pqrs_gestores WHERE usuario_id = ?)
)";
$params_stats = [$usuario_id, $usuario_id];

$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN p.estado = 'analisis_servicio_cliente' THEN 1 ELSE 0 END) as en_analisis,
                SUM(CASE WHEN p.estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados
               FROM pqrs p
               $where_stats";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute([$usuario_id, $usuario_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_analisis = $stats['total'] ?? 0;
$en_analisis = $stats['en_analisis'] ?? 0;
$cerrados_gestor = $stats['cerrados'] ?? 0;

// ============================================
// 9. OBTENER PQRS PAGINADAS
// ============================================
$sql = "SELECT 
            p.*,
            u.nombre_completo AS gestor_principal_nombre,
            (SELECT COUNT(*) FROM pqrs_mensajes WHERE pqrs_id = p.id) as total_mensajes
        FROM pqrs p
        LEFT JOIN usuarios u ON p.gestor_principal_id = u.id
        $where_clause
        ORDER BY p.actualizado_el DESC, p.creado_el DESC
        LIMIT $por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
    || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)
    || !empty($filtro_buscar_codigo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis Serv. Cliente | INTRANET</title>
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

        /* ===== BANNER ===== */
        .banner-analisis {
            background: linear-gradient(135deg, #6c3483, #9b59b6);
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 25px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 20px rgba(155,89,182,0.3);
            flex-wrap: wrap;
        }
        .banner-analisis .icono {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.15);
            color: #fff;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        .banner-analisis h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .banner-analisis p {
            font-size: 13px;
            color: #e8daef;
        }
        .banner-analisis .contador {
            margin-left: auto;
            background: rgba(255,255,255,0.15);
            padding: 8px 18px;
            border-radius: 12px;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
        }

        /* ===== CARDS DE RESUMEN ===== */
        .resumen-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        @media (max-width: 768px) { .resumen-cards { grid-template-columns: 1fr; } }

        .resumen-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-top: 4px solid;
        }
        .resumen-card i { font-size: 28px; margin-bottom: 8px; }
        .resumen-card .numero { font-size: 26px; font-weight: 700; color: #12232b; }
        .resumen-card .label { font-size: 12px; color: #7f8c8d; font-weight: 500; }

        .resumen-card.total { border-color: #9b59b6; }
        .resumen-card.total i { color: #9b59b6; }
        .resumen-card.analisis { border-color: #9b59b6; }
        .resumen-card.analisis i { color: #9b59b6; }
        .resumen-card.cerrado { border-color: #27ae60; }
        .resumen-card.cerrado i { color: #27ae60; }

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
            border-left: 4px solid #9b59b6;
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
        .filtro-grupo label i { color: #9b59b6; font-size: 12px; }
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
            border-color: #9b59b6;
            box-shadow: 0 0 0 3px rgba(155,89,182,0.1);
        }

        .filtros-acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            padding-top: 5px;
        }
        .btn-filtrar {
            background: linear-gradient(135deg, #6c3483, #9b59b6);
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
            box-shadow: 0 4px 12px rgba(155,89,182,0.3);
        }
        .btn-filtrar:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(155,89,182,0.4); }
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
            border-left: 3px solid #9b59b6;
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
        .chips-label i { color: #9b59b6; }
        .chip-filtro {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            color: #6c3483;
            padding: 5px 10px 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #d3d3d3;
            transition: all 0.25s ease;
        }
        .chip-filtro:hover { background: #6c3483; color: white; border-color: #6c3483; }
        .chip-filtro:hover .chip-close { color: #ffc107; }
        .chip-filtro i:first-child { color: #9b59b6; font-size: 11px; }
        .chip-filtro:hover i:first-child { color: #ffc107; }
        .chip-close { font-size: 10px; color: #7f8c8d; transition: color 0.25s ease; margin-left: 2px; }

        /* ===== LISTA ===== */
        .pqrs-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .pqrs-item {
            background: white;
            border-radius: 10px;
            padding: 14px 18px;
            border-left: 4px solid #9b59b6;
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
        .pqrs-item.estado-cerrado {
            border-left-color: #27ae60;
            opacity: 0.9;
        }
        .pqrs-item.estado-cerrado:hover { opacity: 1; }

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
        .cierre-info {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #d5f5e3;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10.5px;
            color: #1a7a3a;
            font-weight: 600;
        }

        .pqrs-estado {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }

        .badge-estado {
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 10.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .estado-analisis { background: #ebdef0; color: #6c3483; border: 1px solid #9b59b6; }
        .estado-cerrado { background: #d5f5e3; color: #1a7a3a; border: 1px solid #27ae60; }

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
            border-color: #9b59b6;
            color: #6c3483;
            transform: translateY(-2px);
        }
        .pag-btn.activa {
            background: linear-gradient(135deg, #6c3483, #9b59b6);
            border-color: #9b59b6;
            color: white;
            cursor: default;
        }
        .pag-btn.disabled { opacity: 0.35; cursor: not-allowed; background: #f8f9fa; color: #b0b0b0; }
        .pag-btn.pag-nav { background: #f8f9fa; color: #445960; }
        .pag-btn.pag-nav:hover:not(.disabled) { background: #6c3483; color: white; border-color: #6c3483; }
        .pag-ellipsis { color: #b0b0b0; font-weight: 700; padding: 0 4px; }
        .pag-info { margin-left: 12px; font-size: 12.5px; color: #7f8c8d; }
        .pag-info strong { color: #6c3483; }

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
            .banner-analisis { flex-direction: column; text-align: center; }
            .banner-analisis .contador { margin-left: 0; }
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
                <p class="logo-subtitle">| Análisis Serv. Cliente</p>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver a mis PQRS
            </a>
        </div>
    </header>

    <div class="container">

        <!-- ===== BANNER ===== -->
        <div class="banner-analisis">
            <div class="icono">
                <i class="fas fa-search"></i>
            </div>
            <div>
                <h2>Análisis Servicio al Cliente</h2>
                <p>PQRS que has enviado a revisión por un supervisor</p>
            </div>
            <div class="contador">
                <?php echo $total_analisis; ?>
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

        <!-- ===== RESUMEN ===== -->
        <div class="resumen-cards">
            <div class="resumen-card total">
                <i class="fas fa-clipboard-list"></i>
                <div class="numero"><?php echo $total_analisis; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="resumen-card analisis">
                <i class="fas fa-search"></i>
                <div class="numero"><?php echo $en_analisis; ?></div>
                <div class="label">En Análisis</div>
            </div>
            <div class="resumen-card cerrado">
                <i class="fas fa-check-circle"></i>
                <div class="numero"><?php echo $cerrados_gestor; ?></div>
                <div class="label">Cerrados por Supervisor</div>
            </div>
        </div>

        <!-- ===== LISTA ===== -->
        <div class="card">
            <h2>
                <i class="fas fa-list"></i>
                <?php echo $hay_filtros_activos ? 'Resultados filtrados' : 'Todos los enviados a Análisis'; ?>
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
                            <label><i class="fas fa-tag"></i> Estado</label>
                            <select name="estado">
                                <option value="">Todos</option>
                                <option value="analisis_servicio_cliente" <?php echo $filtro_estado == 'analisis_servicio_cliente' ? 'selected' : ''; ?>>En Análisis</option>
                                <option value="cerrado" <?php echo $filtro_estado == 'cerrado' ? 'selected' : ''; ?>>Cerrados</option>
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
                            <label><i class="fas fa-calendar-day"></i> Creada desde</label>
                            <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                        </div>

                        <div class="filtro-grupo">
                            <label><i class="fas fa-calendar-day"></i> Creada hasta</label>
                            <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                        </div>
                    </div>

                    <div class="filtros-acciones">
                        <button type="submit" class="btn-filtrar">
                            <i class="fas fa-filter"></i> Aplicar filtros
                        </button>
                        <?php if ($hay_filtros_activos): ?>
                            <a href="analisis.php" class="btn-limpiar">
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

            if (!empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)) {
                $label_fecha = 'Creada: ';
                if ($filtro_fecha_desde && $filtro_fecha_hasta) {
                    $label_fecha .= $filtro_fecha_desde . ' → ' . $filtro_fecha_hasta;
                } elseif ($filtro_fecha_desde) {
                    $label_fecha .= 'desde ' . $filtro_fecha_desde;
                } else {
                    $label_fecha .= 'hasta ' . $filtro_fecha_hasta;
                }
                $chips_activos[] = [
                    'icono' => 'fa-calendar-alt',
                    'label' => $label_fecha,
                    'url'   => '?' . http_build_query(array_diff_key($_GET, ['fecha_desde' => '', 'fecha_hasta' => '', 'pagina' => '']))
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
                    <i class="fas fa-search"></i>
                    <h3>No hay PQRS en Análisis Serv. Cliente</h3>
                    <p>Cuando envíes una PQRS a revisión por un supervisor, aparecerá aquí.</p>
                </div>
            <?php else: ?>
                <div class="pqrs-lista">
                    <?php foreach ($pqrs_lista as $pqrs): 
                        $estado_info = $estados[$pqrs['estado']] ?? $estados['analisis_servicio_cliente'];
                        $estado_clase = str_replace('_', '-', $pqrs['estado']);
                    ?>
                        <a href="ver.php?id=<?php echo $pqrs['id']; ?>" class="pqrs-item estado-<?php echo $estado_clase; ?>">
                            <div class="pqrs-info">
                                <div class="pqrs-header">
                                    <span class="pqrs-codigo"><?php echo htmlspecialchars($pqrs['codigo']); ?></span>
                                    <span class="pqrs-asunto"><?php echo htmlspecialchars($pqrs['asunto']); ?></span>
                                </div>
                                <div class="pqrs-meta">
                                    <span class="cliente-info">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($pqrs['nombre_completo']); ?>
                                    </span>
                                    <?php if (!empty($pqrs['gestor_principal_nombre'])): ?>
                                        <span class="cierre-info">
                                            <i class="fas fa-user-tie"></i>
                                            <?php echo htmlspecialchars($pqrs['gestor_principal_nombre']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span>
                                        <i class="fas fa-tag"></i>
                                        <?php echo $tipos_solicitud[$pqrs['tipo_solicitud']] ?? $pqrs['tipo_solicitud']; ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($pqrs['marca_temporal'] ?? $pqrs['creado_el'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="pqrs-estado">
                                <span class="badge-estado <?php echo $estado_info['clase']; ?>">
                                    <i class="fas <?php echo $estado_info['icono']; ?>"></i>
                                    <?php echo $estado_info['texto']; ?>
                                </span>
                                
                                <?php if ($pqrs['total_mensajes'] > 0): ?>
                                    <span class="badge-total-mensajes">
                                        <i class="fas fa-comment"></i>
                                        <?php echo $pqrs['total_mensajes']; ?> mensaje<?php echo $pqrs['total_mensajes'] > 1 ? 's' : ''; ?>
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

            if (window.location.search.includes('pagina=')) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

        });
    </script>
</body>
</html>