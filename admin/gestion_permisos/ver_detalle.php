<?php
// admin/gestion_permisos/ver_detalle.php - Ver detalle de solicitud de permiso
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Jefes (es_jefe = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$usuario_actual = obtenerUsuario();
$usuario_id = $usuario_actual['id'];
$rol_usuario = $usuario_actual['rol'];
$es_jefe = $usuario_actual['es_jefe'] ?? 0;

// Verificar que tenga permisos para ver detalles
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para ver detalles de permisos";
    header('Location: ../../index.php');
    exit();
}

// ============================================
// OBTENER ID DE LA SOLICITUD
// ============================================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    $_SESSION['error'] = "❌ ID de solicitud no válido";
    header('Location: index.php');
    exit();
}

// ============================================
// OBTENER DATOS DE LA SOLICITUD
// ============================================
$sql = "SELECT 
            sp.*,
            u.nombre_completo as solicitante_nombre,
            u.usuario as solicitante_usuario,
            u.cargo as solicitante_cargo,
            j.nombre_completo as jefe_nombre,
            j.usuario as jefe_usuario,
            j.cargo as jefe_cargo,
            a.nombre_completo as aprobador_nombre
        FROM solicitudes_permisos sp
        LEFT JOIN usuarios u ON sp.usuario_id = u.id
        LEFT JOIN usuarios j ON sp.jefe_inmediato = j.id
        LEFT JOIN usuarios a ON sp.aprobado_por = a.id
        WHERE sp.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    $_SESSION['error'] = "❌ La solicitud no existe";
    header('Location: index.php');
    exit();
}

// ============================================
// VERIFICAR PERMISOS PARA VER LA SOLICITUD
// ============================================
$es_admin = ($rol_usuario == 1);
$es_jefe_asignado = ($solicitud['jefe_inmediato'] == $usuario_id);

// Si no es admin y no es el jefe asignado, no puede ver
if (!$es_admin && !$es_jefe_asignado) {
    $_SESSION['error'] = "❌ No tienes permiso para ver esta solicitud";
    header('Location: index.php');
    exit();
}

// ============================================
// PROCESAR APROBACIÓN/RECHAZO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Verificar que la solicitud esté pendiente
    if ($solicitud['estado'] != 'pendiente') {
        $_SESSION['error'] = "❌ Esta solicitud ya fue " . $solicitud['estado'];
        header("Location: ver_detalle.php?id=$id");
        exit();
    }
    
    // Verificar que el usuario tenga permisos para aprobar/rechazar
    if (!$es_admin && !$es_jefe_asignado) {
        $_SESSION['error'] = "❌ No tienes permiso para realizar esta acción";
        header("Location: ver_detalle.php?id=$id");
        exit();
    }
    
    try {
        if ($action == 'aprobar') {
            $estado = 'aprobado';
            $mensaje = "✅ Solicitud aprobada correctamente";
        } elseif ($action == 'rechazar') {
            $estado = 'rechazado';
            $mensaje = "❌ Solicitud rechazada";
        } else {
            $_SESSION['error'] = "❌ Acción no válida";
            header("Location: ver_detalle.php?id=$id");
            exit();
        }
        
        // Actualizar la solicitud
        $stmt = $pdo->prepare("
            UPDATE solicitudes_permisos 
            SET estado = ?,
                aprobado_el = NOW(),
                aprobado_por = ?
            WHERE id = ?
        ");
        $stmt->execute([$estado, $usuario_id, $id]);
        
        $_SESSION['mensaje'] = $mensaje;
        header("Location: index.php");
        exit();
        
    } catch (PDOException $e) {
        $error = "❌ Error al procesar la solicitud: " . $e->getMessage();
    }
}

// ============================================
// TIPOS DE PERMISOS
// ============================================
$tipos_permisos = [
    'Cita Medica' => 'Cita Médica',
    'Cita Medica Urgencias' => 'Cita Médica Urgencias',
    'Cita con Especialista' => 'Cita con Especialista',
    'Acudiente de Obligaciones Escolares' => 'Acudiente de Obligaciones Escolares',
    'Permiso para Atender Situaciones Judiciales o Administrativas' => 'Permiso para Atender Situaciones Judiciales o Administrativas',
    'Cumpleaños' => 'Cumpleaños',
    'Permiso Remunerado' => 'Permiso Remunerado',
    'Permiso Menos a 4 Horas' => 'Permiso Menos a 4 Horas',
    'Jurado de Votacion' => 'Jurado de Votación',
    'Permiso por Votacion' => 'Permiso por Votación',
    'Jornada Flexible' => 'Jornada Flexible'
];

