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
// (si vienes de "Cancelar" en documentos.php)
// ============================================
$valores = [
    'placa' => '',
    'marca' => '',
    'modelo' => '',
    'referencia' => '',
    'fecha_matricula' => '',
    'tipo' => ''
];

if (isset($_SESSION['vehiculo_temp'])) {
    $valores['placa'] = $_SESSION['vehiculo_temp']['placa'] ?? '';
    $valores['marca'] = $_SESSION['vehiculo_temp']['marca'] ?? '';
    $valores['modelo'] = $_SESSION['vehiculo_temp']['modelo'] ?? '';
    $valores['referencia'] = $_SESSION['vehiculo_temp']['referencia'] ?? '';
    $valores['fecha_matricula'] = $_SESSION['vehiculo_temp']['fecha_matricula'] ?? '';
    $valores['tipo'] = $_SESSION['vehiculo_temp']['tipo'] ?? '';
}

// ============================================
// ✅ PROCESAR FORMULARIO
// ============================================
$error = '';
$tipos_validos = ['NUEVO', 'TEST_DRIVE', 'USADO'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos - TODOS los campos de texto en MAYÚSCULAS
    $valores['placa'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['placa'] ?? ''));
    $valores['marca'] = strtoupper(trim($_POST['marca'] ?? ''));
    $valores['modelo'] = strtoupper(trim($_POST['modelo'] ?? ''));
    $valores['referencia'] = strtoupper(trim($_POST['referencia'] ?? ''));
    $valores['fecha_matricula'] = trim($_POST['fecha_matricula'] ?? '');
    $valores['tipo'] = strtoupper(trim($_POST['tipo'] ?? ''));
    
    // ✅ VALIDACIONES
    if (empty($valores['placa']) || empty($valores['marca']) || empty($valores['modelo']) || 
        empty($valores['referencia']) || empty($valores['fecha_matricula']) || empty($valores['tipo'])) {
        $error = "❌ Todos los campos son obligatorios";
    }
    // Validar formato de placa
    elseif (!preg_match('/^([A-Z]{3}[0-9]{3}|[A-Z]{3}[0-9]{2}[A-Z]|[A-Z]{3}[0-9]{2})$/', $valores['placa'])) {
        $error = "❌ Formato de placa no válido. Ejemplos: ABC123, ABC12D, ABC12";
    }
    // Validar tipo
    elseif (!in_array($valores['tipo'], $tipos_validos)) {
        $error = "❌ Tipo de vehículo no válido";
    }
    // Validar que la fecha de matrícula no sea futura
    elseif (strtotime($valores['fecha_matricula']) > time()) {
        $error = "❌ La fecha de matrícula no puede ser futura";
    }
    else {
        // Verificar que NO exista ya en BD
        $stmt = $pdo->prepare("SELECT id FROM ope_vehiculos WHERE placa = ?");
        $stmt->execute([$valores['placa']]);
        
        if ($stmt->fetch()) {
            $error = "❌ Ya existe un vehículo con la placa {$valores['placa']}";
        } else {
            // ✅ PRESERVAR documentos_temp si ya existían (cuando vuelve de "Cancelar" en documentos.php)
            $documentos_existentes = $_SESSION['vehiculo_temp']['documentos_temp'] ?? [];
            
            // ✅ GUARDAR EN SESIÓN TEMPORAL
            $_SESSION['vehiculo_temp'] = [
                'placa' => $valores['placa'],
                'marca' => $valores['marca'],
                'modelo' => $valores['modelo'],
                'referencia' => $valores['referencia'],
                'fecha_matricula' => $valores['fecha_matricula'],
                'tipo' => $valores['tipo'],
                'añadido_por' => $_SESSION['usuario_id'],
                'documentos_temp' => $documentos_existentes   // ← Se mantienen si ya existían
            ];
            
            // Redirigir a documentos
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
        
        /* ========== CARD ========== */
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
        
        /* ========== AVISO SESIÓN EXISTENTE ========== */
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
        
        .aviso-sesion i {
            color: #3498db;
            font-size: 16px;
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
        
        /* ========== FORMULARIO ========== */
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
        
        .form-campo small {
            font-size: 11px;
            color: #95a5a6;
            margin-top: -4px;
        }
        
        /* ========== BOTONES ========== */
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

<!-- ========== HEADER ========== -->
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

<!-- ========== CONTENIDO ========== -->
<div class="container">
    
    <div class="card">
        <div class="card-header">
            <h1>
                <i class="fas fa-car"></i>
                Agregar Vehículo
            </h1>
            <p>Completa los datos del vehículo. En el siguiente paso agregarás SOAT y Tecnomecánica.</p>
        </div>
        
        <!-- Aviso si vienes de "Cancelar" en documentos.php -->
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
        
        <form method="POST" action="">
            <div class="form-grid">
                
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
                    <label for="tipo">Tipo <span class="obligatorio">*</span></label>
                    <select name="tipo" id="tipo" required>
                        <option value="">Selecciona...</option>
                        <option value="NUEVO" <?php echo $valores['tipo'] === 'NUEVO' ? 'selected' : ''; ?>>
                            🆕 NUEVO
                        </option>
                        <option value="TEST_DRIVE" <?php echo $valores['tipo'] === 'TEST_DRIVE' ? 'selected' : ''; ?>>
                            🚗 TEST DRIVE
                        </option>
                        <option value="USADO" <?php echo $valores['tipo'] === 'USADO' ? 'selected' : ''; ?>>
                            🔄 USADO
                        </option>
                    </select>
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
                
                <div class="form-campo">
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

</body>
</html>