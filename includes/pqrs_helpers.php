<?php
/**
 * pqrs_helpers.php
 * Funciones auxiliares para el sistema de PQRS
 * 
 * Incluye:
 * - registrarEventoSistema()   → Inserta eventos automáticos en el timeline
 * - textoEvento()              → Genera el texto legible de cada evento
 * - iconoEvento()              → Devuelve la clase de icono FontAwesome
 * - colorEvento()              → Devuelve la clase CSS de color
 * - estadoLegible()            → Convierte 'en_proceso' → 'En Proceso'
 */

// ============================================
// 1. REGISTRAR EVENTO DEL SISTEMA
// ============================================
/**
 * Inserta un evento automático en el timeline de la PQRS.
 * Se guarda como un mensaje con tipo='sistema'.
 *
 * @param PDO    $pdo            Conexión a la base de datos
 * @param int    $pqrs_id        ID de la PQRS
 * @param int    $usuario_id     ID del usuario que provocó la acción
 * @param string $accion         Tipo de acción: 'creacion', 'cambio_estado', 'asignacion', etc.
 * @param string|null $valor_anterior Valor previo (ej: 'abierto')
 * @param string|null $valor_nuevo    Valor nuevo (ej: 'en_proceso')
 * @param string|null $detalle_extra  Texto adicional opcional
 * @return bool
 */
