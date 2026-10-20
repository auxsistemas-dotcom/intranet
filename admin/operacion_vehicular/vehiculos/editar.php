<?php
// admin/operacion_vehicular/vehiculos/editar.php
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
// ✅ VALIDAR ID
// ============================================
$vehiculo_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($vehiculo_id <= 0) {
    $_SESSION['error'] = "❌ Vehículo no especificado";
    header('Location: ../index.php');
    exit();
}

// ============================================
// ✅ OBTENER DATOS DEL VEHÍCULO
// ============================================
$stmt = $pdo->prepare("
    SELECT v.id, v.placa, v.marca, v.modelo, v.referencia,
           v.fecha_matricula, v.tipo, v.activo
    FROM ope_vehiculos v
    WHERE v.id = ?
");
$stmt->execute([$vehiculo_id]);
$vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehiculo) {
    $_SESSION['error'] = "❌ Vehículo no encontrado";
    header('Location: ../index.php');
    exit();
}

// ============================================
// ✅ CALCULAR EDAD DEL VEHÍCULO
// ============================================
$fecha_matricula = new DateTime($vehiculo['fecha_matricula']);
$hoy = new DateTime();
$hoy->setTime(0, 0, 0);
$anios = (int) $fecha_matricula->diff($hoy)->y;
$requiere_tecno = ($anios >= 5);

// ============================================
// ✅ PROCESAR ACCIONES
// ============================================
$error = '';
$mensaje = '';

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

// --- AGREGAR DOCUMENTO NUEVO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $tipo_doc = trim($_POST['tipo_doc'] ?? '');
    $fecha_expedicion = trim($_POST['fecha_expedicion'] ?? '');
    $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
    
    $tipos_validos = ['SOAT', 'TECNOMECANICA'];
    
    if (!in_array($tipo_doc, $tipos_validos)) {
        $error = "❌ Tipo de documento no válido";
    } elseif ($tipo_doc === 'TECNOMECANICA' && !$requiere_tecno) {
        $error = "❌ Este vehículo NO requiere Tecnomecánica (tiene menos de 5 años)";
    } elseif (empty($fecha_expedicion) || empty($fecha_vencimiento)) {
        $error = "❌ Ambas fechas son obligatorias";
    } elseif (strtotime($fecha_expedicion) > time()) {
        $error = "❌ La fecha de expedición no puede ser futura";
    } elseif (strtotime($fecha_vencimiento) <= strtotime($fecha_expedicion)) {
        $error = "❌ La fecha de vencimiento debe ser posterior a la de expedición";
    } else {
        // Verificar que no exista el tipo activo
        $stmt = $pdo->prepare("
            SELECT id FROM ope_documentos_vh 
            WHERE vehiculo_id = ? AND tipo_documento = ? AND activo = 1
        ");
        $stmt->execute([$vehiculo_id, $tipo_doc]);
        
        if ($stmt->fetch()) {
            $error = "❌ Ya existe un documento $tipo_doc activo. Usa 'Renovar' si quieres actualizarlo.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO ope_documentos_vh 
                    (vehiculo_id, tipo_documento, fecha_expedicion, fecha_vencimiento, activo, añadido_por, creado_el)
                    VALUES (?, ?, ?, ?, 1, ?, NOW())
                ");
                $stmt->execute([
                    $vehiculo_id,
                    $tipo_doc,
                    $fecha_expedicion,
                    $fecha_vencimiento,
                    $_SESSION['usuario_id']
                ]);
                
                $_SESSION['mensaje'] = "✅ Documento " . ($tipo_doc === 'SOAT' ? 'SOAT' : 'Tecnomecánica') . " agregado correctamente";
                header("Location: editar.php?id=" . $vehiculo_id);
                exit();
                
            } catch (PDOException $e) {
                error_log("Error al agregar documento: " . $e->getMessage());
                $error = "❌ Error al agregar el documento";
            }
        }
    }
}

