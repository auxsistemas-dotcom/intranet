<?php
// admin/operacion_vehicular/vehiculos/agregar.php
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
// ✅ CANCELAR TODO EL PROCESO
// ============================================
if (isset($_GET['cancelar'])) {
    unset($_SESSION['vehiculo_temp']);
    $_SESSION['error'] = "❌ Proceso cancelado. No se guardó nada.";
    header("Location: ../index.php");
    exit();
}

// ============================================
// ✅ PRECARGAR VALORES DESDE SESIÓN
// ============================================
$valores = [
    'placa' => '',
    'marca' => '',
    'modelo' => '',
    'referencia' => '',
    'fecha_matricula' => '',
    'tipo_vehiculo' => '',
    'modalidad_vehiculo' => '',
    'kilometraje' => 0,
    'fecha_ultimo_mantenimiento' => ''  // ✅ NUEVO
];

if (isset($_SESSION['vehiculo_temp'])) {
    $valores['placa'] = $_SESSION['vehiculo_temp']['placa'] ?? '';
    $valores['marca'] = $_SESSION['vehiculo_temp']['marca'] ?? '';
    $valores['modelo'] = $_SESSION['vehiculo_temp']['modelo'] ?? '';
    $valores['referencia'] = $_SESSION['vehiculo_temp']['referencia'] ?? '';
    $valores['fecha_matricula'] = $_SESSION['vehiculo_temp']['fecha_matricula'] ?? '';
    $valores['tipo_vehiculo'] = $_SESSION['vehiculo_temp']['tipo_vehiculo'] ?? '';
    $valores['modalidad_vehiculo'] = $_SESSION['vehiculo_temp']['modalidad_vehiculo'] ?? '';
    $valores['kilometraje'] = $_SESSION['vehiculo_temp']['kilometraje'] ?? 0;
    $valores['fecha_ultimo_mantenimiento'] = $_SESSION['vehiculo_temp']['fecha_ultimo_mantenimiento'] ?? '';
}

// ============================================
// ✅ PROCESAR FORMULARIO
// ============================================
$error = '';

