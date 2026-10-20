<?php
// aplicaciones/otros/pqrs/ver.php - Detalle de PQRS (Vista del Gestor)

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/pqrs_helpers.php';

// ============================================
// 1. VERIFICAR SESIÓN Y ROL
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id  = $_SESSION['usuario_id'];

// Admin/Supervisor → redirigir a su vista completa
if ($rol_usuario == 1 || $rol_usuario == 2) {
    $id_redir = intval($_GET['id'] ?? 0);
    header('Location: ../../../admin/pqrs/ver.php?id=' . $id_redir);
    exit();
}

$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

// ============================================
// 2. VERIFICAR QUE ES GESTOR ACTIVO
// ============================================
$stmt = $pdo->prepare("
    SELECT 1 FROM pqrs_gestores_usuarios 
    WHERE usuario_id = ? AND activo = 1
    LIMIT 1
");
$stmt->execute([$usuario_id]);
$es_gestor_activo = (bool) $stmt->fetch();

if (!$es_gestor_activo) {
    $_SESSION['error'] = "❌ No estás registrado como gestor de PQRS";
    header('Location: ../../../index.php');
    exit();
}

// ============================================
// 3. VALIDAR ID
// ============================================
$pqrs_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pqrs_id <= 0) {
    $_SESSION['error'] = "❌ PQRS no válida";
    header('Location: index.php');
    exit();
}

// ============================================
// 4. CARGAR LA PQRS
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.nombre_completo AS gestor_principal_nombre,
        u.usuario          AS gestor_principal_usuario,
        u.email            AS gestor_principal_email,
        u.cargo            AS gestor_principal_cargo
    FROM pqrs p
    LEFT JOIN usuarios u ON p.gestor_principal_id = u.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$pqrs_id]);
$pqrs = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pqrs) {
    $_SESSION['error'] = "❌ La PQRS no existe o fue eliminada";
    header('Location: index.php');
    exit();
}

// ============================================
// 5. VERIFICAR ACCESO A ESTA PQRS
// ============================================
$es_gestor_principal = ($pqrs['gestor_principal_id'] == $usuario_id);
$es_gestor_secundario = false;

if (!$es_gestor_principal) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM pqrs_gestores 
        WHERE pqrs_id = ? AND usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$pqrs_id, $usuario_id]);
    $es_gestor_secundario = (bool) $stmt->fetch();
}

if (!$es_gestor_principal && !$es_gestor_secundario) {
    $_SESSION['error'] = "❌ No tienes acceso a esta PQRS";
    header('Location: index.php');
    exit();
}

// ============================================
// 5.5 VERIFICAR SI PUEDE INTERACTUAR
// ============================================
$ticket_en_analisis = ($pqrs['estado'] === 'analisis_servicio_cliente');
$ticket_cerrado = ($pqrs['estado'] === 'cerrado');
$puede_interactuar = !$ticket_en_analisis && !$ticket_cerrado;

