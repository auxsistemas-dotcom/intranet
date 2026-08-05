<?php
// aplicaciones/otros/index.php - Menú de Otras Aplicaciones
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Obtener usuario de forma segura
$usuario = obtenerUsuario();
$nombre_usuario = isset($usuario['nombre_completo']) ? $usuario['nombre_completo'] : 'Visitante';
$rol_usuario = isset($usuario['rol']) ? $usuario['rol'] : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otras Aplicaciones | INTRANET</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ============================================ */
        /* ESTILOS COMPLETOS - SIN DEPENDER DE EXTERNOS */
        /* ============================================ */

        /* === RESET === */
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
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* === HEADER === */
        .header {
            background: #12232b;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 3px solid #173742;
        }

        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: #445960;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 700;
            color: white;
        }

        .logo-subtitle {
            font-size: 11px;
            color: #d3d3d3;
            letter-spacing: 0.5px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .welcome-message {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-size: 14px;
        }

        .welcome-message i {
            color: #445960;
        }

        .login-link {
            color: #445960;
            margin-left: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 8px;
        }

        .login-link:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        .logout-link {
            color: #445960;
            margin-left: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 8px;
        }

        .logout-link:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        .admin-panel-btn {
            background: #173742;
            color: white;
            margin-left: 15px;
            padding: 6px 15px;
            text-decoration: none;
            border-radius: 20px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
        }

        .admin-panel-btn:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* === CONTENIDO PRINCIPAL === */
        .otros-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* === HEADER DE BIENVENIDA === */
        .welcome-header {
            background: linear-gradient(135deg, #173742, #12232b);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 40px;
            color: white;
            text-align: center;
        }

        .welcome-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-header h1 i {
            margin-right: 15px;
            color: #ffc107;
        }

        .welcome-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .user-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 50px;
            margin-top: 15px;
            font-size: 14px;
        }

        /* === BOTÓN VOLVER === */
        .btn-volver-doc {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-volver-doc:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* === GRID DE APLICACIONES === */
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .app-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 18px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid transparent;
            min-height: 120px;
        }

        .app-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: #445960;
        }

        .app-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #173742, #445960);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }

        .app-info {
            flex: 1;
        }

        .app-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .app-description {
            font-size: 13px;
            color: #7f8c8d;
        }

        .app-arrow {
            color: #d3d3d3;
            font-size: 18px;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .app-card:hover .app-arrow {
            color: #173742;
            transform: translateX(5px);
        }

        /* === FOOTER === */
        .footer {
            background: #12232b;
            color: white;
            margin-top: 80px;
            padding: 40px 0 24px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 30px;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-logo i {
            font-size: 28px;
            color: #445960;
        }

        .footer-logo span {
            font-weight: 600;
            font-size: 18px;
        }

        .footer-copyright {
            font-size: 12px;
            opacity: 0.7;
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .container {
                padding: 0 16px;
            }

            .header .container {
                flex-direction: column;
                gap: 15px;
            }

            .welcome-header {
                padding: 25px;
            }

            .welcome-header h1 {
                font-size: 24px;
            }

            .apps-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- HEADER                                        -->
<!-- ============================================ -->
<header class="header">
    <div class="container">
        <div class="logo-container">
            <div>
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <p class="logo-subtitle" style="color: #8aa8b8; font-size: 14px;">| Plataforma Corporativa</p>
            </div>
        </div>
        <div class="header-actions">
            <div class="welcome-message">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <i class="fas fa-user-check"></i>
                    <span>Bienvenido, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
                    <?php if ($rol_usuario === 'admin'): ?>
                        <a href="../../admin/index.php" class="admin-panel-btn">
                            <i class="fas fa-cog"></i> Panel Administrador
                        </a>
                    <?php endif; ?>
                    <a href="../../logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                <?php else: ?>
                    <i class="fas fa-user"></i>
                    <span>Bienvenido, <strong>Visitante</strong></span>
                    <a href="../../login.php" class="login-link">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- ============================================ -->
<!-- CONTENIDO PRINCIPAL                           -->
<!-- ============================================ -->
<main>
    <div class="otros-container">
        <a href="../../index.php" class="btn-volver-doc">
            <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
        
        <div class="welcome-header">
            <h1><i class="fas fa-th-large"></i> Otras Aplicaciones</h1>
            <p>Accesos adicionales y herramientas externas</p>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <div class="user-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($nombre_usuario); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="apps-grid">
            <!-- Logística -->
            <div class="app-card" 
                 data-url="logistica/index.php" 
                 data-name="Logística" 
                 data-requiere-login="false">
                <div class="app-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div class="app-info">
                    <h3 class="app-name">Logística</h3>
                    <p class="app-description">Gestión de transporte y envíos</p>
                </div>
                <i class="fas fa-arrow-right app-arrow"></i>
            </div>
            <!-- Posventa -->
            <div class="app-card" 
                data-url="posventa/index.php" 
                data-name="Posventa" 
                data-requiere-login="false">
                <div class="app-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="app-info">
                    <h3 class="app-name">Posventa</h3>
                    <p class="app-description">Gestión de servicios postventa</p>
                </div>
                <i class="fas fa-arrow-right app-arrow"></i>
            </div>
        </div>
    </div>
</main>

<script src="../../js/main.js"></script>
</body>
</html>