<?php
// includes/pqrs_sync.php
// Helper para sincronizar PQRS desde la API de Google Apps Script

require_once __DIR__ . '/pqrs_api.php';
require_once __DIR__ . '/pqrs_consecutivos.php';
require_once __DIR__ . '/email_config.php';

/**
 * Sincroniza las PQRS desde la API hacia la base de datos.
 * 
 * @param PDO $pdo Conexión a la BD
 * @return array Resultado de la sincronización
 *   [
 *     'status' => 'ok|error',
 *     'mensaje' => string,
 *     'insertadas' => int,
 *     'omitidas' => int,
 *     'errores' => int,
 *     'correos_enviados' => int,
 *     'correos_fallidos' => int,
 *     'detalles' => array
 *   ]
 */
function sincronizarPQRS($pdo) {
    $resultado = [
        'status' => 'ok',
        'mensaje' => '',
        'insertadas' => 0,
        'omitidas' => 0,
        'errores' => 0,
        'correos_enviados' => 0,
        'correos_fallidos' => 0,
        'detalles' => []
    ];
    
    // ============================================
    // 1. Llamar a la API
    // ============================================
    $respuesta = obtenerPQRSDesdeAPI();
    
    if ($respuesta['status'] !== 'ok') {
        return [
            'status' => 'error',
            'mensaje' => 'Error al consultar la API: ' . ($respuesta['mensaje'] ?? 'Desconocido'),
            'insertadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
            'correos_enviados' => 0,
            'correos_fallidos' => 0,
            'detalles' => []
        ];
    }
    
    $pqrs_api = $respuesta['pqrs'] ?? [];
    
    if (empty($pqrs_api)) {
        return [
            'status' => 'ok',
            'mensaje' => 'No hay PQRS nuevas por importar.',
            'insertadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
            'correos_enviados' => 0,
            'correos_fallidos' => 0,
            'detalles' => []
        ];
    }
    
    // ============================================
    // 2. Preparar consulta para verificar duplicados
    // ============================================
    $stmt_check = $pdo->prepare("SELECT id FROM pqrs WHERE fila_sheet = ? LIMIT 1");
    
    // ============================================
    // 3. Preparar consulta para insertar
    // ============================================
    $stmt_insert = $pdo->prepare("
        INSERT INTO pqrs 
        (codigo, fecha_radicacion, fila_sheet, marca_temporal, tipo_persona, 
         nombre_completo, tipo_documento, documento, celular, ciudad_cliente, 
         email_contacto, ciudad_concesionario, tipo_solicitud, relacionada_vh, 
         marca_vh, placa, referencia_vh, modelo, asunto, descripcion, 
         estado, creado_el) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'abierto', NOW())
    ");
    
    // ============================================
    // 4. Procesar cada PQRS
    // ============================================
    foreach ($pqrs_api as $item) {
        try {
            $fila_sheet = intval($item['fila'] ?? 0);
            
            // 4.1 Verificar si ya existe (por fila_sheet)
            if ($fila_sheet > 0) {
                $stmt_check->execute([$fila_sheet]);
                $existe = $stmt_check->fetchColumn();
                
                if ($existe) {
                    $resultado['omitidas']++;
                    continue;
                }
            }
            
            // 4.2 Generar código único
            $tipo_solicitud = $item['tipo_solicitud'] ?? '';
            $marca_temporal = $item['marca_temporal'] ?? null;
            
            if (empty($tipo_solicitud) || empty($marca_temporal)) {
                throw new Exception("Faltan datos obligatorios: tipo_solicitud o marca_temporal");
            }
            
            $info_codigo = generarCodigoPQRS($pdo, $tipo_solicitud, $marca_temporal);
            $codigo = $info_codigo['codigo'];
            $fecha_radicacion = $info_codigo['fecha_radicacion'];
            
            // 4.3 Normalizar datos
            $relacionada_vh = !empty($item['relacionada_vh']) ? 1 : 0;
            
            // Si NO está relacionada con VH, limpiar los campos del vehículo
            $marca_vh = $relacionada_vh ? ($item['vehiculo']['marca'] ?? null) : null;
            $placa = $relacionada_vh ? ($item['vehiculo']['placa'] ?? null) : null;
            $referencia_vh = $relacionada_vh ? ($item['vehiculo']['referencia'] ?? null) : null;
            $modelo = $relacionada_vh ? ($item['vehiculo']['modelo'] ?? null) : null;
            
            // ✅ Convertir marca temporal a formato MySQL (con limpieza)
            // Quitar el paréntesis con el texto en español: "(hora estándar de Colombia)"
            $marca_temporal_limpia = preg_replace('/\s*\([^)]*\)/', '', $marca_temporal);
            $marca_temporal_limpia = trim($marca_temporal_limpia);
            
            $timestamp = strtotime($marca_temporal_limpia);
            if ($timestamp === false) {
                throw new Exception("No se pudo parsear la fecha: $marca_temporal");
            }
            $marca_temporal_mysql = date('Y-m-d H:i:s', $timestamp);
            
            // 4.4 Insertar
            $stmt_insert->execute([
                $codigo,
                $fecha_radicacion,
                $fila_sheet > 0 ? $fila_sheet : null,
                $marca_temporal_mysql,
                $item['tipo_persona'] ?? null,
                $item['nombre_completo'] ?? '',
                $item['tipo_documento'] ?? null,
                $item['documento'] ?? null,
                $item['celular'] ?? null,
                $item['ciudad_cliente'] ?? null,
                $item['email_contacto'] ?? null,
                $item['ciudad_concesionario'] ?? null,
                $tipo_solicitud,
                $relacionada_vh,
                $marca_vh,
                $placa,
                $referencia_vh,
                $modelo,
                $item['asunto'] ?? '',
                $item['descripcion'] ?? null
            ]);
            
            $resultado['insertadas']++;
            $resultado['detalles'][] = "✅ $codigo insertada (fila #$fila_sheet)";
            
            // ============================================
            // 4.5 📧 ENVIAR CORREO CON EL RADICADO
            // ============================================
            $email_contacto = trim($item['email_contacto'] ?? '');
            $nombre_completo = trim($item['nombre_completo'] ?? '');
            
            if (!empty($email_contacto) && filter_var($email_contacto, FILTER_VALIDATE_EMAIL)) {
                try {
                    $correo_enviado = enviarCorreoRadicadoPQRS(
                        $email_contacto,
                        $nombre_completo,
                        $codigo,
                        [
                            'tipo_solicitud'   => $tipo_solicitud,
                            'asunto'           => $item['asunto'] ?? '',
                            'descripcion'      => $item['descripcion'] ?? '',
                            'fecha_radicacion' => $fecha_radicacion
                        ]
                    );
                    
                    if ($correo_enviado) {
                        $resultado['correos_enviados']++;
                        $resultado['detalles'][] = "📧 Correo enviado a $email_contacto";
                    } else {
                        $resultado['correos_fallidos']++;
                        $resultado['detalles'][] = "⚠️ Fallo al enviar correo a $email_contacto";
                        error_log("⚠️ Fallo al enviar correo PQRS $codigo a $email_contacto");
                    }
                } catch (Exception $e) {
                    $resultado['correos_fallidos']++;
                    $resultado['detalles'][] = "❌ Error al enviar correo: " . $e->getMessage();
                    error_log("❌ Error al enviar correo PQRS $codigo: " . $e->getMessage());
                }
            } elseif (empty($email_contacto)) {
                $resultado['detalles'][] = "⚠️ Sin email de contacto para $codigo";
            } else {
                $resultado['detalles'][] = "⚠️ Email inválido para $codigo: $email_contacto";
                error_log("⚠️ Email inválido en PQRS $codigo: $email_contacto");
            }
            
        } catch (Exception $e) {
            $resultado['errores']++;
            $resultado['detalles'][] = "❌ Error en fila #" . ($item['fila'] ?? '?') . ": " . $e->getMessage();
            error_log("❌ Error al sincronizar PQRS fila #" . ($item['fila'] ?? '?') . ": " . $e->getMessage());
        }
    }
    
    // ============================================
    // 5. Mensaje final
    // ============================================
    $partes = [];
    if ($resultado['insertadas'] > 0) {
        $partes[] = "{$resultado['insertadas']} nuevas";
    }
    if ($resultado['omitidas'] > 0) {
        $partes[] = "{$resultado['omitidas']} omitidas (ya existían)";
    }
    if ($resultado['errores'] > 0) {
        $partes[] = "{$resultado['errores']} con error";
    }
    if ($resultado['correos_enviados'] > 0) {
        $partes[] = "{$resultado['correos_enviados']} correos enviados";
    }
    if ($resultado['correos_fallidos'] > 0) {
        $partes[] = "{$resultado['correos_fallidos']} correos fallidos";
    }
    
    if (empty($partes)) {
        $resultado['mensaje'] = 'No se encontraron PQRS nuevas.';
    } else {
        $resultado['mensaje'] = '✅ Sincronización completada: ' . implode(', ', $partes) . '.';
    }
    
    return $resultado;
}