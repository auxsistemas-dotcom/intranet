<?php
// aplicaciones/otros/solicitud_permisos/index.php - Lista de solicitudes de permisos
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO - Requiere login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];
$rol_usuario = $usuario['rol'];
$nombre_usuario = $usuario['nombre_completo'];

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
// OBTENER SOLICITUDES DEL USUARIO
// ============================================

$sql = "SELECT * FROM solicitudes_permisos 
        WHERE usuario_id = ? 
        ORDER BY solicitado_el DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$no_solicitudes = empty($solicitudes);

// Tipos de permisos
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

// Estados
$estados = [
    'pendiente' => '⏳ Pendiente',
    'aprobado' => '✅ Aprobado',
    'rechazado' => '❌ Rechazado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes de Permisos | INTRANET</title>
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
        .badge-pendiente { background: #fef9e7; color: #7f6000; border: 1px solid #f39c12; }
        .badge-aprobado { background: #d5f5e3; color: #1a7a3a; border: 1px solid #27ae60; }
        .badge-rechazado { background: #fadbd8; color: #922b21; border: 1px solid #e74c3c; }

        .badge-tipo {
            padding: 7px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
            background: #e8f0fe;
            color: #173742;
        }

        /* ===== BOTONES ACCIÓN UNIFICADOS ===== */
        .btn-accion {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-accion i {
            font-size: 12px;
            line-height: 1;
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

        .btn-ver { 
            background: #8e44ad; 
            color: white; 
        }
        .btn-ver:hover { 
            background: #732d91; 
        }

        .btn-cancelar { 
            background: #95a5a6; 
            color: white; 
        }
        .btn-cancelar:hover { 
            background: #7f8c8d; 
        }

        /* ===== MENSAJES ===== */
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            transition: opacity 0.5s ease;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state i { font-size: 48px; color: #d3d3d3; margin-bottom: 15px; }
        .empty-state p { font-size: 14px; }

        .text-muted { color: #7f8c8d; font-size: 12px; }
        .td-min { white-space: nowrap; }
        .text-center { text-align: center; }

        /* ============================================ */
        /* ===== MODAL PERSONALIZADO ===== */
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
            max-width: 480px;
            width: 90%;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
            position: relative;
        }

        .modal-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-icon i {
            font-size: 56px;
            color: #e74c3c;
            background: #fadbd8;
            padding: 20px;
            border-radius: 50%;
        }

        .modal-title {
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .modal-message {
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.6;
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
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            min-width: 120px;
        }

        .modal-actions .btn-cancelar-modal {
            background: #ecf0f1;
            color: #2c3e50;
        }

        .modal-actions .btn-cancelar-modal:hover {
            background: #d5dbdb;
            transform: translateY(-2px);
        }

        .modal-actions .btn-confirmar-eliminar {
            background: #e74c3c;
            color: white;
        }

        .modal-actions .btn-confirmar-eliminar:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .modal-actions .btn-confirmar-eliminar i {
            margin-right: 8px;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            color: #bdc3c7;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            color: #e74c3c;
            transform: rotate(90deg);
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

        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header-actions { flex-direction: column; align-items: stretch; }
            .btn-agregar { text-align: center; }
            table { font-size: 12px; }
            th, td { padding: 8px 10px; }
            .modal-container { padding: 20px; }
            .modal-actions { flex-direction: column; }
            .modal-actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div>
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <p class="logo-subtitle" style="color: #8aa8b8; font-size: 14px;">| Permisos</p>
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito" id="mensajeExito">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="header-actions">
                <h2><i class="fas fa-calendar-check"></i> Mis Solicitudes de Permisos</h2>
                <a href="crear.php" class="btn-agregar">
                    <i class="fas fa-plus"></i> Nueva Solicitud
                </a>
            </div>

            <?php if ($no_solicitudes): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <p>No tienes solicitudes de permisos registradas.</p>
                    <p style="font-size: 13px; margin-top: 5px;">Haz clic en <strong>"Nueva Solicitud"</strong> para crear una.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tipo</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; foreach ($solicitudes as $solicitud): ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td>
                                        <span class="badge-tipo">
                                            <?php echo $tipos_permisos[$solicitud['tipo']] ?? $solicitud['tipo']; ?>
                                        </span>
                                    </td>
                                    <td class="td-min">
                                        <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_inicio'])); ?>
                                    </td>
                                    <td class="td-min">
                                        <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_fin'])); ?>
                                    </td>
                                    <td class="td-min">
                                        <?php
                                        $estado_class = '';
                                        $estado_text = '';
                                        switch ($solicitud['estado']) {
                                            case 'pendiente':
                                                $estado_class = 'badge-pendiente';
                                                $estado_text = '⏳ Pendiente';
                                                break;
                                            case 'aprobado':
                                                $estado_class = 'badge-aprobado';
                                                $estado_text = '✅ Aprobado';
                                                break;
                                            case 'rechazado':
                                                $estado_class = 'badge-rechazado';
                                                $estado_text = '❌ Rechazado';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $estado_class; ?>">
                                            <?php echo $estado_text; ?>
                                        </span>
                                    </td>
                                    <td class="text-center td-min">

                                        <?php if ($solicitud['estado'] == 'pendiente'): ?>
                                            <!-- Botón Editar -->
                                            <a href="editar.php?id=<?php echo $solicitud['id']; ?>" class="btn-accion btn-editar" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- ✅ Botón Eliminar con Modal -->
                                            <button type="button" class="btn-accion btn-eliminar" onclick="abrirModalEliminar(<?php echo $solicitud['id']; ?>, '<?php echo addslashes($solicitud['tipo']); ?>')" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 15px; color: #7f8c8d; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> 
                    Total: <strong><?php echo count($solicitudes); ?></strong> solicitudes
                    <?php if ($rol_usuario != 1): ?>
                        | <i class="fas fa-clock"></i> 
                        <strong><?php echo count(array_filter($solicitudes, function($s) { return $s['estado'] == 'pendiente'; })); ?></strong> pendientes
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MODAL PERSONALIZADO PARA ELIMINAR -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="modalEliminar">
        <div class="modal-container">
            <!-- Botón cerrar -->
            <button class="modal-close" onclick="cerrarModalEliminar()">
                <i class="fas fa-times"></i>
            </button>

            <!-- Icono -->
            <div class="modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>

            <!-- Título -->
            <h3 class="modal-title">¿Eliminar solicitud?</h3>

            <!-- Mensaje -->
            <p class="modal-message" id="modalMensaje">
                ¿Estás seguro de que deseas eliminar la solicitud de <strong id="modalTipoPermiso"></strong>?
                <br><br>
            </p>

            <!-- Botones -->
            <div class="modal-actions">
                <button class="btn btn-cancelar-modal" onclick="cerrarModalEliminar()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form id="formEliminar" method="POST" action="eliminar.php" style="display: inline;">
                    <input type="hidden" name="id" id="modalEliminarId" value="">
                    <button type="submit" class="btn btn-confirmar-eliminar">
                        <i class="fas fa-trash-alt"></i> Sí, eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // MODAL DE ELIMINACIÓN PERSONALIZADO
        // ============================================

        function abrirModalEliminar(id, tipo) {
            // Obtener elementos
            const modal = document.getElementById('modalEliminar');
            const inputId = document.getElementById('modalEliminarId');
            const tipoSpan = document.getElementById('modalTipoPermiso');
            
            // Configurar datos
            inputId.value = id;
            tipoSpan.textContent = tipo;
            
            // Mostrar modal con animación
            modal.classList.add('active');
            
            // Prevenir scroll del body
            document.body.style.overflow = 'hidden';
            
            // Cerrar al hacer clic fuera del modal
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    cerrarModalEliminar();
                }
            });
            
            // Cerrar con tecla ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarModalEliminar();
                }
            });
        }

        function cerrarModalEliminar() {
            const modal = document.getElementById('modalEliminar');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // ============================================
        // OCULTAR MENSAJE DE ÉXITO DESPUÉS DE 3 SEGUNDOS
        // ============================================
        
        document.addEventListener('DOMContentLoaded', function() {
            const mensajeExito = document.getElementById('mensajeExito');
            
            if (mensajeExito) {
                setTimeout(function() {
                    mensajeExito.style.transition = 'opacity 0.5s ease';
                    mensajeExito.style.opacity = '0';
                    
                    setTimeout(function() {
                        mensajeExito.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>