$tipos_vehiculo_validos = ['MOTO', 'CARRO', 'CAMION'];
$modalidades_validas = ['NUEVO', 'TEST_DRIVE', 'USADO', 'PROPIO'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos
    $valores['placa'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['placa'] ?? ''));
    $valores['marca'] = strtoupper(trim($_POST['marca'] ?? ''));
    $valores['modelo'] = strtoupper(trim($_POST['modelo'] ?? ''));
    $valores['referencia'] = strtoupper(trim($_POST['referencia'] ?? ''));
    $valores['fecha_matricula'] = trim($_POST['fecha_matricula'] ?? '');
    $valores['tipo_vehiculo'] = strtoupper(trim($_POST['tipo_vehiculo'] ?? ''));
    $valores['modalidad_vehiculo'] = strtoupper(trim($_POST['modalidad_vehiculo'] ?? ''));
    $valores['kilometraje'] = isset($_POST['kilometraje']) ? intval($_POST['kilometraje']) : 0;
    $valores['fecha_ultimo_mantenimiento'] = trim($_POST['fecha_ultimo_mantenimiento'] ?? ''); // ✅ NUEVO
    
    // ✅ VALIDACIONES
    if (empty($valores['placa']) || empty($valores['marca']) || empty($valores['modelo']) || 
        empty($valores['referencia']) || empty($valores['fecha_matricula']) || 
        empty($valores['tipo_vehiculo']) || empty($valores['modalidad_vehiculo']) ||
        empty($valores['fecha_ultimo_mantenimiento'])) {
        $error = "❌ Todos los campos son obligatorios (incluida la fecha de mantenimiento)";
    }
    elseif (!preg_match('/^([A-Z]{3}[0-9]{3}|[A-Z]{3}[0-9]{2}[A-Z]|[A-Z]{3}[0-9]{2})$/', $valores['placa'])) {
        $error = "❌ Formato de placa no válido. Ejemplos: ABC123, ABC12D, ABC12";
    }
    elseif (!in_array($valores['tipo_vehiculo'], $tipos_vehiculo_validos)) {
        $error = "❌ Tipo de vehículo no válido";
    }
    elseif (!in_array($valores['modalidad_vehiculo'], $modalidades_validas)) {
        $error = "❌ Modalidad de vehículo no válida";
    }
    elseif (strtotime($valores['fecha_matricula']) > time()) {
        $error = "❌ La fecha de matrícula no puede ser futura";
    }
    elseif (strtotime($valores['fecha_ultimo_mantenimiento']) > time()) {
        $error = "❌ La fecha del último mantenimiento no puede ser futura";
    }
    elseif (strtotime($valores['fecha_ultimo_mantenimiento']) < strtotime($valores['fecha_matricula'])) {
        $error = "❌ La fecha del último mantenimiento no puede ser anterior a la matrícula";
    }
    elseif ($valores['kilometraje'] < 0) {
        $error = "❌ El kilometraje no puede ser negativo";
    }
    elseif ($valores['modalidad_vehiculo'] === 'NUEVO' && $valores['kilometraje'] !== 0) {
        $error = "❌ Un vehículo NUEVO debe tener kilometraje 0";
    }
    elseif (in_array($valores['modalidad_vehiculo'], ['TEST_DRIVE', 'USADO', 'PROPIO']) && $valores['kilometraje'] <= 0) {
        $error = "❌ Un vehículo " . $valores['modalidad_vehiculo'] . " debe tener kilometraje mayor a 0";
    }
    else {
        // Verificar que NO exista ya en BD
        $stmt = $pdo->prepare("SELECT id FROM ope_vehiculos WHERE placa = ?");
        $stmt->execute([$valores['placa']]);
        
        if ($stmt->fetch()) {
            $error = "❌ Ya existe un vehículo con la placa {$valores['placa']}";
        } else {
            // Preservar documentos_temp si ya existían
            $documentos_existentes = $_SESSION['vehiculo_temp']['documentos_temp'] ?? [];
            
            // Guardar en sesión temporal con los nuevos campos
            $_SESSION['vehiculo_temp'] = [
                'placa' => $valores['placa'],
                'marca' => $valores['marca'],
                'modelo' => $valores['modelo'],
                'referencia' => $valores['referencia'],
                'fecha_matricula' => $valores['fecha_matricula'],
                'tipo_vehiculo' => $valores['tipo_vehiculo'],
                'modalidad_vehiculo' => $valores['modalidad_vehiculo'],
                'kilometraje' => $valores['kilometraje'],
                'fecha_ultimo_mantenimiento' => $valores['fecha_ultimo_mantenimiento'], // ✅ NUEVO
                'añadido_por' => $_SESSION['usuario_id'],
                'documentos_temp' => $documentos_existentes
            ];
            
            header("Location: documentos.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Vehículo | Operación Vehicular</title>
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
        
        .aviso-sesion {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            color: #1a5276;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .aviso-sesion i { color: #3498db; font-size: 16px; }
        
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
        
        .form-campo input::placeholder {
            color: #b0b0b0;
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
        
        .form-campo .aviso-km.visible {
            display: flex;
        }
        
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
        
        /* ============================================ */
        /* ✅ NUEVO: Aviso de mantenimiento             */
        /* ============================================ */
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
        
        .form-acciones {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
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
        
        .btn-continuar {
            background: #27ae60;
            color: white;
        }
        
        .btn-continuar:hover {
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
        <a href="../index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>
</header>

<div class="container">
    
    <div class="card">
        <div class="card-header">
            <h1>
                <i class="fas fa-car"></i>
                Agregar Vehículo
            </h1>
            <p>Completa los datos del vehículo. En el siguiente paso agregarás SOAT y Tecnomecánica.</p>
        </div>
        
        <?php if (isset($_SESSION['vehiculo_temp']) && !empty($_SESSION['vehiculo_temp']['placa'])): ?>
            <div class="aviso-sesion">
                <i class="fas fa-info-circle"></i>
                <div>
                    Estás editando un vehículo en proceso. Los datos se conservan de tu intento anterior. 
                    <?php 
                    $total_docs = count($_SESSION['vehiculo_temp']['documentos_temp'] ?? []);
                    if ($total_docs > 0): 
                    ?>
                        <strong><?php echo $total_docs; ?> documento(s)</strong> ya agregado(s) se conservarán.
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
        
        <form method="POST" action="" id="formVehiculo">
            <div class="form-grid">
                
                <!-- ============================================ -->
                <!-- SECCIÓN 1: Datos básicos del vehículo -->
                <!-- ============================================ -->
                <div class="form-separador">
                    <i class="fas fa-info-circle"></i>
                    Datos del vehículo
                </div>
                
                <div class="form-campo">
                    <label for="tipo_vehiculo">Tipo de vehículo <span class="obligatorio">*</span></label>
                    <select name="tipo_vehiculo" id="tipo_vehiculo" required>
                        <option value="">Selecciona...</option>
                        <option value="CARRO" <?php echo $valores['tipo_vehiculo'] === 'CARRO' ? 'selected' : ''; ?>>
                            🚗 Carro
                        </option>
                        <option value="MOTO" <?php echo $valores['tipo_vehiculo'] === 'MOTO' ? 'selected' : ''; ?>>
                            🏍️ Moto
                        </option>
                        <option value="CAMION" <?php echo $valores['tipo_vehiculo'] === 'CAMION' ? 'selected' : ''; ?>>
                            🚚 Camión
                        </option>
                    </select>
                </div>
                
                <div class="form-campo">
                    <label for="placa">Placa <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="placa" 
                        id="placa" 
                        placeholder="ABC123"
                        value="<?php echo htmlspecialchars($valores['placa']); ?>"
                        required
                        maxlength="10"
                        style="text-transform: uppercase;"
                        autofocus
                    >
                    <small>Formatos válidos: ABC123, ABC12D o ABC12</small>
                </div>
                
                <div class="form-campo">
                    <label for="marca">Marca <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="marca" 
                        id="marca" 
                        placeholder="KIA, CHEVROLET, RENAULT..."
                        value="<?php echo htmlspecialchars($valores['marca']); ?>"
                        required
                        maxlength="50"
                        style="text-transform: uppercase;"
                    >
                </div>
                
                <div class="form-campo">
                    <label for="modelo">Modelo (año) <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="modelo" 
                        id="modelo" 
                        placeholder="2023"
                        value="<?php echo htmlspecialchars($valores['modelo']); ?>"
                        required
                        maxlength="20"
                        style="text-transform: uppercase;"
                    >
                </div>
                
                <div class="form-campo full-width">
                    <label for="referencia">Referencia <span class="obligatorio">*</span></label>
                    <input 
                        type="text" 
                        name="referencia" 
                        id="referencia" 
                        placeholder="RIO, SPARK, DUSTER..."
                        value="<?php echo htmlspecialchars($valores['referencia']); ?>"
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
                
                <div class="form-campo">
                    <label for="modalidad_vehiculo">Modalidad <span class="obligatorio">*</span></label>
                    <select name="modalidad_vehiculo" id="modalidad_vehiculo" required onchange="actualizarKilometraje()">
                        <option value="">Selecciona...</option>
                        <option value="NUEVO" <?php echo $valores['modalidad_vehiculo'] === 'NUEVO' ? 'selected' : ''; ?>>
                            🆕 NUEVO
                        </option>
                        <option value="TEST_DRIVE" <?php echo $valores['modalidad_vehiculo'] === 'TEST_DRIVE' ? 'selected' : ''; ?>>
                            🚗 TEST DRIVE
                        </option>
                        <option value="USADO" <?php echo $valores['modalidad_vehiculo'] === 'USADO' ? 'selected' : ''; ?>>
                            🔄 USADO
                        </option>
                        <option value="PROPIO" <?php echo $valores['modalidad_vehiculo'] === 'PROPIO' ? 'selected' : ''; ?>>
                            👤 PROPIO (empleado)
                        </option>
                    </select>
                </div>
                
                <div class="form-campo">
                    <label for="kilometraje">Kilometraje (km) <span class="obligatorio">*</span></label>
                    <input 
                        type="number" 
                        name="kilometraje" 
                        id="kilometraje" 
                        placeholder="0"
                        value="<?php echo htmlspecialchars($valores['kilometraje']); ?>"
                        required
                        min="0"
                        max="9999999"
                        step="1"
                    >
                    <div class="aviso-km" id="avisoKm"></div>
                </div>
                
                <!-- ============================================ -->
                <!-- SECCIÓN 3: Fecha de matrícula -->
                <!-- ============================================ -->
                <div class="form-separador">
                    <i class="fas fa-calendar"></i>
                    Fecha de matrícula
                </div>
                
                <div class="form-campo full-width">
                    <label for="fecha_matricula">Fecha de matrícula <span class="obligatorio">*</span></label>
                    <input 
                        type="date" 
                        name="fecha_matricula" 
                        id="fecha_matricula" 
                        value="<?php echo htmlspecialchars($valores['fecha_matricula']); ?>"
                        required
                        max="<?php echo date('Y-m-d'); ?>"
                    >
                    <small>Si el VH tiene 5 años o más, la Tecnomecánica será obligatoria</small>
                </div>
                
                <!-- ============================================ -->
                <!-- SECCIÓN 4: Mantenimiento (NUEVO) -->
                <!-- ============================================ -->
                <div class="form-separador">
                    <i class="fas fa-tools"></i>
                    Mantenimiento
                </div>
                
                <div class="form-campo full-width">
                    <label for="fecha_ultimo_mantenimiento">Fecha del último mantenimiento <span class="obligatorio">*</span></label>
                    <input 
                        type="date" 
                        name="fecha_ultimo_mantenimiento" 
                        id="fecha_ultimo_mantenimiento" 
                        value="<?php echo htmlspecialchars($valores['fecha_ultimo_mantenimiento']); ?>"
                        required
                        max="<?php echo date('Y-m-d'); ?>"
                        onchange="actualizarVistaMantenimiento()"
                    >
                    <small>Se debe renovar cada 6 meses. Si vence, el vehículo no podrá viajar.</small>
                    <div class="aviso-mantenimiento" id="avisoMantenimiento"></div>
                </div>
                
            </div>
            
            <div class="form-acciones">
                <a href="?cancelar=1" class="btn btn-cancelar">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-continuar">
                    <i class="fas fa-arrow-right"></i> Continuar a documentos
                </button>
            </div>
        </form>
        
    </div>
</div>

<script>
    // ============================================
    // ACTUALIZAR EL CAMPO KILOMETRAJE SEGÚN LA MODALIDAD
    // ============================================
    function actualizarKilometraje() {
        const modalidad = document.getElementById('modalidad_vehiculo').value;
        const kmInput = document.getElementById('kilometraje');
        const aviso = document.getElementById('avisoKm');
        
        aviso.classList.remove('visible', 'info', 'warning');
        aviso.innerHTML = '';
        
        if (modalidad === 'NUEVO') {
            kmInput.value = 0;
            kmInput.disabled = true;
            aviso.innerHTML = '<i class="fas fa-info-circle"></i> Un vehículo NUEVO debe tener kilometraje 0';
            aviso.classList.add('visible', 'info');
        } 
        else if (modalidad === 'TEST_DRIVE' || modalidad === 'USADO' || modalidad === 'PROPIO') {
            kmInput.disabled = false;
            if (kmInput.value == 0 || kmInput.value === '') {
                kmInput.value = '';
            }
            aviso.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Debes ingresar el kilometraje actual del vehículo (mayor a 0)';
            aviso.classList.add('visible', 'warning');
        } 
        else {
            kmInput.disabled = false;
        }
    }
    
    // ============================================
    // ✅ NUEVO: VISTA PREVIA DEL MANTENIMIENTO
    // Calcula +6 meses y muestra estado en vivo
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
    // VALIDACIÓN AL ENVIAR
    // ============================================
    document.getElementById('formVehiculo').addEventListener('submit', function(e) {
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
    });
    
    // ============================================
    // INICIALIZAR
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('modalidad_vehiculo').value) {
            actualizarKilometraje();
            <?php if (!empty($valores['kilometraje'])): ?>
                document.getElementById('kilometraje').value = <?php echo intval($valores['kilometraje']); ?>;
            <?php endif; ?>
        }
        
        // ✅ NUEVO: Si ya hay fecha de mantenimiento precargada, mostrar vista previa
        if (document.getElementById('fecha_ultimo_mantenimiento').value) {
            actualizarVistaMantenimiento();
        }
    });
</script>

</body>
</html>