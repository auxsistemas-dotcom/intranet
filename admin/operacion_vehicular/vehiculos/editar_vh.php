<?php
// admin/operacion_vehicular/vehiculos/editar_vh.php
// Editar datos del vehículo (marca, modelo, referencia, fecha matrícula, tipo_vehiculo, modalidad_vehiculo, kilometraje, activo, fecha_ultimo_mantenimiento)

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ============================================
// ✅ VERIFICAR ACCESO
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$rol_usuario = (int)($_SESSION['rol'] ?? 3);
$usuario_id  = (int)$_SESSION['usuario_id'];

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
// ✅ OBTENER VEHÍCULO
// ============================================
$stmt = $pdo->prepare("
    SELECT v.id, v.placa, v.marca, v.modelo, v.referencia,
           v.fecha_matricula, v.tipo_vehiculo, v.modalidad_vehiculo, v.activo, v.kilometraje,
           v.fecha_ultimo_mantenimiento,
           v.añadido_por, v.creado_el,
           v.actualizado_por, v.actualizado_el,
           ua.nombre_completo AS agregado_por_nombre,
           um.nombre_completo AS actualizado_por_nombre
    FROM ope_vehiculos v
    LEFT JOIN usuarios ua ON v.añadido_por = ua.id
    LEFT JOIN usuarios um ON v.actualizado_por = um.id
    WHERE v.id = ?
");
$stmt->execute([$vehiculo_id]);
$vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehiculo) {
    $_SESSION['error'] = "❌ Vehículo no encontrado";
    header('Location: ../index.php');
    exit();
}

$km_anterior = (int)$vehiculo['kilometraje'];

