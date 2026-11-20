<?php
// admin/directorio/index.php - Administración del Directorio Corporativo
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

// Si es usuario normal (rol 3), no puede entrar
if ($rol_usuario == 3) {
    header('Location: ../../index.php?error=sin_permiso');
    exit();
}

// Verificar permiso específico si es Supervisor (rol 2)
if ($rol_usuario == 2) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM permisos_usuarios pu
            JOIN modulos m ON pu.modulo_id = m.id
            WHERE pu.usuario_id = ? AND m.nombre = 'Directorio Corporativo' AND m.activo = 1
        ");
        $stmt->execute([$_SESSION['usuario_id']]);
        if ($stmt->fetchColumn() == 0) {
            header('Location: ../index.php?error=sin_permiso');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error al verificar permiso Directorio: " . $e->getMessage());
        header('Location: ../index.php?error=error_permisos');
        exit();
    }
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones POST (Añadir usuario por ID o Quitar del directorio)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);

        if ($usuario_id <= 0) {
            $mensaje = 'Por favor selecciona un usuario válido de la lista.';
            $tipo_mensaje = 'error';
        } else {
            try {
                // Verificar que exista y no esté ya en directorio
                $check = $pdo->prepare("SELECT COUNT(*) FROM directorio WHERE usuario_id = ?");
                $check->execute([$usuario_id]);

                if ($check->fetchColumn() > 0) {
                    $mensaje = 'Este usuario ya se encuentra en el directorio corporativo.';
                    $tipo_mensaje = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO directorio (usuario_id) VALUES (?)");
                    $stmt->execute([$usuario_id]);
                    $mensaje = 'Persona añadida correctamente al directorio corporativo.';
                    $tipo_mensaje = 'exito';
                }
            } catch (PDOException $e) {
                error_log("Error al añadir usuario al directorio: " . $e->getMessage());
                $mensaje = 'Ocurrió un error al añadir el usuario al directorio.';
                $tipo_mensaje = 'error';
            }
        }
    } elseif ($accion === 'eliminar') {
        $directorio_id = (int)($_POST['directorio_id'] ?? 0);

        if ($directorio_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM directorio WHERE id = ?");
                $stmt->execute([$directorio_id]);
                $mensaje = 'Persona retirada del directorio corporativo.';
                $tipo_mensaje = 'exito';
            } catch (PDOException $e) {
                error_log("Error al quitar usuario del directorio: " . $e->getMessage());
                $mensaje = 'Ocurrió un error al quitar la persona del directorio.';
                $tipo_mensaje = 'error';
            }
        }
    }
}

