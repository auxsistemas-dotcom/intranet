<?php
// admin/gestionar_pagina/anuncios/crear.php - Crear nuevo anuncio
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para crear anuncios";
    header('Location: ../../index.php');
    exit();
}

$error = '';
$mensaje = '';

// Obtener datos del usuario actual
$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];

// ============================================
// ✅ VERIFICAR SI YA HAY UN ANUNCIO ACTIVO
// ============================================
$stmt = $pdo->prepare("SELECT id, titulo FROM anuncios WHERE activo = 1 LIMIT 1");
$stmt->execute();
$anuncio_activo = $stmt->fetch(PDO::FETCH_ASSOC);
$hay_anuncio_activo = $anuncio_activo ? true : false;

// Configuración de archivos
$carpeta_destino = '../../../uploads/anuncios/';
$archivo_nombre = '';
$archivo_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ VERIFICAR NUEVAMENTE ANTES DE GUARDAR
    $stmt = $pdo->prepare("SELECT id, titulo FROM anuncios WHERE activo = 1 LIMIT 1");
    $stmt->execute();
    $activo_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($activo_existente) {
        $error = "❌ Ya hay un anuncio activo: <strong>\"" . htmlspecialchars($activo_existente['titulo']) . "\"</strong>. Debes desactivarlo o eliminarlo antes de crear uno nuevo.";
    } else {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Procesar archivo
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivo_tmp = $_FILES['archivo']['tmp_name'];
            $archivo_nombre_original = $_FILES['archivo']['name'];
            $archivo_tipo = $_FILES['archivo']['type'];
            $archivo_tamano = $_FILES['archivo']['size'];
            
            // Validar tipo de archivo
            $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower(pathinfo($archivo_nombre_original, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $extensiones_permitidas)) {
                $archivo_error = "❌ Solo se permiten archivos PDF, JPG, PNG, GIF y WEBP";
            } elseif ($archivo_tamano > 6242880) { // 6MB
                $archivo_error = "❌ El archivo no debe superar los 6MB";
            } else {
                // Generar nombre único
                $archivo_nombre = 'anuncio_' . time() . '_' . uniqid() . '.' . $extension;
                $ruta_completa = $carpeta_destino . $archivo_nombre;
                
                // Mover archivo
                if (!move_uploaded_file($archivo_tmp, $ruta_completa)) {
                    $archivo_error = "❌ Error al subir el archivo";
                }
            }
        } elseif (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $archivo_error = "❌ Error al subir el archivo: " . $_FILES['archivo']['error'];
        }
        
        if (empty($titulo)) {
            $error = "❌ El título es obligatorio";
        } elseif (empty($descripcion)) {
            $error = "❌ La descripción es obligatoria";
        } elseif (!empty($archivo_error)) {
            $error = $archivo_error;
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO anuncios (titulo, descripcion, archivo, activo, creado_por) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$titulo, $descripcion, $archivo_nombre, $activo, $usuario_id]);
                
                $_SESSION['mensaje'] = "✅ Anuncio creado correctamente";
                header("Location: index.php");
                exit();
                
            } catch (PDOException $e) {
                $error = "❌ Error al crear el anuncio: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Anuncio | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 700px; margin: 50px auto; padding: 20px; }

        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            padding: 10px 0;
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

        /* ===== CARD ===== */
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

        /* ===== AVISO DE ANUNCIO ACTIVO ===== */
        .aviso-activo {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            color: #856404;
        }
        .aviso-activo i {
            font-size: 20px;
            margin-right: 10px;
        }
        .aviso-activo strong {
            color: #856404;
        }
        .aviso-activo .btn-desactivar {
            display: inline-block;
            margin-top: 10px;
            background: #e67e22;
            color: white;
            padding: 5px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .aviso-activo .btn-desactivar:hover {
            background: #d35400;
            transform: translateY(-2px);
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #173742;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
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
        .btn-guardar:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
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
                <small style="color: #8aa8b8;">| Crear Anuncio</small>
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
            <h2><i class="fas fa-plus-circle"></i> Nuevo Anuncio</h2>

            <?php if ($hay_anuncio_activo): ?>
                <div class="aviso-activo">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Ya hay un anuncio activo:</strong> "<?php echo htmlspecialchars($anuncio_activo['titulo']); ?>"
                    <br>
                    <small>Debes desactivarlo o eliminarlo antes de crear uno nuevo.</small>
                    <br>
                    <a href="index.php" class="btn-desactivar">
                        <i class="fas fa-arrow-left"></i> Ir a la lista de anuncios
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Título *</label>
                    <input type="text" name="titulo" placeholder="Ej: Bienvenida 2024" value="<?php echo htmlspecialchars($titulo ?? ''); ?>" required <?php echo $hay_anuncio_activo ? 'disabled' : ''; ?>>
                </div>

                <div class="form-group">
                    <label>Descripción *</label>
                    <textarea name="descripcion" placeholder="Escribe la descripción del anuncio..." required <?php echo $hay_anuncio_activo ? 'disabled' : ''; ?>><?php echo htmlspecialchars($descripcion ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Adjuntar Archivo (PDF o Imagen)</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" <?php echo $hay_anuncio_activo ? 'disabled' : ''; ?>>
                        <span class="file-info">
                            <i class="fas fa-info-circle"></i> PDF, JPG, PNG, GIF, WEBP (max 6MB)
                        </span>
                    </div>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" name="activo" <?php echo ($activo ?? 1) ? 'checked' : ''; ?> <?php echo $hay_anuncio_activo ? 'disabled' : ''; ?>>
                    <label>Publicar anuncio (activo)</label>
                </div>

                <button type="submit" class="btn-guardar" <?php echo $hay_anuncio_activo ? 'disabled' : ''; ?>>
                    <i class="fas fa-save"></i> Crear Anuncio
                </button>
            </form>
        </div>
    </div>
</body>
</html>