<?php
// admin/operacion_vehicular/conductores/licencias.php
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
// ✅ MODO: TEMPORAL (nuevo) o EXISTENTE (editar)
// ============================================
$modo = 'temporal';
$conductor = null;
$licencias = [];
$conductor_id = 0;

// Escenario A: viene con ?id=X → conductor existente
if (isset($_GET['id']) && is_numeric($_GET['id']) && $_GET['id'] > 0) {
    $conductor_id = (int) $_GET['id'];
    $modo = 'existente';
    
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
        header('Location: index.php');
        exit();
    }
    
    $stmt = $pdo->prepare("
        SELECT id, categoria, fecha_vencimiento, activo, creado_el, 'bd' AS origen
        FROM ope_licencias
        WHERE conductor_id = ?
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

// Escenario B: viene sin ID → conductor temporal (en sesión)
} else {
    if (!isset($_SESSION['conductor_temp'])) {
        $_SESSION['error'] = "❌ No hay conductor en proceso. Selecciona uno primero.";
        header('Location: agregar.php');
        exit();
    }
    
    $temp = $_SESSION['conductor_temp'];
    
    $stmt = $pdo->prepare("
        SELECT id, nombre_completo, usuario, cargo, sede, perfil_usuario
        FROM usuarios
        WHERE id = ?
    ");
    $stmt->execute([$temp['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        unset($_SESSION['conductor_temp']);
        $_SESSION['error'] = "❌ Usuario no encontrado";
        header('Location: agregar.php');
        exit();
    }
    
    $conductor = [
        'id' => 0,
        'usuario_id' => $temp['usuario_id'],
        'nombre_completo' => $usuario['nombre_completo'],
        'usuario' => $usuario['usuario'],
        'cargo' => $usuario['cargo'],
        'sede' => $usuario['sede'],
        'perfil_usuario' => $usuario['perfil_usuario']
    ];
    
    $licencias = [];
    if (!empty($temp['licencias_temp'])) {
        foreach ($temp['licencias_temp'] as $index => $lic) {
            $licencias[] = [
                'id' => 'temp_' . $index,
                'categoria' => $lic['categoria'],
                'fecha_vencimiento' => $lic['fecha_vencimiento'],
                'activo' => 1,
                'creado_el' => $lic['creado_el'] ?? date('Y-m-d H:i:s'),
                'origen' => 'temp'
            ];
        }
    }
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

// --- AGREGAR LICENCIA ---
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
        $categorias_existentes = array_column($licencias, 'categoria');
        
        if (in_array($categoria, $categorias_existentes)) {
            $error = "❌ Este conductor ya tiene una licencia $categoria registrada";
        } else {
            if ($modo === 'temporal') {
                $_SESSION['conductor_temp']['licencias_temp'][] = [
                    'categoria' => $categoria,
                    'fecha_vencimiento' => $fecha_vencimiento,
                    'creado_el' => date('Y-m-d H:i:s')
                ];
                
                $_SESSION['mensaje'] = "✅ Licencia $categoria agregada (aún sin guardar)";
                header("Location: licencias.php");
                exit();
                
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO ope_licencias (conductor_id, categoria, fecha_vencimiento, activo, añadido_por, creado_el)
                        VALUES (?, ?, ?, 1, ?, NOW())
                    ");
                    $stmt->execute([$conductor_id, $categoria, $fecha_vencimiento, $_SESSION['usuario_id']]);
                    
                    $_SESSION['mensaje'] = "✅ Licencia $categoria agregada correctamente";
                    header("Location: licencias.php?id=" . $conductor_id);
                    exit();
                    
                } catch (PDOException $e) {
                    error_log("Error al agregar licencia: " . $e->getMessage());
                    $error = "❌ Error al agregar la licencia";
                }
            }
        }
    }
}

// --- ELIMINAR LICENCIA ---
if (isset($_GET['eliminar'])) {
    $eliminar = $_GET['eliminar'];
    
    if ($modo === 'temporal' && strpos($eliminar, 'temp_') === 0) {
        $index = (int) substr($eliminar, 5);
        
        if (isset($_SESSION['conductor_temp']['licencias_temp'][$index])) {
            $cat_eliminada = $_SESSION['conductor_temp']['licencias_temp'][$index]['categoria'];
            unset($_SESSION['conductor_temp']['licencias_temp'][$index]);
            $_SESSION['conductor_temp']['licencias_temp'] = array_values($_SESSION['conductor_temp']['licencias_temp']);
            
            $_SESSION['mensaje'] = "✅ Licencia $cat_eliminada eliminada";
            header("Location: licencias.php");
            exit();
        }
        
    } elseif ($modo === 'existente' && is_numeric($eliminar)) {
        $licencia_id = (int) $eliminar;
        
        $stmt = $pdo->prepare("SELECT id, categoria FROM ope_licencias WHERE id = ? AND conductor_id = ?");
        $stmt->execute([$licencia_id, $conductor_id]);
        $licencia = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($licencia) {
            $stmt = $pdo->prepare("DELETE FROM ope_licencias WHERE id = ?");
            $stmt->execute([$licencia_id]);
            
            $_SESSION['mensaje'] = "✅ Licencia {$licencia['categoria']} eliminada";
            header("Location: licencias.php?id=" . $conductor_id);
            exit();
        }
    }
}

// --- FINALIZAR (solo modo temporal) ---
if (isset($_GET['finalizar']) && $modo === 'temporal') {
    if (empty($_SESSION['conductor_temp']['licencias_temp'])) {
        $_SESSION['error'] = "❌ Debes agregar al menos 1 licencia antes de finalizar";
        header("Location: licencias.php");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        $temp = $_SESSION['conductor_temp'];
        $total_licencias = count($temp['licencias_temp']);
        
        // 1. Insertar conductor
        $stmt = $pdo->prepare("
            INSERT INTO ope_conductores (usuario_id, activo, añadido_por, fecha_agregado)
            VALUES (?, 1, ?, NOW())
        ");
        $stmt->execute([$temp['usuario_id'], $temp['añadido_por']]);
        $nuevo_conductor_id = $pdo->lastInsertId();
        
        // 2. Insertar todas las licencias
        $stmt = $pdo->prepare("
            INSERT INTO ope_licencias (conductor_id, categoria, fecha_vencimiento, activo, añadido_por, creado_el)
            VALUES (?, ?, ?, 1, ?, NOW())
        ");
        
        foreach ($temp['licencias_temp'] as $lic) {
            $stmt->execute([
                $nuevo_conductor_id,
                $lic['categoria'],
                $lic['fecha_vencimiento'],
                $temp['añadido_por']
            ]);
        }
        
        $pdo->commit();
        
        // Limpiar sesión temporal
        unset($_SESSION['conductor_temp']);
        
        // ✅ MENSAJE Y REDIRECCIÓN A agregar.php
        $_SESSION['mensaje'] = "✅ Conductor y $total_licencias licencia(s) guardados correctamente";
        header("Location: agregar.php");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al guardar conductor: " . $e->getMessage());
        $_SESSION['error'] = "❌ Error al guardar. Intenta de nuevo.";
        header("Location: licencias.php");
        exit();
    }
}

// --- VOLVER (solo modo temporal) ---
if (isset($_GET['volver']) && $modo === 'temporal') {
    unset($_SESSION['conductor_temp']);
    header("Location: agregar.php");
    exit();
}

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
    <title>Licencias | Operación Vehicular</title>
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
            justify-content: space-between;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
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
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">| Añadir Licencia</div>
        </div>
        <?php if ($modo === 'temporal'): ?>
            <a href="?volver=1" class="btn-volver-header">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        <?php else: ?>
            <a href="index.php" class="btn-volver-header">
                <i class="fas fa-arrow-left"></i> Volver al listado
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="container">
    
    <?php if ($modo === 'temporal'): ?>
        <div class="banner-temporal">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Modo temporal:</strong> el conductor no se guardará hasta que hagas clic en <strong>"Finalizar y guardar"</strong>. Si le das "Volver", no se guardará nada.
            </div>
        </div>
    <?php endif; ?>
    
    <div class="card">
        
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
        
        <div class="form-titulo">
            <i class="fas fa-plus-circle"></i>
            Agregar licencia
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
                            <?php echo $cat; ?><?php echo $usada ? ' (ya registrada)' : ''; ?>
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
        
        <div class="lista-titulo">
            <i class="fas fa-id-card"></i>
            Licencias registradas
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
                        <!-- ✅ SIN confirm() -->
                        <a 
                            href="?<?php echo $modo === 'temporal' ? '' : 'id=' . $conductor_id . '&'; ?>eliminar=<?php echo $lic['id']; ?>" 
                            class="btn-eliminar"
                            title="Eliminar licencia"
                        >
                            <i class="fas fa-trash"></i>
                        </a>
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
        
        <?php if ($modo === 'temporal'): ?>
            <div class="footer-acciones">
                <!-- ✅ SIN confirm() -->
                <a href="?volver=1" class="btn-cancelar">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                
                <?php if (count($licencias) > 0): ?>
                    <a href="?finalizar=1" class="btn-finalizar">
                        <i class="fas fa-check-circle"></i> Finalizar y guardar
                    </a>
                <?php else: ?>
                    <span class="btn-finalizar deshabilitado" title="Agrega al menos 1 licencia primero">
                        <i class="fas fa-check-circle"></i> Finalizar y guardar
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="footer-acciones">
                <div></div>
                <a href="../index.php" class="btn-finalizar">
                    <i class="fas fa-check-circle"></i> Volver al listado
                </a>
            </div>
        <?php endif; ?>
        
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