<?php
// admin/capacitaciones/editar_carpeta.php - Editar carpeta
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
        $_SESSION['error'] = "❌ No tienes permiso para editar carpetas";
        header('Location: ../index.php');
        exit();
    }
} 
// Usuario normal: sin acceso
else {
    header('Location: ../index.php');
    exit();
}

$id = $_GET['id'] ?? 0;
$error = '';
$mensaje = '';

// Obtener datos de la carpeta
$stmt = $pdo->prepare("SELECT * FROM categorias_capacitaciones WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item || $item['tipo'] != 'carpeta') {
    header("Location: index.php?error=" . urlencode("❌ Carpeta no encontrada"));
    exit();
}

// Obtener áreas para el select
$stmt = $pdo->query("SELECT * FROM categorias_capacitaciones WHERE tipo = 'area' AND activo = 1 ORDER BY nombre ASC");
$areas_select = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $padre_id = isset($_POST['padre_id']) && !empty($_POST['padre_id']) ? intval($_POST['padre_id']) : null;
    
    if (empty($nombre)) {
        $error = "❌ El nombre es obligatorio";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE categorias_capacitaciones SET nombre = ?, descripcion = ?, padre_id = ? WHERE id = ?");
            $stmt->execute([$nombre, $descripcion, $padre_id, $id]);
            $mensaje = "✅ Carpeta actualizada correctamente";
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM categorias_capacitaciones WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "❌ Error al actualizar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Carpeta | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #2c3e50; }
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        
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
            color: #f39c12;
            background: #fff3cd;
            padding: 12px;
            border-radius: 12px;
        }
        
        .card-header h2 {
            font-size: 22px;
            color: #12232b;
        }
        
        .card-header .badge-tipo {
            background: #f39c12;
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #f39c12;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .info-box i {
            color: #f39c12;
            font-size: 18px;
        }
        
        .info-box .label {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .info-box .valor {
            font-weight: 600;
            color: #12232b;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #7f8c8d;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f39c12;
            box-shadow: 0 0 0 3px rgba(243,156,18,0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-guardar {
            background: linear-gradient(135deg, #f39c12, #e67e22);
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
            box-shadow: 0 4px 15px rgba(243,156,18,0.3);
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
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .mensaje-exito i {
            color: #27ae60;
            font-size: 18px;
        }
        
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .mensaje-error i {
            color: #e74c3c;
            font-size: 18px;
        }
        
        .info-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .tipo-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tipo-area {
            background: #173742;
            color: white;
        }
        
        .tipo-carpeta {
            background: #f39c12;
            color: white;
        }
        
        @media (max-width: 600px) {
            .container { margin: 20px auto; padding: 10px; }
            .card { padding: 20px; }
            .card-header { flex-wrap: wrap; }
            .card-header .badge-tipo { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver a Áreas
        </a>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-folder"></i>
                <h2>Editar Carpeta</h2>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje-exito">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mensaje-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nombre</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($item['nombre']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" rows="4"><?php echo htmlspecialchars($item['descripcion']); ?></textarea>
                    <div class="info-text">Describe brevemente el propósito de esta carpeta</div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-level-up-alt"></i> Área padre</label>
                    <select name="padre_id">
                        <option value="">Seleccionar área</option>
                        <?php foreach ($areas_select as $area): ?>
                            <option value="<?php echo $area['id']; ?>" <?php echo $item['padre_id'] == $area['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="info-text">Cambia el área a la que pertenece esta carpeta</div>
                </div>
                
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</body>
</html>