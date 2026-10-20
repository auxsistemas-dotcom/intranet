<?php
// admin/operacion_vehicular/vehiculos/ver.php
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
// ✅ OBTENER VEHÍCULO
// ============================================
$stmt = $pdo->prepare("
    SELECT v.id, v.placa, v.marca, v.modelo, v.referencia,
           v.fecha_matricula, v.tipo, v.activo, v.creado_el, v.añadido_por,
           u.nombre_completo AS agregado_por_nombre
    FROM ope_vehiculos v
    LEFT JOIN usuarios u ON v.añadido_por = u.id
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
// ✅ OBTENER DOCUMENTOS DEL VEHÍCULO (activos + históricos)
// ============================================
$stmt = $pdo->prepare("
    SELECT id, tipo_documento, fecha_expedicion, fecha_vencimiento, activo, creado_el
    FROM ope_documentos_vh
    WHERE vehiculo_id = ?
    ORDER BY 
        activo DESC,
        tipo_documento ASC,
        fecha_vencimiento DESC
");
$stmt->execute([$vehiculo_id]);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ✅ OBTENER TOTAL DE RUTAS DEL VEHÍCULO
// ============================================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ope_ruta WHERE vehiculo_id = ?");
$stmt->execute([$vehiculo_id]);
$total_rutas = $stmt->fetchColumn();

// ============================================
// ✅ CALCULAR AÑOS DEL VEHÍCULO
// ============================================
$fecha_matricula = new DateTime($vehiculo['fecha_matricula']);
$hoy = new DateTime();
$hoy->setTime(0, 0, 0);
$anios_vehiculo = (int) $fecha_matricula->diff($hoy)->y;
$requiere_tecno = ($anios_vehiculo >= 5);

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
        return ['clase' => 'vencido', 'texto' => "Vencido hace " . abs($dias) . " días"];
    } elseif ($dias <= 30) {
        return ['clase' => 'por-vencer', 'texto' => "Vence en $dias días"];
    } else {
        return ['clase' => 'vigente', 'texto' => "Vence el " . date('d/m/Y', strtotime($fecha_vencimiento))];
    }
}

