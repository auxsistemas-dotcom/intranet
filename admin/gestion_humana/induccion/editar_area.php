<?php
// admin/gestion_humana/induccion/editar_area.php - Editar área
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

$id = $_GET['id'] ?? 0;
$error = '';
$mensaje = '';

// Obtener datos del área
$stmt = $pdo->prepare("SELECT * FROM categorias_gestion_humana WHERE id = ? AND tipo = 'area'");
$stmt->execute([$id]);
$area = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$area) {
    $_SESSION['error'] = "❌ Área no encontrada";
    header("Location: index.php");
    exit();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $orden = intval($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (empty($nombre)) {
        $error = "❌ El nombre es obligatorio";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE categorias_gestion_humana 
                SET nombre = ?, descripcion = ?, orden = ?, activo = ? 
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $descripcion, $orden, $activo, $id]);
            
            $_SESSION['mensaje'] = "✅ Área actualizada correctamente";
            header("Location: index.php");
            exit();
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
    <title>Editar Área | INTRANET</title>
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
        .card h2 { margin-bottom: 20px; border-left: 4px solid #173742; padding-left: 15px; }
        
        .info-area {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #173742;
        }
        .info-area p { margin: 5px 0; font-size: 14px; }
        .info-area strong { color: #173742; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        
        .btn-guardar {
            background: #173742;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-guardar:hover { background: #445960; transform: translateY(-2px); }
        
        .btn-volver {
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .btn-volver:hover { background: #ffc107; color: #12232b; transform: translateY(-2px); }
        
        .mensaje-exito { background: #d5f5e3; color: #1a7a3a; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .mensaje-error { background: #fadbd8; color: #922b21; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        
        <div class="card">
            <h2><i class="fas fa-edit"></i> Editar Área</h2>
            
            <?php if ($mensaje): ?>
                <div class="mensaje-exito"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mensaje-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nombre del área</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($area['nombre']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="4"><?php echo htmlspecialchars($area['descripcion']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Orden</label>
                    <input type="number" name="orden" value="<?php echo $area['orden']; ?>">
                    <div class="info-text" style="font-size: 12px; color: #7f8c8d; margin-top: 5px;">
                        Los números más bajos aparecerán primero
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="activo" <?php echo $area['activo'] ? 'checked' : ''; ?>>
                    <label>Área activa</label>
                </div>
                
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</body>
</html>