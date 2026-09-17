<?php
// admin/comercial/index.php - Panel del módulo Comercial
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

// Admin: acceso total
if ($rol_usuario == 1) {
    // Tiene acceso
} 
// Supervisor: verificar permiso
elseif ($rol_usuario == 2) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as tiene_permiso 
        FROM permisos_usuarios pu
        JOIN modulos m ON pu.modulo_id = m.id
        WHERE pu.usuario_id = ? AND m.nombre = 'comercial' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para acceder al módulo Comercial";
        header('Location: ../index.php');
        exit();
    }
} 
// Usuario normal: sin acceso
else {
    header('Location: ../index.php');
    exit();
}

// ============================================
// MÓDULOS DE COMERCIAL
// ============================================
$modulos_comercial = [
    [
        'url' => 'comisiones/index.php',
        'icono' => 'fa-money-bill-wave',
        'nombre' => 'Comisiones',
        'descripcion' => 'Cargar y gestionar comisiones de asesores'
    ],
    [
        'url' => 'estadisticas/index.php',
        'icono' => 'fa-chart-bar',
        'nombre' => 'Estadísticas',
        'descripcion' => 'Ver estadísticas globales de todos los asesores'
    ],
    [
        'url' => 'reclamos/index.php',
        'icono' => 'fa-comments',
        'nombre' => 'Reclamos',
        'descripcion' => 'Atender inconformidades de los asesores'
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comercial | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER (MISMO DISEÑO QUE admin/index.php) ===== */
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

        /* ===== MENÚ DE ADMINISTRACIÓN (MISMO DISEÑO) ===== */
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
        .menu-item .descripcion {
            font-size: 12px;
            color: #7f8c8d;
            display: block;
            margin-top: 2px;
        }
        .menu-item:hover .descripcion {
            color: #d3d3d3;
        }

        @media (max-width: 600px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER (MISMO DISEÑO QUE admin/index.php) ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Comercial</small>
            </div>
            <div class="user-info">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- ===== MENÚ DE MÓDULOS ===== -->
        <div class="admin-menu">
            <h2><i class="fas fa-chart-line"></i> Módulos de Comercial</h2>
            <div class="menu-grid">
                <?php foreach ($modulos_comercial as $modulo): ?>
                    <a href="<?php echo $modulo['url']; ?>" class="menu-item">
                        <i class="fas <?php echo $modulo['icono']; ?>"></i>
                        <span>
                            <?php echo $modulo['nombre']; ?>
                            <small class="descripcion"><?php echo $modulo['descripcion']; ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>