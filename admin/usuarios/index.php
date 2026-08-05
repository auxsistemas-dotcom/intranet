<?php
// admin/usuarios/index.php - Lista y gestionar usuarios
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores (rol_id = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

// Solo administradores pueden gestionar usuarios
if ($rol_usuario != 1) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar usuarios";
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

// ✅ Cambiar estado (activar/desactivar)
if (isset($_GET['cambiar_estado']) && is_numeric($_GET['cambiar_estado'])) {
    $id = $_GET['cambiar_estado'];
    
    // No permitir cambiar el estado del propio usuario
    if ($id == $_SESSION['usuario_id']) {
        $_SESSION['error'] = "❌ No puedes cambiar tu propio estado";
        header("Location: index.php");
        exit();
    }
    
    $stmt = $pdo->prepare("UPDATE usuarios SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensaje'] = "✅ Estado del usuario actualizado correctamente";
    header("Location: index.php");
    exit();
}

// Eliminar usuario
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    // No permitir eliminar el propio usuario
    if ($id != $_SESSION['usuario_id']) {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['mensaje'] = "✅ Usuario eliminado correctamente";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "❌ No puedes eliminar tu propio usuario";
        header("Location: index.php");
        exit();
    }
}

// ============================================
// ✅ BUSCADOR DE USUARIOS
// ============================================
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

if (!empty($busqueda)) {
    $sql = "SELECT u.*, r.nombre as rol_nombre 
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            WHERE u.nombre_completo LIKE :busqueda 
               OR u.usuario LIKE :busqueda
            ORDER BY u.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['busqueda' => '%' . $busqueda . '%']);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sql = "SELECT u.*, r.nombre as rol_nombre 
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            ORDER BY u.id ASC";
    $usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios | INTRANET</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #12232b;
            padding: 0px 0;
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
        }
        
        .logo span {
            color: #445960;
        }
        
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
        
        .btn-agregar {
            background: #173742;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-agregar:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #d3d3d3;
        }
        
        th {
            background: #12232b;
            color: white;
            font-weight: 600;
            white-space: nowrap;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        
        .badge-admin {
            background: #173742;
            color: white;
        }
        
        .badge-supervisor {
            background: #f39c12;
            color: white;
        }
        
        .badge-usuario {
            background: #445960;
            color: white;
        }
        
        .badge-activo {
            background: #27ae60;
            color: white;
        }
        
        .badge-inactivo {
            background: #e74c3c;
            color: white;
        }
        
        .btn-accion {
            padding: 5px 10px;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-accion:hover {
            transform: translateY(-2px);
        }
        
        .btn-editar {
            background: #3498db;
            color: white;
        }
        
        .btn-editar:hover {
            background: #2980b9;
        }
        
        .btn-eliminar {
            background: #e74c3c;
            color: white;
        }
        
        .btn-eliminar:hover {
            background: #c0392b;
        }
        
        .btn-permisos {
            background: #8e44ad;
            color: white;
        }
        
        .btn-permisos:hover {
            background: #732d91;
        }
        
        .btn-estado-activo {
            background: #f39c12;
            color: white;
        }
        
        .btn-estado-activo:hover {
            background: #e67e22;
        }
        
        .btn-estado-inactivo {
            background: #27ae60;
            color: white;
        }
        
        .btn-estado-inactivo:hover {
            background: #219a52;
        }
        
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .acciones-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .text-muted {
            color: #7f8c8d;
            font-size: 12px;
        }

        .td-min {
            white-space: nowrap;
        }

        /* ============================================
           ✅ ESTILOS DEL BUSCADOR
        ============================================ */
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

        @media (max-width: 768px) {
            .container { padding: 10px; }
            .acciones-header { flex-direction: column; align-items: stretch; }
            .btn-agregar { text-align: center; }
            table { font-size: 12px; }
            th, td { padding: 6px 8px; }
            .buscador-container form { flex-direction: column; }
            .buscador-container input[type="text"] { width: 100%; min-width: auto; }
            .buscador-container .btn-buscar,
            .buscador-container .btn-limpiar { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Gestión de Usuarios</small>
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
        
        <div class="card">
            <div class="acciones-header">
                <h2><i class="fas fa-users"></i> Lista de Usuarios</h2>
                <a href="crear.php" class="btn-agregar">
                    <i class="fas fa-plus"></i> Nuevo Usuario
                </a>
            </div>

            <!-- ============================================
            ✅ BUSCADOR
            ============================================ -->
            <div class="buscador-container">
                <form method="GET" action="">
                    <input type="text" name="buscar" placeholder="Buscar por nombre o usuario..." value="<?php echo htmlspecialchars($busqueda); ?>">
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

            <!-- ============================================
            ✅ RESULTADO DE BÚSQUEDA
            ============================================ -->
            <?php if (!empty($busqueda)): ?>
                <div class="resultado-busqueda">
                    <i class="fas fa-search"></i> Resultados para: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong>
                    (<?php echo count($usuarios); ?> encontrados)
                </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Usuario</th>
                            <th>Perfil</th>
                            <th>Cargo</th>
                            <th>Sede</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($usuarios) > 0): ?>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['perfil_usuario'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['cargo'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['sede'] ?? '-'); ?></td>
                                <td class="td-min">
                                    <?php
                                    $badge_class = 'badge-usuario';
                                    $rol_nombre = 'Usuario';
                                    
                                    if ($usuario['rol_id'] == 1) {
                                        $badge_class = 'badge-admin';
                                        $rol_nombre = 'Administrador';
                                    } elseif ($usuario['rol_id'] == 2) {
                                        $badge_class = 'badge-supervisor';
                                        $rol_nombre = 'Supervisor';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $rol_nombre; ?>
                                    </span>
                                </td>
                                <td class="td-min">
                                    <span class="badge <?php echo $usuario['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                        <?php echo $usuario['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="td-min">
                                    <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                        <!-- ✅ BOTÓN CAMBIAR ESTADO -->
                                        <a href="?cambiar_estado=<?php echo $usuario['id']; ?>" class="btn-accion <?php echo $usuario['activo'] ? 'btn-estado-activo' : 'btn-estado-inactivo'; ?>" title="<?php echo $usuario['activo'] ? 'Desactivar usuario' : 'Activar usuario'; ?>" onclick="return confirm('¿<?php echo $usuario['activo'] ? 'Desactivar' : 'Activar'; ?> este usuario?')">
                                            <i class="fas <?php echo $usuario['activo'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                        </a>

                                        <!-- Editar -->
                                        <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="btn-accion btn-editar" title="Editar usuario">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Solo para SUPERVISORES -->
                                        <?php if ($usuario['rol_id'] == 2): ?>
                                            <a href="../permisos/asignar.php?id=<?php echo $usuario['id']; ?>" class="btn-accion btn-permisos" title="Asignar permisos">
                                                <i class="fas fa-lock"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Eliminar -->
                                        <a href="?eliminar=<?php echo $usuario['id']; ?>" class="btn-accion btn-eliminar" onclick="return confirm('¿Eliminar este usuario?')" title="Eliminar usuario">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">(Tú)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                    <i class="fas fa-search" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                                    No se encontraron usuarios para: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>