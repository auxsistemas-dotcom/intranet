<?php
// admin/gestionar_pagina/slider/editar.php - Editar slide
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para editar slides";
    header('Location: ../../index.php');
    exit();
}

$id = $_GET['id'] ?? 0;
$error = '';
$mensaje = '';

// Obtener datos del slide
$stmt = $pdo->prepare("SELECT * FROM slider WHERE id = ?");
$stmt->execute([$id]);
$slide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slide) {
    header("Location: index.php?error=" . urlencode("❌ Slide no encontrado"));
    exit();
}

// Configuración de archivos
$carpeta_destino = '../../../uploads/slider/';

// Crear carpeta si no existe
if (!file_exists($carpeta_destino)) {
    mkdir($carpeta_destino, 0777, true);
}

$imagen_nombre = '';
$imagen_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'noticia');
    $enlace = trim($_POST['enlace'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $orden = intval($_POST['orden'] ?? 0);
    $mantener_imagen = isset($_POST['mantener_imagen']) ? 1 : 0;
    
    // Procesar nueva imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $imagen_tmp = $_FILES['imagen']['tmp_name'];
        $imagen_nombre_original = $_FILES['imagen']['name'];
        $imagen_tamano = $_FILES['imagen']['size'];
        
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($imagen_nombre_original, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $extensiones_permitidas)) {
            $imagen_error = "❌ Solo se permiten archivos JPG, PNG, GIF y WEBP";
        } elseif ($imagen_tamano > 5242880) { // 5MB
            $imagen_error = "❌ La imagen no debe superar los 5MB";
        } else {
            // Eliminar imagen anterior si existe y no se mantiene
            if (!$mantener_imagen && !empty($slide['imagen_url'])) {
                $ruta_anterior = '../../../' . $slide['imagen_url'];
                if (file_exists($ruta_anterior)) {
                    unlink($ruta_anterior);
                }
            }
            
            $imagen_nombre = 'slide_' . time() . '_' . uniqid() . '.' . $extension;
            $ruta_completa = $carpeta_destino . $imagen_nombre;
            
            if (!move_uploaded_file($imagen_tmp, $ruta_completa)) {
                $imagen_error = "❌ Error al subir la imagen";
            }
        }
    } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
        $imagen_error = "❌ Error al subir la imagen: " . $_FILES['imagen']['error'];
    }
    
    if (empty($titulo)) {
        $error = "❌ El título es obligatorio";
    } elseif (!empty($imagen_error)) {
        $error = $imagen_error;
    } else {
        try {
            // Construir la consulta
            if (!empty($imagen_nombre)) {
                // Con nueva imagen
                $ruta_imagen = 'uploads/slider/' . $imagen_nombre;
                $stmt = $pdo->prepare("
                    UPDATE slider 
                    SET titulo = ?, descripcion = ?, imagen_url = ?, tipo = ?, enlace = ?, orden = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$titulo, $descripcion, $ruta_imagen, $tipo, $enlace, $orden, $activo, $id]);
            } else {
                // Sin cambiar imagen
                $stmt = $pdo->prepare("
                    UPDATE slider 
                    SET titulo = ?, descripcion = ?, tipo = ?, enlace = ?, orden = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$titulo, $descripcion, $tipo, $enlace, $orden, $activo, $id]);
            }
            
            $_SESSION['mensaje'] = "✅ Slide actualizado correctamente";
            header("Location: index.php");
            exit();
            
        } catch (PDOException $e) {
            $error = "❌ Error al actualizar el slide: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Slide | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 700px; margin: 50px auto; padding: 20px; }

        .header {
            background: #12232b;
            padding: 20px 0;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        .header .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { color: white; font-size: 24px; }
        .logo span { color: #445960; }
        .btn-volver {
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
        }

        .info-slide {
            background: #e8f0fe;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            font-size: 13px;
            color: #2c3e50;
        }
        .info-slide i { color: #173742; margin-right: 8px; }
        .info-slide strong { color: #12232b; }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #173742;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group .file-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .form-group .file-input-wrapper input[type="file"] {
            flex: 1;
            padding: 10px;
            border: 2px dashed #d3d3d3;
            border-radius: 8px;
            cursor: pointer;
            background: #fafafa;
            transition: all 0.3s;
        }
        .form-group .file-input-wrapper input[type="file"]:hover {
            border-color: #173742;
            background: #f0f4f8;
        }
        .form-group .file-info {
            font-size: 12px;
            color: #7f8c8d;
        }
        .form-group .file-info i { color: #173742; }

        .form-group .imagen-actual {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .form-group .imagen-actual img {
            max-height: 60px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        .form-group .imagen-actual .info {
            font-size: 13px;
            color: #7f8c8d;
        }
        .form-group .imagen-actual .info i { color: #173742; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-group input {
            width: auto;
        }

        .btn-guardar {
            background: #173742;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-guardar:hover {
            background: #445960;
            transform: translateY(-2px);
        }

        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .info-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <span>| Editar Slide</span>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fas fa-edit"></i> Editar Slide</h2>

            <div class="info-slide">
                <i class="fas fa-info-circle"></i>
                <strong>ID:</strong> <?php echo $slide['id']; ?> &nbsp;|&nbsp;
                <strong>Creado:</strong> <?php echo date('d/m/Y H:i', strtotime($slide['fecha_creacion'])); ?>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Título *</label>
                    <input type="text" name="titulo" placeholder="Ej: Innovación que Mueve tu Negocio" value="<?php echo htmlspecialchars($slide['titulo']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" placeholder="Escribe la descripción del slide..."><?php echo htmlspecialchars($slide['descripcion']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Imagen Actual</label>
                    <div class="imagen-actual">
                        <?php if (!empty($slide['imagen_url'])): ?>
                            <img src="../../../<?php echo htmlspecialchars($slide['imagen_url']); ?>" alt="Imagen actual">
                            <div class="info">
                                <i class="fas fa-image"></i> 
                                <?php echo basename($slide['imagen_url']); ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">No hay imagen</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Cambiar Imagen</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.gif,.webp">
                        <span class="file-info">
                            <i class="fas fa-info-circle"></i> JPG, PNG, GIF, WEBP (max 5MB)
                        </span>
                    </div>
                    <div style="margin-top: 8px;">
                        <input type="checkbox" name="mantener_imagen" id="mantener_imagen" checked>
                        <label for="mantener_imagen" style="font-size: 12px; color: #7f8c8d;">Mantener imagen actual</label>
                    </div>
                    <div class="info-text">Si seleccionas una nueva imagen, reemplazará la actual</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="noticia" <?php echo $slide['tipo'] == 'noticia' ? 'selected' : ''; ?>>Noticia</option>
                            <option value="promocion" <?php echo $slide['tipo'] == 'promocion' ? 'selected' : ''; ?>>Promoción</option>
                            <option value="aviso" <?php echo $slide['tipo'] == 'aviso' ? 'selected' : ''; ?>>Aviso</option>
                            <option value="evento" <?php echo $slide['tipo'] == 'evento' ? 'selected' : ''; ?>>Evento</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Orden</label>
                        <input type="number" name="orden" value="<?php echo $slide['orden']; ?>" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label>Enlace (opcional)</label>
                    <input type="url" name="enlace" placeholder="https://ejemplo.com" value="<?php echo htmlspecialchars($slide['enlace']); ?>">
                    <div class="info-text">URL a la que redirigirá al hacer clic en el slide</div>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" name="activo" <?php echo $slide['activo'] ? 'checked' : ''; ?>>
                    <label>Publicar slide (activo)</label>
                </div>

                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</body>
</html>