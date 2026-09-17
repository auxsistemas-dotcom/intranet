<?php
// admin/comercial/reclamos/ver.php - Ver y responder reclamo
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/reclamos_mailer.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

// Admin y Supervisor: acceso total
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso";
    header('Location: ../../index.php');
    exit();
}

$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

// ============================================
// MESES EN ESPAÑOL
// ============================================
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// ============================================
// TIPOS DE COMISIONES
// ============================================
$tipos_comisiones = [
    'matriculas' => 'Matrículas',
    'usados' => 'Usados',
    'accesorios' => 'Accesorios',
    'incentivos' => 'Incentivos',
    'financieras' => 'Financieras'
];

// ============================================
// ESTADOS
// ============================================
$estados = [
    'pendiente'   => ['texto' => 'Pendiente',   'clase' => 'estado-pendiente', 'icono' => 'fa-clock'],
    'en_revision' => ['texto' => 'En Revisión', 'clase' => 'estado-revision',  'icono' => 'fa-search'],
    'resuelto'    => ['texto' => 'Resuelto',    'clase' => 'estado-resuelto',  'icono' => 'fa-check-circle']
];

// ============================================
// OBTENER ID DEL RECLAMO
// ============================================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    $_SESSION['error'] = "❌ Reclamo no válido";
    header('Location: index.php');
    exit();
}

// ============================================
// OBTENER EL RECLAMO
// ============================================
$stmt = $pdo->prepare("
    SELECT r.*, 
           u.nombre_completo as asesor_nombre, 
           u.usuario as asesor_usuario,
           u.cod_asesor as asesor_codigo,
           u.cargo as asesor_cargo
    FROM reclamos r
    LEFT JOIN usuarios u ON r.asesor_id = u.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$reclamo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reclamo) {
    $_SESSION['error'] = "❌ El reclamo no existe";
    header('Location: index.php');
    exit();
}

