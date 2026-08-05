<?php
// admin/capacitaciones/index.php - Gestionar áreas y carpetas de capacitaciones
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

// Admin: acceso total
if ($rol_usuario == 1) {
    // Tiene acceso
} 
// Supervisor: verificar permiso
elseif ($rol_usuario == 2) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as tiene_permiso 
        FROM permisos_usuarios pu
        JOIN modulos m ON pu.modulo_id = m.id
        WHERE pu.usuario_id = ? AND m.nombre = 'capacitaciones' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para gestionar capacitaciones";
        header('Location: ../index.php');
        exit();
    }
} 
// Usuario normal: sin acceso
else {
    header('Location: ../index.php');
    exit();
}

// ✅ Recuperar mensajes de sesión
$mensaje = '';
$error = '';
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
    $stmt = $pdo->prepare("UPDATE categorias_capacitaciones SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Estado actualizado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Eliminar área o carpeta
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM categorias_capacitaciones WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Elemento eliminado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Obtener todas las áreas (incluyendo inactivas)
$stmt = $pdo->query("SELECT * FROM categorias_capacitaciones WHERE tipo = 'area' ORDER BY nombre ASC");
$areas_principales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Obtener todas las carpetas (incluyendo inactivas)
$stmt = $pdo->query("SELECT * FROM categorias_capacitaciones WHERE tipo = 'carpeta' ORDER BY nombre ASC");
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
$stmt = $pdo->query("SELECT * FROM categorias_capacitaciones WHERE tipo = 'area' AND activo = 1 ORDER BY nombre ASC");
$areas_select = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Áreas | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #12232b; padding: 0px 0; margin-bottom: 30px; }
        .header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { color: white; font-size: 24px; }
        .logo span { color: #445960; }
        .btn-volver { background: #445960; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; transition: all 0.3s ease; }
        .btn-volver:hover { background: #ffc107; color: #12232b; transform: translateY(-2px); }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card h2 { margin-bottom: 20px; border-left: 4px solid #173742; padding-left: 15px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 15px; border: 1px solid #d3d3d3; border-radius: 8px; font-family: 'Poppins', sans-serif; }
        .btn-guardar { background: #173742; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; }
        .btn-guardar:hover { background: #445960; transform: translateY(-2px); }
        
        .forms-row { display: flex; gap: 25px; margin-bottom: 30px; flex-wrap: wrap; }
        .form-card { flex: 1; min-width: 300px; margin-bottom: 0; }
        
        .tabla-areas { width: 100%; border-collapse: collapse; }
        .tabla-areas th, .tabla-areas td { padding: 12px; text-align: left; border-bottom: 1px solid #d3d3d3; }
        .tabla-areas th { background: #12232b; color: white; }
        
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
        .btn-toggle:hover { color: #ffc107; }
        
        .carpeta-indent { display: inline-flex; align-items: center; gap: 8px; }
        .badge-area { background: #173742; color: white; padding: 4px 10px; border-radius: 20px; font-size: 10px; margin-left: 8px; display: inline-block; }
        .badge-carpeta { background: #f39c12; color: white; padding: 4px 10px; border-radius: 20px; font-size: 10px; margin-left: 8px; display: inline-block; }
        .badge-activo { background: #27ae60; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .badge-inactivo { background: #e74c3c; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        
        .acciones { white-space: nowrap; }
        .btn-accion { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; margin: 0 3px; text-decoration: none; border-radius: 6px; font-size: 12px; transition: all 0.3s ease; }
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
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .info-text { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        .fa-rotate-90 { transform: rotate(90deg); }
        .folder-icon { color: #f39c12; margin-right: 5px; }
        .area-icon { color: #173742; margin-right: 5px; }

        .btn-permisos {
            background: #8e44ad;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .btn-permisos:hover {
            background: #732d91;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(142,68,173,0.3);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Gestionar Áreas y Carpetas</small>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="permisos/index.php" class="btn-permisos" style="
                    background: #8e44ad;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 8px;
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    font-weight: 500;
                "
                onmouseover="this.style.background='#732d91'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(142,68,173,0.3)';"
                onmouseout="this.style.background='#8e44ad'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <i class="fas fa-user-lock"></i> Permisos
                </a>
                <a href="../index.php" class="btn-volver" style="
                    background: #445960;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 8px;
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                "
                onmouseover="this.style.background='#ffc107'; this.style.color='#12232b'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(255,193,7,0.3)';"
                onmouseout="this.style.background='#445960'; this.style.color='white'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
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
                    <div class="form-group">
                        <label>Nombre del área</label>
                        <input type="text" name="nombre" required placeholder="Ej: Sistemas, Comercial, Contabilidad">
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" rows="2" placeholder="Descripción del área"></textarea>
                    </div>
                    <button type="submit" class="btn-guardar">Crear área</button>
                </form>
            </div>

            <div class="card form-card">
                <h2><i class="fas fa-plus-circle"></i> Agregar nueva carpeta</h2>
                <form action="guardar_area.php" method="POST">
                    <input type="hidden" name="tipo" value="carpeta">
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
                        <input type="text" name="nombre" required placeholder="Ej: Actas, Manuales, Capacitaciones">
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" rows="2" placeholder="Descripción de la carpeta"></textarea>
                    </div>
                    <button type="submit" class="btn-guardar">Crear carpeta</button>
                </form>
            </div>
        </div>

        <!-- ===== LISTA DE ÁREAS Y CARPETAS ===== -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Áreas y Carpetas</h2>
            
            <?php if (empty($areas_principales)): ?>
                <p>No hay áreas registradas. Crea un área primero.</p>
            <?php else: ?>
                <table class="tabla-areas">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
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