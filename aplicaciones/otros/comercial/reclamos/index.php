<?php
// aplicaciones/otros/comercial/reclamos/index.php - Lista de reclamos del asesor
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
// ESTADOS (solo 3 ahora)
// ============================================
$estados = [
    'pendiente'   => ['texto' => 'Pendiente',   'clase' => 'estado-pendiente', 'icono' => 'fa-clock'],
    'en_revision' => ['texto' => 'En Revisión', 'clase' => 'estado-revision',  'icono' => 'fa-search'],
    'resuelto'    => ['texto' => 'Resuelto',    'clase' => 'estado-resuelto',  'icono' => 'fa-check-circle']
];

// ============================================
// OBTENER MIS RECLAMOS
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        (SELECT COUNT(*) FROM reclamos_mensajes WHERE reclamo_id = r.id) as total_mensajes,
        (SELECT COUNT(*) FROM reclamos_mensajes WHERE reclamo_id = r.id AND leido = 0 AND usuario_id != ?) as mensajes_nuevos,
        (SELECT mensaje FROM reclamos_mensajes WHERE reclamo_id = r.id ORDER BY creado_el DESC LIMIT 1) as ultimo_mensaje,
        (SELECT creado_el FROM reclamos_mensajes WHERE reclamo_id = r.id ORDER BY creado_el DESC LIMIT 1) as ultimo_mensaje_fecha,
        (SELECT u.nombre_completo FROM reclamos_mensajes rm 
         LEFT JOIN usuarios u ON rm.usuario_id = u.id 
         WHERE rm.reclamo_id = r.id ORDER BY rm.creado_el DESC LIMIT 1) as ultimo_mensaje_autor
    FROM reclamos r
    WHERE r.asesor_id = ?
    ORDER BY 
        CASE r.estado 
            WHEN 'pendiente'   THEN 1 
            WHEN 'en_revision' THEN 2 
            WHEN 'resuelto'    THEN 3 
        END,
        r.actualizado_el DESC, 
        r.creado_el DESC
");
$stmt->execute([$usuario_id, $usuario_id]);
$reclamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ESTADÍSTICAS
// ============================================
$total_reclamos = count($reclamos);
$reclamos_pendientes = 0;
$reclamos_revision = 0;
$reclamos_resueltos = 0;

foreach ($reclamos as $r) {
    if ($r['estado'] == 'pendiente')        $reclamos_pendientes++;
    elseif ($r['estado'] == 'en_revision')  $reclamos_revision++;
    elseif ($r['estado'] == 'resuelto')     $reclamos_resueltos++;
}

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
    <title>Mis Reclamos | INTRANET</title>
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

        /* ===== HEADER ACTIONS ===== */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .btn-crear {
            background: linear-gradient(135deg, #e67e22, #f39c12);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(230,126,34,0.3);
        }
        .btn-crear:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(230,126,34,0.4);
        }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .stat-card.total { border-color: #173742; }
        .stat-card.pendiente { border-color: #f39c12; }
        .stat-card.revision { border-color: #3498db; }
        .stat-card.resuelto { border-color: #27ae60; }

        .stat-card .numero {
            font-size: 28px;
            font-weight: 700;
            color: #12232b;
            margin-bottom: 4px;
        }
        .stat-card .label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
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
        }
        .reclamo-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .reclamo-item.estado-pendiente { border-left-color: #f39c12; }
        .reclamo-item.estado-revision  { border-left-color: #3498db; }
        .reclamo-item.estado-resuelto  { border-left-color: #27ae60; }

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
        }
        .reclamo-meta i {
            margin-right: 4px;
            color: #173742;
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
            margin-bottom: 20px;
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .reclamo-item { flex-direction: column; align-items: flex-start; }
            .reclamo-estado { align-items: flex-start; width: 100%; flex-direction: row; justify-content: space-between; }
            .header-actions { flex-direction: column; align-items: stretch; }
            .btn-crear { justify-content: center; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Mis Reclamos</small>
            </div>
            <div class="user-info">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver
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

        <!-- ===== HEADER ACTIONS ===== -->
        <div class="header-actions">
            <div>
                <h2 style="border-left: 4px solid #173742; padding-left: 15px; font-size: 22px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-comments" style="color: #e67e22;"></i>
                    Mis Reclamos
                </h2>
                <p style="color: #7f8c8d; font-size: 13px; margin-top: 5px; padding-left: 19px;">
                    Gestiona tus inconformidades sobre comisiones
                </p>
            </div>
            <a href="crear.php" class="btn-crear">
                <i class="fas fa-plus"></i> Nuevo Reclamo
            </a>
        </div>

        <!-- ===== ESTADÍSTICAS ===== -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="numero"><?php echo $total_reclamos; ?></div>
                <div class="label">Total Reclamos</div>
            </div>
            <div class="stat-card pendiente">
                <div class="numero"><?php echo $reclamos_pendientes; ?></div>
                <div class="label">Pendientes</div>
            </div>
            <div class="stat-card revision">
                <div class="numero"><?php echo $reclamos_revision; ?></div>
                <div class="label">En Revisión</div>
            </div>
            <div class="stat-card resuelto">
                <div class="numero"><?php echo $reclamos_resueltos; ?></div>
                <div class="label">Resueltos</div>
            </div>
        </div>

        <!-- ===== LISTA DE RECLAMOS ===== -->
        <?php if (empty($reclamos)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>No tienes reclamos registrados</h3>
                    <p>Si tienes alguna inconformidad con tus comisiones, puedes crear un reclamo.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="reclamos-lista">
                <?php foreach ($reclamos as $reclamo): 
                    $estado_info = $estados[$reclamo['estado']] ?? $estados['pendiente'];
                ?>
                    <a href="ver.php?id=<?php echo $reclamo['id']; ?>" class="reclamo-item estado-<?php echo str_replace('_', '-', $reclamo['estado']); ?>">
                        <div class="reclamo-info">
                            <div class="reclamo-header">
                                <span class="reclamo-id">#<?php echo str_pad($reclamo['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                <span class="reclamo-asunto"><?php echo htmlspecialchars($reclamo['asunto']); ?></span>
                            </div>
                            <div class="reclamo-meta">
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