// ============================================
// MARCAR MENSAJES COMO LEÍDOS
// ============================================
$stmt = $pdo->prepare("
    UPDATE reclamos_mensajes 
    SET leido = 1 
    WHERE reclamo_id = ? AND usuario_id != ? AND leido = 0
");
$stmt->execute([$id, $usuario_id]);

// ============================================
// PROCESAR NUEVO MENSAJE
// ============================================
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'mensaje') {
    $mensaje = trim($_POST['mensaje'] ?? '');
    $imagen_nombre = null;
    
    // ✅ PROCESAR IMAGEN
    if (!empty($_FILES['imagen']['name'])) {
        $archivo = $_FILES['imagen'];
        
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $error = "❌ Error al subir la imagen (código: " . $archivo['error'] . ")";
        } else {
            // Validar tamaño máximo (5 MB)
            $max_size = 5 * 1024 * 1024;
            if ($archivo['size'] > $max_size) {
                $error = "❌ La imagen no debe pesar más de 5 MB";
            } else {
                // Validar extensión
                $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $error = "❌ Solo se permiten imágenes JPG, JPEG o PNG";
                } else {
                    // Validar MIME real
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $archivo['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'])) {
                        $error = "❌ El archivo no es una imagen válida";
                    } else {
                        // Crear carpeta del reclamo si no existe
                        $carpeta_reclamo = __DIR__ . '/../../../uploads/reclamos/' . $id . '/';
                        if (!is_dir($carpeta_reclamo)) {
                            mkdir($carpeta_reclamo, 0755, true);
                        }
                        
                        // Generar nombre único
                        $imagen_nombre = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $ruta_destino = $carpeta_reclamo . $imagen_nombre;
                        
                        if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                            $error = "❌ Error al guardar la imagen en el servidor";
                            $imagen_nombre = null;
                        }
                    }
                }
            }
        }
    }
    
    // Validaciones del mensaje
    if (empty($error)) {
        if (empty($mensaje) && empty($imagen_nombre)) {
            $error = "❌ Debes escribir un mensaje o adjuntar una imagen";
        } elseif (!empty($mensaje) && strlen($mensaje) < 2) {
            $error = "❌ El mensaje debe tener al menos 2 caracteres";
        } elseif ($reclamo['estado'] == 'resuelto') {
            $error = "❌ Este reclamo ya fue resuelto y no se pueden enviar más mensajes";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO reclamos_mensajes 
                    (reclamo_id, usuario_id, mensaje, imagen, leido, creado_el) 
                    VALUES (?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$id, $usuario_id, $mensaje, $imagen_nombre]);
                
                // Actualizar estado a "en_revision" si estaba pendiente
                if ($reclamo['estado'] == 'pendiente') {
                    $stmt = $pdo->prepare("UPDATE reclamos SET estado = 'en_revision', actualizado_el = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // ✅ Notificar al asesor del cambio de estado automático
                    try {
                        notificarCambioEstado($id, 'pendiente', 'en_revision');
                    } catch (Exception $e) {
                        error_log("⚠️ Error al notificar cambio automático del reclamo #$id: " . $e->getMessage());
                    }
                }
                
                header("Location: ver.php?id=" . $id);
                exit();
                
            } catch (PDOException $e) {
                $error = "❌ Error al enviar el mensaje: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// PROCESAR CAMBIO DE ESTADO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'cambiar_estado') {
    $nuevo_estado = trim($_POST['nuevo_estado'] ?? '');
    
    if (!array_key_exists($nuevo_estado, $estados)) {
        $error = "❌ Estado no válido";
    } else {
        try {
            // Si pasa a "resuelto", guardamos la fecha de cierre
            if ($nuevo_estado == 'resuelto') {
                $stmt = $pdo->prepare("UPDATE reclamos SET estado = ?, cerrado_el = NOW(), actualizado_el = NOW() WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE reclamos SET estado = ?, cerrado_el = NULL, actualizado_el = NOW() WHERE id = ?");
            }
            $stmt->execute([$nuevo_estado, $id]);
            
            // ✅ Notificar por correo al asesor sobre el cambio de estado
            try {
                notificarCambioEstado($id, $reclamo['estado'], $nuevo_estado);
            } catch (Exception $e) {
                error_log("⚠️ Error al notificar cambio de estado del reclamo #$id: " . $e->getMessage());
            }
            
            $_SESSION['mensaje'] = "✅ Estado cambiado a: " . $estados[$nuevo_estado]['texto'];
            header("Location: ver.php?id=" . $id);
            exit();
            
        } catch (PDOException $e) {
            $error = "❌ Error al cambiar el estado: " . $e->getMessage();
        }
    }
}

// ============================================
// OBTENER MENSAJES
// ============================================
$stmt = $pdo->prepare("
    SELECT rm.*, u.nombre_completo, u.usuario, u.rol_id
    FROM reclamos_mensajes rm
    LEFT JOIN usuarios u ON rm.usuario_id = u.id
    WHERE rm.reclamo_id = ?
    ORDER BY rm.creado_el ASC
");
$stmt->execute([$id]);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$estado_info = $estados[$reclamo['estado']] ?? $estados['pendiente'];

// Recuperar mensajes de sesión
$mensaje_exito = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje_exito = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamo #<?php echo str_pad($reclamo['id'], 4, '0', STR_PAD_LEFT); ?> | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            margin-bottom: 20px;
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

        /* ===== INFO DEL RECLAMO ===== */
        .reclamo-header {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .reclamo-titulo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .reclamo-titulo h2 {
            font-size: 20px;
            color: #12232b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .reclamo-titulo h2 i { color: #e67e22; }
        .reclamo-id {
            color: #7f8c8d;
            font-size: 14px;
            font-weight: 600;
        }

        .reclamo-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f0f2f5;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .meta-item i {
            width: 35px;
            height: 35px;
            background: #e8f0fe;
            color: #173742;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .meta-item .info {
            display: flex;
            flex-direction: column;
        }
        .meta-item .label {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .meta-item .valor {
            font-size: 13px;
            font-weight: 500;
            color: #2c3e50;
        }

        .badge-estado {
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .estado-pendiente {
            background: #fef9e7;
            color: #7f6000;
            border: 2px solid #f39c12;
        }
        .estado-revision {
            background: #d6eaf8;
            color: #1a5276;
            border: 2px solid #3498db;
        }
        .estado-resuelto {
            background: #d5f5e3;
            color: #1a7a3a;
            border: 2px solid #27ae60;
        }

        /* ===== BOTONES DE ESTADO ===== */
        .estado-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f0f2f5;
        }
        .estado-actions .btn-estado {
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            color: white;
        }
        .btn-estado.en-revision { background: #3498db; }
        .btn-estado.en-revision:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-estado.resuelto { background: #27ae60; }
        .btn-estado.resuelto:hover { background: #219a52; transform: translateY(-2px); }
        .btn-estado:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .info-resuelto {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #d5f5e3;
            color: #1a7a3a;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #27ae60;
        }

        /* ===== CHAT ===== */
        .chat-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 600px;
        }

        .chat-header {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            padding: 18px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-header i {
            font-size: 22px;
            color: #ffc107;
        }
        .chat-header .info {
            flex: 1;
        }
        .chat-header .info .titulo {
            font-size: 15px;
            font-weight: 600;
        }
        .chat-header .info .sub {
            font-size: 12px;
            color: #8aa8b8;
        }

        /* ===== MENSAJES ===== */
        .chat-mensajes {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .chat-mensajes::-webkit-scrollbar {
            width: 8px;
        }
        .chat-mensajes::-webkit-scrollbar-track {
            background: #e0e0e0;
        }
        .chat-mensajes::-webkit-scrollbar-thumb {
            background: #b0b0b0;
            border-radius: 4px;
        }
        .chat-mensajes::-webkit-scrollbar-thumb:hover {
            background: #909090;
        }

        .mensaje {
            display: flex;
            gap: 12px;
            max-width: 75%;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensaje.propio {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .mensaje-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .mensaje-avatar.asesor {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }
        .mensaje-avatar.admin {
            background: linear-gradient(135deg, #e67e22, #f39c12);
        }

        .mensaje-contenido {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .mensaje.propio .mensaje-contenido {
            align-items: flex-end;
        }

        .mensaje-autor {
            font-size: 12px;
            font-weight: 600;
            color: #173742;
            padding: 0 5px;
        }
        .mensaje-autor .rol-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            margin-left: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .rol-badge.asesor {
            background: #d5f5e3;
            color: #1a7a3a;
        }
        .rol-badge.admin {
            background: #fdebd0;
            color: #a04000;
        }

        .mensaje-burbuja {
            background: white;
            padding: 12px 18px;
            border-radius: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            font-size: 14px;
            line-height: 1.5;
            color: #2c3e50;
            word-wrap: break-word;
            white-space: pre-wrap;
            position: relative;
        }

        .mensaje:not(.propio) .mensaje-burbuja {
            border-top-left-radius: 4px;
        }

        .mensaje.propio .mensaje-burbuja {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            border-top-right-radius: 4px;
        }

        .mensaje-hora {
            font-size: 11px;
            color: #7f8c8d;
            padding: 0 5px;
        }
        .mensaje.propio .mensaje-hora {
            text-align: right;
        }

        /* ===== IMAGEN EN MENSAJE ===== */
        .mensaje-imagen {
            max-width: 280px;
            margin: 5px 0;
        }
        .mensaje.propio .mensaje-imagen {
            margin-left: auto;
        }
        .mensaje-imagen img {
            width: 100%;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: block;
        }
        .mensaje-imagen img:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        /* ===== INPUT MENSAJE ===== */
        .chat-input {
            background: white;
            padding: 18px 25px;
            border-top: 2px solid #f0f2f5;
        }
        .chat-input form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .input-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            width: 100%;
        }
        .chat-input textarea {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            resize: none;
            min-height: 48px;
            max-height: 120px;
            transition: all 0.3s ease;
        }
        .chat-input textarea:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }

        /* ===== BOTÓN ADJUNTAR ===== */
        .btn-adjuntar {
            background: #ecf0f1;
            color: #173742;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        .btn-adjuntar:hover {
            background: #173742;
            color: white;
            transform: scale(1.08);
        }
        .btn-adjuntar.activo {
            background: #27ae60;
            color: white;
        }

        /* ===== PREVIEW IMAGEN ===== */
        .preview-imagen {
            position: relative;
            padding: 10px 14px;
            background: #f0f2f5;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: 2px dashed #b0b0b0;
            max-width: 100%;
        }
        .preview-imagen img {
            max-height: 70px;
            max-width: 100px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .preview-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
            flex: 1;
        }
        .nombre-imagen {
            font-size: 12px;
            color: #2c3e50;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 220px;
        }
        .peso-imagen {
            font-size: 11px;
            color: #7f8c8d;
        }
        .btn-quitar-imagen {
            background: #e74c3c;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .btn-quitar-imagen:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        .btn-enviar {
            background: linear-gradient(135deg, #e67e22, #f39c12);
            color: white;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        .btn-enviar:hover {
            transform: scale(1.1) rotate(15deg);
            box-shadow: 0 6px 18px rgba(230,126,34,0.4);
        }
        .btn-enviar:active {
            transform: scale(0.95);
        }

        /* ===== RECLAMO RESUELTO ===== */
        .chat-cerrado {
            background: #d5f5e3;
            padding: 20px;
            text-align: center;
            border-top: 2px solid #f0f2f5;
            color: #1a7a3a;
            font-size: 14px;
        }
        .chat-cerrado i {
            font-size: 24px;
            color: #27ae60;
            margin-bottom: 8px;
            display: block;
        }

        /* ===== EMPTY CHAT ===== */
        .chat-vacio {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        .chat-vacio i {
            font-size: 60px;
            color: #d3d3d3;
            margin-bottom: 15px;
            display: block;
        }

        /* ===== MENSAJES FLASH ===== */
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #e74c3c;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #27ae60;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        /* ===== LIGHTBOX ===== */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.92);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            cursor: zoom-out;
        }
        .lightbox.activo {
            display: flex;
            animation: fadeIn 0.2s ease;
        }
        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            transition: transform 0.2s ease;
            line-height: 1;
        }
        .lightbox-close:hover {
            transform: scale(1.2) rotate(90deg);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .container { padding: 10px; }
            .chat-container { height: calc(100vh - 250px); min-height: 500px; }
            .mensaje { max-width: 90%; }
            .reclamo-titulo { flex-direction: column; align-items: flex-start; }
            .chat-mensajes { padding: 15px; }
            .chat-input { padding: 12px 15px; }
            .estado-actions { flex-direction: column; }
            .estado-actions .btn-estado { width: 100%; justify-content: center; }
            .nombre-imagen { max-width: 120px; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
                <p class="logo-subtitle">| Reclamo #<?php echo str_pad($reclamo['id'], 4, '0', STR_PAD_LEFT); ?></p>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <!-- ===== MENSAJES FLASH ===== -->
        <?php if ($mensaje_exito): ?>
            <div class="mensaje-exito" id="mensajeExito">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ===== INFO DEL RECLAMO ===== -->
        <div class="reclamo-header">
            <div class="reclamo-titulo">
                <h2>
                    <i class="fas fa-comments"></i>
                    <?php echo htmlspecialchars($reclamo['asunto']); ?>
                </h2>
                <span class="badge-estado <?php echo $estado_info['clase']; ?>">
                    <i class="fas <?php echo $estado_info['icono']; ?>"></i>
                    <?php echo $estado_info['texto']; ?>
                </span>
            </div>
            <div class="reclamo-id">
                Reclamo #<?php echo str_pad($reclamo['id'], 4, '0', STR_PAD_LEFT); ?> 
                · Creado el <?php echo date('d/m/Y H:i', strtotime($reclamo['creado_el'])); ?>
            </div>

            <div class="reclamo-meta">
                <div class="meta-item">
                    <i class="fas fa-user"></i>
                    <div class="info">
                        <span class="label">Asesor</span>
                        <span class="valor">
                            <?php echo htmlspecialchars($reclamo['asesor_nombre']); ?>
                            <?php if (!empty($reclamo['asesor_codigo'])): ?>
                                (<?php echo htmlspecialchars($reclamo['asesor_codigo']); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="meta-item">
                    <i class="fas fa-tag"></i>
                    <div class="info">
                        <span class="label">Tipo</span>
                        <span class="valor"><?php echo $tipos_comisiones[$reclamo['tipo_comision']] ?? $reclamo['tipo_comision']; ?></span>
                    </div>
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="info">
                        <span class="label">Período</span>
                        <span class="valor"><?php echo $meses_espanol[$reclamo['mes']] . ' ' . $reclamo['anio']; ?></span>
                    </div>
                </div>
                <div class="meta-item">
                    <i class="fas fa-comment"></i>
                    <div class="info">
                        <span class="label">Mensajes</span>
                        <span class="valor"><?php echo count($mensajes); ?></span>
                    </div>
                </div>
            </div>

            <!-- ===== BOTONES DE ESTADO ===== -->
            <div class="estado-actions">
                <?php if ($reclamo['estado'] == 'resuelto'): ?>
                    <div class="info-resuelto">
                        <i class="fas fa-check-circle"></i>
                        Este reclamo fue resuelto
                        <?php if (!empty($reclamo['cerrado_el'])): ?>
                            el <?php echo date('d/m/Y H:i', strtotime($reclamo['cerrado_el'])); ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if ($reclamo['estado'] != 'en_revision'): ?>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="action" value="cambiar_estado">
                            <input type="hidden" name="nuevo_estado" value="en_revision">
                            <button type="submit" class="btn-estado en-revision">
                                <i class="fas fa-search"></i> Marcar en Revisión
                            </button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="cambiar_estado">
                        <input type="hidden" name="nuevo_estado" value="resuelto">
                        <button type="submit" class="btn-estado resuelto" onclick="return confirm('¿Marcar este reclamo como resuelto? El asesor no podrá enviar más mensajes.')">
                            <i class="fas fa-check-circle"></i> Marcar como Resuelto
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== CHAT ===== -->
        <div class="chat-container">
            <div class="chat-header">
                <i class="fas fa-comments"></i>
                <div class="info">
                    <div class="titulo">Chat del Reclamo</div>
                    <div class="sub">Conversación entre asesor y área de comisiones</div>
                </div>
            </div>

            <!-- ===== MENSAJES ===== -->
            <div class="chat-mensajes" id="chatMensajes">
                <?php if (empty($mensajes)): ?>
                    <div class="chat-vacio">
                        <i class="fas fa-comment-slash"></i>
                        <p>No hay mensajes aún</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($mensajes as $msg): 
                        $es_propio = ($msg['usuario_id'] == $usuario_id);
                        $es_admin = ($msg['rol_id'] == 1 || $msg['rol_id'] == 2);
                        
                        // Iniciales del nombre
                        $iniciales = '';
                        $partes = explode(' ', trim($msg['nombre_completo'] ?? 'Usuario'));
                        if (count($partes) >= 2) {
                            $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
                        } else {
                            $iniciales = strtoupper(substr($partes[0], 0, 2));
                        }
                    ?>
                        <div class="mensaje <?php echo $es_propio ? 'propio' : ''; ?>">
                            <div class="mensaje-avatar <?php echo $es_admin ? 'admin' : 'asesor'; ?>">
                                <?php echo $iniciales; ?>
                            </div>
                            <div class="mensaje-contenido">
                                <div class="mensaje-autor">
                                    <?php echo htmlspecialchars($msg['nombre_completo'] ?? 'Usuario'); ?>
                                    <span class="rol-badge <?php echo $es_admin ? 'admin' : 'asesor'; ?>">
                                        <?php echo $es_admin ? 'Admin' : 'Asesor'; ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($msg['imagen'])): ?>
                                    <div class="mensaje-imagen">
                                        <img 
                                            src="../../../uploads/reclamos/<?php echo $reclamo['id']; ?>/<?php echo htmlspecialchars($msg['imagen']); ?>" 
                                            alt="Adjunto"
                                            onclick="abrirLightbox(this.src)"
                                        >
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty(trim($msg['mensaje']))): ?>
                                    <div class="mensaje-burbuja"><?php echo nl2br(htmlspecialchars($msg['mensaje'])); ?></div>
                                <?php endif; ?>
                                
                                <div class="mensaje-hora">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($msg['creado_el'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ===== INPUT ===== -->
            <?php if ($reclamo['estado'] == 'resuelto'): ?>
                <div class="chat-cerrado">
                    <i class="fas fa-check-circle"></i>
                    <strong>Este reclamo fue resuelto</strong>
                    <p style="margin-top: 5px;">No se pueden enviar más mensajes</p>
                </div>
            <?php else: ?>
                <div class="chat-input">
                    <form method="POST" action="" id="formMensaje" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="mensaje">
                        <div class="input-row">
                            <button type="button" class="btn-adjuntar" id="btnAdjuntar" title="Adjuntar imagen">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <input type="file" name="imagen" id="inputImagen" accept="image/jpeg,image/jpg,image/png" style="display: none;">
                            <textarea 
                                name="mensaje" 
                                id="inputMensaje"
                                placeholder="Escribe tu respuesta aquí..."
                                rows="1"
                            ></textarea>
                            <button type="submit" class="btn-enviar" title="Enviar mensaje">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        <div id="previewImagen" class="preview-imagen" style="display: none;">
                            <img id="previewImg" src="" alt="Vista previa">
                            <div class="preview-info">
                                <span id="nombreImagen" class="nombre-imagen"></span>
                                <span id="pesoImagen" class="peso-imagen"></span>
                            </div>
                            <button type="button" id="btnQuitarImagen" class="btn-quitar-imagen" title="Quitar imagen">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ============================================
        // SCROLL AL FINAL DEL CHAT
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const chatMensajes = document.getElementById('chatMensajes');
            if (chatMensajes) {
                chatMensajes.scrollTop = chatMensajes.scrollHeight;
            }

            // Ocultar mensaje de éxito
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

        // ============================================
        // TEXTAREA AUTO-RESIZE + ENVIAR CON ENTER
        // ============================================
        const textarea = document.getElementById('inputMensaje');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    document.getElementById('formMensaje').submit();
                }
            });

            textarea.focus();
        }

        // ============================================
        // ADJUNTAR IMAGEN
        // ============================================
        const btnAdjuntar = document.getElementById('btnAdjuntar');
        const inputImagen = document.getElementById('inputImagen');
        const previewImagen = document.getElementById('previewImagen');
        const previewImg = document.getElementById('previewImg');
        const btnQuitarImagen = document.getElementById('btnQuitarImagen');
        const nombreImagen = document.getElementById('nombreImagen');
        const pesoImagen = document.getElementById('pesoImagen');

        if (btnAdjuntar && inputImagen) {
            btnAdjuntar.addEventListener('click', function() {
                inputImagen.click();
            });

            inputImagen.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;

                const tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!tiposPermitidos.includes(file.type)) {
                    alert('❌ Solo se permiten imágenes JPG, JPEG o PNG');
                    this.value = '';
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    alert('❌ La imagen no debe pesar más de 5 MB');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    nombreImagen.textContent = file.name;
                    pesoImagen.textContent = (file.size / 1024).toFixed(1) + ' KB';
                    previewImagen.style.display = 'inline-flex';
                    btnAdjuntar.classList.add('activo');
                };
                reader.readAsDataURL(file);
            });

            btnQuitarImagen.addEventListener('click', function() {
                inputImagen.value = '';
                previewImg.src = '';
                nombreImagen.textContent = '';
                pesoImagen.textContent = '';
                previewImagen.style.display = 'none';
                btnAdjuntar.classList.remove('activo');
            });
        }

        // ============================================
        // LIGHTBOX
        // ============================================
        function abrirLightbox(src) {
            let lightbox = document.getElementById('lightboxGlobal');
            if (!lightbox) {
                lightbox = document.createElement('div');
                lightbox.id = 'lightboxGlobal';
                lightbox.className = 'lightbox';
                lightbox.innerHTML = `
                    <span class="lightbox-close">&times;</span>
                    <img src="" alt="Vista ampliada">
                `;
                document.body.appendChild(lightbox);

                lightbox.addEventListener('click', function(e) {
                    if (e.target === lightbox || e.target.classList.contains('lightbox-close')) {
                        lightbox.classList.remove('activo');
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        lightbox.classList.remove('activo');
                    }
                });
            }
            lightbox.querySelector('img').src = src;
            lightbox.classList.add('activo');
        }
    </script>
</body>
</html>