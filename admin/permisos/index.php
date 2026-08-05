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

// Mostrar SOLO supervisores (rol_id = 2)
$sql = "SELECT u.*, r.nombre as rol_nombre 
        FROM usuarios u
        LEFT JOIN roles r ON u.rol_id = r.id
        WHERE u.rol_id = 2
        ORDER BY u.nombre_completo ASC";

$usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Contar módulos asignados a cada supervisor (CORREGIDO - SIN REFERENCIA)
foreach ($usuarios as $index => $usuario) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM permisos_usuarios WHERE usuario_id = ?");
    $stmt->execute([$usuario['id']]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $usuarios[$index]['total_modulos'] = $total;
}

// Verificar si hay supervisores
$no_supervisores = empty($usuarios);

// Obtener mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
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
            background: #f0f2f5;
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
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 8px 18px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
            font-size: 14px;
        }
        
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
            border-color: #ffc107;
        }
        
        /* ========== CARD ========== */
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
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
            font-size: 22px;
            color: #12232b;
            display: flex;
            align-items: center;
            gap: 10px;
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
        .btn-crear {
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: white;
            padding: 10px 22px;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn-crear:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        
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
            box-shadow: 0 4px 12px rgba(23,55,66,0.3);
        }
        
        .btn-asignar i {
            font-size: 12px;
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
            border-radius: 10px 10px 0 0;
        }
        
        thead th {
            color: white;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        thead th:first-child {
            border-radius: 10px 0 0 0;
        }
        
        thead th:last-child {
            border-radius: 0 10px 0 0;
        }
        
        tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            font-size: 14px;
        }
        
        /* ========== BADGES MEJORADOS ========== */
        .badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        
        .badge-supervisor {
            background: #f39c12;
            color: white;
        }
        
        .badge-supervisor i {
            background: #f39c12;
            color: white;
        }
        
        .badge-modulos {
            background: #3498db;
            color: white;
        }
        
        .badge-modulos i {
            background: #3498db;
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
            background: #3498db;
            color: white;
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
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #d3d3d3;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .empty-state p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        /* ========== MENSAJES ========== */
        .mensaje-exito {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 14px 20px;
            border-radius: 10px;
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
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
        }
        
        .mensaje-error i {
            margin-right: 10px;
            color: #e74c3c;
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
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
            
            .container {
                padding: 10px;
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
                            <?php echo count($usuarios); ?>
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
            
            <!-- ===== TABLA ===== -->
            <?php if ($no_supervisores): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No hay supervisores registrados</h3>
                    <p>Crea un usuario con rol de <strong>Supervisor</strong> para comenzar a asignar permisos.</p>
                    <a href="../usuarios/crear.php" class="btn-asignar" style="font-size: 14px; padding: 10px 25px;">
                        <i class="fas fa-user-plus"></i> Crear Supervisor
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
                            $contador = 1;
                            foreach ($usuarios as $usuario): 
                                $total = $usuario['total_modulos'];
                                
                                // Clase según cantidad de módulos
                                if ($total >= 6) {
                                    $badge_modulos_class = 'badge-modulos-completo';
                                } elseif ($total >= 3) {
                                    $badge_modulos_class = 'badge-modulos-medio';
                                } else {
                                    $badge_modulos_class = 'badge-modulos-bajo';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>
                                    </td>
                                    <td>
                                        <code style="background: #f0f0f0; padding: 5px 7px; border-radius: 4px; font-size: 17px;">
                                            <?php echo htmlspecialchars($usuario['usuario']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="badge badge-supervisor">
                                            <i class="fas fa-user-tie"></i> Supervisor
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($total == 0): ?>
                                            <span style="color: #95a5a6; font-size: 13px;">
                                                <i class="fas fa-ban"></i> Sin módulos asignados
                                            </span>
                                        <?php else: ?>
                                            <span class="badge <?php echo $badge_modulos_class; ?>">
                                                <i class="fas fa-th-list"></i> 
                                                <?php echo $total; ?> módulo<?php echo $total > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php endif; ?>
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
                        Total: <strong><?php echo count($usuarios); ?></strong> supervisores registrados
                    </div>
                    <div class="info">
                        <i class="fas fa-th-list"></i> 
                        <?php 
                        $total_modulos_sistema = $pdo->query("SELECT COUNT(*) FROM modulos WHERE activo = 1")->fetchColumn();
                        ?>
                        <strong><?php echo $total_modulos_sistema; ?></strong> módulos disponibles en el sistema
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>