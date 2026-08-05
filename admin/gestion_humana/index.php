<?php
// admin/gestion_humana/index.php - Dashboard de Gestión Humana
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

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
// DASHBOARD - ESTADÍSTICAS
// ============================================

// Total de áreas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM categorias_gestion_humana WHERE tipo = 'area'");
$total_areas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de carpetas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM categorias_gestion_humana WHERE tipo = 'carpeta'");
$total_carpetas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de documentos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM documentos_gestion_humana");
$total_documentos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de usuarios que han visto documentos
$stmt = $pdo->query("SELECT COUNT(DISTINCT usuario_id) as total FROM progreso_gestion_humana");
$total_usuarios_progreso = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Últimos documentos agregados
$stmt = $pdo->query("
    SELECT d.*, c.nombre as carpeta 
    FROM documentos_gestion_humana d
    JOIN categorias_gestion_humana c ON d.categoria_id = c.id
    ORDER BY d.fecha_creacion DESC 
    LIMIT 5
");
$ultimos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Gestión Humana | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== ESTILOS GENERALES ===== */
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

        .logo h1 { color: white; font-size: 24px; font-weight: 700; margin: 0; }
        .logo small { color: #8aa8b8; font-size: 14px; font-weight: 300; }

        .btn-volver {
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* ===== MENSAJES ===== */
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        /* ===== DASHBOARD CARDS ===== */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card-dashboard {
            background: white;
            border-radius: 12px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card-dashboard:hover { transform: translateY(-5px); }

        .card-dashboard .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }
        .card-dashboard .icon.areas { background: #d5f5e3; color: #27ae60; }
        .card-dashboard .icon.carpetas { background: #e8d5f5; color: #8e44ad; }
        .card-dashboard .icon.documentos { background: #d6eaf8; color: #2980b9; }
        .card-dashboard .icon.usuarios { background: #fdebd0; color: #e67e22; }
        .card-dashboard .icon i { font-size: 24px; }

        .card-dashboard .numero { font-size: 32px; font-weight: 700; color: #12232b; }
        .card-dashboard .label { font-size: 14px; color: #7f8c8d; margin-top: 5px; }

        /* ===== MÓDULOS ===== */
        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .modulo-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            text-decoration: none;
            color: #2c3e50;
            display: block;
            border-left: 5px solid #173742;
        }
        .modulo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .modulo-card .modulo-icon {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .modulo-card .modulo-icon i {
            font-size: 32px;
            color: #173742;
            width: 55px;
            height: 55px;
            background: #e8f0fe;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modulo-card .modulo-icon h3 { font-size: 18px; color: #12232b; margin: 0; }
        .modulo-card p { font-size: 14px; color: #7f8c8d; margin-bottom: 15px; }

        .modulo-card .btn-acceder {
            display: inline-block;
            background: #173742;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .modulo-card .btn-acceder:hover { background: #445960; }

        .modulo-card.induccion { border-left-color: #f39c12; }
        .modulo-card.induccion .modulo-icon i { background: #fff3cd; color: #f39c12; }

        .modulo-card.usuarios { border-left-color: #3498db; }
        .modulo-card.usuarios .modulo-icon i { background: #d6eaf8; color: #3498db; }

        .modulo-card.categorias { border-left-color: #8e44ad; }
        .modulo-card.categorias .modulo-icon i { background: #e8d5f5; color: #8e44ad; }

        .modulo-card.administracion { border-left-color: #3498db; }
        .modulo-card.administracion .modulo-icon i { background: #d6eaf8; color: #3498db; }

        /* ===== CARD DE TABLA ===== */
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
            font-size: 18px;
        }
        .card h2 i { color: #173742; margin-right: 10px; }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #12232b; color: white; font-weight: 600; font-size: 13px; }
        tr:hover { background: #f8f9fa; }

        /* ===== BADGES ===== */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-pdf { background: #fadbd8; color: #922b21; }
        .badge-video { background: #d6eaf8; color: #1a5276; }
        .badge-word { background: #d4e6f1; color: #1a4a7a; }
        .badge-excel { background: #d5f5e3; color: #1a7a3a; }
        .badge-link { background: #e8d5f5; color: #6c3483; }
        .badge-imagen { background: #fdebd0; color: #a04000; }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align: center; padding: 30px; color: #7f8c8d; }
        .empty-state i { font-size: 40px; color: #d3d3d3; margin-bottom: 10px; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .dashboard-cards { grid-template-columns: repeat(2, 1fr); }
            .modulos-grid { grid-template-columns: 1fr; }
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
                <small>| Gestión Humana</small>
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito">
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
            <div class="card-dashboard">
                <div class="icon areas"><i class="fas fa-building"></i></div>
                <div class="numero"><?php echo $total_areas; ?></div>
                <div class="label">Áreas</div>
            </div>
            <div class="card-dashboard">
                <div class="icon carpetas"><i class="fas fa-folder"></i></div>
                <div class="numero"><?php echo $total_carpetas; ?></div>
                <div class="label">Carpetas</div>
            </div>
            <div class="card-dashboard">
                <div class="icon documentos"><i class="fas fa-file-alt"></i></div>
                <div class="numero"><?php echo $total_documentos; ?></div>
                <div class="label">Documentos</div>
            </div>
            <div class="card-dashboard">
                <div class="icon usuarios"><i class="fas fa-users"></i></div>
                <div class="numero"><?php echo $total_usuarios_progreso; ?></div>
                <div class="label">Usuarios con Progreso</div>
            </div>
        </div>

        <!-- ===== MÓDULOS ===== -->
        <div class="modulos-grid">
            <a href="induccion/index.php" class="modulo-card induccion">
                <div class="modulo-icon">
                    <i class="fas fa-user-graduate"></i>
                    <div>
                        <h3>Inducción</h3>
                    </div>
                </div>
                <p>Proceso de inducción para nuevos empleados. Manuales, políticas y guías.</p>
                <span class="btn-acceder">
                    <i class="fas fa-arrow-right"></i> Acceder
                </span>
            </a>

            <a href="regis_usuarios/index.php" class="modulo-card administracion">
                <div class="modulo-icon">
                    <i class="fas fa-cogs"></i>
                    <div>
                        <h3>Registro de Usuarios</h3>
                    </div>
                </div>
                <p>Validar y verificar el historial de los usuarios.</p>
                <span class="btn-acceder">
                    <i class="fas fa-arrow-right"></i> Acceder
                </span>
            </a>
        </div>

        <!-- ===== ÚLTIMOS DOCUMENTOS ===== -->
        <div class="card">
            <h2><i class="fas fa-clock"></i> Últimos Documentos Agregados</h2>
            
            <?php if (empty($ultimos_documentos)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No hay documentos registrados aún.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Carpeta</th>
                                <th>Tipo</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_documentos as $doc): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($doc['titulo']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($doc['carpeta']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $doc['tipo_archivo']; ?>">
                                            <?php echo strtoupper($doc['tipo_archivo']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($doc['fecha_creacion'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>