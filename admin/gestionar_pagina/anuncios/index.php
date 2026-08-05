<?php
// admin/gestionar_pagina/anuncios/index.php - Lista de anuncios
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar anuncios";
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
    
    $stmt = $pdo->prepare("UPDATE anuncios SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Estado del anuncio actualizado correctamente";
    header("Location: index.php");
    exit();
}

// ✅ Eliminar anuncio
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    // Eliminar archivo adjunto si existe
    $stmt = $pdo->prepare("SELECT archivo FROM anuncios WHERE id = ?");
    $stmt->execute([$id]);
    $anuncio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($anuncio['archivo'])) {
        $ruta_archivo = '../../../uploads/anuncios/' . $anuncio['archivo'];
        if (file_exists($ruta_archivo)) {
            unlink($ruta_archivo);
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM anuncios WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Anuncio eliminado correctamente";
    header("Location: index.php");
    exit();
}

// ============================================
// OBTENER ANUNCIOS
// ============================================
$sql = "SELECT a.*, u.nombre_completo as creador 
        FROM anuncios a
        LEFT JOIN usuarios u ON a.creado_por = u.id
        ORDER BY a.fecha DESC";

$anuncios = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$no_anuncios = empty($anuncios);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Anuncios | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            padding: 10px 0;
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

        /* ===== ARCHIVO ===== */
        .file-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #173742;
            text-decoration: none;
            font-size: 13px;
            padding: 3px 10px;
            background: #e8f0fe;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .file-link:hover {
            background: #173742;
            color: white;
        }
        .file-link i {
            font-size: 14px;
        }
        .sin-archivo {
            color: #bdc3c7;
            font-size: 13px;
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
                <h2><i class="fas fa-bullhorn"></i> Lista de Anuncios</h2>
                <a href="crear.php" class="btn-agregar">
                    <i class="fas fa-plus"></i> Nuevo Anuncio
                </a>
            </div>

            <?php if ($no_anuncios): ?>
                <div class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <p>No hay anuncios registrados.</p>
                    <p style="font-size: 13px; margin-top: 5px;">Haz clic en <strong>"Nuevo Anuncio"</strong> para crear uno.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Descripción</th>
                                <th>Archivo</th>
                                <th>Estado</th>
                                <th>Creado por</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($anuncios as $anuncio): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($anuncio['titulo']); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $desc = htmlspecialchars($anuncio['descripcion']);
                                        echo strlen($desc) > 60 ? substr($desc, 0, 60) . '...' : $desc;
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($anuncio['archivo'])): ?>
                                            <?php 
                                            $extension = strtolower(pathinfo($anuncio['archivo'], PATHINFO_EXTENSION));
                                            $icono = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'fa-file-image' : 'fa-file-pdf';
                                            $ruta = '../../../uploads/anuncios/' . $anuncio['archivo'];
                                            ?>
                                            <a href="<?php echo $ruta; ?>" target="_blank" class="file-link" title="Ver archivo">
                                                <i class="fas <?php echo $icono; ?>"></i>
                                                <?php echo strtoupper($extension); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="sin-archivo">
                                                <i class="fas fa-minus-circle"></i> Sin archivo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="td-min">
                                        <span class="badge <?php echo $anuncio['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <?php echo $anuncio['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($anuncio['creador'] ?? 'Sistema'); ?></td>
                                    <td class="td-min">
                                        <?php echo date('d/m/Y H:i', strtotime($anuncio['fecha'])); ?>
                                    </td>
                                    <td class="td-min">
                                        <!-- Cambiar estado -->
                                        <a href="?cambiar_estado=<?php echo $anuncio['id']; ?>" 
                                           class="btn-accion <?php echo $anuncio['activo'] ? 'btn-estado-activo' : 'btn-estado-inactivo'; ?>" 
                                           title="<?php echo $anuncio['activo'] ? 'Desactivar' : 'Activar'; ?>"
                                           onclick="return confirm('¿<?php echo $anuncio['activo'] ? 'Desactivar' : 'Activar'; ?> este anuncio?')">
                                            <i class="fas <?php echo $anuncio['activo'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                        </a>

                                        <!-- Editar -->
                                        <a href="editar.php?id=<?php echo $anuncio['id']; ?>" class="btn-accion btn-editar" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <!-- Eliminar -->
                                        <a href="?eliminar=<?php echo $anuncio['id']; ?>" 
                                           class="btn-accion btn-eliminar" 
                                           onclick="return confirm('¿Eliminar este anuncio?')" 
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
                    Total: <strong><?php echo count($anuncios); ?></strong> anuncios registrados
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>