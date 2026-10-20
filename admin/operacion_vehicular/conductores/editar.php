<?php
// admin/operacion_vehicular/conductores/editar.php
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
// ✅ CATEGORÍAS DE LICENCIA
// ============================================
$categorias_licencia = ['A1', 'A2', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'];

// ============================================
// ✅ VALIDAR ID
// ============================================
$conductor_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($conductor_id <= 0) {
    $_SESSION['error'] = "❌ Conductor no especificado";
    header('Location: ../index.php');
    exit();
}

// ============================================
// ✅ OBTENER DATOS DEL CONDUCTOR
// ============================================
$stmt = $pdo->prepare("
    SELECT c.id, c.usuario_id, c.activo,
           u.nombre_completo, u.usuario, u.cargo, u.sede, u.perfil_usuario
    FROM ope_conductores c
    INNER JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$conductor_id]);
$conductor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conductor) {
    $_SESSION['error'] = "❌ Conductor no encontrado";
    header('Location: ../index.php');
    exit();
}

// ============================================
// ✅ PROCESAR ACCIONES
// ============================================
$error = '';
$mensaje = '';

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

// --- AGREGAR LICENCIA NUEVA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $categoria = trim($_POST['categoria'] ?? '');
    $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
    
    if (!in_array($categoria, $categorias_licencia)) {
        $error = "❌ Categoría no válida";
    } elseif (empty($fecha_vencimiento)) {
        $error = "❌ La fecha de vencimiento es obligatoria";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_vencimiento)) {
        $error = "❌ Formato de fecha no válido";
    } else {
        // Verificar que no exista la categoría activa
        $stmt = $pdo->prepare("
            SELECT id FROM ope_licencias 
            WHERE conductor_id = ? AND categoria = ? AND activo = 1
        ");
        $stmt->execute([$conductor_id, $categoria]);
        
        if ($stmt->fetch()) {
            $error = "❌ Este conductor ya tiene una licencia $categoria activa. Usa 'Renovar' si quieres actualizarla.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO ope_licencias (conductor_id, categoria, fecha_vencimiento, activo, añadido_por, creado_el)
                    VALUES (?, ?, ?, 1, ?, NOW())
                ");
                $stmt->execute([$conductor_id, $categoria, $fecha_vencimiento, $_SESSION['usuario_id']]);
                
                $_SESSION['mensaje'] = "✅ Licencia $categoria agregada correctamente";
                header("Location: editar.php?id=" . $conductor_id);
                exit();
                
            } catch (PDOException $e) {
                error_log("Error al agregar licencia: " . $e->getMessage());
                $error = "❌ Error al agregar la licencia";
            }
        }
    }
}

// --- RENOVAR LICENCIA (Opción B: marca histórica + crea nueva) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'renovar') {
    $licencia_id = (int) ($_POST['licencia_id'] ?? 0);
    $nueva_fecha = trim($_POST['nueva_fecha'] ?? '');
    
    if ($licencia_id <= 0) {
        $error = "❌ Licencia no especificada";
    } elseif (empty($nueva_fecha)) {
        $error = "❌ La nueva fecha de vencimiento es obligatoria";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nueva_fecha)) {
        $error = "❌ Formato de fecha no válido";
    } else {
        // Obtener la licencia a renovar (incluye fecha actual para validar)
        $stmt = $pdo->prepare("
            SELECT id, categoria, fecha_vencimiento 
            FROM ope_licencias 
            WHERE id = ? AND conductor_id = ? AND activo = 1
        ");
        $stmt->execute([$licencia_id, $conductor_id]);
        $licencia_vieja = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$licencia_vieja) {
            $error = "❌ Licencia no encontrada o ya renovada";
        } 
        // ✅ VALIDAR QUE LA NUEVA FECHA SEA MAYOR A LA ACTUAL
        elseif ($nueva_fecha <= $licencia_vieja['fecha_vencimiento']) {
            $fecha_formateada = date('d/m/Y', strtotime($licencia_vieja['fecha_vencimiento']));
            $error = "❌ La nueva fecha debe ser posterior al $fecha_formateada";
        } 
        else {
            try {
                $pdo->beginTransaction();
                
                // 1. Marcar la vieja como histórica
                $stmt = $pdo->prepare("UPDATE ope_licencias SET activo = 0 WHERE id = ?");
                $stmt->execute([$licencia_id]);
                
                // 2. Crear la nueva licencia
                $stmt = $pdo->prepare("
                    INSERT INTO ope_licencias (conductor_id, categoria, fecha_vencimiento, activo, añadido_por, creado_el)
                    VALUES (?, ?, ?, 1, ?, NOW())
                ");
                $stmt->execute([
                    $conductor_id,
                    $licencia_vieja['categoria'],
                    $nueva_fecha,
                    $_SESSION['usuario_id']
                ]);
                
                $pdo->commit();
                
                $_SESSION['mensaje'] = "✅ Licencia {$licencia_vieja['categoria']} renovada correctamente";
                header("Location: editar.php?id=" . $conductor_id);
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al renovar licencia: " . $e->getMessage());
                $error = "❌ Error al renovar la licencia";
            }
        }
    }
}

