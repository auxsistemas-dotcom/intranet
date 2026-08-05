<?php
// admin/permisos/asignar.php - Asignar módulos a un usuario
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores (rol_id = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

// Solo administradores pueden asignar permisos
if ($rol_usuario != 1) {
    $_SESSION['error'] = "❌ No tienes permiso para asignar permisos";
    header('Location: ../index.php');
    exit();
}

$id_usuario = $_GET['id'] ?? 0;
$error = '';
$mensaje = '';

// Verificar que el usuario existe
$stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre 
                        FROM usuarios u
                        LEFT JOIN roles r ON u.rol_id = r.id
                        WHERE u.id = ?");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header("Location: index.php?error=" . urlencode("❌ Usuario no encontrado"));
    exit();
}

// No permitir modificar permisos del propio admin
if ($id_usuario == $_SESSION['usuario_id']) {
    header("Location: index.php?error=" . urlencode("❌ No puedes modificar tus propios permisos"));
    exit();
}

// Si es administrador, redirigir (acceso total)
if ($usuario['rol_id'] == 1) {
    header("Location: index.php?error=" . urlencode("❌ Los administradores tienen acceso total por defecto"));
    exit();
}

// Procesar actualización de permisos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Eliminar todos los permisos actuales del usuario
        $stmt_delete = $pdo->prepare("DELETE FROM permisos_usuarios WHERE usuario_id = ?");
        $stmt_delete->execute([$id_usuario]);
        
        // Insertar nuevos permisos
        if (isset($_POST['modulos']) && is_array($_POST['modulos'])) {
            $modulos_seleccionados = $_POST['modulos'];
            
            foreach ($modulos_seleccionados as $modulo_id) {
                $stmt_insert = $pdo->prepare("INSERT INTO permisos_usuarios (usuario_id, modulo_id) VALUES (?, ?)");
                $stmt_insert->execute([$id_usuario, $modulo_id]);
            }
            $mensaje = "✅ Permisos actualizados correctamente";
        } else {
            $mensaje = "✅ Permisos eliminados correctamente (usuario sin acceso a módulos)";
        }
        
        // Redirigir a index con mensaje de éxito
        $_SESSION['mensaje'] = $mensaje;
        header("Location: index.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Error al guardar los permisos: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Obtener todos los módulos disponibles
$modulos = $pdo->query("SELECT * FROM modulos WHERE activo = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Obtener los módulos que ya tiene asignados el usuario
$stmt_permisos = $pdo->prepare("SELECT modulo_id FROM permisos_usuarios WHERE usuario_id = ?");
$stmt_permisos->execute([$id_usuario]);
$permisos_actuales = $stmt_permisos->fetchAll(PDO::FETCH_COLUMN);

$modulos_asignados = array_flip($permisos_actuales);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Permisos | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f2f5;
            color: #2c3e50;
        }
        
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        
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
        
        .card-header h2 {
            font-size: 22px;
            color: #12232b;
        }
        
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
        
        .user-info-grid p {
            margin: 3px 0;
            font-size: 14px;
        }
        
        .user-info-grid strong {
            color: #12232b;
        }
        
        .user-info .info-note {
            grid-column: 1 / -1;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #d3d3d3;
            color: #7f8c8d;
            font-size: 12px;
        }
        
        .user-info .info-note i {
            color: #f39c12;
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
        
        .select-all .total-modulos {
            margin-left: auto;
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .modulo-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #fafafa;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .modulo-item:hover {
            background: #f0f4f8;
            border-color: #173742;
            transform: translateY(-1px);
        }
        
        .modulo-item.checked {
            background: #e8f0fe;
            border-color: #173742;
        }
        
        .modulo-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #173742;
            flex-shrink: 0;
        }
        
        .modulo-item label {
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .modulo-item .modulo-icon {
            color: #7f8c8d;
            font-size: 14px;
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
            background: linear-gradient(135deg, #1a4a55, #173742);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }
        
        .btn-guardar i {
            font-size: 18px;
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
        
        .btn-volver i {
            font-size: 14px;
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
        
        .mensaje-exito i {
            margin-right: 10px;
            color: #27ae60;
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
        
        .mensaje-error i {
            margin-right: 10px;
            color: #e74c3c;
        }
        
        .empty-modulos {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
        }
        
        .empty-modulos i {
            font-size: 40px;
            color: #d3d3d3;
            margin-bottom: 10px;
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 600px) {
            .user-info-grid {
                grid-template-columns: 1fr;
            }
            
            .modulos-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .card-header {
                flex-wrap: wrap;
            }
            
            .card-header .badge-usuario {
                margin-left: 0;
            }
            
            .container {
                padding: 10px;
                margin: 20px auto;
            }
            
            .card {
                padding: 20px;
            }
        }
        
        @media (max-width: 400px) {
            .modulos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver a Permisos
        </a>
        
        <div class="card">
            <!-- ===== CARD HEADER ===== -->
            <div class="card-header">
                <i class="fas fa-user-cog"></i>
                <h2>Asignar Permisos</h2>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje-exito">
                    <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mensaje-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- ===== USER INFO ===== -->
            <div class="user-info">
                <div class="user-info-grid">
                    <p><strong>👤 Usuario:</strong> <?php echo htmlspecialchars($usuario['usuario']); ?></p>
                    <p><strong>📛 Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre_completo']); ?></p>
                    <p><strong>🔑 Rol:</strong> <?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Sin rol'); ?></p>
                    <p><strong>📊 Módulos asignados:</strong> <?php echo count($permisos_actuales); ?></p>
                    <div class="info-note">
                        <i class="fas fa-info-circle"></i> 
                        Selecciona los módulos a los que este supervisor tendrá acceso en el panel.
                    </div>
                </div>
            </div>
            
            <!-- ===== FORMULARIO ===== -->
            <?php if (empty($modulos)): ?>
                <div class="empty-modulos">
                    <i class="fas fa-folder-open"></i>
                    <p>No hay módulos disponibles en el sistema.</p>
                    <p style="font-size: 13px; color: #bdc3c7;">Contacta al administrador para crear módulos.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <!-- Seleccionar todos -->
                    <div class="select-all">
                        <input type="checkbox" id="seleccionar_todos" 
                               <?php echo count($permisos_actuales) == count($modulos) ? 'checked' : ''; ?>>
                        <label for="seleccionar_todos">
                            <i class="fas fa-check-double"></i> Seleccionar todos
                        </label>
                        <span class="total-modulos">
                            <i class="fas fa-th-list"></i> <?php echo count($modulos); ?> módulos disponibles
                        </span>
                    </div>
                    
                    <!-- Módulos -->
                    <div class="modulos-grid">
                        <?php foreach ($modulos as $modulo): ?>
                            <?php $checked = isset($modulos_asignados[$modulo['id']]); ?>
                            <div class="modulo-item <?php echo $checked ? 'checked' : ''; ?>">
                                <input type="checkbox" 
                                       name="modulos[]" 
                                       value="<?php echo $modulo['id']; ?>"
                                       id="modulo_<?php echo $modulo['id']; ?>"
                                       <?php echo $checked ? 'checked' : ''; ?>
                                       onchange="this.parentElement.classList.toggle('checked')">
                                <label for="modulo_<?php echo $modulo['id']; ?>">
                                    <?php echo htmlspecialchars(ucfirst($modulo['nombre'])); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Botón Guardar -->
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> Guardar Permisos
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Seleccionar/Deseleccionar todos los módulos
        document.getElementById('seleccionar_todos').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="modulos[]"]');
            const items = document.querySelectorAll('.modulo-item');
            checkboxes.forEach((checkbox, index) => {
                checkbox.checked = this.checked;
                if (items[index]) {
                    items[index].classList.toggle('checked', this.checked);
                }
            });
        });
    </script>
</body>
</html>