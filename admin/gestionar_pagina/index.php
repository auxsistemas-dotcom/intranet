<?php
// admin/gestionar_pagina/index.php - Dashboard de gestión de página
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar la página";
    header('Location: ../index.php');
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
// ESTADÍSTICAS
// ============================================

// Total de anuncios
$stmt = $pdo->query("SELECT COUNT(*) as total FROM anuncios");
$total_anuncios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Anuncios activos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM anuncios WHERE activo = 1");
$anuncios_activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de slides
$stmt = $pdo->query("SELECT COUNT(*) as total FROM slider");
$total_slides = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Slides activos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM slider WHERE activo = 1");
$slides_activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Página | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 10px; 
        }

        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            padding: 10px 0;
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
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 576px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }

        .card-dashboard {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card-dashboard:hover { transform: translateY(-5px); }
        .card-dashboard i { font-size: 40px; margin-bottom: 12px; }
        .card-dashboard h3 { font-size: 13px; color: #7f8c8d; margin-bottom: 5px; }
        .card-dashboard .numero { font-size: 32px; font-weight: 700; color: #12232b; }

        .card-dashboard .icon-anuncios { color: #f39c12; }
        .card-dashboard .icon-slider { color: #3498db; }
        .card-dashboard .icon-activo { color: #27ae60; }

        /* ===== MÓDULOS ===== */
        .modulos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        @media (max-width: 992px) {
            .modulos-grid {
                grid-template-columns: 1fr;
            }
        }

        .modulo-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-top: 5px solid #173742;
        }
        .modulo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .modulo-card .modulo-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .modulo-card .modulo-header i {
            font-size: 36px;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modulo-card .modulo-header h2 {
            font-size: 22px;
            color: #12232b;
            margin: 0;
        }
        .modulo-card .modulo-header .badge-count {
            background: #e8f0fe;
            color: #173742;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 10px;
        }

        .modulo-card .modulo-body p {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .modulo-card .modulo-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-modulo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-modulo:hover {
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-success:hover {
            background: #219a52;
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }

        .btn-primary {
            background: #173742;
            color: white;
        }
        .btn-primary:hover {
            background: #445960;
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }

        /* ===== MODULO ESPECÍFICO ===== */
        .modulo-card.anuncios { border-top-color: #f39c12; }
        .modulo-card.anuncios .modulo-header i { background: #f39c12; }

        .modulo-card.slider { border-top-color: #3498db; }
        .modulo-card.slider .modulo-header i { background: #3498db; }

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
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Gestionar Página</small>
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ===== DASHBOARD CARDS ===== -->
        <div class="dashboard-cards">
            <div class="card-dashboard">
                <i class="fas fa-bullhorn icon-anuncios"></i>
                <div class="numero"><?php echo $total_anuncios; ?></div>
                <h3>Total Anuncios</h3>
            </div>
            <div class="card-dashboard">
                <i class="fas fa-check-circle icon-activo"></i>
                <div class="numero"><?php echo $anuncios_activos; ?></div>
                <h3>Anuncios Activos</h3>
            </div>
            <div class="card-dashboard">
                <i class="fas fa-images icon-slider"></i>
                <div class="numero"><?php echo $total_slides; ?></div>
                <h3>Total Slides</h3>
            </div>
            <div class="card-dashboard">
                <i class="fas fa-check-circle icon-activo"></i>
                <div class="numero"><?php echo $slides_activos; ?></div>
                <h3>Slides Activos</h3>
            </div>
        </div>

        <!-- ===== MÓDULOS ===== -->
        <div class="modulos-grid">
            <!-- ANUNCIOS -->
            <div class="modulo-card anuncios">
                <div class="modulo-header">
                    <i class="fas fa-bullhorn"></i>
                    <div>
                        <h2>
                            Anuncios
                            <span class="badge-count"><?php echo $total_anuncios; ?></span>
                        </h2>
                    </div>
                </div>
                <div class="modulo-body">
                    <p>Gestiona los anuncios que aparecen como popup al iniciar sesión. Crea, edita y administra los avisos importantes.</p>
                    <div class="modulo-actions">
                        <a href="anuncios/crear.php" class="btn-modulo btn-success">
                            <i class="fas fa-plus"></i> Nuevo Anuncio
                        </a>
                        <a href="anuncios/index.php" class="btn-modulo btn-primary">
                            <i class="fas fa-list"></i> Ver Todos
                        </a>
                    </div>
                </div>
            </div>

            <!-- SLIDER -->
            <div class="modulo-card slider">
                <div class="modulo-header">
                    <i class="fas fa-images"></i>
                    <div>
                        <h2>
                            Slider
                            <span class="badge-count"><?php echo $total_slides; ?></span>
                        </h2>
                    </div>
                </div>
                <div class="modulo-body">
                    <p>Gestiona las imágenes del carrusel principal de la página. Agrega, edita y ordena las slides.</p>
                    <div class="modulo-actions">
                        <a href="slider/crear.php" class="btn-modulo btn-success">
                            <i class="fas fa-plus"></i> Agregar Slide
                        </a>
                        <a href="slider/index.php" class="btn-modulo btn-primary">
                            <i class="fas fa-list"></i> Ver Todos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>