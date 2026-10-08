<?php
// includes/pqrs_consecutivos.php
// Helper para generar códigos únicos de PQRS

/**
 * Genera el código único para una PQRS.
 * 
 * Formato: PREFIJO-YYYYMMDD-CONSECUTIVO
 * Ejemplo: R-20260921-1
 * 
 * @param PDO $pdo Conexión a la BD
 * @param string $tipo_solicitud Tipo del Form (ej: 'RECLAMO')
 * @param string $marca_temporal Marca temporal del Form (ej: 'Mon Sep 21 2026 08:56:54 GMT-0500 (hora estándar de Colombia)')
 * @return array ['codigo' => string, 'fecha_radicacion' => string]
 * @throws Exception Si el tipo no es válido
 */
function generarCodigoPQRS($pdo, $tipo_solicitud, $marca_temporal) {
    // Mapeo de prefijos por tipo
    $prefijos = [
        'PETICION'     => 'P',
        'QUEJA'        => 'Q',
        'RECLAMO'      => 'R',
        'SUGERENCIA'   => 'S',
        'FELICITACION' => 'F',
    ];
    
    // Normalizar tipo (mayúsculas, sin espacios)
    $tipo = strtoupper(trim($tipo_solicitud));
    
    if (!isset($prefijos[$tipo])) {
        throw new Exception("Tipo de solicitud no válido: $tipo");
    }
    
    $prefijo = $prefijos[$tipo];
    
    // ✅ Limpiar el string: quitar el paréntesis con el texto en español
    // Ejemplo: "Mon Sep 21 2026 08:56:54 GMT-0500 (hora estándar de Colombia)"
    //       → "Mon Sep 21 2026 08:56:54 GMT-0500"
    $marca_temporal_limpia = preg_replace('/\s*\([^)]*\)/', '', $marca_temporal);
    $marca_temporal_limpia = trim($marca_temporal_limpia);
    
    // Extraer la fecha de la marca temporal (formato YYYY-MM-DD)
    $timestamp = strtotime($marca_temporal_limpia);
    if ($timestamp === false) {
        throw new Exception("Marca temporal inválida: $marca_temporal");
    }
    
    $fecha_radicacion = date('Y-m-d', $timestamp);
    $fecha_codigo = date('Ymd', $timestamp);
    
    // Insertar o incrementar el consecutivo del día + tipo
    $stmt = $pdo->prepare("
        INSERT INTO pqrs_consecutivos (fecha, tipo_solicitud, ultimo_consecutivo)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE ultimo_consecutivo = ultimo_consecutivo + 1
    ");
    $stmt->execute([$fecha_radicacion, $tipo]);
    
    // Obtener el consecutivo actual
    $stmt = $pdo->prepare("
        SELECT ultimo_consecutivo 
        FROM pqrs_consecutivos 
        WHERE fecha = ? AND tipo_solicitud = ?
    ");
    $stmt->execute([$fecha_radicacion, $tipo]);
    $consecutivo = (int)$stmt->fetchColumn();
    
    // Generar el código final
    $codigo = $prefijo . '-' . $fecha_codigo . '-' . $consecutivo;
    
    return [
        'codigo' => $codigo,
        'fecha_radicacion' => $fecha_radicacion,
    ];
}


/**
 * Obtiene el prefijo correspondiente a un tipo de solicitud.
 * 
 * @param string $tipo_solicitud
 * @return string|null Prefijo (P/Q/R/S/F) o null si no es válido
 */
function obtenerPrefijoPQRS($tipo_solicitud) {
    $prefijos = [
        'PETICION'     => 'P',
        'QUEJA'        => 'Q',
        'RECLAMO'      => 'R',
        'SUGERENCIA'   => 'S',
        'FELICITACION' => 'F',
    ];
    
    $tipo = strtoupper(trim($tipo_solicitud));
    return $prefijos[$tipo] ?? null;
}