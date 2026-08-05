<?php
// admin/index.php - Panel de administración
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Verificar que el usuario tiene acceso al panel admin (rol 1 o 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

// Si es usuario normal (rol 3), no puede entrar
if ($rol_usuario == 3) {
    header('Location: ../index.php?error=sin_permiso');
    exit();
}

// ✅ NUEVO SISTEMA DE PERMISOS
$permisos_usuario = [];
if ($rol_usuario == 2) {
    // Supervisor: obtener módulos asignados directamente
    try {
        $stmt = $pdo->prepare("
            SELECT m.nombre 
            FROM permisos_usuarios pu
            JOIN modulos m ON pu.modulo_id = m.id
            WHERE pu.usuario_id = ? AND m.activo = 1
        ");
        $stmt->execute([$_SESSION['usuario_id']]);
        $permisos_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        // Si hay error, mostrar mensaje
        $permisos_usuario = [];
        error_log("Error al obtener permisos: " . $e->getMessage());
    }
}

// Definir los módulos del panel admin
$modulos_admin = [
    ['url' => 'gestionar_pagina/index.php', 'icono' => 'fa-images', 'nombre' => 'Gestionar Pagina', 'modulo' => 'gestionar_pagina'],
    ['url' => 'usuarios/index.php', 'icono' => 'fa-users', 'nombre' => 'Gestionar Usuarios', 'modulo' => 'usuarios'],
    ['url' => 'permisos/index.php', 'icono' => 'fa-lock', 'nombre' => 'Permisos', 'modulo' => 'permisos'],
    ['url' => 'capacitaciones/index.php', 'icono' => 'fa-folder-open', 'nombre' => 'Capacitaciones', 'modulo' => 'capacitaciones'],
    ['url' => 'politicas/index.php', 'icono' => 'fa-gavel', 'nombre' => 'Políticas', 'modulo' => 'politicas'],
    ['url' => 'documentos_sig/index.php', 'icono' => 'fa-file-alt', 'nombre' => 'Documentos SIG', 'modulo' => 'documentos_sig'],
    ['url' => 'logistica/index.php', 'icono' => 'fa-map-marked-alt', 'nombre' => 'Logística', 'modulo' => 'logistica'],
    ['url' => 'gestion_humana/index.php', 'icono' => 'fa-users-cog', 'nombre' => 'Gestión Humana', 'modulo' => 'gestion_humana'],
];

// Obtener estadísticas
$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$totalSlides = $pdo->query("SELECT COUNT(*) FROM slider")->fetchColumn();
$slidesActivos = $pdo->query("SELECT COUNT(*) FROM slider WHERE activo = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #d3d3d3;
            color: #2c3e50;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
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
        
        .logo h1 {
            color: white;
            font-size: 24px;
        }
        
        .logo span {
            color: #445960;
        }
        
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
        
        /* Dashboard cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .card-dashboard {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .card-dashboard:hover {
            transform: translateY(-5px);
        }
        
        .card-dashboard i {
            font-size: 48px;
            color: #173742;
            margin-bottom: 15px;
        }
        
        .card-dashboard h3 {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .card-dashboard .numero {
            font-size: 32px;
            font-weight: 700;
            color: #12232b;
        }
        
        /* Menú de administración */
        .admin-menu {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-menu h2 {
            margin-bottom: 20px;
            color: #12232b;
            border-left: 4px solid #173742;
            padding-left: 15px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(251px, 1fr));
            gap: 15px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 10px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s;
        }
        
        .menu-item:hover {
            background: #173742;
            color: white;
            transform: translateX(5px);
        }
        
        .menu-item i {
            font-size: 24px;
        }
        
        .menu-item span {
            font-weight: 500;
        }

        .menu-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f0f0f0;
        }
        
        .menu-item.disabled:hover {
            background: #f0f0f0;
            color: #2c3e50;
            transform: none;
        }
        
        .badge-rol {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-admin {
            background: #dc3545;
            color: white;
        }
        
        .badge-supervisor {
            background: #f39c12;
            color: white;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Panel de Administración</small>
            </div>
            <div class="user-info">
                <span>
                    <i class="fas fa-user-shield"></i> 
                    <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>
                    <?php if ($rol_usuario == 1): ?>
                        <span class="badge-rol badge-admin">Admin</span>
                    <?php elseif ($rol_usuario == 2): ?>
                        <span class="badge-rol badge-supervisor">Supervisor</span>
                    <?php endif; ?>
                </span>
                <a href="../logout.php" class="btn-volver">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card-dashboard">
                <i class="fas fa-users"></i>
                <h3>Usuarios Registrados</h3>
                <div class="numero"><?php echo $totalUsuarios; ?></div>
            </div>
            <div class="card-dashboard">
                <i class="fas fa-images"></i>
                <h3>Total Slides</h3>
                <div class="numero"><?php echo $totalSlides; ?></div>
            </div>
            <div class="card-dashboard">
                <i class="fas fa-eye"></i>
                <h3>Slides Activos</h3>
                <div class="numero"><?php echo $slidesActivos; ?></div>
            </div>
        </div>
        
        <!-- Menú de Administración -->
        <div class="admin-menu">
            <h2><i class="fas fa-cog"></i> Módulos de Administración</h2>
            <div class="menu-grid">
                <a href="../index.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Ir al Inicio</span>
                </a>
                
                <?php foreach ($modulos_admin as $modulo): ?>
                    <?php 
                    // ✅ USAR NUEVO SISTEMA DE PERMISOS
                    $visible = ($rol_usuario == 1) || in_array($modulo['modulo'], $permisos_usuario);
                    ?>
                    <?php if ($visible): ?>
                        <a href="<?php echo $modulo['url']; ?>" class="menu-item">
                            <i class="fas <?php echo $modulo['icono']; ?>"></i>
                            <span><?php echo $modulo['nombre']; ?></span>
                        </a>
                    <?php else: ?>
                        <div class="menu-item disabled" style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas <?php echo $modulo['icono']; ?>"></i>
                            <span><?php echo $modulo['nombre']; ?> <small style="font-size: 10px;">(sin acceso)</small></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <?php if ($rol_usuario == 2): ?>
                <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #f39c12;">
                    <i class="fas fa-info-circle" style="color: #f39c12;"></i>
                    <span style="font-size: 13px; color: #856404;">
                        <strong>Supervisor:</strong> Solo ves los módulos que el administrador te ha asignado.
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>