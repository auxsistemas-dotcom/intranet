<?php
// admin/permisos/index.php - Lista de SUPERVISORES y sus permisos
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores (rol_id = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

// Solo administradores pueden gestionar permisos
if ($rol_usuario != 1) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar permisos";
    header('Location: ../index.php');
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

// ============================================
// ✅ PAGINACIÓN DE SUPERVISORES
// ============================================

$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 7;
$offset = ($pagina - 1) * $registros_por_pagina;

// Contar total de supervisores
if (!empty($busqueda)) {
    $sql_count = "SELECT COUNT(*) as total 
                  FROM usuarios u
                  WHERE u.rol_id = 2 
                  AND (u.nombre_completo LIKE :busqueda 
                     OR u.usuario LIKE :busqueda)";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute(['busqueda' => '%' . $busqueda . '%']);
} else {
    $sql_count = "SELECT COUNT(*) as total FROM usuarios WHERE rol_id = 2";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute();
}
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener supervisores con paginación
if (!empty($busqueda)) {
    $sql = "SELECT u.*, r.nombre as rol_nombre 
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            WHERE u.rol_id = 2 
            AND (u.nombre_completo LIKE :busqueda 
               OR u.usuario LIKE :busqueda)
            ORDER BY u.nombre_completo ASC
            LIMIT :offset, :registros_por_pagina";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':busqueda', '%' . $busqueda . '%');
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':registros_por_pagina', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->execute();
} else {
    $sql = "SELECT u.*, r.nombre as rol_nombre 
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            WHERE u.rol_id = 2
            ORDER BY u.nombre_completo ASC
            LIMIT :offset, :registros_por_pagina";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':registros_por_pagina', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->execute();
}
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar módulos asignados a cada supervisor
foreach ($usuarios as $index => $usuario) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM permisos_usuarios WHERE usuario_id = ?");
    $stmt->execute([$usuario['id']]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $usuarios[$index]['total_modulos'] = $total;
}

// Verificar si hay supervisores
$no_supervisores = empty($usuarios) && $total_registros == 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Permisos | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #d3d3d3;
            color: #2c3e50;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ========== HEADER ========== */
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            color: white;
            font-size: 24px;
            font-weight: 700;
        }
        
        .logo h1 span {
            color: #ffc107;
        }
        
        .logo small {
            color: #8aa8b8;
            font-size: 12px;
            font-weight: 300;
        }
        
        .btn-volver {
            background: #445960;
            color: white;
            padding: 7px 14px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        /* ========== CARD ========== */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-actions .left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-actions .left h2 {
            font-size: 20px;
            color: #12232b;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #173742;
            padding-left: 15px;
        }
        
        .header-actions .left h2 i {
            color: #173742;
        }
        
        .header-actions .badge-total {
            background: #173742;
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .subtitle {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 25px;
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #f39c12;
        }
        
        .subtitle i {
            color: #f39c12;
        }
        
        .subtitle small {
            display: block;
            margin-top: 5px;
            color: #95a5a6;
        }
        
        .subtitle small i {
            color: #27ae60;
        }
        
        /* ========== BOTONES ========== */
        .btn-asignar {
            background: #173742;
            color: white;
            padding: 6px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
            cursor: pointer;
        }
        
        .btn-asignar:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .btn-asignar i {
            font-size: 12px;
        }
        
        /* ========== BUSCADOR ========== */
        .buscador-container {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .buscador-container form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex: 1;
            flex-wrap: wrap;
        }
        .buscador-container input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .buscador-container input[type="text"]:focus {
            outline: none;
            border-color: #173742;
        }
        .btn-buscar {
            background: #173742;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .btn-buscar:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        .btn-limpiar {
            background: #7f8c8d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .btn-limpiar:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }
        .resultado-busqueda {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        .resultado-busqueda strong {
            color: #12232b;
        }
        
        /* ========== TABLA ========== */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #12232b;
        }
        
        thead th {
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 14px;
        }
        
        /* ========== BADGES ========== */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .badge-supervisor {
            background: #f39c12;
            color: white;
        }
        
        .badge-modulos-completo {
            background: #d5f5e3;
            color: #1a7a3a;
            border: 1px solid #27ae60;
        }
        
        .badge-modulos-completo i {
            color: #27ae60;
        }
        
        .badge-modulos-medio {
            background: #fdebd0;
            color: #a04000;
            border: 1px solid #e67e22;
        }
        
        .badge-modulos-medio i {
            color: #e67e22;
        }
        
        .badge-modulos-bajo {
            background: #3498db;
            color: white;
        }
        
        .badge-modulos-bajo i {
            color: white;
        }
        
        .badge-sin-modulos {
            background: #ecf0f1;
            color: #7f8c8d;
        }
        
        /* ========== FOOTER ========== */
        .card-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-footer .info {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .card-footer .info i {
            color: #3498db;
        }
        
        .card-footer .info strong {
            color: #2c3e50;
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #d3d3d3;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .empty-state p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .btn-crear-supervisor {
            background: #27ae60;
            color: white;
            padding: 10px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-crear-supervisor:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        
        /* ========== MENSAJES ========== */
        .mensaje-exito {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            font-weight: 500;
        }
        
        .mensaje-exito i {
            margin-right: 10px;
            color: #27ae60;
        }
        
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
        }
        
        .mensaje-error i {
            margin-right: 10px;
            color: #e74c3c;
        }
        
        /* ============================================ */
        /* ===== PAGINACIÓN ===== */
        /* ============================================ */
        .paginacion-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #d3d3d3;
        }
        .paginacion-info {
            font-size: 13px;
            color: #7f8c8d;
        }
        .paginacion-info strong {
            color: #12232b;
        }
        .paginacion {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .paginacion a, .paginacion .pagina-actual {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .paginacion a {
            background: #f0f0f0;
            color: #2c3e50;
        }
        .paginacion a:hover {
            background: #173742;
            color: white;
            transform: translateY(-2px);
        }
        .paginacion .pagina-actual {
            background: #173742;
            color: white;
        }
        .paginacion .pagina-puntos {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            color: #7f8c8d;
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-footer {
                flex-direction: column;
                text-align: center;
            }
            
            tbody td {
                font-size: 13px;
                padding: 10px 12px;
            }
            
            thead th {
                font-size: 11px;
                padding: 10px 12px;
            }
            
            .subtitle {
                font-size: 13px;
                padding: 12px 15px;
            }
            
            .badge {
                font-size: 11px;
                padding: 4px 12px;
            }
            
            .buscador-container form {
                flex-direction: column;
            }
            .buscador-container input[type="text"] {
                width: 100%;
                min-width: auto;
            }
            .buscador-container .btn-buscar,
            .buscador-container .btn-limpiar {
                width: 100%;
                text-align: center;
            }
            
            .paginacion-container {
                flex-direction: column;
                align-items: center;
            }
        }
        
        @media (max-width: 480px) {
            table {
                font-size: 12px;
            }
            
            thead th, tbody td {
                padding: 8px 10px;
            }
            
            .badge {
                font-size: 10px;
                padding: 3px 10px;
                gap: 4px;
            }
            
            .btn-asignar {
                font-size: 10px;
                padding: 4px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- ========== HEADER ========== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small>| Gestión de Permisos</small>
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </header>
    
    <!-- ========== CONTENIDO ========== -->
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <!-- ===== HEADER CARD ===== -->
            <div class="header-actions">
                <div class="left">
                    <h2>
                        <i class="fas fa-user-tie"></i> 
                        Permisos de Supervisores
                        <span class="badge-total">
                            <?php echo $total_registros; ?>
                        </span>
                    </h2>
                </div>
            </div>
            
            <!-- ===== SUBTITLE ===== -->
            <div class="subtitle">
                <i class="fas fa-info-circle"></i> 
                Asigna qué módulos puede ver cada supervisor en el panel de administración.
                <small>
                    <i class="fas fa-check-circle"></i> 
                    Administradores: <strong>Acceso total</strong> | 
                    Supervisores: <strong>Acceso asignado</strong> | 
                    Usuarios: <strong>Sin acceso</strong>
                </small>
            </div>
            
            <!-- ===== BUSCADOR ===== -->
            <div class="buscador-container">
                <form method="GET" action="">
                    <input type="text" name="buscar" placeholder="Buscar supervisor por nombre o usuario..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    <button type="submit" class="btn-buscar">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="index.php" class="btn-limpiar">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($busqueda) && count($usuarios) > 0): ?>
                <div class="resultado-busqueda">
                    <i class="fas fa-search"></i> Resultados para: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong>
                    (<?php echo count($usuarios); ?> encontrados)
                </div>
            <?php endif; ?>
            
            <!-- ===== TABLA ===== -->
            <?php if ($no_supervisores && empty($busqueda)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No hay supervisores registrados</h3>
                    <p>Crea un usuario con rol de <strong>Supervisor</strong> para comenzar a asignar permisos.</p>
                    <a href="../usuarios/crear.php" class="btn-crear-supervisor">
                        <i class="fas fa-user-plus"></i> Crear Supervisor
                    </a>
                </div>
            <?php elseif (empty($usuarios) && !empty($busqueda)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No se encontraron resultados</h3>
                    <p>No hay supervisores que coincidan con: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong></p>
                    <a href="index.php" class="btn-asignar" style="font-size: 14px; padding: 10px 25px;">
                        <i class="fas fa-arrow-left"></i> Ver todos
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="min-width: 180px;">Nombre</th>
                                <th style="min-width: 120px;">Usuario</th>
                                <th style="width: 140px;">Rol</th>
                                <th style="min-width: 180px;">Módulos Asignados</th>
                                <th style="width: 120px; text-align: center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = $offset + 1;
                            foreach ($usuarios as $usuario): 
                                $total = $usuario['total_modulos'] ?? 0;
                                
                                // Clase según cantidad de módulos
                                if ($total >= 6) {
                                    $badge_modulos_class = 'badge-modulos-completo';
                                    $badge_icon = 'fa-check-circle';
                                } elseif ($total >= 3) {
                                    $badge_modulos_class = 'badge-modulos-medio';
                                    $badge_icon = 'fa-minus-circle';
                                } elseif ($total > 0) {
                                    $badge_modulos_class = 'badge-modulos-bajo';
                                    $badge_icon = 'fa-plus-circle';
                                } else {
                                    $badge_modulos_class = 'badge-sin-modulos';
                                    $badge_icon = 'fa-ban';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>
                                    </td>
                                    <td>
                                        <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-size: 13px;">
                                            <?php echo htmlspecialchars($usuario['usuario']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="badge badge-supervisor">
                                            <i class="fas fa-user-tie"></i> Supervisor
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_modulos_class; ?>">
                                            <i class="fas <?php echo $badge_icon; ?>"></i> 
                                            <?php echo $total; ?> módulo<?php echo $total != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="asignar.php?id=<?php echo $usuario['id']; ?>" class="btn-asignar">
                                            <i class="fas fa-edit"></i> 
                                            <?php echo $total == 0 ? 'Asignar' : 'Editar'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- ===== FOOTER CARD ===== -->
                <div class="card-footer">
                    <div class="info">
                        <i class="fas fa-info-circle"></i> 
                        Total: <strong><?php echo $total_registros; ?></strong> supervisores registrados
                        <?php if (!empty($busqueda)): ?>
                            | Resultados para: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong>
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <i class="fas fa-th-list"></i> 
                        <?php 
                        $total_modulos_sistema = $pdo->query("SELECT COUNT(*) FROM modulos WHERE activo = 1")->fetchColumn();
                        ?>
                        <strong><?php echo $total_modulos_sistema; ?></strong> módulos disponibles en el sistema
                    </div>
                </div>
                
                <!-- ===== PAGINACIÓN ===== -->
                <?php if ($total_paginas > 1): ?>
                <div class="paginacion-container">
                    <div class="paginacion-info">
                        <i class="fas fa-info-circle"></i> 
                        Mostrando <strong><?php echo count($usuarios); ?></strong> de <strong><?php echo $total_registros; ?></strong> supervisores
                        <?php if (!empty($busqueda)): ?>
                            | Resultados para: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong>
                        <?php endif; ?>
                    </div>

                    <div class="paginacion">
                        <!-- Primera página -->
                        <?php if ($pagina > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>" title="Primera página">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Anterior -->
                        <?php if ($pagina > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" title="Página anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Páginas -->
                        <?php
                        $rango = 2;
                        $inicio = max(1, $pagina - $rango);
                        $fin = min($total_paginas, $pagina + $rango);

                        if ($inicio > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">1</a>
                            <?php if ($inicio > 2): ?>
                                <span class="pagina-puntos">…</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                            <?php if ($i == $pagina): ?>
                                <span class="pagina-actual"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($fin < $total_paginas): ?>
                            <?php if ($fin < $total_paginas - 1): ?>
                                <span class="pagina-puntos">…</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>"><?php echo $total_paginas; ?></a>
                        <?php endif; ?>

                        <!-- Siguiente -->
                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" title="Página siguiente">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Última página -->
                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>" title="Última página">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>