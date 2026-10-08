<?php
// admin/pqrs/gestores.php - Gestión de gestores de PQRS
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

// Admin y Supervisor: acceso total
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar gestores";
    header('Location: ../../index.php');
    exit();
}

$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

// ============================================
// PROCESAR ACCIONES (con patrón PRG)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ============================================
    // AGREGAR GESTOR
    // ============================================
    if ($action === 'agregar') {
        $nuevos_ids = $_POST['usuarios_ids'] ?? [];
        
        // Sanitizar y filtrar IDs válidos
        $nuevos_ids = array_filter(array_map('intval', $nuevos_ids), fn($id) => $id > 0);
        $nuevos_ids = array_unique($nuevos_ids);
        
        if (empty($nuevos_ids)) {
            $_SESSION['error'] = "❌ Debes seleccionar al menos un usuario";
        } else {
            try {
                $pdo->beginTransaction();
                
                $agregados = 0;
                $ya_existentes = 0;
                
                foreach ($nuevos_ids as $nuevo_usuario_id) {
                    // Verificar si ya existe
                    $stmt = $pdo->prepare("SELECT id FROM pqrs_gestores_usuarios WHERE usuario_id = ?");
                    $stmt->execute([$nuevo_usuario_id]);
                    $existente = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existente) {
                        $ya_existentes++;
                    } else {
                        // Insertar nuevo
                        $stmt = $pdo->prepare("INSERT INTO pqrs_gestores_usuarios (usuario_id, añadido_por, fecha_agregado, activo) VALUES (?, ?, NOW(), 1)");
                        $stmt->execute([$nuevo_usuario_id, $usuario_id]);
                        $agregados++;
                    }
                }
                
                $pdo->commit();
                
                // Construir mensaje según resultados
                $partes = [];
                if ($agregados > 0)     $partes[] = "$agregados añadido" . ($agregados > 1 ? "s" : "");
                if ($ya_existentes > 0) $partes[] = "$ya_existentes ya existente" . ($ya_existentes > 1 ? "s" : "");
                
                if (!empty($partes)) {
                    $_SESSION['mensaje'] = "✅ " . implode(", ", $partes) . " correctamente";
                }
                
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['error'] = "❌ Error al añadir gestores: " . $e->getMessage();
            }
        }
        
        // 🔑 PRG: redirigir para evitar reenvío del formulario
        header('Location: gestores.php');
        exit();
    }
    
    // ============================================
    // QUITAR GESTOR (DELETE físico)
    // ============================================
    elseif ($action === 'quitar') {
        $gestor_id = intval($_POST['gestor_id'] ?? 0);
        
        if ($gestor_id <= 0) {
            $_SESSION['error'] = "❌ Gestor no válido";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Obtener info del gestor antes de borrar
                $stmt = $pdo->prepare("
                    SELECT gu.usuario_id, u.nombre_completo 
                    FROM pqrs_gestores_usuarios gu 
                    INNER JOIN usuarios u ON gu.usuario_id = u.id 
                    WHERE gu.id = ?
                ");
                $stmt->execute([$gestor_id]);
                $gestor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$gestor) {
                    throw new Exception("Gestor no encontrado");
                }
                
                // 🔑 Desasignar PQRS que tuviera este gestor (evita huérfanas / FK)
                $stmt = $pdo->prepare("UPDATE pqrs SET gestor_principal_id = NULL WHERE gestor_principal_id = ?");
                $stmt->execute([$gestor['usuario_id']]);
                
                // 🔑 Eliminar físicamente el registro
                $stmt = $pdo->prepare("DELETE FROM pqrs_gestores_usuarios WHERE id = ?");
                $stmt->execute([$gestor_id]);
                
                $pdo->commit();
                $_SESSION['mensaje'] = "✅ " . $gestor['nombre_completo'] . " eliminado correctamente";
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['error'] = "❌ Error al eliminar gestor: " . $e->getMessage();
            }
        }
        
        // 🔑 PRG
        header('Location: gestores.php');
        exit();
    }
}

