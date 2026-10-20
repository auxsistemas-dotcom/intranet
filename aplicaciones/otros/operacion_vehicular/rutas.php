<?php
// aplicaciones/otros/operacion_vehicular/ruta.php
// Crear nueva ruta con Encuesta de Seguridad Vial
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// ============================================
// ✅ VERIFICAR QUE SEA CONDUCTOR
// ============================================
$stmt = $pdo->prepare("
    SELECT c.id, c.activo,
           u.nombre_completo, u.usuario, u.cargo, u.sede
    FROM ope_conductores c
    INNER JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.usuario_id = ? AND c.activo = 1
");
$stmt->execute([$usuario_id]);
$conductor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conductor) {
    $_SESSION['error'] = "❌ No estás registrado como conductor.";
    header('Location: index.php');
    exit();
}

$conductor_id = $conductor['id'];

// ============================================
// ✅ VALIDAR LICENCIAS VIGENTES
// ============================================
$stmt = $pdo->prepare("
    SELECT categoria, fecha_vencimiento
    FROM ope_licencias
    WHERE conductor_id = ? AND activo = 1
");
$stmt->execute([$conductor_id]);
$licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($licencias)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Acceso Denegado</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Poppins', sans-serif; background: #d3d3d3; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
            .error-card { background: white; border-radius: 16px; padding: 50px 40px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
            .error-icon { width: 100px; height: 100px; border-radius: 50%; background: #fadbd8; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; }
            .error-icon i { font-size: 48px; color: #e74c3c; }
            .error-title { font-size: 26px; color: #12232b; font-weight: 700; margin-bottom: 15px; }
            .error-message { font-size: 15px; color: #7f8c8d; line-height: 1.7; margin-bottom: 30px; }
            .btn-volver { background: #173742; color: white; padding: 14px 30px; text-decoration: none; border-radius: 10px; font-size: 15px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
            .btn-volver:hover { background: #445960; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><i class="fas fa-id-card"></i></div>
            <h1 class="error-title">Sin Licencias Registradas</h1>
            <p class="error-message">No tienes licencias de conducción registradas.<br>Contacta al administrador para registrar tus licencias antes de crear rutas.</p>
            <a href="index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al portal</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$hoy = new DateTime();
$hoy->setTime(0, 0, 0);
$tiene_vigente = false;
$licencias_vencidas = [];

foreach ($licencias as $lic) {
    $vence = new DateTime($lic['fecha_vencimiento']);
    $vence->setTime(0, 0, 0);
    $dias = (int) $hoy->diff($vence)->format('%r%a');
    
    if ($dias >= 0) {
        $tiene_vigente = true;
    } else {
        $licencias_vencidas[] = $lic['categoria'];
    }
}

if (!$tiene_vigente) {
    http_response_code(403);
    $cats_vencidas = implode(', ', $licencias_vencidas);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Licencia Vencida</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Poppins', sans-serif; background: #d3d3d3; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
            .error-card { background: white; border-radius: 16px; padding: 50px 40px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
            .error-icon { width: 100px; height: 100px; border-radius: 50%; background: #fadbd8; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; }
            .error-icon i { font-size: 48px; color: #e74c3c; }
            .error-title { font-size: 26px; color: #12232b; font-weight: 700; margin-bottom: 15px; }
            .error-message { font-size: 15px; color: #7f8c8d; line-height: 1.7; margin-bottom: 30px; }
            .btn-volver { background: #173742; color: white; padding: 14px 30px; text-decoration: none; border-radius: 10px; font-size: 15px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
            .btn-volver:hover { background: #445960; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><i class="fas fa-id-card"></i></div>
            <h1 class="error-title">Licencia Vencida</h1>
            <p class="error-message">Tu(s) licencia(s) <strong><?php echo htmlspecialchars($cats_vencidas); ?></strong> está(n) vencida(s).<br><br>Contacta al administrador para renovar tus licencias antes de crear rutas.</p>
            <a href="index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al portal</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// ✅ VERIFICAR BLOQUEO 24H
// ============================================
$stmt = $pdo->prepare("
    SELECT bloqueado_hasta 
    FROM ope_ruta 
    WHERE conductor_id = ? AND bloqueado_hasta >= CURDATE()
    ORDER BY bloqueado_hasta DESC LIMIT 1
");
$stmt->execute([$conductor_id]);
$bloqueo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($bloqueo) {
    $fecha_bloqueo = date('d/m/Y', strtotime($bloqueo['bloqueado_hasta']));
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Bloqueado</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Poppins', sans-serif; background: #d3d3d3; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
            .error-card { background: white; border-radius: 16px; padding: 50px 40px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
            .error-icon { width: 100px; height: 100px; border-radius: 50%; background: #fef3c7; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; }
            .error-icon i { font-size: 48px; color: #f39c12; }
            .error-title { font-size: 26px; color: #12232b; font-weight: 700; margin-bottom: 15px; }
            .error-message { font-size: 15px; color: #7f8c8d; line-height: 1.7; margin-bottom: 30px; }
            .btn-volver { background: #173742; color: white; padding: 14px 30px; text-decoration: none; border-radius: 10px; font-size: 15px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
            .btn-volver:hover { background: #445960; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><i class="fas fa-clock"></i></div>
            <h1 class="error-title">Bloqueado</h1>
            <p class="error-message">Has sido bloqueado para crear rutas hasta el <strong><?php echo $fecha_bloqueo; ?></strong>.<br><br>Esto se debe a que no cumpliste con los requisitos de la encuesta de seguridad vial.</p>
            <a href="index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al portal</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// ✅ VERIFICAR QUE NO TENGA RUTA EN CURSO
// ============================================
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM ope_ruta 
    WHERE conductor_id = ? AND estado = 'EN_CURSO'
");
$stmt->execute([$conductor_id]);
$rutas_activas = $stmt->fetchColumn();

if ($rutas_activas > 0) {
    $_SESSION['error'] = "❌ Ya tienes una ruta en curso. Debes finalizarla antes de crear otra.";
    header('Location: index.php');
    exit();
}

// ============================================
// ✅ PROCESAR FORMULARIO
// ============================================
$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos
    $documento_conductor = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['documento_conductor'] ?? ''));
    $motivo_viaje = strtoupper(trim($_POST['motivo_viaje'] ?? ''));
    $fecha_regreso = trim($_POST['fecha_regreso'] ?? '');
    $autorizado_jefe = isset($_POST['autorizado_jefe']) ? (int) $_POST['autorizado_jefe'] : 0;
    $ciudad_origen = strtoupper(trim($_POST['ciudad_origen'] ?? ''));
    $ciudad_destino = strtoupper(trim($_POST['ciudad_destino'] ?? ''));
    $medio_transporte = trim($_POST['medio_transporte'] ?? '');
    $medio_transporte_otro = strtoupper(trim($_POST['medio_transporte_otro'] ?? ''));
    $placa_input = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['placa'] ?? ''));
    
    // Checklist
    $checklist = isset($_POST['checklist']) && is_array($_POST['checklist']) ? $_POST['checklist'] : [];
    
    // Encuesta
    $conoce_codigo = isset($_POST['conoce_codigo']) ? (int) $_POST['conoce_codigo'] : 0;
    $salud_estable = isset($_POST['salud_estable']) ? (int) $_POST['salud_estable'] : 0;
    $descanso_optimo = isset($_POST['descanso_optimo']) ? (int) $_POST['descanso_optimo'] : 0;
    $emocional_estable = isset($_POST['emocional_estable']) ? (int) $_POST['emocional_estable'] : 0;
    $alcohol_24h = isset($_POST['alcohol_24h']) ? (int) $_POST['alcohol_24h'] : 0;
    $sustancias_24h = isset($_POST['sustancias_24h']) ? (int) $_POST['sustancias_24h'] : 0;
    $medicamentos = isset($_POST['medicamentos']) ? (int) $_POST['medicamentos'] : 0;
    
    // ✅ VALIDACIONES BÁSICAS
    if (empty($documento_conductor)) {
        $error = "❌ El número de documento es obligatorio";
    }
    elseif (strlen($documento_conductor) < 6) {
        $error = "❌ El número de documento debe tener al menos 6 caracteres";
    }
    elseif (empty($motivo_viaje) || empty($fecha_regreso) || empty($ciudad_origen) || 
        empty($ciudad_destino) || empty($medio_transporte)) {
        $error = "❌ Todos los campos obligatorios deben completarse";
    }
    elseif (strtotime($fecha_regreso) < strtotime(date('Y-m-d'))) {
        $error = "❌ La fecha de regreso no puede ser anterior a hoy";
    }
    elseif ($medio_transporte === 'OTRO' && empty($medio_transporte_otro)) {
        $error = "❌ Debes especificar el medio de transporte";
    }
    elseif ($medio_transporte !== 'PUBLICO' && empty($placa_input)) {
        $error = "❌ Debes indicar la placa del vehículo";
    }
    elseif (count($checklist) < 6) {
        $error = "❌ Debes marcar TODOS los puntos de revisión del vehículo";
    }
    else {
        // ✅ VALIDAR PLACA PRIMERO (ANTES de las preguntas críticas)
        $vehiculo = null;
        $placa_valida = true;
        
        if ($medio_transporte !== 'PUBLICO') {
            $stmt = $pdo->prepare("
                SELECT id, placa, marca, modelo, activo
                FROM ope_vehiculos
                WHERE placa = ?
            ");
            $stmt->execute([$placa_input]);
            $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehiculo) {
                $error = "❌ La placa <strong>$placa_input</strong> no existe en el sistema. Verifica e intenta de nuevo.";
                $placa_valida = false;
            }
            elseif (!$vehiculo['activo']) {
                $error = "❌ El vehículo <strong>{$vehiculo['placa']}</strong> está inactivo. Debes elegir otro.";
                $placa_valida = false;
            }
            else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM ope_ruta 
                    WHERE vehiculo_id = ? AND estado = 'EN_CURSO'
                ");
                $stmt->execute([$vehiculo['id']]);
                $vh_rutas_activas = $stmt->fetchColumn();
                
                if ($vh_rutas_activas > 0) {
                    $error = "🚫 El vehículo <strong>{$vehiculo['placa']}</strong> se encuentra en movilización. Debes elegir otro.";
                    $placa_valida = false;
                }
                else {
                    $stmt = $pdo->prepare("
                        SELECT tipo_documento, fecha_vencimiento
                        FROM ope_documentos_vh
                        WHERE vehiculo_id = ? AND activo = 1
                    ");
                    $stmt->execute([$vehiculo['id']]);
                    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $docs_vencidos = [];
                    foreach ($documentos as $doc) {
                        $vence = new DateTime($doc['fecha_vencimiento']);
                        $vence->setTime(0, 0, 0);
                        if ($vence < $hoy) {
                            $docs_vencidos[] = $doc['tipo_documento'];
                        }
                    }
                    
                    if (!empty($docs_vencidos)) {
                        $error = "❌ El vehículo <strong>{$vehiculo['placa']}</strong> tiene documentos vencidos: "
                               . implode(', ', $docs_vencidos) 
                               . ". Debes elegir otro vehículo.";
                        $placa_valida = false;
                    }
                }
            }
        }
        
        if ($placa_valida) {
            $bloqueos = [];
            
            if ($autorizado_jefe !== 1) {
                $bloqueos[] = "❌ El viaje debe estar autorizado por su jefe inmediato";
            }
            if ($conoce_codigo !== 1) {
                $bloqueos[] = "❌ Debe conocer el Código Nacional de Tránsito";
            }
            if ($salud_estable !== 1) {
                $bloqueos[] = "❌ Debe estar en condiciones de salud estables";
            }
            if ($descanso_optimo !== 1) {
                $bloqueos[] = "❌ Debe tener óptimas condiciones de descanso";
            }
            if ($emocional_estable !== 1) {
                $bloqueos[] = "❌ Debe estar emocionalmente estable";
            }
            if ($alcohol_24h !== 0) {
                $bloqueos[] = "❌ No puede haber consumido bebidas alcohólicas en las últimas 24 horas";
            }
            if ($sustancias_24h !== 0) {
                $bloqueos[] = "❌ No puede haber consumido sustancias psicoactivas en las últimas 24 horas";
            }
            if ($medicamentos !== 0) {
                $bloqueos[] = "❌ No puede haber tomado medicamentos que afecten sus condiciones";
            }
            
            if (!empty($bloqueos)) {
                $bloqueado_hasta = date('Y-m-d', strtotime('+1 day'));
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO ope_ruta 
                        (vehiculo_id, conductor_id, documento_conductor, motivo_viaje, fecha_salida, fecha_regreso,
                         autorizado_jefe, ciudad_origen, ciudad_destino, medio_transporte, 
                         medio_transporte_otro, checklist_vh, conoce_codigo, salud_estable, 
                         descanso_optimo, emocional_estable, alcohol_24h, sustancias_24h, 
                         medicamentos, bloqueado_hasta, estado, añadido_por, creado_el)
                        VALUES (NULL, ?, ?, ?, NOW(), NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CANCELADO', ?, NOW())
                    ");
                    $stmt->execute([
                        $conductor_id,
                        $documento_conductor,
                        $motivo_viaje,
                        $autorizado_jefe,
                        $ciudad_origen,
                        $ciudad_destino,
                        $medio_transporte,
                        $medio_transporte_otro,
                        json_encode($checklist),
                        $conoce_codigo,
                        $salud_estable,
                        $descanso_optimo,
                        $emocional_estable,
                        $alcohol_24h,
                        $sustancias_24h,
                        $medicamentos,
                        $bloqueado_hasta,
                        $usuario_id
                    ]);
                } catch (PDOException $e) {
                    error_log("Error al registrar bloqueo: " . $e->getMessage());
                }
                
                $error = "🔴 <strong>No puedes crear la ruta hoy.</strong><br>" 
                       . implode('<br>', $bloqueos) 
                       . "<br><br>Podrás volver a intentarlo el <strong>"
                       . date('d/m/Y', strtotime('+1 day')) . "</strong>.";
            } else {
                try {
                    if ($medio_transporte !== 'PUBLICO') {
                        $stmt = $pdo->prepare("
                            INSERT INTO ope_ruta 
                            (vehiculo_id, conductor_id, documento_conductor, motivo_viaje, fecha_salida, fecha_regreso,
                             autorizado_jefe, ciudad_origen, ciudad_destino, medio_transporte,
                             medio_transporte_otro, checklist_vh, conoce_codigo, salud_estable,
                             descanso_optimo, emocional_estable, alcohol_24h, sustancias_24h,
                             medicamentos, bloqueado_hasta, estado, añadido_por, creado_el)
                            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'EN_CURSO', ?, NOW())
                        ");
                        $stmt->execute([
                            $vehiculo['id'],
                            $conductor_id,
                            $documento_conductor,
                            $motivo_viaje,
                            $fecha_regreso . ' 23:59:59',
                            $autorizado_jefe,
                            $ciudad_origen,
                            $ciudad_destino,
                            $medio_transporte,
                            $medio_transporte_otro,
                            json_encode($checklist),
                            $conoce_codigo,
                            $salud_estable,
                            $descanso_optimo,
                            $emocional_estable,
                            $alcohol_24h,
                            $sustancias_24h,
                            $medicamentos,
                            $usuario_id
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO ope_ruta 
                            (vehiculo_id, conductor_id, documento_conductor, motivo_viaje, fecha_salida, fecha_regreso,
                             autorizado_jefe, ciudad_origen, ciudad_destino, medio_transporte,
                             medio_transporte_otro, checklist_vh, conoce_codigo, salud_estable,
                             descanso_optimo, emocional_estable, alcohol_24h, sustancias_24h,
                             medicamentos, bloqueado_hasta, estado, añadido_por, creado_el)
                            VALUES (NULL, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'EN_CURSO', ?, NOW())
                        ");
                        $stmt->execute([
                            $conductor_id,
                            $documento_conductor,
                            $motivo_viaje,
                            $fecha_regreso . ' 23:59:59',
                            $autorizado_jefe,
                            $ciudad_origen,
                            $ciudad_destino,
                            $medio_transporte,
                            $medio_transporte_otro,
                            json_encode($checklist),
                            $conoce_codigo,
                            $salud_estable,
                            $descanso_optimo,
                            $emocional_estable,
                            $alcohol_24h,
                            $sustancias_24h,
                            $medicamentos,
                            $usuario_id
                        ]);
                    }
                    
                    $_SESSION['mensaje'] = "✅ Ruta creada correctamente. ¡Buen viaje!";
                    header('Location: index.php');
                    exit();
                    
                } catch (PDOException $e) {
                    error_log("Error al crear ruta: " . $e->getMessage());
                    $error = "❌ Error al guardar la ruta. Intenta de nuevo.";
                }
            }
        }
    }
}

$partes = explode(' ', trim($conductor['nombre_completo']));
if (count($partes) >= 2) {
    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
} else {
    $iniciales = strtoupper(substr($conductor['nombre_completo'], 0, 2));
}

$hoy_str = date('d/m/Y');
$hoy_input = date('Y-m-d');
$manana_input = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Ruta | Operación Vehicular</title>
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
        
        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        .aviso-resolucion {
            background: linear-gradient(135deg, #1a5276, #2980b9);
            color: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(26,82,118,0.3);
        }
        
        .aviso-titulo {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .aviso-texto {
            font-size: 14px;
            line-height: 1.7;
            color: #d6eaf8;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 18px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i { color: #173742; }
        
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        
        .form-campo {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-campo.full-width {
            grid-column: 1 / -1;
        }
        
        .form-campo label {
            font-size: 13px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-campo label .obligatorio {
            color: #e74c3c;
        }
        
        .form-campo input[type="text"],
        .form-campo input[type="date"],
        .form-campo select,
        .form-campo textarea {
            padding: 12px 16px;
            border: 2px solid #d3d3d3;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-campo input:focus,
        .form-campo select:focus,
        .form-campo textarea:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        
        .form-campo input:read-only {
            background: #ecf0f1;
            color: #7f8c8d;
            cursor: not-allowed;
        }
        
        .form-campo small {
            font-size: 11px;
            color: #95a5a6;
            margin-top: -4px;
        }
        
        /* ========== VALIDACIÓN PLACA ========== */
        #placa.placa-ok {
            border-color: #27ae60 !important;
            background: #d5f5e3 !important;
        }
        
        #placa.placa-error {
            border-color: #e74c3c !important;
            background: #fadbd8 !important;
        }
        
        #placa.placa-cargando {
            border-color: #f39c12 !important;
            background: #fef3c7 !important;
        }
        
        .mensaje-placa {
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
            animation: slideDown 0.2s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .mensaje-placa.ok {
            background: #d5f5e3;
            color: #1a7a3a;
            border-left: 3px solid #27ae60;
        }
        
        .mensaje-placa.error {
            background: #fadbd8;
            color: #922b21;
            border-left: 3px solid #e74c3c;
        }
        
        .mensaje-placa.cargando {
            background: #fef3c7;
            color: #92400e;
            border-left: 3px solid #f39c12;
        }
        
        .btn-crear:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-crear:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .radio-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: 2px solid #d3d3d3;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }
        
        .radio-option:hover {
            border-color: #173742;
        }
        
        .radio-option input[type="radio"] {
            cursor: pointer;
        }
        
        .radio-option input[type="radio"]:checked + span {
            font-weight: 600;
            color: #173742;
        }
        
        .radio-option.si:has(input:checked) {
            background: #d5f5e3;
            border-color: #27ae60;
        }
        
        .radio-option.no:has(input:checked) {
            background: #fadbd8;
            border-color: #e74c3c;
        }
        
        .checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
        }
        
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: #f8f9fa;
            border: 2px solid #d3d3d3;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .checklist-item:hover {
            background: #f0f2f4;
            border-color: #173742;
        }
        
        .checklist-item input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
            accent-color: #27ae60;
        }
        
        .checklist-item input[type="checkbox"]:checked ~ span {
            font-weight: 600;
            color: #27ae60;
        }
        
        .checklist-item:has(input:checked) {
            background: #d5f5e3;
            border-color: #27ae60;
        }
        
        .seccion-encuesta {
            background: #f8f9fa;
            border-left: 4px solid #173742;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .seccion-encuesta .form-campo {
            margin-bottom: 18px;
        }
        
        .seccion-encuesta .form-campo:last-child {
            margin-bottom: 0;
        }
        
        .seccion-encuesta .pregunta {
            font-weight: 600;
            color: #12232b;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-acciones {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }
        
        .btn-cancelar {
            background: #7f8c8d;
            color: white;
        }
        
        .btn-cancelar:hover {
            background: #95a5a6;
            transform: translateY(-2px);
        }
        
        .btn-crear {
            background: #27ae60;
            color: white;
        }
        
        .btn-crear:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39,174,96,0.3);
        }
        
        .info-final {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            color: #1a5276;
            padding: 16px 20px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.7;
            margin-top: 20px;
        }
        
        .info-final strong { color: #12232b; }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">| Nueva Ruta</div>
        </div>
        <a href="index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al portal
        </a>
    </div>
</header>

<div class="container">
    
    <div class="aviso-resolucion">
        <div class="aviso-titulo">
            <i class="fas fa-shield-alt"></i>
            ENCUESTA DE SEGURIDAD VIAL
        </div>
        <div class="aviso-texto">
            En cumplimiento de la resolución 40595 del 2022 y con el fin de actualizar el Plan Estratégico de Seguridad Vial de la Entidad, le invitamos a que diligencie la siguiente encuesta.
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="card">
            <div class="mensaje-error">
                <?php echo $error; ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.php" class="btn btn-cancelar">
                    <i class="fas fa-arrow-left"></i> Volver al portal
                </a>
            </div>
        </div>
    <?php else: ?>
    
    <form method="POST" action="">
        
        <div class="card">
            <div class="card-title">
                <i class="fas fa-user-circle"></i>
                Datos del conductor
            </div>
            
            <div class="form-grid">
                <div class="form-campo">
                    <label>Nombre completo</label>
                    <input type="text" value="<?php echo htmlspecialchars($conductor['nombre_completo']); ?>" readonly>
                </div>
                
                <div class="form-campo">
                    <label for="documento_conductor">Número de documento <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="documento_conductor" 
                        id="documento_conductor" 
                        placeholder="Ej: 12345678"
                        required
                        maxlength="50"
                        style="text-transform: uppercase;"
                        value="<?php echo htmlspecialchars($_POST['documento_conductor'] ?? ''); ?>"
                    >
                    <small>Ingresa tu número de documento</small>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-title">
                <i class="fas fa-route"></i>
                Datos del viaje
            </div>
            
            <div class="form-grid">
                <div class="form-campo full-width">
                    <label for="motivo_viaje">Motivo del viaje <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="motivo_viaje" 
                        id="motivo_viaje" 
                        required
                        maxlength="500"
                        style="text-transform: uppercase;"
                        value="<?php echo htmlspecialchars($_POST['motivo_viaje'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-campo">
                    <label>Fecha de inicio del viaje</label>
                    <input type="text" value="<?php echo $hoy_str; ?>" readonly>
                    <small>La fecha de inicio es hoy (no editable)</small>
                </div>
                
                <div class="form-campo">
                    <label for="fecha_regreso">Fecha de regreso del viaje <span class="obligatorio">*</span></label>
                    <input 
                        type="date" 
                        name="fecha_regreso" 
                        id="fecha_regreso" 
                        required
                        min="<?php echo $hoy_input; ?>"
                        value="<?php echo htmlspecialchars($_POST['fecha_regreso'] ?? $manana_input); ?>"
                    >
                    <small>Puede ser hoy o días posteriores</small>
                </div>
                
                <div class="form-campo full-width">
                    <label>¿Su viaje está programado y autorizado por su jefe inmediato? <span class="obligatorio">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option si">
                            <input type="radio" name="autorizado_jefe" value="1" required <?php echo (isset($_POST['autorizado_jefe']) && $_POST['autorizado_jefe'] == '1') ? 'checked' : ''; ?>>
                            <span>✅ Sí</span>
                        </label>
                        <label class="radio-option no">
                            <input type="radio" name="autorizado_jefe" value="0" <?php echo (isset($_POST['autorizado_jefe']) && $_POST['autorizado_jefe'] == '0') ? 'checked' : ''; ?>>
                            <span>❌ No</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-campo">
                    <label for="ciudad_origen">Ciudad de origen <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="ciudad_origen" 
                        id="ciudad_origen" 
                        required
                        maxlength="100"
                        style="text-transform: uppercase;"
                        value="<?php echo htmlspecialchars($_POST['ciudad_origen'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-campo">
                    <label for="ciudad_destino">Ciudad de destino <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="ciudad_destino" 
                        id="ciudad_destino" 
                        required
                        maxlength="100"
                        style="text-transform: uppercase;"
                        value="<?php echo htmlspecialchars($_POST['ciudad_destino'] ?? ''); ?>"
                    >
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-title">
                <i class="fas fa-car"></i>
                Medio de transporte
            </div>
            
            <div class="form-grid">
                <div class="form-campo full-width">
                    <label for="medio_transporte">¿Qué medio de transporte utiliza para el desplazamiento? <span class="obligatorio">*</span></label>
                    <select name="medio_transporte" id="medio_transporte" required onchange="actualizarMedioTransporte()">
                        <option value="">Selecciona...</option>
                        <option value="PUBLICO" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'PUBLICO') ? 'selected' : ''; ?>>🚌 Transporte público</option>
                        <option value="PROPIO" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'PROPIO') ? 'selected' : ''; ?>>🚗 Vehículo propio</option>
                        <option value="MOTO" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'MOTO') ? 'selected' : ''; ?>>🏍️ Motocicleta</option>
                        <option value="ORGANIZACION" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'ORGANIZACION') ? 'selected' : ''; ?>>🚙 Vehículo de la organización</option>
                        <option value="OTRO" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'OTRO') ? 'selected' : ''; ?>>📝 Otro</option>
                    </select>
                </div>
                
                <div class="form-campo full-width" id="campo_otro" style="display: none;">
                    <label for="medio_transporte_otro">Especifique el medio de transporte <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="medio_transporte_otro" 
                        id="medio_transporte_otro" 
                        placeholder="ESPECIFIQUE..."
                        maxlength="100"
                        style="text-transform: uppercase;"
                        value="<?php echo htmlspecialchars($_POST['medio_transporte_otro'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-campo full-width" id="campo_placa" style="display: none;">
                    <label for="placa">Placa del vehículo <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="placa" 
                        id="placa" 
                        placeholder="ABC123"
                        maxlength="10"
                        style="text-transform: uppercase;"
                        value="<?php echo htmlspecialchars($_POST['placa'] ?? ''); ?>"
                        oninput="debounceVerificarPlaca()"
                        autocomplete="off"
                    >
                    <div id="resultado_placa"></div>
                    <small id="hint_placa">La placa debe estar registrada y al día en el sistema</small>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-title">
                <i class="fas fa-clipboard-check"></i>
                ¿Revisión puntos al vehículo antes de viajar?
            </div>
            
            <p style="font-size: 13px; color: #7f8c8d; margin-bottom: 15px;">
                Debes marcar TODOS los puntos para poder continuar.
            </p>
            
            <div class="checklist-grid">
                <?php 
                $checklist_items = [
                    'llantas' => '🛞 Estado de llantas',
                    'interna' => '🔧 Revisión interna (aceite, frenos, refrigerante, batería, filtros)',
                    'externa' => '🚗 Revisión externa (lámina y pintura)',
                    'mantenimientos' => '📋 Mantenimientos al día',
                    'luces' => '💡 Luces y pito',
                    'kit' => '🧰 Kit de carretera'
                ];
                $checklist_seleccionados = $_POST['checklist'] ?? [];
                foreach ($checklist_items as $valor => $texto): 
                ?>
                    <label class="checklist-item">
                        <input type="checkbox" name="checklist[]" value="<?php echo $valor; ?>" <?php echo in_array($valor, $checklist_seleccionados) ? 'checked' : ''; ?>>
                        <span><?php echo $texto; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-title">
                <i class="fas fa-heartbeat"></i>
                Encuesta de seguridad vial
            </div>
            
            <div class="seccion-encuesta">
                <?php 
                $preguntas = [
                    'conoce_codigo' => '11. ¿Conoce el Código Nacional de Tránsito?',
                    'salud_estable' => '12. ¿Se encuentra en condiciones de salud estables para conducir un vehículo?',
                    'descanso_optimo' => '13. ¿Tuvo óptimas condiciones de descanso?',
                    'emocional_estable' => '14. ¿Se encuentra emocionalmente estable para conducir?',
                    'alcohol_24h' => '15. ¿Consumió bebidas alcohólicas en las últimas 24 horas?',
                    'sustancias_24h' => '16. ¿Consumió algún tipo de sustancias psicoactivas en las últimas 24 horas?',
                    'medicamentos' => '17. ¿Tomó algún tipo de medicamento que afecte sus condiciones de salud? (sueño, depresión, relación, etc.)'
                ];
                
                $preguntas_invertidas = ['alcohol_24h', 'sustancias_24h', 'medicamentos'];
                
                foreach ($preguntas as $name => $texto): 
                    $es_invertida = in_array($name, $preguntas_invertidas);
                    $valor_actual = $_POST[$name] ?? null;
                ?>
                    <div class="form-campo">
                        <div class="pregunta"><?php echo $texto; ?> <span class="obligatorio">*</span></div>
                        <div class="radio-group">
                            <label class="radio-option <?php echo $es_invertida ? 'no' : 'si'; ?>">
                                <input type="radio" name="<?php echo $name; ?>" value="1" required <?php echo ($valor_actual == '1') ? 'checked' : ''; ?>>
                                <span><?php echo $es_invertida ? 'Sí' : '✅ Sí'; ?></span>
                            </label>
                            <label class="radio-option <?php echo $es_invertida ? 'si' : 'no'; ?>">
                                <input type="radio" name="<?php echo $name; ?>" value="0" <?php echo ($valor_actual == '0') ? 'checked' : ''; ?>>
                                <span><?php echo $es_invertida ? 'No' : '❌ No'; ?></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="info-final">
            <i class="fas fa-info-circle"></i>
            <strong>POR FAVOR TENGA PRESENTE:</strong> El Plan Estratégico de Seguridad Vial (PESV) de ARMOTOR. En su intranet lo podrá visualizar en sus procedimientos <strong>PVE-PR-25</strong>.
        </div>
        
        <div class="form-acciones">
            <a href="index.php" class="btn btn-cancelar">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-crear" id="btn_crear_ruta">
                <i class="fas fa-check-circle"></i> Crear Ruta
            </button>
        </div>
        
    </form>
    
    <?php endif; ?>
    
</div>

<script>
// ============================================
// VARIABLES GLOBALES
// ============================================
let timeoutPlaca = null;
let placaValida = true;
let placaVerificada = false;

// ============================================
// ACTUALIZAR MEDIO DE TRANSPORTE
// ============================================
function actualizarMedioTransporte() {
    const medio = document.getElementById('medio_transporte').value;
    const campoOtro = document.getElementById('campo_otro');
    const campoPlaca = document.getElementById('campo_placa');
    const inputOtro = document.getElementById('medio_transporte_otro');
    const inputPlaca = document.getElementById('placa');
    const resultado = document.getElementById('resultado_placa');
    const btn = document.getElementById('btn_crear_ruta');
    
    // Reset
    campoOtro.style.display = 'none';
    campoPlaca.style.display = 'none';
    inputOtro.required = false;
    inputPlaca.required = false;
    inputPlaca.classList.remove('placa-ok', 'placa-error', 'placa-cargando');
    resultado.innerHTML = '';
    placaValida = true;
    placaVerificada = false;
    btn.disabled = false;
    
    if (medio === 'OTRO') {
        campoOtro.style.display = 'flex';
        inputOtro.required = true;
    } else if (medio && medio !== 'PUBLICO') {
        campoPlaca.style.display = 'flex';
        inputPlaca.required = true;
        
        // Si ya hay una placa escrita, validarla
        if (inputPlaca.value.trim().length >= 4) {
            verificarPlaca();
        }
    }
}

// ============================================
// DEBOUNCE PARA VALIDACIÓN DE PLACA
// ============================================
function debounceVerificarPlaca() {
    clearTimeout(timeoutPlaca);
    timeoutPlaca = setTimeout(verificarPlaca, 500);
}

// ============================================
// VERIFICAR PLACA (AJAX)
// ============================================
function verificarPlaca() {
    const input = document.getElementById('placa');
    const resultado = document.getElementById('resultado_placa');
    const hint = document.getElementById('hint_placa');
    const btn = document.getElementById('btn_crear_ruta');
    const placa = input.value.trim().toUpperCase();
    
    // Reset
    input.classList.remove('placa-ok', 'placa-error', 'placa-cargando');
    resultado.innerHTML = '';
    hint.style.display = 'block';
    placaValida = true;
    placaVerificada = false;
    btn.disabled = false;
    
    if (placa.length < 4) {
        return;
    }
    
    // Loading
    input.classList.add('placa-cargando');
    resultado.innerHTML = '<div class="mensaje-placa cargando"><i class="fas fa-spinner fa-spin"></i> Verificando placa...</div>';
    hint.style.display = 'none';
    
    fetch('verificar_placa.php?placa=' + encodeURIComponent(placa))
        .then(response => response.json())
        .then(data => {
            input.classList.remove('placa-cargando');
            
            if (data.ok) {
                input.classList.add('placa-ok');
                resultado.innerHTML = `<div class="mensaje-placa ok">
                    <i class="fas fa-check-circle"></i> 
                    <span>${data.mensaje}</span>
                    <span style="margin-left: auto; font-weight: 600;">
                        ${data.vehiculo.marca || ''} ${data.vehiculo.modelo || ''}
                    </span>
                </div>`;
                placaValida = true;
                placaVerificada = true;
                btn.disabled = false;
            } else {
                input.classList.add('placa-error');
                resultado.innerHTML = `<div class="mensaje-placa error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <span>${data.mensaje}</span>
                </div>`;
                placaValida = false;
                placaVerificada = false;
                btn.disabled = true;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            input.classList.remove('placa-cargando');
            input.classList.add('placa-error');
            resultado.innerHTML = '<div class="mensaje-placa error"><i class="fas fa-exclamation-circle"></i> Error al verificar la placa</div>';
            placaValida = false;
            placaVerificada = false;
            btn.disabled = true;
        });
}

// ============================================
// VALIDAR ANTES DE ENVIAR
// ============================================
document.querySelector('form').addEventListener('submit', function(e) {
    const medio = document.getElementById('medio_transporte').value;
    
    // Solo validar si el medio requiere placa
    if (medio && medio !== 'PUBLICO') {
        if (!placaVerificada) {
            e.preventDefault();
            alert('❌ Debes esperar a que se valide la placa antes de crear la ruta');
            return false;
        }
        if (!placaValida) {
            e.preventDefault();
            alert('❌ La placa no es válida. Corrige el error antes de continuar.');
            return false;
        }
    }
});

// ============================================
// AL CARGAR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    actualizarMedioTransporte();
});
</script>

</body>
</html>