// 1. Obtener usuarios activos que AÚN NO están en el directorio (para el selector de añadir)
try {
    $stmtDisponibles = $pdo->query("
        SELECT u.id, u.nombre_completo, u.cargo, u.email, u.telefono
        FROM usuarios u
        LEFT JOIN directorio d ON u.id = d.usuario_id
        WHERE u.activo = 1 AND d.id IS NULL
        ORDER BY u.nombre_completo ASC
    ");
    $usuarios_disponibles = $stmtDisponibles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios_disponibles = [];
    error_log("Error al cargar usuarios disponibles: " . $e->getMessage());
}

// 2. Obtener personas actualmente en el directorio (JOIN con usuarios)
try {
    $stmtDirectorio = $pdo->query("
        SELECT 
            d.id AS directorio_id,
            u.id AS usuario_id,
            u.nombre_completo,
            COALESCE(NULLIF(TRIM(u.cargo), ''), 'Sin cargo asignado') AS cargo,
            COALESCE(NULLIF(TRIM(u.email), ''), 'Sin correo') AS email,
            COALESCE(NULLIF(TRIM(u.telefono), ''), 'Sin celular') AS telefono
        FROM directorio d
        INNER JOIN usuarios u ON d.usuario_id = u.id
        WHERE u.activo = 1
        ORDER BY u.nombre_completo ASC
    ");
    $personas_directorio = $stmtDirectorio->fetchAll(PDO::FETCH_ASSOC);

    // Extraer cargos únicos para el filtro desplegable
    $cargos_unicos = array_unique(array_column($personas_directorio, 'cargo'));
    sort($cargos_unicos, SORT_LOCALE_STRING);
} catch (PDOException $e) {
    $personas_directorio = [];
    $cargos_unicos = [];
    $mensaje = 'Error al consultar el directorio corporativo.';
    $tipo_mensaje = 'error';
    error_log("Error al cargar directorio: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio Corporativo | Panel de Administración</title>
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

        /* Header */
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }

        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo {
            align-items: center;
            gap: 12px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
            flex-wrap: wrap;
        }

        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* Alertas */
        .alerta {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .alerta.ocultando {
            opacity: 0;
            transform: translateY(-8px);
        }

        .alerta-exito {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alerta-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        /* Paneles */
        .panel-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .panel-box h2 {
            margin-bottom: 20px;
            color: #12232b;
            border-left: 4px solid #173742;
            padding-left: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Formulario de selección de usuario */
        .add-grid {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 15px;
            align-items: end;
        }

        @media (max-width: 900px) {
            .add-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #12232b;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #cfd8dc;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #2c3e50;
            transition: border-color 0.3s;
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: #173742;
            background: #ffffff;
        }

        .btn-primary {
            background: #173742;
            color: white;
            border: none;
            padding: 11px 22px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-primary:hover {
            background: #12232b;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            background: #90a4ae;
            cursor: not-allowed;
            transform: none;
        }

        .helper-note {
            margin-top: 10px;
            font-size: 12px;
            color: #607d8b;
        }

        /* Barra de búsqueda y filtro */
        .filtros-bar {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .filtros-bar {
                grid-template-columns: 1fr;
            }
        }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }

        .input-icon-wrap .form-control {
            padding-left: 40px;
        }

        .contador-badge {
            background: #173742;
            color: white;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .btn-secundario {
            background: #cfd8dc;
            color: #2c3e50;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-secundario:hover {
            background: #b0bec5;
        }

        /* Tabla */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead {
            background: #12232b;
            color: white;
        }

        th, td {
            padding: 13px 15px;
            text-align: left;
            border-bottom: 1px solid #eceff1;
        }

        th {
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        tbody tr:hover {
            background: #f5f7f8;
        }

        .cargo-pill {
            display: inline-block;
            background: #e8eef0;
            color: #173742;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-eliminar {
            background: #dc3545;
            color: white;
            border: none;
            padding: 7px 13px;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .btn-eliminar:hover {
            background: #bd2130;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 42px;
            color: #b0bec5;
            margin-bottom: 10px;
        }

        /* Paginación */
        .paginacion-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eceff1;
        }

        .paginacion-info {
            font-size: 13px;
            color: #607d8b;
            font-weight: 500;
        }

        .paginacion-controles {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .page-btn {
            border: 1px solid #cfd8dc;
            background: #f8f9fa;
            color: #2c3e50;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .page-btn:hover:not(:disabled) {
            background: #173742;
            color: white;
            border-color: #173742;
        }

        .page-btn.activo {
            background: #12232b;
            color: #ffc107;
            border-color: #12232b;
        }

        .page-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        /* Modal Confirmación Eliminar */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(18, 35, 43, 0.72);
            backdrop-filter: blur(2px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-overlay.activo {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(15px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-card {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.22s ease-out;
        }

        .modal-header {
            background: #12232b;
            color: white;
            padding: 16px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #dc3545;
        }

        .modal-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cerrar-modal {
            background: none;
            border: none;
            color: #b0bec5;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            transition: color 0.2s;
        }

        .btn-cerrar-modal:hover {
            color: white;
        }

        .modal-body {
            padding: 24px 22px 18px;
            text-align: center;
        }

        .modal-icon-danger {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #fdecea;
            color: #dc3545;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 14px;
        }

        .modal-body p {
            font-size: 14px;
            color: #455a64;
            line-height: 1.5;
        }

        .modal-persona-nombre {
            display: block;
            margin: 8px 0;
            font-size: 16px;
            font-weight: 700;
            color: #12232b;
        }

        .modal-subtext {
            font-size: 12px;
            color: #78909c;
            margin-top: 6px;
        }

        .modal-footer {
            padding: 14px 22px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #f8f9fa;
            border-top: 1px solid #eceff1;
        }

        .btn-modal-cancelar {
            background: #cfd8dc;
            color: #2c3e50;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-modal-cancelar:hover {
            background: #b0bec5;
        }

        .btn-modal-confirmar {
            background: #dc3545;
            color: white;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .btn-modal-confirmar:hover {
            background: #bd2130;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Directorio Corporativo</small>
            </div>
            <div class="user-info">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje !== ''): ?>
            <div class="alerta <?php echo $tipo_mensaje === 'exito' ? 'alerta-exito' : 'alerta-error'; ?>">
                <i class="fas <?php echo $tipo_mensaje === 'exito' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($mensaje); ?></span>
            </div>
        <?php endif; ?>

        <!-- Bloque para Añadir Usuario al Directorio -->
        <div class="panel-box">
            <h2>
                <span><i class="fas fa-user-plus"></i> Añadir Persona al Directorio</span>
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="agregar">
                <div class="add-grid">
                    <div class="form-group">
                        <label for="filtroUsuarioSelector">Filtrar lista de usuarios</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-filter"></i>
                            <input type="text" id="filtroUsuarioSelector" class="form-control" placeholder="Escribe nombre o cargo...">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="usuario_id">Seleccionar Usuario Disponible *</label>
                        <select name="usuario_id" id="usuario_id" class="form-control" required <?php echo empty($usuarios_disponibles) ? 'disabled' : ''; ?>>
                            <?php if (empty($usuarios_disponibles)): ?>
                                <option value="">Todos los usuarios activos ya están en el directorio</option>
                            <?php else: ?>
                                <option value="">-- Selecciona una persona (<?php echo count($usuarios_disponibles); ?> disponibles) --</option>
                                <?php foreach ($usuarios_disponibles as $u): ?>
                                    <?php
                                        $cargo_txt = !empty(trim($u['cargo'] ?? '')) ? $u['cargo'] : 'Sin cargo';
                                        $label = $u['nombre_completo'] . ' — ' . $cargo_txt;
                                    ?>
                                    <option value="<?php echo (int)$u['id']; ?>"
                                            data-search="<?php echo htmlspecialchars(mb_strtolower($u['nombre_completo'] . ' ' . $cargo_txt . ' ' . ($u['email'] ?? ''))); ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn-primary" <?php echo empty($usuarios_disponibles) ? 'disabled' : ''; ?>>
                            <i class="fas fa-plus-circle"></i> Añadir al Directorio
                        </button>
                    </div>
                </div>
                <p class="helper-note">
                    <i class="fas fa-info-circle"></i> Los datos (nombre, cargo, correo y celular) se sincronizan automáticamente desde el perfil de cada usuario.
                </p>
            </form>
        </div>

        <!-- Listado Actual del Directorio con Buscador, Filtro y Paginación -->
        <div class="panel-box">
            <h2>
                <span><i class="fas fa-address-book"></i> Personas en el Directorio Corporativo</span>
                <span class="contador-badge" id="contadorRegistros"><?php echo count($personas_directorio); ?> registros</span>
            </h2>

            <div class="filtros-bar">
                <div class="input-icon-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscadorInput" class="form-control" placeholder="Buscar por nombre, cargo, correo o celular...">
                </div>
                <div>
                    <select id="filtroCargo" class="form-control">
                        <option value="">Todos los cargos</option>
                        <?php foreach ($cargos_unicos as $c): ?>
                            <option value="<?php echo htmlspecialchars(mb_strtolower($c)); ?>">
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="button" id="btnLimpiarFiltros" class="btn-secundario" style="height: 100%; width: 100%;">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tablaDirectorio">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cargo</th>
                            <th>Correo</th>
                            <th>Celular</th>
                            <th style="width: 120px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($personas_directorio)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <p>Aún no has añadido personas al directorio corporativo.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($personas_directorio as $p): ?>
                                <tr class="fila-persona"
                                    data-nombre="<?php echo htmlspecialchars(mb_strtolower($p['nombre_completo'])); ?>"
                                    data-cargo="<?php echo htmlspecialchars(mb_strtolower($p['cargo'])); ?>"
                                    data-correo="<?php echo htmlspecialchars(mb_strtolower($p['email'])); ?>"
                                    data-celular="<?php echo htmlspecialchars(mb_strtolower($p['telefono'])); ?>">
                                    <td><strong><?php echo htmlspecialchars($p['nombre_completo']); ?></strong></td>
                                    <td><span class="cargo-pill"><?php echo htmlspecialchars($p['cargo']); ?></span></td>
                                    <td><?php echo htmlspecialchars($p['email']); ?></td>
                                    <td><?php echo htmlspecialchars($p['telefono']); ?></td>
                                    <td>
                                        <button type="button" class="btn-eliminar"
                                            onclick="abrirModalEliminar(
                                                <?php echo (int)$p['directorio_id']; ?>,
                                                <?php echo htmlspecialchars(json_encode($p['nombre_completo']), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo htmlspecialchars(json_encode($p['cargo']), ENT_QUOTES, 'UTF-8'); ?>
                                            )">
                                            <i class="fas fa-trash-alt"></i> Quitar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr id="filaSinResultados" style="display: none;">
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <p>No se encontraron personas que coincidan con tu búsqueda o filtro.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Controles de Paginación (aparece cuando hay más de 10 registros) -->
            <div class="paginacion-wrapper" id="paginacionWrapper" style="display: none;">
                <div class="paginacion-info" id="paginacionInfo"></div>
                <div class="paginacion-controles" id="paginacionControles"></div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación para Quitar del Directorio -->
    <div class="modal-overlay" id="modalEliminar" role="dialog" aria-modal="true" aria-labelledby="modalEliminarTitulo">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalEliminarTitulo"><i class="fas fa-user-minus"></i> Retirar del Directorio</h3>
                <button type="button" class="btn-cerrar-modal" onclick="cerrarModalEliminar()" aria-label="Cerrar">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="directorio_id" id="delete_directorio_id">
                <div class="modal-body">
                    <div class="modal-icon-danger">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <p>¿Estás seguro de que deseas quitar del directorio corporativo a:</p>
                    <span class="modal-persona-nombre" id="delete_nombre_persona"></span>
                    <span class="cargo-pill" id="delete_cargo_persona"></span>
                    <p class="modal-subtext">Su cuenta de usuario en la intranet no se eliminará y podrás volver a añadirla cuando quieras.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancelar" onclick="cerrarModalEliminar()">Cancelar</button>
                    <button type="submit" class="btn-modal-confirmar">
                        <i class="fas fa-trash-alt"></i> Sí, quitar del directorio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 1. Filtro rápido para el select de usuarios disponibles
        const filtroUsuarioSelector = document.getElementById('filtroUsuarioSelector');
        const selectUsuario = document.getElementById('usuario_id');

        if (filtroUsuarioSelector && selectUsuario) {
            const opcionesOriginales = Array.from(selectUsuario.options);

            filtroUsuarioSelector.addEventListener('input', () => {
                const term = filtroUsuarioSelector.value.toLowerCase().trim();
                selectUsuario.innerHTML = '';

                const coincidencias = opcionesOriginales.filter((opt, idx) => {
                    if (idx === 0) return true; // Mantener opción placeholder
                    const textoBusqueda = opt.dataset.search || opt.text.toLowerCase();
                    return textoBusqueda.includes(term);
                });

                coincidencias.forEach(opt => selectUsuario.appendChild(opt));

                if (coincidencias.length === 2 && term !== '') {
                    selectUsuario.selectedIndex = 1; // Auto-seleccionar si solo queda 1 resultado
                } else {
                    selectUsuario.selectedIndex = 0;
                }
            });
        }

        // 2. Buscador, filtro por cargo y paginación (10 registros por página)
        const buscadorInput = document.getElementById('buscadorInput');
        const filtroCargo = document.getElementById('filtroCargo');
        const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
        const filas = Array.from(document.querySelectorAll('.fila-persona'));
        const filaSinResultados = document.getElementById('filaSinResultados');
        const contadorRegistros = document.getElementById('contadorRegistros');

        const paginacionWrapper = document.getElementById('paginacionWrapper');
        const paginacionInfo = document.getElementById('paginacionInfo');
        const paginacionControles = document.getElementById('paginacionControles');

        const REGISTROS_POR_PAGINA = 10;
        let paginaActual = 1;

        function renderizarTabla(reiniciarPagina = false) {
            if (reiniciarPagina) {
                paginaActual = 1;
            }

            const texto = buscadorInput.value.toLowerCase().trim();
            const cargoSeleccionado = filtroCargo.value.toLowerCase().trim();

            // 1. Filtrar filas coincidentes
            const filasFiltradas = filas.filter(fila => {
                const nombre = fila.dataset.nombre || '';
                const cargo = fila.dataset.cargo || '';
                const correo = fila.dataset.correo || '';
                const celular = fila.dataset.celular || '';

                const coincideTexto = !texto ||
                    nombre.includes(texto) ||
                    cargo.includes(texto) ||
                    correo.includes(texto) ||
                    celular.includes(texto);

                const coincideCargo = !cargoSeleccionado || cargo === cargoSeleccionado;

                return coincideTexto && coincideCargo;
            });

            const totalFiltrados = filasFiltradas.length;
            const totalPaginas = Math.ceil(totalFiltrados / REGISTROS_POR_PAGINA);

            if (paginaActual > totalPaginas && totalPaginas > 0) {
                paginaActual = totalPaginas;
            }

            const inicio = (paginaActual - 1) * REGISTROS_POR_PAGINA;
            const fin = inicio + REGISTROS_POR_PAGINA;

            // 2. Mostrar solo las 10 filas correspondientes a la página actual
            filas.forEach(fila => fila.style.display = 'none');
            filasFiltradas.slice(inicio, fin).forEach(fila => fila.style.display = '');

            // 3. Estado vacío y contador
            if (filas.length > 0) {
                filaSinResultados.style.display = totalFiltrados === 0 ? '' : 'none';
            }
            contadorRegistros.textContent = `${totalFiltrados} ${totalFiltrados === 1 ? 'registro' : 'registros'}`;

            // 4. Renderizar paginación solo si supera los 10 registros
            if (totalFiltrados > REGISTROS_POR_PAGINA) {
                paginacionWrapper.style.display = 'flex';
                const mostrandoHasta = Math.min(fin, totalFiltrados);
                paginacionInfo.textContent = `Mostrando ${inicio + 1} - ${mostrandoHasta} de ${totalFiltrados} personas`;

                paginacionControles.innerHTML = '';

                // Botón Anterior
                const btnPrev = document.createElement('button');
                btnPrev.type = 'button';
                btnPrev.className = 'page-btn';
                btnPrev.innerHTML = '<i class="fas fa-chevron-left"></i>';
                btnPrev.disabled = paginaActual === 1;
                btnPrev.title = 'Anterior';
                btnPrev.addEventListener('click', () => {
                    if (paginaActual > 1) {
                        paginaActual--;
                        renderizarTabla(false);
                    }
                });
                paginacionControles.appendChild(btnPrev);

                // Botones numerados
                for (let i = 1; i <= totalPaginas; i++) {
                    const btnNum = document.createElement('button');
                    btnNum.type = 'button';
                    btnNum.className = 'page-btn' + (i === paginaActual ? ' activo' : '');
                    btnNum.textContent = i;
                    btnNum.addEventListener('click', () => {
                        paginaActual = i;
                        renderizarTabla(false);
                    });
                    paginacionControles.appendChild(btnNum);
                }

                // Botón Siguiente
                const btnNext = document.createElement('button');
                btnNext.type = 'button';
                btnNext.className = 'page-btn';
                btnNext.innerHTML = '<i class="fas fa-chevron-right"></i>';
                btnNext.disabled = paginaActual === totalPaginas;
                btnNext.title = 'Siguiente';
                btnNext.addEventListener('click', () => {
                    if (paginaActual < totalPaginas) {
                        paginaActual++;
                        renderizarTabla(false);
                    }
                });
                paginacionControles.appendChild(btnNext);
            } else {
                paginacionWrapper.style.display = 'none';
            }
        }

        buscadorInput.addEventListener('input', () => renderizarTabla(true));
        filtroCargo.addEventListener('change', () => renderizarTabla(true));
        btnLimpiarFiltros.addEventListener('click', () => {
            buscadorInput.value = '';
            filtroCargo.value = '';
            renderizarTabla(true);
        });

        // Inicializar paginación al cargar la página
        renderizarTabla(true);

        // 3. Control del Modal de Confirmación para Eliminar
        const modalEliminar = document.getElementById('modalEliminar');
        const deleteDirectorioId = document.getElementById('delete_directorio_id');
        const deleteNombrePersona = document.getElementById('delete_nombre_persona');
        const deleteCargoPersona = document.getElementById('delete_cargo_persona');

        function abrirModalEliminar(directorioId, nombre, cargo) {
            deleteDirectorioId.value = directorioId;
            deleteNombrePersona.textContent = nombre;
            deleteCargoPersona.textContent = cargo;
            modalEliminar.classList.add('activo');
        }

        function cerrarModalEliminar() {
            modalEliminar.classList.remove('activo');
        }

        modalEliminar.addEventListener('click', (e) => {
            if (e.target === modalEliminar) {
                cerrarModalEliminar();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modalEliminar.classList.contains('activo')) {
                cerrarModalEliminar();
            }
        });

        // 4. Ocultar alerta automáticamente después de 2 segundos
        const alertaMensaje = document.querySelector('.alerta');
        if (alertaMensaje) {
            setTimeout(() => {
                alertaMensaje.classList.add('ocultando');
                setTimeout(() => alertaMensaje.remove(), 400);
            }, 2000);
        }
    </script>
</body>
</html>