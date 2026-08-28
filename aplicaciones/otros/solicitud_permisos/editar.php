<?php
// aplicaciones/otros/solicitud_permisos/editar.php - Editar solicitud de permiso
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO - Requiere login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];
$nombre_usuario = $usuario['nombre_completo'];
$es_jefe_usuario = $usuario['es_jefe'] ?? 0;

$error = '';
$mensaje = '';
$solicitud = null;

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
$stmt = $pdo->prepare("
    SELECT sp.*, 
           u.nombre_completo as solicitante_nombre,
           j.nombre_completo as jefe_nombre
    FROM solicitudes_permisos sp
    LEFT JOIN usuarios u ON sp.usuario_id = u.id
    LEFT JOIN usuarios j ON sp.jefe_inmediato = j.id
    WHERE sp.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ VERIFICAR QUE EXISTE
if (!$solicitud) {
    $_SESSION['error'] = "❌ La solicitud no existe";
    header('Location: index.php');
    exit();
}

// ✅ VERIFICAR PERMISOS:
// - Solo el usuario que creó la solicitud puede editarla
// - O un administrador (rol_id = 1)
// - Solo si está en estado 'pendiente'
$es_propietario = ($solicitud['usuario_id'] == $usuario_id);
$es_admin = ($usuario['rol'] == 1);

if (!$es_propietario && !$es_admin) {
    $_SESSION['error'] = "❌ No tienes permiso para editar esta solicitud";
    header('Location: index.php');
    exit();
}

// ✅ VERIFICAR ESTADO
if ($solicitud['estado'] != 'pendiente') {
    $_SESSION['error'] = "❌ No puedes editar una solicitud que ya fue " . $solicitud['estado'];
    header('Location: index.php');
    exit();
}

// ============================================
// TIPOS DE PERMISOS
// ============================================
$tipos_permisos = [
    'Cita Medica',
    'Cita Medica Urgencias',
    'Cita con Especialista',
    'Acudiente de Obligaciones Escolares',
    'Permiso para Atender Situaciones Judiciales o Administrativas',
    'Cumpleaños',
    'Permiso Remunerado',
    'Permiso Menos a 4 Horas',
    'Jurado de Votacion',
    'Permiso por Votacion',
    'Jornada Flexible'
];

// ============================================
// LISTA DE JEFES
// ============================================
$stmt_jefes = $pdo->prepare("
    SELECT id, nombre_completo, cargo 
    FROM usuarios 
    WHERE es_jefe = 1 AND activo = 1
    ORDER BY nombre_completo ASC
");
$stmt_jefes->execute();
$jefes = $stmt_jefes->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// CONFIGURACIÓN DE ARCHIVOS
// ============================================
$carpeta_destino = '../../../uploads/permisos/';
$archivo_nombre = $solicitud['archivo'] ?? '';

if (!file_exists($carpeta_destino)) {
    mkdir($carpeta_destino, 0777, true);
}

// ============================================
// PROCESAR FORMULARIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = trim($_POST['tipo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $jefe_inmediato = intval($_POST['jefe_inmediato'] ?? 0);
    
    // Procesar nuevo archivo si se sube
    $nuevo_archivo = $archivo_nombre;
    $archivo_error = '';
    
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo_tmp = $_FILES['archivo']['tmp_name'];
        $archivo_nombre_original = $_FILES['archivo']['name'];
        $archivo_tamano = $_FILES['archivo']['size'];
        
        $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        $extension = strtolower(pathinfo($archivo_nombre_original, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $extensiones_permitidas)) {
            $archivo_error = "❌ Solo se permiten archivos PDF, JPG y PNG";
        } elseif ($archivo_tamano > 6291456) {
            $archivo_error = "❌ El archivo no debe superar los 6MB";
        } else {
            // Eliminar archivo anterior si existe
            if (!empty($archivo_nombre) && file_exists($carpeta_destino . $archivo_nombre)) {
                unlink($carpeta_destino . $archivo_nombre);
            }
            
            $nuevo_archivo = 'permiso_' . time() . '_' . uniqid() . '.' . $extension;
            $ruta_completa = $carpeta_destino . $nuevo_archivo;
            
            if (!move_uploaded_file($archivo_tmp, $ruta_completa)) {
                $archivo_error = "❌ Error al subir el archivo";
            }
        }
    } elseif (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $archivo_error = "❌ Error al subir el archivo: " . $_FILES['archivo']['error'];
    }
    
    // ============================================
    // VALIDACIONES
    // ============================================
    if (empty($tipo)) {
        $error = "❌ Debes seleccionar un tipo de permiso";
    } elseif (empty($jefe_inmediato)) {
        $error = "❌ Debes seleccionar un jefe inmediato";
    } elseif (empty($fecha_inicio)) {
        $error = "❌ Debes seleccionar la fecha y hora de inicio";
    } elseif (empty($fecha_fin)) {
        $error = "❌ Debes seleccionar la fecha y hora de fin";
    } elseif (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
        $error = "❌ La fecha de inicio no puede ser mayor a la fecha de fin";
    } elseif (strtotime($fecha_inicio) < strtotime(date('Y-m-d 00:00:00'))) {
        $error = "❌ La fecha de inicio no puede ser anterior a hoy";
    } elseif (!empty($archivo_error)) {
        $error = $archivo_error;
    } else {
        try {
            // ============================================
            // ACTUALIZAR SOLICITUD
            // ============================================
            $stmt = $pdo->prepare("
                UPDATE solicitudes_permisos 
                SET jefe_inmediato = ?,
                    tipo = ?,
                    descripcion = ?,
                    archivo = ?,
                    fecha_inicio = ?,
                    fecha_fin = ?
                WHERE id = ? AND estado = 'pendiente'
            ");
            
            $stmt->execute([
                $jefe_inmediato,
                $tipo,
                $descripcion,
                $nuevo_archivo,
                $fecha_inicio,
                $fecha_fin,
                $id
            ]);
            
            $_SESSION['mensaje'] = "✅ Solicitud actualizada correctamente";
            header("Location: index.php");
            exit();
            
        } catch (PDOException $e) {
            $error = "❌ Error al actualizar la solicitud: " . $e->getMessage();
        }
    }
}

// ============================================
// FUNCIÓN PARA ELIMINAR ARCHIVO
// ============================================
if (isset($_GET['eliminar_archivo']) && $_GET['eliminar_archivo'] == 1) {
    if (!empty($solicitud['archivo'])) {
        $ruta_archivo = $carpeta_destino . $solicitud['archivo'];
        if (file_exists($ruta_archivo)) {
            unlink($ruta_archivo);
        }
        
        // Actualizar base de datos
        $stmt = $pdo->prepare("UPDATE solicitudes_permisos SET archivo = '' WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['mensaje'] = "✅ Archivo eliminado correctamente";
        header("Location: editar.php?id=$id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Solicitud de Permiso | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 700px; margin: 50px auto; padding: 20px; }

        .header {
            background: #12232b;
            padding: 20px 0;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        .header .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; gap: 10px; }
        .logo h1 { color: white; font-size: 24px; }
        .logo span { color: #445960; }
        .logo-subtitle { color: #8aa8b8; font-size: 14px; }
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
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #f39c12;
            padding-left: 15px;
        }

        .info-usuario {
            background: #e8f0fe;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            font-size: 13px;
            color: #2c3e50;
        }
        .info-usuario i { color: #173742; margin-right: 8px; }
        .info-usuario strong { color: #12232b; }

        .info-estado {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .info-estado.pendiente {
            background: #fef9e7;
            border-left: 4px solid #f39c12;
            color: #7f6000;
        }
        .info-estado i { margin-right: 8px; }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group label .obligatorio {
            color: #e74c3c;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #173742;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group .file-input-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .form-group .file-input-wrapper input[type="file"] {
            flex: 1;
            padding: 10px;
            border: 2px dashed #d3d3d3;
            border-radius: 8px;
            cursor: pointer;
            background: #fafafa;
            transition: all 0.3s;
        }
        .form-group .file-input-wrapper input[type="file"]:hover {
            border-color: #173742;
            background: #f0f4f8;
        }
        .form-group .file-info {
            font-size: 12px;
            color: #7f8c8d;
        }
        .form-group .file-info i { color: #173742; }

        .archivo-actual {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .archivo-actual .nombre {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .archivo-actual .nombre i { 
            font-size: 24px; 
            color: #173742;
        }
        .btn-eliminar-archivo {
            background: #e74c3c;
            color: white;
            padding: 5px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
        }
        .btn-eliminar-archivo:hover {
            background: #c0392b;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }

        .btn-guardar {
            background: #f39c12;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-guardar:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        .btn-eliminar {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        .btn-eliminar:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

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

        .info-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .acciones {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .acciones .btn-cancelar {
            background: #7f8c8d;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            flex: 1;
            transition: all 0.3s ease;
        }
        .acciones .btn-cancelar:hover {
            background: #6c7a7d;
            transform: translateY(-2px);
        }
        .acciones .btn-guardar {
            flex: 2;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div>
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <p class="logo-subtitle" style="color: #8aa8b8; font-size: 14px;">| Editar Permiso</p>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fas fa-edit"></i> Editar Solicitud de Permiso</h2>

            <form method="POST" action="" enctype="multipart/form-data">
                <!-- JEFE INMEDIATO -->
                <div class="form-group">
                    <label>Jefe Inmediato <span class="obligatorio">*</span></label>
                    <select name="jefe_inmediato" required>
                        <option value="">Seleccione un jefe</option>
                        <?php foreach ($jefes as $jefe): ?>
                            <option value="<?php echo $jefe['id']; ?>" <?php echo ($solicitud['jefe_inmediato'] ?? '') == $jefe['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jefe['nombre_completo']); ?>
                                <?php if (!empty($jefe['cargo'])): ?>
                                    (<?php echo htmlspecialchars($jefe['cargo']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- TIPO DE PERMISO -->
                <div class="form-group">
                    <label>Tipo de Permiso <span class="obligatorio">*</span></label>
                    <select name="tipo" required>
                        <option value="">Seleccione un tipo</option>
                        <?php foreach ($tipos_permisos as $value): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($solicitud['tipo'] ?? '') == $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($value); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- DESCRIPCIÓN -->
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" placeholder="Describe el motivo de tu solicitud..."><?php echo htmlspecialchars($solicitud['descripcion'] ?? ''); ?></textarea>
                </div>

                <!-- ARCHIVO -->
                <div class="form-group">
                    <label>Adjuntar Archivo (PDF o Imagen)</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png">
                        <span class="file-info">
                            <i class="fas fa-info-circle"></i> PDF, JPG, PNG (max 6MB)
                        </span>
                    </div>
                    
                    <?php if (!empty($solicitud['archivo'])): ?>
                        <div class="archivo-actual">
                            <div class="nombre">
                                <i class="fas fa-file-pdf"></i>
                                <span><?php echo htmlspecialchars($solicitud['archivo']); ?></span>
                            </div>
                            <a href="editar.php?id=<?php echo $id; ?>&eliminar_archivo=1" class="btn-eliminar-archivo" onclick="return confirm('¿Eliminar este archivo?')">
                                <i class="fas fa-trash"></i> Eliminar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- FECHAS -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha y Hora de Inicio <span class="obligatorio">*</span></label>
                        <input type="datetime-local" name="fecha_inicio" value="<?php echo date('Y-m-d\TH:i', strtotime($solicitud['fecha_inicio'])); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha y Hora de Fin <span class="obligatorio">*</span></label>
                        <input type="datetime-local" name="fecha_fin" value="<?php echo date('Y-m-d\TH:i', strtotime($solicitud['fecha_fin'])); ?>" required>
                    </div>
                </div>

                <div class="info-text" style="margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> 
                    Las solicitudes serán revisadas y aprobadas por tu jefe inmediato.
                </div>

                <div class="acciones">
                    <a href="index.php" class="btn-cancelar">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> Actualizar Solicitud
                    </button>
                </div>
            </form>

            <!-- ELIMINAR SOLICITUD -->
            <hr style="margin: 30px 0; border-color: #d3d3d3;">
            <div style="text-align: center;">
                <form method="POST" action="eliminar.php" onsubmit="return confirm('¿Estás seguro de eliminar esta solicitud? Esta acción no se puede deshacer.')">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn-eliminar">
                        <i class="fas fa-trash-alt"></i> Eliminar Solicitud
                    </button>
                </form>
                <p style="font-size: 12px; color: #7f8c8d; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i>
                    Solo puedes eliminar solicitudes en estado <strong>pendiente</strong>
                </p>
            </div>
        </div>
    </div>
</body>
</html>