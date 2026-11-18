<?php
// aplicaciones/otros/operacion_vehicular/ruta.php
// Crear nueva ruta con Encuesta de Seguridad Vial + Checklist dinámico
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// ============================================
// ✅ DEFINICIÓN DE LOS 3 CHECKLISTS
// ============================================
function getChecklists() {
    return [
        'CARRO' => [
            'titulo' => '🚗 Checklist de Carro',
            'items' => [
                'llantas'  => '🛞 Estado de llantas',
                'interna'  => '🔧 Revisión interna (aceite, frenos, refrigerante, batería, filtros)',
                'externa'  => '🚗 Revisión externa (lámina y pintura)',
                'luces'    => '💡 Luces y pito',
                'kit'      => '🧰 Kit de carretera'
            ]
        ],
        'MOTO' => [
            'titulo' => '🏍️ Checklist de Moto',
            'items' => [
                'casco'           => '🪖 Casco reglamentario',
                'motor'           => '⚙️ Funcionamiento del motor',
                'kit_arrastre'    => '🔗 Kit de arrastre (piñones - cadena)',
                'amortiguadores'  => '🔧 Amortiguadores delantero - trasero(s)',
                'maniguetas'      => '🛞 Maniguetas (izquierda - derecha)',
                'embrague'        => '🖐️ Ajuste de embrague',
                'guayas'          => '🧵 Fundas y guayas',
                'freno_del'       => '🛑 Ajuste de freno delantero',
                'freno_tras'      => '🛑 Ajuste de freno trasero',
                'liquido_freno'   => '💧 Nivel de líquido de freno',
                'encendido'       => '🔑 Encendido (cran - eléctrico)',
                'luces_pito'      => '💡 Luces / pito',
                'rines'           => '⚙️ Rines (radios, aspas)',
                'reposa_pies'     => '🦶 Reposa pies',
                'llantas_presion' => '🛞 Llantas - presión de aire y estado'
            ]
        ],
        'CAMION' => [
            'titulo' => '🚚 Checklist de Camión (Nodriza)',
            'items' => [
                'general'           => '🚛 GENERAL: Carrocería, placa, entorno y parte inferior en buen estado, sin daños ni fugas.',
                'llantas_ruedas'    => '🛞 LLANTAS Y RUEDAS: Llantas, labrado, presión, rines, pernos y repuesto en buen estado.',
                'fluidos'           => '💧 FLUIDOS Y FUGAS: Niveles de fluidos adecuados y ausencia de fugas.',
                'luces_electrico'   => '💡 LUCES Y ELÉCTRICO: Luces, direccionales, stops, tablero, cableado y pito funcionales.',
                'visibilidad'       => '🪟 VISIBILIDAD Y CABINA: Vidrios, espejos, limpiaparabrisas, asientos y cinturones en buen estado.',
                'mandos_mecanica'   => '⚙️ MANDOS Y MECÁNICA: Frenos, dirección, suspensión, ejes, cardán y sistema neumático funcionales.',
                'seguridad'         => '🧯 SEGURIDAD: Extintor vigente, equipo de carretera, botiquín, kit de derrame, linterna y EPP disponibles.',
                'estructura'        => '🏗️ ESTRUCTURA: Chasis, plataformas, torres, soldaduras, rampas, bisagras y seguros íntegros.',
                'sistema_mecanico'  => '🔩 SISTEMA MECÁNICO: Guayas, poleas, rodamientos y anclajes en buen estado y funcionamiento.',
                'sistema_hidraulico'=> '💦 SISTEMA HIDRÁULICO: Tanque, cilindros, vástagos, mangueras, acoples, mandos y válvulas sin daños ni fugas.',
                'prueba_funcional'  => '🏗️ PRUEBA FUNCIONAL: Elevación y descenso sin carga, con movimientos uniformes y seguros.',
                'antes_cargue'      => '📋 ANTES DEL CARGUE: Capacidad y dimensiones permitidas; zona de cargue firme, nivelada y delimitada.',
                'despues_cargue'    => '📦 DESPUÉS DEL CARGUE: Carga distribuida y asegurada; plataformas bloqueadas, dimensiones reglamentarias y revisión 360°.'
            ]
        ]
    ];
}

// Mapeo medio → qué checklist usa
function checklistSegunMedio($medio) {
    $mapa = [
        'PROPIO'       => 'CARRO',
        'ORGANIZACION' => 'CARRO',
        'MOTO'         => 'MOTO',
        'NODRIZA'      => 'CAMION'
    ];
    return $mapa[$medio] ?? 'CARRO';
}

$CHECKLISTS = getChecklists();

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
// ✅ MAPEO: medio → tipo + modalidad
// ============================================
function filtrosSegunMedio($medio) {
    $mapa = [
        'PROPIO'       => ['tipos' => ['CARRO'],  'modalidades' => ['PROPIO']],
        'ORGANIZACION' => ['tipos' => ['CARRO'],  'modalidades' => ['NUEVO', 'TEST_DRIVE', 'USADO']],
        'MOTO'         => ['tipos' => ['MOTO'],   'modalidades' => []],
        'NODRIZA'      => ['tipos' => ['CAMION'], 'modalidades' => []]
    ];
    return $mapa[$medio] ?? ['tipos' => [], 'modalidades' => []];
}