function registrarEventoSistema($pdo, $pqrs_id, $usuario_id, $accion, $valor_anterior = null, $valor_nuevo = null, $detalle_extra = null) {
    try {
        // Generar el texto legible
        $texto = textoEvento($accion, $valor_anterior, $valor_nuevo, $detalle_extra);

        $stmt = $pdo->prepare("
            INSERT INTO pqrs_mensajes 
                (pqrs_id, usuario_id, mensaje, tipo, accion, valor_anterior, valor_nuevo, leido, creado_el)
            VALUES 
                (?, ?, ?, 'sistema', ?, ?, ?, 1, NOW())
        ");
        
        return $stmt->execute([
            $pqrs_id,
            $usuario_id,
            $texto,
            $accion,
            $valor_anterior,
            $valor_nuevo
        ]);
        
    } catch (PDOException $e) {
        // No romper el flujo si falla el log
        error_log("Error al registrar evento PQRS: " . $e->getMessage());
        return false;
    }
}

// ============================================
// 2. GENERAR TEXTO LEGIBLE DEL EVENTO
// ============================================
/**
 * Convierte una acción + valores en un texto legible.
 *
 * @param string $accion
 * @param string|null $va   Valor anterior
 * @param string|null $vn   Valor nuevo
 * @param string|null $extra Detalle extra
 * @return string
 */
function textoEvento($accion, $va = null, $vn = null, $extra = null) {
    switch ($accion) {
        case 'creacion':
            return '📝 PQRS creada desde Google Forms';
        
        case 'asignacion':
            return "👤 Asignada a " . ($vn ?? 'un gestor');
        
        case 'reasignacion':
            return "🔄 Gestor cambiado: " . ($va ?? '—') . " → " . ($vn ?? '—');
        
        case 'cambio_estado':
            $estado_va = estadoLegible($va);
            $estado_vn = estadoLegible($vn);
            return "🔄 Estado: {$estado_va} → {$estado_vn}";
        
        case 'agregar_gestor':
            return "➕ Añadido gestor: " . ($vn ?? '—');
        
        case 'quitar_gestor':
            return "➖ Removido gestor: " . ($va ?? '—');
        
        case 'correo_enviado':
            return "📧 Correo enviado al cliente";
        
        case 'correo_error':
            return "❌ Error al enviar correo" . ($extra ? ": {$extra}" : '');
        
        case 'adjunto_subido':
            return "📎 Archivo adjuntado: " . ($vn ?? '—');
        
        case 'adjunto_eliminado':
            return "🗑️ Archivo eliminado: " . ($va ?? '—');
        
        case 'cierre':
            return "✅ PQRS cerrada";
        
        case 'reapertura':
            return "🔓 PQRS reabierta";

        case 'fecha_limite':
            $fecha_legible = $vn ? date('d/m/Y', strtotime($vn)) : '—';
            return "⏰ Fecha límite: {$fecha_legible}";
        
        case 'fecha_limite_cambiada':
            $va_legible = $va ? date('d/m/Y', strtotime($va)) : '—';
            $vn_legible = $vn ? date('d/m/Y', strtotime($vn)) : '—';
            return "⏰ Fecha límite cambiada: {$va_legible} → {$vn_legible}";
        
        case 'fecha_limite_cancelada':
            return "⏰ Fecha límite cancelada";
        
        case 'vencimiento':
            // El texto viene en $extra porque ya trae la fecha formateada
            return $extra ?? '⏰ Plazo vencido';
        
        case 'reactivacion':
            $fecha_legible = $vn ? date('d/m/Y', strtotime($vn)) : '—';
            return "🔓 Reactivado hasta el {$fecha_legible}";
        
        case 'prioridad_cambiada':
            return "⚡ Prioridad cambiada: " . ucfirst($va ?? '—') . " → " . ucfirst($vn ?? '—');
        
        default:
            return $extra ?? "Evento: {$accion}";
    }
}

// ============================================
// 3. ICONO PARA CADA TIPO DE EVENTO
// ============================================
/**
 * Devuelve la clase del icono FontAwesome según la acción.
 *
 * @param string $accion
 * @return string
 */
function iconoEvento($accion) {
    $iconos = [
        'creacion'          => 'fa-file-alt',
        'asignacion'        => 'fa-user-check',
        'reasignacion'      => 'fa-exchange-alt',
        'cambio_estado'     => 'fa-sync-alt',
        'agregar_gestor'    => 'fa-user-plus',
        'quitar_gestor'     => 'fa-user-minus',
        'correo_enviado'    => 'fa-envelope',
        'correo_error'      => 'fa-exclamation-triangle',
        'adjunto_subido'    => 'fa-paperclip',
        'adjunto_eliminado' => 'fa-trash',
        'cierre'            => 'fa-check-circle',
        'reapertura'        => 'fa-lock-open',
        'fecha_limite'           => 'fa-clock',
        'fecha_limite_cambiada'  => 'fa-clock',
        'fecha_limite_cancelada' => 'fa-clock',
        'vencimiento'            => 'fa-hourglass-end',
        'reactivacion'           => 'fa-lock-open',
        'prioridad_cambiada'=> 'fa-bolt',
    ];
    
    return $iconos[$accion] ?? 'fa-info-circle';
}

// ============================================
// 4. COLOR PARA CADA TIPO DE EVENTO
// ============================================
/**
 * Devuelve la clase CSS de color según la acción.
 * Usado para colorear el icono del evento en el timeline.
 *
 * @param string $accion
 * @return string
 */
function colorEvento($accion) {
    $colores = [
        'creacion'          => 'evento-info',
        'asignacion'        => 'evento-success',
        'reasignacion'      => 'evento-info',
        'cambio_estado'     => 'evento-info',
        'agregar_gestor'    => 'evento-success',
        'quitar_gestor'     => 'evento-warning',
        'correo_enviado'    => 'evento-success',
        'correo_error'      => 'evento-danger',
        'adjunto_subido'    => 'evento-info',
        'adjunto_eliminado' => 'evento-warning',
        'cierre'            => 'evento-success',
        'reapertura'        => 'evento-info',
        'fecha_limite'      => 'evento-info',
        'prioridad_cambiada'=> 'evento-warning',
    ];
    
    return $colores[$accion] ?? 'evento-info';
}

// ============================================
// 5. CONVERTIR ESTADO A TEXTO LEGIBLE
// ============================================
/**
 * Convierte el estado técnico a texto legible.
 * Ej: 'en_proceso' → 'En Proceso'
 *
 * @param string|null $estado
 * @return string
 */
function estadoLegible($estado) {
    if (empty($estado)) return '—';
    
    $estados = [
        'abierto'                    => 'Abierto',
        'en_proceso'                 => 'En Proceso',
        'analisis_servicio_cliente'  => 'Análisis Serv. Cliente',
        'cerrado'                    => 'Cerrado',
    ];
    
    return $estados[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}

// ============================================
// 6. VERIFICAR SI UN USUARIO ES GESTOR DE UNA PQRS
// ============================================
/**
 * Devuelve true si el usuario es gestor (principal o secundario) de la PQRS.
 *
 * @param PDO $pdo
 * @param int $pqrs_id
 * @param int $usuario_id
 * @return bool
 */
function esGestorDePQRS($pdo, $pqrs_id, $usuario_id) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM pqrs_gestores 
        WHERE pqrs_id = ? AND usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$pqrs_id, $usuario_id]);
    if ($stmt->fetch()) return true;
    
    // Verificar si es principal (aunque no esté en pqrs_gestores)
    $stmt = $pdo->prepare("
        SELECT 1 FROM pqrs 
        WHERE id = ? AND gestor_principal_id = ?
        LIMIT 1
    ");
    $stmt->execute([$pqrs_id, $usuario_id]);
    return (bool) $stmt->fetch();
}

