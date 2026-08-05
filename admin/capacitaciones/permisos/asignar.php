<?php
// admin/capacitaciones/permisos/asignar.php - Asignar permisos a un usuario
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1) {
    $_SESSION['error'] = "❌ No tienes permiso para asignar permisos de capacitaciones";
    header('Location: ../../index.php');
    exit();
}

$id_usuario = $_GET['id'] ?? 0;
$error = '';
$mensaje = '';

// ✅ Verificar que el usuario existe y está en la lista de permisos
$stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre 
                        FROM usuarios u
                        LEFT JOIN roles r ON u.rol_id = r.id
                        WHERE u.id = ? AND u.id IN (SELECT usuario_id FROM usuarios_permisos_capacitaciones)");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    $_SESSION['error'] = "❌ Usuario no encontrado o no está en la lista de permisos";
    header('Location: index.php');
    exit();
}

// No permitir modificar permisos del propio administrador
if ($id_usuario == $_SESSION['usuario_id']) {
    $_SESSION['error'] = "❌ No puedes modificar tus propios permisos";
    header('Location: index.php');
    exit();
}

// Procesar actualización de permisos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ✅ ELIMINAR TODOS LOS PERMISOS ACTUALES
        $stmt_delete = $pdo->prepare("DELETE FROM permisos_capacitaciones WHERE usuario_id = ?");
        $stmt_delete->execute([$id_usuario]);
        
        // Insertar nuevos permisos
        if (isset($_POST['permisos']) && is_array($_POST['permisos'])) {
            foreach ($_POST['permisos'] as $categoria_id) {
                $stmt_tipo = $pdo->prepare("SELECT tipo FROM categorias_capacitaciones WHERE id = ?");
                $stmt_tipo->execute([$categoria_id]);
                $categoria = $stmt_tipo->fetch(PDO::FETCH_ASSOC);
                $tipo = $categoria ? $categoria['tipo'] : 'area';
                
                $stmt_insert = $pdo->prepare("INSERT INTO permisos_capacitaciones (usuario_id, categoria_id, tipo, puede_ver) VALUES (?, ?, ?, 1)");
                $stmt_insert->execute([$id_usuario, $categoria_id, $tipo]);
            }
            $_SESSION['mensaje'] = "✅ Permisos actualizados correctamente";
        } else {
            // ✅ Si no seleccionó ningún permiso, solo eliminar los existentes
            $_SESSION['mensaje'] = "✅ Permisos eliminados correctamente (usuario sin acceso)";
        }
        
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Error al guardar los permisos: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Obtener todas las áreas y carpetas disponibles
$areas = $pdo->query("SELECT * FROM categorias_capacitaciones WHERE tipo = 'area' AND activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$carpetas = $pdo->query("
    SELECT c.*, p.nombre as padre_nombre 
    FROM categorias_capacitaciones c
    LEFT JOIN categorias_capacitaciones p ON c.padre_id = p.id
    WHERE c.tipo = 'carpeta' AND c.activo = 1 
    ORDER BY p.nombre, c.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener los permisos actuales del usuario
$stmt_permisos = $pdo->prepare("SELECT categoria_id FROM permisos_capacitaciones WHERE usuario_id = ? AND puede_ver = 1");
$stmt_permisos->execute([$id_usuario]);
$permisos_actuales = $stmt_permisos->fetchAll(PDO::FETCH_COLUMN);

$permisos_asignados = array_flip($permisos_actuales);
?>
<!-- El HTML sigue igual -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Permisos | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #2c3e50; }
        .container { max-width: 800px; margin: 50px auto; padding: 20px; }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .card-header i {
            font-size: 28px;
            color: #173742;
            background: #e8f0fe;
            padding: 12px;
            border-radius: 12px;
        }
        .card-header h2 { font-size: 22px; color: #12232b; }
        .card-header .badge-usuario {
            background: #173742;
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #173742;
        }
        .user-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
        }
        .user-info-grid p { margin: 3px 0; font-size: 14px; }
        .user-info-grid strong { color: #12232b; }
        .user-info .info-note {
            grid-column: 1 / -1;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #d3d3d3;
            color: #7f8c8d;
            font-size: 12px;
        }
        .user-info .info-note i { color: #f39c12; }
        
        .seccion {
            margin-bottom: 20px;
        }
        .seccion h3 {
            font-size: 16px;
            color: #173742;
            margin-bottom: 10px;
            padding-left: 10px;
            border-left: 3px solid #173742;
        }
        
        .permisos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .permiso-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: #fafafa;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .permiso-item:hover {
            background: #f0f4f8;
            border-color: #173742;
        }
        .permiso-item.checked {
            background: #e8f0fe;
            border-color: #173742;
        }
        .permiso-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #173742;
            flex-shrink: 0;
        }
        .permiso-item label {
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            color: #2c3e50;
        }
        .permiso-item .sub-text {
            font-size: 11px;
            color: #7f8c8d;
            font-weight: 400;
        }
        
        .select-all {
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e9ecef;
        }
        .select-all input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #173742;
            cursor: pointer;
        }
        .select-all label {
            font-weight: 600;
            color: #2c3e50;
            cursor: pointer;
        }
        .select-all .total-items {
            margin-left: auto;
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .btn-guardar {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            border: none;
            padding: 14px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
        }
        .btn-guardar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }
        
        .btn-volver {
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        .mensaje-exito {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            font-weight: 500;
        }
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
        }
        
        @media (max-width: 600px) {
            .user-info-grid { grid-template-columns: 1fr; }
            .permisos-grid { grid-template-columns: 1fr 1fr; }
            .card-header { flex-wrap: wrap; }
            .card-header .badge-usuario { margin-left: 0; }
            .container { padding: 10px; margin: 20px auto; }
            .card { padding: 20px; }
        }
        @media (max-width: 400px) {
            .permisos-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver a Permisos
        </a>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-cog"></i>
                <h2>Asignar Permisos</h2>
                <span class="badge-usuario">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($usuario['usuario']); ?>
                </span>
            </div>
            
            <div class="user-info">
                <div class="user-info-grid">
                    <p><strong>👤 Usuario:</strong> <?php echo htmlspecialchars($usuario['usuario']); ?></p>
                    <p><strong>📛 Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre_completo']); ?></p>
                    <p><strong>🔑 Rol:</strong> 
                        <?php 
                        if ($usuario['rol_id'] == 1) {
                            echo 'Administrador';
                        } elseif ($usuario['rol_id'] == 2) {
                            echo 'Supervisor';
                        } else {
                            echo 'Usuario';
                        }
                        ?>
                    </p>
                    <p><strong>📊 Permisos:</strong> <?php echo count($permisos_actuales); ?> elementos</p>
                    <div class="info-note">
                        <i class="fas fa-info-circle"></i> 
                        Selecciona las áreas y carpetas a las que este usuario tendrá acceso en Capacitaciones.
                    </div>
                </div>
            </div>
            
            <form method="POST" action="">
                <!-- ÁREAS -->
                <div class="seccion">
                    <h3><i class="fas fa-building"></i> Áreas</h3>
                    <div class="permisos-grid">
                        <?php foreach ($areas as $area): ?>
                            <div class="permiso-item <?php echo isset($permisos_asignados[$area['id']]) ? 'checked' : ''; ?>">
                                <input type="checkbox" 
                                       name="permisos[]" 
                                       value="<?php echo $area['id']; ?>"
                                       id="area_<?php echo $area['id']; ?>"
                                       <?php echo isset($permisos_asignados[$area['id']]) ? 'checked' : ''; ?>
                                       onchange="this.parentElement.classList.toggle('checked')">
                                <label for="area_<?php echo $area['id']; ?>">
                                    <?php echo htmlspecialchars($area['nombre']); ?>
                                    <span class="sub-text">(Área)</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- CARPETAS -->
                <div class="seccion">
                    <h3><i class="fas fa-folder"></i> Carpetas</h3>
                    <div class="permisos-grid">
                        <?php foreach ($carpetas as $carpeta): ?>
                            <div class="permiso-item <?php echo isset($permisos_asignados[$carpeta['id']]) ? 'checked' : ''; ?>">
                                <input type="checkbox" 
                                       name="permisos[]" 
                                       value="<?php echo $carpeta['id']; ?>"
                                       id="carpeta_<?php echo $carpeta['id']; ?>"
                                       <?php echo isset($permisos_asignados[$carpeta['id']]) ? 'checked' : ''; ?>
                                       onchange="this.parentElement.classList.toggle('checked')">
                                <label for="carpeta_<?php echo $carpeta['id']; ?>">
                                    <?php echo htmlspecialchars($carpeta['padre_nombre']); ?> → <?php echo htmlspecialchars($carpeta['nombre']); ?>
                                    <span class="sub-text">(Carpeta)</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Permisos
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Seleccionar/Deseleccionar todos los permisos
        document.querySelectorAll('.seccion').forEach(seccion => {
            const checkboxes = seccion.querySelectorAll('input[type="checkbox"]');
            if (checkboxes.length > 0) {
                const selectAllDiv = document.createElement('div');
                selectAllDiv.className = 'select-all';
                selectAllDiv.innerHTML = `
                    <input type="checkbox" id="select_all_${seccion.querySelector('h3').textContent.trim().replace(/\s/g, '_')}">
                    <label for="select_all_${seccion.querySelector('h3').textContent.trim().replace(/\s/g, '_')}">
                        <i class="fas fa-check-double"></i> Seleccionar todas
                    </label>
                    <span class="total-items">${checkboxes.length} elementos</span>
                `;
                seccion.insertBefore(selectAllDiv, seccion.querySelector('.permisos-grid'));
                
                const selectAll = selectAllDiv.querySelector('input[type="checkbox"]');
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                        checkbox.parentElement.classList.toggle('checked', this.checked);
                    });
                });
            }
        });
    </script>
</body>
</html>