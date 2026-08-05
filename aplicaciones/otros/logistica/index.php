<?php
// aplicaciones/otros/logistica/index.php - Página de Logística
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../includes/config.php';

// Obtener datos del usuario si está logueado
$usuario_nombre = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : null;
$rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;

// Obtener solo archivos activos
$stmt = $pdo->query("SELECT * FROM logistica WHERE activo = 1 ORDER BY titulo ASC");
$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$marcas_nombres = [
    'kia' => 'KIA',
    'honda' => 'HONDA',
    'faw' => 'FAW',
    'taxis' => 'TAXIS',
    'inventario' => 'INVENTARIO'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logística | INTRANET</title>
    <link rel="stylesheet" href="../../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .logistica-container {
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

        .excel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .excel-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .excel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: #1d6f42;
        }

        .excel-icon {
            width: 70px;
            height: 70px;
            background: #d4edda;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: #1d6f42;
        }

        .excel-card h3 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .excel-card p {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }

        .btn-excel {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #1d6f42;
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

        .btn-excel:hover {
            background: #145c34;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(29, 111, 66, 0.3);
        }

        .btn-excel i {
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

        .sin-archivos {
            text-align: center;
            padding: 60px;
            color: #7f8c8d;
        }

        .sin-archivos i {
            font-size: 48px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .excel-grid {
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
                <p class="logo-subtitle" style="color: #8aa8b8; font-size: 14px;">| Logística</p>
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
    <div class="logistica-container">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <a href="../index.php" class="btn-volver-doc">
                <i class="fas fa-arrow-left"></i> Volver atras
            </a>
        </div>

        <div class="welcome-header">
            <h1><i class="fas fa-map-marked-alt"></i> Logística</h1>
            <p>Descarga los archivos de Excel de cada área</p>
            <?php if (isset($_SESSION['usuario_id'])): ?>
            <?php endif; ?>
        </div>

        <?php if (empty($archivos)): ?>
            <div class="sin-archivos">
                <i class="fas fa-file-excel"></i>
                <p>No hay archivos disponibles.</p>
                <p style="font-size: 14px;">Contacta al administrador para más información.</p>
            </div>
        <?php else: ?>
            <div class="excel-grid">
                <?php foreach ($archivos as $archivo): ?>
                    <?php 
                    $marca_nombre = $marcas_nombres[$archivo['marca']] ?? ucfirst($archivo['marca']);
                    ?>
                    <div class="excel-card">
                        <div class="excel-icon"><i class="fas fa-file-excel"></i></div>
                        <h3><?php echo htmlspecialchars($marca_nombre); ?></h3>
                        <p><?php echo htmlspecialchars($archivo['titulo']); ?></p>
                        <p style="font-size: 13px; color: #7f8c8d; margin-bottom: 15px;">
                            <?php echo htmlspecialchars($archivo['descripcion']); ?>
                        </p>
                        <a href="../../../<?php echo $archivo['archivo_url']; ?>" class="btn-excel" download>
                            <i class="fas fa-download"></i> Descargar Excel
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="../../../js/main.js"></script>
</body>
</html>