<?php
// admin/capacitaciones/editar_documento.php - Editar documento de capacitación
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
        $_SESSION['error'] = "❌ No tienes permiso para editar documentos";
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

// Obtener datos del documento
$stmt = $pdo->prepare("SELECT * FROM documentos_capacitaciones WHERE id = ?");
$stmt->execute([$id]);
$documento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$documento) {
    header("Location: documentos.php?error=" . urlencode("❌ Documento no encontrado"));
    exit();
}

// Obtener categorías para el select
$stmt = $pdo->query("
    SELECT a.*, p.nombre as padre_nombre 
    FROM categorias_capacitaciones a 
    LEFT JOIN categorias_capacitaciones p ON a.padre_id = p.id 
    WHERE a.tipo = 'carpeta' AND a.activo = 1 
    ORDER BY p.nombre ASC, a.nombre ASC
");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo_archivo = $_POST['tipo_archivo'] ?? 'pdf';
    $url = trim($_POST['url'] ?? '');
    $orden = intval($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (empty($titulo) || $categoria_id == 0) {
        $error = "❌ El título y la categoría son obligatorios";
    } else {
        try {
            // Procesar archivo si se subió uno nuevo
            $ruta_db = $documento['url'];
            
            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $archivo = $_FILES['archivo'];
                $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                
                // Validar extensión
                $extensiones_permitidas = [
                    'pdf' => ['pdf'],
                    'word' => ['doc', 'docx'],
                    'excel' => ['xls', 'xlsx'],
                    'imagen' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
                ];
                
                if (!in_array($extension, $extensiones_permitidas[$tipo_archivo] ?? ['pdf'])) {
                    $error = "❌ Extensión no permitida para el tipo seleccionado";
                } elseif ($archivo['size'] > 10 * 1024 * 1024) {
                    $error = "❌ El archivo no puede superar los 10MB";
                } else {
                    // Eliminar archivo anterior si existe
                    if ($documento['url'] && file_exists('../../' . $documento['url'])) {
                        unlink('../../' . $documento['url']);
                    }
                    
                    // Crear carpeta si no existe
                    $carpeta = '../../uploads/documentos/capacitaciones/';
                    if (!file_exists($carpeta)) {
                        mkdir($carpeta, 0777, true);
                    }
                    
                    $nombre_archivo = time() . '_' . uniqid() . '.' . $extension;
                    $ruta_destino = $carpeta . $nombre_archivo;
                    $ruta_db = 'uploads/documentos/capacitaciones/' . $nombre_archivo;
                    
                    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                        $error = "❌ Error al subir el archivo";
                    }
                }
            }
            
            if (empty($error)) {
                // Actualizar documento
                $stmt = $pdo->prepare("UPDATE documentos_capacitaciones SET 
                                       categoria_id = ?,
                                       titulo = ?,
                                       descripcion = ?,
                                       tipo_archivo = ?,
                                       url = ?,
                                       orden = ?,
                                       activo = ?
                                       WHERE id = ?");
                $stmt->execute([$categoria_id, $titulo, $descripcion, $tipo_archivo, $ruta_db, $orden, $activo, $id]);
                $mensaje = "✅ Documento actualizado correctamente";
                
                // Recargar datos
                $stmt = $pdo->prepare("SELECT * FROM documentos_capacitaciones WHERE id = ?");
                $stmt->execute([$id]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            }
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
    <title>Editar Documento | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #2c3e50; }
        .container { max-width: 700px; margin: 50px auto; padding: 20px; }
        
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
        
        .card-header .badge-id {
            background: #173742;
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
            border-left: 4px solid #173742;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .info-box i {
            color: #173742;
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
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
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
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-activo {
            background: #d5f5e3;
            color: #1a7a3a;
        }
        
        .badge-inactivo {
            background: #fadbd8;
            color: #922b21;
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
        
        .archivo-actual {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            border: 1px dashed #d3d3d3;
        }
        
        .archivo-actual i {
            color: #173742;
        }
        
        .archivo-actual .filename {
            font-weight: 500;
            color: #12232b;
        }
        
        @media (max-width: 600px) {
            .container { margin: 20px auto; padding: 10px; }
            .card { padding: 20px; }
            .card-header { flex-wrap: wrap; }
            .card-header .badge-id { margin-left: 0; }
            .form-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="documentos.php?categoria_id=<?php echo $documento['categoria_id']; ?>" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver a Documentos
        </a>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-alt"></i>
                <h2>Editar Documento</h2>
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
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Categoría</label>
                    <select name="categoria_id" required>
                        <option value="">Seleccionar categoría</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>" <?php echo $documento['categoria_id'] == $categoria['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['padre_nombre']); ?> → <?php echo htmlspecialchars($categoria['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Título</label>
                    <input type="text" name="titulo" value="<?php echo htmlspecialchars($documento['titulo']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" rows="3"><?php echo htmlspecialchars($documento['descripcion']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-file"></i> Tipo de archivo</label>
                        <select name="tipo_archivo" id="tipo_archivo" required>
                            <option value="pdf" <?php echo $documento['tipo_archivo'] == 'pdf' ? 'selected' : ''; ?>>📄 PDF</option>
                            <option value="video" <?php echo $documento['tipo_archivo'] == 'video' ? 'selected' : ''; ?>>🎥 Video</option>
                            <option value="word" <?php echo $documento['tipo_archivo'] == 'word' ? 'selected' : ''; ?>>📝 Word</option>
                            <option value="excel" <?php echo $documento['tipo_archivo'] == 'excel' ? 'selected' : ''; ?>>📊 Excel</option>
                            <option value="imagen" <?php echo $documento['tipo_archivo'] == 'imagen' ? 'selected' : ''; ?>>🖼️ Imagen</option>
                            <option value="link" <?php echo $documento['tipo_archivo'] == 'link' ? 'selected' : ''; ?>>🔗 Enlace</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sort-numeric-up"></i> Orden</label>
                        <input type="number" name="orden" value="<?php echo $documento['orden']; ?>">
                    </div>
                </div>
                
                <div class="form-group" id="archivo_field">
                    <label><i class="fas fa-upload"></i> Archivo (dejar vacío para mantener el actual)</label>
                    <input type="file" name="archivo" id="archivo_input">
                    <div class="info-text">Sube un nuevo archivo para reemplazar el actual</div>
                </div>
                
                <?php if ($documento['url']): ?>
                    <div class="archivo-actual">
                        <i class="fas fa-paperclip"></i>
                        <span class="label">Archivo actual:</span>
                        <span class="filename">
                            <?php 
                            $nombre_archivo = basename($documento['url']);
                            echo htmlspecialchars($nombre_archivo);
                            ?>
                        </span>
                        <?php if (filter_var($documento['url'], FILTER_VALIDATE_URL)): ?>
                            <a href="<?php echo htmlspecialchars($documento['url']); ?>" target="_blank" style="color: #173742; text-decoration: none; font-size: 13px;">
                                <i class="fas fa-external-link-alt"></i> Ver
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group" id="url_field" style="display: none;">
                    <label><i class="fas fa-link"></i> URL / Enlace</label>
                    <input type="text" name="url" value="<?php echo htmlspecialchars($documento['url']); ?>" placeholder="https://ejemplo.com/documento">
                    <div class="info-text">Pega el enlace del documento o video</div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Estado</label>
                    <select name="activo">
                        <option value="1" <?php echo $documento['activo'] == 1 ? 'selected' : ''; ?>>Activo</option>
                        <option value="0" <?php echo $documento['activo'] == 0 ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                    <div class="info-text">Los documentos inactivos no se muestran en la página principal</div>
                </div>
                
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
    
    <script>
        const tipoSelect = document.getElementById('tipo_archivo');
        const archivoField = document.getElementById('archivo_field');
        const archivoInput = document.getElementById('archivo_input');
        const urlField = document.getElementById('url_field');
        
        function toggleCampos() {
            const tiposConArchivo = ['pdf', 'word', 'excel', 'imagen'];
            const tiposConUrl = ['video', 'link'];
            const valor = tipoSelect.value;
            
            if (tiposConArchivo.includes(valor)) {
                archivoField.style.display = 'block';
                urlField.style.display = 'none';
                archivoInput.required = false;
                document.querySelector('input[name="url"]').required = false;
            } else if (tiposConUrl.includes(valor)) {
                archivoField.style.display = 'none';
                urlField.style.display = 'block';
                archivoInput.required = false;
                document.querySelector('input[name="url"]').required = true;
            }
        }
        
        tipoSelect.addEventListener('change', toggleCampos);
        toggleCampos(); // Ejecutar al cargar
    </script>
</body>
</html>