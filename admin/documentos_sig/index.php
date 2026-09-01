<?php
// admin/documentos_sig/index.php - Gestionar Documentos SIG
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar documentos SIG";
    header('Location: ../../index.php');
    exit();
}

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

// ✅ Eliminar documento
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    $stmt = $pdo->prepare("SELECT archivo_url FROM documentos_sig WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc && $doc['archivo_url'] && file_exists('../../' . $doc['archivo_url'])) {
        unlink('../../' . $doc['archivo_url']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM documentos_sig WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Documento eliminado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Cambiar estado
if (isset($_GET['cambiar_estado']) && is_numeric($_GET['cambiar_estado'])) {
    $id = $_GET['cambiar_estado'];
    $stmt = $pdo->prepare("UPDATE documentos_sig SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Estado actualizado correctamente";
    header("Location: index.php");
    exit();
}

// Obtener todos los documentos
$stmt = $pdo->query("SELECT * FROM documentos_sig ORDER BY categoria ASC, titulo ASC");
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para obtener el badge según la extensión
function getBadgeInfo($archivo_url) {
    $extension = strtolower(pathinfo($archivo_url, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':
            return ['clase' => 'badge-pdf', 'icono' => '📄', 'texto' => 'PDF'];
        case 'xls':
            return ['clase' => 'badge-excel', 'icono' => '📊', 'texto' => 'Excel'];
        case 'xlsx':
            return ['clase' => 'badge-excel', 'icono' => '📊', 'texto' => 'Excel'];
        case 'doc':
        case 'docx':
            return ['clase' => 'badge-word', 'icono' => '📝', 'texto' => 'Word'];
        default:
            return ['clase' => 'badge-other', 'icono' => '📎', 'texto' => 'Archivo'];
    }
}

// ✅ Lista de categorías predefinidas
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Documentos SIG | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: linear-gradient(135deg, #12232b 0%, #173742 100%);
            padding: 0px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { color: white; font-size: 24px; font-weight: 700; }
        .logo small { color: #8aa8b8; font-size: 14px; font-weight: 300; }
        
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
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        .form-group textarea { resize: vertical; min-height: 60px; }
        
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
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #12232b; color: white; font-weight: 600; font-size: 13px; }
        tr:hover { background: #f8f9fa; }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-activo { background: #d5f5e3; color: #1a7a3a; }
        .badge-inactivo { background: #fadbd8; color: #922b21; }
        .badge-pdf { background: #fadbd8; color: #922b21; }
        .badge-excel { background: #d5f5e3; color: #1a7a3a; }
        .badge-word { background: #d6eaf8; color: #1a5276; }
        .badge-other { background: #e8f0fe; color: #1a4a7a; }
        
        .badge-categoria {
            background: #e8f0fe;
            color: #173742;
            border: 1px solid #173742;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
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

        .filtro-categoria {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filtro-categoria select {
            padding: 8px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            background: white;
            min-width: 200px;
        }
        .filtro-categoria .btn-filtrar {
            background: #173742;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .filtro-categoria .btn-filtrar:hover {
            background: #445960;
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small>| Documentos SIG</small>
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ===== FORMULARIO AGREGAR DOCUMENTO ===== -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Agregar nuevo documento</h2>
            <form action="guardar.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Título <span class="obligatorio">*</span></label>
                    <input type="text" name="titulo" required placeholder="Ej: Formato de Solicitud">
                </div>
                
                <!-- ✅ CAMPO CATEGORÍA - NUEVO -->
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Categoría <span class="obligatorio">*</span></label>
                    <select name="categoria" required>
                        <option value="">Seleccione una categoría</option>
                        <?php foreach ($categorias as $key => $value): ?>
                            <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" rows="2" placeholder="Breve descripción del documento"></textarea>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file"></i> Archivo <span class="obligatorio">*</span></label>
                    <input type="file" name="archivo" accept=".pdf,.xls,.xlsx,.doc,.docx" required>
                    <div class="info-text">Formatos permitidos: PDF, Excel (XLS, XLSX), Word (DOC, DOCX). Máximo 10MB</div>
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
                Documentos existentes 
                <span style="font-size: 14px; font-weight: 400; color: #7f8c8d;">
                    (<?php echo count($documentos); ?>)
                </span>
            </h2>
            
            <?php if (empty($documentos)): ?>
                <div class="empty-state">
                    <i class="fas fa-file"></i>
                    <h3>No hay documentos registrados</h3>
                    <p>Agrega el primer documento usando el formulario superior.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th style="min-width: 150px;">Título</th>
                                <th style="min-width: 120px;">Categoría</th>
                                <th style="min-width: 150px;">Descripción</th>
                                <th style="width: 100px;">Tipo</th>
                                <th style="width: 100px;">Estado</th>
                                <th style="width: 200px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                                <?php $badge = getBadgeInfo($doc['archivo_url']); ?>
                                <tr>
                                    <td><?php echo $doc['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($doc['titulo']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge-categoria">
                                            <i class="fas fa-tag"></i> 
                                            <?php echo htmlspecialchars($doc['categoria'] ?? 'General'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($doc['descripcion']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $badge['clase']; ?>">
                                            <?php echo $badge['icono']; ?> <?php echo $badge['texto']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $doc['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <i class="fas <?php echo $doc['activo'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            <?php echo $doc['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($doc['archivo_url']): ?>
                                            <a href="../../<?php echo htmlspecialchars($doc['archivo_url']); ?>" target="_blank" class="btn-accion btn-ver" title="Ver documento">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?cambiar_estado=<?php echo $doc['id']; ?>" class="btn-accion btn-estado" title="Cambiar estado">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                        <a href="editar.php?id=<?php echo $doc['id']; ?>" class="btn-accion btn-editar" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?eliminar=<?php echo $doc['id']; ?>" class="btn-accion btn-eliminar" title="Eliminar" onclick="return confirm('¿Eliminar este documento permanentemente?')">
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
</body>
</html>