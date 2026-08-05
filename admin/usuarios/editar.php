<?php
// admin/usuarios/editar.php - Editar usuario
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores (rol_id = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

// Solo administradores pueden editar usuarios
if ($rol_usuario != 1) {
    $_SESSION['error'] = "❌ No tienes permiso para editar usuarios";
    header('Location: ../index.php');
    exit();
}

$id = $_GET['id'] ?? 0;
$error = '';
$mensaje = '';

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header("Location: index.php?error=" . urlencode("❌ Usuario no encontrado"));
    exit();
}

// Obtener roles para el select
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// PERFILES Y CARGOS - CONFIGURACIÓN
// ============================================

$perfiles_cargos = [
    'Directivo' => [
        'Gerente General',
        'Gerente Regional',
        'Gerente Operativo Regional',
        'Gerente Comercial',
        'Gerente Comerical FAW',
        'Gerente Comercial Usados',
        'Gerente Flotillas',
        'Director de Servicio',
        'Directora Administrativa',
        'Directora Financiera',
        'Director de Experiencia al Cliente'
    ],
    'Líder / Coordinador / Jefatura' => [
        'Líder de Cartera',
        'Líder de Calidad',
        'Líder Comercial Posventa',
        'Líder de Compras',
        'Líder de Mercadeo',
        'Líder Logística',
        'Líder Financiero',
        'Líder de Sistemas',
        'Líder de Contabilidad',
        'Líder Servicio al Cliente',
        'Líder de Garantías',
        'Líder de Posventa',
        'Líder de Servicio',
        'Líder Comercial',
        'Líder Administrativa',
        'Coordinador Call Center',
        'Coordinador de Taller Mecánica',
        'Coordinador de Seguridad y Salud',
        'Coordinador de Vitrina',
        'Coordinadora Colisión',
        'Jefe de Taller',
        'Jefe de Taller Colisión'
    ],
    'Administrativo' => [
        'Auxiliar Contable',
        'Asistente Contable',
        'Auxiliar Sistemas',
        'Analista de Compras',
        'Analista de Datos',
        'Analista de Garantías',
        'Asistente Administrativo',
        'Asistente Comercial',
        'Asistente Comercial Usados',
        'Asistente Control Interno',
        'Asistente Gestión Humana',
        'Asistente de Contenido',
        'Auxiliar Administrativo',
        'Tesorera',
        'Oficial de Cumplimiento'
    ],
    'Comercial' => [
        'Asesor (a) Comercial',
        'Asesor (a) Comercial Digital',
        'Asesor Digital',
        'Asesor de Entregas',
        'Asesor de Seguros',
        'Auxiliar Comercial',
        'Auxiliar de Seguros',
        'Asistente Comercial',
        'Asistente Comercial Usados'
    ],
    'Servicio al Cliente' => [
        'Agente Contact Center',
        'Anfitrión de Servicio',
        'Asesor (a) de Servicios'
    ],
    'Técnico Mecánica' => [
        'Técnico Básico',
        'Técnico Certificado',
        'Técnico Experto',
        'Técnico Maestro',
        'Técnico Mentor',
        'Técnico Armador',
        'Técnico Alineador y Balanceo'
    ],
    'Técnico Colisión y Pintura' => [
        'Técnico Latonero',
        'Técnico Pintor',
        'Técnico Preparador'
    ],
    'Alistamiento y Accesorios' => [
        'Alistador',
        'Alistador e Instalador de Accesorios',
        'Técnico Instalador de Accesorios',
        'Movilizador',
        'Validador de Calidad'
    ],
    'Operativo Posventa' => [
        'Auxiliar de Bodega',
        'Asistente de Taller',
        'Asistente de Taller y Garantías',
        'Asistente Colisión',
        'Asesor de Repuestos',
        'Supernumerario'
    ],
    'Aprendiz' => [
        'Aprendiz SENA Etapa Productiva'
    ]
];

$perfiles = array_keys($perfiles_cargos);

// ============================================
// SEDES - CONFIGURACIÓN
// ============================================

