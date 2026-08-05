<?php
// admin/logistica/index.php - Gestionar archivos de Logística
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
        WHERE pu.usuario_id = ? AND m.nombre = 'logistica' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para gestionar logística";
        header('Location: ../index.php');
        exit();
    }
} 
// Usuario normal: sin acceso
else {
    header('Location: ../index.php');
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

// ✅ Eliminar archivo - CON REDIRECCIÓN
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    $stmt = $pdo->prepare("SELECT archivo_url FROM logistica WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc && $doc['archivo_url'] && file_exists('../../' . $doc['archivo_url'])) {
        unlink('../../' . $doc['archivo_url']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM logistica WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Archivo eliminado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Cambiar estado - CON REDIRECCIÓN
if (isset($_GET['cambiar_estado']) && is_numeric($_GET['cambiar_estado'])) {
    $id = $_GET['cambiar_estado'];
    $stmt = $pdo->prepare("UPDATE logistica SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Estado actualizado correctamente";
    header("Location: index.php");
    exit();
}

// Obtener todos los archivos
$stmt = $pdo->query("SELECT * FROM logistica ORDER BY titulo ASC");
$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener marcas activas
$stmt = $pdo->query("SELECT marca FROM logistica WHERE activo = 1 GROUP BY marca");
$marcas_activas = $stmt->fetchAll(PDO::FETCH_COLUMN);

$marcas = ['kia' => 'KIA', 'honda' => 'Honda', 'faw' => 'FAW', 'taxis' => 'Taxis', 'inventario' => 'Inventario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Logística | INTRANET</title>
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
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
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
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #f0f0f0; }
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
        .badge-excel { background: #d5f5e3; color: #1a7a3a; }
        
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
        
        .alert-info {
            background: #e8f4f8;
            color: #173742;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #173742;
        }
        .alert-info i { color: #173742; }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small>| Logística</small>
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

        <!-- ===== FORMULARIO AGREGAR ARCHIVO ===== -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Subir nuevo archivo Excel</h2>
            
            <?php if (!empty($marcas_activas)): ?>
                <div class="alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Importante:</strong> Solo puede haber <strong>un archivo activo por marca</strong>. 
                    Las marcas con archivo activo están marcadas con ✅ en la lista.
                    Debes <strong>eliminar</strong> o <strong>inactivar</strong> el archivo actual antes de subir uno nuevo.
                </div>
            <?php endif; ?>

            <form action="guardar.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Marca / Categoría</label>
                    <select name="marca" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($marcas as $key => $nombre): ?>
                            <?php 
                            $tiene_activo = in_array($key, $marcas_activas);
                            ?>
                            <option value="<?php echo $key; ?>" <?php echo $tiene_activo ? 'disabled style="color:#999;"' : ''; ?>>
                                <?php echo $nombre; ?>
                                <?php echo $tiene_activo ? '✅ (tiene archivo activo)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($marcas_activas)): ?>
                        <div class="info-text" style="color: #e67e22;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Las marcas con ✅ ya tienen un archivo activo. Debes eliminarlo o inactivarlo primero.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Título</label>
                    <input type="text" name="titulo" required placeholder="Ej: Inventario KIA 2026">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" rows="2" placeholder="Breve descripción del archivo"></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-file-excel"></i> Archivo Excel</label>
                    <input type="file" name="archivo" accept=".xls,.xlsx" required>
                    <div class="info-text">Formatos permitidos: XLS, XLSX. Máximo 10MB</div>
                </div>
                
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-upload"></i> Subir archivo
                </button>
            </form>
        </div>

        <!-- ===== LISTA DE ARCHIVOS ===== -->
        <div class="card">
            <h2>
                <i class="fas fa-list"></i> 
                Archivos existentes 
                <span style="font-size: 14px; font-weight: 400; color: #7f8c8d;">
                    (<?php echo count($archivos); ?>)
                </span>
            </h2>
            
            <?php if (empty($archivos)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-excel"></i>
                    <h3>No hay archivos registrados</h3>
                    <p>Sube el primer archivo usando el formulario superior.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th style="width: 100px;">Marca</th>
                                <th style="min-width: 150px;">Título</th>
                                <th style="min-width: 150px;">Descripción</th>
                                <th style="width: 100px;">Tipo</th>
                                <th style="width: 100px;">Estado</th>
                                <th style="width: 180px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archivos as $archivo): ?>
                                <tr>
                                    <td><?php echo $archivo['id']; ?></td>
                                    <td>
                                        <strong><?php echo ucfirst($archivo['marca']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($archivo['titulo']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($archivo['descripcion'], 0, 40)); ?>...</td>
                                    <td>
                                        <span class="badge badge-excel">
                                            <i class="fas fa-file-excel"></i> Excel
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $archivo['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <i class="fas <?php echo $archivo['activo'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            <?php echo $archivo['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($archivo['archivo_url']): ?>
                                            <a href="../../<?php echo htmlspecialchars($archivo['archivo_url']); ?>" target="_blank" class="btn-accion btn-ver" title="Ver archivo">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?cambiar_estado=<?php echo $archivo['id']; ?>" class="btn-accion btn-estado" title="Cambiar estado">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                        <a href="editar.php?id=<?php echo $archivo['id']; ?>" class="btn-accion btn-editar" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?eliminar=<?php echo $archivo['id']; ?>" class="btn-accion btn-eliminar" title="Eliminar" onclick="return confirm('¿Eliminar este archivo permanentemente?')">
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