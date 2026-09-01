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

// ============================================
// CONFIGURACIÓN DE ARCHIVOS
// ============================================

$extensiones_permitidas = [
    'pdf'  => 'PDF',
    'doc'  => 'Word',
    'docx' => 'Word',
    'xls'  => 'Excel',
    'xlsx' => 'Excel'
];

$max_size = 10 * 1024 * 1024; // 10MB

// ============================================
// LISTA DE CATEGORÍAS
// ============================================
$categorias = [
    'Administracion De La Infraestructura' => 'Administracion De La Infraestructura',
    'Gestion Contable Y Financiera' => 'Gestion Contable Y Financiera',
    'Gestion De La Relacion Con El Cliente' => 'Gestion De La Relacion Con El Cliente',
    'Gestion Del Talento Humano' => 'Gestion Del Talento Humano',
    'Gestion Logistica' => 'Gestion Logistica',
    'Planeacion Y Seguimiento Organizacional' => 'Planeacion Y Seguimiento Organizacional',
    'Posventa' => 'Posventa',
    'Venta' => 'Venta',
];

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
    $categoria = trim($_POST['categoria'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    // ============================================
    // VALIDACIONES
    // ============================================
    if (empty($titulo)) {
        $error = "❌ El título es obligatorio";
    } elseif (empty($categoria)) {
        $error = "❌ Debes seleccionar una categoría";
    } elseif (!in_array($categoria, array_keys($categorias))) {
        $error = "❌ Categoría no válida";
    } else {
        // Verificar si se subió un nuevo archivo
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $nombre_original = $archivo['name'];
            $tamano = $archivo['size'];
            
            // Validar extensión
            if (!array_key_exists($extension, $extensiones_permitidas)) {
                $error = "❌ Formato no permitido. Usa: PDF, Word (.doc, .docx) o Excel (.xls, .xlsx)";
            } 
            // Validar tamaño
            elseif ($tamano > $max_size) {
                $error = "❌ El archivo no puede superar los 10MB";
            } 
            else {
                // Eliminar archivo anterior
                if ($item['archivo_url'] && file_exists('../../' . $item['archivo_url'])) {
                    unlink('../../' . $item['archivo_url']);
                }
                
                $carpeta = '../../uploads/documentos/documentos_sig/';
                if (!file_exists($carpeta)) {
                    mkdir($carpeta, 0777, true);
                }
                
                // Generar nombre único con la extensión original
                $nombre_archivo = 'sig_' . time() . '_' . uniqid() . '.' . $extension;
                $ruta_destino = $carpeta . $nombre_archivo;
                $ruta_db = 'uploads/documentos/documentos_sig/' . $nombre_archivo;
                
                if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                    $stmt = $pdo->prepare("
                        UPDATE documentos_sig 
                        SET titulo = ?, 
                            categoria = ?,
                            descripcion = ?, 
                            archivo_url = ?,
                            tipo_archivo = ?,
                            nombre_original = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $titulo, 
                        $categoria,
                        $descripcion, 
                        $ruta_db,
                        $extensiones_permitidas[$extension],
                        $nombre_original,
                        $id
                    ]);
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
            // Actualizar sin cambiar archivo (título, categoría y descripción)
            $stmt = $pdo->prepare("UPDATE documentos_sig SET titulo = ?, categoria = ?, descripcion = ? WHERE id = ?");
            $stmt->execute([$titulo, $categoria, $descripcion, $id]);
            $mensaje = "✅ Documento actualizado correctamente";
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM documentos_sig WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// ============================================
// OBTENER ICONO SEGÚN TIPO DE ARCHIVO
// ============================================
function getIconoArchivo($archivo_url) {
    $icono = 'fa-file';
    $color = '#3498db';
    $texto = 'Documento';
    
    if (strpos($archivo_url, '.pdf') !== false) {
        $icono = 'fa-file-pdf';
        $color = '#e74c3c';
        $texto = 'PDF';
    } elseif (strpos($archivo_url, '.doc') !== false || strpos($archivo_url, '.docx') !== false) {
        $icono = 'fa-file-word';
        $color = '#2980b9';
        $texto = 'Word';
    } elseif (strpos($archivo_url, '.xls') !== false || strpos($archivo_url, '.xlsx') !== false) {
        $icono = 'fa-file-excel';
        $color = '#27ae60';
        $texto = 'Excel';
    }
    
    return ['icono' => $icono, 'color' => $color, 'texto' => $texto];
}

$info_archivo = getIconoArchivo($item['archivo_url'] ?? '');
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
        .form-group label .obligatorio { color: #e74c3c; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
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
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-text { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        
        .archivo-actual {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px dashed #d3d3d3;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .archivo-actual .info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .archivo-actual .info i { 
            font-size: 28px; 
        }
        .archivo-actual .info .nombre {
            font-weight: 500;
            font-size: 14px;
        }
        .archivo-actual .info .tipo {
            font-size: 12px;
            color: #7f8c8d;
        }
        .archivo-actual .btn-ver {
            background: #173742;
            color: white;
            padding: 6px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .archivo-actual .btn-ver:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        .badge-tipo {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .text-muted { color: #7f8c8d; font-size: 13px; }
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
            
            <!-- Mostrar archivo actual -->
            <?php if ($item['archivo_url']): ?>
                <div class="archivo-actual">
                    <div class="info">
                        <i class="fas <?php echo $info_archivo['icono']; ?>" style="color: <?php echo $info_archivo['color']; ?>;"></i>
                        <div>
                            <div class="nombre"><?php echo htmlspecialchars($item['nombre_original'] ?? basename($item['archivo_url'])); ?></div>
                            <div class="tipo">
                                <span class="badge-tipo" style="background: <?php echo $info_archivo['color']; ?>20; color: <?php echo $info_archivo['color']; ?>; border: 1px solid <?php echo $info_archivo['color']; ?>;">
                                    <i class="fas <?php echo $info_archivo['icono']; ?>"></i> <?php echo $info_archivo['texto']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <a href="../../<?php echo $item['archivo_url']; ?>" target="_blank" class="btn-ver">
                        <i class="fas fa-eye"></i> Ver
                    </a>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Título <span class="obligatorio">*</span></label>
                    <input type="text" name="titulo" value="<?php echo htmlspecialchars($item['titulo']); ?>" required placeholder="Ej: Formato de Solicitud">
                </div>
                
                <!-- ✅ CAMPO CATEGORÍA -->
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Categoría <span class="obligatorio">*</span></label>
                    <select name="categoria" required>
                        <option value="">Seleccione una categoría</option>
                        <?php foreach ($categorias as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($item['categoria'] == $key) ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" rows="3" placeholder="Breve descripción del documento"><?php echo htmlspecialchars($item['descripcion']); ?></textarea>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file"></i> Nuevo archivo (opcional)</label>
                    <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx">
                    <div class="info-text">
                        <i class="fas fa-info-circle"></i> 
                        Formatos permitidos: PDF, Word (.doc, .docx), Excel (.xls, .xlsx). Máximo 10MB<br>
                        <span class="text-muted">Dejar vacío para mantener el archivo actual.</span>
                    </div>
                </div>
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</body>
</html>