<?php
// admin/gestion_humana/induccion/editar_carpeta.php - Editar carpeta
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

// Obtener datos de la carpeta
$stmt = $pdo->prepare("SELECT * FROM categorias_gestion_humana WHERE id = ? AND tipo = 'carpeta'");
$stmt->execute([$id]);
$carpeta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$carpeta) {
    $_SESSION['error'] = "❌ Carpeta no encontrada";
    header("Location: index.php");
    exit();
}

// Obtener áreas para el select
$stmt = $pdo->query("SELECT * FROM categorias_gestion_humana WHERE tipo = 'area' AND activo = 1 ORDER BY nombre");
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $padre_id = intval($_POST['padre_id'] ?? 0);
    $orden = intval($_POST['orden'] ?? 0);
    $quiz_url = trim($_POST['quiz_url'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (empty($nombre) || $padre_id == 0) {
        $error = "❌ El nombre y el área padre son obligatorios";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE categorias_gestion_humana 
                SET nombre = ?, descripcion = ?, padre_id = ?, orden = ?, quiz_url = ?, activo = ? 
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $descripcion, $padre_id, $orden, $quiz_url, $activo, $id]);
            
            $_SESSION['mensaje'] = "✅ Carpeta actualizada correctamente";
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
        .card h2 { margin-bottom: 20px; border-left: 4px solid #173742; padding-left: 15px; }
        
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
        .info-text { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
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
            <h2><i class="fas fa-edit"></i> Editar Carpeta</h2>
            
            <?php if ($mensaje): ?>
                <div class="mensaje-exito"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mensaje-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nombre de la carpeta</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($carpeta['nombre']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="3"><?php echo htmlspecialchars($carpeta['descripcion']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Área padre</label>
                    <select name="padre_id" required>
                        <option value="">Seleccionar área</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo $area['id']; ?>" <?php echo $carpeta['padre_id'] == $area['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Orden</label>
                    <input type="number" name="orden" value="<?php echo $carpeta['orden']; ?>">
                </div>
                
                <!-- ✅ CAMPO QUIZ URL -->
                <div class="form-group">
                    <label><i class="fas fa-link"></i> URL del Quiz</label>
                    <input type="url" name="quiz_url" value="<?php echo htmlspecialchars($carpeta['quiz_url']); ?>" 
                           placeholder="https://docs.google.com/forms/d/...">
                    <div class="info-text">
                        <i class="fas fa-info-circle"></i> 
                        Si se completa, aparecerá un botón "Quiz" al completar los documentos de esta carpeta.
                        <br>Ejemplo: https://docs.google.com/forms/d/XXXXXXXXXX/viewform
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="activo" <?php echo $carpeta['activo'] ? 'checked' : ''; ?>>
                    <label>Carpeta activa</label>
                </div>
                
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</body>
</html>