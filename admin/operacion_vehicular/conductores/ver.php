<?php
// admin/operacion_vehicular/conductores/ver.php
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
$conductor_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($conductor_id <= 0) {
    $_SESSION['error'] = "❌ Conductor no especificado";
    header('Location: ../index.php');
    exit();
}

// ============================================
// ✅ OBTENER CONDUCTOR + DATOS DEL USUARIO
// ============================================
$stmt = $pdo->prepare("
    SELECT c.id, c.usuario_id, c.activo, c.fecha_agregado,
           u.nombre_completo, u.usuario, u.email, u.telefono,
           u.cargo, u.sede, u.perfil_usuario, u.cod_asesor
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
// ✅ OBTENER LICENCIAS DEL CONDUCTOR
// Activas primero, luego históricas. Dentro de cada grupo: por categoría
// ============================================
$stmt = $pdo->prepare("
    SELECT id, categoria, fecha_vencimiento, activo, creado_el
    FROM ope_licencias
    WHERE conductor_id = ?
    ORDER BY 
        activo DESC,
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
        END ASC,
        fecha_vencimiento DESC
");
$stmt->execute([$conductor_id]);
$licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ OBTENER TOTAL DE RUTAS DEL CONDUCTOR
// ============================================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ope_ruta WHERE conductor_id = ?");
$stmt->execute([$conductor_id]);
$total_rutas = $stmt->fetchColumn();

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
        return ['clase' => 'vencido', 'texto' => "Vencida hace " . abs($dias) . " días"];
    } elseif ($dias <= 30) {
        return ['clase' => 'por-vencer', 'texto' => "Vence en $dias días"];
    } else {
        return ['clase' => 'vigente', 'texto' => "Vence el " . date('d/m/Y', strtotime($fecha_vencimiento))];
    }
}

