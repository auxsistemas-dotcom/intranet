<?php
// admin/operacion_vehicular/vehiculos/documentos.php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para acceder a Operación Vehicular";
    header('Location: ../../index.php');
    exit();
}

// ============================================
// ✅ VERIFICAR QUE EXISTA VEHÍCULO TEMPORAL
// ============================================
if (!isset($_SESSION['vehiculo_temp'])) {
    $_SESSION['error'] = "❌ No hay vehículo en proceso. Selecciona uno primero.";
    header('Location: agregar.php');
    exit();
}

$vehiculo = $_SESSION['vehiculo_temp'];

// ============================================
// ✅ CALCULAR SI NECESITA TECNOMECÁNICA (5+ años)
// ============================================
$fecha_matricula = new DateTime($vehiculo['fecha_matricula']);
$hoy = new DateTime();
$hoy->setTime(0, 0, 0);
$anios = (int) $fecha_matricula->diff($hoy)->y;
$requiere_tecno = ($anios >= 5);

// ============================================
// ✅ PROCESAR FORMULARIO
// ============================================
$error = '';
$mensaje = '';

// --- AGREGAR DOCUMENTO A SESIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_doc') {
    $tipo_doc = trim($_POST['tipo_doc'] ?? '');
    $fecha_expedicion = trim($_POST['fecha_expedicion'] ?? '');
    $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
    
    $tipos_validos = ['SOAT', 'TECNOMECANICA'];
    
    // Validaciones
    if (!in_array($tipo_doc, $tipos_validos)) {
        $error = "❌ Tipo de documento no válido";
    }
    // Si es TECNOMECANICA y el VH <5 años → rechazar
    elseif ($tipo_doc === 'TECNOMECANICA' && !$requiere_tecno) {
        $error = "❌ Este vehículo NO requiere Tecnomecánica (tiene menos de 5 años)";
    }
    elseif (empty($fecha_expedicion)) {
        $error = "❌ La fecha de expedición es obligatoria";
    }
    elseif (empty($fecha_vencimiento)) {
        $error = "❌ La fecha de vencimiento es obligatoria";
    }
    elseif (strtotime($fecha_expedicion) > time()) {
        $error = "❌ La fecha de expedición no puede ser futura";
    }
    elseif (strtotime($fecha_vencimiento) <= strtotime($fecha_expedicion)) {
        $error = "❌ La fecha de vencimiento debe ser posterior a la de expedición";
    }
    else {
        // Verificar que NO exista ya del mismo tipo en sesión
        $existe = false;
        foreach ($vehiculo['documentos_temp'] as $doc) {
            if ($doc['tipo_documento'] === $tipo_doc) {
                $existe = true;
                break;
            }
        }
        
        if ($existe) {
            $error = "❌ Ya agregaste un documento de tipo " . ($tipo_doc === 'SOAT' ? 'SOAT' : 'Tecnomecánica');
        } else {
            // ✅ Guardar en sesión temporal
            $_SESSION['vehiculo_temp']['documentos_temp'][] = [
                'tipo_documento' => $tipo_doc,
                'fecha_expedicion' => $fecha_expedicion,
                'fecha_vencimiento' => $fecha_vencimiento
            ];
            
            $_SESSION['mensaje'] = "✅ " . ($tipo_doc === 'SOAT' ? 'SOAT' : 'Tecnomecánica') . " agregado correctamente";
            header("Location: documentos.php");
            exit();
        }
    }
}

// --- ELIMINAR DOCUMENTO DE SESIÓN ---
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $index = (int) $_GET['eliminar'];
    
    if (isset($_SESSION['vehiculo_temp']['documentos_temp'][$index])) {
        $doc_eliminado = $_SESSION['vehiculo_temp']['documentos_temp'][$index]['tipo_documento'];
        unset($_SESSION['vehiculo_temp']['documentos_temp'][$index]);
        $_SESSION['vehiculo_temp']['documentos_temp'] = array_values($_SESSION['vehiculo_temp']['documentos_temp']);
        
        $_SESSION['mensaje'] = "✅ " . ($doc_eliminado === 'SOAT' ? 'SOAT' : 'Tecnomecánica') . " eliminado";
        header("Location: documentos.php");
        exit();
    }
}

