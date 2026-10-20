<?php
// admin/operacion_vehicular/rutas/ver.php - Detalle completo de una ruta
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

$usuario_id = $_SESSION['usuario_id'];

// ============================================
// ✅ PROCESAR ELIMINAR RUTA
// ============================================
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $ruta_id_eliminar = (int) $_GET['eliminar'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM ope_ruta WHERE id = ?");
        $stmt->execute([$ruta_id_eliminar]);
        
        $_SESSION['mensaje'] = "✅ Ruta eliminada correctamente";
        header('Location: ../rutas.php');
        exit();
        
    } catch (PDOException $e) {
        error_log("Error al eliminar ruta: " . $e->getMessage());
        $_SESSION['error'] = "❌ Error al eliminar la ruta";
        header('Location: ../rutas.php');
        exit();
    }
}

// ============================================
// ✅ VALIDAR ID
// ============================================
$ruta_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($ruta_id <= 0) {
    $_SESSION['error'] = "❌ Ruta no especificada";
    header('Location: ../rutas.php');
    exit();
}

// ============================================
// ✅ OBTENER RUTA
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        v.placa, v.marca, v.modelo, v.referencia, v.tipo AS tipo_vh,
        v.id AS vehiculo_id,
        c.id AS conductor_id,
        u.nombre_completo AS conductor_nombre,
        u.usuario AS conductor_usuario,
        u.cargo AS conductor_cargo,
        u.sede AS conductor_sede
    FROM ope_ruta r
    LEFT JOIN ope_vehiculos v ON r.vehiculo_id = v.id
    LEFT JOIN ope_conductores c ON r.conductor_id = c.id
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    WHERE r.id = ?
");
$stmt->execute([$ruta_id]);
$ruta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ruta) {
    $_SESSION['error'] = "❌ Ruta no encontrada";
    header('Location: ../rutas.php');
    exit();
}

// ============================================
// ✅ MENSAJES FLASH
// ============================================
$mensaje = '';
$error = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ============================================
// ✅ HELPERS
// ============================================
function badgeEstado($estado) {
    $mapa = [
        'EN_CURSO' => ['clase' => 'en-curso', 'texto' => '🟡 En Curso'],
        'FINALIZADO' => ['clase' => 'finalizado', 'texto' => '🟢 Finalizada'],
        'CANCELADO' => ['clase' => 'cancelado', 'texto' => '🔴 Cancelada']
    ];
    return $mapa[$estado] ?? ['clase' => 'sin-datos', 'texto' => $estado];
}

function badgeTipo($tipo) {
    $mapa = [
        'NUEVO' => ['clase' => 'nuevo', 'texto' => 'NUEVO'],
        'TEST_DRIVE' => ['clase' => 'test', 'texto' => 'TEST DRIVE'],
        'USADO' => ['clase' => 'usado', 'texto' => 'USADO']
    ];
    return $mapa[$tipo] ?? ['clase' => 'sin-datos', 'texto' => $tipo];
}

function calcularDuracion($fecha_salida, $fecha_finalizacion) {
    if (empty($fecha_salida) || empty($fecha_finalizacion)) {
        return null;
    }
    
    $salida = new DateTime($fecha_salida);
    $fin = new DateTime($fecha_finalizacion);
    $diff = $salida->diff($fin);
    
    $partes = [];
    if ($diff->days > 0) {
        $partes[] = $diff->days . ' día' . ($diff->days != 1 ? 's' : '');
    }
    if ($diff->h > 0) {
        $partes[] = $diff->h . ' hora' . ($diff->h != 1 ? 's' : '');
    }
    if ($diff->i > 0) {
        $partes[] = $diff->i . ' min';
    }
    
    return empty($partes) ? 'Menos de 1 min' : implode(', ', $partes);
}

function textoMedioTransporte($medio, $otro = null) {
    $mapa = [
        'PUBLICO' => '🚌 Transporte público',
        'PROPIO' => '🚗 Vehículo propio',
        'MOTO' => '🏍️ Motocicleta',
        'ORGANIZACION' => '🚙 Vehículo de la organización',
        'OTRO' => '📝 Otro'
    ];
    $texto = $mapa[$medio] ?? $medio;
    if ($medio === 'OTRO' && !empty($otro)) {
        $texto .= ': ' . $otro;
    }
    return $texto;
}

