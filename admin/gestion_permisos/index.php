<?php
// admin/gestion_permisos/index.php - Lista de solicitudes de permisos
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Jefes (es_jefe = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$usuario_actual = obtenerUsuario();
$usuario_id = $usuario_actual['id'];
$rol_usuario = $usuario_actual['rol'];
$es_jefe = $usuario_actual['es_jefe'] ?? 0;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar permisos de capacitaciones";
    header('Location: ../../index.php');
    exit();
}

$mensaje = '';
$error = '';

// Recuperar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ============================================
// FILTROS
// ============================================

$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_usuario = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;

$where = [];
$params = [];

// ============================================
// CONSTRUIR CONSULTA SEGÚN ROL
// ============================================

if ($rol_usuario == 1) {
    // ADMIN: Ve TODAS las solicitudes
    $titulo_pagina = "Todas las Solicitudes";
    $descripcion_pagina = "Lista completa de todas las solicitudes de permisos";
    
    if (!empty($filtro_estado)) {
        $where[] = "sp.estado = ?";
        $params[] = $filtro_estado;
    }
    if ($filtro_usuario > 0) {
        $where[] = "sp.usuario_id = ?";
        $params[] = $filtro_usuario;
    }
    
} else {
    // JEFE/LÍDER: Ve SOLO las solicitudes donde es jefe_inmediato
    $titulo_pagina = "Mis Solicitudes Asignadas";
    $descripcion_pagina = "Solicitudes de permisos donde eres el jefe inmediato";
    
    $where[] = "sp.jefe_inmediato = ?";
    $params[] = $usuario_id;
    
    if (!empty($filtro_estado)) {
        $where[] = "sp.estado = ?";
        $params[] = $filtro_estado;
    }
    if ($filtro_usuario > 0) {
        $where[] = "sp.usuario_id = ?";
        $params[] = $filtro_usuario;
    }
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ============================================
// ESTADÍSTICAS
// ============================================

$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobadas,
                SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazadas
               FROM solicitudes_permisos sp
               $where_clause";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute($params);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_solicitudes = $stats['total'] ?? 0;
$pendientes = $stats['pendientes'] ?? 0;
$aprobadas = $stats['aprobadas'] ?? 0;
$rechazadas = $stats['rechazadas'] ?? 0;

// ============================================
// OBTENER SOLICITUDES
// ============================================

$sql = "SELECT sp.*, 
        u.nombre_completo as solicitante_nombre,
        u.usuario as solicitante_usuario,
        j.nombre_completo as jefe_nombre
        FROM solicitudes_permisos sp
        LEFT JOIN usuarios u ON sp.usuario_id = u.id
        LEFT JOIN usuarios j ON sp.jefe_inmediato = j.id
        $where_clause
        ORDER BY sp.solicitado_el DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$no_solicitudes = empty($solicitudes);

// ============================================
// OBTENER USUARIOS PARA EL FILTRO
// ============================================

if ($rol_usuario == 1) {
    // Admin: todos los usuarios (excepto admin)
    $stmt_usuarios = $pdo->query("SELECT id, nombre_completo FROM usuarios WHERE rol_id != 1 ORDER BY nombre_completo ASC");
} else {
    // Jefe: solo usuarios que le han enviado solicitudes
    $sql_usuarios = "SELECT DISTINCT u.id, u.nombre_completo 
                      FROM solicitudes_permisos sp
                      JOIN usuarios u ON sp.usuario_id = u.id
                      WHERE sp.jefe_inmediato = ?
                      ORDER BY u.nombre_completo ASC";
    $stmt_usuarios = $pdo->prepare($sql_usuarios);
    $stmt_usuarios->execute([$usuario_id]);
}
$usuarios_filtro = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

// Tipos de permisos
$tipos_permisos = [
    'Cita Medica' => 'Cita Médica',
    'Cita Medica Urgencias' => 'Cita Médica Urgencias',
    'Cita con Especialista' => 'Cita con Especialista',
    'Acudiente de Obligaciones Escolares' => 'Acudiente de Obligaciones Escolares',
    'Permiso para Atender Situaciones Judiciales o Administrativas' => 'Permiso para Atender Situaciones Judiciales o Administrativas',
    'Cumpleaños' => 'Cumpleaños',
    'Permiso Remunerado' => 'Permiso Remunerado',
    'Permiso Menos a 4 Horas' => 'Permiso Menos a 4 Horas',
    'Jurado de Votacion' => 'Jurado de Votación',
    'Permiso por Votacion' => 'Permiso por Votación',
    'Jornada Flexible' => 'Jornada Flexible'
];

// Estados
$estados = [
    'pendiente' => '⏳ Pendiente',
    'aprobado' => '✅ Aprobado',
    'rechazado' => '❌ Rechazado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos | INTRANET</title>
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
        .header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { color: white; font-size: 24px; }
        .logo span { color: #445960; }
        .btn-volver {
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* ===== DASHBOARD CARDS ===== */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        @media (max-width: 992px) {
            .dashboard-cards { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .dashboard-cards { grid-template-columns: 1fr; }
        }

        .card-dashboard {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card-dashboard:hover { transform: translateY(-5px); }
        .card-dashboard i { font-size: 32px; margin-bottom: 8px; }
        .card-dashboard .numero { font-size: 28px; font-weight: 700; color: #12232b; }
        .card-dashboard .label { font-size: 13px; color: #7f8c8d; }

        .icon-total { color: #173742; }
        .icon-pendiente { color: #f39c12; }
        .icon-aprobado { color: #27ae60; }
        .icon-rechazado { color: #e74c3c; }

        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
        }
        .card .subtitle {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* ===== FILTROS ===== */
        .filtros-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }
        .filtros-container select {
            padding: 8px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            background: white;
            min-width: 150px;
        }
        .filtros-container select:focus {
            outline: none;
            border-color: #173742;
        }
        .btn-filtrar {
            background: #173742;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-filtrar:hover {
            background: #445960;
        }
        .btn-limpiar-filtros {
            background: #7f8c8d;
            color: white;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-limpiar-filtros:hover {
            background: #e74c3c;
        }

        /* ===== TABLA ===== */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #d3d3d3; }
        th { background: #12232b; color: white; font-weight: 600; white-space: nowrap; }
        tr:hover { background: #f8f9fa; }

        /* ===== BADGES ===== */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .badge-pendiente { background: #fef9e7; color: #7f6000; border: 1px solid #f39c12; }
        .badge-aprobado { background: #d5f5e3; color: #1a7a3a; border: 1px solid #27ae60; }
        .badge-rechazado { background: #fadbd8; color: #922b21; border: 1px solid #e74c3c; }

        .badge-tipo {
            padding: 7px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
            background: #e8f0fe;
            color: #173742;
        }

        /* ===== BOTONES ACCIÓN UNIFICADOS ===== */
        .btn-accion {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-accion i {
            font-size: 13px;
            line-height: 1;
        }

        .btn-accion:hover {
            transform: translateY(-2px);
        }

        .btn-ver { 
            background: #8e44ad; 
            color: white; 
        }
        .btn-ver:hover { 
            background: #732d91; 
        }

        .btn-aprobar { 
            background: #27ae60; 
            color: white; 
        }
        .btn-aprobar:hover { 
            background: #219a52; 
            box-shadow: 0 3px 10px rgba(39,174,96,0.3);
        }

        .btn-rechazar { 
            background: #e74c3c; 
            color: white; 
        }
        .btn-rechazar:hover { 
            background: #c0392b; 
            box-shadow: 0 3px 10px rgba(231,76,60,0.3);
        }

        .text-muted { color: #7f8c8d; font-size: 12px; }
        .td-min { white-space: nowrap; }
        .text-center { text-align: center; }

        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            transition: opacity 0.5s ease;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            transition: opacity 0.5s ease;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state i { font-size: 48px; color: #d3d3d3; margin-bottom: 15px; }
        .empty-state p { font-size: 14px; }

        /* ============================================ */
        /* ===== MODAL PERSONALIZADO ===== */
        /* ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            background: white;
            border-radius: 16px;
            max-width: 420px;
            width: 92%;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
            position: relative;
            text-align: center;
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            font-size: 22px;
            color: #bdc3c7;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .modal-close:hover {
            color: #e74c3c;
            transform: rotate(90deg);
        }

        .modal-icon {
            margin-bottom: 12px;
        }
        .modal-icon i {
            font-size: 48px;
            padding: 16px;
            border-radius: 50%;
        }
        .modal-icon .icon-aprobar {
            color: #27ae60;
            background: #d5f5e3;
        }
        .modal-icon .icon-rechazar {
            color: #e74c3c;
            background: #fadbd8;
        }

        .modal-title {
            font-size: 19px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .modal-message {
            color: #7f8c8d;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .modal-message strong {
            color: #2c3e50;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .modal-actions .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            min-width: 110px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .modal-actions .btn:hover {
            transform: translateY(-2px);
        }
        .modal-actions .btn-cancelar-modal {
            background: #ecf0f1;
            color: #2c3e50;
        }
        .modal-actions .btn-cancelar-modal:hover {
            background: #d5dbdb;
        }
        .modal-actions .btn-confirmar-aprobar {
            background: #27ae60;
            color: white;
        }
        .modal-actions .btn-confirmar-aprobar:hover {
            background: #219a52;
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        .modal-actions .btn-confirmar-rechazar {
            background: #e74c3c;
            color: white;
        }
        .modal-actions .btn-confirmar-rechazar:hover {
            background: #c0392b;
            box-shadow: 0 4px 15px rgba(231,76,60,0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header-actions { flex-direction: column; align-items: stretch; }
            table { font-size: 12px; }
            th, td { padding: 8px 10px; }
            .dashboard-cards { grid-template-columns: repeat(2, 1fr); }
            .filtros-container { flex-direction: column; align-items: stretch; }
            .filtros-container select { width: 100%; }
            .modal-container { padding: 25px 20px; }
            .modal-actions { flex-direction: column; }
            .modal-actions .btn { width: 100%; }
        }
        @media (max-width: 480px) {
            .dashboard-cards { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Gestionar Permisos</small>
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito" id="mensajeExito"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error" id="mensajeError"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ===== DASHBOARD CARDS ===== -->
        <div class="dashboard-cards">
            <div class="card-dashboard">
                <i class="fas fa-file-alt icon-total"></i>
                <div class="numero"><?php echo $total_solicitudes; ?></div>
                <div class="label">Total Solicitudes</div>
            </div>
            <div class="card-dashboard">
                <i class="fas fa-clock icon-pendiente"></i>
                <div class="numero"><?php echo $pendientes; ?></div>
                <div class="label">Pendientes</div>
            </div>
            <div class="card-dashboard">
                <i class="fas fa-check-circle icon-aprobado"></i>
                <div class="numero"><?php echo $aprobadas; ?></div>
                <div class="label">Aprobadas</div>
            </div>
            <div class="card-dashboard">
                <i class="fas fa-times-circle icon-rechazado"></i>
                <div class="numero"><?php echo $rechazadas; ?></div>
                <div class="label">Rechazadas</div>
            </div>
        </div>

        <!-- ===== LISTA DE SOLICITUDES ===== -->
        <div class="card">
            <div class="header-actions">
                <div>
                    <h2><i class="fas fa-list"></i> <?php echo $titulo_pagina; ?></h2>
                    <p class="subtitle"><?php echo $descripcion_pagina; ?></p>
                </div>
            </div>

            <!-- ===== FILTROS ===== -->
            <div class="filtros-container">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; flex: 1;">
                    <select name="estado">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estados as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filtro_estado == $key ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (!empty($usuarios_filtro) && count($usuarios_filtro) > 1): ?>
                        <select name="usuario_id">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios_filtro as $usu): ?>
                                <option value="<?php echo $usu['id']; ?>" <?php echo $filtro_usuario == $usu['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usu['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <button type="submit" class="btn-filtrar">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    
                    <?php if (!empty($filtro_estado) || $filtro_usuario > 0): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['estado' => '', 'usuario_id' => ''])); ?>" class="btn-limpiar-filtros">
                            <i class="fas fa-times"></i> Limpiar filtros
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($no_solicitudes): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No hay solicitudes de permisos.</p>
                    <?php if ($rol_usuario != 1): ?>
                        <p style="font-size: 13px; margin-top: 5px;">Aún no tienes solicitudes asignadas como jefe.</p>
                    <?php else: ?>
                        <p style="font-size: 13px; margin-top: 5px;">No hay solicitudes registradas en el sistema.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Solicitante</th>
                                <th>Tipo</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; foreach ($solicitudes as $solicitud): ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge-tipo">
                                            <?php echo $tipos_permisos[$solicitud['tipo']] ?? $solicitud['tipo']; ?>
                                        </span>
                                    </td>
                                    <td class="td-min"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_inicio'])); ?></td>
                                    <td class="td-min"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_fin'])); ?></td>
                                    <td class="td-min">
                                        <?php
                                        $estado_class = '';
                                        $estado_text = '';
                                        switch ($solicitud['estado']) {
                                            case 'pendiente':
                                                $estado_class = 'badge-pendiente';
                                                $estado_text = '⏳ Pendiente';
                                                break;
                                            case 'aprobado':
                                                $estado_class = 'badge-aprobado';
                                                $estado_text = '✅ Aprobado';
                                                break;
                                            case 'rechazado':
                                                $estado_class = 'badge-rechazado';
                                                $estado_text = '❌ Rechazado';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $estado_class; ?>">
                                            <?php echo $estado_text; ?>
                                        </span>
                                    </td>
                                    <td class="text-center td-min">
                                        <a href="ver_detalle.php?id=<?php echo $solicitud['id']; ?>" class="btn-accion btn-ver" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if ($solicitud['estado'] == 'pendiente'): ?>
                                            <!-- Botón Aprobar con Modal -->
                                            <button type="button" class="btn-accion btn-aprobar" onclick="abrirModalAprobar(<?php echo $solicitud['id']; ?>, '<?php echo addslashes($solicitud['solicitante_nombre']); ?>')" title="Aprobar">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <!-- Botón Rechazar con Modal -->
                                            <button type="button" class="btn-accion btn-rechazar" onclick="abrirModalRechazar(<?php echo $solicitud['id']; ?>, '<?php echo addslashes($solicitud['solicitante_nombre']); ?>')" title="Rechazar">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 15px; color: #7f8c8d; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> 
                    Total: <strong><?php echo count($solicitudes); ?></strong> solicitudes
                    <?php if ($rol_usuario != 1): ?>
                        | Eres jefe de <strong><?php echo count($solicitudes); ?></strong> solicitudes
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MODAL APROBAR -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="modalAprobar">
        <div class="modal-container">
            <button class="modal-close" onclick="cerrarModal('modalAprobar')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-check-circle icon-aprobar"></i>
            </div>
            <h3 class="modal-title">¿Aprobar solicitud?</h3>
            <p class="modal-message">
                ¿Estás seguro de que deseas <strong>APROBAR</strong> la solicitud de <strong id="aprobarSolicitante"></strong>?
            </p>
            <div class="modal-actions">
                <button class="btn btn-cancelar-modal" onclick="cerrarModal('modalAprobar')">
                    Cancelar
                </button>
                <form method="POST" action="aprobar.php" style="display: inline;">
                    <input type="hidden" name="id" id="aprobarId" value="">
                    <input type="hidden" name="action" value="aprobar">
                    <button type="submit" class="btn btn-confirmar-aprobar">
                        <i class="fas fa-check"></i> Aprobar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MODAL RECHAZAR -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="modalRechazar">
        <div class="modal-container">
            <button class="modal-close" onclick="cerrarModal('modalRechazar')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-times-circle icon-rechazar"></i>
            </div>
            <h3 class="modal-title">¿Rechazar solicitud?</h3>
            <p class="modal-message">
                ¿Estás seguro de que deseas <strong>RECHAZAR</strong> la solicitud de <strong id="rechazarSolicitante"></strong>?
            </p>
            <div class="modal-actions">
                <button class="btn btn-cancelar-modal" onclick="cerrarModal('modalRechazar')">
                    Cancelar
                </button>
                <form method="POST" action="aprobar.php" style="display: inline;">
                    <input type="hidden" name="id" id="rechazarId" value="">
                    <input type="hidden" name="action" value="rechazar">
                    <button type="submit" class="btn btn-confirmar-rechazar">
                        <i class="fas fa-times"></i> Rechazar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // MODALES PARA APROBAR/RECHAZAR
        // ============================================

        function abrirModalAprobar(id, solicitante) {
            document.getElementById('aprobarId').value = id;
            document.getElementById('aprobarSolicitante').textContent = solicitante;
            document.getElementById('modalAprobar').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function abrirModalRechazar(id, solicitante) {
            document.getElementById('rechazarId').value = id;
            document.getElementById('rechazarSolicitante').textContent = solicitante;
            document.getElementById('modalRechazar').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(function(modal) {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = 'auto';
            }
        });

        // ============================================
        // OCULTAR MENSAJE DE ÉXITO/ERROR A LOS 2 SEGUNDOS
        // ============================================
        
        document.addEventListener('DOMContentLoaded', function() {
            // Mensaje de éxito
            const mensajeExito = document.getElementById('mensajeExito');
            if (mensajeExito) {
                setTimeout(function() {
                    mensajeExito.style.transition = 'opacity 0.5s ease';
                    mensajeExito.style.opacity = '0';
                    setTimeout(function() {
                        mensajeExito.style.display = 'none';
                    }, 500);
                }, 2000);
            }

            // Mensaje de error
            const mensajeError = document.getElementById('mensajeError');
            if (mensajeError) {
                setTimeout(function() {
                    mensajeError.style.transition = 'opacity 0.5s ease';
                    mensajeError.style.opacity = '0';
                    setTimeout(function() {
                        mensajeError.style.display = 'none';
                    }, 500);
                }, 2000);
            }
        });
    </script>
</body>
</html>