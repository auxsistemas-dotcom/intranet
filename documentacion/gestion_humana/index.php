<?php
// documentacion/gestion_humana/index.php - Página de Gestión Humana
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Requiere login
requiereLogin();

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];
$rol_usuario = $usuario['rol'];

// ✅ VERIFICAR ACCESO BÁSICO - Permitir a todos los usuarios autenticados
if ($rol_usuario == 3) {
    // Usuario normal - tiene acceso
    // No hacer nada, solo seguir
} elseif ($rol_usuario == 1 || $rol_usuario == 2) {
    // Admin o supervisor - también tienen acceso
    // No hacer nada, solo seguir
} else {
    // Si por alguna razón no tiene rol válido, redirigir
    $_SESSION['error_permiso'] = "No tienes permiso para acceder a Inducción.";
    header('Location: ../../../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Humana | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .gestion-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            background: #12232b;
            border-bottom: 3px solid #173742;
        }
        
        .header .container { 
            display: flex;
            justify-content: space-between;
            align-items: center; 
        }
        
        .welcome-header {
            background: linear-gradient(135deg, #2c3e50, #173742);
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
        
        /* Módulo de acceso */
        .modulo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .modulo-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            text-decoration: none;
            color: #2c3e50;
            display: block;
        }
        
        .modulo-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        
        .modulo-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, #2c3e50, #173742);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .modulo-icon i {
            font-size: 36px;
            color: #ffc107;
        }
        
        .modulo-card h3 {
            font-size: 22px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .modulo-card p {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        
        .modulo-card .btn-acceder {
            display: inline-block;
            background: #2c3e50;
            color: white;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .modulo-card .btn-acceder:hover {
            background: #173742;
            transform: scale(1.05);
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
            margin-bottom: 40px;
            transition: all 0.3s;
        }
        
        .btn-volver-doc:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        .progreso-modulo {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .progreso-modulo .barra {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progreso-modulo .barra .fill {
            height: 100%;
            background: linear-gradient(90deg, #2c3e50, #27ae60);
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        
        .progreso-modulo .texto {
            font-size: 13px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>

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
                <i class="fas fa-user-check"></i>
                <span>Bienvenido, <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong></span>
                <a href="../../logout.php" style="
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
            </div>
        </div>
    </div>
</header>

<main>
    <div class="gestion-container">
        <a href="../../index.php" class="btn-volver-doc">
            <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
        
        <div class="welcome-header">
            <h1><i class="fas fa-users-cog"></i> Gestión Humana</h1>
            <p>Documentos, procesos y guías del área de Gestión Humana</p>
        </div>
        
        <div class="modulo-grid">
            <!-- ✅ Módulo de Inducción -->
            <a href="induccion/index.php" class="modulo-card">
                <div class="modulo-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h3>Inducción</h3>
                <p>Proceso de inducción para nuevos empleados</p>
                <span class="btn-acceder">
                    <i class="fas fa-arrow-right"></i> Acceder
                </span>
            </a>
            
            <!-- Aquí se pueden agregar más módulos en el futuro -->
            <!--
            <a href="capacitaciones/index.php" class="modulo-card">
                <div class="modulo-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <h3>Capacitaciones</h3>
                <p>Capacitaciones y formación continua</p>
                <span class="btn-acceder">
                    <i class="fas fa-arrow-right"></i> Acceder
                </span>
            </a>
            -->
        </div>
    </div>
</main>
</body>
</html>