// ============================================
// 7. OBTENER GESTORES DE UNA PQRS
// ============================================
/**
 * Devuelve la lista de gestores asignados a una PQRS.
 * Incluye al principal primero, luego los secundarios.
 *
 * @param PDO $pdo
 * @param int $pqrs_id
 * @return array Lista de gestores con [id, nombre_completo, es_principal, fecha_asignacion]
 */
function obtenerGestoresDePQRS($pdo, $pqrs_id) {
    // Obtener el principal
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.nombre_completo,
            u.usuario,
            u.email,
            u.cargo,
            1 AS es_principal,
            p.fecha_asignacion,
            NULL AS asignado_por,
            NULL AS asignado_por_nombre,
            NULL AS asignado_por_usuario
        FROM pqrs p
        INNER JOIN usuarios u ON p.gestor_principal_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pqrs_id]);
    $principal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener los secundarios (con info de quién los añadió)
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.nombre_completo,
            u.usuario,
            u.email,
            u.cargo,
            0 AS es_principal,
            pg.fecha_asignacion,
            pg.asignado_por,
            u2.nombre_completo AS asignado_por_nombre,
            u2.usuario          AS asignado_por_usuario
        FROM pqrs_gestores pg
        INNER JOIN usuarios u ON pg.usuario_id = u.id
        LEFT JOIN usuarios u2 ON pg.asignado_por = u2.id
        WHERE pg.pqrs_id = ?
          AND u.id != (SELECT gestor_principal_id FROM pqrs WHERE id = ?)
        ORDER BY pg.fecha_asignacion ASC
    ");
    $stmt->execute([$pqrs_id, $pqrs_id]);
    $secundarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $resultado = [];
    if ($principal) $resultado[] = $principal;
    foreach ($secundarios as $s) $resultado[] = $s;
    
    return $resultado;
}

// ============================================
// 8. MARCAR MENSAJES COMO LEÍDOS
// ============================================
/**
 * Marca todos los mensajes NO leídos de una PQRS como leídos por el usuario.
 * Solo actualiza los que no son del propio usuario.
 *
 * @param PDO $pdo
 * @param int $pqrs_id
 * @param int $usuario_id
 * @return int Cantidad de mensajes actualizados
 */