function textoSiNo($valor) {
    if ($valor == 1) return '✅ Sí';
    if ($valor == 0) return '❌ No';
    return '—';
}

// ============================================
// ✅ DECODIFICAR CHECKLIST (JSON)
// ============================================
$checklist_items = [
    'llantas' => '🛞 Estado de llantas',
    'interna' => '🔧 Revisión interna (aceite, frenos, refrigerante, batería, filtros)',
    'externa' => '🚗 Revisión externa (lámina y pintura)',
    'mantenimientos' => '📋 Mantenimientos al día',
    'luces' => '💡 Luces y pito',
    'kit' => '🧰 Kit de carretera'
];

$checklist_respuestas = [];
if (!empty($ruta['checklist_vh'])) {
    $checklist_respuestas = json_decode($ruta['checklist_vh'], true) ?: [];
}

// ============================================
// ✅ PREGUNTAS DE LA ENCUESTA
// ============================================
$preguntas_encuesta = [
    'autorizado_jefe' => [
        'numero' => '6',
        'texto' => '¿Su viaje está programado y autorizado por su jefe inmediato?',
        'tipo' => 'si_no_ok'
    ],
    'conoce_codigo' => [
        'numero' => '11',
        'texto' => '¿Conoce el Código Nacional de Tránsito?',
        'tipo' => 'si_no_ok'
    ],
    'salud_estable' => [
        'numero' => '12',
        'texto' => '¿Se encuentra en condiciones de salud estables para conducir un vehículo?',
        'tipo' => 'si_no_ok'
    ],
    'descanso_optimo' => [
        'numero' => '13',
        'texto' => '¿Tuvo óptimas condiciones de descanso?',
        'tipo' => 'si_no_ok'
    ],
    'emocional_estable' => [
        'numero' => '14',
        'texto' => '¿Se encuentra emocionalmente estable para conducir?',
        'tipo' => 'si_no_ok'
    ],
    'alcohol_24h' => [
        'numero' => '15',
        'texto' => '¿Consumió bebidas alcohólicas en las últimas 24 horas?',
        'tipo' => 'si_no_mal'
    ],
    'sustancias_24h' => [
        'numero' => '16',
        'texto' => '¿Consumió algún tipo de sustancias psicoactivas en las últimas 24 horas?',
        'tipo' => 'si_no_mal'
    ],
    'medicamentos' => [
        'numero' => '17',
        'texto' => '¿Tomó algún tipo de medicamento que afecte sus condiciones de salud?',
        'tipo' => 'si_no_mal'
    ]
];

