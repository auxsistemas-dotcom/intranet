<?php
// admin/capacitaciones/permisos/index.php - Gestionar permisos de capacitaciones
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar permisos de capacitaciones";
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

// ✅ AGREGAR USUARIO A LA LISTA DE PERMISOS
if (isset($_GET['agregar']) && is_numeric($_GET['agregar'])) {
    $id_usuario = $_GET['agregar'];
    
    // Verificar que el usuario existe y no es administrador
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND rol_id != 1");
    $stmt->execute([$id_usuario]);
    if ($stmt->fetch()) {
        // Verificar que no esté ya en la lista
        $stmt = $pdo->prepare("SELECT id FROM usuarios_permisos_capacitaciones WHERE usuario_id = ?");
        $stmt->execute([$id_usuario]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO usuarios_permisos_capacitaciones (usuario_id) VALUES (?)");
            $stmt->execute([$id_usuario]);
            
            $_SESSION['mensaje'] = "✅ Usuario añadido correctamente. Ahora asigna los permisos desde el botón 'Asignar'.";
        } else {
            $_SESSION['error'] = "❌ El usuario ya está en la lista de permisos";
        }
    } else {
        $_SESSION['error'] = "❌ Usuario no encontrado o es administrador";
    }
    header("Location: index.php");
    exit();
}

// ✅ ELIMINAR USUARIO DE LA LISTA DE PERMISOS
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id_usuario = $_GET['eliminar'];
    
    $stmt = $pdo->prepare("DELETE FROM usuarios_permisos_capacitaciones WHERE usuario_id = ?");
    $stmt->execute([$id_usuario]);
    
    $stmt = $pdo->prepare("DELETE FROM permisos_capacitaciones WHERE usuario_id = ?");
    $stmt->execute([$id_usuario]);
    
    $_SESSION['mensaje'] = "✅ Usuario eliminado de la lista de permisos";
    header("Location: index.php");
    exit();
}

// ✅ OBTENER USUARIOS CON PERMISOS
$sql = "SELECT u.id, u.nombre_completo, u.usuario, u.rol_id, r.nombre as rol_nombre
        FROM usuarios u
        LEFT JOIN roles r ON u.rol_id = r.id
        WHERE u.id IN (SELECT usuario_id FROM usuarios_permisos_capacitaciones)
        AND u.rol_id != 1
        GROUP BY u.id
        ORDER BY u.nombre_completo ASC";

