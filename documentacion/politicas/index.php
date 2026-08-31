<?php
// documentacion/politicas_armotor/index.php - Página pública de Políticas ARMOTOR
require_once '../../includes/config.php';

// No requiere login - Página pública

// Iniciar sesión para mostrar usuario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener datos del usuario si está logueado
$usuario_nombre = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : null;
$rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;

// ✅ CORREGIDO: Obtener todos los documentos de Políticas ARMOTOR (solo activos)
$stmt = $pdo->prepare("
    SELECT * FROM politicas
    WHERE activo = 1 
    ORDER BY orden ASC, titulo ASC
");
$stmt->execute();
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas ARMOTOR | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .politicas-container {
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
        
        /* === BUSCADOR === */
        .search-container {
            max-width: 500px;
            margin: 0 auto 30px;
        }
        
        .search-box {
            background: white;
            border-radius: 50px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .search-box:focus-within {
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .search-box i {
            color: #7f8c8d;
            font-size: 18px;
        }
        
        .search-box input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            background: transparent;
        }
        
        .search-box .clear-btn {
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            font-size: 18px;
            display: none;
        }
        
        .search-box .clear-btn:hover {
            color: #e74c3c;
        }
        
        .result-count {
            text-align: center;
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        /* === DOCUMENTOS === */
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
        }
        
        .documento-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .documento-item.hidden {
            display: none;
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
        
        .documento-info {
            flex: 1;
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
        
        .btn-documento {
            padding: 8px 20px;
            background: #173742;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .btn-documento:hover {
            background: #445960;
            transform: translateY(-2px);
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
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .documento-item {
                flex-direction: column;
                text-align: center;
            }
            .documento-icon {
                margin: 0 auto;
            }
            .btn-documento {
                width: 100%;
                justify-content: center;
            }
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
                    <?php if ($rol_usuario == 1 || $rol_usuario == 'admin'): ?>
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
    <div class="politicas-container">
        <a href="../../index.php" class="btn-volver-doc">
            <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
        
        <div class="welcome-header">
            <h1><i class="fas fa-gavel"></i> Políticas ARMOTOR</h1>
            <p>Políticas y normativas de la organización</p>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <div class="user-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($usuario_nombre); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- === BUSCADOR === -->
        <div class="search-container">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscador" placeholder="Buscar política por nombre..." onkeyup="filtrarPoliticas()">
                <button class="clear-btn" id="clearBtn" onclick="limpiarBusqueda()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="result-count" id="resultCount">
                <?php echo count($documentos); ?> políticas encontradas
            </div>
        </div>
        
        <?php if (empty($documentos)): ?>
            <div class="sin-documentos">
                <i class="fas fa-file-alt"></i>
                <p>No hay políticas disponibles.</p>
                <p style="font-size: 14px;">Contacta al administrador para más información.</p>
            </div>
        <?php else: ?>
            <div class="documentos-grid" id="documentosGrid">
                <?php foreach ($documentos as $doc): ?>
                    <div class="documento-item" data-titulo="<?php echo strtolower(htmlspecialchars($doc['titulo'])); ?>">
                        <!-- ✅ ICONO FIJO PARA TODOS LOS DOCUMENTOS (todos son PDF) -->
                        <div class="documento-icon pdf">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="documento-info">
                            <h3><?php echo htmlspecialchars($doc['titulo']); ?></h3>
                            <p><?php echo htmlspecialchars($doc['descripcion']); ?></p>
                        </div>
                        <!-- ✅ BOTÓN PARA VER PDF -->
                        <a href="../../<?php echo htmlspecialchars($doc['archivo_url']); ?>" class="btn-documento" target="_blank">
                            <i class="fas fa-file-pdf"></i> Ver PDF
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<script>
    function filtrarPoliticas() {
        const input = document.getElementById('buscador');
        const filter = input.value.toLowerCase();
        const items = document.querySelectorAll('.documento-item');
        let visibleCount = 0;
        
        items.forEach(function(item) {
            const titulo = item.getAttribute('data-titulo');
            if (titulo.includes(filter)) {
                item.classList.remove('hidden');
                visibleCount++;
            } else {
                item.classList.add('hidden');
            }
        });
        
        const clearBtn = document.getElementById('clearBtn');
        if (filter.length > 0) {
            clearBtn.style.display = 'block';
        } else {
            clearBtn.style.display = 'none';
        }
        
        document.getElementById('resultCount').textContent = visibleCount + ' políticas encontradas';
    }
    
    function limpiarBusqueda() {
        document.getElementById('buscador').value = '';
        document.getElementById('clearBtn').style.display = 'none';
        filtrarPoliticas();
    }
</script>

</body>
</html>