<?php
// aplicaciones/otros/comercial/reclamos/crear.php - Crear nuevo reclamo
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth_check.php';
require_once '../../../../includes/reclamos_mailer.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../../login.php');
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];
$nombre_usuario = $usuario['nombre_completo'];

// ✅ OBTENER cod_asesor DIRECTAMENTE
$stmt = $pdo->prepare("SELECT cod_asesor FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$cod_asesor = $stmt->fetchColumn();

// ============================================
// SI NO TIENE CÓDIGO DE ASESOR
// ============================================
if (empty($cod_asesor)) {
    $_SESSION['error'] = "❌ No tienes código de asesor asignado";
    header('Location: ../index.php');
    exit();
}

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
// AÑOS DISPONIBLES
// ============================================
$años = [];
for ($i = 2026; $i <= intval(date('Y')); $i++) {
    $años[] = $i;
}

// ============================================
// MES VENCIDO (por defecto)
// ============================================
$mes_vencido = date('n') - 1;
$anio_vencido = date('Y');
if ($mes_vencido < 1) {
    $mes_vencido = 12;
    $anio_vencido = date('Y') - 1;
}

// ============================================
// VARIABLES DEL FORMULARIO
// ============================================
$error = '';
$tipo_comision = '';
$mes = $mes_vencido;
$anio = $anio_vencido;
$asunto = '';
$mensaje = '';

// ============================================
// PROCESAR FORMULARIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_comision = trim($_POST['tipo_comision'] ?? '');
    $mes = intval($_POST['mes'] ?? 0);
    $anio = intval($_POST['anio'] ?? 0);
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    
    // ✅ VALIDAR IMAGEN (opcional)
    $imagen_tmp = null;
    $imagen_ext = null;
    
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
                        // Guardar temporalmente la info para usarla después del INSERT
                        $imagen_tmp = $archivo['tmp_name'];
                        $imagen_ext = $ext;
                    }
                }
            }
        }
    }
    
    // Validaciones de texto (solo si no hubo error con la imagen)
    if (empty($error)) {
        if (empty($tipo_comision) || !array_key_exists($tipo_comision, $tipos_comisiones)) {
            $error = "❌ Debes seleccionar un tipo de comisión válido";
        } elseif ($mes < 1 || $mes > 12) {
            $error = "❌ Debes seleccionar un mes válido";
        } elseif ($anio < 2026 || $anio > intval(date('Y'))) {
            $error = "❌ Debes seleccionar un año válido";
        } elseif (empty($asunto)) {
            $error = "❌ El asunto es obligatorio";
        } elseif (strlen($asunto) < 5) {
            $error = "❌ El asunto debe tener al menos 5 caracteres";
        } elseif (empty($mensaje)) {
            $error = "❌ El mensaje es obligatorio";
        } elseif (strlen($mensaje) < 10) {
            $error = "❌ El mensaje debe tener al menos 10 caracteres";
        } else {
            try {
                $pdo->beginTransaction();
                
                // 1. Insertar el reclamo
                $stmt = $pdo->prepare("
                    INSERT INTO reclamos 
                    (asesor_id, tipo_comision, mes, anio, asunto, estado, creado_el) 
                    VALUES (?, ?, ?, ?, ?, 'pendiente', NOW())
                ");
                $stmt->execute([
                    $usuario_id,
                    $tipo_comision,
                    $mes,
                    $anio,
                    $asunto
                ]);
                
                $reclamo_id = $pdo->lastInsertId();
                
                // 2. Procesar imagen si existe
                $imagen_nombre = null;
                if ($imagen_tmp !== null) {
                    $carpeta_reclamo = __DIR__ . '/../../../../uploads/reclamos/' . $reclamo_id . '/';
                    if (!is_dir($carpeta_reclamo)) {
                        mkdir($carpeta_reclamo, 0755, true);
                    }
                    
                    $imagen_nombre = time() . '_' . bin2hex(random_bytes(4)) . '.' . $imagen_ext;
                    $ruta_destino = $carpeta_reclamo . $imagen_nombre;
                    
                    if (!move_uploaded_file($imagen_tmp, $ruta_destino)) {
                        throw new Exception("Error al guardar la imagen en el servidor");
                    }
                }
                
                // 3. Insertar el primer mensaje
                $stmt = $pdo->prepare("
                    INSERT INTO reclamos_mensajes 
                    (reclamo_id, usuario_id, mensaje, imagen, creado_el) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $reclamo_id,
                    $usuario_id,
                    $mensaje,
                    $imagen_nombre
                ]);
                
                $pdo->commit();
                
                // ✅ Notificar por correo a los supervisores (silencioso si falla)
                try {
                    notificarNuevoReclamo($reclamo_id);
                } catch (Exception $e) {
                    error_log("⚠️ Error al notificar nuevo reclamo #$reclamo_id: " . $e->getMessage());
                }
                
                $_SESSION['mensaje'] = "✅ Reclamo creado correctamente. Pronto recibirás respuesta.";
                header("Location: ver.php?id=" . $reclamo_id);
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                
                // Si se había movido la imagen, la borramos
                if (isset($ruta_destino) && file_exists($ruta_destino)) {
                    @unlink($ruta_destino);
                }
                
                $error = "❌ Error al crear el reclamo: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Reclamo | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }

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
        .logo {
            display: flex;
            flex-direction: column;
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
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .card h2 {
            font-size: 22px;
            color: #12232b;
            margin-bottom: 10px;
            border-left: 4px solid #e67e22;
            padding-left: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h2 i { color: #e67e22; }
        .card .subtitle {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 25px;
            padding-left: 19px;
        }

        /* ===== INFO USUARIO ===== */
        .info-usuario {
            background: #e8f0fe;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #173742;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .info-usuario i { color: #173742; font-size: 20px; }
        .info-usuario strong { color: #12232b; }
        .cod-badge {
            background: #ffc107;
            color: #12232b;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
        }

        /* ===== FORMULARIO ===== */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #2c3e50;
        }
        .form-group label .obligatorio {
            color: #e74c3c;
            margin-left: 3px;
        }
        .form-group label .opcional {
            color: #7f8c8d;
            font-size: 12px;
            font-weight: 400;
            margin-left: 5px;
        }
        .form-group label i {
            color: #173742;
            margin-right: 6px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
            background: white;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        .form-group .info-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }

        /* ===== ADJUNTAR IMAGEN ===== */
        .adjuntar-box {
            margin-top: 10px;
        }
        .btn-adjuntar {
            background: #ecf0f1;
            color: #173742;
            border: 2px dashed #b0b0b0;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            width: 100%;
            justify-content: center;
        }
        .btn-adjuntar:hover {
            background: #173742;
            color: white;
            border-color: #173742;
        }
        .btn-adjuntar.activo {
            background: #d5f5e3;
            border-color: #27ae60;
            border-style: solid;
            color: #1a7a3a;
        }

        .preview-imagen {
            position: relative;
            margin-top: 12px;
            padding: 12px 14px;
            background: #f0f2f5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid #27ae60;
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
            font-size: 13px;
            color: #2c3e50;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .peso-imagen {
            font-size: 11px;
            color: #7f8c8d;
        }
        .btn-quitar-imagen {
            background: #e74c3c;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 13px;
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

        /* ===== ACCIONES ===== */
        .acciones {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .btn-cancelar {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 14px 25px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            flex: 1;
            min-width: 150px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-cancelar:hover {
            background: #d5dbdb;
            transform: translateY(-2px);
        }
        .btn-guardar {
            background: linear-gradient(135deg, #e67e22, #f39c12);
            color: white;
            border: none;
            padding: 14px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            flex: 2;
            min-width: 200px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(230,126,34,0.3);
        }
        .btn-guardar:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(230,126,34,0.4);
        }

        /* ===== MENSAJES ===== */
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== INFO AYUDA ===== */
        .info-ayuda {
            background: #fef9e7;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #f39c12;
            margin-bottom: 20px;
            font-size: 13px;
            color: #7f6000;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .info-ayuda i {
            color: #f39c12;
            font-size: 18px;
            margin-top: 2px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .card { padding: 20px; }
            .acciones { flex-direction: column; }
            .btn-cancelar, .btn-guardar { width: 100%; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
                <p class="logo-subtitle">| Nuevo Reclamo</p>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <h2>
                <i class="fas fa-comments"></i>
                Nuevo Reclamo
            </h2>
            <p class="subtitle">Reporta una inconformidad sobre tus comisiones</p>

            <!-- ===== INFO USUARIO ===== -->
            <div class="info-usuario">
                <i class="fas fa-user-circle"></i>
                <span>
                    <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong>
                </span>
                <?php if (!$cod_asesor): ?>
                    <span class="cod-badge" style="background: #e74c3c; color: white;">
                        <i class="fas fa-exclamation-triangle"></i> Sin código
                    </span>
                <?php else: ?>
                    <span class="cod-badge">
                        <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($cod_asesor); ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- ===== INFO AYUDA ===== -->
            <div class="info-ayuda">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>¿Cómo funciona?</strong><br>
                    Al crear el reclamo, se abrirá un chat donde tú y el área de comisiones podrán conversar para resolver tu inconformidad. 
                    Recibirás respuesta lo antes posible.
                </div>
            </div>

            <!-- ===== ERROR ===== -->
            <?php if ($error): ?>
                <div class="mensaje-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- ===== FORMULARIO ===== -->
            <form method="POST" action="" enctype="multipart/form-data">
                
                <!-- Tipo de comisión y mes/año -->
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-tag"></i>
                            Tipo de Comisión
                            <span class="obligatorio">*</span>
                        </label>
                        <select name="tipo_comision" required>
                            <option value="">Selecciona un tipo</option>
                            <?php foreach ($tipos_comisiones as $key => $nombre): ?>
                                <option value="<?php echo $key; ?>" <?php echo $tipo_comision == $key ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar-alt"></i>
                            Mes
                            <span class="obligatorio">*</span>
                        </label>
                        <select name="mes" required>
                            <option value="">Selecciona un mes</option>
                            <?php foreach ($meses_espanol as $num => $nombre): ?>
                                <option value="<?php echo $num; ?>" <?php echo $mes == $num ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Año -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-calendar"></i>
                        Año
                        <span class="obligatorio">*</span>
                    </label>
                    <select name="anio" required>
                        <option value="">Selecciona un año</option>
                        <?php foreach ($años as $a): ?>
                            <option value="<?php echo $a; ?>" <?php echo $anio == $a ? 'selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Asunto -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-heading"></i>
                        Asunto
                        <span class="obligatorio">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="asunto" 
                        value="<?php echo htmlspecialchars($asunto); ?>" 
                        placeholder="Ej: No aparece mi comisión de la venta #12345"
                        maxlength="200"
                        required
                    >
                    <div class="info-text">
                        <i class="fas fa-info-circle"></i>
                        Sé breve y claro en el asunto
                    </div>
                </div>

                <!-- Mensaje -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-comment"></i>
                        Mensaje
                        <span class="obligatorio">*</span>
                    </label>
                    <textarea 
                        name="mensaje" 
                        placeholder="Describe tu inconformidad con el mayor detalle posible..."
                        required
                    ><?php echo htmlspecialchars($mensaje); ?></textarea>
                    <div class="info-text">
                        <i class="fas fa-info-circle"></i>
                        Mínimo 10 caracteres. Incluye detalles como número de venta, cliente, etc.
                    </div>

                    <!-- ===== ADJUNTAR IMAGEN ===== -->
                    <div class="adjuntar-box">
                        <button type="button" class="btn-adjuntar" id="btnAdjuntar">
                            <i class="fas fa-paperclip"></i>
                            Adjuntar imagen (opcional) — JPG, PNG · Máx 5 MB
                        </button>
                        <input type="file" name="imagen" id="inputImagen" accept="image/jpeg,image/jpg,image/png" style="display: none;">
                        
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
                    </div>
                </div>

                <!-- Acciones -->
                <div class="acciones">
                    <a href="index.php" class="btn-cancelar">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-paper-plane"></i> Enviar Reclamo
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script>
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

                // Validar tipo
                const tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!tiposPermitidos.includes(file.type)) {
                    alert('❌ Solo se permiten imágenes JPG, JPEG o PNG');
                    this.value = '';
                    return;
                }

                // Validar tamaño (5 MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('❌ La imagen no debe pesar más de 5 MB');
                    this.value = '';
                    return;
                }

                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    nombreImagen.textContent = file.name;
                    pesoImagen.textContent = (file.size / 1024).toFixed(1) + ' KB';
                    previewImagen.style.display = 'flex';
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
    </script>
</body>
</html>