// ============================================
// OBTENER GESTORES ACTIVOS
// ============================================
$stmt = $pdo->query("
    SELECT 
        gu.id,
        gu.usuario_id,
        gu.fecha_agregado,
        u.nombre_completo,
        u.usuario,
        u.email,
        u.cargo,
        u.sede,
        u2.nombre_completo as agregado_por_nombre
    FROM pqrs_gestores_usuarios gu
    INNER JOIN usuarios u ON gu.usuario_id = u.id
    LEFT JOIN usuarios u2 ON gu.añadido_por = u2.id
    WHERE gu.activo = 1
    ORDER BY u.nombre_completo ASC
");
$gestores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// OBTENER USUARIOS DISPONIBLES (no gestores)
// ============================================
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.nombre_completo,
        u.usuario,
        u.email,
        u.cargo,
        u.sede
    FROM usuarios u
    WHERE u.activo = 1
    AND u.id NOT IN (
        SELECT usuario_id 
        FROM pqrs_gestores_usuarios
    )
    ORDER BY u.nombre_completo ASC
");
$usuarios_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recuperar mensajes de sesión
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestores PQRS | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        .header .container {
            max-width: 1200px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo img { width: 130px; }
        .logo-subtitle {
            color: #8aa8b8;
            font-size: 14px;
            margin-top: -5px;
        }
        .btn-volver {
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* ===== MENSAJES ===== */
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .card-header h2 {
            font-size: 22px;
            color: #12232b;
            border-left: 4px solid #e67e22;
            padding-left: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header h2 i { color: #e67e22; }
        .card-header p {
            color: #7f8c8d;
            font-size: 13px;
            margin-top: 5px;
            padding-left: 19px;
        }

        /* ===== BOTÓN AÑADIR ===== */
        .btn-añadir {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
            white-space: nowrap;
        }
        .btn-añadir:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(39,174,96,0.4);
        }

        /* ===== BUSCADOR ===== */
        .buscador-container {
            margin-bottom: 20px;
            position: relative;
        }
        .buscador-container input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        .buscador-container input:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
            background: white;
        }
        .buscador-container .icono-buscar {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 16px;
        }

        /* ===== TABLA ===== */
        .tabla-gestores {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .tabla-gestores thead {
            background: #12232b;
            color: white;
        }
        .tabla-gestores th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .tabla-gestores td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .tabla-gestores tr:hover {
            background: #f8f9fa;
        }
        .gestor-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .gestor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }
        .gestor-datos {
            display: flex;
            flex-direction: column;
        }
        .gestor-nombre {
            font-weight: 600;
            color: #12232b;
        }
        .gestor-usuario {
            font-size: 12px;
            color: #7f8c8d;
        }
        .gestor-email {
            color: #2c3e50;
            font-size: 13px;
        }
        .gestor-cargo {
            display: inline-block;
            background: #e8f0fe;
            color: #173742;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        /* ===== BOTÓN QUITAR ===== */
        .btn-quitar {
            background: #e74c3c;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .btn-quitar:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 60px;
            color: #d3d3d3;
            margin-bottom: 15px;
            display: block;
        }
        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 18px;
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(18, 35, 43, 0.75);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(3px);
        }
        .modal-overlay.activo {
            display: flex;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            background: linear-gradient(135deg, #12232b, #173742);
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            justify-content: space-between;
        }
        .modal-header .titulo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 17px;
            font-weight: 600;
        }
        .modal-header .titulo i {
            color: #27ae60;
            font-size: 20px;
        }
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.2s ease;
            padding: 0 10px;
            line-height: 1;
        }
        .modal-close:hover {
            transform: scale(1.2) rotate(90deg);
        }
        .modal-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
        }
        .modal-body .buscador-modal {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
            margin-bottom: 15px;
        }
        .modal-body .buscador-modal:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
            background: white;
        }
        .buscador-modal-container {
            position: relative;
        }
        .buscador-modal-container .icono-buscar {
            position: absolute;
            left: 15px;
            top: 22px;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 16px;
        }

        .lista-usuarios {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 5px;
        }
        .lista-usuarios::-webkit-scrollbar { width: 8px; }
        .lista-usuarios::-webkit-scrollbar-track { background: #f0f0f0; border-radius: 4px; }
        .lista-usuarios::-webkit-scrollbar-thumb { background: #b0b0b0; border-radius: 4px; }

        .usuario-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        .usuario-item:hover {
            border-color: #173742;
            background: #f8f9fa;
        }
        .usuario-item.seleccionado {
            border-color: #27ae60;
            background: #d5f5e3;
            box-shadow: inset 4px 0 0 #27ae60;
        }
        .usuario-item input[type="checkbox"] {
            display: none;
        }
        .usuario-item .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }
        .usuario-item .datos { flex: 1; }
        .usuario-item .datos .nombre {
            font-weight: 600;
            font-size: 14px;
            color: #12232b;
        }
        .usuario-item .datos .email {
            font-size: 12px;
            color: #7f8c8d;
        }
        .usuario-item .check {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 2px solid #d3d3d3;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.25s ease;
            background: white;
        }
        .usuario-item:hover .check { border-color: #27ae60; }
        .usuario-item.seleccionado .check {
            background: #27ae60;
            border-color: #27ae60;
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(39,174,96,0.4);
        }
        .usuario-item.seleccionado .check::after {
            content: '✓';
            color: white;
            font-size: 15px;
            font-weight: 700;
            animation: checkPop 0.25s ease;
        }
        @keyframes checkPop {
            0%   { transform: scale(0); }
            70%  { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .lista-vacia {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .lista-vacia i {
            font-size: 48px;
            color: #d3d3d3;
            margin-bottom: 10px;
            display: block;
        }

        .modal-footer {
            padding: 18px 25px;
            background: #f8f9fa;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-top: 1px solid #e9ecef;
        }
        .modal-footer button {
            padding: 10px 22px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-cancelar-modal {
            background: #ecf0f1;
            color: #2c3e50;
        }
        .btn-cancelar-modal:hover {
            background: #d5dbdb;
            transform: translateY(-2px);
        }
        .btn-confirmar-modal {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            box-shadow: 0 4px 12px rgba(39,174,96,0.3);
        }
        .btn-confirmar-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(39,174,96,0.4);
        }
        .btn-confirmar-modal:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== MODAL QUITAR GESTOR ===== */
        .modal-quitar .modal-header {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
        }
        .modal-quitar .modal-header .titulo i { color: #fff; }
        .modal-quitar .modal-body {
            text-align: center;
            padding: 30px 25px;
        }
        .modal-quitar .modal-body .icono-grande {
            width: 70px;
            height: 70px;
            background: #fadbd8;
            color: #e74c3c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px;
        }
        .modal-quitar .modal-body p {
            font-size: 15px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .modal-quitar .modal-body strong { color: #12232b; }
        .modal-quitar .modal-body .aviso {
            font-size: 13px;
            color: #7f8c8d;
            background: #f0f2f5;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .modal-quitar .btn-confirmar-modal {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            box-shadow: 0 4px 12px rgba(231,76,60,0.3);
        }
        .modal-quitar .btn-confirmar-modal:hover {
            box-shadow: 0 6px 18px rgba(231,76,60,0.4);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .card { padding: 20px; }
            .card-header { flex-direction: column; align-items: stretch; }
            .btn-añadir { width: 100%; justify-content: center; }
            .tabla-gestores { font-size: 12px; }
            .tabla-gestores th,
            .tabla-gestores td { padding: 10px 8px; }
            .gestor-info { gap: 8px; }
            .gestor-avatar { width: 35px; height: 35px; font-size: 12px; }
            .modal-footer { flex-direction: column; }
            .modal-footer button { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
                <p class="logo-subtitle">| Gestores PQRS</p>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <!-- ===== MENSAJES ===== -->
        <?php if ($mensaje): ?>
            <div class="mensaje-exito" id="mensajeExito">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ===== CARD PRINCIPAL ===== -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>
                        <i class="fas fa-users-cog"></i>
                        Gestión de Gestores PQRS
                    </h2>
                    <p>Administra las personas que pueden resolver PQRS en la intranet</p>
                </div>
                <button type="button" class="btn-añadir" id="btnAbrirModal">
                    <i class="fas fa-plus"></i> Añadir gestor
                </button>
            </div>

            <!-- ===== BUSCADOR ===== -->
            <?php if (!empty($gestores)): ?>
                <div class="buscador-container">
                    <i class="fas fa-search icono-buscar"></i>
                    <input type="text" id="buscadorGestores" placeholder="Buscar gestor por nombre, usuario, email o cargo...">
                </div>
            <?php endif; ?>

            <!-- ===== TABLA ===== -->
            <?php if (empty($gestores)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No hay gestores registrados</h3>
                    <p>Haz clic en "Añadir gestor" para agregar el primero.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="tabla-gestores" id="tablaGestores">
                        <thead>
                            <tr>
                                <th>Gestor</th>
                                <th>Email</th>
                                <th>Cargo</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gestores as $g): 
                                $partes = explode(' ', trim($g['nombre_completo']));
                                if (count($partes) >= 2) {
                                    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
                                } else {
                                    $iniciales = strtoupper(substr($partes[0], 0, 2));
                                }
                            ?>
                                <tr data-busqueda="<?php echo strtolower(htmlspecialchars($g['nombre_completo'] . ' ' . $g['usuario'] . ' ' . $g['email'] . ' ' . $g['cargo'])); ?>">
                                    <td>
                                        <div class="gestor-info">
                                            <div class="gestor-avatar"><?php echo $iniciales; ?></div>
                                            <div class="gestor-datos">
                                                <span class="gestor-nombre"><?php echo htmlspecialchars($g['nombre_completo']); ?></span>
                                                <span class="gestor-usuario">@<?php echo htmlspecialchars($g['usuario']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="gestor-email"><?php echo htmlspecialchars($g['email']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($g['cargo'])): ?>
                                            <span class="gestor-cargo"><?php echo htmlspecialchars($g['cargo']); ?></span>
                                        <?php else: ?>
                                            <span style="color: #7f8c8d;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <button 
                                            type="button" 
                                            class="btn-quitar" 
                                            title="Quitar gestor"
                                            data-gestor-id="<?php echo $g['id']; ?>"
                                            data-gestor-nombre="<?php echo htmlspecialchars($g['nombre_completo']); ?>"
                                        >
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div id="sinResultados" style="display: none; text-align: center; padding: 40px 20px; color: #7f8c8d;">
                    <i class="fas fa-search" style="font-size: 40px; color: #d3d3d3; display: block; margin-bottom: 10px;"></i>
                    <p>No se encontraron gestores que coincidan con la búsqueda.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== MODAL AÑADIR GESTOR ===== -->
    <div class="modal-overlay" id="modalAñadir">
        <div class="modal-content">
            <div class="modal-header">
                <div class="titulo">
                    <i class="fas fa-user-plus"></i>
                    Añadir Gestor
                </div>
                <button type="button" class="modal-close" id="btnCerrarModalAñadir">&times;</button>
            </div>
            <form method="POST" action="" id="formAñadirGestor">
                <input type="hidden" name="action" value="agregar">
                
                <div class="modal-body">
                    <div class="buscador-modal-container">
                        <i class="fas fa-search icono-buscar"></i>
                        <input 
                            type="text" 
                            class="buscador-modal" 
                            id="buscadorUsuarios" 
                            placeholder="Buscar por nombre, usuario, email o cargo..."
                            autocomplete="off"
                        >
                    </div>
                    
                    <?php if (empty($usuarios_disponibles)): ?>
                        <div class="lista-vacia">
                            <i class="fas fa-users-slash"></i>
                            <p>No hay usuarios disponibles para añadir como gestor.</p>
                            <p style="font-size: 12px; margin-top: 5px;">Todos los usuarios activos ya son gestores.</p>
                        </div>
                    <?php else: ?>
                        <div class="lista-usuarios" id="listaUsuarios">
                            <?php foreach ($usuarios_disponibles as $u): 
                                $partes = explode(' ', trim($u['nombre_completo']));
                                if (count($partes) >= 2) {
                                    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
                                } else {
                                    $iniciales = strtoupper(substr($partes[0], 0, 2));
                                }
                            ?>
                                <label 
                                    class="usuario-item" 
                                    data-busqueda="<?php echo strtolower(htmlspecialchars($u['nombre_completo'] . ' ' . $u['usuario'] . ' ' . $u['email'] . ' ' . ($u['cargo'] ?? ''))); ?>"
                                >
                                    <input type="checkbox" name="usuarios_ids[]" value="<?php echo $u['id']; ?>">
                                    <div class="avatar"><?php echo $iniciales; ?></div>
                                    <div class="datos">
                                        <div class="nombre"><?php echo htmlspecialchars($u['nombre_completo']); ?></div>
                                        <div class="email"><?php echo htmlspecialchars($u['email']); ?></div>
                                    </div>
                                    <div class="check"></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="sinResultadosUsuarios" style="display: none; text-align: center; padding: 30px 20px; color: #7f8c8d;">
                            <i class="fas fa-search" style="font-size: 32px; color: #d3d3d3; display: block; margin-bottom: 10px;"></i>
                            <p style="font-size: 14px;">No se encontraron usuarios.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar-modal" id="btnCancelarAñadir">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-confirmar-modal" id="btnConfirmarAñadir" disabled>
                        <i class="fas fa-check"></i> Añadir gestor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL QUITAR GESTOR ===== -->
    <div class="modal-overlay modal-quitar" id="modalQuitar">
        <div class="modal-content">
            <div class="modal-header">
                <div class="titulo">
                    <i class="fas fa-exclamation-triangle"></i>
                    Quitar Gestor
                </div>
                <button type="button" class="modal-close" id="btnCerrarModalQuitar">&times;</button>
            </div>
            <form method="POST" action="" id="formQuitarGestor">
                <input type="hidden" name="action" value="quitar">
                <input type="hidden" name="gestor_id" id="gestorIdQuitar" value="">
                
                <div class="modal-body">
                    <div class="icono-grande">
                        <i class="fas fa-user-minus"></i>
                    </div>
                    <p>¿Estás seguro de que deseas eliminar a</p>
                    <p><strong id="nombreGestorQuitar"></strong></p>
                    <p>como gestor de PQRS?</p>
                    <div class="aviso">
                        <i class="fas fa-info-circle"></i>
                        Las PQRS que tuviera asignadas quedarán sin gestor y podrás reasignarlas.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar-modal" id="btnCancelarQuitar">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-confirmar-modal">
                        <i class="fas fa-trash-alt"></i> Sí, eliminar gestor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        
        // ============================================
        // ELEMENTOS DEL DOM
        // ============================================
        const btnAbrirModal = document.getElementById('btnAbrirModal');
        const modalAñadir = document.getElementById('modalAñadir');
        const btnCerrarModalAñadir = document.getElementById('btnCerrarModalAñadir');
        const btnCancelarAñadir = document.getElementById('btnCancelarAñadir');
        const formAñadirGestor = document.getElementById('formAñadirGestor');
        const buscadorUsuarios = document.getElementById('buscadorUsuarios');
        const listaUsuarios = document.getElementById('listaUsuarios');
        const sinResultadosUsuarios = document.getElementById('sinResultadosUsuarios');
        const btnConfirmarAñadir = document.getElementById('btnConfirmarAñadir');
        
        const modalQuitar = document.getElementById('modalQuitar');
        const btnCerrarModalQuitar = document.getElementById('btnCerrarModalQuitar');
        const btnCancelarQuitar = document.getElementById('btnCancelarQuitar');
        const gestorIdQuitar = document.getElementById('gestorIdQuitar');
        const nombreGestorQuitar = document.getElementById('nombreGestorQuitar');
        
        const buscadorGestores = document.getElementById('buscadorGestores');
        const tablaGestores = document.getElementById('tablaGestores');
        const sinResultados = document.getElementById('sinResultados');
        
        // ============================================
        // SELECCIÓN MÚLTIPLE
        // ============================================
        let seleccionados = new Set();
        
        // ============================================
        // ABRIR / CERRAR MODAL AÑADIR
        // ============================================
        function abrirModalAñadir() {
            modalAñadir.classList.add('activo');
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                if (buscadorUsuarios) buscadorUsuarios.focus();
            }, 300);
        }
        
        function cerrarModalAñadir() {
            modalAñadir.classList.remove('activo');
            document.body.style.overflow = '';
            
            // Resetear formulario
            if (formAñadirGestor) formAñadirGestor.reset();
            
            // Limpiar set
            seleccionados.clear();
            
            // Limpiar clases visuales
            document.querySelectorAll('.usuario-item').forEach(item => {
                item.classList.remove('seleccionado');
                item.style.display = '';
            });
            
            // Resetear botón
            if (btnConfirmarAñadir) {
                btnConfirmarAñadir.disabled = true;
                btnConfirmarAñadir.innerHTML = '<i class="fas fa-check"></i> Añadir gestor';
            }
            
            // Limpiar buscador
            if (buscadorUsuarios) buscadorUsuarios.value = '';
            if (sinResultadosUsuarios) sinResultadosUsuarios.style.display = 'none';
        }
        
        if (btnAbrirModal) btnAbrirModal.addEventListener('click', abrirModalAñadir);
        if (btnCerrarModalAñadir) btnCerrarModalAñadir.addEventListener('click', cerrarModalAñadir);
        if (btnCancelarAñadir) btnCancelarAñadir.addEventListener('click', cerrarModalAñadir);
        
        if (modalAñadir) {
            modalAñadir.addEventListener('click', function (e) {
                if (e.target === modalAñadir) cerrarModalAñadir();
            });
        }
        
        // ============================================
        // SELECCIONAR / DESELECCIONAR USUARIOS
        // ============================================
        if (listaUsuarios) {
            listaUsuarios.addEventListener('click', function (e) {
                const item = e.target.closest('.usuario-item');
                if (!item) return;
                
                e.preventDefault();
                
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (!checkbox) return;
                
                const id = checkbox.value;
                
                if (seleccionados.has(id)) {
                    seleccionados.delete(id);
                    item.classList.remove('seleccionado');
                    checkbox.checked = false;
                } else {
                    seleccionados.add(id);
                    item.classList.add('seleccionado');
                    checkbox.checked = true;
                }
                
                btnConfirmarAñadir.disabled = seleccionados.size === 0;
                
                if (seleccionados.size > 0) {
                    btnConfirmarAñadir.innerHTML = `<i class="fas fa-check"></i> Añadir ${seleccionados.size} gestor${seleccionados.size > 1 ? 'es' : ''}`;
                } else {
                    btnConfirmarAñadir.innerHTML = '<i class="fas fa-check"></i> Añadir gestor';
                }
            });
        }
        
        // ============================================
        // BUSCADOR DE USUARIOS EN MODAL
        // ============================================
        if (buscadorUsuarios && listaUsuarios) {
            buscadorUsuarios.addEventListener('input', function () {
                const termino = this.value.toLowerCase().trim();
                const items = listaUsuarios.querySelectorAll('.usuario-item');
                let visibles = 0;
                
                items.forEach(item => {
                    const busqueda = item.getAttribute('data-busqueda') || '';
                    if (busqueda.includes(termino)) {
                        item.style.display = '';
                        visibles++;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                if (sinResultadosUsuarios) {
                    sinResultadosUsuarios.style.display = visibles === 0 ? 'block' : 'none';
                }
            });
        }
        
        // ============================================
        // VALIDAR FORM AÑADIR
        // ============================================
        if (formAñadirGestor) {
            formAñadirGestor.addEventListener('submit', function (e) {
                if (seleccionados.size === 0) {
                    e.preventDefault();
                    alert('Debes seleccionar al menos un usuario');
                    return false;
                }
                btnConfirmarAñadir.disabled = true;
                btnConfirmarAñadir.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Añadiendo ${seleccionados.size}...`;
            });
        }
        
        // ============================================
        // ABRIR MODAL QUITAR
        // ============================================
        document.querySelectorAll('.btn-quitar').forEach(btn => {
            btn.addEventListener('click', function () {
                const gestorId = this.getAttribute('data-gestor-id');
                const gestorNombre = this.getAttribute('data-gestor-nombre');
                
                gestorIdQuitar.value = gestorId;
                nombreGestorQuitar.textContent = gestorNombre;
                modalQuitar.classList.add('activo');
                document.body.style.overflow = 'hidden';
            });
        });
        
        // ============================================
        // CERRAR MODAL QUITAR
        // ============================================
        function cerrarModalQuitar() {
            modalQuitar.classList.remove('activo');
            document.body.style.overflow = '';
            gestorIdQuitar.value = '';
        }
        
        if (btnCerrarModalQuitar) btnCerrarModalQuitar.addEventListener('click', cerrarModalQuitar);
        if (btnCancelarQuitar) btnCancelarQuitar.addEventListener('click', cerrarModalQuitar);
        
        if (modalQuitar) {
            modalQuitar.addEventListener('click', function (e) {
                if (e.target === modalQuitar) cerrarModalQuitar();
            });
        }
        
        // ============================================
        // BUSCADOR DE GESTORES EN TABLA
        // ============================================
        if (buscadorGestores && tablaGestores) {
            buscadorGestores.addEventListener('input', function () {
                const termino = this.value.toLowerCase().trim();
                const filas = tablaGestores.querySelectorAll('tbody tr');
                let visibles = 0;
                
                filas.forEach(fila => {
                    const busqueda = fila.getAttribute('data-busqueda') || '';
                    if (busqueda.includes(termino)) {
                        fila.style.display = '';
                        visibles++;
                    } else {
                        fila.style.display = 'none';
                    }
                });
                
                if (sinResultados) {
                    sinResultados.style.display = visibles === 0 ? 'block' : 'none';
                }
            });
        }
        
        // ============================================
        // CERRAR MODALES CON ESC
        // ============================================
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (modalAñadir && modalAñadir.classList.contains('activo')) {
                    cerrarModalAñadir();
                }
                if (modalQuitar && modalQuitar.classList.contains('activo')) {
                    cerrarModalQuitar();
                }
            }
        });
        
        // ============================================
        // AUTO-OCULTAR MENSAJE DE ÉXITO
        // ============================================
        const mensajeExito = document.getElementById('mensajeExito');
        if (mensajeExito) {
            setTimeout(() => {
                mensajeExito.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                mensajeExito.style.opacity = '0';
                mensajeExito.style.transform = 'translateY(-10px)';
                setTimeout(() => mensajeExito.remove(), 500);
            }, 4000);
        }
    });
    </script>
</body>
</html>