// ============================================
// ✅ PROCESAR FORMULARIO
// ============================================
$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento_conductor = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['documento_conductor'] ?? ''));
    $motivo_viaje = strtoupper(trim($_POST['motivo_viaje'] ?? ''));
    $fecha_regreso = trim($_POST['fecha_regreso'] ?? '');
    $autorizado_jefe = isset($_POST['autorizado_jefe']) ? (int) $_POST['autorizado_jefe'] : 0;
    $ciudad_origen = strtoupper(trim($_POST['ciudad_origen'] ?? ''));
    $ciudad_destino = strtoupper(trim($_POST['ciudad_destino'] ?? ''));
    $medio_transporte = trim($_POST['medio_transporte'] ?? '');
    $placa_input = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['placa'] ?? ''));
    $km_salida = isset($_POST['km_salida']) ? (int) $_POST['km_salida'] : 0;
    
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
    
    $medios_validos = ['PROPIO', 'MOTO', 'ORGANIZACION', 'NODRIZA'];
    
    // ✅ Validar checklist según medio
    $tipo_checklist = checklistSegunMedio($medio_transporte);
    $items_esperados = array_keys($CHECKLISTS[$tipo_checklist]['items']);
    $items_faltantes = array_diff($items_esperados, $checklist);
    $total_esperados = count($items_esperados);
    $total_marcados = count(array_intersect($items_esperados, $checklist));
    
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
    elseif (!in_array($medio_transporte, $medios_validos)) {
        $error = "❌ Medio de transporte no válido";
    }
    elseif (strtotime($fecha_regreso) < strtotime(date('Y-m-d'))) {
        $error = "❌ La fecha de regreso no puede ser anterior a hoy";
    }
    elseif (empty($placa_input)) {
        $error = "❌ Debes indicar la placa del vehículo";
    }
    elseif ($km_salida < 0) {
        $error = "❌ El kilometraje de salida no puede ser negativo";
    }
    elseif (!empty($items_faltantes)) {
        // ✅ VALIDACIÓN ESTRICTA DEL CHECKLIST
        $error = "❌ Debes marcar TODOS los puntos del checklist para <strong>" 
               . $CHECKLISTS[$tipo_checklist]['titulo'] . "</strong>. "
               . "Marcaste <strong>{$total_marcados} de {$total_esperados}</strong>. "
               . "Faltan <strong>" . count($items_faltantes) . " item(s)</strong>.";
    }
    else {
        // ✅ VALIDAR PLACA CONTRA BD
        $vehiculo = null;
        $placa_valida = true;
        
        $stmt = $pdo->prepare("
            SELECT id, placa, marca, modelo, activo, tipo_vehiculo, modalidad_vehiculo, kilometraje,
                   fecha_ultimo_mantenimiento
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
            // 🔒 VALIDAR TIPO Y MODALIDAD SEGÚN EL MEDIO
            $filtros = filtrosSegunMedio($medio_transporte);
            $tipo_real = $vehiculo['tipo_vehiculo'];
            $modalidad_real = $vehiculo['modalidad_vehiculo'];
            
            $tipo_ok = empty($filtros['tipos']) || in_array($tipo_real, $filtros['tipos']);
            $modalidad_ok = empty($filtros['modalidades']) || in_array($modalidad_real, $filtros['modalidades']);
            
            if (!$tipo_ok || !$modalidad_ok) {
                $nombres_medio = [
                    'PROPIO' => 'Vehículo propio',
                    'ORGANIZACION' => 'Vehículo de la organización',
                    'MOTO' => 'Motocicleta',
                    'NODRIZA' => 'Vehículo nodriza'
                ];
                
                $descripcion_esperada = '';
                if ($medio_transporte === 'PROPIO') {
                    $descripcion_esperada = 'un CARRO con modalidad PROPIO (del empleado)';
                } elseif ($medio_transporte === 'ORGANIZACION') {
                    $descripcion_esperada = 'un CARRO con modalidad NUEVO, TEST DRIVE o USADO (de la organización)';
                } elseif ($medio_transporte === 'MOTO') {
                    $descripcion_esperada = 'una MOTO';
                } elseif ($medio_transporte === 'NODRIZA') {
                    $descripcion_esperada = 'el CAMIÓN (nodriza)';
                }
                
                $error = "❌ El vehículo <strong>{$vehiculo['placa']}</strong> no cumple con los requisitos del medio " 
                       . "<strong>" . ($nombres_medio[$medio_transporte] ?? $medio_transporte) . "</strong>. "
                       . "Debes elegir " . $descripcion_esperada . ".";
                $placa_valida = false;
            }
            elseif ($km_salida < (int)$vehiculo['kilometraje']) {
                $km_actual_fmt = number_format((int)$vehiculo['kilometraje'], 0, ',', '.');
                $error = "❌ El kilometraje de salida (<strong>" . number_format($km_salida, 0, ',', '.') . " km</strong>) " 
                       . "no puede ser menor al kilometraje actual del vehículo (<strong>{$km_actual_fmt} km</strong>).";
                $placa_valida = false;
            }
            elseif ($km_salida === 0 && (int)$vehiculo['kilometraje'] > 0) {
                $km_actual_fmt = number_format((int)$vehiculo['kilometraje'], 0, ',', '.');
                $error = "❌ El vehículo ya tiene <strong>{$km_actual_fmt} km</strong> registrados. " 
                       . "El kilometraje de salida no puede ser 0.";
                $placa_valida = false;
            }
            else {
                // 🔒 VALIDAR MANTENIMIENTO VIGENTE
                $fecha_mant = $vehiculo['fecha_ultimo_mantenimiento'] ?? null;
                
                if (empty($fecha_mant)) {
                    $error = "🔧 El vehículo <strong>{$vehiculo['placa']}</strong> no tiene registrada la fecha del último mantenimiento. "
                           . "Contacta al administrador para actualizarla antes de poder viajar.";
                    $placa_valida = false;
                }
                else {
                    $proximo_mant = new DateTime($fecha_mant);
                    $proximo_mant->modify('+6 months');
                    $proximo_mant->setTime(0, 0, 0);
                    
                    if ($proximo_mant < $hoy) {
                        $dias_vencido = (int) $proximo_mant->diff($hoy)->days;
                        $fecha_mant_fmt = date('d/m/Y', strtotime($fecha_mant));
                        $fecha_prox_fmt = $proximo_mant->format('d/m/Y');
                        
                        $error = "🔧 El vehículo <strong>{$vehiculo['placa']}</strong> tiene el <strong>mantenimiento VENCIDO</strong> "
                               . "hace <strong>{$dias_vencido} días</strong>. "
                               . "Último mantenimiento: <strong>{$fecha_mant_fmt}</strong>. "
                               . "Venció el <strong>{$fecha_prox_fmt}</strong>. "
                               . "Contacta al administrador para registrar el nuevo mantenimiento antes de poder viajar.";
                        $placa_valida = false;
                    }
                }
                
                // 🔒 VALIDAR QUE EL VEHÍCULO NO ESTÉ EN OTRA RUTA
                if ($placa_valida) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM ope_ruta 
                        WHERE vehiculo_id = ? AND estado = 'EN_CURSO'
                    ");
                    $stmt->execute([$vehiculo['id']]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "🚫 El vehículo <strong>{$vehiculo['placa']}</strong> se encuentra en movilización. Debes elegir otro.";
                        $placa_valida = false;
                    }
                }
                
                // 🔒 VALIDAR DOCUMENTOS DEL VEHÍCULO
                if ($placa_valida) {
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
            
            // Guardar checklist con tipo y detalles
            $checklist_guardar = [
                'tipo' => $tipo_checklist,
                'items' => array_intersect($items_esperados, $checklist)
            ];
            
            if (!empty($bloqueos)) {
                $bloqueado_hasta = date('Y-m-d', strtotime('+1 day'));
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO ope_ruta 
                        (vehiculo_id, conductor_id, documento_conductor, motivo_viaje, km_salida, km_llegada, 
                         fecha_salida, fecha_regreso, autorizado_jefe, ciudad_origen, ciudad_destino, 
                         medio_transporte, medio_transporte_otro, checklist_vh, conoce_codigo, 
                         salud_estable, descanso_optimo, emocional_estable, alcohol_24h, sustancias_24h, 
                         medicamentos, bloqueado_hasta, estado, añadido_por, creado_el)
                        VALUES (NULL, ?, ?, ?, ?, NULL, NOW(), NULL, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CANCELADO', ?, NOW())
                    ");
                    $stmt->execute([
                        $conductor_id,
                        $documento_conductor,
                        $motivo_viaje,
                        $km_salida,
                        $autorizado_jefe,
                        $ciudad_origen,
                        $ciudad_destino,
                        $medio_transporte,
                        json_encode($checklist_guardar),
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
                    $stmt = $pdo->prepare("
                        INSERT INTO ope_ruta 
                        (vehiculo_id, conductor_id, documento_conductor, motivo_viaje, km_salida, km_llegada,
                         fecha_salida, fecha_regreso, autorizado_jefe, ciudad_origen, ciudad_destino, 
                         medio_transporte, medio_transporte_otro, checklist_vh, conoce_codigo, 
                         salud_estable, descanso_optimo, emocional_estable, alcohol_24h, sustancias_24h,
                         medicamentos, bloqueado_hasta, estado, añadido_por, creado_el)
                        VALUES (?, ?, ?, ?, ?, NULL, NOW(), ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'EN_CURSO', ?, NOW())
                    ");
                    $stmt->execute([
                        $vehiculo['id'],
                        $conductor_id,
                        $documento_conductor,
                        $motivo_viaje,
                        $km_salida,
                        $fecha_regreso . ' 23:59:59',
                        $autorizado_jefe,
                        $ciudad_origen,
                        $ciudad_destino,
                        $medio_transporte,
                        json_encode($checklist_guardar),
                        $conoce_codigo,
                        $salud_estable,
                        $descanso_optimo,
                        $emocional_estable,
                        $alcohol_24h,
                        $sustancias_24h,
                        $medicamentos,
                        $usuario_id
                    ]);
                    
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

$hoy_str = date('d/m/Y');
$hoy_input = date('Y-m-d');
$manana_input = date('Y-m-d', strtotime('+1 day'));

// Determinar medio seleccionado (para precargar el checklist correcto)
$medio_actual = $_POST['medio_transporte'] ?? '';
$tipo_checklist_actual = checklistSegunMedio($medio_actual);
$checklist_seleccionados = $_POST['checklist'] ?? [];
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
        .form-campo input[type="number"],
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
        
        /* AUTOCOMPLETE PLACA */
        .autocomplete-wrapper { position: relative; }
        
        .autocomplete-dropdown {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            z-index: 1000;
            max-height: 320px;
            overflow-y: auto;
            display: none;
        }
        
        .autocomplete-dropdown.visible {
            display: block;
            animation: slideDown 0.15s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .autocomplete-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item.disponible { cursor: pointer; }
        .autocomplete-item.disponible:hover { background: #f0fdf4; }
        .autocomplete-item.no-disponible {
            cursor: not-allowed;
            opacity: 0.55;
            background: #fafafa;
        }
        .autocomplete-item.no-disponible:hover { background: #fafafa; }
        
        .autocomplete-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .autocomplete-icon.disponible { background: #d5f5e3; color: #27ae60; }
        .autocomplete-icon.en_ruta { background: #fef3c7; color: #f39c12; }
        .autocomplete-icon.docs_vencidos { background: #fadbd8; color: #e74c3c; }
        .autocomplete-icon.inactivo { background: #ecf0f1; color: #95a5a6; }
        .autocomplete-icon.mantenimiento_vencido { background: #fadbd8; color: #e74c3c; }
        
        .autocomplete-info { flex: 1; min-width: 0; }
        
        .autocomplete-placa {
            font-weight: 700;
            color: #12232b;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .autocomplete-detalle {
            font-size: 11px;
            color: #7f8c8d;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .autocomplete-motivo {
            font-size: 11px;
            font-weight: 600;
            color: #e74c3c;
            margin-top: 3px;
        }
        
        .autocomplete-badge {
            padding: 3px 9px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .autocomplete-badge.disponible { background: #27ae60; color: white; }
        .autocomplete-badge.no-disponible { background: #95a5a6; color: white; }
        
        .autocomplete-loading {
            padding: 16px;
            text-align: center;
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .autocomplete-vacio {
            padding: 20px;
            text-align: center;
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .autocomplete-vacio i {
            font-size: 24px;
            color: #d3d3d3;
            display: block;
            margin-bottom: 6px;
        }
        
        #placa.placa-ok { border-color: #27ae60 !important; background: #d5f5e3 !important; }
        #placa.placa-error { border-color: #e74c3c !important; background: #fadbd8 !important; }
        
        .mensaje-placa {
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
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
        
        #km_salida.km-ok { border-color: #27ae60 !important; background: #f0fdf4 !important; }
        #km_salida.km-error { border-color: #e74c3c !important; background: #fadbd8 !important; }
        
        .info-km-actual {
            font-size: 12px;
            color: #1a5276;
            background: #e8f4f8;
            border-left: 3px solid #3498db;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 4px;
            display: none;
            align-items: center;
            gap: 6px;
        }
        
        .info-km-actual.visible { display: flex; }
        .info-km-actual i { color: #3498db; font-size: 14px; }
        .info-km-actual.nuevo { background: #fef3c7; border-color: #f39c12; color: #92400e; }
        .info-km-actual.nuevo i { color: #f39c12; }
        
        .mensaje-km {
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .mensaje-km.ok { background: #d5f5e3; color: #1a7a3a; border-left: 3px solid #27ae60; }
        .mensaje-km.info { background: #fef3c7; color: #92400e; border-left: 3px solid #f39c12; }
        .mensaje-km.error { background: #fadbd8; color: #922b21; border-left: 3px solid #e74c3c; }
        
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
        
        .radio-option:hover { border-color: #173742; }
        .radio-option input[type="radio"] { cursor: pointer; }
        .radio-option input[type="radio"]:checked + span { font-weight: 600; color: #173742; }
        .radio-option.si:has(input:checked) { background: #d5f5e3; border-color: #27ae60; }
        .radio-option.no:has(input:checked) { background: #fadbd8; border-color: #e74c3c; }
        
        /* CHECKLIST */
        .checklist-header-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #173742;
        }
        
        .checklist-titulo-tipo {
            font-size: 14px;
            font-weight: 700;
            color: #12232b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checklist-contador {
            font-size: 13px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            background: #e74c3c;
            color: white;
            transition: all 0.3s ease;
        }
        
        .checklist-contador.completo {
            background: #27ae60;
        }
        
        .checklist-contador i {
            margin-right: 4px;
        }
        
        .checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
        }
        
        .checklist-grid.moto {
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }
        
        .checklist-grid.camion {
            grid-template-columns: 1fr;
        }
        
        .checklist-item {
            display: flex;
            align-items: flex-start;
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
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .checklist-item span {
            flex: 1;
            line-height: 1.4;
        }
        
        .checklist-item input[type="checkbox"]:checked ~ span {
            font-weight: 600;
            color: #27ae60;
        }
        
        .checklist-item:has(input:checked) {
            background: #d5f5e3;
            border-color: #27ae60;
        }
        
        .checklist-aviso {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            color: #1a5276;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checklist-aviso i { color: #3498db; font-size: 16px; }
        
        /* ENCUESTA */
        .seccion-encuesta {
            background: #f8f9fa;
            border-left: 4px solid #173742;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .seccion-encuesta .form-campo { margin-bottom: 18px; }
        .seccion-encuesta .form-campo:last-child { margin-bottom: 0; }
        
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
        
        .btn-cancelar { background: #7f8c8d; color: white; }
        .btn-cancelar:hover { background: #95a5a6; transform: translateY(-2px); }
        
        .btn-crear { background: #27ae60; color: white; }
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
                <a href="ruta.php" class="btn btn-cancelar">
                    <i class="fas fa-redo"></i> Volver a intentar
                </a>
            </div>
        </div>
    <?php else: ?>
    
    <form method="POST" action="" id="form_ruta">
        
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
                        <option value="PROPIO" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'PROPIO') ? 'selected' : ''; ?>>🚗 Vehículo propio</option>
                        <option value="MOTO" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'MOTO') ? 'selected' : ''; ?>>🏍️ Motocicleta</option>
                        <option value="ORGANIZACION" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'ORGANIZACION') ? 'selected' : ''; ?>>🚙 Vehículo de la organización</option>
                        <option value="NODRIZA" <?php echo (isset($_POST['medio_transporte']) && $_POST['medio_transporte'] == 'NODRIZA') ? 'selected' : ''; ?>>🚐 Vehículo nodriza / niñera</option>
                    </select>
                </div>
                
                <div class="form-campo full-width" id="campo_placa" style="display: none;">
                    <label for="placa">Placa del vehículo <span class="obligatorio">*</span></label>
                    <div class="autocomplete-wrapper">
                        <input 
                            type="text" 
                            name="placa" 
                            id="placa" 
                            placeholder="Escribe la placa o marca..."
                            maxlength="6"
                            style="text-transform: uppercase;"
                            value="<?php echo htmlspecialchars($_POST['placa'] ?? ''); ?>"
                            oninput="onInputPlaca(this)"
                            onfocus="onFocusPlaca()"
                            onblur="onBlurPlaca()"
                            autocomplete="off"
                        >
                        <div class="autocomplete-dropdown" id="autocomplete_dropdown">
                            <!-- Contenido dinámico -->
                        </div>
                    </div>
                    <div id="resultado_placa"></div>
                    <small id="hint_placa">La placa debe estar registrada y disponible en el sistema</small>
                </div>
                
                <div class="form-campo full-width" id="campo_km_salida" style="display: none;">
                    <label for="km_salida">Kilometraje actual del vehículo (salida) <span class="obligatorio">*</span></label>
                    <input 
                        type="number" 
                        name="km_salida" 
                        id="km_salida" 
                        placeholder="Ej: 45100"
                        min="0"
                        max="9999999"
                        step="1"
                        oninput="validarKmSalida()"
                        value="<?php echo htmlspecialchars($_POST['km_salida'] ?? ''); ?>"
                    >
                    <div class="info-km-actual" id="info_km_actual">
                        <i class="fas fa-tachometer-alt"></i>
                        <span id="texto_km_actual"></span>
                    </div>
                    <div id="resultado_km"></div>
                    <small id="hint_km_salida">Ingresa el km que marca el tablero actualmente. Debe ser igual o mayor al km registrado.</small>
                </div>
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- CHECKLIST DINÁMICO (según medio) -->
        <!-- ============================================ -->
        <div class="card" id="card_checklist" style="display: none;">
            <div class="card-title">
                <i class="fas fa-clipboard-check"></i>
                Revisión de puntos al vehículo antes de viajar
            </div>
            
            <div class="checklist-aviso">
                <i class="fas fa-info-circle"></i>
                <span>Debes marcar <strong>TODOS los puntos</strong> del checklist para poder continuar. No se puede enviar el formulario si falta algún item.</span>
            </div>
            
            <div class="checklist-header-info">
                <div class="checklist-titulo-tipo" id="checklist_titulo_tipo">
                    <i class="fas fa-list-check"></i>
                    <span id="checklist_titulo">Selecciona un medio de transporte</span>
                </div>
                <div class="checklist-contador" id="checklist_contador">
                    <i class="fas fa-check-circle"></i>
                    <span id="checklist_contador_texto">0 / 0</span>
                </div>
            </div>
            
            <div class="checklist-grid" id="checklist_grid">
                <!-- Se llena dinámicamente con JS -->
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
            <button type="submit" class="btn btn-crear" id="btn_crear_ruta" disabled>
                <i class="fas fa-check-circle"></i> Crear Ruta
            </button>
        </div>
        
    </form>
    
    <?php endif; ?>
    
</div>

<!-- ============================================ -->
<!-- DATOS DE CHECKLISTS PASADOS A JS -->
<!-- ============================================ -->
<script>
const CHECKLISTS = <?php echo json_encode($CHECKLISTS, JSON_UNESCAPED_UNICODE); ?>;
const CHECKLIST_POR_MEDIO = {
    'PROPIO': 'CARRO',
    'ORGANIZACION': 'CARRO',
    'MOTO': 'MOTO',
    'NODRIZA': 'CAMION'
};
const CHECKLIST_SELECCIONADOS = <?php echo json_encode($checklist_seleccionados, JSON_UNESCAPED_UNICODE); ?>;
</script>

<script>
// ============================================
// VARIABLES GLOBALES
// ============================================
let timeoutBusqueda = null;
let placaSeleccionada = null;
let placaValida = false;
let blurTimeout = null;
let kmActualVehiculo = 0;
let kmSalidaValido = false;
let checklistCompleto = false;

// ============================================
// CONFIGURACIÓN DE MEDIOS
// ============================================
const CONFIG_MEDIOS = {
    'PROPIO': {
        nombre_tipo: 'CARRO PROPIO',
        placeholder: 'Placa del carro propio (Ej: ABC123)',
        hint: 'Solo se aceptan CARROS con modalidad PROPIO (del empleado)'
    },
    'ORGANIZACION': {
        nombre_tipo: 'CARRO de la organización',
        placeholder: 'Placa del carro (Ej: ABC123)',
        hint: 'Solo se aceptan CARROS con modalidad NUEVO, TEST DRIVE o USADO (de la organización)'
    },
    'MOTO': {
        nombre_tipo: 'MOTO',
        placeholder: 'Placa de la moto (Ej: ABC12D)',
        hint: 'Solo se aceptan MOTOCICLETAS'
    },
    'NODRIZA': {
        nombre_tipo: 'CAMIÓN (nodriza)',
        placeholder: 'Placa del camión (Ej: ABC123)',
        hint: 'Solo se acepta el CAMIÓN nodriza'
    }
};

// ============================================
// RENDERIZAR CHECKLIST SEGÚN MEDIO
// ============================================
function renderizarChecklist(medio) {
    const cardChecklist = document.getElementById('card_checklist');
    const grid = document.getElementById('checklist_grid');
    const tituloTipo = document.getElementById('checklist_titulo');
    const contador = document.getElementById('checklist_contador');
    const contadorTexto = document.getElementById('checklist_contador_texto');
    
    grid.innerHTML = '';
    grid.classList.remove('moto', 'camion');
    
    if (!medio || !CHECKLIST_POR_MEDIO[medio]) {
        cardChecklist.style.display = 'none';
        checklistCompleto = false;
        return;
    }
    
    const tipoChecklist = CHECKLIST_POR_MEDIO[medio];
    const data = CHECKLISTS[tipoChecklist];
    
    if (!data) {
        cardChecklist.style.display = 'none';
        checklistCompleto = false;
        return;
    }
    
    cardChecklist.style.display = 'block';
    tituloTipo.textContent = data.titulo;
    
    if (tipoChecklist === 'MOTO') grid.classList.add('moto');
    if (tipoChecklist === 'CAMION') grid.classList.add('camion');
    
    Object.entries(data.items).forEach(([valor, texto]) => {
        const checked = CHECKLIST_SELECCIONADOS.includes(valor) ? 'checked' : '';
        const label = document.createElement('label');
        label.className = 'checklist-item';
        label.innerHTML = `
            <input type="checkbox" name="checklist[]" value="${valor}" ${checked} onchange="actualizarContadorChecklist()">
            <span>${texto}</span>
        `;
        grid.appendChild(label);
    });
    
    actualizarContadorChecklist();
}

// ============================================
// CONTADOR DEL CHECKLIST
// ============================================
function actualizarContadorChecklist() {
    const checkboxes = document.querySelectorAll('#checklist_grid input[type="checkbox"]');
    const total = checkboxes.length;
    let marcados = 0;
    
    checkboxes.forEach(cb => { if (cb.checked) marcados++; });
    
    const contador = document.getElementById('checklist_contador');
    const contadorTexto = document.getElementById('checklist_contador_texto');
    
    contadorTexto.textContent = `${marcados} / ${total}`;
    contador.classList.toggle('completo', marcados === total && total > 0);
    
    checklistCompleto = (marcados === total && total > 0);
    
    // Actualizar estado del botón (junto con placa + km)
    actualizarBotonEnviar();
}

// ============================================
// ACTUALIZAR BOTÓN ENVIAR
// ============================================
function actualizarBotonEnviar() {
    const btn = document.getElementById('btn_crear_ruta');
    btn.disabled = !(placaValida && kmSalidaValido && checklistCompleto);
}

// ============================================
// ACTUALIZAR MEDIO DE TRANSPORTE
// ============================================
function actualizarMedioTransporte() {
    const medio = document.getElementById('medio_transporte').value;
    const campoPlaca = document.getElementById('campo_placa');
    const campoKmSalida = document.getElementById('campo_km_salida');
    const inputPlaca = document.getElementById('placa');
    const inputKmSalida = document.getElementById('km_salida');
    const resultado = document.getElementById('resultado_placa');
    const hint = document.getElementById('hint_placa');
    const dropdown = document.getElementById('autocomplete_dropdown');
    const infoKmActual = document.getElementById('info_km_actual');
    const resultadoKm = document.getElementById('resultado_km');
    const hintKm = document.getElementById('hint_km_salida');
    
    campoPlaca.style.display = 'none';
    campoKmSalida.style.display = 'none';
    inputPlaca.value = '';
    inputPlaca.required = false;
    inputPlaca.classList.remove('placa-ok', 'placa-error');
    inputPlaca.placeholder = '';
    inputKmSalida.value = '';
    inputKmSalida.classList.remove('km-ok', 'km-error');
    resultado.innerHTML = '';
    resultadoKm.innerHTML = '';
    infoKmActual.classList.remove('visible', 'nuevo');
    dropdown.innerHTML = '';
    dropdown.classList.remove('visible');
    placaSeleccionada = null;
    placaValida = false;
    kmSalidaValido = false;
    kmActualVehiculo = 0;
    hintKm.textContent = 'Ingresa el km que marca el tablero actualmente. Debe ser igual o mayor al km registrado.';
    
    if (medio && CONFIG_MEDIOS[medio]) {
        const config = CONFIG_MEDIOS[medio];
        campoPlaca.style.display = 'flex';
        inputPlaca.required = true;
        inputPlaca.placeholder = config.placeholder;
        hint.textContent = config.hint;
        hint.style.color = '#3498db';
        hint.style.fontWeight = '500';
    }
    
    // Renderizar checklist
    renderizarChecklist(medio);
    actualizarBotonEnviar();
}

// ============================================
// INPUT PLACA
// ============================================
function onInputPlaca(input) {
    const valorLimpio = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
    
    input.value = valorLimpio;
    input.classList.remove('placa-ok', 'placa-error');
    placaValida = false;
    placaSeleccionada = null;
    kmActualVehiculo = 0;
    
    const campoKmSalida = document.getElementById('campo_km_salida');
    campoKmSalida.style.display = 'none';
    document.getElementById('km_salida').value = '';
    document.getElementById('info_km_actual').classList.remove('visible', 'nuevo');
    document.getElementById('resultado_km').innerHTML = '';
    kmSalidaValido = false;
    
    actualizarBotonEnviar();
    
    const resultado = document.getElementById('resultado_placa');
    if (valorLimpio.length < 2) {
        resultado.innerHTML = '';
        ocultarDropdown();
        return;
    }
    
    debounceBuscar(valorLimpio);
}

// ============================================
// DEBOUNCE
// ============================================
function debounceBuscar(termino) {
    clearTimeout(timeoutBusqueda);
    timeoutBusqueda = setTimeout(() => buscarPlacas(termino), 300);
}

// ============================================
// BUSCAR PLACAS
// ============================================
function buscarPlacas(termino) {
    const dropdown = document.getElementById('autocomplete_dropdown');
    const medio = document.getElementById('medio_transporte').value;
    
    if (!medio) {
        dropdown.innerHTML = '<div class="autocomplete-vacio"><i class="fas fa-exclamation-circle"></i>Primero selecciona un medio de transporte</div>';
        dropdown.classList.add('visible');
        return;
    }
    
    dropdown.innerHTML = '<div class="autocomplete-loading"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';
    dropdown.classList.add('visible');
    
    fetch('verificar_placa.php?placa=' + encodeURIComponent(termino) + '&medio=' + encodeURIComponent(medio))
        .then(response => response.json())
        .then(data => {
            if (!data.ok) {
                dropdown.innerHTML = '<div class="autocomplete-vacio"><i class="fas fa-exclamation-circle"></i>Error al buscar</div>';
                return;
            }
            
            const sugerencias = data.sugerencias || [];
            const config = CONFIG_MEDIOS[medio];
            
            if (sugerencias.length === 0) {
                dropdown.innerHTML = `<div class="autocomplete-vacio">
                    <i class="fas fa-search"></i>
                    No se encontraron vehículos que cumplan con: <strong>${config ? config.nombre_tipo : ''}</strong>
                </div>`;
                return;
            }
            
            let html = '';
            sugerencias.forEach(sug => {
                const iconClass = sug.estado_visual;
                let icono = 'fa-car';
                if (sug.estado_visual === 'en_ruta') icono = 'fa-route';
                else if (sug.estado_visual === 'docs_vencidos') icono = 'fa-file-excel';
                else if (sug.estado_visual === 'inactivo') icono = 'fa-times-circle';
                else if (sug.estado_visual === 'mantenimiento_vencido') icono = 'fa-tools';
                else if (sug.estado_visual === 'disponible') icono = 'fa-check-circle';
                
                const detalle = [sug.marca, sug.modelo, sug.referencia].filter(Boolean).join(' ');
                const disabledClass = sug.puede_usar ? 'disponible' : 'no-disponible';
                const badgeClass = sug.puede_usar ? 'disponible' : 'no-disponible';
                const badgeText = sug.puede_usar ? 'Disponible' : (sug.motivo || 'No disponible');
                const kmFmt = new Intl.NumberFormat('es-CO').format(sug.kilometraje || 0);
                
                html += `
                    <div class="autocomplete-item ${disabledClass}" 
                         data-placa="${sug.placa}" 
                         data-id="${sug.id}"
                         ${sug.puede_usar ? `onclick="seleccionarPlaca('${sug.placa}', ${sug.id}, '${detalle.replace(/'/g, "\\'")}', ${sug.kilometraje || 0})"` : ''}>
                        <div class="autocomplete-icon ${iconClass}">
                            <i class="fas ${icono}"></i>
                        </div>
                        <div class="autocomplete-info">
                            <div class="autocomplete-placa">${sug.placa}</div>
                            ${detalle ? `<div class="autocomplete-detalle">${detalle} · ${kmFmt} km</div>` : `<div class="autocomplete-detalle">${kmFmt} km</div>`}
                            ${!sug.puede_usar && sug.motivo ? `<div class="autocomplete-motivo">${sug.motivo}</div>` : ''}
                        </div>
                        <span class="autocomplete-badge ${badgeClass}">${badgeText}</span>
                    </div>
                `;
            });
            
            dropdown.innerHTML = html;
            dropdown.classList.add('visible');
            
            if (data.existe_exacta && data.disponible_exacta) {
                placaValida = true;
                const input = document.getElementById('placa');
                input.classList.add('placa-ok');
                
                const vehiculoExacto = sugerencias.find(s => s.placa === termino);
                if (vehiculoExacto) {
                    kmActualVehiculo = vehiculoExacto.kilometraje || 0;
                }
                
                document.getElementById('resultado_placa').innerHTML = 
                    '<div class="mensaje-placa ok"><i class="fas fa-check-circle"></i> Placa válida y disponible</div>';
                
                mostrarCampoKm(kmActualVehiculo);
            }
            
            actualizarBotonEnviar();
        })
        .catch(error => {
            console.error('Error:', error);
            dropdown.innerHTML = '<div class="autocomplete-vacio"><i class="fas fa-exclamation-circle"></i>Error al buscar</div>';
        });
}

// ============================================
// MOSTRAR CAMPO KM
// ============================================
function mostrarCampoKm(kmActual) {
    const campoKmSalida = document.getElementById('campo_km_salida');
    const inputKm = document.getElementById('km_salida');
    const infoKmActual = document.getElementById('info_km_actual');
    const textoKmActual = document.getElementById('texto_km_actual');
    const hintKm = document.getElementById('hint_km_salida');
    
    campoKmSalida.style.display = 'flex';
    infoKmActual.classList.add('visible');
    
    const esVehiculoNuevo = (kmActual === 0);
    
    if (esVehiculoNuevo) {
        infoKmActual.classList.add('nuevo');
        textoKmActual.textContent = '🚗 Vehículo NUEVO (0 km). Puedes dejar el km en 0.';
        hintKm.textContent = 'Este vehículo es nuevo. Se permite km_salida = 0.';
        hintKm.style.color = '#92400e';
        hintKm.style.fontWeight = '500';
    } else {
        infoKmActual.classList.remove('nuevo');
        textoKmActual.textContent = 'Km registrado en el sistema: ' + new Intl.NumberFormat('es-CO').format(kmActual) + ' km';
        hintKm.textContent = 'Ingresa el km que marca el tablero actualmente. Debe ser igual o mayor al km registrado.';
        hintKm.style.color = '';
        hintKm.style.fontWeight = '';
    }
    
    inputKm.value = kmActual;
    inputKm.min = 0;
    
    validarKmSalida();
}

// ============================================
// VALIDAR KM SALIDA
// ============================================
function validarKmSalida() {
    const inputKm = document.getElementById('km_salida');
    const resultado = document.getElementById('resultado_km');
    const km = parseInt(inputKm.value) || 0;
    
    inputKm.classList.remove('km-ok', 'km-error');
    resultado.innerHTML = '';
    
    if (!placaValida) {
        kmSalidaValido = false;
        actualizarBotonEnviar();
        return;
    }
    
    if (km < 0) {
        inputKm.classList.add('km-error');
        resultado.innerHTML = '<div class="mensaje-km error"><i class="fas fa-exclamation-circle"></i> El km no puede ser negativo</div>';
        kmSalidaValido = false;
        actualizarBotonEnviar();
        return;
    }
    
    if (km < kmActualVehiculo) {
        inputKm.classList.add('km-error');
        resultado.innerHTML = `<div class="mensaje-km error"><i class="fas fa-exclamation-circle"></i> El km no puede ser menor al registrado (${new Intl.NumberFormat('es-CO').format(kmActualVehiculo)} km)</div>`;
        kmSalidaValido = false;
        actualizarBotonEnviar();
        return;
    }
    
    if (km === 0 && kmActualVehiculo > 0) {
        inputKm.classList.add('km-error');
        resultado.innerHTML = `<div class="mensaje-km error"><i class="fas fa-exclamation-circle"></i> El vehículo ya tiene ${new Intl.NumberFormat('es-CO').format(kmActualVehiculo)} km registrados. El km de salida no puede ser 0.</div>`;
        kmSalidaValido = false;
        actualizarBotonEnviar();
        return;
    }
    
    if (km === 0 && kmActualVehiculo === 0) {
        inputKm.classList.add('km-ok');
        kmSalidaValido = true;
        actualizarBotonEnviar();
        return;
    }
    
    inputKm.classList.add('km-ok');
    resultado.innerHTML = '<div class="mensaje-km ok"><i class="fas fa-check-circle"></i> Kilometraje válido</div>';
    kmSalidaValido = true;
    actualizarBotonEnviar();
}

// ============================================
// SELECCIONAR PLACA
// ============================================
function seleccionarPlaca(placa, id, detalle, kmActual) {
    const input = document.getElementById('placa');
    input.value = placa;
    input.classList.remove('placa-error');
    input.classList.add('placa-ok');
    
    placaValida = true;
    placaSeleccionada = { placa, id, detalle };
    kmActualVehiculo = kmActual || 0;
    
    document.getElementById('resultado_placa').innerHTML = 
        `<div class="mensaje-placa ok"><i class="fas fa-check-circle"></i> ${placa} — ${detalle || 'Disponible'}</div>`;
    
    mostrarCampoKm(kmActualVehiculo);
    ocultarDropdown();
}

// ============================================
// FOCUS / BLUR / OCULTAR
// ============================================
function onFocusPlaca() {
    clearTimeout(blurTimeout);
    const dropdown = document.getElementById('autocomplete_dropdown');
    if (dropdown.innerHTML.trim() !== '') dropdown.classList.add('visible');
}

function onBlurPlaca() {
    blurTimeout = setTimeout(() => ocultarDropdown(), 200);
}

function ocultarDropdown() {
    document.getElementById('autocomplete_dropdown').classList.remove('visible');
}

// ============================================
// VALIDAR ANTES DE ENVIAR
// ============================================
document.getElementById('form_ruta').addEventListener('submit', function(e) {
    const medio = document.getElementById('medio_transporte').value;
    
    if (!placaValida) {
        e.preventDefault();
        alert('❌ Debes seleccionar una placa válida del autocompletado');
        document.getElementById('placa').focus();
        return false;
    }
    
    if (!kmSalidaValido) {
        e.preventDefault();
        alert('❌ Debes ingresar un kilometraje de salida válido');
        document.getElementById('km_salida').focus();
        return false;
    }
    
    if (!checklistCompleto) {
        e.preventDefault();
        const checkboxes = document.querySelectorAll('#checklist_grid input[type="checkbox"]');
        const total = checkboxes.length;
        let marcados = 0;
        checkboxes.forEach(cb => { if (cb.checked) marcados++; });
        
        alert(`❌ Debes marcar TODOS los puntos del checklist.\n\nMarcaste ${marcados} de ${total}.\nFaltan ${total - marcados} item(s).`);
        document.getElementById('card_checklist').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
});

// ============================================
// AL CARGAR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const medioInicial = document.getElementById('medio_transporte').value;
    actualizarMedioTransporte();
    
    // Si había una placa válida precargada (por POST con error), revalidar
    <?php if (!empty($_POST['placa'])): ?>
    const placaPrevia = document.getElementById('placa').value;
    if (placaPrevia) {
        // Disparar búsqueda para revalidar
        onInputPlaca(document.getElementById('placa'));
    }
    <?php endif; ?>
});
</script>

</body>
</html>