// Estados con colores
$estados_colores = [
    'pendiente' => ['class' => 'badge-pendiente', 'text' => '⏳ Pendiente'],
    'aprobado' => ['class' => 'badge-aprobado', 'text' => '✅ Aprobado'],
    'rechazado' => ['class' => 'badge-rechazado', 'text' => '❌ Rechazado']
];

// Establecer zona horaria
date_default_timezone_set('America/Bogota');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Solicitud de Permiso | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: #d3d3d3; 
            color: #2c3e50; 
        }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        .header .container { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            padding: 15px 20px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-left img { 
            height: 45px; 
            width: auto;
        }
        .header-left .header-title {
            color: #8aa8b8;
            font-size: 14px;
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

        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .card-header h2 {
            font-size: 20px;
            font-weight: 600;
            border-left: 4px solid #173742;
            padding-left: 15px;
        }
        .card-header h2 i {
            margin-right: 10px;
            color: #173742;
        }
        .card-header .badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* ===== BADGES ===== */
        .badge-pendiente { 
            background: #fef9e7; 
            color: #7f6000; 
            border: 1px solid #f39c12; 
        }
        .badge-aprobado { 
            background: #d5f5e3; 
            color: #1a7a3a; 
            border: 1px solid #27ae60; 
        }
        .badge-rechazado { 
            background: #fadbd8; 
            color: #922b21; 
            border: 1px solid #e74c3c; 
        }

        /* ===== INFO GRID ===== */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
        }

        .info-item {
            padding: 8px 0;
        }
        .info-item .label {
            font-size: 12px;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .info-item .label i {
            margin-right: 6px;
            color: #173742;
        }
        .info-item .value {
            font-size: 15px;
            font-weight: 500;
            color: #2c3e50;
            line-height: 1.6;
        }
        .info-item .value .text-muted {
            color: #7f8c8d;
            font-weight: 400;
            font-size: 13px;
        }

        .badge-tipo {
            display: inline-block;
            background: #e8f0fe;
            color: #173742;
            padding: 4px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        /* ===== ARCHIVO ===== */
        .archivo-container {
            margin-top: 15px;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e9ecef;
        }
        .archivo-container i {
            font-size: 28px;
            color: #e74c3c;
        }
        .archivo-container .archivo-info {
            flex: 1;
        }
        .archivo-container .archivo-info .nombre {
            font-weight: 500;
            font-size: 13px;
        }
        .archivo-container .archivo-info .tamano {
            font-size: 11px;
            color: #7f8c8d;
        }
        .btn-descargar {
            background: #173742;
            color: white;
            padding: 2px 8px;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 12px;
        }
        .btn-descargar:hover {
            background: #445960;
            transform: translateY(-2px);
        }

        /* ===== INFO ADICIONAL ===== */
        .info-adicional {
            background: #e8f0fe;
            padding: 10px 16px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 3px solid #173742;
            font-size: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .info-adicional i { 
            color: #173742; 
            margin-right: 6px;
        }
        .info-adicional .highlight {
            font-weight: 600;
        }
        .info-adicional .separator {
            color: #bdc3c7;
        }

        .info-adicional-comentario {
            background: #fef9e7;
            border-left-color: #f39c12;
        }
        .info-adicional-comentario i {
            color: #f39c12;
        }

        /* ===== SECCIÓN ACCIONES ===== */
        .acciones-section {
            background: white;
        }
        .acciones-section .card-header {
            border-bottom-color: #f0f0f0;
        }
        .acciones-section .card-header h3 {
            font-size: 17px;
            font-weight: 600;
        }
        .acciones-section .card-header h3 i {
            color: #f39c12;
            margin-right: 10px;
        }

        .acciones-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 5px;
        }

        .btn-accion {
            padding: 11px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 130px;
            justify-content: center;
        }
        .btn-accion:hover {
            transform: translateY(-2px);
        }

        .btn-aprobar {
            background: #27ae60;
            color: white;
        }
        .btn-aprobar:hover {
            background: #219a52;
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }

        .btn-rechazar {
            background: #e74c3c;
            color: white;
        }
        .btn-rechazar:hover {
            background: #c0392b;
            box-shadow: 0 4px 15px rgba(231,76,60,0.3);
        }

        .btn-volver-accion {
            background: #95a5a6;
            color: white;
            text-decoration: none;
            padding: 11px 28px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 130px;
            justify-content: center;
            border: none;
            font-size: 14px;
        }
        .btn-volver-accion:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

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

        /* ============================================ */
        /* ===== MODALES PERSONALIZADOS ===== */
        /* ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            background: white;
            border-radius: 16px;
            max-width: 420px;
            width: 92%;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
            position: relative;
            text-align: center;
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            font-size: 22px;
            color: #bdc3c7;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .modal-close:hover {
            color: #e74c3c;
            transform: rotate(90deg);
        }

        .modal-icon {
            margin-bottom: 12px;
        }
        .modal-icon i {
            font-size: 48px;
            padding: 16px;
            border-radius: 50%;
        }
        .modal-icon .icon-aprobar {
            color: #27ae60;
            background: #d5f5e3;
        }
        .modal-icon .icon-rechazar {
            color: #e74c3c;
            background: #fadbd8;
        }

        .modal-title {
            font-size: 19px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .modal-message {
            color: #7f8c8d;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .modal-message strong {
            color: #2c3e50;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .modal-actions .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            min-width: 110px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .modal-actions .btn:hover {
            transform: translateY(-2px);
        }
        .modal-actions .btn-cancelar-modal {
            background: #ecf0f1;
            color: #2c3e50;
        }
        .modal-actions .btn-cancelar-modal:hover {
            background: #d5dbdb;
        }
        .modal-actions .btn-confirmar-aprobar {
            background: #27ae60;
            color: white;
        }
        .modal-actions .btn-confirmar-aprobar:hover {
            background: #219a52;
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        .modal-actions .btn-confirmar-rechazar {
            background: #e74c3c;
            color: white;
        }
        .modal-actions .btn-confirmar-rechazar:hover {
            background: #c0392b;
            box-shadow: 0 4px 15px rgba(231,76,60,0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .card { padding: 18px; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .card-header h2 { font-size: 17px; }
            .acciones-container { flex-direction: column; align-items: stretch; }
            .btn-accion, .btn-volver-accion { width: 100%; justify-content: center; }
            .modal-container { padding: 25px 20px; }
            .modal-actions { flex-direction: column; }
            .modal-actions .btn { width: 100%; }
            .header .container { flex-direction: column; gap: 10px; text-align: center; }
            .info-adicional { flex-direction: column; gap: 8px; }
        }
        @media (max-width: 480px) {
            .archivo-container { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Detalles del permiso</small>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <?php if (isset($mensaje)): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- INFORMACIÓN DE LA SOLICITUD -->
        <!-- ============================================ -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-file-alt"></i> 
                    Detalle de Solicitud
                </h2>
                <span class="badge <?php echo $estados_colores[$solicitud['estado']]['class']; ?>">
                    <?php echo $estados_colores[$solicitud['estado']]['text']; ?>
                </span>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="label"><i class="fas fa-user"></i> Solicitante</div>
                    <div class="value">
                        <?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?>
                        <br>
                        <span class="text-muted">@<?php echo htmlspecialchars($solicitud['solicitante_usuario']); ?></span>
                        <?php if (!empty($solicitud['solicitante_cargo'])): ?>
                            <br>
                            <span class="text-muted"><?php echo htmlspecialchars($solicitud['solicitante_cargo']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="label"><i class="fas fa-tag"></i> Tipo de Permiso</div>
                    <div class="value">
                        <span class="badge-tipo">
                            <?php echo $tipos_permisos[$solicitud['tipo']] ?? $solicitud['tipo']; ?>
                        </span>
                    </div>
                </div>

                <div class="info-item">
                    <div class="label"><i class="fas fa-calendar-alt"></i> Fechas</div>
                    <div class="value">
                        <div><i class="fas fa-play" style="color: #27ae60; font-size: 11px;"></i> <strong>Inicio:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_inicio'])); ?></div>
                        <div><i class="fas fa-stop" style="color: #e74c3c; font-size: 11px;"></i> <strong>Fin:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_fin'])); ?></div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="label"><i class="fas fa-clock"></i> Duración</div>
                    <div class="value">
                        <?php
                        $inicio = new DateTime($solicitud['fecha_inicio']);
                        $fin = new DateTime($solicitud['fecha_fin']);
                        $diff = $inicio->diff($fin);
                        $dias = $diff->d;
                        $horas = $diff->h;
                        $minutos = $diff->i;
                        
                        $duracion = [];
                        if ($dias > 0) $duracion[] = $dias . ' día(s)';
                        if ($horas > 0) $duracion[] = $horas . ' hora(s)';
                        if ($minutos > 0) $duracion[] = $minutos . ' minuto(s)';
                        echo !empty($duracion) ? implode(', ', $duracion) : 'Menos de 1 minuto';
                        ?>
                    </div>
                </div>

                <?php if (!empty($solicitud['descripcion'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="label"><i class="fas fa-align-left"></i> Descripción</div>
                        <div class="value">
                            <?php echo nl2br(htmlspecialchars($solicitud['descripcion'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Archivo Adjunto -->
            <?php if (!empty($solicitud['archivo'])): ?>
                <div class="archivo-container">
                    <i class="fas fa-file-pdf"></i>
                    <div class="archivo-info">
                        <div class="nombre"><?php echo htmlspecialchars($solicitud['archivo']); ?></div>
                        <?php
                        $ruta_archivo = '../../uploads/permisos/' . $solicitud['archivo'];
                        if (file_exists($ruta_archivo)) {
                            $tamano = filesize($ruta_archivo);
                            if ($tamano < 1024) {
                                $tamano_texto = $tamano . ' B';
                            } elseif ($tamano < 1048576) {
                                $tamano_texto = round($tamano / 1024, 2) . ' KB';
                            } else {
                                $tamano_texto = round($tamano / 1048576, 2) . ' MB';
                            }
                        }
                        ?>
                    </div>
                    <a href="../../uploads/permisos/<?php echo $solicitud['archivo']; ?>" target="_blank" class="btn-descargar">
                        <i class="fas fa-eye"></i> Ver
                    </a>
                </div>
            <?php endif; ?>

            <!-- Información Adicional -->
            <div class="info-adicional">
                <span>
                    <i class="fas fa-calendar-plus"></i>
                    <span class="highlight">Solicitado:</span> <?php echo date('d/m/Y H:i', strtotime($solicitud['solicitado_el'])); ?>
                </span>
                
                <?php if ($solicitud['estado'] != 'pendiente'): ?>
                    <span>
                        <i class="fas fa-user-check"></i>
                        <span class="highlight"><?php echo $solicitud['estado'] == 'aprobado' ? 'Aprobado' : 'Rechazado'; ?>:</span>
                        <?php echo htmlspecialchars($solicitud['aprobador_nombre'] ?? 'Sistema'); ?>
                        <span class="text-muted">
                            <?php echo date('d/m/Y H:i', strtotime($solicitud['aprobado_el'])); ?>
                        </span>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Comentario del Admin/Jefe -->
            <?php if (!empty($solicitud['comentario_admin'])): ?>
                <div class="info-adicional info-adicional-comentario">
                    <span>
                        <i class="fas fa-comment"></i>
                        <span class="highlight">Comentario:</span>
                        <?php echo nl2br(htmlspecialchars($solicitud['comentario_admin'])); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- ACCIONES (SOLO PARA PENDIENTES) -->
        <!-- ============================================ -->
        <?php if ($solicitud['estado'] == 'pendiente' && ($es_admin || $es_jefe_asignado)): ?>
            <div class="card acciones-section">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-gavel"></i> Gestionar Solicitud
                    </h3>
                </div>

                <div class="acciones-container">
                    <button type="button" class="btn-accion btn-aprobar" onclick="abrirModalAprobar()">
                        <i class="fas fa-check"></i> Aprobar
                    </button>
                    <button type="button" class="btn-accion btn-rechazar" onclick="abrirModalRechazar()">
                        <i class="fas fa-times"></i> Rechazar
                    </button>
                    <a href="index.php" class="btn-volver-accion">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- MODAL APROBAR -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="modalAprobar">
        <div class="modal-container">
            <button class="modal-close" onclick="cerrarModal('modalAprobar')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-check-circle icon-aprobar"></i>
            </div>
            <h3 class="modal-title">¿Aprobar solicitud?</h3>
            <p class="modal-message">
                ¿Estás seguro de que deseas <strong>APROBAR</strong> esta solicitud?
            </p>
            <div class="modal-actions">
                <button class="btn btn-cancelar-modal" onclick="cerrarModal('modalAprobar')">
                    Cancelar
                </button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="aprobar">
                    <button type="submit" class="btn btn-confirmar-aprobar">
                        <i class="fas fa-check"></i> Aprobar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MODAL RECHAZAR -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="modalRechazar">
        <div class="modal-container">
            <button class="modal-close" onclick="cerrarModal('modalRechazar')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-times-circle icon-rechazar"></i>
            </div>
            <h3 class="modal-title">¿Rechazar solicitud?</h3>
            <p class="modal-message">
                ¿Estás seguro de que deseas <strong>RECHAZAR</strong> esta solicitud?
            </p>
            <div class="modal-actions">
                <button class="btn btn-cancelar-modal" onclick="cerrarModal('modalRechazar')">
                    Cancelar
                </button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="rechazar">
                    <button type="submit" class="btn btn-confirmar-rechazar">
                        <i class="fas fa-times"></i> Rechazar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // FUNCIONES PARA MODALES
        // ============================================

        function abrirModalAprobar() {
            document.getElementById('modalAprobar').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function abrirModalRechazar() {
            document.getElementById('modalRechazar').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(function(modal) {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>
</html>