// ============================================
// ✅ PROCESAR FORMULARIO (PRG pattern)
// ============================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipos_vehiculo_validos = ['MOTO', 'CARRO', 'CAMION'];
    $modalidades_validas = ['NUEVO', 'TEST_DRIVE', 'USADO', 'PROPIO'];
    
    $nueva_marca = strtoupper(trim($_POST['marca'] ?? ''));
    $nuevo_modelo = strtoupper(trim($_POST['modelo'] ?? ''));
    $nueva_referencia = strtoupper(trim($_POST['referencia'] ?? ''));
    $nueva_fecha_matricula = trim($_POST['fecha_matricula'] ?? '');
    $nuevo_tipo_vehiculo = strtoupper(trim($_POST['tipo_vehiculo'] ?? ''));
    $nueva_modalidad = strtoupper(trim($_POST['modalidad_vehiculo'] ?? ''));
    $nuevo_km = isset($_POST['kilometraje']) ? intval($_POST['kilometraje']) : 0;
    $nuevo_activo = isset($_POST['activo']) ? (int) $_POST['activo'] : 0;
    $nueva_fecha_mantenimiento = trim($_POST['fecha_ultimo_mantenimiento'] ?? ''); // ✅ NUEVO
    
    // ============================================
    // VALIDACIONES
    // ============================================
    if (empty($nueva_marca) || empty($nuevo_modelo) || empty($nueva_referencia) || 
        empty($nueva_fecha_matricula) || empty($nuevo_tipo_vehiculo) || empty($nueva_modalidad) ||
        empty($nueva_fecha_mantenimiento)) {
        $error = "❌ Todos los campos son obligatorios (incluida la fecha de mantenimiento)";
    }
    elseif (!in_array($nuevo_tipo_vehiculo, $tipos_vehiculo_validos)) {
        $error = "❌ Tipo de vehículo no válido";
    }
    elseif (!in_array($nueva_modalidad, $modalidades_validas)) {
        $error = "❌ Modalidad de vehículo no válida";
    }
    elseif (strtotime($nueva_fecha_matricula) > time()) {
        $error = "❌ La fecha de matrícula no puede ser futura";
    }
    elseif (strtotime($nueva_fecha_mantenimiento) > time()) {
        $error = "❌ La fecha del último mantenimiento no puede ser futura";
    }
    elseif (strtotime($nueva_fecha_mantenimiento) < strtotime($nueva_fecha_matricula)) {
        $error = "❌ La fecha del último mantenimiento no puede ser anterior a la matrícula";
    }
    elseif ($nuevo_km < 0) {
        $error = "❌ El kilometraje no puede ser negativo";
    }
    elseif ($nueva_modalidad === 'NUEVO' && $nuevo_km !== 0) {
        $error = "❌ Un vehículo NUEVO debe tener kilometraje 0";
    }
    elseif (in_array($nueva_modalidad, ['TEST_DRIVE', 'USADO', 'PROPIO']) && $nuevo_km <= 0) {
        $error = "❌ Un vehículo " . $nueva_modalidad . " debe tener kilometraje mayor a 0";
    }
    elseif ($nuevo_km < $km_anterior) {
        $error = "❌ El kilometraje no puede ser menor al actual ({$km_anterior} km). Solo puede aumentar.";
    }
    else {
        try {
            $stmt = $pdo->prepare("
                UPDATE ope_vehiculos 
                SET marca = ?, modelo = ?, referencia = ?, 
                    fecha_matricula = ?, tipo_vehiculo = ?, modalidad_vehiculo = ?, kilometraje = ?,
                    fecha_ultimo_mantenimiento = ?,
                    activo = ?, actualizado_por = ?, actualizado_el = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $nueva_marca,
                $nuevo_modelo,
                $nueva_referencia,
                $nueva_fecha_matricula,
                $nuevo_tipo_vehiculo,
                $nueva_modalidad,
                $nuevo_km,
                $nueva_fecha_mantenimiento,   // ✅ NUEVO
                $nuevo_activo,
                $usuario_id,
                $vehiculo_id
            ]);
            
            // Calcular próximo mantenimiento para el mensaje
            $prox = (new DateTime($nueva_fecha_mantenimiento))->modify('+6 months')->format('d/m/Y');
            $_SESSION['mensaje'] = "✅ Datos del vehículo actualizados. Próximo mantenimiento: {$prox}";
            header("Location: ver.php?id=" . $vehiculo_id);
            exit();
            
        } catch (PDOException $e) {
            error_log("Error al actualizar vehículo: " . $e->getMessage());
            $error = "❌ Error al guardar los cambios. Intenta de nuevo.";
        }
    }
    
    // Si hay error, reflejar valores en el form
    $vehiculo['marca'] = $nueva_marca;
    $vehiculo['modelo'] = $nuevo_modelo;
    $vehiculo['referencia'] = $nueva_referencia;
    $vehiculo['fecha_matricula'] = $nueva_fecha_matricula;
    $vehiculo['tipo_vehiculo'] = $nuevo_tipo_vehiculo;
    $vehiculo['modalidad_vehiculo'] = $nueva_modalidad;
    $vehiculo['kilometraje'] = $nuevo_km;
    $vehiculo['activo'] = $nuevo_activo;
    $vehiculo['fecha_ultimo_mantenimiento'] = $nueva_fecha_mantenimiento;
}

// ============================================
// ✅ CALCULAR AÑOS (para alerta tecnomecánica)
// ============================================
$tipo_vh = $vehiculo['tipo_vehiculo'] ?? 'CARRO';
$fecha_matricula = new DateTime($vehiculo['fecha_matricula']);
$hoy = new DateTime();
$hoy->setTime(0, 0, 0);
$anios_actuales = (int) $fecha_matricula->diff($hoy)->y;

$anios_minimos_tecno = ($tipo_vh === 'CARRO') ? 5 : 2;
$requiere_tecno = ($anios_actuales >= $anios_minimos_tecno);