$estado_info = badgeEstado($ruta['estado']);
$tipo_info = badgeTipo($ruta['tipo_vh'] ?? null);
$duracion = calcularDuracion($ruta['fecha_salida'], $ruta['fecha_finalizacion']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruta <?php echo $ruta['id']; ?> | Operación Vehicular</title>
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
        
        .header-botones {
            display: flex;
            gap: 10px;
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
        
        .btn-eliminar {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }
        
        .btn-eliminar:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        /* ========== MENSAJES ========== */
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
        
        /* ========== CARD ========== */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        /* ========== ENCABEZADO RUTA ========== */
        .ruta-header-card {
            border-left: 6px solid #e67e22;
        }
        
        .ruta-header-card.estado-en-curso { border-left-color: #f39c12; }
        .ruta-header-card.estado-finalizado { border-left-color: #27ae60; }
        .ruta-header-card.estado-cancelado { border-left-color: #e74c3c; }
        
        .ruta-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .ruta-titulo h1 {
            font-size: 24px;
            color: #12232b;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .ruta-titulo .badge-tipo {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .badge-tipo.nuevo { background: #27ae60; color: white; }
        .badge-tipo.test { background: #8e44ad; color: white; }
        .badge-tipo.usado { background: #3498db; color: white; }
        .badge-tipo.sin-datos { background: #bdc3c7; color: white; }
        
        .ruta-titulo .ruta-subtitulo {
            font-size: 14px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge-estado {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-estado.en-curso { background: #fdebd0; color: #a04000; border: 1px solid #f39c12; }
        .badge-estado.finalizado { background: #d5f5e3; color: #1a7a3a; border: 1px solid #27ae60; }
        .badge-estado.cancelado { background: #fadbd8; color: #922b21; border: 1px solid #e74c3c; }
        
        /* ========== TIMELINE DE FECHAS ========== */
        .timeline-fechas {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 200px;
        }
        
        .timeline-item .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            flex-shrink: 0;
        }
        
        .timeline-item .timeline-icon.salida { background: #3498db; }
        .timeline-item .timeline-icon.regreso { background: #95a5a6; }
        .timeline-item .timeline-icon.fin { background: #27ae60; }
        
        .timeline-item .timeline-content {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .timeline-item .timeline-label {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .timeline-item .timeline-value {
            font-size: 14px;
            color: #12232b;
            font-weight: 600;
        }
        
        .timeline-item .timeline-value.vacio {
            color: #bdc3c7;
            font-style: italic;
            font-weight: 400;
        }
        
        .duracion-badge {
            background: linear-gradient(135deg, #e8f4f8, #d6eaf8);
            color: #1a5276;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        /* ========== TÍTULOS DE SECCIÓN ========== */
        .seccion-titulo {
            font-size: 16px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .seccion-titulo i { color: #173742; }
        
        /* ========== DATOS GRID ========== */
        .datos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        
        .dato-item {
            padding: 12px 14px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #173742;
        }
        
        .dato-label {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .dato-valor {
            font-size: 14px;
            color: #12232b;
            font-weight: 500;
            word-break: break-word;
        }
        
        .dato-valor.vacio {
            color: #bdc3c7;
            font-style: italic;
            font-weight: 400;
        }
        
        /* ========== DESCRIPCIÓN ========== */
        .descripcion-box {
            padding: 15px;
            background: #fafafa;
            border-radius: 10px;
            border: 1px solid #f0f0f0;
            font-size: 14px;
            line-height: 1.7;
            color: #2c3e50;
            white-space: pre-wrap;
        }
        
        /* ========== CHECKLIST ========== */
        .checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 10px;
        }
        
        .checklist-item {
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
        
        .checklist-item.completo {
            background: #d5f5e3;
            border-left: 3px solid #27ae60;
            color: #1a7a3a;
        }
        
        .checklist-item.pendiente {
            background: #fadbd8;
            border-left: 3px solid #e74c3c;
            color: #922b21;
        }
        
        .checklist-item i {
            font-size: 14px;
        }
        
        /* ========== ENCUESTA ========== */
        .encuesta-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .encuesta-item {
            padding: 14px 16px;
            border-radius: 10px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            border-left: 4px solid #d3d3d3;
        }
        
        .encuesta-item.ok {
            border-left-color: #27ae60;
            background: #f0fdf4;
        }
        
        .encuesta-item.mal {
            border-left-color: #e74c3c;
            background: #fef5f5;
        }
        
        .encuesta-item .pregunta {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            flex: 1;
            min-width: 250px;
        }
        
        .encuesta-item .pregunta-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #173742;
            color: white;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 1px;
        }
        
        .encuesta-item .pregunta-texto {
            font-size: 13px;
            color: #2c3e50;
            line-height: 1.5;
        }
        
        .encuesta-item .respuesta {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        
        .encuesta-item .respuesta.ok {
            background: #27ae60;
            color: white;
        }
        
        .encuesta-item .respuesta.mal {
            background: #e74c3c;
            color: white;
        }
        
        .encuesta-item .respuesta.neutro {
            background: #ecf0f1;
            color: #7f8c8d;
        }
        
        /* ========== OBSERVACIONES ========== */
        .observaciones-box {
            padding: 15px 18px;
            background: #fef3c7;
            border-left: 4px solid #f39c12;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.7;
            color: #92400e;
            white-space: pre-wrap;
        }
        
        /* ========== BOTONES ACCIÓN ========== */
        .acciones-seccion {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .acciones-seccion .acciones-derecha {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-accion {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-ver-conductor {
            background: #3498db;
            color: white;
        }
        
        .btn-ver-conductor:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-ver-vehiculo {
            background: #8e44ad;
            color: white;
        }
        
        .btn-ver-vehiculo:hover {
            background: #732d91;
            transform: translateY(-2px);
        }
        
        /* ========== MODAL ELIMINAR ========== */
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
        }
        
        .modal-overlay.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 35px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fadbd8;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .modal-icon i { font-size: 38px; color: #e74c3c; }
        
        .modal-title {
            font-size: 22px;
            color: #12232b;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .modal-message {
            font-size: 15px;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .modal-message strong { color: #12232b; }
        
        .modal-warning {
            background: #fef3c7;
            color: #92400e;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
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
            text-decoration: none;
            min-width: 130px;
            justify-content: center;
        }
        
        .btn-modal-cancelar { background: #ecf0f1; color: #2c3e50; }
        .btn-modal-cancelar:hover { background: #d3d3d3; transform: translateY(-2px); }
        
        .btn-modal-eliminar { background: #e74c3c; color: white; }
        .btn-modal-eliminar:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231,76,60,0.4);
        }
    </style>
</head>
<body>

<!-- ========== HEADER ========== -->
<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">Operación Vehicular | Detalle de Ruta</div>
        </div>
        <div class="header-botones">
            <a href="rutas.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver a rutas
            </a>
            <button type="button" class="btn-eliminar" onclick="abrirModalEliminar()">
                <i class="fas fa-trash"></i> Eliminar ruta
            </button>
        </div>
    </div>
</header>

<div class="container">
    
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
    
    <!-- ===== ENCABEZADO RUTA ===== -->
    <div class="card ruta-header-card estado-<?php echo strtolower(str_replace('_', '-', $ruta['estado'])); ?>">
        <div class="ruta-header-top">
            <div class="ruta-titulo">
                <h1>
                    Ruta #<?php echo $ruta['id']; ?>
                    <?php if (!empty($ruta['placa'])): ?>
                        <span class="badge-tipo <?php echo $tipo_info['clase']; ?>">
                            <?php echo $tipo_info['texto']; ?>
                        </span>
                    <?php endif; ?>
                </h1>
                <?php if (!empty($ruta['ciudad_origen']) || !empty($ruta['ciudad_destino'])): ?>
                    <div class="ruta-subtitulo">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($ruta['ciudad_origen'] ?? '—'); ?>
                        <i class="fas fa-arrow-right" style="font-size: 10px;"></i>
                        <?php echo htmlspecialchars($ruta['ciudad_destino'] ?? '—'); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge-estado <?php echo $estado_info['clase']; ?>">
                    <?php echo $estado_info['texto']; ?>
                </span>
            </div>
        </div>
        
        <!-- Timeline de fechas -->
        <div class="timeline-fechas">
            <div class="timeline-item">
                <div class="timeline-icon salida">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="timeline-content">
                    <span class="timeline-label">Fecha de salida</span>
                    <span class="timeline-value">
                        <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_salida'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="timeline-item">
                <div class="timeline-icon regreso">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="timeline-content">
                    <span class="timeline-label">Regreso estimado</span>
                    <?php if (!empty($ruta['fecha_regreso'])): ?>
                        <span class="timeline-value">
                            <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_regreso'])); ?>
                        </span>
                    <?php else: ?>
                        <span class="timeline-value vacio">No definido</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="timeline-item">
                <div class="timeline-icon fin">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="timeline-content">
                    <span class="timeline-label">Fecha de finalización</span>
                    <?php if (!empty($ruta['fecha_finalizacion'])): ?>
                        <span class="timeline-value">
                            <?php echo date('d/m/Y H:i', strtotime($ruta['fecha_finalizacion'])); ?>
                        </span>
                    <?php else: ?>
                        <span class="timeline-value vacio">Aún en curso</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($duracion): ?>
                <div class="duracion-badge">
                    <i class="fas fa-hourglass-half"></i>
                    Duración: <?php echo $duracion; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ===== DATOS DEL VIAJE ===== -->
    <div class="card">
        <div class="seccion-titulo">
            <i class="fas fa-route"></i>
            Datos del viaje
        </div>
        
        <div class="datos-grid">
            <div class="dato-item">
                <div class="dato-label">Motivo del viaje</div>
                <div class="dato-value <?php echo empty($ruta['motivo_viaje']) ? 'vacio' : ''; ?>">
                    <?php echo htmlspecialchars($ruta['motivo_viaje'] ?: 'No especificado'); ?>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-label">Ciudad origen</div>
                <div class="dato-valor <?php echo empty($ruta['ciudad_origen']) ? 'vacio' : ''; ?>">
                    <?php echo htmlspecialchars($ruta['ciudad_origen'] ?: 'No especificada'); ?>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-label">Ciudad destino</div>
                <div class="dato-valor <?php echo empty($ruta['ciudad_destino']) ? 'vacio' : ''; ?>">
                    <?php echo htmlspecialchars($ruta['ciudad_destino'] ?: 'No especificada'); ?>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-label">Medio de transporte</div>
                <div class="dato-valor">
                    <?php echo textoMedioTransporte($ruta['medio_transporte'], $ruta['medio_transporte_otro']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ===== DATOS DEL CONDUCTOR ===== -->
    <div class="card">
        <div class="seccion-titulo">
            <i class="fas fa-user-tie"></i>
            Datos del conductor
        </div>
        
        <div class="datos-grid">
            <div class="dato-item">
                <div class="dato-label">Nombre completo</div>
                <div class="dato-valor">
                    <?php echo htmlspecialchars($ruta['conductor_nombre'] ?? '—'); ?>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-label">Documento (registrado en la ruta)</div>
                <div class="dato-valor <?php echo empty($ruta['documento_conductor']) ? 'vacio' : ''; ?>">
                    <?php echo htmlspecialchars($ruta['documento_conductor'] ?: 'No registrado'); ?>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-label">Cargo</div>
                <div class="dato-valor <?php echo empty($ruta['conductor_cargo']) ? 'vacio' : ''; ?>">
                    <?php echo htmlspecialchars($ruta['conductor_cargo'] ?: '—'); ?>
                </div>
            </div>
            
            <div class="dato-item">
                <div class="dato-label">Sede</div>
                <div class="dato-valor <?php echo empty($ruta['conductor_sede']) ? 'vacio' : ''; ?>">
                    <?php echo htmlspecialchars($ruta['conductor_sede'] ?: '—'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ===== DATOS DEL VEHÍCULO ===== -->
    <div class="card">
        <div class="seccion-titulo">
            <i class="fas fa-car"></i>
            Datos del vehículo
        </div>
        
        <?php if (!empty($ruta['placa'])): ?>
            <div class="datos-grid">
                <div class="dato-item">
                    <div class="dato-label">Placa</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($ruta['placa']); ?></div>
                </div>
                
                <div class="dato-item">
                    <div class="dato-label">Marca</div>
                    <div class="dato-valor <?php echo empty($ruta['marca']) ? 'vacio' : ''; ?>">
                        <?php echo htmlspecialchars($ruta['marca'] ?: '—'); ?>
                    </div>
                </div>
                
                <div class="dato-item">
                    <div class="dato-label">Modelo</div>
                    <div class="dato-valor <?php echo empty($ruta['modelo']) ? 'vacio' : ''; ?>">
                        <?php echo htmlspecialchars($ruta['modelo'] ?: '—'); ?>
                    </div>
                </div>
                
                <div class="dato-item">
                    <div class="dato-label">Referencia</div>
                    <div class="dato-valor <?php echo empty($ruta['referencia']) ? 'vacio' : ''; ?>">
                        <?php echo htmlspecialchars($ruta['referencia'] ?: '—'); ?>
                    </div>
                </div>
                
                <div class="dato-item">
                    <div class="dato-label">Tipo</div>
                    <div class="dato-valor"><?php echo $tipo_info['texto']; ?></div>
                </div>
            </div>
        <?php else: ?>
            <p style="color: #95a5a6; font-style: italic; text-align: center; padding: 20px;">
                <i class="fas fa-info-circle"></i> No se utilizó un vehículo de la organización en esta ruta
            </p>
        <?php endif; ?>
    </div>
    
    <!-- ===== CHECKLIST DEL VEHÍCULO ===== -->
    <div class="card">
        <div class="seccion-titulo">
            <i class="fas fa-clipboard-check"></i>
            Revisión del vehículo antes de viajar
        </div>
        
        <div class="checklist-grid">
            <?php foreach ($checklist_items as $key => $texto): 
                $completado = in_array($key, $checklist_respuestas);
            ?>
                <div class="checklist-item <?php echo $completado ? 'completo' : 'pendiente'; ?>">
                    <i class="fas <?php echo $completado ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <span><?php echo $texto; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- ===== ENCUESTA DE SEGURIDAD VIAL ===== -->
    <div class="card">
        <div class="seccion-titulo">
            <i class="fas fa-heartbeat"></i>
            Encuesta de seguridad vial
        </div>
        
        <div class="encuesta-lista">
            <?php foreach ($preguntas_encuesta as $campo => $pregunta): 
                $valor = $ruta[$campo] ?? null;
                
                // Determinar si la respuesta es "buena" o "mala"
                $es_buena = false;
                if ($pregunta['tipo'] === 'si_no_ok') {
                    $es_buena = ($valor == 1);
                } elseif ($pregunta['tipo'] === 'si_no_mal') {
                    $es_buena = ($valor == 0);
                }
                
                $clase_item = '';
                $clase_respuesta = 'neutro';
                if ($valor !== null) {
                    $clase_item = $es_buena ? 'ok' : 'mal';
                    $clase_respuesta = $es_buena ? 'ok' : 'mal';
                }
            ?>
                <div class="encuesta-item <?php echo $clase_item; ?>">
                    <div class="pregunta">
                        <span class="pregunta-num"><?php echo $pregunta['numero']; ?></span>
                        <span class="pregunta-texto"><?php echo htmlspecialchars($pregunta['texto']); ?></span>
                    </div>
                    <span class="respuesta <?php echo $clase_respuesta; ?>">
                        <?php echo textoSiNo($valor); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- ===== OBSERVACIONES DE CIERRE ===== -->
    <?php if (!empty($ruta['observaciones_cierre'])): ?>
        <div class="card">
            <div class="seccion-titulo">
                <i class="fas fa-comment-dots"></i>
                Observaciones de cierre
            </div>
            
            <div class="observaciones-box">
                <?php echo nl2br(htmlspecialchars($ruta['observaciones_cierre'])); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- ===== BOTONES ACCIÓN ===== -->
    <div class="card">
        <div class="acciones-seccion">
            <a href="rutas.php" class="btn-accion btn-volver">
                <i class="fas fa-arrow-left"></i> Volver a rutas
            </a>
            
            <div class="acciones-derecha">
                <?php if (!empty($ruta['conductor_id'])): ?>
                    <a href="../conductores/ver.php?id=<?php echo $ruta['conductor_id']; ?>" class="btn-accion btn-ver-conductor">
                        <i class="fas fa-user"></i> Ver conductor
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($ruta['vehiculo_id'])): ?>
                    <a href="../vehiculos/ver.php?id=<?php echo $ruta['vehiculo_id']; ?>" class="btn-accion btn-ver-vehiculo">
                        <i class="fas fa-car"></i> Ver vehículo
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========== MODAL ELIMINAR ========== -->
<div class="modal-overlay" id="modalEliminar">
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        
        <h2 class="modal-title">¿Eliminar ruta?</h2>
        
        <p class="modal-message">
            Vas a eliminar la <strong>Ruta #<?php echo $ruta['id']; ?></strong>. Esta acción no se puede deshacer.
        </p>
        
        <div class="modal-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Se eliminará el registro completo de esta ruta, incluyendo la encuesta y observaciones.</span>
        </div>
        
        <div class="modal-acciones">
            <button type="button" class="btn-modal btn-modal-cancelar" onclick="cerrarModalEliminar()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <a href="?eliminar=<?php echo $ruta['id']; ?>" class="btn-modal btn-modal-eliminar">
                <i class="fas fa-trash"></i> Sí, eliminar
            </a>
        </div>
    </div>
</div>

<script>
// ============================================
// MODAL ELIMINAR
// ============================================
function abrirModalEliminar() {
    document.getElementById('modalEliminar').classList.add('active');
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModalEliminar();
});

document.addEventListener('click', function(e) {
    const modal = document.getElementById('modalEliminar');
    if (e.target === modal) cerrarModalEliminar();
});

// Auto-ocultar mensaje de éxito
document.addEventListener('DOMContentLoaded', function() {
    const msgExito = document.getElementById('msgExito');
    if (msgExito) {
        setTimeout(function() {
            msgExito.style.opacity = '0';
            setTimeout(function() { msgExito.style.display = 'none'; }, 500);
        }, 3000);
    }
});
</script>

</body>
</html>