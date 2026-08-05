<?php
// documentacion/documentos_sig/index.php - Página pública de Documentos SIG
require_once '../../includes/config.php';

// No requiere login - Página pública

// Iniciar sesión para mostrar usuario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener datos del usuario si está logueado
$usuario_nombre = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : null;
$rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;

// Obtener todos los documentos de Documentos SIG (solo activos)
$stmt = $pdo->prepare("
    SELECT * FROM documentos_sig 
    WHERE activo = 1 
    ORDER BY titulo ASC
");
$stmt->execute();
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para obtener información según la extensión
function getFileInfo($archivo_url) {
    $extension = strtolower(pathinfo($archivo_url, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'pdf':
            return [
                'icono' => 'fa-file-pdf',
                'color' => '#dc2626',
                'boton' => 'Descargar PDF',
                'clase' => 'pdf'
            ];
        case 'xls':
        case 'xlsx':
            return [
                'icono' => 'fa-file-excel',
                'color' => '#1d6f42',
                'boton' => 'Descargar Excel',
                'clase' => 'excel'
            ];
        default:
            return [
                'icono' => 'fa-file-alt',
                'color' => '#7f8c8d',
                'boton' => 'Descargar',
                'clase' => 'other'
            ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos SIG | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .documentos-container {
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
        
        .documentos-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .documento-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            flex-wrap: wrap;
        }
        
        .documento-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .documento-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .documento-icon.pdf {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .documento-icon.excel {
            background: #d4edda;
            color: #1d6f42;
        }
        
        .documento-icon.other {
            background: #f0f0f0;
            color: #7f8c8d;
        }
        
        .documento-info {
            flex: 1;
            min-width: 200px;
        }
        
        .documento-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .documento-info p {
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .btn-descargar {
            padding: 8px 20px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn-descargar:hover {
            transform: translateY(-2px);
        }
        
        .btn-descargar.pdf {
            background: #dc2626;
        }
        
        .btn-descargar.pdf:hover {
            background: #b91c1c;
        }
        
        .btn-descargar.excel {
            background: #1d6f42;
        }
        
        .btn-descargar.excel:hover {
            background: #145c34;
        }
        
        .btn-descargar.other {
            background: #7f8c8d;
        }
        
        .btn-descargar.other:hover {
            background: #5a6a72;
        }
        
        .sin-documentos {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .sin-documentos i {
            font-size: 48px;
            margin-bottom: 20px;
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
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <i class="fas fa-user-check"></i>
                    <span>Bienvenido, <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong></span>
                    <?php if ($rol_usuario === 'admin'): ?>
                        <a href="../../admin/index.php" style="
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

<main>
    <div class="documentos-container">
        <a href="../../index.php" class="btn-volver-doc">
            <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
        
        <div class="welcome-header">
            <h1><i class="fas fa-file-alt"></i> Documentos SIG</h1>
            <p>Documentos del Sistema de Gestión</p>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <div class="user-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($usuario_nombre); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($documentos)): ?>
            <div class="sin-documentos">
                <i class="fas fa-file-alt"></i>
                <p>No hay documentos disponibles.</p>
                <p style="font-size: 14px;">Contacta al administrador para más información.</p>
            </div>
        <?php else: ?>
            <div class="documentos-grid">
                <?php foreach ($documentos as $doc): ?>
                    <?php 
                    $info = getFileInfo($doc['archivo_url']);
                    $ruta_descarga = '../../' . $doc['archivo_url'];
                    ?>
                    <div class="documento-item">
                        <div class="documento-icon <?php echo $info['clase']; ?>">
                            <i class="fas <?php echo $info['icono']; ?>"></i>
                        </div>
                        <div class="documento-info">
                            <h3><?php echo htmlspecialchars($doc['titulo']); ?></h3>
                            <p><?php echo htmlspecialchars($doc['descripcion']); ?></p>
                        </div>
                        <a href="<?php echo $ruta_descarga; ?>" class="btn-descargar <?php echo $info['clase']; ?>" download>
                            <i class="fas fa-download"></i> <?php echo $info['boton']; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-logo">
                <i class="fas fa-car"></i>
                <span>INTRANET</span>
            </div>
            <div class="footer-copyright">
                <p>© <?php echo date('Y'); ?> INTRANET - Todos los derechos reservados</p>
            </div>
        </div>
    </div>
</footer>

</body>
</html>