<?php
// index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/auth_check.php';

// ============================================
// OBTENER ANUNCIO ACTIVO PARA POPUP
// ============================================
$stmt = $pdo->prepare("
    SELECT * FROM anuncios 
    WHERE activo = 1 
    ORDER BY fecha DESC 
    LIMIT 1
");
$stmt->execute();
$anuncio_activo = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar si ya se mostró este anuncio en esta sesión
$mostrar_popup = false;
if ($anuncio_activo) {
    $session_key = 'anuncio_visto_' . $anuncio_activo['id'];
    if (!isset($_SESSION[$session_key])) {
        $mostrar_popup = true;
    }
}

// Mostrar mensaje de error de permisos
$error_permiso = '';
if (isset($_SESSION['error_permiso'])) {
    $error_permiso = $_SESSION['error_permiso'];
    unset($_SESSION['error_permiso']);
}

// Obtener slides activos de la base de datos
$stmt = $pdo->prepare("SELECT * FROM slider WHERE activo = 1 ORDER BY orden ASC, id ASC");
$stmt->execute();
$slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si no hay slides en BD, usar datos por defecto
if (empty($slides)) {
    $slides = [
        [
            'id' => 1,
            'titulo' => 'Innovación que Mueve tu Negocio',
            'descripcion' => 'Tecnología al servicio de tu productividad',
            'imagen_url' => 'https://www.elcarrocolombiano.com/wp-content/uploads/2026/04/20260403-KIA-SELTOS-2027-ESTADOS-UNIDOS-01.jpg',
            'tipo' => 'noticia',
            'enlace' => null
        ],
        [
            'id' => 2,
            'titulo' => 'Conectividad Inteligente',
            'descripcion' => 'Todas tus herramientas en un solo lugar',
            'imagen_url' => 'https://cdn-images.motor.es/image/m/1320w/fotos-noticias/2026/06/precio-honda-cr-v-2026-2026114494-1780304381_1.jpg',
            'tipo' => 'promocion',
            'enlace' => null
        ],
        [
            'id' => 3,
            'titulo' => 'Gestión Eficiente',
            'descripcion' => 'Optimiza tus procesos diarios',
            'imagen_url' => 'https://www.elcarrocolombiano.com/wp-content/uploads/2025/11/20251119-FAW-TRUCKS-LION-TIGER-COLOMBIA-PORTADA.jpg',
            'tipo' => 'aviso',
            'enlace' => null
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INTRANET | Plataforma Corporativa</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

<!-- ============================================ -->
<!-- HEADER CLÁSICO CORPORATIVO                    -->
<!-- ============================================ -->
<header class="header">
    <div class="container">
        <div class="logo-container" style="display: flex; align-items: center; gap: 10px;">
            <div>
                <img src="uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
            </div>
        </div>
        
        <div class="header-actions">
            <div class="welcome-message">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <i class="fas fa-user-check"></i>
                    <span>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></strong></span>
                    
                    <?php if ($_SESSION['rol'] == 1 || $_SESSION['rol'] == 2): ?>
                        <a href="admin/index.php" style="color: #445960; margin-left: 10px; text-decoration: none; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 8px; font-weight: 500;"
                        onmouseover="this.style.background='#ffc107'; this.style.color='#12232b'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(255,193,7,0.3)';"
                        onmouseout="this.style.background='transparent'; this.style.color='#445960'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fas fa-cog"></i> Panel Administrador
                        </a>
                    <?php endif; ?>
                    
                    <a href="logout.php" style="color: #445960; margin-left: 10px; text-decoration: none; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 8px; font-weight: 500;"
                    onmouseover="this.style.background='#ffc107'; this.style.color='#12232b'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(255,193,7,0.3)';"
                    onmouseout="this.style.background='transparent'; this.style.color='#445960'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                <?php else: ?>
                    <i class="fas fa-user"></i>
                    <span>Bienvenido, <strong>Visitante</strong></span>
                    <a href="login.php" class="login-link">
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
<main class="main">
    <div class="container">
        
        <!-- SECCIÓN 1: SLIDER DINÁMICO (DESDE BD) -->
        <section id="inicio" class="slider-section">
            <div class="slider-container" id="slider-container">
                <div class="slider-wrapper" id="slider-wrapper">
                    <?php foreach ($slides as $slide): ?>
                        <div class="slide">
                            <div class="slide-overlay"></div>
                            <img src="<?php echo htmlspecialchars($slide['imagen_url']); ?>" alt="<?php echo htmlspecialchars($slide['titulo']); ?>">
                            <div class="slide-content">
                                <?php if ($slide['tipo']): ?>
                                    <div class="slide-badge"><?php echo strtoupper(htmlspecialchars($slide['tipo'])); ?></div>
                                <?php endif; ?>
                                <h2><?php echo htmlspecialchars($slide['titulo']); ?></h2>
                                <p><?php echo htmlspecialchars($slide['descripcion']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button class="slider-btn prev" id="slider-prev" aria-label="Anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="slider-btn next" id="slider-next" aria-label="Siguiente">
                    <i class="fas fa-chevron-right"></i>
                </button>
                
                <div class="slider-dots" id="slider-dots"></div>
            </div>
        </section>
        
        <!-- SECCIÓN 2: APLICACIONES (TODAS PÚBLICAS) -->
        <section id="aplicaciones" class="apps-section">
            <div class="section-header">
                <div class="section-header-left">
                    <span class="section-tag">ACCESO RÁPIDO</span>
                    <h2 class="section-title">ARMOTOR DIGITAL</h2>
                    <p class="section-subtitle">Herramientas para tu día a día</p>
                </div>
                <div class="search-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" placeholder="Buscar aplicación...">
                    </div>
                </div>
            </div>
            
            <div class="apps-grid">
                <!-- GLPI -->
                <div class="app-card" 
                     data-url="https://gestion.armotor.com/index.php?noAUTO=1" 
                     data-name="GLPI" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">ARMOTOR 360</h3>
                        <p class="app-description">Permisos, Facturas, Legalizaciones, Tickets, Trazabilidad y Logística</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- Control de VH -->
                <div class="app-card" 
                     data-url="https://script.google.com/macros/s/AKfycbxloW5450LWibJOk2iwebj9kmlHGiwLJpMumPwhT0LQTI1164kdZM5b_vz1Vf3YJ7VW/exec" 
                     data-name="Control de VH" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Control de VH</h3>
                        <p class="app-description">Control de ingreso y salida de VH del taller</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- Heinsohn -->
                <div class="app-card" 
                     data-url="https://nominasaas184.heinsohn.com.co/NominaWEB/common/mainPages/login.seam" 
                     data-name="Heinsohn" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Heinsohn Nomina</h3>
                        <p class="app-description">Sistema de gestión del empleado</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- Canal de Denuncias -->
                <div class="app-card" 
                     data-url="https://docs.google.com/forms/d/e/1FAIpQLSdUGl4A8wlVoayNAudzVNFHTElB95mVstX_tmcjxClB9dt1kQ/viewform" 
                     data-name="Canal de Denuncias" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Canal de Denuncias</h3>
                        <p class="app-description">Reportes y denuncias</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- Página Armotor -->
                <div class="app-card" 
                     data-url="https://armotor.com/" 
                     data-name="Página Armotor" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Página Web</h3>
                        <p class="app-description">Sitio web oficial</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- Comisiones -->
                <div class="app-card" 
                     data-url="https://comisiones.armotor.com/" 
                     data-name="Comisiones" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Comisiones</h3>
                        <p class="app-description">Gestión de comisiones</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- Fondo Empleados -->
                <div class="app-card" 
                     data-url="https://www.fondarme.com.co/" 
                     data-name="Fondo Empleados" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Fondo Empleados</h3>
                        <p class="app-description">Beneficios para empleados</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- NotiArmotor -->
                <div class="app-card" 
                     data-url="https://online.fliphtml5.com/rtorp/nnuq/#p=1" 
                     data-name="NotiArmotor" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">NotiArmotor</h3>
                        <p class="app-description">Noticias y comunicados</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- CENF -->
                <div class="app-card" 
                     data-url="https://cenf.cen.biz/site/" 
                     data-name="CENF" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">CENF</h3>
                        <p class="app-description">Centro electrónico de negocios financiero</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
                
                <!-- Correo Empresarial -->
                <div class="app-card" 
                     data-url="https://mail.google.com/" 
                     data-name="Correo Empresarial" 
                     data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Correo Corporativo</h3>
                        <p class="app-description">Correo electrónico</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>

                <!-- Sala Reuniones -->
                <div class="app-card" 
                    data-url="https://script.google.com/a/macros/armotor.com/s/AKfycbyrpdOAzZ_BbqtXWjOSj4a4fQmENYt1ONluT0Pdhn5xWYNXLwBl_GOnKFc1_gLph8W9/exec" 
                    data-name="Sala Reuniones" 
                    data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Sala Reuniones</h3>
                        <p class="app-description">Reserva y gestión de espacios</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>

                <!-- OTROS -->
                <div class="app-card" 
                    data-url="aplicaciones/otros/index.php" 
                    data-name="Otros" 
                    data-requiere-login="false">
                    <div class="app-icon">
                        <i class="fas fa-ellipsis-h"></i>
                    </div>
                    <div class="app-info">
                        <h3 class="app-name">Otros</h3>
                        <p class="app-description">Acceso a otras herramientas</p>
                    </div>
                    <i class="fas fa-arrow-right app-arrow"></i>
                </div>
        </section>
        
        <!-- SECCIÓN 3: DOCUMENTACIÓN (TODA REQUIERE LOGIN) -->
        <section id="documentacion" class="docs-section">
            <div class="section-header">
                <div class="section-header-left">
                    <span class="section-tag">RECURSOS</span>
                    <h2 class="section-title">DOCUMENTACIÓN</h2>
                    <p class="section-subtitle">Manuales y guías operativas</p>
                </div>
            </div>
            
            <div class="docs-grid">
                <!-- Gestión Humana -->
                <div class="doc-card" data-url="documentacion/gestion_humana/index.php" data-nombre="Gestión Humana" data-requiere-login="true">
                    <div class="doc-icon"><i class="fas fa-users-cog"></i></div>
                    <div class="doc-info">
                        <h3>Gestión Humana</h3>
                        <p>Formatos y documentación de Gestión Humana</p>
                    </div>
                </div>

                <!-- Capacitación -->
                <div class="doc-card" data-url="documentacion/capacitaciones/index.php" data-nombre="Capacitación" data-requiere-login="true">
                    <div class="doc-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="doc-info">
                        <h3>Capacitación</h3>
                        <p>Todos los documentos, procesos y capacitación de la empresa</p>
                    </div>
                </div>
                
                <!-- Políticas ARMOTOR -->
                <div class="doc-card" data-url="documentacion/politicas/index.php" data-nombre="Políticas ARMOTOR" data-requiere-login="false">
                    <div class="doc-icon"><i class="fas fa-gavel"></i></div>
                    <div class="doc-info">
                        <h3>Políticas</h3>
                        <p>Todas las políticas creadas por la empresa</p>
                    </div>
                </div>
                
                <!-- Documentos SIG -->
                <div class="doc-card" data-url="documentacion/documentos_sig/index.php" data-nombre="Documentos SIG" data-requiere-login="true">
                    <div class="doc-icon"><i class="fas fa-table"></i></div>
                    <div class="doc-info">
                        <h3>Documentos SIG</h3>
                        <p>Documentos del Sistema de Gestión</p>
                    </div>
                </div>
            </div>
        </section>
        
    </div>
</main>

<!-- ============================================ -->
<!-- FOOTER                                        -->
<!-- ============================================ -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-logo">
                <span>INTRANET</span>
            </div>
            <div class="footer-links">
                <a href="index.php">Inicio</a>
                <a href="index.php#aplicaciones">Aplicaciones</a>
                <a href="index.php#documentacion">Documentación</a>
                <a href="mailto:sistemas@armotor.com">Soporte</a>
            </div>
            <div class="footer-copyright">
                <p>© 2026 ARMOTOR - Todos los derechos reservados</p>
            </div>
        </div>
    </div>
</footer>

<!-- ============================================ -->
<!-- MODAL ERROR DE PERMISOS                      -->
<!-- ============================================ -->
<?php if ($error_permiso): ?>
<div id="modalError" class="modal-error-overlay">
    <div class="modal-error-content">
        <div class="modal-error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2 class="modal-error-title">Acceso Denegado</h2>
        <p class="modal-error-message"><?php echo htmlspecialchars($error_permiso); ?></p>
        <button onclick="cerrarModalError()" class="modal-error-btn">
            Aceptar
        </button>
    </div>
</div>

<script>
    function cerrarModalError() {
        const modal = document.getElementById('modalError');
        modal.style.display = 'none';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('modalError');
            if (modal) {
                cerrarModalError();
            }
        }
    });

    document.addEventListener('click', function(e) {
        const modal = document.getElementById('modalError');
        if (modal && e.target === modal) {
            cerrarModalError();
        }
    });
</script>
<?php endif; ?>

<!-- ============================================ -->
<!-- POPUP DE ANUNCIOS                            -->
<!-- ============================================ -->
<?php if ($mostrar_popup && $anuncio_activo): ?>
<div id="popupAnuncio" class="popup-overlay active">
    <div class="popup-container">
        <!-- Botón cerrar X arriba -->
        <button class="popup-close" onclick="cerrarPopup()">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="popup-content">
            <?php if (!empty($anuncio_activo['archivo'])): ?>
                <?php 
                $extension = strtolower(pathinfo($anuncio_activo['archivo'], PATHINFO_EXTENSION));
                $ruta_archivo = 'uploads/anuncios/' . $anuncio_activo['archivo'];
                ?>
                
                <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                    <!-- Visualización de imagen -->
                    <div class="popup-imagen">
                        <img src="<?php echo $ruta_archivo; ?>" alt="<?php echo htmlspecialchars($anuncio_activo['titulo']); ?>">
                    </div>
                <?php elseif ($extension === 'pdf'): ?>
                    <!-- Visualización de PDF embebido -->
                    <div class="popup-pdf">
                        <iframe src="<?php echo $ruta_archivo; ?>#toolbar=0&navpanes=0&scrollbar=0" 
                                frameborder="0" 
                                class="pdf-iframe"
                                style="width:100%; min-height:300px; border-radius:8px;">
                        </iframe>
                    </div>
                <?php else: ?>
                    <!-- Otros archivos (descarga) -->
                    <div class="popup-icono-archivo">
                        <i class="fas fa-file"></i>
                        <p>Archivo: <?php echo htmlspecialchars($anuncio_activo['archivo']); ?></p>
                        <a href="<?php echo $ruta_archivo; ?>" target="_blank" class="popup-descargar">
                            <i class="fas fa-external-link-alt"></i> Ver archivo
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="popup-texto">
                <h2><?php echo htmlspecialchars($anuncio_activo['titulo']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($anuncio_activo['descripcion'])); ?></p>
            </div>
        </div>
        
        <!-- Botón Cerrar abajo -->
        <button class="popup-btn-cerrar" onclick="cerrarPopup()">
            <i class="fas fa-times-circle"></i> Cerrar
        </button>
    </div>
</div>

<script>
    function cerrarPopup() {
        const popup = document.getElementById('popupAnuncio');
        popup.classList.remove('active');
        popup.style.display = 'none';
        
        // Ocultar también el iframe del PDF si existe
        const iframe = document.querySelector('.pdf-iframe');
        if (iframe) {
            iframe.src = '';
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const popup = document.getElementById('popupAnuncio');
            if (popup && popup.classList.contains('active')) {
                cerrarPopup();
            }
        }
    });

    document.addEventListener('click', function(e) {
        const popup = document.getElementById('popupAnuncio');
        if (popup && popup.classList.contains('active') && e.target === popup) {
            cerrarPopup();
        }
    });

    <?php if ($anuncio_activo): ?>
        <?php $session_key = 'anuncio_visto_' . $anuncio_activo['id']; ?>
        <?php if (!isset($_SESSION[$session_key])): ?>
            <?php $_SESSION[$session_key] = true; ?>
        <?php endif; ?>
    <?php endif; ?>
</script>
<?php endif; ?>

<!-- JavaScript -->
<script src="js/main.js"></script>
</body>
</html>