<?php
// includes/pqrs_sync.php
// Helper para sincronizar PQRS desde la API de Google Apps Script
// ✅ Sincronizado con el formulario NUEVO de 13 columnas (A → M)

require_once __DIR__ . '/pqrs_api.php';
require_once __DIR__ . '/pqrs_consecutivos.php';
require_once __DIR__ . '/email_config.php';

/**
 * Sincroniza las PQRS desde la API hacia la base de datos.
 *
 * @param PDO $pdo Conexión a la BD
 * @return array Resultado de la sincronización
 */
function sincronizarPQRS($pdo) {
    $resultado = [
        'status' => 'ok',
        'mensaje' => '',
        'insertadas' => 0,
        'actualizadas' => 0,
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

    if (($respuesta['status'] ?? '') !== 'ok') {
        return array_merge($resultado, [
            'status' => 'error',
            'mensaje' => 'Error al consultar la API: ' . ($respuesta['mensaje'] ?? 'Desconocido')
        ]);
    }

    $pqrs_api = $respuesta['pqrs'] ?? [];

    if (empty($pqrs_api)) {
        return array_merge($resultado, [
            'mensaje' => 'No hay PQRS nuevas por importar.'
        ]);
    }

    // ============================================
    // 2. Preparar consultas
    // ============================================
    // Buscar duplicado por documento + marca_temporal (más robusto que fila_sheet)
    $stmt_check = $pdo->prepare("
        SELECT id, codigo FROM pqrs
        WHERE documento = ? AND marca_temporal = ?
        LIMIT 1
    ");

    // INSERT alineado con la estructura real de pqrs
    $stmt_insert = $pdo->prepare("
        INSERT INTO pqrs 
        (codigo, fecha_radicacion, marca_temporal,
         nombre_completo, tipo_documento, documento, celular, email_contacto,
         ciudad_concesionario, tipo_solicitud, marca_vh, placa, relacionada_vh,
         asunto, descripcion, autoriza_datos, estado, creado_el) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'abierto', NOW())
    ");

    // ============================================
    // 3. Procesar cada PQRS
    // ============================================
    foreach ($pqrs_api as $item) {
        try {
            // 3.1 Validar datos obligatorios
            $tipo_solicitud = strtoupper(trim($item['tipo_solicitud'] ?? ''));
            $marca_temporal = $item['marca_temporal'] ?? null;
            $documento      = trim($item['documento'] ?? '');

            if (empty($tipo_solicitud) || empty($marca_temporal) || empty($documento)) {
                throw new Exception("Faltan datos obligatorios: tipo_solicitud, marca_temporal o documento");
            }

            // 3.2 Normalizar tipo_solicitud
            $tipos_validos = ['PETICION','QUEJA','RECLAMO','SUGERENCIA','FELICITACION'];
            if (!in_array($tipo_solicitud, $tipos_validos, true)) {
                if (stripos($tipo_solicitud, 'PETIC')     !== false) $tipo_solicitud = 'PETICION';
                elseif (stripos($tipo_solicitud, 'QUEJ')  !== false) $tipo_solicitud = 'QUEJA';
                elseif (stripos($tipo_solicitud, 'RECLAM')!== false) $tipo_solicitud = 'RECLAMO';
                elseif (stripos($tipo_solicitud, 'SUGER') !== false) $tipo_solicitud = 'SUGERENCIA';
                elseif (stripos($tipo_solicitud, 'FELIC') !== false) $tipo_solicitud = 'FELICITACION';
                else $tipo_solicitud = 'PETICION';
            }

            // 3.3 Limpiar marca_temporal (quitar "(hora estándar de Colombia)")
            $marca_limpia = preg_replace('/\s*\([^)]*\)/', '', $marca_temporal);
            $marca_limpia = trim($marca_limpia);
            $timestamp = strtotime($marca_limpia);
            if ($timestamp === false) {
                throw new Exception("No se pudo parsear la fecha: $marca_temporal");
            }
            $marca_mysql = date('Y-m-d H:i:s', $timestamp);

            // 3.4 Verificar si ya existe
            $stmt_check->execute([$documento, $marca_mysql]);
            $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                $resultado['omitidas']++;
                $resultado['detalles'][] = "⏭️ Ya existe: {$existente['codigo']} (doc $documento)";
                continue;
            }

            // 3.5 Generar código único
            $info_codigo      = generarCodigoPQRS($pdo, $tipo_solicitud, $marca_mysql);
            $codigo           = $info_codigo['codigo'];
            $fecha_radicacion = $info_codigo['fecha_radicacion'];

            // 3.6 Vehículo (solo si hay marca o placa)
            $marca_vh = trim($item['vehiculo']['marca'] ?? '');
            $placa    = trim($item['vehiculo']['placa'] ?? '');
            $relacionada_vh = (!empty($marca_vh) || !empty($placa)) ? 1 : 0;

            // 3.7 Autoriza datos
            $autoriza_datos = !empty($item['autoriza_datos']) ? 1 : 0;

            // 3.8 INSERT
            $stmt_insert->execute([
                $codigo,
                $fecha_radicacion,
                $marca_mysql,
                $item['nombre_completo']       ?? '',
                $item['tipo_documento']        ?? '',
                $documento,
                $item['celular']               ?? '',
                $item['email_contacto']        ?? '',
                $item['ciudad_concesionario']  ?? '',
                $tipo_solicitud,
                $relacionada_vh ? $marca_vh : null,
                $relacionada_vh ? $placa    : null,
                $relacionada_vh,
                $item['asunto']                ?? '',
                $item['descripcion']           ?? '',
                $autoriza_datos
            ]);

            $resultado['insertadas']++;
            $resultado['detalles'][] = "✅ $codigo insertada";

            // ============================================
            // 3.9 📧 Enviar correo de radicado al cliente
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
            }

        } catch (Exception $e) {
            $resultado['errores']++;
            $resultado['detalles'][] = "❌ Error en fila #" . ($item['fila'] ?? '?') . ": " . $e->getMessage();
            error_log("❌ Error al sincronizar PQRS fila #" . ($item['fila'] ?? '?') . ": " . $e->getMessage());
        }
    }

    // ============================================
    // 4. Mensaje final
    // ============================================
    $partes = [];
    if ($resultado['insertadas'] > 0)        $partes[] = "{$resultado['insertadas']} nuevas";
    if ($resultado['omitidas'] > 0)          $partes[] = "{$resultado['omitidas']} omitidas (ya existían)";
    if ($resultado['errores'] > 0)           $partes[] = "{$resultado['errores']} con error";
    if ($resultado['correos_enviados'] > 0)  $partes[] = "{$resultado['correos_enviados']} correos enviados";
    if ($resultado['correos_fallidos'] > 0)  $partes[] = "{$resultado['correos_fallidos']} correos fallidos";

    $resultado['mensaje'] = empty($partes)
        ? 'No se encontraron PQRS nuevas.'
        : '✅ Sincronización completada: ' . implode(', ', $partes) . '.';

    return $resultado;
}