<?php
// admin/documentos_sig/editar.php - Editar documento SIG
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
        WHERE pu.usuario_id = ? AND m.nombre = 'documentos_sig' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para editar documentos SIG";
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

// ✅ Recuperar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Obtener datos del documento
$stmt = $pdo->prepare("SELECT * FROM documentos_sig WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    $_SESSION['error'] = "❌ Documento no encontrado";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if (empty($titulo)) {
        $error = "❌ El título es obligatorio";
    } else {
        // Verificar si se subió un nuevo archivo
        if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo_pdf'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $extensiones_permitidas = ['pdf', 'xls', 'xlsx'];
            
            if (!in_array($extension, $extensiones_permitidas)) {
                $error = "❌ Formatos permitidos: PDF, XLS, XLSX";
            } elseif ($archivo['size'] > 6 * 1024 * 1024) {
                $error = "❌ El archivo no puede superar los 6MB";
            } else {
                // Eliminar archivo anterior
                if ($item['archivo_url'] && file_exists('../../' . $item['archivo_url'])) {
                    unlink('../../' . $item['archivo_url']);
                }
                
                $carpeta = '../../uploads/documentos/documentos_sig/';
                if (!file_exists($carpeta)) {
                    mkdir($carpeta, 0777, true);
                }
                
                // Mantener la extensión original
                $nombre_archivo = time() . '_' . uniqid() . '.' . $extension;
                $ruta_destino = $carpeta . $nombre_archivo;
                $ruta_db = 'uploads/documentos/documentos_sig/' . $nombre_archivo;
                
                if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                    $stmt = $pdo->prepare("UPDATE documentos_sig SET titulo = ?, descripcion = ?, archivo_url = ? WHERE id = ?");
                    $stmt->execute([$titulo, $descripcion, $ruta_db, $id]);
                    $mensaje = "✅ Documento actualizado correctamente";
                    
                    // Recargar datos
                    $stmt = $pdo->prepare("SELECT * FROM documentos_sig WHERE id = ?");
                    $stmt->execute([$id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "❌ Error al subir el archivo";
                }
            }
        } else {
            // Actualizar sin cambiar archivo
            $stmt = $pdo->prepare("UPDATE documentos_sig SET titulo = ?, descripcion = ? WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $id]);
            $mensaje = "✅ Documento actualizado correctamente";
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM documentos_sig WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// ✅ Guardar mensajes en sesión y redirigir
if ($mensaje) {
    $_SESSION['mensaje'] = $mensaje;
    header("Location: index.php");
    exit();
} elseif ($error) {
    $_SESSION['error'] = $error;
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Documento SIG | INTRANET</title>
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
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        
        .btn-guardar {
            background: linear-gradient(135deg, #173742, #1a4a55);
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
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }
        
        .btn-volver {
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        .mensaje-exito {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
        }
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
        }
        
        .info-text { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        
        .archivo-actual {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px dashed #d3d3d3;
        }
        .archivo-actual i { font-size: 24px; }
        .archivo-actual a { color: #173742; font-weight: 500; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        
        <div class="card">
            <h2><i class="fas fa-edit"></i> Editar Documento SIG</h2>
            
            <?php if ($mensaje): ?>
                <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($item['archivo_url']): ?>
                <div class="archivo-actual">
                    <label>Archivo actual:</label><br>
                    <a href="../../<?php echo $item['archivo_url']; ?>" target="_blank">
                        <?php 
                        $ext = pathinfo($item['archivo_url'], PATHINFO_EXTENSION);
                        if ($ext === 'pdf') {
                            echo '📄 Ver PDF';
                        } elseif ($ext === 'xls' || $ext === 'xlsx') {
                            echo '📊 Ver Excel';
                        } else {
                            echo '📎 Ver Archivo';
                        }
                        ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Título</label>
                    <input type="text" name="titulo" value="<?php echo htmlspecialchars($item['titulo']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="3"><?php echo htmlspecialchars($item['descripcion']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Archivo (opcional, dejar en blanco para mantener)</label>
                    <input type="file" name="archivo_pdf" accept=".pdf,.xls,.xlsx">
                    <div class="info-text">Formatos permitidos: PDF, XLS, XLSX. Máximo 6MB</div>
                </div>
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</body>
</html>