// Etiquetas de tipo
function etiquetaTipoVehiculo($tipo) {
    $mapa = [
        'CARRO' => '🚗 Carro',
        'MOTO' => '🏍️ Moto',
        'CAMION' => '🚚 Camión'
    ];
    return $mapa[$tipo] ?? $tipo;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Vehículo | Operación Vehicular</title>
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
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .card-header {
            border-left: 4px solid #173742;
            padding-left: 15px;
            margin-bottom: 25px;
        }
        
        .card-header h1 {
            font-size: 22px;
            color: #12232b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header h1 i { color: #173742; }
        
        .card-header p {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
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
        
        .info-actual {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            color: #1a5276;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .info-actual i {
            color: #3498db;
            font-size: 16px;
            margin-top: 2px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
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
        
        .form-campo input,
        .form-campo select {
            padding: 12px 16px;
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
        
        .form-campo input:disabled {
            background: #f0f0f0;
            color: #95a5a6;
            cursor: not-allowed;
        }
        
        .form-campo small {
            font-size: 11px;
            color: #95a5a6;
            margin-top: -4px;
        }
        
        .form-campo .aviso-km {
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 4px;
            display: none;
            align-items: center;
            gap: 6px;
        }
        
        .form-campo .aviso-km.visible { display: flex; }
        
        .form-campo .aviso-km.info {
            background: #e8f4f8;
            color: #1a5276;
            border-left: 3px solid #3498db;
        }
        
        .form-campo .aviso-km.warning {
            background: #fef9e7;
            color: #7f6000;
            border-left: 3px solid #f39c12;
        }
        
        .form-campo .aviso-km.danger {
            background: #fadbd8;
            color: #922b21;
            border-left: 3px solid #e74c3c;
        }
        
        /* ✅ NUEVO: Aviso de mantenimiento */
        .form-campo .aviso-mantenimiento {
            font-size: 12px;
            padding: 10px 14px;
            border-radius: 6px;
            margin-top: 4px;
            display: none;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
        }
        
        .form-campo .aviso-mantenimiento.visible {
            display: flex;
        }
        
        .form-campo .aviso-mantenimiento i {
            font-size: 14px;
            margin-top: 1px;
            flex-shrink: 0;
        }
        
        .form-campo .aviso-mantenimiento.ok {
            background: #d5f5e3;
            color: #1a7a3a;
            border-left: 3px solid #27ae60;
        }
        
        .form-campo .aviso-mantenimiento.proximo {
            background: #fef9e7;
            color: #7f6000;
            border-left: 3px solid #f39c12;
        }
        
        .form-campo .aviso-mantenimiento.vencido {
            background: #fadbd8;
            color: #922b21;
            border-left: 3px solid #e74c3c;
        }
        
        /* Separador de secciones */
        .form-separador {
            grid-column: 1 / -1;
            font-size: 13px;
            font-weight: 700;
            color: #173742;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f0f0;
            margin-top: 8px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-separador i {
            color: #173742;
            font-size: 14px;
        }
        
        /* Toggle activo */
        .toggle-activo-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
            flex-shrink: 0;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 26px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background-color: #27ae60;
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .toggle-label {
            font-size: 14px;
            font-weight: 500;
            color: #12232b;
        }
        
        .toggle-label small {
            display: block;
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 400;
            margin-top: 2px;
        }
        
        .form-acciones {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-guardar {
            background: #27ae60;
            color: white;
        }
        
        .btn-guardar:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">Operación Vehicular</div>
        </div>
        <a href="ver.php?id=<?php echo $vehiculo_id; ?>" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al vehículo
        </a>
    </div>
</header>

<div class="container">
    
    <div class="card">
        <div class="card-header">
            <h1>
                <i class="fas fa-edit"></i>
                Editar datos del vehículo
            </h1>
            <p>Placa <strong><?php echo htmlspecialchars($vehiculo['placa']); ?></strong> — Modifica los campos que necesites</p>
        </div>
        
        <?php if (!empty($vehiculo['actualizado_el'])): ?>
            <div class="info-actual">
                <i class="fas fa-info-circle"></i>
                <div>
                    Última actualización: <strong><?php echo date('d/m/Y H:i', strtotime($vehiculo['actualizado_el'])); ?></strong>
                    <?php if (!empty($vehiculo['actualizado_por_nombre'])): ?>
                        por <strong><?php echo htmlspecialchars($vehiculo['actualizado_por_nombre']); ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="formEditar">
            <div class="form-grid">
                
                <!-- ============================================ -->
                <!-- SECCIÓN 1: Datos básicos del vehículo -->
                <!-- ============================================ -->
                <div class="form-separador">
                    <i class="fas fa-info-circle"></i>
                    Datos del vehículo
                </div>
                
                <!-- Placa (solo lectura) -->
                <div class="form-campo">
                    <label for="placa">Placa</label>
                    <input 
                        type="text" 
                        id="placa" 
                        value="<?php echo htmlspecialchars($vehiculo['placa']); ?>"
                        disabled
                    >
                    <small>La placa no se puede modificar</small>
                </div>
                
                <!-- Tipo de vehículo -->
                <div class="form-campo">
                    <label for="tipo_vehiculo">Tipo de vehículo <span class="obligatorio">*</span></label>
                    <select name="tipo_vehiculo" id="tipo_vehiculo" required onchange="actualizarTipoVehiculo()">
                        <option value="">Selecciona...</option>
                        <option value="CARRO" <?php echo $vehiculo['tipo_vehiculo'] === 'CARRO' ? 'selected' : ''; ?>>
                            🚗 Carro
                        </option>
                        <option value="MOTO" <?php echo $vehiculo['tipo_vehiculo'] === 'MOTO' ? 'selected' : ''; ?>>
                            🏍️ Moto
                        </option>
                        <option value="CAMION" <?php echo $vehiculo['tipo_vehiculo'] === 'CAMION' ? 'selected' : ''; ?>>
                            🚚 Camión
                        </option>
                    </select>
                </div>
                
                <!-- Marca -->
                <div class="form-campo">
                    <label for="marca">Marca <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="marca" 
                        id="marca" 
                        placeholder="KIA, CHEVROLET, RENAULT..."
                        value="<?php echo htmlspecialchars($vehiculo['marca']); ?>"
                        required
                        maxlength="50"
                        style="text-transform: uppercase;"
                    >
                </div>
                
                <!-- Modelo -->
                <div class="form-campo">
                    <label for="modelo">Modelo (año) <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="modelo" 
                        id="modelo" 
                        placeholder="2023"
                        value="<?php echo htmlspecialchars($vehiculo['modelo']); ?>"
                        required
                        maxlength="20"
                        style="text-transform: uppercase;"
                    >
                </div>
                
                <!-- Referencia -->
                <div class="form-campo full-width">
                    <label for="referencia">Referencia <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="referencia" 
                        id="referencia" 
                        placeholder="RIO, SPARK, DUSTER..."
                        value="<?php echo htmlspecialchars($vehiculo['referencia']); ?>"
                        required
                        maxlength="100"
                        style="text-transform: uppercase;"
                    >
                </div>
                
                <!-- ============================================ -->
                <!-- SECCIÓN 2: Modalidad y kilometraje -->
                <!-- ============================================ -->
                <div class="form-separador">
                    <i class="fas fa-tag"></i>
                    Modalidad y kilometraje
                </div>
                
                <!-- Modalidad -->
                <div class="form-campo">
                    <label for="modalidad_vehiculo">Modalidad <span class="obligatorio">*</span></label>
                    <select name="modalidad_vehiculo" id="modalidad_vehiculo" required onchange="actualizarKilometraje()">
                        <option value="">Selecciona...</option>
                        <option value="NUEVO" <?php echo $vehiculo['modalidad_vehiculo'] === 'NUEVO' ? 'selected' : ''; ?>>🆕 NUEVO</option>
                        <option value="TEST_DRIVE" <?php echo $vehiculo['modalidad_vehiculo'] === 'TEST_DRIVE' ? 'selected' : ''; ?>>🚗 TEST DRIVE</option>
                        <option value="USADO" <?php echo $vehiculo['modalidad_vehiculo'] === 'USADO' ? 'selected' : ''; ?>>🔄 USADO</option>
                        <option value="PROPIO" <?php echo $vehiculo['modalidad_vehiculo'] === 'PROPIO' ? 'selected' : ''; ?>>👤 PROPIO (empleado)</option>
                    </select>
                </div>
                
                <!-- Kilometraje -->
                <div class="form-campo">
                    <label for="kilometraje">Kilometraje (km) <span class="obligatorio">*</span></label>
                    <input 
                        type="number" 
                        name="kilometraje" 
                        id="kilometraje" 
                        placeholder="0"
                        value="<?php echo (int)$vehiculo['kilometraje']; ?>"
                        required
                        min="<?php echo $km_anterior; ?>"
                        max="9999999"
                        step="1"
                    >
                    <div class="aviso-km" id="avisoKm"></div>
                    <small>Actual: <strong><?php echo number_format($km_anterior); ?> km</strong> · Solo puede aumentar</small>
                </div>
                
                <!-- ============================================ -->
                <!-- SECCIÓN 3: Fecha de matrícula -->
                <!-- ============================================ -->
                <div class="form-separador">
                    <i class="fas fa-calendar"></i>
                    Fecha de matrícula
                </div>
                
                <!-- Fecha matrícula -->
                <div class="form-campo full-width">
                    <label for="fecha_matricula">Fecha de matrícula <span class="obligatorio">*</span></label>
                    <input 
                        type="date" 
                        name="fecha_matricula" 
                        id="fecha_matricula" 
                        value="<?php echo htmlspecialchars($vehiculo['fecha_matricula']); ?>"
                        required
                        max="<?php echo date('Y-m-d'); ?>"
                        onchange="actualizarAvisoTecno()"
                    >
                    <small id="avisoTecno">Calculando...</small>
                </div>
                
                <!-- ============================================ -->
                <!-- SECCIÓN 4: Mantenimiento (NUEVO) -->
                <!-- ============================================ -->
                <div class="form-separador">
                    <i class="fas fa-tools"></i>
                    Mantenimiento
                </div>
                
                <!-- Fecha último mantenimiento -->
                <div class="form-campo full-width">
                    <label for="fecha_ultimo_mantenimiento">Fecha del último mantenimiento <span class="obligatorio">*</span></label>
                    <input 
                        type="date" 
                        name="fecha_ultimo_mantenimiento" 
                        id="fecha_ultimo_mantenimiento" 
                        value="<?php echo htmlspecialchars($vehiculo['fecha_ultimo_mantenimiento'] ?? ''); ?>"
                        required
                        max="<?php echo date('Y-m-d'); ?>"
                        onchange="actualizarVistaMantenimiento()"
                    >
                    <small>Se debe renovar cada 6 meses. Si vence, el vehículo no podrá viajar.</small>
                    <div class="aviso-mantenimiento" id="avisoMantenimiento"></div>
                </div>
                
                <!-- ============================================ -->
                <!-- SECCIÓN 5: Estado del vehículo -->
                <!-- ============================================ -->
                <div class="form-separador">
                    <i class="fas fa-toggle-on"></i>
                    Estado del vehículo
                </div>
                
                <!-- Activo -->
                <div class="form-campo full-width">
                    <label>Estado del vehículo</label>
                    <div class="toggle-activo-wrapper">
                        <input type="hidden" name="activo" value="0">
                        
                        <label class="toggle-switch">
                            <input 
                                type="checkbox" 
                                name="activo" 
                                id="activo"
                                value="1"
                                <?php echo ((int)$vehiculo['activo'] === 1) ? 'checked' : ''; ?>
                            >
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="toggle-label">
                            <span id="labelActivo">
                                <?php echo ((int)$vehiculo['activo'] === 1) ? 'Activo' : 'Inactivo'; ?>
                            </span>
                            <small>Los vehículos inactivos no aparecen en el listado principal</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-acciones">
                <a href="ver.php?id=<?php echo $vehiculo_id; ?>" class="btn btn-cancelar">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-guardar">
                    <i class="fas fa-save"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ============================================
    // VARIABLES GLOBALES
    // ============================================
    const kmAnterior = <?php echo $km_anterior; ?>;
    
    // ============================================
    // ACTUALIZAR KM SEGÚN MODALIDAD
    // ============================================
    function actualizarKilometraje() {
        const modalidad = document.getElementById('modalidad_vehiculo').value;
        const kmInput = document.getElementById('kilometraje');
        const aviso = document.getElementById('avisoKm');
        
        aviso.classList.remove('visible', 'info', 'warning', 'danger');
        aviso.innerHTML = '';
        
        if (modalidad === 'NUEVO') {
            if (kmAnterior > 0) {
                aviso.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Este vehículo ya tiene ' + kmAnterior + ' km. Cambiar la modalidad a NUEVO requiere kilometraje 0, pero no puedes reducir el km actual.';
                aviso.classList.add('visible', 'danger');
            } else {
                kmInput.value = 0;
                kmInput.disabled = true;
                aviso.innerHTML = '<i class="fas fa-info-circle"></i> Un vehículo NUEVO debe tener kilometraje 0';
                aviso.classList.add('visible', 'info');
            }
        } 
        else if (modalidad === 'TEST_DRIVE' || modalidad === 'USADO' || modalidad === 'PROPIO') {
            kmInput.disabled = false;
            if (kmInput.value == 0 || kmInput.value === '') {
                kmInput.value = kmAnterior;
            }
            aviso.innerHTML = '<i class="fas fa-check-circle"></i> Debes ingresar un km mayor o igual a ' + kmAnterior + ' km';
            aviso.classList.add('visible', 'warning');
        }
        else {
            kmInput.disabled = false;
        }
    }
    
    // ============================================
    // ACTUALIZAR AVISO DE TECNOMECÁNICA
    // ============================================
    function actualizarAvisoTecno() {
        const tipoVh = document.getElementById('tipo_vehiculo').value;
        const fechaMat = document.getElementById('fecha_matricula').value;
        const aviso = document.getElementById('avisoTecno');
        
        if (!tipoVh || !fechaMat) {
            aviso.textContent = 'Completa el tipo de vehículo y la fecha de matrícula';
            aviso.style.color = '#95a5a6';
            return;
        }
        
        const aniosMinimos = (tipoVh === 'CARRO') ? 5 : 2;
        const fecha = new Date(fechaMat + 'T00:00:00');
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        
        let anios = hoy.getFullYear() - fecha.getFullYear();
        const m = hoy.getMonth() - fecha.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < fecha.getDate())) {
            anios--;
        }
        
        const nombreTipo = tipoVh === 'CARRO' ? 'carro' : (tipoVh === 'MOTO' ? 'moto' : 'camión');
        
        if (anios >= aniosMinimos) {
            aviso.textContent = `Este ${nombreTipo} tiene ${anios} año(s). Requiere Tecnomecánica obligatoria (${aniosMinimos}+ años).`;
            aviso.style.color = '#92400e';
            aviso.style.fontWeight = '600';
        } else {
            aviso.textContent = `Este ${nombreTipo} tiene ${anios} año(s). No requiere Tecnomecánica (menos de ${aniosMinimos} años).`;
            aviso.style.color = '#1a5276';
            aviso.style.fontWeight = '500';
        }
    }
    
    // ============================================
    // ACTUALIZAR TIPO DE VEHÍCULO → RECALCULAR TECNO
    // ============================================
    function actualizarTipoVehiculo() {
        actualizarAvisoTecno();
    }
    
    // ============================================
    // ✅ NUEVO: VISTA PREVIA DEL MANTENIMIENTO
    // ============================================
    function actualizarVistaMantenimiento() {
        const input = document.getElementById('fecha_ultimo_mantenimiento');
        const aviso = document.getElementById('avisoMantenimiento');
        
        aviso.classList.remove('visible', 'ok', 'proximo', 'vencido');
        aviso.innerHTML = '';
        
        if (!input.value) return;
        
        const fechaMant = new Date(input.value + 'T00:00:00');
        const proximo = new Date(fechaMant);
        proximo.setMonth(proximo.getMonth() + 6);
        
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        
        const diffMs = proximo - hoy;
        const diasRestantes = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        
        const fechaProxStr = proximo.toLocaleDateString('es-CO', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        });
        
        let clase, icono, mensaje;
        
        if (diasRestantes < 0) {
            clase = 'vencido';
            icono = 'fa-times-circle';
            mensaje = `<strong>VENCIDO hace ${Math.abs(diasRestantes)} días.</strong> El vehículo NO podrá viajar hasta que se actualice el mantenimiento.`;
        } 
        else if (diasRestantes <= 15) {
            clase = 'proximo';
            icono = 'fa-exclamation-triangle';
            mensaje = `<strong>Próximo mantenimiento: ${fechaProxStr}</strong> — vence en ${diasRestantes} días. Avisar con anticipación.`;
        } 
        else {
            clase = 'ok';
            icono = 'fa-check-circle';
            mensaje = `<strong>Próximo mantenimiento: ${fechaProxStr}</strong> — al día (${diasRestantes} días restantes).`;
        }
        
        aviso.innerHTML = `<i class="fas ${icono}"></i><div>${mensaje}</div>`;
        aviso.classList.add('visible', clase);
    }
    
    // ============================================
    // TOGGLE ACTIVO/INACTIVO
    // ============================================
    const activoCheckbox = document.getElementById('activo');
    const labelActivo = document.getElementById('labelActivo');
    
    if (activoCheckbox && labelActivo) {
        activoCheckbox.addEventListener('change', function() {
            labelActivo.textContent = this.checked ? 'Activo' : 'Inactivo';
        });
    }
    
    // ============================================
    // INICIALIZAR AL CARGAR
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('modalidad_vehiculo').value) {
            const modalidad = document.getElementById('modalidad_vehiculo').value;
            if (modalidad === 'NUEVO' && kmAnterior === 0) {
                document.getElementById('kilometraje').disabled = true;
            }
        }
        
        actualizarAvisoTecno();
        
        // ✅ NUEVO: Si ya hay fecha de mantenimiento, mostrar vista previa
        if (document.getElementById('fecha_ultimo_mantenimiento').value) {
            actualizarVistaMantenimiento();
        }
    });
    
    // ============================================
    // VALIDACIÓN AL ENVIAR
    // ============================================
    document.getElementById('formEditar').addEventListener('submit', function(e) {
        const modalidad = document.getElementById('modalidad_vehiculo').value;
        const km = parseInt(document.getElementById('kilometraje').value) || 0;
        
        if (modalidad === 'NUEVO' && km !== 0) {
            e.preventDefault();
            alert('❌ Un vehículo NUEVO debe tener kilometraje 0');
            return false;
        }
        
        if ((modalidad === 'TEST_DRIVE' || modalidad === 'USADO' || modalidad === 'PROPIO') && km <= 0) {
            e.preventDefault();
            alert('❌ Un vehículo ' + modalidad + ' debe tener kilometraje mayor a 0');
            return false;
        }
        
        if (km < kmAnterior) {
            e.preventDefault();
            alert('❌ El kilometraje no puede ser menor al actual (' + kmAnterior + ' km)');
            return false;
        }
    });
</script>

</body>
</html>