<?php
// admin/gestionar_pagina/slider/index.php - Lista de slides
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar el slider";
    header('Location: ../../index.php');
    exit();
}

$mensaje = '';
$error = '';

// Recuperar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ✅ Cambiar estado (activar/desactivar)
if (isset($_GET['cambiar_estado']) && is_numeric($_GET['cambiar_estado'])) {
    $id = $_GET['cambiar_estado'];
    
    $stmt = $pdo->prepare("UPDATE slider SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Estado del slide actualizado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Subir orden
if (isset($_GET['subir']) && is_numeric($_GET['subir'])) {
    $id = $_GET['subir'];
    
    // Obtener el slide actual
    $stmt = $pdo->prepare("SELECT orden FROM slider WHERE id = ?");
    $stmt->execute([$id]);
    $slide_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    $orden_actual = $slide_actual['orden'];
    
    // Buscar el slide anterior (orden menor)
    $stmt = $pdo->prepare("SELECT id, orden FROM slider WHERE orden < ? ORDER BY orden DESC LIMIT 1");
    $stmt->execute([$orden_actual]);
    $slide_anterior = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($slide_anterior) {
        // Intercambiar órdenes
        $stmt = $pdo->prepare("UPDATE slider SET orden = ? WHERE id = ?");
        $stmt->execute([$slide_anterior['orden'], $id]);
        $stmt->execute([$orden_actual, $slide_anterior['id']]);
        
        $_SESSION['mensaje'] = "✅ Orden actualizado correctamente";
    }
    header("Location: index.php");
    exit();
}

// ✅ Bajar orden
if (isset($_GET['bajar']) && is_numeric($_GET['bajar'])) {
    $id = $_GET['bajar'];
    
    // Obtener el slide actual
    $stmt = $pdo->prepare("SELECT orden FROM slider WHERE id = ?");
    $stmt->execute([$id]);
    $slide_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    $orden_actual = $slide_actual['orden'];
    
    // Buscar el slide siguiente (orden mayor)
    $stmt = $pdo->prepare("SELECT id, orden FROM slider WHERE orden > ? ORDER BY orden ASC LIMIT 1");
    $stmt->execute([$orden_actual]);
    $slide_siguiente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($slide_siguiente) {
        // Intercambiar órdenes
        $stmt = $pdo->prepare("UPDATE slider SET orden = ? WHERE id = ?");
        $stmt->execute([$slide_siguiente['orden'], $id]);
        $stmt->execute([$orden_actual, $slide_siguiente['id']]);
        
        $_SESSION['mensaje'] = "✅ Orden actualizado correctamente";
    }
    header("Location: index.php");
    exit();
}

// ✅ Eliminar slide
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    $stmt = $pdo->prepare("SELECT imagen_url FROM slider WHERE id = ?");
    $stmt->execute([$id]);
    $slide = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Eliminar imagen física
    if (!empty($slide['imagen_url'])) {
        $ruta_imagen = '../../../' . $slide['imagen_url'];
        if (file_exists($ruta_imagen)) {
            unlink($ruta_imagen);
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM slider WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Slide eliminado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Obtener slides
$sql = "SELECT * FROM slider ORDER BY orden ASC, id ASC";
$slides = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$no_slides = empty($slides);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Slider | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            padding: 0px 0;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        .header .container { display: flex; justify-content: space-between; align-items: center; }
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
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-agregar {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-agregar:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }

        /* ===== TABLA ===== */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #d3d3d3; }
        th { background: #12232b; color: white; font-weight: 600; white-space: nowrap; }
        tr:hover { background: #f8f9fa; }

        /* ===== BADGES ===== */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .badge-activo { background: #d5f5e3; color: #1a7a3a; }
        .badge-inactivo { background: #fadbd8; color: #922b21; }

        .badge-tipo {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
        }
        .badge-noticia { background: #d6eaf8; color: #1a5276; }
        .badge-promocion { background: #fdebd0; color: #a04000; }
        .badge-aviso { background: #fadbd8; color: #922b21; }
        .badge-evento { background: #e8d5f5; color: #6c3483; }

        /* ===== IMAGEN PREVIEW ===== */
        .slide-preview {
            width: 80px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        /* ===== BOTONES ACCIÓN ===== */
        .btn-accion {
            padding: 5px 10px;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-accion:hover { transform: translateY(-2px); }

        .btn-editar { background: #3498db; color: white; }
        .btn-editar:hover { background: #2980b9; }

        .btn-eliminar { background: #e74c3c; color: white; }
        .btn-eliminar:hover { background: #c0392b; }

        .btn-estado-activo { background: #f39c12; color: white; }
        .btn-estado-activo:hover { background: #e67e22; }

        .btn-estado-inactivo { background: #27ae60; color: white; }
        .btn-estado-inactivo:hover { background: #219a52; }

        .btn-orden {
            background: #8e44ad;
            color: white;
            font-size: 11px;
            padding: 4px 8px;
        }
        .btn-orden:hover { background: #732d91; }

        /* ===== MENSAJES ===== */
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

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state i { font-size: 48px; color: #d3d3d3; margin-bottom: 15px; }
        .empty-state p { font-size: 14px; }

        .text-muted { color: #7f8c8d; font-size: 12px; }
        .td-min { white-space: nowrap; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header-actions { flex-direction: column; align-items: stretch; }
            .btn-agregar { text-align: center; }
            table { font-size: 12px; }
            th, td { padding: 8px 10px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
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

        <div class="card">
            <div class="header-actions">
                <h2><i class="fas fa-images"></i> Lista de Slides</h2>
                <a href="crear.php" class="btn-agregar">
                    <i class="fas fa-plus"></i> Agregar Slide
                </a>
            </div>

            <?php if ($no_slides): ?>
                <div class="empty-state">
                    <i class="fas fa-images"></i>
                    <p>No hay slides registrados.</p>
                    <p style="font-size: 13px; margin-top: 5px;">Haz clic en <strong>"Agregar Slide"</strong> para crear uno.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Imagen</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Orden</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; foreach ($slides as $slide): ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td>
                                        <?php if (!empty($slide['imagen_url'])): ?>
                                            <img src="../../../<?php echo htmlspecialchars($slide['imagen_url']); ?>" alt="<?php echo htmlspecialchars($slide['titulo']); ?>" class="slide-preview">
                                        <?php else: ?>
                                            <span class="text-muted">Sin imagen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($slide['titulo']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge-tipo badge-<?php echo htmlspecialchars($slide['tipo']); ?>">
                                            <?php echo htmlspecialchars($slide['tipo']); ?>
                                        </span>
                                    </td>
                                    <td class="td-min">
                                        <span style="font-weight: 600; color: #173742;"><?php echo $slide['orden']; ?></span>
                                    </td>
                                    <td class="td-min">
                                        <span class="badge <?php echo $slide['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <?php echo $slide['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="td-min">
                                        <!-- Cambiar estado -->
                                        <a href="?cambiar_estado=<?php echo $slide['id']; ?>" 
                                           class="btn-accion <?php echo $slide['activo'] ? 'btn-estado-activo' : 'btn-estado-inactivo'; ?>" 
                                           title="<?php echo $slide['activo'] ? 'Desactivar' : 'Activar'; ?>"
                                           onclick="return confirm('¿<?php echo $slide['activo'] ? 'Desactivar' : 'Activar'; ?> este slide?')">
                                            <i class="fas <?php echo $slide['activo'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                        </a>

                                        <!-- Editar -->
                                        <a href="editar.php?id=<?php echo $slide['id']; ?>" class="btn-accion btn-editar" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Eliminar -->
                                        <a href="?eliminar=<?php echo $slide['id']; ?>" 
                                           class="btn-accion btn-eliminar" 
                                           onclick="return confirm('¿Eliminar este slide?')" 
                                           title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 15px; color: #7f8c8d; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> 
                    Total: <strong><?php echo count($slides); ?></strong> slides registrados
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>