// ============================================
// 6. PROCESAR ACCIONES POST (con PRG)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 🔒 BLOQUEO BACKEND: si el ticket está en análisis o cerrado
    if (!$puede_interactuar) {
        $_SESSION['error'] = "🔒 Este ticket está bloqueado. No puedes modificar nada.";
        header("Location: ver.php?id=$pqrs_id");
        exit();
    }
    
    try {
        // ----------------------------------------
        // ENVIAR COMENTARIO
        // ----------------------------------------
        if ($action === 'comentar') {
            $texto = trim($_POST['mensaje'] ?? '');
            $archivo_info = null;
            
            if (!empty($_FILES['archivo']['name'])) {
                $archivo_info = procesarArchivoSubido($_FILES['archivo'], $pqrs['codigo']);
                
                if (isset($archivo_info['error'])) {
                    $_SESSION['error'] = "❌ " . $archivo_info['error'];
                    header("Location: ver.php?id=$pqrs_id");
                    exit();
                }
            }
            
            if (empty($texto) && !$archivo_info) {
                $_SESSION['error'] = "❌ Escribe un mensaje o adjunta un archivo";
            } else {
                if ($archivo_info) {
                    $stmt = $pdo->prepare("
                        INSERT INTO pqrs_mensajes 
                            (pqrs_id, usuario_id, mensaje, tipo, archivo, archivo_tipo, archivo_nombre_original, leido, creado_el)
                        VALUES 
                            (?, ?, ?, 'comentario', ?, ?, ?, 0, NOW())
                    ");
                    $stmt->execute([
                        $pqrs_id, 
                        $usuario_id, 
                        $texto, 
                        $archivo_info['archivo'],
                        $archivo_info['archivo_tipo'],
                        $archivo_info['archivo_nombre_original']
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO pqrs_mensajes 
                            (pqrs_id, usuario_id, mensaje, tipo, leido, creado_el)
                        VALUES 
                            (?, ?, ?, 'comentario', 0, NOW())
                    ");
                    $stmt->execute([$pqrs_id, $usuario_id, $texto]);
                }
                
                // 🔑 Si la PQRS está "abierto" y el gestor comenta → pasa a "en_proceso"
                if ($pqrs['estado'] === 'abierto') {
                    $pdo->prepare("UPDATE pqrs SET estado = 'en_proceso', actualizado_el = NOW() WHERE id = ?")
                        ->execute([$pqrs_id]);
                    
                    registrarEventoSistema(
                        $pdo, $pqrs_id, $usuario_id, 
                        'cambio_estado', 'abierto', 'en_proceso'
                    );
                } else {
                    $pdo->prepare("UPDATE pqrs SET actualizado_el = NOW() WHERE id = ?")
                        ->execute([$pqrs_id]);
                }
                
                $_SESSION['mensaje'] = "✅ Comentario publicado";
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // CAMBIAR ESTADO
        // ----------------------------------------
        elseif ($action === 'cambiar_estado') {
            $nuevo_estado = $_POST['nuevo_estado'] ?? '';
            
            if ($es_gestor_principal) {
                $estados_permitidos = ['en_proceso', 'analisis_servicio_cliente'];
            } else {
                $estados_permitidos = ['en_proceso'];
            }
            
            if (!in_array($nuevo_estado, $estados_permitidos)) {
                $_SESSION['error'] = "❌ No tienes permiso para cambiar a ese estado";
            } elseif ($nuevo_estado === $pqrs['estado']) {
                $_SESSION['error'] = "❌ La PQRS ya está en ese estado";
            } else {
                $estado_anterior = $pqrs['estado'];
                
                $stmt = $pdo->prepare("UPDATE pqrs SET estado = ?, actualizado_el = NOW() WHERE id = ?");
                $stmt->execute([$nuevo_estado, $pqrs_id]);
                
                registrarEventoSistema($pdo, $pqrs_id, $usuario_id, 'cambio_estado', $estado_anterior, $nuevo_estado);
                
                $_SESSION['mensaje'] = "✅ Estado cambiado a " . estadoLegible($nuevo_estado);
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // 🔑 AGREGAR COLABORADOR (solo gestor principal)
        // ----------------------------------------
        elseif ($action === 'agregar_colaborador') {
            
            // 🔒 Validar que sea el gestor principal
            if (!$es_gestor_principal) {
                $_SESSION['error'] = "❌ Solo el gestor principal puede añadir colaboradores";
                header("Location: ver.php?id=$pqrs_id");
                exit();
            }
            
            $nuevo_gestor_id = intval($_POST['gestor_id'] ?? 0);
            
            if ($nuevo_gestor_id <= 0) {
                $_SESSION['error'] = "❌ Debes seleccionar un gestor";
            } elseif ($nuevo_gestor_id == $usuario_id) {
                $_SESSION['error'] = "❌ No puedes añadirte a ti mismo";
            } else {
                // Validar que sea gestor registrado
                $stmt = $pdo->prepare("
                    SELECT u.id, u.nombre_completo 
                    FROM usuarios u
                    INNER JOIN pqrs_gestores_usuarios gu ON gu.usuario_id = u.id AND gu.activo = 1
                    WHERE u.id = ? AND u.activo = 1
                    LIMIT 1
                ");
                $stmt->execute([$nuevo_gestor_id]);
                $nuevo_gestor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$nuevo_gestor) {
                    $_SESSION['error'] = "❌ Usuario no válido o no es gestor";
                } else {
                    // Verificar que no esté ya asignado
                    $stmt = $pdo->prepare("SELECT 1 FROM pqrs_gestores WHERE pqrs_id = ? AND usuario_id = ? LIMIT 1");
                    $stmt->execute([$pqrs_id, $nuevo_gestor_id]);
                    
                    if ($stmt->fetch()) {
                        $_SESSION['error'] = "❌ Ese gestor ya está asignado a esta PQRS";
                    } else {
                        // Insertar con asignado_por = gestor_principal_id
                        $stmt = $pdo->prepare("
                            INSERT INTO pqrs_gestores 
                                (pqrs_id, usuario_id, asignado_por, fecha_asignacion) 
                            VALUES 
                                (?, ?, ?, NOW())
                        ");
                        $stmt->execute([$pqrs_id, $nuevo_gestor_id, $usuario_id]);
                        
                        registrarEventoSistema(
                            $pdo, $pqrs_id, $usuario_id, 
                            'agregar_gestor', null, 
                            $nuevo_gestor['nombre_completo']
                        );
                        
                        $_SESSION['mensaje'] = "✅ Colaborador añadido: " . $nuevo_gestor['nombre_completo'];
                    }
                }
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // 🔑 QUITAR COLABORADOR (solo gestor principal, solo los que él añadió)
        // ----------------------------------------
        elseif ($action === 'quitar_colaborador') {
            
            // 🔒 Validar que sea el gestor principal
            if (!$es_gestor_principal) {
                $_SESSION['error'] = "❌ Solo el gestor principal puede quitar colaboradores";
                header("Location: ver.php?id=$pqrs_id");
                exit();
            }
            
            $gestor_a_quitar = intval($_POST['gestor_id'] ?? 0);
            
            if ($gestor_a_quitar <= 0) {
                $_SESSION['error'] = "❌ Gestor no válido";
            } else {
                // Verificar que ese colaborador fue añadido por el gestor principal actual
                $stmt = $pdo->prepare("
                    SELECT pg.id, u.nombre_completo, pg.asignado_por
                    FROM pqrs_gestores pg
                    INNER JOIN usuarios u ON pg.usuario_id = u.id
                    WHERE pg.pqrs_id = ? AND pg.usuario_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$pqrs_id, $gestor_a_quitar]);
                $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$colaborador) {
                    $_SESSION['error'] = "❌ El colaborador no existe en esta PQRS";
                } elseif ($colaborador['asignado_por'] != $usuario_id) {
                    $_SESSION['error'] = "❌ Solo puedes quitar los colaboradores que tú mismo añadiste";
                } else {
                    // Eliminar
                    $stmt = $pdo->prepare("DELETE FROM pqrs_gestores WHERE pqrs_id = ? AND usuario_id = ?");
                    $stmt->execute([$pqrs_id, $gestor_a_quitar]);
                    
                    registrarEventoSistema(
                        $pdo, $pqrs_id, $usuario_id, 
                        'quitar_gestor', 
                        $colaborador['nombre_completo'], null
                    );
                    
                    $_SESSION['mensaje'] = "✅ Colaborador removido: " . $colaborador['nombre_completo'];
                }
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // Acción desconocida
        // ----------------------------------------
        else {
            $_SESSION['error'] = "❌ Acción no permitida";
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
        header("Location: ver.php?id=$pqrs_id");
        exit();
    }
}

// ============================================
// 7. RECARGAR PQRS
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.nombre_completo AS gestor_principal_nombre,
        u.usuario          AS gestor_principal_usuario,
        u.email            AS gestor_principal_email,
        u.cargo            AS gestor_principal_cargo
    FROM pqrs p
    LEFT JOIN usuarios u ON p.gestor_principal_id = u.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$pqrs_id]);
$pqrs = $stmt->fetch(PDO::FETCH_ASSOC);

// Recalcular rol del usuario
$es_gestor_principal = ($pqrs['gestor_principal_id'] == $usuario_id);
$es_gestor_secundario = false;
if (!$es_gestor_principal) {
    $stmt = $pdo->prepare("SELECT 1 FROM pqrs_gestores WHERE pqrs_id = ? AND usuario_id = ? LIMIT 1");
    $stmt->execute([$pqrs_id, $usuario_id]);
    $es_gestor_secundario = (bool) $stmt->fetch();
}

// Recalcular permisos
$ticket_en_analisis = ($pqrs['estado'] === 'analisis_servicio_cliente');
$ticket_cerrado = ($pqrs['estado'] === 'cerrado');
$puede_interactuar = !$ticket_en_analisis && !$ticket_cerrado;

// ============================================
// 8. CARGAR GESTORES ASIGNADOS
// ============================================
$gestores_asignados = obtenerGestoresDePQRS($pdo, $pqrs_id);

// IDs de gestores ya asignados (para el dropdown)
$ids_asignados = array_column($gestores_asignados, 'id');

// Cargar gestores disponibles (que no estén asignados)
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.nombre_completo,
        u.usuario,
        u.cargo
    FROM usuarios u
    INNER JOIN pqrs_gestores_usuarios gu ON gu.usuario_id = u.id AND gu.activo = 1
    WHERE u.activo = 1
      AND u.id != ?
    ORDER BY u.nombre_completo ASC
");
$stmt->execute([$usuario_id]);
$gestores_todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrar los ya asignados
$gestores_disponibles = array_filter($gestores_todos, function($g) use ($ids_asignados) {
    return !in_array($g['id'], $ids_asignados);
});

// ============================================
// 9. CARGAR MENSAJES (timeline)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        u.nombre_completo AS autor_nombre,
        u.usuario          AS autor_usuario,
        u.cargo            AS autor_cargo
    FROM pqrs_mensajes m
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.pqrs_id = ?
    ORDER BY m.creado_el ASC
");
$stmt->execute([$pqrs_id]);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 10. MARCAR MENSAJES COMO LEÍDOS
// ============================================
marcarMensajesLeidos($pdo, $pqrs_id, $usuario_id);

// ============================================
// 11. RECUPERAR MENSAJES FLASH
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
// 12. ESTADOS, TIPOS Y PRIORIDADES
// ============================================
$estados = [
    'abierto'                  => ['texto' => 'Abierto',                 'clase' => 'estado-abierto',   'icono' => 'fa-folder-open'],
    'en_proceso'               => ['texto' => 'En Proceso',              'clase' => 'estado-proceso',   'icono' => 'fa-spinner'],
    'analisis_servicio_cliente'=> ['texto' => 'Análisis Serv. Cliente',  'clase' => 'estado-analisis',  'icono' => 'fa-search'],
    'cerrado'                  => ['texto' => 'Cerrado',                 'clase' => 'estado-cerrado',   'icono' => 'fa-check-circle']
];

$tipos_solicitud = [
    'PETICION'     => 'Petición',
    'QUEJA'        => 'Queja',
    'RECLAMO'      => 'Reclamo',
    'SUGERENCIA'   => 'Sugerencia',
    'FELICITACION' => 'Felicitación'
];

$prioridades = [
    'alta'  => ['texto' => 'Alta',  'clase' => 'prio-alta',  'icono' => 'fa-exclamation-circle'],
    'media' => ['texto' => 'Media', 'clase' => 'prio-media', 'icono' => 'fa-minus-circle'],
    'baja'  => ['texto' => 'Baja',  'clase' => 'prio-baja',  'icono' => 'fa-arrow-down']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pqrs['codigo']); ?> | Mis PQRSF</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER ===== */
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
        .logo img { width: 130px; }
        .logo-subtitle { color: #8aa8b8; font-size: 14px; margin-top: -5px; }
        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-volver:hover { background: #ffc107; color: #12232b; transform: translateY(-2px); }

        /* ===== MENSAJES FLASH ===== */
        .mensaje-exito, .mensaje-error {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .mensaje-exito { background: #d4edda; color: #155724; border-left: 4px solid #27ae60; }
        .mensaje-error { background: #f8d7da; color: #721c24; border-left: 4px solid #e74c3c; }

        /* ===== MI ROL BANNER ===== */
        .mi-rol-banner {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: white;
        }
        .mi-rol-banner.principal { background: linear-gradient(135deg, #d68910, #f39c12); }
        .mi-rol-banner.colaborador { background: linear-gradient(135deg, #173742, #1a4a55); }
        .mi-rol-banner .icono {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .mi-rol-banner .texto strong { font-size: 14px; display: block; }
        .mi-rol-banner .texto p { font-size: 12px; opacity: 0.9; margin-top: 2px; }

        /* ===== HEADER PQRS ===== */
        .pqrs-header-card {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            border-left: 6px solid #e67e22;
        }
        .pqrs-header-card.estado-abierto { border-left-color: #f39c12; }
        .pqrs-header-card.estado-en_proceso { border-left-color: #3498db; }
        .pqrs-header-card.estado-analisis_servicio_cliente { border-left-color: #9b59b6; }
        .pqrs-header-card.estado-cerrado { border-left-color: #27ae60; }

        .pqrs-titulo h1 {
            font-size: 22px;
            color: #12232b;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pqrs-titulo .codigo {
            font-size: 13px;
            background: #e8f0fe;
            color: #173742;
            padding: 3px 12px;
            border-radius: 10px;
            font-weight: 700;
        }
        .pqrs-titulo .asunto {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 400;
        }

        .pqrs-header-badges { display: flex; gap: 10px; flex-wrap: wrap; }

        .badge-estado {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .estado-abierto { background: #fef9e7; color: #7f6000; border: 1px solid #f39c12; }
        .estado-proceso { background: #d6eaf8; color: #1a5276; border: 1px solid #3498db; }
        .estado-analisis { background: #ebdef0; color: #6c3483; border: 1px solid #9b59b6; }
        .estado-cerrado { background: #d5f5e3; color: #1a7a3a; border: 1px solid #27ae60; }

        .badge-prioridad {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .prio-alta { background: #fadbd8; color: #922b21; border: 1px solid #e74c3c; }
        .prio-media { background: #fef9e7; color: #7f6000; border: 1px solid #f39c12; }
        .prio-baja { background: #eafaf1; color: #196f3d; border: 1px solid #27ae60; }

        /* ===== GRID ===== */
        .pqrs-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 1024px) {
            .pqrs-grid { grid-template-columns: 1fr; }
        }

        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card h3 {
            font-size: 15px;
            color: #12232b;
            margin-bottom: 15px;
            border-left: 3px solid #e67e22;
            padding-left: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card h3 i { color: #e67e22; font-size: 14px; }

        /* ===== DATOS ===== */
        .datos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }
        .dato-item {
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #173742;
        }
        .dato-label {
            font-size: 10.5px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .dato-valor {
            font-size: 13.5px;
            color: #12232b;
            font-weight: 500;
            word-break: break-word;
        }

        .descripcion-box {
            padding: 15px;
            background: #fafafa;
            border-radius: 10px;
            border: 1px solid #f0f0f0;
            font-size: 13.5px;
            line-height: 1.6;
            color: #2c3e50;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }

        /* ===== ACCIONES ===== */
        .acciones-seccion {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .accion-grupo {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        .accion-grupo label {
            font-size: 11px;
            font-weight: 600;
            color: #445960;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
            margin-bottom: 6px;
        }
        .accion-grupo select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            background: white;
            color: #2c3e50;
            cursor: pointer;
        }
        .accion-grupo select:focus { outline: none; border-color: #173742; }

        .btn {
            padding: 9px 16px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: all 0.25s ease;
            text-decoration: none;
            width: 100%;
        }
        .btn-primary { background: linear-gradient(135deg, #173742, #1a4a55); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(23,55,66,0.3); }
        .btn-info { background: linear-gradient(135deg, #3498db, #5dade2); color: white; }
        .btn-info:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(52,152,219,0.3); }
        .btn-success { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(39,174,96,0.3); }

        /* ===== GESTORES ASIGNADOS ===== */
        .gestores-lista {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .gestor-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #3498db;
        }
        .gestor-card.principal {
            border-left-color: #e67e22;
            background: #fef9e7;
        }
        .gestor-card.yo {
            border-left-color: #27ae60;
            background: #eafaf1;
        }
        .gestor-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
        }
        .gestor-card.principal .gestor-avatar {
            background: linear-gradient(135deg, #d68910, #f39c12);
        }
        .gestor-card.yo .gestor-avatar {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }
        .gestor-info-mini { flex: 1; min-width: 0; }
        .gestor-info-mini .nombre {
            font-weight: 600;
            font-size: 13px;
            color: #12232b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gestor-info-mini .rol { font-size: 11px; color: #7f8c8d; }
        .gestor-info-mini .rol .agregado-por {
            color: #95a5a6;
            font-style: italic;
        }

        .btn-remove-mini {
            background: transparent;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .btn-remove-mini:hover { background: #fadbd8; }

        /* ===== TIMELINE ===== */
        .timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 8px;
        }
        .timeline::-webkit-scrollbar { width: 6px; }
        .timeline::-webkit-scrollbar-thumb { background: #b0b0b0; border-radius: 3px; }

        .timeline-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            background: #f8f9fa;
            border-left: 3px solid #3498db;
        }
        .timeline-item.sistema {
            background: #f0f2f5;
            border-left-color: #7f8c8d;
            padding: 8px 12px;
        }
        .timeline-item.propio {
            background: #eafaf1;
            border-left-color: #27ae60;
        }

        .timeline-icono {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 13px;
            font-weight: 700;
        }
        .timeline-icono.sistema-icono { background: #7f8c8d; }
        .timeline-icono.success-icono { background: #27ae60; }
        .timeline-icono.info-icono { background: #3498db; }
        .timeline-icono.warning-icono { background: #f39c12; }
        .timeline-icono.danger-icono { background: #e74c3c; }

        .timeline-contenido { flex: 1; min-width: 0; }
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 4px;
            flex-wrap: wrap;
        }
        .timeline-autor {
            font-weight: 600;
            font-size: 12.5px;
            color: #12232b;
        }
        .timeline-autor.sistema-autor { color: #7f8c8d; font-style: italic; }
        .timeline-fecha { font-size: 11px; color: #95a5a6; white-space: nowrap; }
        .timeline-mensaje {
            font-size: 13px;
            color: #2c3e50;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .timeline-item.sistema .timeline-mensaje { font-size: 12.5px; color: #7f8c8d; }

        .timeline-adjunto { margin-top: 8px; display: inline-block; }
        .timeline-adjunto img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            border: 1px solid #e0e0e0;
            transition: transform 0.2s;
        }
        .timeline-adjunto img:hover { transform: scale(1.02); }
        .timeline-adjunto-pdf {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            font-size: 12.5px;
            transition: all 0.2s;
        }
        .timeline-adjunto-pdf:hover { border-color: #e74c3c; background: #fef5f5; }
        .timeline-adjunto-pdf i { color: #e74c3c; font-size: 18px; }

        /* ===== FORM RESPUESTA ===== */
        .form-respuesta {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        .form-respuesta textarea {
            width: 100%;
            min-height: 70px;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            resize: vertical;
            color: #2c3e50;
        }
        .form-respuesta textarea:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        .form-respuesta-acciones {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            gap: 10px;
            flex-wrap: wrap;
        }
        .archivo-preview {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 12px;
            color: #2c3e50;
        }
        .archivo-preview.visible { display: inline-flex; }
        .archivo-preview button {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 2px;
        }

        .btn-adjuntar {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 8px 14px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-adjuntar:hover { background: #d5dbdb; }

        /* ===== INFO VACÍA ===== */
        .empty-info {
            text-align: center;
            padding: 25px 15px;
            color: #7f8c8d;
            font-size: 13px;
        }
        .empty-info i {
            font-size: 32px;
            color: #d3d3d3;
            display: block;
            margin-bottom: 8px;
        }

        /* ===== AVISO BLOQUEADO ===== */
        .aviso-bloqueado {
            background: linear-gradient(135deg, #fef9e7, #fdebd0);
            border: 2px solid #f39c12;
            border-left: 6px solid #e67e22;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(243,156,18,0.15);
        }
        .aviso-bloqueado i {
            font-size: 28px;
            color: #e67e22;
            flex-shrink: 0;
        }
        .aviso-bloqueado strong {
            font-size: 15px;
            color: #7f6000;
            display: block;
            margin-bottom: 3px;
        }
        .aviso-bloqueado p {
            font-size: 13px;
            color: #a06a00;
            margin: 0;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .pqrs-header-card { flex-direction: column; align-items: flex-start; }
            .pqrs-header-badges { width: 100%; }
            .datos-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
                <p class="logo-subtitle">| PQRSF · Detalle</p>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver a mis PQRS
            </a>
        </div>
    </header>

    <div class="container">

        <!-- ===== MENSAJES FLASH ===== -->
        <?php if ($mensaje): ?>
            <div class="mensaje-exito" id="mensajeExito">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ===== AVISO: TICKET BLOQUEADO ===== -->
        <?php if (!$puede_interactuar): ?>
            <div class="aviso-bloqueado">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>
                        <?php if ($ticket_cerrado): ?>
                            Este ticket está cerrado
                        <?php else: ?>
                            Este ticket está en revisión por un supervisor
                        <?php endif; ?>
                    </strong>
                    <p>
                        <?php if ($ticket_cerrado): ?>
                            No puedes editar ni comentar. Solo un supervisor puede reabrirlo.
                        <?php else: ?>
                            No puedes comentar ni modificar nada hasta que sea reactivado.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- ===== MI ROL ===== -->
        <div class="mi-rol-banner <?php echo $es_gestor_principal ? 'principal' : 'colaborador'; ?>">
            <div class="icono">
                <i class="fas fa-<?php echo $es_gestor_principal ? 'star' : 'user-friends'; ?>"></i>
            </div>
            <div class="texto">
                <strong>
                    <?php echo $es_gestor_principal ? 'Eres el gestor principal' : 'Eres colaborador'; ?>
                </strong>
                <p>
                    <?php if ($es_gestor_principal): ?>
                        Puedes cambiar el estado, añadir y quitar colaboradores
                    <?php else: ?>
                        Puedes comentar y adjuntar todo lo que requiera la PQRSF
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- ===== HEADER DE LA PQRS ===== -->
        <?php
        $estado_info = $estados[$pqrs['estado']] ?? $estados['abierto'];
        $prioridad_info = $prioridades[$pqrs['prioridad']] ?? $prioridades['media'];
        ?>
        <div class="pqrs-header-card estado-<?php echo $pqrs['estado']; ?>">
            <div class="pqrs-titulo">
                <h1>
                    <span class="codigo"><?php echo htmlspecialchars($pqrs['codigo']); ?></span>
                    <?php echo htmlspecialchars($pqrs['asunto']); ?>
                </h1>
                <p class="asunto">
                    <?php echo $tipos_solicitud[$pqrs['tipo_solicitud']] ?? $pqrs['tipo_solicitud']; ?>
                    · Creada el <?php echo date('d/m/Y H:i', strtotime($pqrs['marca_temporal'] ?? $pqrs['creado_el'])); ?>
                </p>
            </div>
            <div class="pqrs-header-badges">
                <span class="badge-estado <?php echo $estado_info['clase']; ?>">
                    <i class="fas <?php echo $estado_info['icono']; ?>"></i>
                    <?php echo $estado_info['texto']; ?>
                </span>
                <span class="badge-prioridad <?php echo $prioridad_info['clase']; ?>">
                    <i class="fas <?php echo $prioridad_info['icono']; ?>"></i>
                    <?php echo $prioridad_info['texto']; ?>
                </span>
            </div>
        </div>

        <!-- ===== GRID ===== -->
        <div class="pqrs-grid">

            <!-- ===== COLUMNA IZQUIERDA ===== -->
            <div>
                <!-- DATOS DEL CLIENTE -->
                <div class="card">
                    <h3><i class="fas fa-user"></i> Datos del cliente</h3>
                    <div class="datos-grid">
                        <div class="dato-item">
                            <div class="dato-label">Nombre</div>
                            <div class="dato-valor"><?php echo htmlspecialchars($pqrs['nombre_completo']); ?></div>
                        </div>
                        <div class="dato-item">
                            <div class="dato-label">Tipo persona</div>
                            <div class="dato-valor"><?php echo htmlspecialchars($pqrs['tipo_persona'] ?? '—'); ?></div>
                        </div>
                        <div class="dato-item">
                            <div class="dato-label">Documento</div>
                            <div class="dato-valor">
                                <?php echo htmlspecialchars($pqrs['tipo_documento'] ?? ''); ?>
                                <?php echo htmlspecialchars($pqrs['documento'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="dato-item">
                            <div class="dato-label">Celular</div>
                            <div class="dato-valor"><?php echo htmlspecialchars($pqrs['celular'] ?? '—'); ?></div>
                        </div>
                        <div class="dato-item">
                            <div class="dato-label">Email</div>
                            <div class="dato-valor"><?php echo htmlspecialchars($pqrs['email_contacto'] ?? '—'); ?></div>
                        </div>
                        <div class="dato-item">
                            <div class="dato-label">Ciudad cliente</div>
                            <div class="dato-valor"><?php echo htmlspecialchars($pqrs['ciudad_cliente'] ?? '—'); ?></div>
                        </div>
                        <div class="dato-item">
                            <div class="dato-label">Ciudad concesionario</div>
                            <div class="dato-valor"><?php echo htmlspecialchars($pqrs['ciudad_concesionario'] ?? '—'); ?></div>
                        </div>
                        <div class="dato-item">
                            <div class="dato-label">Relacionada con VH</div>
                            <div class="dato-valor"><?php echo $pqrs['relacionada_vh'] ? 'Sí' : 'No'; ?></div>
                        </div>
                        <?php if ($pqrs['relacionada_vh']): ?>
                            <div class="dato-item">
                                <div class="dato-label">Marca VH</div>
                                <div class="dato-valor"><?php echo htmlspecialchars($pqrs['marca_vh'] ?? '—'); ?></div>
                            </div>
                            <div class="dato-item">
                                <div class="dato-label">Placa</div>
                                <div class="dato-valor"><?php echo htmlspecialchars($pqrs['placa'] ?? '—'); ?></div>
                            </div>
                            <div class="dato-item">
                                <div class="dato-label">Referencia VH</div>
                                <div class="dato-valor"><?php echo htmlspecialchars($pqrs['referencia_vh'] ?? '—'); ?></div>
                            </div>
                            <div class="dato-item">
                                <div class="dato-label">Modelo</div>
                                <div class="dato-valor"><?php echo htmlspecialchars($pqrs['modelo'] ?? '—'); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- DESCRIPCIÓN -->
                <div class="card">
                    <h3><i class="fas fa-align-left"></i> Descripción del caso</h3>
                    <div class="descripcion-box"><?php echo htmlspecialchars($pqrs['descripcion'] ?? 'Sin descripción'); ?></div>
                </div>

                <!-- TIMELINE -->
                <div class="card">
                    <h3>
                        <i class="fas fa-comments"></i>
                        Timeline (<?php echo count($mensajes); ?>)
                    </h3>

                    <?php if (empty($mensajes)): ?>
                        <div class="empty-info">
                            <i class="fas fa-comment-slash"></i>
                            No hay actividad todavía
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($mensajes as $m): 
                                $es_sistema = ($m['tipo'] === 'sistema');
                                $es_propio = (!$es_sistema && $m['usuario_id'] == $usuario_id);
                                
                                $iniciales = '?';
                                if ($m['autor_nombre']) {
                                    $partes = explode(' ', trim($m['autor_nombre']));
                                    if (count($partes) >= 2) {
                                        $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
                                    } else {
                                        $iniciales = strtoupper(substr($partes[0], 0, 2));
                                    }
                                }
                                
                                $icono_clase = 'info-icono';
                                $icono = iconoEvento($m['accion'] ?? '');
                                $color = colorEvento($m['accion'] ?? '');
                                if ($color === 'evento-success') $icono_clase = 'success-icono';
                                elseif ($color === 'evento-warning') $icono_clase = 'warning-icono';
                                elseif ($color === 'evento-danger') $icono_clase = 'danger-icono';
                            ?>
                                <div class="timeline-item <?php echo $es_sistema ? 'sistema' : ''; ?> <?php echo $es_propio ? 'propio' : ''; ?>">
                                    <div class="timeline-icono <?php echo $es_sistema ? 'sistema-icono' : $icono_clase; ?>">
                                        <?php if ($es_sistema): ?>
                                            <i class="fas <?php echo $icono; ?>"></i>
                                        <?php else: ?>
                                            <?php echo $iniciales; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-contenido">
                                        <div class="timeline-header">
                                            <span class="timeline-autor <?php echo $es_sistema ? 'sistema-autor' : ''; ?>">
                                                <?php if ($es_sistema): ?>
                                                    Sistema
                                                <?php elseif ($es_propio): ?>
                                                    Tú
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($m['autor_nombre'] ?? 'Usuario'); ?>
                                                    <?php if (!empty($m['autor_cargo'])): ?>
                                                        · <?php echo htmlspecialchars($m['autor_cargo']); ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </span>
                                            <span class="timeline-fecha">
                                                <?php echo date('d/m/Y H:i', strtotime($m['creado_el'])); ?>
                                            </span>
                                        </div>
                                        <div class="timeline-mensaje">
                                            <?php echo htmlspecialchars($m['mensaje']); ?>
                                        </div>
                                        
                                        <?php if (!empty($m['archivo'])): ?>
                                            <?php if (strpos($m['archivo_tipo'], 'image/') === 0): ?>
                                                <a href="../../../uploads/pqrs/<?php echo htmlspecialchars($m['archivo']); ?>" 
                                                    target="_blank" class="timeline-adjunto">
                                                        <img src="../../../uploads/pqrs/<?php echo htmlspecialchars($m['archivo']); ?>"  
                                                         alt="<?php echo htmlspecialchars($m['archivo_nombre_original']); ?>"
                                                         loading="lazy">
                                                </a>
                                            <?php else: ?>
                                                <a href="../../../uploads/pqrs/<?php echo htmlspecialchars($m['archivo']); ?>" 
                                                   target="_blank"
                                                   download="<?php echo htmlspecialchars($m['archivo_nombre_original']); ?>"
                                                   class="timeline-adjunto-pdf">
                                                    <i class="fas fa-file-pdf"></i>
                                                    <span><?php echo htmlspecialchars($m['archivo_nombre_original']); ?></span>
                                                    <i class="fas fa-download" style="font-size: 12px; color: #7f8c8d;"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- FORM RESPUESTA -->
                    <?php if ($puede_interactuar): ?>
                        
                        <form method="POST" action="" class="form-respuesta" enctype="multipart/form-data" id="formComentario">
                            <input type="hidden" name="action" value="comentar">
                            <textarea name="mensaje" id="textareaMensaje" placeholder="Escribe un comentario o arrastra un archivo..."></textarea>
                            
                            <div class="archivo-preview" id="archivoPreview">
                                <i class="fas fa-paperclip"></i>
                                <span id="archivoNombre"></span>
                                <button type="button" id="btnQuitarArchivo" title="Quitar archivo">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <input type="file" name="archivo" id="inputArchivo" 
                                   accept="image/*,application/pdf, .eml" style="display: none;">
                            
                            <div class="form-respuesta-acciones">
                                <button type="button" class="btn-adjuntar" id="btnAdjuntar">
                                    <i class="fas fa-paperclip"></i> Adjuntar archivo
                                </button>
                                <button type="submit" class="btn btn-primary" style="width: auto; padding: 9px 20px;">
                                    <i class="fas fa-paper-plane"></i> Enviar
                                </button>
                            </div>
                            <p style="font-size: 11px; color: #95a5a6; margin-top: 8px;">
                                <i class="fas fa-info-circle"></i>
                                Tip: puedes pegar capturas con <strong>Ctrl+V</strong> directamente
                            </p>
                        </form>
                        
                    <?php else: ?>
                        
                        <div class="form-respuesta" style="background: #f0f2f5; border-color: #d3d3d3; text-align: center; padding: 25px 15px;">
                            <i class="fas fa-lock" style="font-size: 32px; color: #95a5a6; margin-bottom: 10px; display: block;"></i>
                            <p style="color: #7f8c8d; font-size: 13px; margin: 0;">
                                <strong>Chat bloqueado</strong>
                            </p>
                            <p style="color: #95a5a6; font-size: 12px; margin-top: 5px;">
                                <?php if ($ticket_cerrado): ?>
                                    Este ticket está cerrado. Un supervisor debe reabrirlo.
                                <?php else: ?>
                                    Un supervisor debe reactivar el ticket para poder comentar
                                <?php endif; ?>
                            </p>
                        </div>
                        
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== COLUMNA DERECHA ===== -->
            <div>

                <!-- GESTORES ASIGNADOS -->
                <div class="card">
                    <h3><i class="fas fa-users"></i> Equipo de esta PQRS</h3>
                    
                    <?php if (empty($gestores_asignados)): ?>
                        <div class="empty-info">
                            <i class="fas fa-user-slash"></i>
                            Sin gestor asignado
                        </div>
                    <?php else: ?>
                        <div class="gestores-lista">
                            <?php foreach ($gestores_asignados as $g): 
                                $partes = explode(' ', trim($g['nombre_completo']));
                                $iniciales = count($partes) >= 2 
                                    ? strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1))
                                    : strtoupper(substr($partes[0], 0, 2));
                                $soy_yo = ($g['id'] == $usuario_id);
                                
                                // 🔑 Determinar si puedo quitar a este colaborador
                                $puedo_quitar = false;
                                if (!$g['es_principal'] && $es_gestor_principal && $puede_interactuar) {
                                    // Solo si yo (gestor principal) lo añadí
                                    if (!empty($g['asignado_por']) && $g['asignado_por'] == $usuario_id) {
                                        $puedo_quitar = true;
                                    }
                                }
                            ?>
                                <div class="gestor-card <?php echo $g['es_principal'] ? 'principal' : ''; ?> <?php echo $soy_yo ? 'yo' : ''; ?>">
                                    <div class="gestor-avatar"><?php echo $iniciales; ?></div>
                                    <div class="gestor-info-mini">
                                        <div class="nombre">
                                            <?php echo htmlspecialchars($g['nombre_completo']); ?>
                                            <?php if ($soy_yo): ?>
                                                <span style="color: #27ae60; font-size: 11px;">(Tú)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="rol">
                                            <?php echo $g['es_principal'] ? '🌟 Principal' : 'Colaborador'; ?>
                                            <?php if (!$g['es_principal'] && !empty($g['asignado_por_nombre'])): ?>
                                                · <span class="agregado-por">añadido por <?php echo htmlspecialchars($g['asignado_por_nombre']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($puedo_quitar): ?>
                                        <form method="POST" 
                                              onsubmit="return confirm('¿Quitar a <?php echo htmlspecialchars(addslashes($g['nombre_completo'])); ?> como colaborador?');" 
                                              style="margin: 0;">
                                            <input type="hidden" name="action" value="quitar_colaborador">
                                            <input type="hidden" name="gestor_id" value="<?php echo $g['id']; ?>">
                                            <button type="submit" class="btn-remove-mini" title="Quitar colaborador">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ACCIONES -->
                <div class="card">
                    <h3><i class="fas fa-cog"></i> Acciones</h3>
                    <div class="acciones-seccion">

                        <?php if (!$puede_interactuar): ?>
                            
                            <!-- Ticket bloqueado -->
                            <div class="empty-info" style="padding: 20px;">
                                <i class="fas fa-lock" style="color: #e67e22;"></i>
                                <p style="color: #7f6000; font-weight: 600;">Acciones bloqueadas</p>
                                <p style="font-size: 12px; margin-top: 4px; color: #95a5a6;">
                                    <?php if ($ticket_cerrado): ?>
                                        Ticket cerrado - solo un supervisor puede reabrirlo
                                    <?php else: ?>
                                        El ticket está en revisión por un supervisor
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                        <?php else: ?>
                            
                            <!-- CAMBIAR ESTADO -->
                            <?php
                            if ($es_gestor_principal) {
                                $estados_permitidos = ['en_proceso', 'analisis_servicio_cliente'];
                            } else {
                                $estados_permitidos = ['en_proceso'];
                            }
                            $estados_disponibles = array_filter($estados_permitidos, fn($e) => $e !== $pqrs['estado']);
                            ?>

                            <?php if (!empty($estados_disponibles)): ?>
                                <div class="accion-grupo">
                                    <label>Cambiar estado</label>
                                    <form method="POST" style="display: flex; gap: 6px;">
                                        <input type="hidden" name="action" value="cambiar_estado">
                                        <select name="nuevo_estado" style="flex: 1;">
                                            <?php foreach ($estados_disponibles as $key): ?>
                                                <option value="<?php echo $key; ?>">
                                                    <?php echo $estados[$key]['texto']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary" style="width: auto; padding: 8px 12px;">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <!-- ATAJO: MARCAR EN PROCESO -->
                            <?php if ($pqrs['estado'] === 'abierto'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="cambiar_estado">
                                    <input type="hidden" name="nuevo_estado" value="en_proceso">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-play"></i> Marcar como En Proceso
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- 🔑 AÑADIR COLABORADOR (solo gestor principal) -->
                            <?php if ($es_gestor_principal && !empty($gestores_disponibles)): ?>
                                <div class="accion-grupo" style="background: #eafaf1; border-color: #a9dfbf;">
                                    <label><i class="fas fa-user-plus"></i> Añadir colaborador</label>
                                    <form method="POST" style="display: flex; gap: 6px;">
                                        <input type="hidden" name="action" value="agregar_colaborador">
                                        <select name="gestor_id" style="flex: 1;">
                                            <option value="">— Seleccionar —</option>
                                            <?php foreach ($gestores_disponibles as $gd): ?>
                                                <option value="<?php echo $gd['id']; ?>">
                                                    <?php echo htmlspecialchars($gd['nombre_completo']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-success" style="width: auto; padding: 8px 12px;">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                        <?php endif; ?>

                    </div>
                </div>

                <!-- INFO EXTRA -->
                <div class="card">
                    <h3><i class="fas fa-info-circle"></i> Información</h3>
                    <div class="datos-grid" style="grid-template-columns: 1fr;">
                        <div class="dato-item">
                            <div class="dato-label">Fecha asignación</div>
                            <div class="dato-valor">
                                <?php echo $pqrs['fecha_asignacion'] 
                                    ? date('d/m/Y H:i', strtotime($pqrs['fecha_asignacion'])) 
                                    : '—'; ?>
                            </div>
                        </div>
                        <div class="dato-item">
                            <div class="dato-label">Última actualización</div>
                            <div class="dato-valor">
                                <?php echo date('d/m/Y H:i', strtotime($pqrs['actualizado_el'] ?? $pqrs['creado_el'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ============================================
            // OCULTAR MENSAJE FLASH
            // ============================================
            const mensajeExito = document.getElementById('mensajeExito');
            if (mensajeExito) {
                setTimeout(() => {
                    mensajeExito.style.transition = 'opacity 0.5s ease';
                    mensajeExito.style.opacity = '0';
                    setTimeout(() => mensajeExito.remove(), 500);
                }, 4000);
            }

            // ============================================
            // ADJUNTAR ARCHIVO
            // ============================================
            const btnAdjuntar = document.getElementById('btnAdjuntar');
            const inputArchivo = document.getElementById('inputArchivo');
            const archivoPreview = document.getElementById('archivoPreview');
            const archivoNombre = document.getElementById('archivoNombre');
            const btnQuitarArchivo = document.getElementById('btnQuitarArchivo');
            const textareaMensaje = document.getElementById('textareaMensaje');

            if (btnAdjuntar) {
                btnAdjuntar.addEventListener('click', () => inputArchivo.click());
            }

            if (inputArchivo) {
                inputArchivo.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        mostrarPreviewArchivo(this.files[0].name);
                    }
                });
            }

            if (btnQuitarArchivo) {
                btnQuitarArchivo.addEventListener('click', function() {
                    inputArchivo.value = '';
                    archivoPreview.classList.remove('visible');
                });
            }

            function mostrarPreviewArchivo(nombre) {
                archivoNombre.textContent = nombre;
                archivoPreview.classList.add('visible');
            }

            // ============================================
            // PASTE DE CAPTURAS (Ctrl+V)
            // ============================================
            if (textareaMensaje) {
                textareaMensaje.addEventListener('paste', function(e) {
                    const items = e.clipboardData.items;
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            e.preventDefault();
                            const blob = items[i].getAsFile();
                            
                            const dt = new DataTransfer();
                            dt.items.add(new File([blob], 'captura_' + Date.now() + '.png', { type: blob.type }));
                            
                            inputArchivo.files = dt.files;
                            mostrarPreviewArchivo('captura_' + Date.now() + '.png');
                            return;
                        }
                    }
                });
            }

            // ============================================
            // DRAG & DROP
            // ============================================
            if (textareaMensaje) {
                textareaMensaje.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#27ae60';
                    this.style.background = '#f0fdf4';
                });
                
                textareaMensaje.addEventListener('dragleave', function(e) {
                    this.style.borderColor = '#e0e0e0';
                    this.style.background = '';
                });
                
                textareaMensaje.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#e0e0e0';
                    this.style.background = '';
                    
                    if (e.dataTransfer.files.length > 0) {
                        inputArchivo.files = e.dataTransfer.files;
                        mostrarPreviewArchivo(e.dataTransfer.files[0].name);
                    }
                });
            }

            // ============================================
            // ENVIAR CON ENTER
            // ============================================
            if (textareaMensaje) {
                textareaMensaje.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        const form = document.getElementById('formComentario');
                        if (form) form.submit();
                    }
                });
            }

        });
    </script>

</body>
</html>