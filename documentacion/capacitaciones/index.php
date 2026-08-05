<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Requiere login
requiereLogin();

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];
$rol_usuario = $usuario['rol'];

// ✅ VERIFICAR ACCESO BÁSICO
if ($rol_usuario == 3) {
    $_SESSION['error_permiso'] = "No tienes permiso para acceder a Capacitaciones. Contacta al administrador.";
    header('Location: ../../index.php');
    exit();
}

// ✅ OBTENER PERMISOS DEL SUPERVISOR
$permisos_areas = [];
$permisos_carpetas = [];

if ($rol_usuario == 2) {
    // Obtener permisos de áreas para este supervisor
    $stmt = $pdo->prepare("
        SELECT categoria_id 
        FROM permisos_capacitaciones 
        WHERE usuario_id = ? AND tipo = 'area' AND puede_ver = 1
    ");
    $stmt->execute([$usuario_id]);
    $areas_permiso = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener permisos de carpetas para este supervisor
    $stmt = $pdo->prepare("
        SELECT categoria_id 
        FROM permisos_capacitaciones 
        WHERE usuario_id = ? AND tipo = 'carpeta' AND puede_ver = 1
    ");
    $stmt->execute([$usuario_id]);
    $carpetas_permiso = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $permisos_areas = $areas_permiso;
    $permisos_carpetas = $carpetas_permiso;
}

// ✅ OBTENER ÁREAS
if ($rol_usuario == 1) {
    // Admin: ver todas las áreas
    $stmt = $pdo->prepare("SELECT * FROM categorias_capacitaciones WHERE tipo = 'area' AND activo = 1 ORDER BY orden ASC");
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Supervisor: ver solo las áreas permitidas
    if (empty($permisos_areas)) {
        $_SESSION['error_permiso'] = "No tienes permisos para ver áreas de capacitación.";
        header('Location: ../../index.php');
        exit();
    }
    
    $placeholders = implode(',', array_fill(0, count($permisos_areas), '?'));
    $stmt = $pdo->prepare("
        SELECT * FROM categorias_capacitaciones 
        WHERE tipo = 'area' AND activo = 1 AND id IN ($placeholders) 
        ORDER BY orden ASC
    ");
    $stmt->execute($permisos_areas);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Para cada área, obtener sus carpetas y documentos
foreach ($areas as $key => $area) {
    // Obtener carpetas de esta área
    if ($rol_usuario == 1) {
        // Admin: ver todas las carpetas
        $stmt = $pdo->prepare("
            SELECT * FROM categorias_capacitaciones 
            WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1 
            ORDER BY orden ASC
        ");
        $stmt->execute([$area['id']]);
        $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Supervisor: ver solo las carpetas permitidas que pertenecen a esta área
        if (empty($permisos_carpetas)) {
            $carpetas = [];
        } else {
            $placeholders = implode(',', array_fill(0, count($permisos_carpetas), '?'));
            $stmt = $pdo->prepare("
                SELECT * FROM categorias_capacitaciones 
                WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1 AND id IN ($placeholders) 
                ORDER BY orden ASC
            ");
            $stmt->execute(array_merge([$area['id']], $permisos_carpetas));
            $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ✅ Para cada carpeta, obtener sus documentos y progreso
    foreach ($carpetas as $subkey => $carpeta) {
        // ✅ CORREGIDO: Usar progreso_usuarios_capacitaciones
        $stmt = $pdo->prepare("
            SELECT d.*, 
            (SELECT COUNT(*) FROM progreso_usuarios_capacitaciones WHERE usuario_id = ? AND documento_id = d.id) as visto
            FROM documentos_capacitaciones d
            WHERE d.categoria_id = ? AND d.activo = 1 
            ORDER BY d.orden ASC, d.titulo ASC
        ");
        $stmt->execute([$usuario_id, $carpeta['id']]);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular progreso de la carpeta
        $total_docs = count($documentos);
        $vistos = 0;
        foreach ($documentos as $doc) {
            if ($doc['visto'] > 0) $vistos++;
        }
        $progreso = $total_docs > 0 ? round(($vistos / $total_docs) * 100) : 0;
        
        $carpetas[$subkey]['documentos'] = $documentos;
        $carpetas[$subkey]['progreso'] = $progreso;
        $carpetas[$subkey]['total_docs'] = $total_docs;
        $carpetas[$subkey]['vistos'] = $vistos;
    }
    
    $areas[$key]['carpetas'] = $carpetas;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capacitación | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .documentacion-container {
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
        
        /* Área principal */
        .area-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .area-header {
            background: linear-gradient(135deg, #173742, #12232b);
            color: white;
            padding: 20px 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
            transition: background 0.3s;
        }
        
        .area-header:hover {
            background: linear-gradient(135deg, #1a4a5a, #12232b);
        }
        
        .area-header .area-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .area-header .area-title h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .area-header .area-title i {
            margin-right: 10px;
        }
        
        .area-header .area-toggle {
            font-size: 20px;
            color: #ffc107;
            transition: transform 0.3s;
        }
        
        .area-header .area-toggle.rotated {
            transform: rotate(180deg);
        }
        
        .area-header p {
            margin: 5px 0 0;
            opacity: 0.8;
            font-size: 14px;
        }
        
        /* Contenido del área (carpetas) - oculto por defecto */
        .area-content {
            display: none;
            padding: 0;
        }
        
        .area-content.visible {
            display: block;
        }
        
        /* Carpeta */
        .carpeta-container {
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .carpeta-container:last-child {
            border-bottom: none;
        }
        
        .carpeta-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #173742;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            user-select: none;
        }
        
        .carpeta-header:hover {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 5px 10px;
            margin: -5px -10px 10px -10px;
        }
        
        .carpeta-header h3 {
            font-size: 18px;
            color: #173742;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
        .carpeta-header h3 i {
            color: #f39c12;
        }
        
        .carpeta-header .toggle-icon {
            font-size: 14px;
            color: #173742;
            transition: transform 0.3s;
        }
        
        .carpeta-header p {
            font-size: 13px;
            color: #7f8c8d;
            margin: 0;
        }
        
        /* Barra de progreso */
        .progress-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
            padding: 8px 0;
        }
        
        .progress-bar-container {
            flex: 1;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
            background: linear-gradient(90deg, #173742, #445960);
        }
        
        .progress-bar.completed {
            background: linear-gradient(90deg, #27ae60, #2ecc71);
        }
        
        .progress-text {
            font-size: 13px;
            font-weight: 600;
            color: #173742;
            min-width: 45px;
            text-align: right;
        }
        
        .progress-text.completed {
            color: #27ae60;
        }
        
        /* Documentos (ocultos por defecto) */
        .documentos-list {
            display: none;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
            padding-left: 35px;
        }
        
        .documentos-list.visible {
            display: flex;
        }
        
        .documento-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            background: #f9f9f9;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .documento-item:hover {
            background: #f0f0f0;
            transform: translateX(5px);
        }
        
        .documento-item.visto {
            background: #d4edda;
            border-left: 4px solid #27ae60;
        }
        
        .documento-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .documento-icon.pdf {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .documento-icon.video {
            background: #e0f2fe;
            color: #0284c7;
        }
        
        .documento-icon.word {
            background: #d4e6f1;
            color: #1a4a7a;
        }
        
        .documento-icon.excel {
            background: #d5f5e3;
            color: #1a7a3a;
        }
        
        .documento-icon.link {
            background: #e8d5f5;
            color: #6c3483;
        }
        
        .documento-icon.imagen {
            background: #fdebd0;
            color: #a04000;
        }
        
        .documento-info {
            flex: 1;
        }
        
        .documento-info h4 {
            font-size: 15px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .documento-info p {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .documento-check {
            color: #27ae60;
            font-size: 18px;
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
        }
        
        .btn-documento:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .sin-documentos {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            font-style: italic;
        }
        
        .sin-carpetas {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
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
                <i class="fas fa-user-check"></i>
                <span>Bienvenido, <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong></span>
                <?php if ($rol_usuario == 1): ?>
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
            </div>
        </div>
    </div>
</header>

<main>
    <div class="documentacion-container">
        <a href="../../index.php" class="btn-volver-doc">
            <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
        
        <div class="welcome-header">
            <h1><i class="fas fa-folder-open"></i> Capacitación</h1>
            <p>Manuales y guías técnicas disponibles para tu área</p>
            <div class="user-badge">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($usuario['nombre_completo']); ?>
            </div>
        </div>
        
        <?php if (empty($areas)): ?>
            <div class="sin-carpetas">
                <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 20px;"></i>
                <p>No tienes capacitaciones asignadas.</p>
                <p style="font-size: 14px;">Contacta al administrador para solicitar acceso.</p>
            </div>
        <?php else: ?>
            <?php foreach ($areas as $area): ?>
                <div class="area-card">
                    <!-- Área header con flecha -->
                    <div class="area-header" onclick="toggleArea(<?php echo $area['id']; ?>)">
                        <div class="area-title">
                            <i class="fas fa-building"></i>
                            <h2><?php echo htmlspecialchars($area['nombre']); ?></h2>
                            <p><?php echo htmlspecialchars($area['descripcion']); ?></p>
                        </div>
                        <span class="area-toggle" id="area_icon_<?php echo $area['id']; ?>">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </div>
                    
                    <!-- Contenido del área (carpetas) -->
                    <div class="area-content" id="area_content_<?php echo $area['id']; ?>">
                        <?php if (empty($area['carpetas'])): ?>
                            <div class="sin-carpetas">
                                <p>No hay carpetas disponibles en esta área.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($area['carpetas'] as $index => $carpeta): ?>
                                <div class="carpeta-container">
                                    <div class="carpeta-header" onclick="toggleCarpeta(<?php echo $area['id']; ?>, <?php echo $index; ?>)">
                                        <span class="toggle-icon" id="icon_<?php echo $area['id']; ?>_<?php echo $index; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                        <h3>
                                            <i class="fas fa-folder"></i>
                                            <?php echo htmlspecialchars($carpeta['nombre']); ?>
                                        </h3>
                                        <p><?php echo htmlspecialchars($carpeta['descripcion']); ?></p>
                                    </div>
                                    
                                    <!-- Barra de progreso -->
                                    <div class="progress-wrapper">
                                        <span style="font-size: 13px; color: #7f8c8d;">
                                            Progreso: <?php echo $carpeta['vistos']; ?>/<?php echo $carpeta['total_docs']; ?>
                                        </span>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar <?php echo $carpeta['progreso'] == 100 ? 'completed' : ''; ?>" 
                                                style="width: <?php echo $carpeta['progreso']; ?>%;">
                                            </div>
                                        </div>
                                        <span class="progress-text <?php echo $carpeta['progreso'] == 100 ? 'completed' : ''; ?>">
                                            <?php echo $carpeta['progreso']; ?>%
                                        </span>
                                    </div>
                                    
                                    <!-- Documentos -->
                                    <div class="documentos-list" id="docs_<?php echo $area['id']; ?>_<?php echo $index; ?>">
                                        <?php if (empty($carpeta['documentos'])): ?>
                                            <div class="sin-documentos">
                                                <i class="fas fa-file-alt"></i> No hay documentos en esta carpeta.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($carpeta['documentos'] as $doc): ?>
                                                <div class="documento-item <?php echo $doc['visto'] > 0 ? 'visto' : ''; ?>" data-doc-id="<?php echo $doc['id']; ?>">
                                                    <div class="documento-icon <?php echo $doc['tipo_archivo']; ?>">
                                                        <i class="fas <?php 
                                                            echo $doc['tipo_archivo'] === 'pdf' ? 'fa-file-pdf' : 
                                                                ($doc['tipo_archivo'] === 'video' ? 'fa-video' :
                                                                ($doc['tipo_archivo'] === 'word' ? 'fa-file-word' :
                                                                ($doc['tipo_archivo'] === 'excel' ? 'fa-file-excel' :
                                                                ($doc['tipo_archivo'] === 'link' ? 'fa-link' : 'fa-file-image')))); 
                                                        ?>"></i>
                                                    </div>
                                                    <div class="documento-info">
                                                        <h4><?php echo htmlspecialchars($doc['titulo']); ?></h4>
                                                        <p><?php echo htmlspecialchars($doc['descripcion']); ?></p>
                                                    </div>
                                                    <?php if ($doc['visto'] > 0): ?>
                                                        <span class="documento-check"><i class="fas fa-check-circle"></i></span>
                                                    <?php endif; ?>
                                                    <a href="javascript:void(0)" onclick="abrirYMarcar('<?php echo $doc['url']; ?>', <?php echo $doc['id']; ?>)" class="btn-documento">
                                                        <i class="fas fa-external-link-alt"></i> Ver
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
                <p>© 2026 INTRANET - Todos los derechos reservados</p>
            </div>
        </div>
    </div>
</footer>

<script>
    // =============================================
    // 1. FUNCIONES DE TOGGLE (con guardado de estado)
    // =============================================
    function toggleArea(areaId) {
        const content = document.getElementById('area_content_' + areaId);
        const icon = document.getElementById('area_icon_' + areaId);
        
        if (content.classList.contains('visible')) {
            content.classList.remove('visible');
            icon.innerHTML = '<i class="fas fa-chevron-down"></i>';
        } else {
            content.classList.add('visible');
            icon.innerHTML = '<i class="fas fa-chevron-up"></i>';
        }
        
        guardarEstado();
    }

    function toggleCarpeta(areaId, index) {
        const docs = document.getElementById('docs_' + areaId + '_' + index);
        const icon = document.getElementById('icon_' + areaId + '_' + index);
        
        if (docs.classList.contains('visible')) {
            docs.classList.remove('visible');
            icon.innerHTML = '<i class="fas fa-chevron-right"></i>';
        } else {
            docs.classList.add('visible');
            icon.innerHTML = '<i class="fas fa-chevron-down"></i>';
        }
        
        guardarEstado();
    }
    
    // =============================================
    // 2. GUARDAR ESTADO EN localStorage
    // =============================================
    function guardarEstado() {
        const areasAbiertas = [];
        const carpetasAbiertas = [];
        
        document.querySelectorAll('.area-content').forEach(content => {
            if (content.classList.contains('visible')) {
                const id = content.id.replace('area_content_', '');
                areasAbiertas.push(id);
            }
        });
        
        document.querySelectorAll('.documentos-list').forEach(list => {
            if (list.classList.contains('visible')) {
                const id = list.id;
                carpetasAbiertas.push(id);
            }
        });
        
        localStorage.setItem('areas_abiertas', JSON.stringify(areasAbiertas));
        localStorage.setItem('carpetas_abiertas', JSON.stringify(carpetasAbiertas));
    }
    
    // =============================================
    // 3. RESTAURAR ESTADO DESPUÉS DE RECARGAR
    // =============================================
    function restaurarEstado() {
        const areasAbiertas = JSON.parse(localStorage.getItem('areas_abiertas') || '[]');
        areasAbiertas.forEach(id => {
            const content = document.getElementById('area_content_' + id);
            const icon = document.getElementById('area_icon_' + id);
            if (content && icon) {
                content.classList.add('visible');
                icon.innerHTML = '<i class="fas fa-chevron-up"></i>';
            }
        });
        
        const carpetasAbiertas = JSON.parse(localStorage.getItem('carpetas_abiertas') || '[]');
        carpetasAbiertas.forEach(id => {
            const docs = document.getElementById(id);
            if (docs) {
                docs.classList.add('visible');
                const parent = docs.closest('.carpeta-container');
                if (parent) {
                    const icon = parent.querySelector('.toggle-icon');
                    if (icon) {
                        icon.innerHTML = '<i class="fas fa-chevron-down"></i>';
                    }
                }
            }
        });
    }
    
    // =============================================
    // 4. ABRIR Y MARCAR DOCUMENTO
    // =============================================
    function abrirYMarcar(url, documentoId) {
        guardarEstado();
        marcarVisto(documentoId);
        
        setTimeout(function() {
            if (url.startsWith('http://') || url.startsWith('https://')) {
                window.open(url, '_blank');
            } else {
                window.open('../../' + url, '_blank');
            }
        }, 300);
    }

    // =============================================
    // 5. MARCAR VISTO
    // =============================================
    function marcarVisto(documentoId) {
        fetch('marcar_visto_capacitacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ documento_id: documentoId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const allDocs = document.querySelectorAll('[data-doc-id]');
                allDocs.forEach(docElement => {
                    if (parseInt(docElement.getAttribute('data-doc-id')) === documentoId) {
                        docElement.classList.add('visto');
                        
                        if (!docElement.querySelector('.documento-check')) {
                            const check = document.createElement('span');
                            check.className = 'documento-check';
                            check.innerHTML = '<i class="fas fa-check-circle"></i>';
                            docElement.insertBefore(check, docElement.lastChild);
                        }
                        
                        actualizarProgresoCarpeta(docElement);
                    }
                });
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    // =============================================
    // 6. ACTUALIZAR PROGRESO DE LA CARPETA
    // =============================================
    function actualizarProgresoCarpeta(docElement) {
        const docList = docElement.closest('.documentos-list');
        if (!docList) return;
        
        const carpetaContainer = docList.closest('.carpeta-container');
        if (!carpetaContainer) return;
        
        const allDocs = carpetaContainer.querySelectorAll('.documento-item');
        const vistos = carpetaContainer.querySelectorAll('.documento-item.visto').length;
        const total = allDocs.length;
        const porcentaje = total > 0 ? Math.round((vistos / total) * 100) : 0;
        
        const progressBar = carpetaContainer.querySelector('.progress-bar');
        const progressText = carpetaContainer.querySelector('.progress-text');
        const progressWrapper = carpetaContainer.querySelector('.progress-wrapper');
        
        if (progressBar) {
            progressBar.style.width = porcentaje + '%';
            if (porcentaje === 100) {
                progressBar.classList.add('completed');
            } else {
                progressBar.classList.remove('completed');
            }
        }
        
        if (progressText) {
            progressText.textContent = porcentaje + '%';
            if (porcentaje === 100) {
                progressText.classList.add('completed');
            } else {
                progressText.classList.remove('completed');
            }
        }
        
        const span = progressWrapper.querySelector('span:first-child');
        if (span) {
            span.textContent = 'Progreso: ' + vistos + '/' + total;
        }
    }

    // =============================================
    // 7. RESTAURAR ESTADO AL CARGAR LA PÁGINA
    // =============================================
    document.addEventListener('DOMContentLoaded', function() {
        restaurarEstado();
    });
</script>
</body>
</html>