// ============================================
// ✅ ETIQUETA DEL TIPO CON EMOJI
// ============================================
function etiquetaTipo($tipo) {
    $mapa = [
        'NUEVO' => '🆕 NUEVO',
        'TEST_DRIVE' => '🚗 TEST DRIVE',
        'USADO' => '🔄 USADO'
    ];
    return $mapa[$tipo] ?? $tipo;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vehiculo['placa']); ?> | Operación Vehicular</title>
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
        
        /* ========== ENCABEZADO DEL VEHÍCULO ========== */
        .vehiculo-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .vehiculo-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #445960);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            flex-shrink: 0;
        }
        
        .vehiculo-info { flex: 1; min-width: 0; }
        
        .vehiculo-placa {
            font-size: 28px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        
        .vehiculo-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .badge-tipo {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #173742;
            color: white;
        }
        
        .badge-activo {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #d5f5e3;
            color: #1a7a3a;
        }
        
        .badge-inactivo {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #fadbd8;
            color: #922b21;
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
        
        /* ========== LISTA DOCUMENTOS ========== */
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
        .documento-item.historico { 
            border-left-color: #bdc3c7;
            background: #f0f0f0;
            opacity: 0.75;
        }
        
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
        
        .documento-tipo.historico {
            background: #95a5a6;
        }
        
        .documento-fecha {
            font-size: 14px;
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
        .documento-estado.historico { background: #d5dbdb; color: #566573; }
        
        /* ========== ALERTA TECNO ========== */
        .alerta-tecno {
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
        
        .alerta-tecno i {
            color: #3498db;
            font-size: 16px;
            margin-top: 2px;
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-documentos {
            text-align: center;
            padding: 30px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #7f8c8d;
        }
        
        .empty-documentos i {
            font-size: 36px;
            color: #d3d3d3;
            margin-bottom: 12px;
        }
        
        .empty-documentos h3 {
            color: #2c3e50;
            font-size: 15px;
            margin-bottom: 6px;
        }
        
        .empty-documentos p { font-size: 13px; }
        
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
        
        .btn-volver-lista {
            background: #7f8c8d;
            color: white;
        }
        
        .btn-volver-lista:hover {
            background: #95a5a6;
            transform: translateY(-2px);
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
        
        <!-- ===== ENCABEZADO DEL VEHÍCULO ===== -->
        <div class="vehiculo-header">
            <div class="vehiculo-avatar">
                <i class="fas fa-car"></i>
            </div>
            <div class="vehiculo-info">
                <div class="vehiculo-placa">
                    <?php echo htmlspecialchars($vehiculo['placa']); ?>
                </div>
                <div class="vehiculo-meta">
                    <span class="badge-tipo"><?php echo etiquetaTipo($vehiculo['tipo']); ?></span>
                    <span class="<?php echo $vehiculo['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                        <i class="fas <?php echo $vehiculo['activo'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                        <?php echo $vehiculo['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- ===== INFORMACIÓN DEL VEHÍCULO ===== -->
        <div class="seccion-titulo">
            <i class="fas fa-info-circle"></i>
            Información del vehículo
        </div>
        
        <div class="datos-grid">
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-hashtag"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Placa</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($vehiculo['placa']); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Marca</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($vehiculo['marca']); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Modelo (año)</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($vehiculo['modelo']); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Referencia</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($vehiculo['referencia']); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Fecha de matrícula</div>
                    <div class="dato-valor"><?php echo date('d/m/Y', strtotime($vehiculo['fecha_matricula'])); ?></div>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="dato-content">
                    <div class="dato-label">Años de antigüedad</div>
                    <div class="dato-valor"><?php echo $anios_vehiculo; ?> año(s)</div>
                </div>
            </div>
        </div>
        
        <!-- ===== ALERTA TECNO ===== -->
        <?php if (!$requiere_tecno): ?>
            <div class="alerta-tecno">
                <i class="fas fa-info-circle"></i>
                <div>
                    Este vehículo tiene <strong><?php echo $anios_vehiculo; ?> año(s)</strong> (menos de 5). 
                    <strong>No requiere Tecnomecánica</strong>. Solo se requiere SOAT.
                </div>
            </div>
        <?php else: ?>
            <div class="alerta-tecno" style="background: #fef3c7; border-color: #f39c12; color: #92400e;">
                <i class="fas fa-exclamation-triangle" style="color: #f39c12;"></i>
                <div>
                    Este vehículo tiene <strong><?php echo $anios_vehiculo; ?> años</strong> (5+). 
                    <strong>Requiere SOAT y Tecnomecánica</strong> obligatoriamente.
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ===== DOCUMENTOS ===== -->
        <div class="seccion-titulo">
            <i class="fas fa-file-alt"></i>
            Documentos
            <span class="badge-count"><?php echo count($documentos); ?></span>
        </div>
        
        <?php if (count($documentos) > 0): ?>
            <div class="documentos-lista">
                <?php foreach ($documentos as $doc): 
                    $es_activo = ($doc['activo'] == 1);
                    $es_tecno = ($doc['tipo_documento'] === 'TECNOMECANICA');
                    
                    if ($es_activo) {
                        $estado = calcularEstadoVencimiento($doc['fecha_vencimiento']);
                        $clase = $estado['clase'];
                        $texto = $estado['texto'];
                    } else {
                        $clase = 'historico';
                        $texto = '📁 Histórico';
                    }
                ?>
                    <div class="documento-item <?php echo $clase; ?>">
                        <div class="documento-info">
                            <div class="documento-tipo <?php echo $es_tecno ? 'tecno' : ''; ?> <?php echo $clase === 'historico' ? 'historico' : ''; ?>">
                                <?php echo $es_tecno ? 'TECNOMECÁNICA' : 'SOAT'; ?>
                            </div>
                            <div class="documento-fecha">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y', strtotime($doc['fecha_vencimiento'])); ?>
                            </div>
                            <span class="documento-estado <?php echo $clase; ?>">
                                <?php echo $texto; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-documentos">
                <i class="fas fa-file-alt"></i>
                <h3>Sin documentos registrados</h3>
                <p>Este vehículo no tiene documentos asociados.</p>
            </div>
        <?php endif; ?>
        
        <!-- ===== BOTONES ===== -->
        <div class="footer-acciones">
            <a href="../index.php" class="btn-accion btn-volver-lista">
                <i class="fas fa-arrow-left"></i> Volver al listado
            </a>
            
            <a href="rutas.php?id=<?php echo $vehiculo['id']; ?>" class="btn-accion btn-rutas">
                <i class="fas fa-route"></i> Ver rutas (<?php echo $total_rutas; ?>)
            </a>
            
            <a href="editar.php?id=<?php echo $vehiculo['id']; ?>" class="btn-accion btn-editar">
                <i class="fas fa-edit"></i> Editar documentos
            </a>
        </div>
        
    </div>
</div>

</body>
</html>