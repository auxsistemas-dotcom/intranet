<?php
// aplicaciones/otros/solicitud_permisos/crear.php - Crear nueva solicitud de permiso
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

// Tipos de permisos
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
// LISTA DE JEFES DESDE LA BASE DE DATOS
// ============================================
$stmt = $pdo->prepare("
    SELECT id, nombre_completo 
    FROM usuarios 
    WHERE es_jefe = 1 AND activo = 1
    ORDER BY nombre_completo ASC
");
$stmt->execute();
$jefes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configuración de archivos
$carpeta_destino = '../../../uploads/permisos/';
$archivo_nombre = '';
$archivo_error = '';

if (!file_exists($carpeta_destino)) {
    mkdir($carpeta_destino, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = trim($_POST['tipo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $jefe_inmediato = intval($_POST['jefe_inmediato'] ?? 0);
    
    // Procesar archivo
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
            $archivo_nombre = 'permiso_' . time() . '_' . uniqid() . '.' . $extension;
            $ruta_completa = $carpeta_destino . $archivo_nombre;
            
            if (!move_uploaded_file($archivo_tmp, $ruta_completa)) {
                $archivo_error = "❌ Error al subir el archivo";
            }
        }
    } elseif (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $archivo_error = "❌ Error al subir el archivo: " . $_FILES['archivo']['error'];
    }
    
    // Validaciones
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
    } elseif (strtotime(date('Y-m-d', strtotime($fecha_inicio))) < strtotime(date('Y-m-d'))) {
        $error = "❌ La fecha de inicio no puede ser anterior a hoy";
    } elseif (!empty($archivo_error)) {
        $error = $archivo_error;
    } else {
        try {
            // Ya no necesitas obtener el nombre, solo guarda el ID directamente
            $stmt = $pdo->prepare("
                INSERT INTO solicitudes_permisos 
                (usuario_id, jefe_inmediato, tipo, descripcion, archivo, fecha_inicio, fecha_fin) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $usuario_id, 
                $jefe_inmediato,  // ✅ Guarda el ID del jefe (INT)
                $tipo, 
                $descripcion, 
                $archivo_nombre, 
                $fecha_inicio, 
                $fecha_fin
            ]);
            
            $_SESSION['mensaje'] = "✅ Solicitud de permiso creada correctamente";
            header("Location: index.php");
            exit();
            
        } catch (PDOException $e) {
            $error = "❌ Error al crear la solicitud: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud de Permiso | INTRANET</title>
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
            border-left: 4px solid #173742;
            padding-left: 15px;
        }

        .info-jefe {
            background: #e8f0fe;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f39c12;
            font-size: 13px;
            color: #2c3e50;
        }
        .info-jefe i { color: #f39c12; margin-right: 8px; }

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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }

        .btn-guardar {
            background: #173742;
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
            background: #445960;
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
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div>
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <p class="logo-subtitle" style="color: #8aa8b8; font-size: 14px;">| Crear Permiso</p>
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
            <h2><i class="fas fa-plus-circle"></i> Nueva Solicitud de Permiso</h2>

            <div class="info-usuario">
                <i class="fas fa-user"></i>
                <strong>Usuario:</strong> <?php echo htmlspecialchars($nombre_usuario); ?>
                <?php if ($es_jefe_usuario): ?>
                    <span style="background: #f39c12; color: white; padding: 2px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 10px;">
                        <i class="fas fa-user-tie"></i> Jefe
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($es_jefe_usuario): ?>
                <div class="info-jefe">
                    <i class="fas fa-info-circle"></i>
                    <strong>Eres jefe o líder.</strong> 
                    Las solicitudes que apruebes o rechaces se registrarán con tu nombre.
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <!-- JEFE INMEDIATO -->
                <div class="form-group">
                    <label>Jefe Inmediato <span class="obligatorio">*</span></label>
                    <select name="jefe_inmediato" required>
                        <option value="">Seleccione un jefe</option>
                        <?php foreach ($jefes as $jefe): ?>
                            <option value="<?php echo $jefe['id']; ?>" <?php echo ($jefe_inmediato ?? '') == $jefe['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jefe['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($es_jefe_usuario): ?>
                        <div class="info-text" style="margin-top: 5px; color: #f39c12;">
                            <i class="fas fa-info-circle"></i> 
                            Como jefe, selecciona tu jefe para que sepa que estás solicitando un permiso.
                        </div>
                    <?php else: ?>
                        <div class="info-text" style="margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> 
                            Selecciona tu jefe inmediato para que reciba la solicitud.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Tipo de Permiso <span class="obligatorio">*</span></label>
                    <select name="tipo" required>
                        <option value="">Seleccione un tipo</option>
                        <?php foreach ($tipos_permisos as $value): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($tipo ?? '') == $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($value); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" placeholder="Describe el motivo de tu solicitud..."><?php echo htmlspecialchars($descripcion ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Adjuntar Archivo (PDF o Imagen)</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png">
                        <span class="file-info">
                            <i class="fas fa-info-circle"></i> PDF, JPG, PNG (max 6MB)
                        </span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha y Hora de Inicio <span class="obligatorio">*</span></label>
                        <input type="datetime-local" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha y Hora de Fin <span class="obligatorio">*</span></label>
                        <input type="datetime-local" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin ?? ''); ?>" required>
                    </div>
                </div>

                <div class="info-text" style="margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> 
                    Las solicitudes serán revisadas y aprobadas por el área de Gestión Humana.
                </div>

                <button type="submit" class="btn-guardar">
                    <i class="fas fa-paper-plane"></i> Enviar Solicitud
                </button>
            </form>
        </div>
    </div>
</body>
</html>