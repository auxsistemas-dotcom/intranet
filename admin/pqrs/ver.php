<?php
// admin/pqrs/ver.php - Detalle completo de una PQRS (Admin/Supervisor)

require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/pqrs_helpers.php';

// ============================================
// 1. VERIFICAR SESIÓN Y ROL
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id  = $_SESSION['usuario_id'];

if ($rol_usuario != 1 && $rol_usuario != 2) {
    if ($rol_usuario == 3) {
        $id_redir = intval($_GET['id'] ?? 0);
        header('Location: ../../../aplicaciones/otros/pqrs/ver.php?id=' . $id_redir);
        exit();
    }
    $_SESSION['error'] = "❌ No tienes permiso para ver esta PQRS";
    header('Location: ../../index.php');
    exit();
}

$usuario = obtenerUsuario();
$nombre_usuario = $usuario['nombre_completo'];

// ============================================
// 2. VALIDAR ID
// ============================================
$pqrs_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pqrs_id <= 0) {
    $_SESSION['error'] = "❌ PQRS no válida";
    header('Location: index.php');
    exit();
}

// ============================================
// 3. CARGAR LA PQRS (inicial)
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

// 🔑 Verificar si esta PQRS tiene vencimiento pendiente
verificarVencimientoPQRS($pdo, $pqrs_id);

// Recargar datos después de la verificación
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

// ============================================
// 3.5 VERIFICAR SI ESTÁ CERRADA (BLOQUEO TOTAL)
// ============================================
// 🔒 Si está cerrada, el ticket NO se puede editar. Solo se puede reabrir.
$ticket_cerrado = ($pqrs['estado'] === 'cerrado');