function marcarMensajesLeidos($pdo, $pqrs_id, $usuario_id) {
    $stmt = $pdo->prepare("
        UPDATE pqrs_mensajes 
        SET leido = 1 
        WHERE pqrs_id = ? 
          AND usuario_id != ?
          AND leido = 0
    ");
    $stmt->execute([$pqrs_id, $usuario_id]);
    return $stmt->rowCount();
}

// ============================================
// 9. PROCESAR ARCHIVO SUBIDO
// ============================================
/**
 * Procesa la subida de un archivo (imagen o PDF).
 * Guarda en: uploads/pqrs/{AÑO}/{CODIGO_PQRS}/{archivo_unico}
 * 
 * Sirve para el admin Y para el gestor (ambos usan esta misma función).
 *
 * @param array  $file        $_FILES['archivo']
 * @param string $codigo_pqrs Código de la PQRS (ej: PQRS-2026-00047)
 * @return array ['archivo' => ..., 'archivo_tipo' => ..., 'archivo_nombre_original' => ...]
 *               o ['error' => 'mensaje']
 */
function procesarArchivoSubido($file, $codigo_pqrs) {
    
    // Verificar errores de subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE   => 'El archivo es demasiado grande',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario',
            UPLOAD_ERR_PARTIAL    => 'La subida se interrumpió',
            UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'No hay carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo',
        ];
        return ['error' => $errores[$file['error']] ?? 'Error desconocido al subir'];
    }
    
    // Tipos permitidos
    $tipos_permitidos = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf'
    ];
    
    // Tamaño máximo: 10 MB
    $tamaño_maximo = 10 * 1024 * 1024;
    if ($file['size'] > $tamaño_maximo) {
        return ['error' => 'El archivo supera los 10 MB'];
    }
    
    // Detectar MIME real (no confiar en el del cliente)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_real = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_real, $tipos_permitidos)) {
        return ['error' => 'Tipo de archivo no permitido. Solo imágenes y PDF.'];
    }
    
    // ============================================
    // 📁 CONSTRUIR RUTA: uploads/pqrs/{AÑO}/{CODIGO}/
    // ============================================
    
    $año = date('Y');
    $codigo_limpio = preg_replace('/[^A-Za-z0-9\-_]/', '_', $codigo_pqrs);
    
    // 🔑 NUEVA RUTA: uploads/pqrs/ en la raíz del proyecto
    // Como este archivo está en includes/, subimos 1 nivel a la raíz
    $ruta_base = __DIR__ . '/../uploads/pqrs/';
    
    $ruta_año = $ruta_base . $año . '/';
    if (!is_dir($ruta_año)) {
        if (!mkdir($ruta_año, 0755, true)) {
            return ['error' => 'No se pudo crear la carpeta del año'];
        }
    }
    
    $ruta_pqrs = $ruta_año . $codigo_limpio . '/';
    if (!is_dir($ruta_pqrs)) {
        if (!mkdir($ruta_pqrs, 0755, true)) {
            return ['error' => 'No se pudo crear la carpeta de la PQRS'];
        }
    }
    
    // ============================================
    // 📄 GENERAR NOMBRE ÚNICO
    // ============================================
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extension = preg_replace('/[^a-z0-9]/', '', $extension);
    $nombre_unico = uniqid('', true) . '_' . time() . '.' . $extension;
    $ruta_completa = $ruta_pqrs . $nombre_unico;
    
    // ============================================
    // 💾 MOVER ARCHIVO
    // ============================================
    
    if (!move_uploaded_file($file['tmp_name'], $ruta_completa)) {
        return ['error' => 'No se pudo guardar el archivo en el servidor'];
    }
    
    // Retornar ruta relativa para guardar en BD: 2026/PQRS-2026-00047/archivo.pdf
    $ruta_relativa = $año . '/' . $codigo_limpio . '/' . $nombre_unico;
    
    return [
        'archivo'                 => $ruta_relativa,
        'archivo_tipo'            => $mime_real,
        'archivo_nombre_original' => $file['name'],
    ];
}


// ============================================
// 10. VERIFICAR Y APLICAR VENCIMIENTO DE PQRS
// ============================================
/**
 * Verifica si una PQRS tiene fecha_limite vencida.
 * Si venció y no está cerrada ni en análisis → la pasa a 'analisis_servicio_cliente'
 * y registra un evento en el timeline.
 */
