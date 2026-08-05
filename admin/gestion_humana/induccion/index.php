<?php
// admin/gestion_humana/induccion/index.php - Gestionar Inducción
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar permisos de capacitaciones";
    header('Location: ../../index.php');
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

// ✅ Cambiar estado (activar/desactivar)
if (isset($_GET['cambiar_estado']) && is_numeric($_GET['cambiar_estado'])) {
    $id = $_GET['cambiar_estado'];
    $stmt = $pdo->prepare("UPDATE categorias_gestion_humana SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Estado actualizado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Eliminar área o carpeta
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM categorias_gestion_humana WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Elemento eliminado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Obtener todas las áreas (tipo 'area')
$stmt = $pdo->query("
    SELECT * FROM categorias_gestion_humana 
    WHERE tipo = 'area' AND activo = 1
    ORDER BY orden ASC, nombre ASC
");
$areas_principales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Obtener todas las carpetas (tipo 'carpeta') con su quiz_url
$stmt = $pdo->query("
    SELECT * FROM categorias_gestion_humana 
    WHERE tipo = 'carpeta' AND activo = 1
    ORDER BY nombre ASC
");
$todas_carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar carpetas por padre_id
$carpetas_por_padre = [];
foreach ($todas_carpetas as $carpeta) {
    $padre = $carpeta['padre_id'] ?? 0;
    if (!isset($carpetas_por_padre[$padre])) {
        $carpetas_por_padre[$padre] = [];
    }
    $carpetas_por_padre[$padre][] = $carpeta;
}

// Obtener áreas para el select del formulario (solo activas)
$stmt = $pdo->query("
    SELECT * FROM categorias_gestion_humana 
    WHERE tipo = 'area' AND activo = 1 
    ORDER BY nombre ASC
");
$areas_select = $stmt->fetchAll(PDO::FETCH_ASSOC);

$no_areas = empty($areas_principales);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Inducción | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
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
        .logo h1 { color: white; font-size: 24px; font-weight: 700; }
        .logo small { color: #8aa8b8; font-size: 14px; font-weight: 300; }
        
        .btn-volver {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 8px 18px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
            border-color: #ffc107;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
            font-size: 18px;
        }
        
        .forms-row {
            display: flex;
            gap: 25px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .form-card { flex: 1; min-width: 300px; margin-bottom: 0; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 60px; }
        
        .btn-guardar {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-guardar:hover {
            background: #445960;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }
        
        .table-responsive { overflow-x: auto; }
        .tabla-areas { width: 100%; border-collapse: collapse; }
        .tabla-areas th, .tabla-areas td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        .tabla-areas th { background: #12232b; color: white; font-weight: 600; font-size: 13px; }
        tr:hover { background: #f8f9fa; }
        
        .area-row { background-color: #f0f7ff; }
        .carpeta-row { background-color: #f9f9f9; display: none; }
        .carpeta-row.visible { display: table-row; }
        .carpeta-row td:first-child { padding-left: 50px; }
        
        .btn-toggle {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-right: 8px;
            color: #173742;
            transition: color 0.3s;
        }
        .btn-toggle:hover { color: #f39c12; }
        
        .carpeta-indent { display: inline-flex; align-items: center; gap: 8px; }
        
        .badge-area {
            background: #173742;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
            display: inline-block;
        }
        .badge-carpeta {
            background: #f39c12;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
            display: inline-block;
        }
        .badge-activo {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        .badge-inactivo {
            background: #fadbd8;
            color: #922b21;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        
        /* ✅ Badge para Quiz */
        .badge-quiz {
            background: #8e44ad;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
            display: inline-block;
        }
        .badge-quiz-configurado {
            background: #27ae60;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
            display: inline-block;
        }
        
        .acciones { white-space: nowrap; }
        .btn-accion {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            margin: 0 3px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-accion i { font-size: 12px; }
        .btn-accion:hover { transform: translateY(-2px); }
        .btn-documentos { background: #27ae60; color: white; }
        .btn-documentos:hover { background: #219a52; }
        .btn-estado { background: #f39c12; color: white; }
        .btn-estado:hover { background: #e67e22; }
        .btn-editar { background: #3498db; color: white; }
        .btn-editar:hover { background: #2980b9; }
        .btn-eliminar { background: #e74c3c; color: white; }
        .btn-eliminar:hover { background: #c0392b; }
        
        .mensaje-exito {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
        }
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state i { font-size: 48px; color: #d3d3d3; margin-bottom: 15px; }
        .empty-state h3 { color: #2c3e50; }
        
        .info-text { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        .fa-rotate-90 { transform: rotate(90deg); }
        .folder-icon { color: #f39c12; margin-right: 5px; }
        .area-icon { color: #173742; margin-right: 5px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small>| Inducción</small>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver a Gestión Humana
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ===== FORMULARIOS ===== -->
        <div class="forms-row">
            <div class="card form-card">
                <h2><i class="fas fa-plus-circle"></i> Agregar nueva área</h2>
                <form action="guardar_area.php" method="POST">
                    <input type="hidden" name="tipo" value="area">
                    <input type="hidden" name="modulo" value="induccion">
                    <div class="form-group">
                        <label>Nombre del área</label>
                        <input type="text" name="nombre" required placeholder="Ej: Inducción">
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" rows="2" placeholder="Descripción del área"></textarea>
                    </div>
                    <button type="submit" class="btn-guardar">Crear área</button>
                </form>
            </div>

            <!-- ===== FORMULARIO AGREGAR CARPETA ===== -->
            <div class="card form-card">
                <h2><i class="fas fa-plus-circle"></i> Agregar nueva carpeta</h2>
                <form action="guardar_carpeta.php" method="POST">
                    <input type="hidden" name="tipo" value="carpeta">
                    <input type="hidden" name="modulo" value="induccion">
                    
                    <div class="form-group">
                        <label>Área padre</label>
                        <select name="padre_id" required>
                            <option value="">Seleccionar área</option>
                            <?php foreach ($areas_select as $area_sel): ?>
                                <option value="<?php echo $area_sel['id']; ?>">
                                    <?php echo htmlspecialchars($area_sel['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Nombre de la carpeta</label>
                        <input type="text" name="nombre" required placeholder="Ej: Módulo 1: Bienvenida">
                    </div>
                    
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" rows="2" placeholder="Descripción de la carpeta"></textarea>
                    </div>
                    
                    <!-- ✅ CAMPO QUIZ URL (OPCIONAL) -->
                    <div class="form-group">
                        <label><i class="fas fa-link"></i> URL del Quiz <span style="font-weight: 400; color: #7f8c8d; font-size: 12px;">(opcional)</span></label>
                        <input type="url" name="quiz_url" placeholder="https://docs.google.com/forms/d/...">
                        <div class="info-text">
                            <i class="fas fa-info-circle"></i> 
                            Si se completa, aparecerá un botón "Quiz" al completar los documentos de esta carpeta.
                            <br>Puedes configurarlo después desde la edición.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-guardar">Crear carpeta</button>
                </form>
            </div>
        </div>

        <!-- ===== LISTA DE ÁREAS Y CARPETAS ===== -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Áreas y Carpetas de Inducción</h2>
            
            <?php if ($no_areas): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No hay áreas registradas</h3>
                    <p>Crea un área para comenzar a organizar el contenido de Inducción.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="tabla-areas">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Quiz</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($areas_principales as $area): ?>
                                <?php 
                                $carpetas_aqui = $carpetas_por_padre[$area['id']] ?? [];
                                $tiene_carpetas = !empty($carpetas_aqui);
                                ?>
                                <tr class="area-row" data-area-id="<?php echo $area['id']; ?>">
                                    <td>
                                        <?php if ($tiene_carpetas): ?>
                                            <button class="btn-toggle" onclick="toggleCarpetas(<?php echo $area['id']; ?>)">
                                                <i class="fas fa-chevron-right" id="icon_<?php echo $area['id']; ?>"></i>
                                            </button>
                                        <?php else: ?>
                                            <span style="display: inline-block; width: 24px;"></span>
                                        <?php endif; ?>
                                        <i class="fas fa-folder-open area-icon"></i>
                                        <strong><?php echo htmlspecialchars($area['nombre']); ?></strong>
                                        <span class="badge-area">Área</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($area['descripcion']); ?></td>
                                    <td>
                                        <?php 
                                        // Contar carpetas con quiz configurado en esta área
                                        $quiz_count = 0;
                                        foreach ($carpetas_aqui as $c) {
                                            if (!empty($c['quiz_url'])) {
                                                $quiz_count++;
                                            }
                                        }
                                        ?>
                                        <?php if ($quiz_count > 0): ?>
                                            <span class="badge-quiz-configurado">
                                                <i class="fas fa-check-circle"></i> <?php echo $quiz_count; ?> quiz(es)
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #95a5a6; font-size: 12px;">Sin quiz</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo $area['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <?php echo $area['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="acciones">
                                        <a href="?cambiar_estado=<?php echo $area['id']; ?>" class="btn-accion btn-estado" title="Cambiar estado">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                        <a href="editar_area.php?id=<?php echo $area['id']; ?>" class="btn-accion btn-editar" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?eliminar=<?php echo $area['id']; ?>" class="btn-accion btn-eliminar" title="Eliminar" onclick="return confirm('¿Eliminar esta área y todas sus carpetas?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                
                                <!-- Carpetas de esta área (ocultas por defecto) -->
                                <?php if ($tiene_carpetas): ?>
                                    <?php foreach ($carpetas_aqui as $carpeta): ?>
                                        <tr class="carpeta-row" data-padre="<?php echo $area['id']; ?>">
                                            <td>
                                                <div class="carpeta-indent">
                                                    <i class="fas fa-level-down-alt fa-rotate-90"></i>
                                                    <i class="fas fa-folder folder-icon"></i>
                                                    <?php echo htmlspecialchars($carpeta['nombre']); ?>
                                                    <span class="badge-carpeta">Carpeta</span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($carpeta['descripcion']); ?></td>
                                            <td>
                                                <?php if (!empty($carpeta['quiz_url'])): ?>
                                                    <span class="badge-quiz-configurado" title="URL: <?php echo htmlspecialchars($carpeta['quiz_url']); ?>">
                                                        <i class="fas fa-link"></i> Configurado
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #95a5a6; font-size: 12px;">Sin configurar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="<?php echo $carpeta['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                    <?php echo $carpeta['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td class="acciones">
                                                <a href="?cambiar_estado=<?php echo $carpeta['id']; ?>" class="btn-accion btn-estado" title="Cambiar estado">
                                                    <i class="fas fa-toggle-on"></i>
                                                </a>
                                                <a href="documentos.php?categoria_id=<?php echo $carpeta['id']; ?>" class="btn-accion btn-documentos" title="Ver documentos">
                                                    <i class="fas fa-file-alt"></i>
                                                </a>
                                                <a href="editar_carpeta.php?id=<?php echo $carpeta['id']; ?>" class="btn-accion btn-editar" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?eliminar=<?php echo $carpeta['id']; ?>" class="btn-accion btn-eliminar" title="Eliminar" onclick="return confirm('¿Eliminar esta carpeta?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleCarpetas(areaId) {
            const carpetas = document.querySelectorAll(`tr.carpeta-row[data-padre="${areaId}"]`);
            const icon = document.getElementById(`icon_${areaId}`);
            
            carpetas.forEach(carpeta => {
                carpeta.classList.toggle('visible');
            });
            
            if (carpetas.length > 0 && carpetas[0].classList.contains('visible')) {
                icon.className = 'fas fa-chevron-down';
            } else {
                icon.className = 'fas fa-chevron-right';
            }
        }
    </script>
</body>
</html>