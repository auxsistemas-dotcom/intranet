<?php
// admin/capacitaciones/documentos.php - Gestionar documentos de capacitación
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
        $_SESSION['error'] = "❌ No tienes permiso para gestionar documentos de capacitación";
        header('Location: ../index.php');
        exit();
    }
} 
// Usuario normal: sin acceso
else {
    header('Location: ../index.php');
    exit();
}

// ✅ OBLIGATORIO: Filtrar por categoría
$categoria_filtro = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : 0;

// ✅ Si no hay categoría, redirigir a index
if ($categoria_filtro == 0) {
    $_SESSION['error'] = "❌ Debes seleccionar una carpeta para ver sus documentos";
    header('Location: index.php');
    exit();
}

// Obtener la ruta completa de la categoría
$ruta_parts = [];
$current_id = $categoria_filtro;

while ($current_id) {
    $stmt = $pdo->prepare("SELECT id, nombre, padre_id, tipo FROM categorias_capacitaciones WHERE id = ?");
    $stmt->execute([$current_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $ruta_parts[] = $row['nombre'];
        $current_id = $row['padre_id'];
    } else {
        $current_id = null;
    }
}

if (empty($ruta_parts)) {
    $_SESSION['error'] = "❌ Carpeta no encontrada";
    header('Location: index.php');
    exit();
}

$ruta_parts = array_reverse($ruta_parts);
$ruta_completa = implode(' → ', $ruta_parts);
$categoria_nombre = end($ruta_parts);

// Guardar información de la categoría
$stmt = $pdo->prepare("SELECT * FROM categorias_capacitaciones WHERE id = ?");
$stmt->execute([$categoria_filtro]);
$categoria_info = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Recuperar mensajes de sesión
$mensaje = '';
$error = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ✅ Eliminar documento - MANTENIENDO LA CATEGORÍA
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    $stmt = $pdo->prepare("SELECT url FROM documentos_capacitaciones WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc && $doc['url'] && file_exists('../../' . $doc['url'])) {
        unlink('../../' . $doc['url']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM documentos_capacitaciones WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Documento eliminado correctamente";
    header("Location: documentos.php?categoria_id=" . $categoria_filtro);
    exit();
}

// ✅ Cambiar estado - MANTENIENDO LA CATEGORÍA
if (isset($_GET['cambiar_estado']) && is_numeric($_GET['cambiar_estado'])) {
    $id = $_GET['cambiar_estado'];
    $stmt = $pdo->prepare("UPDATE documentos_capacitaciones SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Estado actualizado correctamente";
    header("Location: documentos.php?categoria_id=" . $categoria_filtro);
    exit();
}

// ✅ Obtener SOLO los documentos de esta categoría
$stmt = $pdo->prepare("
    SELECT d.*, a.nombre as categoria_nombre, p.nombre as padre_nombre
    FROM documentos_capacitaciones d
    LEFT JOIN categorias_capacitaciones a ON d.categoria_id = a.id
    LEFT JOIN categorias_capacitaciones p ON a.padre_id = p.id
    WHERE d.categoria_id = ?
    ORDER BY d.orden ASC, d.titulo ASC
");
$stmt->execute([$categoria_filtro]);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos - <?php echo htmlspecialchars($categoria_nombre); ?> | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== ESTILOS ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #12232b 0%, #173742 100%);
            padding: 0px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { color: white; font-size: 24px; font-weight: 700; }
        .logo small { color: #8aa8b8; font-size: 12px; font-weight: 300; }
        
        .btn-volver {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 8px 18px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
            border-color: #ffc107;
        }
        
        /* ===== INDICADOR DE UBICACIÓN ===== */
        .ubicacion-indicador {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            border-left: 5px solid #ffc107;
        }
        .ubicacion-indicador-icon {
            width: 55px;
            height: 55px;
            background: #fff3cd;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ubicacion-indicador-icon i {
            font-size: 24px;
            color: #f39c12;
        }
        .ubicacion-indicador-info { flex: 1; }
        .ubicacion-indicador-etiqueta {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #7f8c8d;
            display: block;
        }
        .ubicacion-indicador-ruta {
            font-size: 16px;
            color: #2c3e50;
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .ubicacion-indicador-ruta .separador {
            color: #bdc3c7;
        }
        .ubicacion-indicador-ruta .ruta-item {
            background: #e8f0fe;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 13px;
            color: #173742;
        }
        .ubicacion-indicador-ruta .ruta-actual {
            background: #173742;
            color: white;
            padding: 2px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        
        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
            font-size: 18px;
        }
        
        /* ===== FORMULARIO ===== */
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
        .form-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; min-width: 200px; }
        
        .btn-guardar {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-guardar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }
        
        /* ===== TABLA ===== */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #12232b; color: white; font-weight: 600; font-size: 13px; }
        tr:hover { background: #f8f9fa; }
        
        /* ===== BADGES ===== */
        .badge {
            padding: 6px 6px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-activo { background: #d5f5e3; color: #1a7a3a; }
        .badge-inactivo { background: #fadbd8; color: #922b21; }
        .badge-pdf { background: #fadbd8; color: #922b21; }
        .badge-video { background: #d6eaf8; color: #1a5276; }
        .badge-word { background: #d4e6f1; color: #1a4a7a; }
        .badge-excel { background: #d5f5e3; color: #1a7a3a; }
        .badge-link { background: #e8d5f5; color: #6c3483; }
        .badge-imagen { background: #fdebd0; color: #a04000; }
        
        /* ===== BOTONES ACCIÓN ===== */
        .btn-accion {
            padding: 5px 10px;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-accion:hover { transform: translateY(-2px); }
        .btn-editar { background: #3498db; color: white; }
        .btn-eliminar { background: #e74c3c; color: white; }
        .btn-estado { background: #f39c12; color: white; }
        .btn-ver { background: #27ae60; color: white; }
        
        .info-text { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        .mensaje-exito {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
        }
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state i { font-size: 48px; color: #d3d3d3; margin-bottom: 15px; }
        .empty-state h3 { color: #2c3e50; margin-bottom: 5px; }
        
        .categoria-info-box {
            background: #e8f4f8;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .categoria-info-box i {
            color: #173742;
            font-size: 18px;
        }
        .categoria-info-box strong {
            color: #173742;
        }
        .categoria-info-box .badge-categoria {
            background: #173742;
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small>Documentos de Capacitación</small>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver a Áreas
            </a>
        </div>
    </header>

    <div class="container">
        <!-- ===== MENSAJES ===== -->
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ===== INDICADOR DE UBICACIÓN ===== -->
        <div class="ubicacion-indicador">
            <div class="ubicacion-indicador-icon">
                <i class="fas fa-folder-tree"></i>
            </div>
            <div class="ubicacion-indicador-info">
                <span class="ubicacion-indicador-etiqueta">
                    <i class="fas fa-map-pin"></i> Ubicación actual
                </span>
                <div class="ubicacion-indicador-ruta">
                    <?php 
                    $partes = explode(' → ', $ruta_completa);
                    foreach ($partes as $index => $parte): 
                        $es_ultimo = ($index === count($partes) - 1);
                    ?>
                        <?php if (!$es_ultimo): ?>
                            <span class="ruta-item"><?php echo htmlspecialchars($parte); ?></span>
                            <span class="separador"><i class="fas fa-chevron-right" style="font-size: 10px; color: #bdc3c7;"></i></span>
                        <?php else: ?>
                            <span class="ruta-actual"><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($parte); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ===== FORMULARIO AGREGAR DOCUMENTO ===== -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Agregar nuevo documento</h2>
            
            <form action="guardar_documento.php" method="POST" enctype="multipart/form-data">
                <!-- ✅ CAMPO OCULTO con la categoría actual -->
                <input type="hidden" name="categoria_id" value="<?php echo $categoria_filtro; ?>">
                
                <div class="categoria-info-box">
                    <i class="fas fa-folder-open"></i>
                    <span>El documento se guardará en:</span>
                    <strong><?php echo htmlspecialchars($ruta_completa); ?></strong>
                    <span class="badge-categoria">
                        <i class="fas fa-check-circle"></i> Categoría seleccionada
                    </span>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Título</label>
                    <input type="text" name="titulo" required placeholder="Ej: Manual de PHP - Nivel Básico">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" rows="2" placeholder="Breve descripción del documento (opcional)"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-file"></i> Tipo de archivo</label>
                        <select name="tipo_archivo" id="tipo_archivo" required>
                            <option value="pdf">📄 PDF</option>
                            <option value="video">🎥 Video</option>
                            <option value="word">📝 Word</option>
                            <option value="excel">📊 Excel</option>
                            <option value="imagen">🖼️ Imagen</option>
                            <option value="link">🔗 Enlace</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="archivo_field">
                        <label><i class="fas fa-upload"></i> Archivo</label>
                        <input type="file" name="archivo" id="archivo_input">
                        <div class="info-text">Sube el archivo (PDF, Word, Excel, Imagen)</div>
                    </div>
                    
                    <div class="form-group" id="url_field" style="display: none;">
                        <label><i class="fas fa-link"></i> URL / Enlace</label>
                        <input type="text" name="url" placeholder="https://ejemplo.com/documento">
                        <div class="info-text">Pega el enlace del documento o video</div>
                    </div>
                </div>
                
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar documento
                </button>
            </form>
        </div>

        <!-- ===== LISTA DE DOCUMENTOS ===== -->
        <div class="card">
            <h2>
                <i class="fas fa-list"></i> 
                Documentos en <?php echo htmlspecialchars($categoria_nombre); ?>
                <span style="font-size: 14px; font-weight: 400; color: #7f8c8d;">
                    (<?php echo count($documentos); ?>)
                </span>
            </h2>
            
            <?php if (empty($documentos)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No hay documentos en esta carpeta</h3>
                    <p>Agrega el primer documento usando el formulario superior.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="min-width: 150px;">Título</th>
                                <th style="min-width: 150px;">Descripción</th>
                                <th style="width: 100px;">Tipo</th>
                                <th style="width: 100px;">Estado</th>
                                <th style="width: 180px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($doc['titulo']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($doc['descripcion']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $doc['tipo_archivo']; ?>">
                                            <?php echo strtoupper($doc['tipo_archivo']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $doc['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <i class="fas <?php echo $doc['activo'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            <?php echo $doc['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($doc['url'] && filter_var($doc['url'], FILTER_VALIDATE_URL)): ?>
                                            <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank" class="btn-accion btn-ver" title="Ver documento">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- ✅ CORREGIDO: PASAR categoria_id -->
                                        <a href="?cambiar_estado=<?php echo $doc['id']; ?>&categoria_id=<?php echo $categoria_filtro; ?>" class="btn-accion btn-estado" title="Cambiar estado">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                        
                                        <!-- ✅ CORREGIDO: PASAR categoria_id en editar -->
                                        <a href="editar_documento.php?id=<?php echo $doc['id']; ?>&categoria_id=<?php echo $categoria_filtro; ?>" class="btn-accion btn-editar" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- ✅ CORREGIDO: PASAR categoria_id en eliminar -->
                                        <a href="?eliminar=<?php echo $doc['id']; ?>&categoria_id=<?php echo $categoria_filtro; ?>" class="btn-accion btn-eliminar" title="Eliminar" onclick="return confirm('¿Eliminar este documento permanentemente?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== SCRIPT ===== -->
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
                archivoInput.required = true;
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