// --- ELIMINAR LICENCIA ---
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $licencia_id = (int) $_GET['eliminar'];
    
    // Verificar que la licencia pertenece al conductor
    $stmt = $pdo->prepare("SELECT id, categoria FROM ope_licencias WHERE id = ? AND conductor_id = ?");
    $stmt->execute([$licencia_id, $conductor_id]);
    $licencia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($licencia) {
        $stmt = $pdo->prepare("DELETE FROM ope_licencias WHERE id = ?");
        $stmt->execute([$licencia_id]);
        
        $_SESSION['mensaje'] = "✅ Licencia {$licencia['categoria']} eliminada";
        header("Location: editar.php?id=" . $conductor_id);
        exit();
    }
}

// ============================================
// ✅ OBTENER LICENCIAS ACTIVAS
// ============================================
$stmt = $pdo->prepare("
    SELECT id, categoria, fecha_vencimiento, activo, creado_el
    FROM ope_licencias
    WHERE conductor_id = ? AND activo = 1
    ORDER BY 
        CASE categoria
            WHEN 'A1' THEN 1
            WHEN 'A2' THEN 2
            WHEN 'B1' THEN 3
            WHEN 'B2' THEN 4
            WHEN 'B3' THEN 5
            WHEN 'C1' THEN 6
            WHEN 'C2' THEN 7
            WHEN 'C3' THEN 8
            ELSE 99
        END ASC
");
$stmt->execute([$conductor_id]);
$licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ FUNCIÓN: Calcular estado de vencimiento
// ============================================
function calcularEstadoVencimiento($fecha_vencimiento) {
    if (empty($fecha_vencimiento)) {
        return ['clase' => 'sin-datos', 'texto' => '⚪ Sin datos'];
    }
    
    $hoy = new DateTime();
    $vence = new DateTime($fecha_vencimiento);
    $dias = (int) $hoy->diff($vence)->format('%r%a');
    
    if ($dias < 0) {
        return ['clase' => 'vencido', 'texto' => "🔴 Vencida hace " . abs($dias) . " días"];
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
    <title>Editar Licencias | Operación Vehicular</title>
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
        
        .conductor-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .conductor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #445960);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .conductor-info { flex: 1; min-width: 0; }
        
        .conductor-nombre {
            font-size: 22px;
            color: #12232b;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .conductor-meta {
            font-size: 13px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .conductor-meta span {
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
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #173742;
            margin-bottom: 25px;
        }
        
        .form-campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 160px;
        }
        
        .form-campo label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-campo select,
        .form-campo input[type="date"] {
            padding: 10px 14px;
            border: 2px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-campo select:focus,
        .form-campo input[type="date"]:focus {
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
        
        .licencias-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .licencia-item {
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
        
        .licencia-item:hover { background: #f0f2f4; }
        
        .licencia-item.vigente { border-left-color: #27ae60; }
        .licencia-item.por-vencer { border-left-color: #f39c12; }
        .licencia-item.vencido { border-left-color: #e74c3c; }
        
        .licencia-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
            min-width: 0;
        }
        
        .licencia-categoria {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            padding: 8px 14px;
            background: #173742;
            color: white;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.5px;
        }
        
        .licencia-fecha {
            font-size: 14px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .licencia-fecha i {
            color: #7f8c8d;
            font-size: 12px;
        }
        
        .licencia-estado {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .licencia-estado.vigente { background: #d5f5e3; color: #1a7a3a; }
        .licencia-estado.por-vencer { background: #fdebd0; color: #a04000; }
        .licencia-estado.vencido { background: #fadbd8; color: #922b21; }
        
        .licencia-acciones {
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
        
        .empty-licencias {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #7f8c8d;
        }
        
        .empty-licencias i {
            font-size: 42px;
            color: #d3d3d3;
            margin-bottom: 15px;
        }
        
        .empty-licencias h3 {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .empty-licencias p { font-size: 13px; }
        
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
            max-width: 480px;
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
            margin-bottom: 25px;
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
        
        .modal-hint i {
            color: #f39c12;
        }
        
        .modal-acciones {
            display: flex;
            gap: 12px;
            justify-content: center;
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
            <div class="logo-titulo">| Editar Licencias</div>
        </div>
        <a href="ver.php?id=<?php echo $conductor_id; ?>" class="btn-volver-header">
            <i class="fas fa-arrow-left"></i> Volver al conductor
        </a>
    </div>
</header>

<div class="container">
    
    <div class="card">
        
        <!-- ===== INFO CONDUCTOR ===== -->
        <div class="conductor-header">
            <div class="conductor-avatar">
                <?php 
                $partes = explode(' ', trim($conductor['nombre_completo']));
                if (count($partes) >= 2) {
                    echo strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
                } else {
                    echo strtoupper(substr($conductor['nombre_completo'], 0, 2));
                }
                ?>
            </div>
            <div class="conductor-info">
                <div class="conductor-nombre">
                    <?php echo htmlspecialchars($conductor['nombre_completo']); ?>
                </div>
                <div class="conductor-meta">
                    <span><i class="fas fa-at"></i> <?php echo htmlspecialchars($conductor['usuario']); ?></span>
                    <?php if (!empty($conductor['cargo'])): ?>
                        <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($conductor['cargo']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($conductor['sede'])): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($conductor['sede']); ?></span>
                    <?php endif; ?>
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
        
        <!-- ===== AGREGAR LICENCIA NUEVA ===== -->
        <div class="form-titulo">
            <i class="fas fa-plus-circle"></i>
            Agregar nueva licencia
        </div>
        
        <form method="POST" action="" class="form-agregar">
            <input type="hidden" name="accion" value="agregar">
            
            <div class="form-campo">
                <label for="categoria">Categoría</label>
                <select name="categoria" id="categoria" required>
                    <option value="">Selecciona...</option>
                    <?php 
                    $categorias_usadas = array_column($licencias, 'categoria');
                    foreach ($categorias_licencia as $cat): 
                        $usada = in_array($cat, $categorias_usadas);
                    ?>
                        <option value="<?php echo $cat; ?>" <?php echo $usada ? 'disabled' : ''; ?>>
                            <?php echo $cat; ?><?php echo $usada ? ' (ya tiene activa)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-campo">
                <label for="fecha_vencimiento">Fecha de vencimiento</label>
                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required>
            </div>
            
            <button type="submit" class="btn-agregar">
                <i class="fas fa-plus"></i> Agregar
            </button>
        </form>
        
        <!-- ===== LISTA LICENCIAS ===== -->
        <div class="lista-titulo">
            <i class="fas fa-id-card"></i>
            Licencias activas
            <span class="badge"><?php echo count($licencias); ?></span>
        </div>
        
        <?php if (count($licencias) > 0): ?>
            <div class="licencias-lista">
                <?php foreach ($licencias as $lic): 
                    $estado = calcularEstadoVencimiento($lic['fecha_vencimiento']);
                ?>
                    <div class="licencia-item <?php echo $estado['clase']; ?>">
                        <div class="licencia-info">
                            <div class="licencia-categoria">
                                <?php echo htmlspecialchars($lic['categoria']); ?>
                            </div>
                            <div class="licencia-fecha">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y', strtotime($lic['fecha_vencimiento'])); ?>
                            </div>
                            <span class="licencia-estado <?php echo $estado['clase']; ?>">
                                <?php echo $estado['texto']; ?>
                            </span>
                        </div>
                        <div class="licencia-acciones">
                            <button 
                                type="button" 
                                class="btn-renovar"
                                title="Renovar licencia"
                                onclick="abrirModalRenovar(
                                    <?php echo $lic['id']; ?>,
                                    '<?php echo htmlspecialchars($lic['categoria']); ?>',
                                    '<?php echo $lic['fecha_vencimiento']; ?>'
                                )"
                            >
                                <i class="fas fa-sync-alt"></i> Renovar
                            </button>
                            <a 
                                href="?id=<?php echo $conductor_id; ?>&eliminar=<?php echo $lic['id']; ?>" 
                                class="btn-eliminar"
                                title="Eliminar licencia"
                            >
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-licencias">
                <i class="fas fa-id-card"></i>
                <h3>Sin licencias registradas</h3>
                <p>Agrega al menos una licencia de conducción para este conductor.</p>
            </div>
        <?php endif; ?>
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
        
        <h2 class="modal-title">Renovar licencia</h2>
        
        <p class="modal-message">
            Renovando la licencia <strong id="modalCategoria"></strong>. 
            La licencia anterior quedará guardada como histórica.
        </p>
        
        <form method="POST" action="" id="formRenovar">
            <input type="hidden" name="accion" value="renovar">
            <input type="hidden" name="licencia_id" id="modalLicenciaId">
            
            <div class="modal-campo">
                <label for="modalNuevaFecha">Nueva fecha de vencimiento</label>
                <input 
                    type="date" 
                    name="nueva_fecha" 
                    id="modalNuevaFecha" 
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
// MODAL DE RENOVAR CON VALIDACIÓN DE FECHA
// ============================================
function abrirModalRenovar(licenciaId, categoria, fechaActual) {
    const modal = document.getElementById('modalRenovar');
    const modalCategoria = document.getElementById('modalCategoria');
    const modalLicenciaId = document.getElementById('modalLicenciaId');
    const modalNuevaFecha = document.getElementById('modalNuevaFecha');
    const modalFechaActual = document.getElementById('modalFechaActual');
    
    modalCategoria.textContent = categoria;
    modalLicenciaId.value = licenciaId;
    
    // ✅ Formatear fecha actual para mostrar en el hint
    const fechaActualObj = new Date(fechaActual + 'T00:00:00');
    const fechaActualFormateada = fechaActualObj.toLocaleDateString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    modalFechaActual.textContent = fechaActualFormateada;
    
    // ✅ Calcular el DÍA SIGUIENTE (mínimo permitido)
    const fechaMinima = new Date(fechaActual + 'T00:00:00');
    fechaMinima.setDate(fechaMinima.getDate() + 1);
    
    const year = fechaMinima.getFullYear();
    const month = String(fechaMinima.getMonth() + 1).padStart(2, '0');
    const day = String(fechaMinima.getDate()).padStart(2, '0');
    const fechaMinimaStr = `${year}-${month}-${day}`;
    
    // ✅ Aplicar restricciones al input
    modalNuevaFecha.min = fechaMinimaStr;
    modalNuevaFecha.value = fechaMinimaStr; // Sugerir el día siguiente como valor inicial
    
    modal.classList.add('active');
    
    setTimeout(() => modalNuevaFecha.focus(), 100);
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