// ============================================
// ✅ INICIALES PARA EL AVATAR
// ============================================
$partes = explode(' ', trim($conductor['nombre_completo']));
if (count($partes) >= 2) {
    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
} else {
    $iniciales = strtoupper(substr($conductor['nombre_completo'], 0, 2));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($conductor['nombre_completo']); ?> | Operación Vehicular</title>
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
            margin-bottom: 20px;
        }
        
        /* ========== ENCABEZADO DEL CONDUCTOR ========== */
        .conductor-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .conductor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #445960);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 600;
            flex-shrink: 0;
            letter-spacing: 1px;
        }
        
        .conductor-info { flex: 1; min-width: 0; }
        
        .conductor-nombre {
            font-size: 26px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 6px;
        }
        
        .badge-activo {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #d5f5e3;
            color: #1a7a3a;
            margin-top: 8px;
        }
        
        .badge-inactivo {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #fadbd8;
            color: #922b21;
            margin-top: 8px;
        }
        
        /* ========== SECCIONES ========== */
        .seccion-titulo {
            font-size: 16px;
            color: #12232b;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .seccion-titulo i { color: #173742; }
        
        .seccion-titulo .badge-count {
            background: #173742;
            color: white;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* ========== GRID DE DATOS ========== */
        .datos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .dato-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 3px solid #173742;
        }
        
        .dato-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #173742;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .dato-content { flex: 1; min-width: 0; }
        
        .dato-label {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .dato-valor {
            font-size: 14px;
            color: #12232b;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* ========== LISTA LICENCIAS ========== */
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
        
        .licencia-item.vigente { border-left-color: #27ae60; }
        .licencia-item.por-vencer { border-left-color: #f39c12; }
        .licencia-item.vencido { border-left-color: #e74c3c; }
        .licencia-item.historica { 
            border-left-color: #bdc3c7; 
            background: #f0f0f0;
            opacity: 0.75;
        }
        
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
        
        .licencia-categoria.historica {
            background: #95a5a6;
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
        .licencia-estado.historica { background: #d5dbdb; color: #566573; }
        
        /* ========== EMPTY STATE ========== */
        .empty-licencias {
            text-align: center;
            padding: 30px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #7f8c8d;
        }
        
        .empty-licencias i {
            font-size: 36px;
            color: #d3d3d3;
            margin-bottom: 12px;
        }
        
        .empty-licencias h3 {
            color: #2c3e50;
            font-size: 15px;
            margin-bottom: 6px;
        }
        
        .empty-licencias p { font-size: 13px; }
        
        /* ========== BOTONES FINALES ========== */
        .footer-acciones {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .btn-accion {
            padding: 12px 22px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-rutas {
            background: #3498db;
            color: white;
        }
        
        .btn-rutas:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52,152,219,0.3);
        }
        
        .btn-editar {
            background: #f39c12;
            color: white;
        }
        
        .btn-editar:hover {
            background: #e67e22;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(243,156,18,0.3);
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
        
        <!-- ===== ENCABEZADO DEL CONDUCTOR ===== -->
        <div class="conductor-header">
            <div class="conductor-avatar">
                <?php echo $iniciales; ?>
            </div>
            <div class="conductor-info">
                <div class="conductor-nombre">
                    <?php echo htmlspecialchars($conductor['nombre_completo']); ?>
                </div>
                <span class="<?php echo $conductor['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                    <i class="fas <?php echo $conductor['activo'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <?php echo $conductor['activo'] ? 'Activo' : 'Inactivo'; ?>
                </span>
            </div>
        </div>
        
        <!-- ===== INFORMACIÓN PERSONAL ===== -->
        <div class="seccion-titulo">
            <i class="fas fa-user-circle"></i>
            Información personal
        </div>
        
        <div class="datos-grid">
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Nombre completo</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($conductor['nombre_completo']); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Teléfono</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($conductor['telefono'] ?: '—'); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Cargo</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($conductor['cargo'] ?: '—'); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Sede</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($conductor['sede'] ?: '—'); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-id-badge"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Perfil</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($conductor['perfil_usuario'] ?: '—'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- ===== LICENCIAS ===== -->
        <div class="seccion-titulo">
            <i class="fas fa-id-card"></i>
            Licencias de conducción
            <span class="badge-count"><?php echo count($licencias); ?></span>
        </div>
        
        <?php if (count($licencias) > 0): ?>
            <div class="licencias-lista">
                <?php foreach ($licencias as $lic): 
                    $es_activa = ($lic['activo'] == 1);
                    
                    if ($es_activa) {
                        // Licencia activa: calcular semáforo normal
                        $estado = calcularEstadoVencimiento($lic['fecha_vencimiento']);
                        $clase = $estado['clase'];
                        $texto = $estado['texto'];
                    } else {
                        // Licencia histórica
                        $clase = 'historica';
                        $texto = '📁 Histórica';
                    }
                ?>
                    <div class="licencia-item <?php echo $clase; ?>">
                        <div class="licencia-info">
                            <div class="licencia-categoria <?php echo $clase === 'historica' ? 'historica' : ''; ?>">
                                <?php echo htmlspecialchars($lic['categoria']); ?>
                            </div>
                            <div class="licencia-fecha">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y', strtotime($lic['fecha_vencimiento'])); ?>
                            </div>
                            <span class="licencia-estado <?php echo $clase; ?>">
                                <?php echo $texto; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-licencias">
                <i class="fas fa-id-card"></i>
                <h3>Sin licencias registradas</h3>
                <p>Este conductor no tiene licencias de conducción asociadas.</p>
            </div>
        <?php endif; ?>
        
        <!-- ===== BOTONES ===== -->
        <div class="footer-acciones">
            <a href="rutas.php?id=<?php echo $conductor['id']; ?>" class="btn-accion btn-rutas">
                <i class="fas fa-route"></i> Ver rutas (<?php echo $total_rutas; ?>)
            </a>
            
            <a href="editar.php?id=<?php echo $conductor['id']; ?>" class="btn-accion btn-editar">
                <i class="fas fa-edit"></i> Editar licencias
            </a>
        </div>
        
    </div>
</div>

</body>
</html>