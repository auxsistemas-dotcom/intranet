<?php
// aplicaciones/otros/posventa/index.php - Página de Posventa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../includes/config.php';

// Obtener datos del usuario si está logueado
$usuario_nombre = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : null;
$rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posventa | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../css/styles.css">
    <style>
        .posventa-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .welcome-header {
            background: linear-gradient(135deg, #173742, #12232b);
            border-radius: 20px;
            padding: 40px;
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

        /* Grid de servicios */
        .servicios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .servicio-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .servicio-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: #173742;
        }

        .servicio-icon {
            width: 70px;
            height: 70px;
            background: #e8f4f8;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: #173742;
        }

        .servicio-card h3 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .servicio-card p {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 15px;
        }

        .btn-servicio {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #173742;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-servicio:hover {
            background: #445960;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23, 55, 66, 0.3);
        }

        .btn-servicio i {
            font-size: 16px;
        }

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
        }

        .btn-volver-doc:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        .sin-servicios {
            text-align: center;
            padding: 60px;
            color: #7f8c8d;
        }

        .sin-servicios i {
            font-size: 48px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .servicios-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo-container">
            <div>
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <p class="logo-subtitle" style="color: #8aa8b8; font-size: 14px;">| Posventa</p>
            </div>
        </div>
        <div class="header-actions">
            <div class="welcome-message">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <i class="fas fa-user-check"></i>
                    <span>Bienvenido, <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong></span>
                    <?php if ($rol_usuario === 'admin'): ?>
                        <a href="../../../admin/index.php" style="
                            color: #445960;
                            margin-left: 10px;
                            text-decoration: none;
                            transition: all 0.3s ease;
                            display: inline-flex;
                            align-items: center;
                            gap: 5px;
                            padding: 5px 12px;
                            border-radius: 8px;
                            font-weight: 500;
                        "
                        onmouseover="this.style.background='#ffc107'; this.style.color='#12232b'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(255,193,7,0.3)';"
                        onmouseout="this.style.background='transparent'; this.style.color='#445960'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fas fa-cog"></i> Panel Administrador
                        </a>
                    <?php endif; ?>
                    <a href="../../../logout.php" style="
                        color: #445960;
                        margin-left: 10px;
                        text-decoration: none;
                        transition: all 0.3s ease;
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                        padding: 5px 12px;
                        border-radius: 8px;
                        font-weight: 500;
                    "
                    onmouseover="this.style.background='#ffc107'; this.style.color='#12232b'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(255,193,7,0.3)';"
                    onmouseout="this.style.background='transparent'; this.style.color='#445960'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                <?php else: ?>
                    <i class="fas fa-user"></i>
                    <span>Bienvenido, <strong>Visitante</strong></span>
                    <a href="../../../login.php" class="login-link">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main>
    <div class="posventa-container">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <a href="../index.php" class="btn-volver-doc">
                <i class="fas fa-arrow-left"></i> Volver atras
            </a>
        </div>

        <div class="welcome-header">
            <h1><i class="fas fa-headset"></i> Posventa</h1>
            <p>Gestión de servicios postventa y atención al cliente</p>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <div class="user-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($usuario_nombre); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="servicios-grid">
            <!-- Servicio 1 -->
            <div class="servicio-card">
                <div class="servicio-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3>Gestión de Garantías</h3>
                <p>Gestión de garantías postventa</p>
                <a href="#" class="btn-servicio">
                    <i class="fas fa-arrow-right"></i> Acceder
                </a>
            </div>

            <!-- Servicio 2 -->
            <div class="servicio-card">
                <div class="servicio-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Agendamiento de Citas</h3>
                <p>Agendamiento de citas postventa</p>
                <a href="#" class="btn-servicio">
                    <i class="fas fa-arrow-right"></i> Acceder
                </a>
            </div>

            <!-- Servicio 3 -->
            <div class="servicio-card">
                <div class="servicio-icon">
                    <i class="fas fa-file-signature"></i>
                </div>
                <h3>Formularios de Satisfacción</h3>
                <p>Encuestas de satisfacción postventa</p>
                <a href="#" class="btn-servicio">
                    <i class="fas fa-arrow-right"></i> Acceder
                </a>
            </div>

            <!-- Servicio 4 -->
            <div class="servicio-card">
                <div class="servicio-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3>Servicio Técnico</h3>
                <p>Gestión de servicio técnico postventa</p>
                <a href="#" class="btn-servicio">
                    <i class="fas fa-arrow-right"></i> Acceder
                </a>
            </div>

            <!-- Servicio 5 -->
            <div class="servicio-card">
                <div class="servicio-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3>Historial de Vehículos</h3>
                <p>Historial de servicios postventa</p>
                <a href="#" class="btn-servicio">
                    <i class="fas fa-arrow-right"></i> Acceder
                </a>
            </div>

            <!-- Servicio 6 -->
            <div class="servicio-card">
                <div class="servicio-icon">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <h3>Contacto con Ventas</h3>
                <p>Contacto con el equipo de ventas</p>
                <a href="#" class="btn-servicio">
                    <i class="fas fa-arrow-right"></i> Acceder
                </a>
            </div>
        </div>
    </div>
</main>

<script src="../../../js/main.js"></script>
</body>
</html>