function verificarVencimientoPQRS($pdo, $pqrs_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, estado, fecha_limite, codigo
            FROM pqrs
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$pqrs_id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$p) return false;
        if (empty($p['fecha_limite'])) return false;
        if ($p['estado'] === 'cerrado') return false;
        if ($p['estado'] === 'analisis_servicio_cliente') return false;
        
        // 🔑 Solo usar los primeros 10 caracteres (YYYY-MM-DD) para evitar duplicar la hora
        $fecha_solo_dia = substr($p['fecha_limite'], 0, 10);
        $fecha_limite_ts = strtotime($fecha_solo_dia . ' 00:00:00') + 86400;
        $ahora = time();
        
        if ($ahora >= $fecha_limite_ts) {
            // 🔑 Aplicar vencimiento
            $pdo->prepare("
                UPDATE pqrs 
                SET estado = 'analisis_servicio_cliente', actualizado_el = NOW() 
                WHERE id = ?
            ")->execute([$pqrs_id]);
            
            // Evento: "⏰ Venció el 30/09/2026"
            $fecha_legible = date('d/m/Y', strtotime($p['fecha_limite']));
            registrarEventoSistema(
                $pdo,
                $pqrs_id,
                0,
                'vencimiento',
                $p['estado'],
                'analisis_servicio_cliente',
                "⏰ Venció el {$fecha_legible}"
            );
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error al verificar vencimiento PQRS: " . $e->getMessage());
        return false;
    }
}

// ============================================
// 11. APLICAR VENCIMIENTOS EN LOTE
// ============================================
/**
 * Recorre todas las PQRS con fecha_limite vencida y las pasa a análisis.
 */
function aplicarVencimientosPendientes($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id 
            FROM pqrs
            WHERE fecha_limite IS NOT NULL
              AND DATE(fecha_limite) < CURDATE()
              AND estado NOT IN ('cerrado', 'analisis_servicio_cliente')
        ");
        $vencidas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $actualizadas = 0;
        foreach ($vencidas as $id) {
            if (verificarVencimientoPQRS($pdo, $id)) {
                $actualizadas++;
            }
        }
        return $actualizadas;
        
    } catch (Exception $e) {
        error_log("Error al aplicar vencimientos: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// 12. CALCULAR DÍAS RESTANTES PARA UNA PQRS
// ============================================
/**
 * Devuelve información sobre la fecha límite de una PQRS.
 */
function infoFechaFimite($fecha_limite) {
    if (empty($fecha_limite)) return null;
    
    $hoy = new DateTime('today');
    $limite = new DateTime($fecha_limite);
    $diff = $hoy->diff($limite);
    $dias = (int) $diff->format('%r%a');
    
    if ($dias < 0) {
        return [
            'dias'   => $dias,
            'estado' => 'vencido',
            'texto'  => 'Vencida hace ' . abs($dias) . ' día' . (abs($dias) != 1 ? 's' : ''),
            'clase'  => 'fecha-vencida'
        ];
    } elseif ($dias == 0) {
        return [
            'dias'   => 0,
            'estado' => 'rojo',
            'texto'  => 'Vence hoy',
            'clase'  => 'fecha-hoy'
        ];
    } elseif ($dias == 1) {
        return [
            'dias'   => 1,
            'estado' => 'amarillo',
            'texto'  => 'Vence mañana',
            'clase'  => 'fecha-proxima'
        ];
    } elseif ($dias <= 2) {
        return [
            'dias'   => $dias,
            'estado' => 'amarillo',
            'texto'  => "Vence en {$dias} días",
            'clase'  => 'fecha-proxima'
        ];
    } else {
        return [
            'dias'   => $dias,
            'estado' => 'verde',
            'texto'  => "Vence en {$dias} días",
            'clase'  => 'fecha-ok'
        ];
    }
}