// --- FINALIZAR Y GUARDAR EN BD ---
if (isset($_GET['finalizar'])) {
    $documentos = $_SESSION['vehiculo_temp']['documentos_temp'];
    
    // Verificar que tenga SOAT y Tecno si aplica
    $tiene_soat = false;
    $tiene_tecno = false;
    foreach ($documentos as $doc) {
        if ($doc['tipo_documento'] === 'SOAT') $tiene_soat = true;
        if ($doc['tipo_documento'] === 'TECNOMECANICA') $tiene_tecno = true;
    }
    
    // Validaciones
    if (!$tiene_soat) {
        $error = "❌ Debes agregar el SOAT antes de finalizar";
    }
    elseif ($requiere_tecno && !$tiene_tecno) {
        $error = "❌ Este VH requiere Tecnomecánica (5+ años). Debes agregarla antes de finalizar";
    }
    else {
        try {
            // ⚠️ INSERT separado (Opción B)
            // 1. Insertar el vehículo
            $stmt = $pdo->prepare("
                INSERT INTO ope_vehiculos (placa, marca, modelo, referencia, fecha_matricula, tipo, activo, añadido_por, creado_el)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            $stmt->execute([
                $vehiculo['placa'],
                $vehiculo['marca'],
                $vehiculo['modelo'],
                $vehiculo['referencia'],
                $vehiculo['fecha_matricula'],
                $vehiculo['tipo'],
                $vehiculo['añadido_por']
            ]);
            
            $vehiculo_id = $pdo->lastInsertId();
            
            // 2. Insertar los documentos (sin numero_documento)
            $stmt = $pdo->prepare("
                INSERT INTO ope_documentos_vh 
                (vehiculo_id, tipo_documento, fecha_expedicion, fecha_vencimiento, activo, añadido_por, creado_el)
                VALUES (?, ?, ?, ?, 1, ?, NOW())
            ");
            
            foreach ($documentos as $doc) {
                $stmt->execute([
                    $vehiculo_id,
                    $doc['tipo_documento'],
                    $doc['fecha_expedicion'],
                    $doc['fecha_vencimiento'],
                    $vehiculo['añadido_por']
                ]);
            }
            
            // ✅ Limpiar sesión temporal
            unset($_SESSION['vehiculo_temp']);
            
            $_SESSION['mensaje'] = "✅ Vehículo {$vehiculo['placa']} y " . count($documentos) . " documento(s) guardados correctamente";
            header("Location: ../index.php");
            exit();
            
        } catch (PDOException $e) {
            error_log("Error al guardar vehículo: " . $e->getMessage());
            $error = "❌ Error al guardar. Intenta de nuevo.";
        }
    }
}

// --- CANCELAR (volver a agregar CON datos precargados) ---
if (isset($_GET['cancelar'])) {
    // ✅ NO borrar vehiculo_temp para que agregar.php precargue los datos
    header("Location: agregar.php");
    exit();
}

// Recargar documentos desde sesión (por si se agregó/eliminó)
$documentos = $_SESSION['vehiculo_temp']['documentos_temp'];

// Recuperar mensaje
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

// Verificar qué documentos ya están agregados
$tiene_soat = false;
$tiene_tecno = false;
foreach ($documentos as $doc) {
    if ($doc['tipo_documento'] === 'SOAT') $tiene_soat = true;
    if ($doc['tipo_documento'] === 'TECNOMECANICA') $tiene_tecno = true;
}

// ============================================
// ✅ FUNCIÓN: Calcular estado de vencimiento
// ============================================
function calcularEstadoVencimiento($fecha_vencimiento) {
    if (empty($fecha_vencimiento)) {
        return ['clase' => 'sin-datos', 'texto' => '⚪ Sin datos'];
    }
    
    $hoy = new DateTime();
    $hoy->setTime(0, 0, 0);
    $vence = new DateTime($fecha_vencimiento);
    $dias = (int) $hoy->diff($vence)->format('%r%a');
    
    if ($dias < 0) {
        return ['clase' => 'vencido', 'texto' => "🔴 Vencido hace " . abs($dias) . " días"];
    } elseif ($dias <= 30) {
        return ['clase' => 'por-vencer', 'texto' => "🟡 Vence en $dias días"];
    } else {
        return ['clase' => 'vigente', 'texto' => "🟢 Vence el " . date('d/m/Y', strtotime($fecha_vencimiento))];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos del Vehículo | Operación Vehicular</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #d3d3d3;
            color: #2c3e50;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px 40px;
        }
        
        /* ========== HEADER ========== */
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo { display: flex; flex-direction: column; gap: 5px; }
        .logo img { display: block; width: 130px; }
        
        .logo-titulo {
            color: #8aa8b8;
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0.5px;
            padding-left: 2px;
        }
        
        .btn-volver-header {
            background: #445960;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-volver-header:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        /* ========== CARD ========== */
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        /* ========== VEHÍCULO HEADER ========== */
        .vehiculo-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .vehiculo-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #445960);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 26px;
            flex-shrink: 0;
        }
        
        .vehiculo-info { flex: 1; min-width: 0; }
        
        .vehiculo-placa {
            font-size: 24px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .vehiculo-meta {
            font-size: 13px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .vehiculo-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .vehiculo-tipo {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            background: #173742;
            color: white;
        }
        
        /* ========== BANNER TEMPORAL ========== */
        .banner-temporal {
            background: #fef3c7;
            border-left: 4px solid #f39c12;
            color: #92400e;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .banner-temporal i {
            font-size: 18px;
            color: #f39c12;
        }
        
        /* ========== MENSAJES ========== */
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .mensaje-exito {
            background: #d5f5e3;
            color: #1a7a3a;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: opacity 0.5s ease;
        }
        
        /* ========== FORM DOCUMENTO ========== */
        .form-titulo {
            font-size: 16px;
            color: #12232b;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-titulo i { color: #173742; }
        
        .form-documento {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #173742;
            margin-bottom: 25px;
        }
        
        .form-documento-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: flex-end;
        }
        
        @media (max-width: 900px) {
            .form-documento-grid { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 600px) {
            .form-documento-grid { grid-template-columns: 1fr; }
        }
        
        .form-campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .form-campo label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-campo input,
        .form-campo select {
            padding: 10px 14px;
            border: 2px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-campo input:focus,
        .form-campo select:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        
        .form-campo input:disabled,
        .form-campo select:disabled {
            background: #ecf0f1;
            color: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-agregar-doc {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-agregar-doc:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        
        /* ========== AVISO TECNO ========== */
        .aviso-tecno {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            color: #1a5276;
            padding: 12px 16px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 13px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .aviso-tecno i {
            color: #3498db;
            font-size: 16px;
            margin-top: 2px;
        }
        
        /* ========== LISTA DOCUMENTOS ========== */
        .lista-titulo {
            font-size: 16px;
            color: #12232b;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .lista-titulo i { color: #173742; }
        
        .lista-titulo .badge {
            background: #173742;
            color: white;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .documentos-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .documento-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #173742;
            transition: all 0.2s ease;
        }
        
        .documento-item:hover { background: #f0f2f4; }
        
        .documento-item.vigente { border-left-color: #27ae60; }
        .documento-item.por-vencer { border-left-color: #f39c12; }
        .documento-item.vencido { border-left-color: #e74c3c; }
        
        .documento-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
            min-width: 0;
            flex-wrap: wrap;
        }
        
        .documento-tipo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 140px;
            padding: 8px 14px;
            background: #173742;
            color: white;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        .documento-tipo.tecno {
            background: #8e44ad;
        }
        
        .documento-fecha {
            font-size: 13px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .documento-fecha i {
            color: #7f8c8d;
            font-size: 12px;
        }
        
        .documento-estado {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .documento-estado.vigente { background: #d5f5e3; color: #1a7a3a; }
        .documento-estado.por-vencer { background: #fdebd0; color: #a04000; }
        .documento-estado.vencido { background: #fadbd8; color: #922b21; }
        
        .btn-eliminar-doc {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .btn-eliminar-doc:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-documentos {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #7f8c8d;
        }
        
        .empty-documentos i {
            font-size: 42px;
            color: #d3d3d3;
            margin-bottom: 15px;
        }
        
        .empty-documentos h3 {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .empty-documentos p { font-size: 13px; }
        
        /* ========== BOTONES FINALES ========== */
        .footer-acciones {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .btn-cancelar {
            background: #7f8c8d;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-cancelar:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }
        
        .btn-finalizar {
            background: #27ae60;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-finalizar:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        
        .btn-finalizar.deshabilitado {
            background: #95a5a6;
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
        
        .info-requeridos {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .info-requeridos .check { color: #27ae60; }
        .info-requeridos .pending { color: #e74c3c; }
    </style>
</head>
<body>

<!-- ========== HEADER ========== -->
<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">| Documentos del Vehículo</div>
        </div>
        <a href="agregar.php" class="btn-volver-header">
            <i class="fas fa-arrow-left"></i> Volver a datos
        </a>
    </div>
</header>

<!-- ========== CONTENIDO ========== -->
<div class="container">
    
    <div class="card">
        
        <!-- ===== INFO VEHÍCULO ===== -->
        <div class="vehiculo-header">
            <div class="vehiculo-avatar">
                <i class="fas fa-car"></i>
            </div>
            <div class="vehiculo-info">
                <div class="vehiculo-placa">
                    <?php echo htmlspecialchars($vehiculo['placa']); ?>
                </div>
                <div class="vehiculo-meta">
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($vehiculo['marca']); ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($vehiculo['modelo']); ?></span>
                    <span><i class="fas fa-list"></i> <?php echo htmlspecialchars($vehiculo['referencia']); ?></span>
                    <span class="vehiculo-tipo"><?php echo htmlspecialchars($vehiculo['tipo']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- ===== BANNER TEMPORAL ===== -->
        <div class="banner-temporal">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Modo temporal:</strong> el vehículo no se guardará hasta que hagas clic en <strong>"Finalizar y guardar"</strong>. Si cancelas, no se guardará nada.
            </div>
        </div>
        
        <!-- ===== MENSAJES ===== -->
        <?php if ($mensaje): ?>
            <div class="mensaje-exito" id="msgExito">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- ===== FORMULARIO AGREGAR DOCUMENTO ===== -->
        <div class="form-titulo">
            <i class="fas fa-plus-circle"></i>
            Agregar documento
        </div>
        
        <div class="form-documento">
            <form method="POST" action="">
                <input type="hidden" name="accion" value="agregar_doc">
                
                <div class="form-documento-grid">
                    
                    <div class="form-campo">
                        <label for="tipo_doc">Tipo documento</label>
                        <select name="tipo_doc" id="tipo_doc" required>
                            <option value="">Selecciona...</option>
                            <?php if (!$tiene_soat): ?>
                                <option value="SOAT">SOAT</option>
                            <?php endif; ?>
                            <?php if (!$tiene_tecno && $requiere_tecno): ?>
                                <option value="TECNOMECANICA">Tecnomecánica</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-campo">
                        <label for="fecha_expedicion">Fecha expedición</label>
                        <input 
                            type="date" 
                            name="fecha_expedicion" 
                            id="fecha_expedicion" 
                            required
                            max="<?php echo date('Y-m-d'); ?>"
                        >
                    </div>
                    
                    <div class="form-campo">
                        <label for="fecha_vencimiento">Fecha vencimiento</label>
                        <input 
                            type="date" 
                            name="fecha_vencimiento" 
                            id="fecha_vencimiento" 
                            required
                        >
                    </div>
                    
                    <button type="submit" class="btn-agregar-doc">
                        <i class="fas fa-plus"></i> Agregar
                    </button>
                </div>
            </form>
            
            <!-- Aviso si el VH es <5 años -->
            <?php if (!$requiere_tecno): ?>
                <div class="aviso-tecno">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        Este vehículo tiene <strong><?php echo $anios; ?> año(s)</strong> (menos de 5). 
                        <strong>No requiere Tecnomecánica</strong>. Solo agrega el SOAT.
                    </div>
                </div>
            <?php else: ?>
                <div class="aviso-tecno" style="background: #fef3c7; border-color: #f39c12; color: #92400e;">
                    <i class="fas fa-exclamation-triangle" style="color: #f39c12;"></i>
                    <div>
                        Este vehículo tiene <strong><?php echo $anios; ?> años</strong> (5+). 
                        <strong>Requiere SOAT y Tecnomecánica</strong> obligatoriamente.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ===== LISTA DOCUMENTOS ===== -->
        <div class="lista-titulo">
            <i class="fas fa-file-alt"></i>
            Documentos agregados
            <span class="badge"><?php echo count($documentos); ?></span>
        </div>
        
        <?php if (count($documentos) > 0): ?>
            <div class="documentos-lista">
                <?php foreach ($documentos as $index => $doc): 
                    $estado = calcularEstadoVencimiento($doc['fecha_vencimiento']);
                    $es_tecno = ($doc['tipo_documento'] === 'TECNOMECANICA');
                ?>
                    <div class="documento-item <?php echo $estado['clase']; ?>">
                        <div class="documento-info">
                            <div class="documento-tipo <?php echo $es_tecno ? 'tecno' : ''; ?>">
                                <?php echo $es_tecno ? 'TECNOMECÁNICA' : 'SOAT'; ?>
                            </div>
                            <div class="documento-fecha">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y', strtotime($doc['fecha_vencimiento'])); ?>
                            </div>
                            <span class="documento-estado <?php echo $estado['clase']; ?>">
                                <?php echo $estado['texto']; ?>
                            </span>
                        </div>
                        <a 
                            href="?eliminar=<?php echo $index; ?>" 
                            class="btn-eliminar-doc"
                            title="Eliminar documento"
                        >
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-documentos">
                <i class="fas fa-file-alt"></i>
                <h3>Sin documentos agregados</h3>
                <p>Agrega al menos el SOAT para poder finalizar.</p>
            </div>
        <?php endif; ?>
        
        <!-- ===== INFO DE REQUERIDOS ===== -->
        <div class="info-requeridos">
            <?php if ($tiene_soat): ?>
                <span class="check"><i class="fas fa-check-circle"></i> SOAT agregado</span>
            <?php else: ?>
                <span class="pending"><i class="fas fa-times-circle"></i> SOAT pendiente</span>
            <?php endif; ?>
            
            <?php if ($requiere_tecno): ?>
                <?php if ($tiene_tecno): ?>
                    <span class="check" style="margin-left: 15px;"><i class="fas fa-check-circle"></i> Tecnomecánica agregada</span>
                <?php else: ?>
                    <span class="pending" style="margin-left: 15px;"><i class="fas fa-times-circle"></i> Tecnomecánica pendiente</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- ===== BOTONES FINALES ===== -->
        <div class="footer-acciones">
            <a href="?cancelar=1" class="btn-cancelar">
                <i class="fas fa-times"></i> Cancelar
            </a>
            
            <?php 
            $puede_finalizar = $tiene_soat && (!$requiere_tecno || $tiene_tecno);
            ?>
            
            <?php if ($puede_finalizar): ?>
                <a href="?finalizar=1" class="btn-finalizar">
                    <i class="fas fa-check-circle"></i> Finalizar y guardar
                </a>
            <?php else: ?>
                <span class="btn-finalizar deshabilitado" title="Agrega los documentos requeridos primero">
                    <i class="fas fa-check-circle"></i> Finalizar y guardar
                </span>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const msgExito = document.getElementById('msgExito');
    if (msgExito) {
        setTimeout(function() {
            msgExito.style.opacity = '0';
            setTimeout(function() {
                msgExito.style.display = 'none';
            }, 500);
        }, 2000);
    }
});
</script>

</body>
</html>