$usuarios_con_permisos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ✅ Contar permisos REALES asignados a cada usuario (SIN REFERENCIA)
foreach ($usuarios_con_permisos as $key => $usuario) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM permisos_capacitaciones WHERE usuario_id = ? AND puede_ver = 1");
    $stmt->execute([$usuario['id']]);
    $usuarios_con_permisos[$key]['total_permisos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

// ✅ OBTENER TODOS LOS USUARIOS DISPONIBLES PARA EL MODAL
$sql_disponibles = "SELECT u.id, u.nombre_completo, u.usuario, u.rol_id, r.nombre as rol_nombre
                    FROM usuarios u
                    LEFT JOIN roles r ON u.rol_id = r.id
                    WHERE u.rol_id != 1 
                    AND u.id NOT IN (SELECT usuario_id FROM usuarios_permisos_capacitaciones)
                    GROUP BY u.id
                    ORDER BY u.nombre_completo ASC";

$usuarios_disponibles = $pdo->query($sql_disponibles)->fetchAll(PDO::FETCH_ASSOC);

$no_usuarios = empty($usuarios_con_permisos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permisos de Capacitaciones | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        /* ===== HEADER ===== */
        .header {
            background: linear-gradient(135deg, #12232b 0%, #173742 100%);
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
        .logo h1 { color: white; font-size: 24px; font-weight: 700; }
        .logo small { color: #8aa8b8; font-size: 14px; font-weight: 300; display: block; }
        
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
        
        .btn-back-capacitaciones {
            background: #445960;
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
        .btn-back-capacitaciones:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
            border-color: #ffc107;
        }
        
        /* ===== CARD ===== */
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
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-actions h2 i { color: #173742; }
        
        .badge-total {
            background: #173742;
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        /* ===== BOTONES ===== */
        .btn-add {
            background: #27ae60;
            color: white;
            padding: 10px 22px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-add:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        
        .btn-asignar {
            background: #173742;
            color: white;
            padding: 6px 14px;
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
        
        .btn-eliminar-usuario {
            background: #e74c3c;
            color: white;
            padding: 9px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            margin-left: 3px;
        }
        .btn-eliminar-usuario:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        /* ===== TABLA ===== */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #12232b; color: white; font-weight: 600; font-size: 13px; }
        tr:hover { background: #f8f9fa; }
        
        .badge {
            padding: 7px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-supervisor { background: #f39c12; color: white; }
        .badge-usuario { background: #445960; color: white; }
        .badge-permisos { background: #8e44ad; color: white; }
        
        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        
        .modal {
            background: white;
            border-radius: 16px;
            max-width: 800px;
            width: 95%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            animation: modalSlide 0.3s ease;
        }
        
        @keyframes modalSlide {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .modal-header h2 {
            font-size: 20px;
            color: #12232b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h2 i { color: #173742; }
        
        .btn-close-modal {
            background: none;
            border: none;
            font-size: 35px;
            color: #7f8c8d;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-close-modal:hover { color: #e74c3c; }
        
        .modal-body {
            padding: 20px 25px;
            overflow-y: auto;
            flex: 1;
        }
        
        /* ===== BUSCADOR ===== */
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }
        .search-box:focus-within {
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        .search-box i { color: #7f8c8d; font-size: 16px; }
        .search-box input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 8px 0;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            outline: none;
            color: #2c3e50;
        }
        .search-box input::placeholder { color: #bdc3c7; }
        
        /* ===== USUARIOS EN MODAL ===== */
        .usuario-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s;
            flex-wrap: wrap;
            gap: 10px;
        }
        .usuario-item:hover { background: #f8f9fa; }
        .usuario-item .info { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .usuario-item .info strong { font-size: 15px; color: #2c3e50; }
        .usuario-item .info code {
            background: #f0f0f0;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 13px;
        }
        .usuario-item.hidden { display: none; }
        
        .btn-add-usuario-modal {
            background: #27ae60;
            color: white;
            padding: 6px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        .btn-add-usuario-modal:hover {
            background: #219a52;
            transform: scale(1.05);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state i { font-size: 48px; color: #d3d3d3; margin-bottom: 15px; }
        .empty-state h3 { color: #2c3e50; }
        
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
        
        .modal-footer {
            padding: 15px 25px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-actions { flex-direction: column; align-items: flex-start; }
            .usuario-item { flex-direction: column; align-items: flex-start; }
            .usuario-item .info { width: 100%; }
            .modal { width: 100%; max-height: 100vh; border-radius: 0; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="Logo INTRANET" style="width: 130px;">
                <small>| Permisos</small>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="../index.php" class="btn-back-capacitaciones">
                    <i class="fas fa-arrow-left"></i> Volver a Capacitaciones
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ===== CARD PRINCIPAL ===== -->
        <div class="card">
            <div class="header-actions">
                <h2>
                    <i class="fas fa-user-lock"></i> 
                    Usuarios con Permisos
                    <span class="badge-total">
                        <?php echo count($usuarios_con_permisos); ?>
                    </span>
                </h2>
                <button class="btn-add" onclick="openModal()">
                    <i class="fas fa-user-plus"></i> Añadir Usuario
                </button>
            </div>
            
            <p style="color: #7f8c8d; margin-bottom: 20px; font-size: 14px;">
                <i class="fas fa-info-circle"></i> 
                Administra qué usuarios tienen acceso al módulo de Capacitaciones.
                <br>
            </p>
            
            <!-- ===== TABLA DE USUARIOS CON PERMISOS ===== -->
            <?php if ($no_usuarios): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No hay usuarios con permisos</h3>
                    <p>Haz clic en <strong>"Añadir Usuario"</strong> para comenzar a asignar permisos.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="min-width: 180px;">Nombre</th>
                                <th style="min-width: 120px;">Usuario</th>
                                <th style="width: 120px;">Rol</th>
                                <th style="width: 150px;">Permisos Asignados</th>
                                <th style="width: 200px; text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; foreach ($usuarios_con_permisos as $usuario): ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>
                                    </td>
                                    <td>
                                        <code style="background: #f0f0f0; padding: 2px 10px; border-radius: 4px; font-size: 16px;">
                                            <?php echo htmlspecialchars($usuario['usuario']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ($usuario['rol_id'] == 2): ?>
                                            <span class="badge badge-supervisor">
                                                <i class="fas fa-user-tie"></i> Supervisor
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-usuario">
                                                <i class="fas fa-user"></i> Usuario
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-permisos">
                                            <i class="fas fa-th-list"></i> 
                                            <?php echo $usuario['total_permisos']; ?> permiso<?php echo $usuario['total_permisos'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="asignar.php?id=<?php echo $usuario['id']; ?>" class="btn-asignar">
                                            <i class="fas fa-edit"></i> 
                                            Asignar
                                        </a>
                                        <button class="btn-eliminar-usuario" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)">
                                            <i class="fas fa-user-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== MODAL AÑADIR USUARIO ===== -->
    <div class="modal-overlay" id="modalAddUser">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Añadir Usuario</h2>
                <button class="btn-close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($usuarios_disponibles)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                        <h3>Todos los usuarios ya tienen permisos</h3>
                        <p>No hay usuarios disponibles para añadir.</p>
                    </div>
                <?php else: ?>
                    <!-- ===== BUSCADOR ===== -->
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Buscar usuario por nombre o usuario..." onkeyup="filtrarUsuarios()">
                    </div>
                    
                    <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">
                        <i class="fas fa-info-circle"></i> 
                        Selecciona un usuario para añadirlo a la lista de permisos.
                    </p>
                    
                    <div id="listaUsuarios">
                        <?php foreach ($usuarios_disponibles as $usuario): ?>
                            <div class="usuario-item" data-nombre="<?php echo strtolower($usuario['nombre_completo']); ?>" data-usuario="<?php echo strtolower($usuario['usuario']); ?>">
                                <div class="info">
                                    <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>
                                    <code><?php echo htmlspecialchars($usuario['usuario']); ?></code>
                                    <?php if ($usuario['rol_id'] == 2): ?>
                                        <span class="badge badge-supervisor" style="font-size: 11px;">
                                            <i class="fas fa-user-tie"></i> Supervisor
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-usuario" style="font-size: 11px;">
                                            <i class="fas fa-user"></i> Usuario
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn-add-usuario-modal" onclick="agregarUsuario(<?php echo $usuario['id']; ?>)">
                                    <i class="fas fa-plus"></i> Añadir
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // ===== FUNCIONES DEL MODAL =====
        function openModal() {
            document.getElementById('modalAddUser').classList.add('active');
            document.body.style.overflow = 'hidden';
            // Limpiar buscador al abrir
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.value = '';
            }
            document.querySelectorAll('.usuario-item').forEach(item => {
                item.classList.remove('hidden');
            });
        }

        function closeModal() {
            document.getElementById('modalAddUser').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalAddUser').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // ===== FILTRAR USUARIOS EN EL MODAL =====
        function filtrarUsuarios() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const items = document.querySelectorAll('.usuario-item');
            
            items.forEach(item => {
                const nombre = item.getAttribute('data-nombre') || '';
                const usuario = item.getAttribute('data-usuario') || '';
                
                if (nombre.includes(filter) || usuario.includes(filter)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }

        // ===== AGREGAR USUARIO =====
        function agregarUsuario(id) {
            window.location.href = '?agregar=' + id;
        }

        // ===== ELIMINAR USUARIO DE LA LISTA =====
        function eliminarUsuario(usuarioId) {
            window.location.href = `?eliminar=${usuarioId}`;
        }
    </script>
</body>
</html>