// ============================================
// 4. PROCESAR ACCIONES POST (con PRG)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 🔒 BLOQUEO BACKEND: si está cerrado, solo se permite "reabrir"
    if ($ticket_cerrado && $action !== 'reabrir') {
        $_SESSION['error'] = "🔒 Este ticket está cerrado. Solo puedes reabrirlo.";
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
            
            // Procesar archivo si viene
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
                
                $pdo->prepare("UPDATE pqrs SET actualizado_el = NOW() WHERE id = ?")
                    ->execute([$pqrs_id]);
                
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
            $estados_validos = ['abierto', 'en_proceso', 'analisis_servicio_cliente', 'cerrado'];
            
            if (!in_array($nuevo_estado, $estados_validos)) {
                $_SESSION['error'] = "❌ Estado no válido";
            } elseif ($nuevo_estado === $pqrs['estado']) {
                $_SESSION['error'] = "❌ La PQRS ya está en ese estado";
            } else {
                $estado_anterior = $pqrs['estado'];
                
                if ($nuevo_estado === 'cerrado') {
                    $stmt = $pdo->prepare("UPDATE pqrs SET estado = ?, fecha_cierre = NOW(), actualizado_el = NOW() WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE pqrs SET estado = ?, actualizado_el = NOW() WHERE id = ?");
                }
                $stmt->execute([$nuevo_estado, $pqrs_id]);
                
                registrarEventoSistema($pdo, $pqrs_id, $usuario_id, 'cambio_estado', $estado_anterior, $nuevo_estado);
                
                $_SESSION['mensaje'] = "✅ Estado cambiado a " . estadoLegible($nuevo_estado);
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // CERRAR PQRS
        // ----------------------------------------
        elseif ($action === 'cerrar') {
            if ($pqrs['estado'] === 'cerrado') {
                $_SESSION['error'] = "❌ La PQRS ya está cerrada";
            } else {
                $estado_anterior = $pqrs['estado'];
                
                $stmt = $pdo->prepare("UPDATE pqrs SET estado = 'cerrado', fecha_cierre = NOW(), actualizado_el = NOW() WHERE id = ?");
                $stmt->execute([$pqrs_id]);
                
                registrarEventoSistema($pdo, $pqrs_id, $usuario_id, 'cierre', $estado_anterior, 'cerrado');
                
                $_SESSION['mensaje'] = "✅ PQRS cerrada correctamente";
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // REABRIR PQRS
        // ----------------------------------------
        elseif ($action === 'reabrir') {
            if ($pqrs['estado'] !== 'cerrado') {
                $_SESSION['error'] = "❌ Solo se pueden reabrir PQRS cerradas";
            } else {
                $stmt = $pdo->prepare("UPDATE pqrs SET estado = 'abierto', fecha_cierre = NULL, actualizado_el = NOW() WHERE id = ?");
                $stmt->execute([$pqrs_id]);
                
                registrarEventoSistema($pdo, $pqrs_id, $usuario_id, 'reapertura', 'cerrado', 'abierto');
                
                $_SESSION['mensaje'] = "✅ PQRS reabierta. Ahora está en estado Abierto.";
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // ASIGNAR / CAMBIAR GESTOR PRINCIPAL
        // ----------------------------------------
        elseif ($action === 'asignar_principal') {
            $nuevo_gestor_id = intval($_POST['gestor_id'] ?? 0);
            
            if ($nuevo_gestor_id <= 0) {
                $_SESSION['error'] = "❌ Debes seleccionar un gestor";
            } else {
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
                    $gestor_anterior = $pqrs['gestor_principal_nombre'] ?? null;
                    
                    $stmt = $pdo->prepare("UPDATE pqrs SET gestor_principal_id = ?, fecha_asignacion = NOW(), actualizado_el = NOW() WHERE id = ?");
                    $stmt->execute([$nuevo_gestor_id, $pqrs_id]);
                    
                    $stmt = $pdo->prepare("INSERT IGNORE INTO pqrs_gestores (pqrs_id, usuario_id, asignado_por, fecha_asignacion) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$pqrs_id, $nuevo_gestor_id, $usuario_id]);
                    
                    $accion_tipo = $gestor_anterior ? 'reasignacion' : 'asignacion';
                    registrarEventoSistema($pdo, $pqrs_id, $usuario_id, $accion_tipo, $gestor_anterior, $nuevo_gestor['nombre_completo']);
                    
                    $_SESSION['mensaje'] = "✅ Gestor asignado: " . $nuevo_gestor['nombre_completo'];
                }
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // AGREGAR GESTOR SECUNDARIO
        // ----------------------------------------
        elseif ($action === 'agregar_gestor') {
            $nuevo_gestor_id = intval($_POST['gestor_id'] ?? 0);
            
            if ($nuevo_gestor_id <= 0) {
                $_SESSION['error'] = "❌ Debes seleccionar un gestor";
            } elseif ($nuevo_gestor_id == $pqrs['gestor_principal_id']) {
                $_SESSION['error'] = "❌ Ese usuario ya es el gestor principal";
            } else {
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
                    $_SESSION['error'] = "❌ Usuario no válido";
                } else {
                    $stmt = $pdo->prepare("SELECT 1 FROM pqrs_gestores WHERE pqrs_id = ? AND usuario_id = ? LIMIT 1");
                    $stmt->execute([$pqrs_id, $nuevo_gestor_id]);
                    
                    if ($stmt->fetch()) {
                        $_SESSION['error'] = "❌ Ese gestor ya está asignado a esta PQRS";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO pqrs_gestores (pqrs_id, usuario_id, asignado_por, fecha_asignacion) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$pqrs_id, $nuevo_gestor_id, $usuario_id]);
                        
                        registrarEventoSistema($pdo, $pqrs_id, $usuario_id, 'agregar_gestor', null, $nuevo_gestor['nombre_completo']);
                        
                        $_SESSION['mensaje'] = "✅ Gestor añadido: " . $nuevo_gestor['nombre_completo'];
                    }
                }
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // QUITAR GESTOR SECUNDARIO
        // ----------------------------------------
        elseif ($action === 'quitar_gestor') {
            $gestor_a_quitar = intval($_POST['gestor_id'] ?? 0);
            
            if ($gestor_a_quitar <= 0) {
                $_SESSION['error'] = "❌ Gestor no válido";
            } else {
                $stmt = $pdo->prepare("SELECT u.nombre_completo FROM usuarios u WHERE u.id = ?");
                $stmt->execute([$gestor_a_quitar]);
                $gestor_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("DELETE FROM pqrs_gestores WHERE pqrs_id = ? AND usuario_id = ?");
                $stmt->execute([$pqrs_id, $gestor_a_quitar]);
                
                if ($stmt->rowCount() > 0) {
                    registrarEventoSistema($pdo, $pqrs_id, $usuario_id, 'quitar_gestor', $gestor_info['nombre_completo'] ?? null, null);
                    $_SESSION['mensaje'] = "✅ Gestor removido";
                } else {
                    $_SESSION['error'] = "❌ No se pudo remover el gestor";
                }
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // GUARDAR FECHA LÍMITE
        // ----------------------------------------
        elseif ($action === 'guardar_fecha_limite') {
            $tipo_fecha = $_POST['tipo_fecha'] ?? 'sin_fecha';
            $fecha_anterior = $pqrs['fecha_limite'] ?? null;
            $estado_anterior = $pqrs['estado'];
            
            // ============================================
            // CASO 1: QUITAR FECHA LÍMITE
            // ============================================
            if ($tipo_fecha === 'sin_fecha') {
                $pdo->prepare("UPDATE pqrs SET fecha_limite = NULL, actualizado_el = NOW() WHERE id = ?")
                    ->execute([$pqrs_id]);
                
                if (!empty($fecha_anterior)) {
                    registrarEventoSistema(
                        $pdo, $pqrs_id, $usuario_id,
                        'fecha_limite_cancelada',
                        $fecha_anterior,
                        null
                    );
                }
                
                $_SESSION['mensaje'] = "✅ Fecha límite cancelada";
                
            // ============================================
            // CASO 2: PONER/CAMBIAR FECHA LÍMITE
            // ============================================
            } elseif ($tipo_fecha === 'con_fecha') {
                $fecha = $_POST['fecha_limite'] ?? '';
                
                if (empty($fecha)) {
                    $_SESSION['error'] = "❌ Debes seleccionar una fecha";
                } else {
                    $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha);
                    if (!$fecha_obj) {
                        $_SESSION['error'] = "❌ Fecha no válida";
                    } elseif ($fecha_obj < new DateTime('today')) {
                        $_SESSION['error'] = "❌ La fecha debe ser hoy o posterior";
                    } else {
                        $nuevo_estado = 'en_proceso';
                        $cambia_estado = ($estado_anterior !== 'en_proceso');
                        
                        if ($cambia_estado) {
                            $pdo->prepare("
                                UPDATE pqrs 
                                SET fecha_limite = ?, estado = 'en_proceso', actualizado_el = NOW() 
                                WHERE id = ?
                            ")->execute([$fecha, $pqrs_id]);
                        } else {
                            $pdo->prepare("
                                UPDATE pqrs 
                                SET fecha_limite = ?, actualizado_el = NOW() 
                                WHERE id = ?
                            ")->execute([$fecha, $pqrs_id]);
                        }
                        
                        // Caso A: estaba en análisis → REACTIVACIÓN
                        if ($estado_anterior === 'analisis_servicio_cliente') {
                            registrarEventoSistema(
                                $pdo, $pqrs_id, $usuario_id,
                                'reactivacion',
                                null,
                                $fecha,
                                "🔓 Reactivado hasta el " . date('d/m/Y', strtotime($fecha))
                            );
                            $_SESSION['mensaje'] = "✅ Ticket reactivado hasta el " . date('d/m/Y', strtotime($fecha));
                        }
                        // Caso B: ya tenía fecha → CAMBIO DE FECHA
                        elseif (!empty($fecha_anterior)) {
                            registrarEventoSistema(
                                $pdo, $pqrs_id, $usuario_id,
                                'fecha_limite_cambiada',
                                $fecha_anterior,
                                $fecha
                            );
                            $_SESSION['mensaje'] = "✅ Fecha límite cambiada a " . date('d/m/Y', strtotime($fecha));
                        }
                        // Caso C: primera vez que se pone fecha → FECHA LÍMITE
                        else {
                            registrarEventoSistema(
                                $pdo, $pqrs_id, $usuario_id,
                                'fecha_limite',
                                null,
                                $fecha
                            );
                            $_SESSION['mensaje'] = "✅ Fecha límite guardada: " . date('d/m/Y', strtotime($fecha));
                        }
                    }
                }
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }

        // ----------------------------------------
        // DEVOLVER A EN PROCESO (con o sin nueva fecha)
        // ----------------------------------------
        elseif ($action === 'devolver_a_proceso') {
            if ($pqrs['estado'] === 'cerrado') {
                $_SESSION['error'] = "❌ Debes reabrir la PQRS primero";
            } else {
                $estado_anterior = $pqrs['estado'];
                $tipo_fecha = $_POST['tipo_fecha'] ?? 'sin_fecha';
                
                $nueva_fecha = null;
                if ($tipo_fecha === 'con_fecha' && !empty($_POST['fecha_limite'])) {
                    $fecha_obj = DateTime::createFromFormat('Y-m-d', $_POST['fecha_limite']);
                    if ($fecha_obj && $fecha_obj >= new DateTime('today')) {
                        $nueva_fecha = $_POST['fecha_limite'];
                    }
                }
                
                $stmt = $pdo->prepare("
                    UPDATE pqrs 
                    SET estado = 'en_proceso', fecha_limite = ?, actualizado_el = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$nueva_fecha, $pqrs_id]);
                
                registrarEventoSistema(
                    $pdo, $pqrs_id, $usuario_id,
                    'cambio_estado',
                    $estado_anterior,
                    'en_proceso'
                );
                
                if ($nueva_fecha) {
                    registrarEventoSistema(
                        $pdo, $pqrs_id, $usuario_id,
                        'fecha_limite',
                        $pqrs['fecha_limite'] ?? null,
                        $nueva_fecha
                    );
                }
                
                $_SESSION['mensaje'] = "✅ PQRS devuelta a En Proceso" . ($nueva_fecha ? " con nueva fecha límite" : "");
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // CAMBIAR PRIORIDAD
        // ----------------------------------------
        elseif ($action === 'cambiar_prioridad') {
            $nueva_prioridad = $_POST['prioridad'] ?? '';
            $prioridades_validas = ['alta', 'media', 'baja'];
            
            if (!in_array($nueva_prioridad, $prioridades_validas)) {
                $_SESSION['error'] = "❌ Prioridad no válida";
            } elseif ($nueva_prioridad === $pqrs['prioridad']) {
                $_SESSION['error'] = "❌ La PQRS ya tiene esa prioridad";
            } else {
                $prioridad_anterior = $pqrs['prioridad'];
                
                $stmt = $pdo->prepare("UPDATE pqrs SET prioridad = ?, actualizado_el = NOW() WHERE id = ?");
                $stmt->execute([$nueva_prioridad, $pqrs_id]);
                
                registrarEventoSistema($pdo, $pqrs_id, $usuario_id, 'prioridad_cambiada', $prioridad_anterior, $nueva_prioridad);
                
                $_SESSION['mensaje'] = "✅ Prioridad cambiada a " . ucfirst($nueva_prioridad);
            }
            
            header("Location: ver.php?id=$pqrs_id");
            exit();
        }
        
        // ----------------------------------------
        // Acción desconocida
        // ----------------------------------------
        else {
            $_SESSION['error'] = "❌ Acción no reconocida";
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
// 5. RECARGAR PQRS (por si cambió en POST)
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

// Recalcular si está cerrado (por si cambió en POST)
$ticket_cerrado = ($pqrs['estado'] === 'cerrado');

// ============================================
// 6. PERMISOS
// ============================================
$puede_ver                = true;
$puede_comentar           = !$ticket_cerrado;
$puede_adjuntar           = !$ticket_cerrado;
$puede_cambiar_estado     = !$ticket_cerrado;
$puede_cerrar             = !$ticket_cerrado;
$puede_reabrir            = $ticket_cerrado;
$puede_asignar_principal  = !$ticket_cerrado;
$puede_gestionar_gestores = !$ticket_cerrado;
$puede_ver_historial      = true;

// ============================================
// 7. CARGAR GESTORES ASIGNADOS
// ============================================
$gestores_asignados = obtenerGestoresDePQRS($pdo, $pqrs_id);

// ============================================
// 8. CARGAR TODOS LOS GESTORES DISPONIBLES (para selects)
// ============================================
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.nombre_completo,
        u.usuario,
        u.cargo
    FROM usuarios u
    INNER JOIN pqrs_gestores_usuarios gu ON gu.usuario_id = u.id AND gu.activo = 1
    WHERE u.activo = 1
    ORDER BY u.nombre_completo ASC
");
$gestores_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
// 12. ESTADOS Y TIPOS PARA RENDERIZAR
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

// ============================================
// 13. RENDERIZAR HTML
// ============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pqrs['codigo']); ?> | PQRSF</title>
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

        /* ===== AVISO BLOQUEADO ===== */
        .aviso-bloqueado {
            background: linear-gradient(135deg, #e8f8f5, #d5f5e3);
            border: 2px solid #27ae60;
            border-left: 6px solid #1e8449;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(39,174,96,0.15);
        }
        .aviso-bloqueado i {
            font-size: 28px;
            color: #1e8449;
            flex-shrink: 0;
        }
        .aviso-bloqueado strong {
            font-size: 15px;
            color: #1a7a3a;
            display: block;
            margin-bottom: 3px;
        }
        .aviso-bloqueado p {
            font-size: 13px;
            color: #196f3d;
            margin: 0;
        }

        /* ===== LAYOUT PRINCIPAL ===== */
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

        .pqrs-header-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ===== BADGES ===== */
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

        /* ===== GRID DE 2 COLUMNAS ===== */
        .pqrs-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 1024px) {
            .pqrs-grid { grid-template-columns: 1fr; }
        }

        /* ===== CARD GENÉRICA ===== */
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

        /* ===== DATOS DEL CLIENTE ===== */
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
            margin-top: 15px;
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
        .accion-grupo select,
        .accion-grupo input[type="text"] {
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
        .accion-grupo select:focus,
        .accion-grupo input:focus {
            outline: none;
            border-color: #173742;
        }

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
        .btn-success { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(39,174,96,0.3); }
        .btn-danger { background: linear-gradient(135deg, #c0392b, #e74c3c); color: white; }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(231,76,60,0.3); }
        .btn-warning { background: linear-gradient(135deg, #d68910, #f39c12); color: white; }
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(243,156,18,0.3); }

        /* ===== BOTÓN REABRIR DESTACADO ===== */
        .btn-reabrir {
            background: linear-gradient(135deg, #1e8449, #27ae60);
            color: white;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(39,174,96,0.4);
            transition: all 0.3s ease;
        }
        .btn-reabrir:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(39,174,96,0.5);
        }

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
        .gestor-info-mini { flex: 1; min-width: 0; }
        .gestor-info-mini .nombre {
            font-weight: 600;
            font-size: 13px;
            color: #12232b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gestor-info-mini .rol {
            font-size: 11px;
            color: #7f8c8d;
        }
        .btn-remove-mini {
            background: transparent;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.2s;
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
        .timeline-item.sistema .timeline-icono {
            background: #7f8c8d;
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
        .timeline-icono.sistema-icono {
            background: #7f8c8d;
        }
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
        .timeline-autor.sistema-autor {
            color: #7f8c8d;
            font-style: italic;
        }
        .timeline-fecha {
            font-size: 11px;
            color: #95a5a6;
            white-space: nowrap;
        }
        .timeline-mensaje {
            font-size: 13px;
            color: #2c3e50;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .timeline-item.sistema .timeline-mensaje {
            font-size: 12.5px;
            color: #7f8c8d;
        }

        .timeline-adjunto {
            margin-top: 8px;
            display: inline-block;
        }
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
        .timeline-adjunto-pdf:hover {
            border-color: #e74c3c;
            background: #fef5f5;
        }
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
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 12px;
            color: #2c3e50;
            display: none;
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

        /* ===== BADGE DE FECHA LÍMITE ===== */
        .badge-fecha {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .badge-fecha.fecha-ok {
            background: #eafaf1; color: #196f3d; border: 1px solid #27ae60;
        }
        .badge-fecha.fecha-proxima {
            background: #fef9e7; color: #7f6000; border: 1px solid #f39c12;
        }
        .badge-fecha.fecha-hoy {
            background: #fadbd8; color: #922b21; border: 1px solid #e74c3c;
            animation: pulse 2s infinite;
        }
        .badge-fecha.fecha-vencida {
            background: #f5b7b1; color: #7b241c; border: 1px solid #c0392b;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
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
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
                <p class="logo-subtitle">| PQRSF · Detalle</p>
            </div>
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al listado
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

        <!-- ===== AVISO: TICKET CERRADO ===== -->
        <?php if ($ticket_cerrado): ?>
            <div class="aviso-bloqueado">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>Este ticket está cerrado</strong>
                    <p>No puedes editar ni comentar. Solo puedes reabrirlo para volver a trabajar en él.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- ===== HEADER DE LA PQRS ===== -->
        <?php
        $estado_info = $estados[$pqrs['estado']] ?? $estados['abierto'];
        $prioridad_info = $prioridades[$pqrs['prioridad']] ?? $prioridades['media'];
        $info_limite = infoFechaFimite($pqrs['fecha_limite'] ?? null);
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
                <?php if ($info_limite): ?>
                    <span class="badge-fecha <?php echo $info_limite['clase']; ?>">
                        <i class="fas fa-clock"></i>
                        <?php echo $info_limite['texto']; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== GRID DE 2 COLUMNAS ===== -->
        <div class="pqrs-grid">

            <!-- ===== COLUMNA IZQUIERDA: DATOS ===== -->
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
                                <div class="timeline-item <?php echo $es_sistema ? 'sistema' : ''; ?>">
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
                                                <a href="../../uploads/pqrs/<?php echo htmlspecialchars($m['archivo']); ?>" 
                                                    target="_blank" class="timeline-adjunto">
                                                        <img src="../../uploads/pqrs/<?php echo htmlspecialchars($m['archivo']); ?>"  
                                                         alt="<?php echo htmlspecialchars($m['archivo_nombre_original']); ?>"
                                                         loading="lazy">
                                                </a>
                                            <?php else: ?>
                                                <a href="../../uploads/pqrs/<?php echo htmlspecialchars($m['archivo']); ?>" 
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
                    <?php if ($puede_comentar): ?>
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
                                   accept="image/*,application/pdf" style="display: none;">
                            
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
                                Este ticket está cerrado. Reábrelo para poder comentar.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== COLUMNA DERECHA: ACCIONES ===== -->
            <div>

                <!-- GESTORES ASIGNADOS -->
                <div class="card">
                    <h3><i class="fas fa-users"></i> Gestores asignados</h3>
                    
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
                            ?>
                                <div class="gestor-card <?php echo $g['es_principal'] ? 'principal' : ''; ?>">
                                    <div class="gestor-avatar"><?php echo $iniciales; ?></div>
                                    <div class="gestor-info-mini">
                                        <div class="nombre"><?php echo htmlspecialchars($g['nombre_completo']); ?></div>
                                        <div class="rol">
                                            <?php echo $g['es_principal'] ? '🌟 Principal' : 'Colaborador'; ?>
                                            <?php if (!empty($g['cargo'])): ?>
                                                · <?php echo htmlspecialchars($g['cargo']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!$g['es_principal'] && $puede_gestionar_gestores): ?>
                                        <form method="POST" onsubmit="return confirm('¿Quitar a <?php echo htmlspecialchars(addslashes($g['nombre_completo'])); ?> como colaborador?');" style="margin: 0;">
                                            <input type="hidden" name="action" value="quitar_gestor">
                                            <input type="hidden" name="gestor_id" value="<?php echo $g['id']; ?>">
                                            <button type="submit" class="btn-remove-mini" title="Quitar gestor">
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

                        <?php if ($ticket_cerrado): ?>

                            <!-- 🔒 SOLO REABRIR CUANDO ESTÁ CERRADO -->
                            <div class="empty-info" style="padding: 20px; background: #f0fdf4; border-radius: 10px; border: 2px solid #d5f5e3; margin-bottom: 10px;">
                                <i class="fas fa-lock" style="color: #27ae60;"></i>
                                <p style="color: #1a7a3a; font-weight: 600;">Ticket cerrado</p>
                                <p style="font-size: 12px; margin-top: 4px; color: #7f8c8d;">
                                    Para editar o comentar debes reabrirlo
                                </p>
                            </div>

                            <form method="POST" onsubmit="return confirm('¿Reabrir esta PQRS? Volverá al estado Abierto.');">
                                <input type="hidden" name="action" value="reabrir">
                                <button type="submit" class="btn btn-reabrir">
                                    <i class="fas fa-lock-open"></i> Reabrir PQRS
                                </button>
                            </form>

                        <?php else: ?>

                            <!-- CAMBIAR ESTADO -->
                            <div class="accion-grupo">
                                <label>Cambiar estado</label>
                                <form method="POST" style="display: flex; gap: 6px;">
                                    <input type="hidden" name="action" value="cambiar_estado">
                                    <select name="nuevo_estado" style="flex: 1;">
                                        <?php foreach ($estados as $key => $info): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $pqrs['estado'] === $key ? 'disabled' : ''; ?>>
                                                <?php echo $info['texto']; ?><?php echo $pqrs['estado'] === $key ? ' (actual)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 8px 12px;">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </div>

                            <!-- CAMBIAR PRIORIDAD -->
                            <div class="accion-grupo">
                                <label>Cambiar prioridad</label>
                                <form method="POST" style="display: flex; gap: 6px;">
                                    <input type="hidden" name="action" value="cambiar_prioridad">
                                    <select name="prioridad" style="flex: 1;">
                                        <?php foreach ($prioridades as $key => $info): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $pqrs['prioridad'] === $key ? 'disabled' : ''; ?>>
                                                <?php echo $info['texto']; ?><?php echo $pqrs['prioridad'] === $key ? ' (actual)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-warning" style="width: auto; padding: 8px 12px;">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </div>

                            <!-- ASIGNAR GESTOR PRINCIPAL -->
                            <div class="accion-grupo">
                                <label>Gestor principal</label>
                                <form method="POST" style="display: flex; gap: 6px;">
                                    <input type="hidden" name="action" value="asignar_principal">
                                    <select name="gestor_id" style="flex: 1;">
                                        <option value="">— Seleccionar —</option>
                                        <?php foreach ($gestores_disponibles as $gd): ?>
                                            <option value="<?php echo $gd['id']; ?>" <?php echo $pqrs['gestor_principal_id'] == $gd['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($gd['nombre_completo']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 8px 12px;">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                </form>
                            </div>

                            <!-- AÑADIR GESTOR SECUNDARIO -->
                            <?php 
                            $ids_asignados = array_column($gestores_asignados, 'id');
                            $gestores_para_agregar = array_filter($gestores_disponibles, function($g) use ($ids_asignados) {
                                return !in_array($g['id'], $ids_asignados);
                            });
                            ?>
                            <?php if (!empty($gestores_para_agregar)): ?>
                                <div class="accion-grupo">
                                    <label>Añadir colaborador</label>
                                    <form method="POST" style="display: flex; gap: 6px;">
                                        <input type="hidden" name="action" value="agregar_gestor">
                                        <select name="gestor_id" style="flex: 1;">
                                            <option value="">— Seleccionar —</option>
                                            <?php foreach ($gestores_para_agregar as $gd): ?>
                                                <option value="<?php echo $gd['id']; ?>">
                                                    <?php echo htmlspecialchars($gd['nombre_completo']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary" style="width: auto; padding: 8px 12px;">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <!-- FECHA LÍMITE -->
                            <div class="accion-grupo">
                                <label><i class="fas fa-clock"></i> Fecha límite</label>
                                
                                <?php if ($pqrs['fecha_limite']): ?>
                                    <div style="padding: 8px 10px; background: white; border-radius: 6px; margin-bottom: 8px; font-size: 12px; color: #445960;">
                                        📅 Actual: <strong><?php echo date('d/m/Y', strtotime($pqrs['fecha_limite'])); ?></strong>
                                        <?php if ($info_limite): ?>
                                            <span style="color: <?php echo $info_limite['estado'] === 'verde' ? '#27ae60' : ($info_limite['estado'] === 'amarillo' ? '#f39c12' : '#e74c3c'); ?>; margin-left: 6px;">
                                                (<?php echo $info_limite['texto']; ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="guardar_fecha_limite">
                                    
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; padding: 6px 8px; background: white; border-radius: 6px; border: 2px solid #e0e0e0;">
                                            <input type="radio" name="tipo_fecha" value="sin_fecha" <?php echo empty($pqrs['fecha_limite']) ? 'checked' : ''; ?> onchange="toggleFechaInput()">
                                            <span>Sin fecha límite</span>
                                        </label>
                                        
                                        <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; padding: 6px 8px; background: white; border-radius: 6px; border: 2px solid #e0e0e0;">
                                            <input type="radio" name="tipo_fecha" value="con_fecha" <?php echo !empty($pqrs['fecha_limite']) ? 'checked' : ''; ?> onchange="toggleFechaInput()">
                                            <span>Con fecha límite:</span>
                                        </label>
                                        
                                        <input type="date" 
                                               name="fecha_limite" 
                                               id="inputFecha" 
                                               value="<?php echo htmlspecialchars($pqrs['fecha_limite'] ?? ''); ?>"
                                               min="<?php echo date('Y-m-d'); ?>"
                                               style="padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; <?php echo empty($pqrs['fecha_limite']) ? 'display: none;' : ''; ?>">
                                        
                                        <button type="submit" class="btn btn-primary" style="padding: 8px 12px; font-size: 12px;">
                                            <i class="fas fa-check"></i> Guardar
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- BOTÓN DEVOLVER A PROCESO (solo si está en análisis por vencimiento) -->
                            <?php if ($pqrs['estado'] === 'analisis_servicio_cliente' && $pqrs['fecha_limite']): ?>
                                <div class="accion-grupo" style="background: #fef9e7; border-color: #f39c12;">
                                    <label><i class="fas fa-undo"></i> Devolver a proceso</label>
                                    <form method="POST" id="formDevolver">
                                        <input type="hidden" name="action" value="devolver_a_proceso">
                                        
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; padding: 6px 8px; background: white; border-radius: 6px; border: 2px solid #e0e0e0;">
                                                <input type="radio" name="tipo_fecha" value="sin_fecha" checked onchange="toggleFechaDevolver()">
                                                <span>Sin nueva fecha</span>
                                            </label>
                                            
                                            <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; padding: 6px 8px; background: white; border-radius: 6px; border: 2px solid #e0e0e0;">
                                                <input type="radio" name="tipo_fecha" value="con_fecha" onchange="toggleFechaDevolver()">
                                                <span>Con nueva fecha:</span>
                                            </label>
                                            
                                            <input type="date" 
                                                   name="fecha_limite" 
                                                   id="inputFechaDevolver"
                                                   min="<?php echo date('Y-m-d'); ?>"
                                                   style="padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; display: none;">
                                            
                                            <button type="submit" class="btn btn-warning" style="padding: 8px 12px; font-size: 12px;">
                                                <i class="fas fa-undo"></i> Devolver a En Proceso
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <!-- CERRAR PQRS -->
                            <form method="POST" onsubmit="return confirm('¿Cerrar esta PQRS? Una vez cerrada no podrás editarla sin reabrirla.');">
                                <input type="hidden" name="action" value="cerrar">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle"></i> Cerrar PQRS
                                </button>
                            </form>

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
                            <div class="dato-label">Fecha cierre</div>
                            <div class="dato-valor">
                                <?php echo $pqrs['fecha_cierre'] 
                                    ? date('d/m/Y H:i', strtotime($pqrs['fecha_cierre'])) 
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
            // DRAG & DROP DE ARCHIVOS
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
            // TOGGLE FECHA LÍMITE
            // ============================================
            window.toggleFechaInput = function() {
                const input = document.getElementById('inputFecha');
                const form = input ? input.closest('form') : null;
                if (!form) return;
                
                const tipo = form.querySelector('input[name="tipo_fecha"]:checked');
                if (input && tipo) {
                    input.style.display = tipo.value === 'con_fecha' ? 'block' : 'none';
                }
            };
            
            window.toggleFechaDevolver = function() {
                const form = document.getElementById('formDevolver');
                if (!form) return;
                const tipo = form.querySelector('input[name="tipo_fecha"]:checked');
                const input = document.getElementById('inputFechaDevolver');
                if (input && tipo) {
                    input.style.display = tipo.value === 'con_fecha' ? 'block' : 'none';
                }
            };

            // ============================================
            // ENVIAR CON ENTER (Shift+Enter = nueva línea)
            // ============================================
            if (textareaMensaje) {
                textareaMensaje.keydown = null;
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