$sedes = [
    'Manizales',
    'Pereira',
    'Armenia',
    'Cartago',
    'La Dorada'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $usuario_nombre = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol_id = intval($_POST['rol_id'] ?? 3);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $telefono = trim($_POST['telefono'] ?? '');
    $perfil_usuario = trim($_POST['perfil_usuario'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $sede = trim($_POST['sede'] ?? '');
    
    if (empty($nombre_completo) || empty($usuario_nombre)) {
        $error = "❌ Nombre completo y usuario son obligatorios";
    } else {
        try {
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error = "❌ La contraseña debe tener al menos 6 caracteres";
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET 
                                           nombre_completo = ?, 
                                           usuario = ?, 
                                           email = ?,
                                           password = ?, 
                                           rol_id = ?, 
                                           activo = ?,
                                           telefono = ?,
                                           perfil_usuario = ?,
                                           cargo = ?,
                                           sede = ?
                                           WHERE id = ?");
                    $stmt->execute([
                        $nombre_completo, 
                        $usuario_nombre, 
                        $email, 
                        $password_hash, 
                        $rol_id, 
                        $activo,
                        $telefono,
                        $perfil_usuario,
                        $cargo,
                        $sede,
                        $id
                    ]);
                    $mensaje = "✅ Usuario actualizado correctamente";
                }
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET 
                                       nombre_completo = ?, 
                                       usuario = ?, 
                                       email = ?,
                                       rol_id = ?, 
                                       activo = ?,
                                       telefono = ?,
                                       perfil_usuario = ?,
                                       cargo = ?,
                                       sede = ?
                                       WHERE id = ?");
                $stmt->execute([
                    $nombre_completo, 
                    $usuario_nombre, 
                    $email, 
                    $rol_id, 
                    $activo,
                    $telefono,
                    $perfil_usuario,
                    $cargo,
                    $sede,
                    $id
                ]);
                $mensaje = "✅ Usuario actualizado correctamente";
            }
            
            // ============================================
            // ✅ ELIMINAR PERMISOS SI EL USUARIO YA NO ES SUPERVISOR
            // ============================================
            if ($rol_id != 2) {
                $stmt_delete = $pdo->prepare("DELETE FROM permisos_usuarios WHERE usuario_id = ?");
                $stmt_delete->execute([$id]);
            }
            
            // Recargar datos si fue exitoso
            if (empty($error)) {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "❌ Error al actualizar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 700px; margin: 50px auto; padding: 20px; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 25px; }
        .card h2 { margin-bottom: 20px; border-left: 4px solid #173742; padding-left: 15px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px 15px; border: 1px solid #d3d3d3; border-radius: 8px; font-family: 'Poppins', sans-serif; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #173742; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
        .btn-guardar { background: #173742; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; transition: all 0.3s ease; }
        .btn-guardar:hover { background: #445960; transform: translateY(-2px); }
        .mensaje-exito { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .mensaje-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        .info-text { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        .btn-volver { background: #445960; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; transition: all 0.3s ease; display: inline-block; }
        .btn-volver:hover { background: #ffc107; color: #12232b; transform: translateY(-2px); }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        
        <div class="card">
            <h2><i class="fas fa-user-edit"></i> Editar Usuario</h2>
            
            <?php if ($mensaje): ?>
                <div class="mensaje-exito">
                    <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mensaje-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- Información básica -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre Completo *</label>
                        <input type="text" name="nombre_completo" value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Usuario *</label>
                        <input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Contraseña (dejar en blanco para no cambiar)</label>
                        <input type="password" name="password" placeholder="Nueva contraseña">
                        <div class="info-text">Mínimo 6 caracteres</div>
                    </div>
                </div>

                <!-- Información de contacto -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Teléfono / Celular</label>
                        <input type="text" name="telefono" placeholder="Ej: 3001234567" value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Sede</label>
                        <select name="sede">
                            <option value="">Seleccione una sede</option>
                            <?php foreach ($sedes as $sede_opcion): ?>
                                <option value="<?php echo htmlspecialchars($sede_opcion); ?>" <?php echo ($usuario['sede'] ?? '') == $sede_opcion ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sede_opcion); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Perfil y Cargo con dependencia -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Perfil</label>
                        <select name="perfil_usuario" id="perfil_usuario" onchange="actualizarCargos()">
                            <option value="">Seleccione un perfil</option>
                            <?php foreach ($perfiles as $perfil): ?>
                                <option value="<?php echo htmlspecialchars($perfil); ?>" <?php echo ($usuario['perfil_usuario'] ?? '') == $perfil ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($perfil); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cargo</label>
                        <select name="cargo" id="cargo">
                            <option value="">Seleccione un perfil primero</option>
                            <?php 
                            $perfil_actual = $usuario['perfil_usuario'] ?? '';
                            $cargo_actual = $usuario['cargo'] ?? '';
                            if ($perfil_actual && isset($perfiles_cargos[$perfil_actual])) {
                                foreach ($perfiles_cargos[$perfil_actual] as $cargo_opcion) {
                                    $selected = ($cargo_actual == $cargo_opcion) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($cargo_opcion) . '" ' . $selected . '>' . htmlspecialchars($cargo_opcion) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Rol y estado -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol_id">
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id']; ?>" <?php echo $usuario['rol_id'] == $rol['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rol['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group checkbox-group" style="display: flex; align-items: center; padding-top: 30px;">
                        <input type="checkbox" name="activo" <?php echo $usuario['activo'] ? 'checked' : ''; ?>>
                        <label style="margin: 0;">Usuario Activo</label>
                    </div>
                </div>

                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // PERFILES Y CARGOS - JavaScript
        // ============================================
        
        const perfilesCargos = <?php echo json_encode($perfiles_cargos); ?>;
        const cargoActual = '<?php echo htmlspecialchars($usuario['cargo'] ?? ''); ?>';
        
        function actualizarCargos() {
            const perfilSelect = document.getElementById('perfil_usuario');
            const cargoSelect = document.getElementById('cargo');
            const perfilSeleccionado = perfilSelect.value;
            
            // Limpiar cargos
            cargoSelect.innerHTML = '<option value="">Seleccione un cargo</option>';
            
            if (perfilSeleccionado && perfilesCargos[perfilSeleccionado]) {
                const cargos = perfilesCargos[perfilSeleccionado];
                cargos.forEach(function(cargo) {
                    const option = document.createElement('option');
                    option.value = cargo;
                    option.textContent = cargo;
                    // Si el cargo coincide con el actual, seleccionarlo
                    if (cargo === cargoActual) {
                        option.selected = true;
                    }
                    cargoSelect.appendChild(option);
                });
            }
        }
        
        // Ejecutar al cargar la página si ya hay un perfil seleccionado
        document.addEventListener('DOMContentLoaded', function() {
            const perfilSelect = document.getElementById('perfil_usuario');
            if (perfilSelect.value) {
                actualizarCargos();
            }
        });
    </script>
</body>
</html>