// --- RENOVAR DOCUMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'renovar') {
    $documento_id = (int) ($_POST['documento_id'] ?? 0);
    $nueva_expedicion = trim($_POST['nueva_expedicion'] ?? '');
    $nueva_vencimiento = trim($_POST['nueva_vencimiento'] ?? '');
    
    if ($documento_id <= 0) {
        $error = "❌ Documento no especificado";
    } elseif (empty($nueva_expedicion) || empty($nueva_vencimiento)) {
        $error = "❌ Ambas fechas son obligatorias";
    } elseif (strtotime($nueva_expedicion) > time()) {
        $error = "❌ La fecha de expedición no puede ser futura";
    } elseif (strtotime($nueva_vencimiento) <= strtotime($nueva_expedicion)) {
        $error = "❌ La fecha de vencimiento debe ser posterior a la de expedición";
    } else {
        // Obtener el documento a renovar
        $stmt = $pdo->prepare("
            SELECT id, tipo_documento, fecha_vencimiento 
            FROM ope_documentos_vh 
            WHERE id = ? AND vehiculo_id = ? AND activo = 1
        ");
        $stmt->execute([$documento_id, $vehiculo_id]);
        $documento_viejo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento_viejo) {
            $error = "❌ Documento no encontrado o ya renovado";
        } 
        // ✅ Validar que la nueva fecha de vencimiento sea mayor a la actual
        elseif ($nueva_vencimiento <= $documento_viejo['fecha_vencimiento']) {
            $fecha_actual = date('d/m/Y', strtotime($documento_viejo['fecha_vencimiento']));
            $error = "❌ La nueva fecha de vencimiento debe ser posterior al $fecha_actual";
        } 
        else {
            try {
                $pdo->beginTransaction();
                
                // 1. Marcar el viejo como histórico
                $stmt = $pdo->prepare("UPDATE ope_documentos_vh SET activo = 0 WHERE id = ?");
                $stmt->execute([$documento_id]);
                
                // 2. Crear el nuevo documento
                $stmt = $pdo->prepare("
                    INSERT INTO ope_documentos_vh 
                    (vehiculo_id, tipo_documento, fecha_expedicion, fecha_vencimiento, activo, añadido_por, creado_el)
                    VALUES (?, ?, ?, ?, 1, ?, NOW())
                ");
                $stmt->execute([
                    $vehiculo_id,
                    $documento_viejo['tipo_documento'],
                    $nueva_expedicion,
                    $nueva_vencimiento,
                    $_SESSION['usuario_id']
                ]);
                
                $pdo->commit();
                
                $nombre_doc = ($documento_viejo['tipo_documento'] === 'SOAT' ? 'SOAT' : 'Tecnomecánica');
                $_SESSION['mensaje'] = "✅ $nombre_doc renovado correctamente";
                header("Location: editar.php?id=" . $vehiculo_id);
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al renovar documento: " . $e->getMessage());
                $error = "❌ Error al renovar el documento";
            }
        }
    }
}

// --- ELIMINAR DOCUMENTO ---
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $doc_id = (int) $_GET['eliminar'];
    
    $stmt = $pdo->prepare("SELECT id, tipo_documento FROM ope_documentos_vh WHERE id = ? AND vehiculo_id = ?");
    $stmt->execute([$doc_id, $vehiculo_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc) {
        $stmt = $pdo->prepare("DELETE FROM ope_documentos_vh WHERE id = ?");
        $stmt->execute([$doc_id]);
        
        $nombre_doc = ($doc['tipo_documento'] === 'SOAT' ? 'SOAT' : 'Tecnomecánica');
        $_SESSION['mensaje'] = "✅ $nombre_doc eliminado";
        header("Location: editar.php?id=" . $vehiculo_id);
        exit();
    }
}

