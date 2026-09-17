<?php
// admin/comercial/reclamos/index.php - Lista de TODOS los reclamos
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

// Admin y Supervisor: acceso total
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para ver reclamos";
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
// TIPOS DE COMISIONES
// ============================================
$tipos_comisiones = [
    'matriculas' => 'Matrículas',
    'usados' => 'Usados',
    'accesorios' => 'Accesorios',
    'incentivos' => 'Incentivos',
    'financieras' => 'Financieras'
];

// ============================================
// ESTADOS (solo 3)
// ============================================
$estados = [
    'pendiente'   => ['texto' => 'Pendiente',   'clase' => 'estado-pendiente', 'icono' => 'fa-clock'],
    'en_revision' => ['texto' => 'En Revisión', 'clase' => 'estado-revision',  'icono' => 'fa-search'],
    'resuelto'    => ['texto' => 'Resuelto',    'clase' => 'estado-resuelto',  'icono' => 'fa-check-circle']
];

// ============================================
// FILTROS
// ============================================
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_asesor = isset($_GET['asesor_id']) ? intval($_GET['asesor_id']) : 0;

$where = [];
$params = [];

if (!empty($filtro_estado) && array_key_exists($filtro_estado, $estados)) {
    $where[] = "r.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_tipo) && array_key_exists($filtro_tipo, $tipos_comisiones)) {
    $where[] = "r.tipo_comision = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_asesor > 0) {
    $where[] = "r.asesor_id = ?";
    $params[] = $filtro_asesor;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ============================================
// ESTADÍSTICAS
// ============================================
$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END) as en_revision,
                SUM(CASE WHEN estado = 'resuelto' THEN 1 ELSE 0 END) as resueltos
               FROM reclamos r
               $where_clause";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute($params);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_reclamos = $stats['total'] ?? 0;
$pendientes = $stats['pendientes'] ?? 0;
$en_revision = $stats['en_revision'] ?? 0;
$resueltos = $stats['resueltos'] ?? 0;

// ============================================
// OBTENER RECLAMOS
// ============================================
$sql = "SELECT 
            r.*,
            u.nombre_completo as asesor_nombre,
            u.usuario as asesor_usuario,
            u.cod_asesor as asesor_codigo,
            (SELECT COUNT(*) FROM reclamos_mensajes WHERE reclamo_id = r.id) as total_mensajes,
            (SELECT COUNT(*) FROM reclamos_mensajes WHERE reclamo_id = r.id AND leido = 0) as mensajes_nuevos,
            (SELECT mensaje FROM reclamos_mensajes WHERE reclamo_id = r.id ORDER BY creado_el DESC LIMIT 1) as ultimo_mensaje,
            (SELECT u2.nombre_completo FROM reclamos_mensajes rm 
             LEFT JOIN usuarios u2 ON rm.usuario_id = u2.id 
             WHERE rm.reclamo_id = r.id ORDER BY rm.creado_el DESC LIMIT 1) as ultimo_mensaje_autor
        FROM reclamos r
        LEFT JOIN usuarios u ON r.asesor_id = u.id
        $where_clause
        ORDER BY 
            CASE r.estado 
                WHEN 'pendiente'   THEN 1 
                WHEN 'en_revision' THEN 2 
                WHEN 'resuelto'    THEN 3 
            END,
            r.actualizado_el DESC, 
            r.creado_el DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reclamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// OBTENER ASESORES PARA EL FILTRO
// ============================================
$stmt_asesores = $pdo->query("
    SELECT DISTINCT u.id, u.nombre_completo, u.cod_asesor
    FROM reclamos r
    JOIN usuarios u ON r.asesor_id = u.id
    ORDER BY u.nombre_completo ASC
");
$asesores_filtro = $stmt_asesores->fetchAll(PDO::FETCH_ASSOC);

// Recuperar mensajes de sesión
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamos | INTRANET</title>
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

        /* ===== DASHBOARD CARDS ===== */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        @media (max-width: 992px) {
            .dashboard-cards { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .dashboard-cards { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .dashboard-cards { grid-template-columns: 1fr; }
        }

        .card-dashboard {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border-top: 4px solid;
        }
        .card-dashboard:hover { transform: translateY(-5px); }
        .card-dashboard i { font-size: 32px; margin-bottom: 8px; }
        .card-dashboard .numero { font-size: 28px; font-weight: 700; color: #12232b; }
        .card-dashboard .label { font-size: 12px; color: #7f8c8d; font-weight: 500; }

        .card-dashboard.total { border-color: #173742; }
        .card-dashboard.total i { color: #173742; }
        .card-dashboard.pendiente { border-color: #f39c12; }
        .card-dashboard.pendiente i { color: #f39c12; }
        .card-dashboard.revision { border-color: #3498db; }
        .card-dashboard.revision i { color: #3498db; }
        .card-dashboard.resuelto { border-color: #27ae60; }
        .card-dashboard.resuelto i { color: #27ae60; }

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
            align-items: center;
            margin-bottom: 20px;
        }
        .filtros-container select {
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            background: white;
            min-width: 180px;
            transition: all 0.3s ease;
        }
        .filtros-container select:focus {
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
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-limpiar:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }

        /* ===== LISTA DE RECLAMOS ===== */
        .reclamos-lista {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .reclamo-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border-left: 5px solid #f39c12;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }
        .reclamo-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: #f8f9fa;
        }
        .reclamo-item.estado-pendiente { border-left-color: #f39c12; }
        .reclamo-item.estado-en-revision { border-left-color: #3498db; }
        .reclamo-item.estado-resuelto { border-left-color: #27ae60; }

        .reclamo-info {
            flex: 1;
            min-width: 250px;
        }
        .reclamo-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .reclamo-id {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
        }
        .reclamo-asunto {
            font-size: 15px;
            font-weight: 600;
            color: #12232b;
        }
        .reclamo-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #7f8c8d;
            flex-wrap: wrap;
            align-items: center;
        }
        .reclamo-meta i {
            margin-right: 4px;
            color: #173742;
        }
        .asesor-info {
            display: flex;
            align-items: center;
            gap: 5px;
            background: #e8f0fe;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            color: #173742;
            font-weight: 600;
        }
        .reclamo-ultimo-mensaje {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 8px;
            font-style: italic;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
        }

        .reclamo-estado {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .badge-estado {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .estado-pendiente {
            background: #fef9e7;
            color: #7f6000;
            border: 1px solid #f39c12;
        }
        .estado-revision {
            background: #d6eaf8;
            color: #1a5276;
            border: 1px solid #3498db;
        }
        .estado-resuelto {
            background: #d5f5e3;
            color: #1a7a3a;
            border: 1px solid #27ae60;
        }

        .badge-mensajes {
            background: #e74c3c;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .badge-total-mensajes {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* ===== MENSAJES ===== */
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            display: flex;
            align-items: center;
            gap: 10px;
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
            .reclamo-item { flex-direction: column; align-items: flex-start; }
            .reclamo-estado { align-items: flex-start; width: 100%; flex-direction: row; justify-content: space-between; }
            .filtros-container { flex-direction: column; align-items: stretch; }
            .filtros-container select { width: 100%; }
            .btn-filtrar, .btn-limpiar { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Reclamos</small>
            </div>
            <div class="user-info">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- ===== MENSAJES ===== -->
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

        <!-- ===== DASHBOARD CARDS ===== -->
        <div class="dashboard-cards">
            <div class="card-dashboard total">
                <i class="fas fa-comments"></i>
                <div class="numero"><?php echo $total_reclamos; ?></div>
                <div class="label">Total Reclamos</div>
            </div>
            <div class="card-dashboard pendiente">
                <i class="fas fa-clock"></i>
                <div class="numero"><?php echo $pendientes; ?></div>
                <div class="label">Pendientes</div>
            </div>
            <div class="card-dashboard revision">
                <i class="fas fa-search"></i>
                <div class="numero"><?php echo $en_revision; ?></div>
                <div class="label">En Revisión</div>
            </div>
            <div class="card-dashboard resuelto">
                <i class="fas fa-check-circle"></i>
                <div class="numero"><?php echo $resueltos; ?></div>
                <div class="label">Resueltos</div>
            </div>
        </div>

        <!-- ===== LISTA DE RECLAMOS ===== -->
        <div class="card">
            <h2>
                <i class="fas fa-list"></i>
                Todos los Reclamos
                <span style="font-size: 13px; font-weight: 400; color: #7f8c8d;">
                    (<?php echo count($reclamos); ?>)
                </span>
            </h2>

            <!-- ===== FILTROS ===== -->
            <div class="filtros-container">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; flex: 1;">
                    <select name="estado">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estados as $key => $info): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filtro_estado == $key ? 'selected' : ''; ?>>
                                <?php echo $info['texto']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="tipo">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($tipos_comisiones as $key => $nombre): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filtro_tipo == $key ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (!empty($asesores_filtro)): ?>
                        <select name="asesor_id">
                            <option value="">Todos los asesores</option>
                            <?php foreach ($asesores_filtro as $asesor): ?>
                                <option value="<?php echo $asesor['id']; ?>" <?php echo $filtro_asesor == $asesor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($asesor['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <button type="submit" class="btn-filtrar">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    
                    <?php if (!empty($filtro_estado) || !empty($filtro_tipo) || $filtro_asesor > 0): ?>
                        <a href="index.php" class="btn-limpiar">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- ===== LISTA ===== -->
            <?php if (empty($reclamos)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay reclamos registrados</h3>
                    <p>No se encontraron reclamos con los filtros aplicados.</p>
                </div>
            <?php else: ?>
                <div class="reclamos-lista">
                    <?php foreach ($reclamos as $reclamo): 
                        $estado_info = $estados[$reclamo['estado']] ?? $estados['pendiente'];
                        $estado_clase = str_replace('_', '-', $reclamo['estado']);
                    ?>
                        <a href="ver.php?id=<?php echo $reclamo['id']; ?>" class="reclamo-item estado-<?php echo $estado_clase; ?>">
                            <div class="reclamo-info">
                                <div class="reclamo-header">
                                    <span class="reclamo-id">#<?php echo str_pad($reclamo['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    <span class="reclamo-asunto"><?php echo htmlspecialchars($reclamo['asunto']); ?></span>
                                </div>
                                <div class="reclamo-meta">
                                    <span class="asesor-info">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($reclamo['asesor_nombre']); ?>
                                        <?php if (!empty($reclamo['asesor_codigo'])): ?>
                                            (<?php echo htmlspecialchars($reclamo['asesor_codigo']); ?>)
                                        <?php endif; ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-tag"></i>
                                        <?php echo $tipos_comisiones[$reclamo['tipo_comision']] ?? $reclamo['tipo_comision']; ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?php echo $meses_espanol[$reclamo['mes']] . ' ' . $reclamo['anio']; ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($reclamo['creado_el'])); ?>
                                    </span>
                                </div>
                                <?php if (!empty($reclamo['ultimo_mensaje'])): ?>
                                    <div class="reclamo-ultimo-mensaje">
                                        <i class="fas fa-comment-dots" style="color: #173742;"></i>
                                        <strong><?php echo htmlspecialchars($reclamo['ultimo_mensaje_autor'] ?? 'Sistema'); ?>:</strong>
                                        <?php echo htmlspecialchars(mb_strimwidth($reclamo['ultimo_mensaje'], 0, 80, '...')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="reclamo-estado">
                                <span class="badge-estado <?php echo $estado_info['clase']; ?>">
                                    <i class="fas <?php echo $estado_info['icono']; ?>"></i>
                                    <?php echo $estado_info['texto']; ?>
                                </span>
                                
                                <?php if ($reclamo['mensajes_nuevos'] > 0): ?>
                                    <span class="badge-mensajes">
                                        <i class="fas fa-bell"></i>
                                        <?php echo $reclamo['mensajes_nuevos']; ?> nuevo<?php echo $reclamo['mensajes_nuevos'] > 1 ? 's' : ''; ?>
                                    </span>
                                <?php elseif ($reclamo['total_mensajes'] > 0): ?>
                                    <span class="badge-total-mensajes">
                                        <i class="fas fa-comment"></i>
                                        <?php echo $reclamo['total_mensajes']; ?> mensaje<?php echo $reclamo['total_mensajes'] > 1 ? 's' : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Ocultar mensaje de éxito después de 3 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const mensajeExito = document.getElementById('mensajeExito');
            if (mensajeExito) {
                setTimeout(function() {
                    mensajeExito.style.transition = 'opacity 0.5s ease';
                    mensajeExito.style.opacity = '0';
                    setTimeout(function() {
                        mensajeExito.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>