// ============================================
// ✅ OBTENER DOCUMENTOS ACTIVOS
// ============================================
$stmt = $pdo->prepare("
    SELECT id, tipo_documento, fecha_expedicion, fecha_vencimiento, activo, creado_el
    FROM ope_documentos_vh
    WHERE vehiculo_id = ? AND activo = 1
    ORDER BY tipo_documento ASC, fecha_vencimiento DESC
");
$stmt->execute([$vehiculo_id]);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar qué documentos ya están
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
    <title>Editar Documentos | Operación Vehicular</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 40px;
        }
        
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
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .vehiculo-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .vehiculo-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #445960);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .vehiculo-info { flex: 1; min-width: 0; }
        
        .vehiculo-placa {
            font-size: 22px;
            color: #12232b;
            font-weight: 600;
            margin-bottom: 5px;
            letter-spacing: 1px;
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
        
        .form-agregar {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #173742;
            margin-bottom: 25px;
        }
        
        .form-agregar-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: flex-end;
        }
        
        @media (max-width: 900px) {
            .form-agregar-grid { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 600px) {
            .form-agregar-grid { grid-template-columns: 1fr; }
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
        
        .btn-agregar {
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
        
        .btn-agregar:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        
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
        
        .documento-acciones {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .btn-renovar {
            padding: 8px 14px;
            border-radius: 8px;
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-renovar:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-eliminar {
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
        }
        
        .btn-eliminar:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
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
        
        .footer-acciones {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-finalizar {
            background: #173742;
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
            background: #445960;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }
        
        /* ============================================
           MODAL DE RENOVAR
           ============================================ */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(18, 35, 43, 0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
            animation: fadeIn 0.2s ease;
        }
        
        .modal-overlay.active { display: flex; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 35px 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }
        
        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #d6eaf8;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .modal-icon i { font-size: 38px; color: #3498db; }
        
        .modal-title {
            font-size: 22px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .modal-message {
            font-size: 14px;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .modal-message strong { color: #12232b; font-weight: 600; }
        
        .modal-campo {
            text-align: left;
            margin-bottom: 18px;
        }
        
        .modal-campo label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 8px;
        }
        
        .modal-campo input[type="date"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #d3d3d3;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .modal-campo input[type="date"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.15);
        }
        
        .modal-hint {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .modal-hint i { color: #f39c12; }
        
        .modal-acciones {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 25px;
        }
        
        .btn-modal {
            padding: 12px 28px;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            min-width: 130px;
            justify-content: center;
        }
        
        .btn-modal-cancelar {
            background: #ecf0f1;
            color: #2c3e50;
        }
        
        .btn-modal-cancelar:hover {
            background: #d3d3d3;
            transform: translateY(-2px);
        }
        
        .btn-modal-renovar {
            background: #3498db;
            color: white;
        }
        
        .btn-modal-renovar:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52,152,219,0.4);
        }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">| Editar Documentos</div>
        </div>
        <a href="ver.php?id=<?php echo $vehiculo_id; ?>" class="btn-volver-header">
            <i class="fas fa-arrow-left"></i> Volver al vehículo
        </a>
    </div>
</header>

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
                    <span><i class="fas fa-clock"></i> <?php echo $anios; ?> año(s)</span>
                </div>
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
        
        <!-- ===== AGREGAR DOCUMENTO (si falta alguno) ===== -->
        <?php if (!$tiene_soat || ($requiere_tecno && !$tiene_tecno)): ?>
            <div class="form-titulo">
                <i class="fas fa-plus-circle"></i>
                Agregar documento nuevo
            </div>
            
            <div class="form-agregar">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="form-agregar-grid">
                        
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
                        
                        <button type="submit" class="btn-agregar">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- ===== LISTA DOCUMENTOS ACTIVOS ===== -->
        <div class="lista-titulo">
            <i class="fas fa-file-alt"></i>
            Documentos activos
            <span class="badge"><?php echo count($documentos); ?></span>
        </div>
        
        <?php if (count($documentos) > 0): ?>
            <div class="documentos-lista">
                <?php foreach ($documentos as $doc): 
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
                                Vence: <?php echo date('d/m/Y', strtotime($doc['fecha_vencimiento'])); ?>
                            </div>
                            <span class="documento-estado <?php echo $estado['clase']; ?>">
                                <?php echo $estado['texto']; ?>
                            </span>
                        </div>
                        <div class="documento-acciones">
                            <button 
                                type="button" 
                                class="btn-renovar"
                                title="Renovar documento"
                                onclick="abrirModalRenovar(
                                    <?php echo $doc['id']; ?>,
                                    '<?php echo $es_tecno ? 'Tecnomecánica' : 'SOAT'; ?>',
                                    '<?php echo $doc['fecha_expedicion']; ?>',
                                    '<?php echo $doc['fecha_vencimiento']; ?>'
                                )"
                            >
                                <i class="fas fa-sync-alt"></i> Renovar
                            </button>
                            <a 
                                href="?id=<?php echo $vehiculo_id; ?>&eliminar=<?php echo $doc['id']; ?>" 
                                class="btn-eliminar"
                                title="Eliminar documento"
                            >
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-documentos">
                <i class="fas fa-file-alt"></i>
                <h3>Sin documentos registrados</h3>
                <p>Agrega al menos el SOAT para este vehículo.</p>
            </div>
        <?php endif; ?>
        
        <!-- ===== BOTÓN FINALIZAR ===== -->
        <div class="footer-acciones">
            <a href="ver.php?id=<?php echo $vehiculo_id; ?>" class="btn-finalizar">
                <i class="fas fa-check-circle"></i> Listo, volver al vehículo
            </a>
        </div>
        
    </div>
</div>

<!-- ============================================
     MODAL DE RENOVAR
     ============================================ -->
<div class="modal-overlay" id="modalRenovar">
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-sync-alt"></i>
        </div>
        
        <h2 class="modal-title">Renovar documento</h2>
        
        <p class="modal-message">
            Renovando: <strong id="modalTipoDoc"></strong>. 
            El documento anterior quedará guardado como histórico.
        </p>
        
        <form method="POST" action="" id="formRenovar">
            <input type="hidden" name="accion" value="renovar">
            <input type="hidden" name="documento_id" id="modalDocumentoId">
            
            <div class="modal-campo">
                <label for="modalNuevaExpedicion">Nueva fecha de expedición</label>
                <input 
                    type="date" 
                    name="nueva_expedicion" 
                    id="modalNuevaExpedicion" 
                    required
                    max="<?php echo date('Y-m-d'); ?>"
                >
            </div>
            
            <div class="modal-campo">
                <label for="modalNuevaVencimiento">Nueva fecha de vencimiento</label>
                <input 
                    type="date" 
                    name="nueva_vencimiento" 
                    id="modalNuevaVencimiento" 
                    required
                >
                <div class="modal-hint">
                    <i class="fas fa-info-circle"></i>
                    La fecha debe ser posterior a <strong id="modalFechaActual"></strong>
                </div>
            </div>
            
            <div class="modal-acciones">
                <button type="button" class="btn-modal btn-modal-cancelar" onclick="cerrarModalRenovar()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn-modal btn-modal-renovar">
                    <i class="fas fa-check"></i> Renovar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// MODAL DE RENOVAR
// ============================================
function abrirModalRenovar(documentoId, tipoDoc, fechaExpedicion, fechaVencimiento) {
    const modal = document.getElementById('modalRenovar');
    const modalTipoDoc = document.getElementById('modalTipoDoc');
    const modalDocumentoId = document.getElementById('modalDocumentoId');
    const modalNuevaExpedicion = document.getElementById('modalNuevaExpedicion');
    const modalNuevaVencimiento = document.getElementById('modalNuevaVencimiento');
    const modalFechaActual = document.getElementById('modalFechaActual');
    
    modalTipoDoc.textContent = tipoDoc;
    modalDocumentoId.value = documentoId;
    
    // ✅ Mostrar la fecha actual de vencimiento
    const fechaActualObj = new Date(fechaVencimiento + 'T00:00:00');
    const fechaActualFormateada = fechaActualObj.toLocaleDateString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    modalFechaActual.textContent = fechaActualFormateada;
    
    // ✅ Calcular día siguiente para el mínimo de vencimiento
    const fechaMinima = new Date(fechaVencimiento + 'T00:00:00');
    fechaMinima.setDate(fechaMinima.getDate() + 1);
    
    const year = fechaMinima.getFullYear();
    const month = String(fechaMinima.getMonth() + 1).padStart(2, '0');
    const day = String(fechaMinima.getDate()).padStart(2, '0');
    const fechaMinimaStr = `${year}-${month}-${day}`;
    
    // ✅ Aplicar restricciones
    modalNuevaVencimiento.min = fechaMinimaStr;
    modalNuevaVencimiento.value = fechaMinimaStr;
    
    // ✅ Precargar la fecha de expedición (la actual o hoy)
    modalNuevaExpedicion.value = fechaExpedicion || new Date().toISOString().split('T')[0];
    
    modal.classList.add('active');
    setTimeout(() => modalNuevaExpedicion.focus(), 100);
}

function cerrarModalRenovar() {
    document.getElementById('modalRenovar').classList.remove('active');
}

// Cerrar con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalRenovar();
    }
});

// Cerrar al hacer clic fuera
document.addEventListener('click', function(e) {
    const modal = document.getElementById('modalRenovar');
    if (e.target === modal) {
        cerrarModalRenovar();
    }
});

// Auto-ocultar mensaje de éxito
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