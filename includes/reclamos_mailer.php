<?php
// includes/reclamos_mailer.php
// Helper para enviar notificaciones por correo del módulo de reclamos

require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/reclamos_config.php';

/**
 * Envía notificación a los supervisores cuando se crea un reclamo nuevo.
 * 
 * @param int $reclamo_id ID del reclamo recién creado
 * @return bool true si al menos un correo se envió, false si falló todo
 */
function notificarNuevoReclamo($reclamo_id) {
    global $pdo, $CORREOS_RECLAMOS, $ASUNTO_NUEVO_RECLAMO, $NOTIFICACIONES_RECLAMOS_ACTIVAS;
    
    // Si las notificaciones están desactivadas, no hacer nada
    if (!$NOTIFICACIONES_RECLAMOS_ACTIVAS) {
        return false;
    }
    
    // Si no hay correos configurados, no enviar
    if (empty($CORREOS_RECLAMOS)) {
        error_log("⚠️ notificarNuevoReclamo: No hay correos configurados en \$CORREOS_RECLAMOS");
        return false;
    }
    
    try {
        // Obtener datos del reclamo + asesor
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   u.nombre_completo AS asesor_nombre,
                   u.cod_asesor AS asesor_codigo
            FROM reclamos r
            LEFT JOIN usuarios u ON r.asesor_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reclamo_id]);
        $reclamo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reclamo) {
            error_log("⚠️ notificarNuevoReclamo: Reclamo #$reclamo_id no encontrado");
            return false;
        }
        
        // Tipos de comisión (para mostrar el nombre legible)
        $tipos_comisiones = [
            'matriculas' => 'Matrículas',
            'usados' => 'Usados',
            'accesorios' => 'Accesorios',
            'incentivos' => 'Incentivos',
            'financieras' => 'Financieras'
        ];
        
        $meses_espanol = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        $tipo_nombre = $tipos_comisiones[$reclamo['tipo_comision']] ?? $reclamo['tipo_comision'];
        $periodo = ($meses_espanol[$reclamo['mes']] ?? $reclamo['mes']) . ' ' . $reclamo['anio'];
        $reclamo_num = str_pad($reclamo['id'], 4, '0', STR_PAD_LEFT);
        
        // URL base
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url_base = $protocolo . $host;
        $url_reclamo = $url_base . '/admin/comercial/reclamos/ver.php?id=' . $reclamo['id'];
        
        // ============================================
        // ARMAR HTML DEL CORREO
        // ============================================
        $mensaje_html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #2c3e50; line-height: 1.6; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; border-radius: 10px; }
                .header { background: #12232b; padding: 25px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { color: #ffc107; margin: 0; font-size: 22px; }
                .header p { color: #8aa8b8; margin: 5px 0 0; font-size: 13px; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .content h2 { color: #12232b; margin-top: 0; font-size: 18px; }
                .detalle { background: #f0f2f5; padding: 18px; border-radius: 8px; margin: 20px 0; }
                .detalle-item { padding: 10px 0; border-bottom: 1px solid #e9ecef; }
                .detalle-item:last-child { border-bottom: none; }
                .detalle-item .label { font-weight: 600; color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
                .detalle-item .value { font-size: 15px; color: #2c3e50; margin-top: 3px; }
                .badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: #fef9e7; color: #7f6000; border: 1px solid #f39c12; }
                .btn { display: inline-block; padding: 12px 30px; background: #173742; color: white !important; text-decoration: none; border-radius: 6px; margin-top: 20px; font-weight: 600; }
                .btn:hover { background: #445960; }
                .footer { text-align: center; font-size: 12px; color: #7f8c8d; margin-top: 25px; border-top: 1px solid #e9ecef; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📩 Nuevo Reclamo Creado</h1>
                    <p>Sistema Intranet ARMOTOR</p>
                </div>
                <div class="content">
                    <h2>Reclamo #' . $reclamo_num . '</h2>
                    <p>Se ha creado un nuevo reclamo que requiere tu revisión.</p>
                    
                    <div class="detalle">
                        <div class="detalle-item">
                            <div class="label">Asunto</div>
                            <div class="value"><strong>' . htmlspecialchars($reclamo['asunto']) . '</strong></div>
                        </div>
                        <div class="detalle-item">
                            <div class="label">Asesor</div>
                            <div class="value">' . htmlspecialchars($reclamo['asesor_nombre'] ?? 'N/A') . 
                            (!empty($reclamo['asesor_codigo']) ? ' (' . htmlspecialchars($reclamo['asesor_codigo']) . ')' : '') . '</div>
                        </div>
                        <div class="detalle-item">
                            <div class="label">Tipo de Comisión</div>
                            <div class="value">' . htmlspecialchars($tipo_nombre) . '</div>
                        </div>
                        <div class="detalle-item">
                            <div class="label">Período</div>
                            <div class="value">' . htmlspecialchars($periodo) . '</div>
                        </div>
                        <div class="detalle-item">
                            <div class="label">Fecha de Creación</div>
                            <div class="value">' . date('d/m/Y H:i', strtotime($reclamo['creado_el'])) . '</div>
                        </div>
                        <div class="detalle-item">
                            <div class="label">Estado</div>
                            <div class="value"><span class="badge">⏳ Pendiente</span></div>
                        </div>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="' . $url_reclamo . '" class="btn">🔍 Ver Reclamo</a>
                    </p>
                </div>
                <div class="footer">
                    <p>Este es un mensaje automático del Sistema Intranet de ARMOTOR.</p>
                    <p>Por favor no respondas a este correo.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // ============================================
        // TEXTO PLANO (fallback)
        // ============================================
        $mensaje_texto = "Nuevo Reclamo Creado - ARMOTOR\n";
        $mensaje_texto .= "================================\n\n";
        $mensaje_texto .= "Reclamo #" . $reclamo_num . "\n";
        $mensaje_texto .= "Asunto: " . $reclamo['asunto'] . "\n";
        $mensaje_texto .= "Asesor: " . ($reclamo['asesor_nombre'] ?? 'N/A') . "\n";
        $mensaje_texto .= "Tipo: " . $tipo_nombre . "\n";
        $mensaje_texto .= "Período: " . $periodo . "\n";
        $mensaje_texto .= "Fecha: " . date('d/m/Y H:i', strtotime($reclamo['creado_el'])) . "\n";
        $mensaje_texto .= "Estado: Pendiente\n\n";
        $mensaje_texto .= "Ver reclamo: " . $url_reclamo . "\n\n";
        $mensaje_texto .= "Este es un mensaje automático del Sistema Intranet de ARMOTOR.\n";
        
        // ============================================
        // ENVIAR A TODOS LOS CORREOS
        // ============================================
        $enviados = 0;
        $fallidos = 0;
        
        foreach ($CORREOS_RECLAMOS as $correo) {
            $correo = trim($correo);
            if (empty($correo)) continue;
            
            $resultado = enviarCorreo($correo, $ASUNTO_NUEVO_RECLAMO, $mensaje_html, $mensaje_texto);
            
            if ($resultado) {
                $enviados++;
            } else {
                $fallidos++;
                error_log("⚠️ Error al enviar correo de nuevo reclamo #$reclamo_id a: $correo");
            }
        }
        
        error_log("✅ notificarNuevoReclamo #$reclamo_id: $enviados enviados, $fallidos fallidos");
        return $enviados > 0;
        
    } catch (Exception $e) {
        error_log("❌ Error en notificarNuevoReclamo: " . $e->getMessage());
        return false;
    }
}


/**
 * Envía notificación al asesor cuando el admin cambia el estado del reclamo.
 * 
 * @param int $reclamo_id ID del reclamo
 * @param string $estado_anterior Estado anterior
 * @param string $estado_nuevo Estado nuevo
 * @return bool true si el correo se envió, false si falló
 */
function notificarCambioEstado($reclamo_id, $estado_anterior, $estado_nuevo) {
    global $pdo, $ASUNTO_CAMBIO_ESTADO, $NOTIFICACIONES_RECLAMOS_ACTIVAS;
    
    // Si las notificaciones están desactivadas, no hacer nada
    if (!$NOTIFICACIONES_RECLAMOS_ACTIVAS) {
        return false;
    }
    
    try {
        // Obtener datos del reclamo + email del asesor
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   u.nombre_completo AS asesor_nombre,
                   u.email AS asesor_email,
                   u.cod_asesor AS asesor_codigo
            FROM reclamos r
            LEFT JOIN usuarios u ON r.asesor_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reclamo_id]);
        $reclamo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reclamo) {
            error_log("⚠️ notificarCambioEstado: Reclamo #$reclamo_id no encontrado");
            return false;
        }
        
        if (empty($reclamo['asesor_email'])) {
            error_log("⚠️ notificarCambioEstado: El asesor del reclamo #$reclamo_id no tiene correo");
            return false;
        }
        
        // Estados legibles
        $estados_texto = [
            'pendiente'   => 'Pendiente',
            'en_revision' => 'En Revisión',
            'resuelto'    => 'Resuelto'
        ];
        
        $estado_anterior_txt = $estados_texto[$estado_anterior] ?? $estado_anterior;
        $estado_nuevo_txt = $estados_texto[$estado_nuevo] ?? $estado_nuevo;
        
        // Colores según el nuevo estado
        $colores = [
            'pendiente'   => ['bg' => '#fef9e7', 'text' => '#7f6000', 'border' => '#f39c12', 'icon' => '⏳'],
            'en_revision' => ['bg' => '#d6eaf8', 'text' => '#1a5276', 'border' => '#3498db', 'icon' => '🔍'],
            'resuelto'    => ['bg' => '#d5f5e3', 'text' => '#1a7a3a', 'border' => '#27ae60', 'icon' => '✅']
        ];
        $color = $colores[$estado_nuevo] ?? $colores['pendiente'];
        
        // URL base
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url_base = $protocolo . $host;
        $url_reclamo = $url_base . '/aplicaciones/otros/comercial/reclamos/ver.php?id=' . $reclamo['id'];
        
        $reclamo_num = str_pad($reclamo['id'], 4, '0', STR_PAD_LEFT);
        
        // ============================================
        // ARMAR HTML DEL CORREO
        // ============================================
        $mensaje_html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #2c3e50; line-height: 1.6; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; border-radius: 10px; }
                .header { background: #12232b; padding: 25px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { color: #ffc107; margin: 0; font-size: 22px; }
                .header p { color: #8aa8b8; margin: 5px 0 0; font-size: 13px; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                .content h2 { color: #12232b; margin-top: 0; font-size: 18px; }
                .detalle { background: #f0f2f5; padding: 18px; border-radius: 8px; margin: 20px 0; }
                .detalle-item { padding: 10px 0; border-bottom: 1px solid #e9ecef; }
                .detalle-item:last-child { border-bottom: none; }
                .detalle-item .label { font-weight: 600; color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
                .detalle-item .value { font-size: 15px; color: #2c3e50; margin-top: 3px; }
                .badge { display: inline-block; padding: 5px 15px; border-radius: 12px; font-size: 13px; font-weight: 600; background: ' . $color['bg'] . '; color: ' . $color['text'] . '; border: 1px solid ' . $color['border'] . '; }
                .btn { display: inline-block; padding: 12px 30px; background: #173742; color: white !important; text-decoration: none; border-radius: 6px; margin-top: 20px; font-weight: 600; }
                .btn:hover { background: #445960; }
                .footer { text-align: center; font-size: 12px; color: #7f8c8d; margin-top: 25px; border-top: 1px solid #e9ecef; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔔 Actualización de Reclamo</h1>
                    <p>Sistema Intranet ARMOTOR</p>
                </div>
                <div class="content">
                    <h2>Tu Reclamo #' . $reclamo_num . ' ha sido actualizado</h2>
                    <p>Hola <strong>' . htmlspecialchars($reclamo['asesor_nombre']) . '</strong>,</p>
                    <p>El estado de tu reclamo ha cambiado.</p>
                    
                    <div class="detalle">
                        <div class="detalle-item">
                            <div class="label">Asunto</div>
                            <div class="value"><strong>' . htmlspecialchars($reclamo['asunto']) . '</strong></div>
                        </div>
                        <div class="detalle-item">
                            <div class="label">Estado Anterior</div>
                            <div class="value">' . htmlspecialchars($estado_anterior_txt) . '</div>
                        </div>
                        <div class="detalle-item">
                            <div class="label">Estado Nuevo</div>
                            <div class="value"><span class="badge">' . $color['icon'] . ' ' . htmlspecialchars($estado_nuevo_txt) . '</span></div>
                        </div>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="' . $url_reclamo . '" class="btn">🔍 Ver Reclamo</a>
                    </p>
                </div>
                <div class="footer">
                    <p>Este es un mensaje automático del Sistema Intranet de ARMOTOR.</p>
                    <p>Por favor no respondas a este correo.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // ============================================
        // TEXTO PLANO (fallback)
        // ============================================
        $mensaje_texto = "Actualización de Reclamo - ARMOTOR\n";
        $mensaje_texto .= "================================\n\n";
        $mensaje_texto .= "Hola " . $reclamo['asesor_nombre'] . ",\n\n";
        $mensaje_texto .= "Tu reclamo #" . $reclamo_num . " ha cambiado de estado.\n\n";
        $mensaje_texto .= "Asunto: " . $reclamo['asunto'] . "\n";
        $mensaje_texto .= "Estado anterior: " . $estado_anterior_txt . "\n";
        $mensaje_texto .= "Estado nuevo: " . $estado_nuevo_txt . "\n\n";
        $mensaje_texto .= "Ver reclamo: " . $url_reclamo . "\n\n";
        $mensaje_texto .= "Este es un mensaje automático del Sistema Intranet de ARMOTOR.\n";
        
        // ============================================
        // ENVIAR
        // ============================================
        $resultado = enviarCorreo($reclamo['asesor_email'], $ASUNTO_CAMBIO_ESTADO, $mensaje_html, $mensaje_texto);
        
        if ($resultado) {
            error_log("✅ notificarCambioEstado #$reclamo_id: correo enviado a " . $reclamo['asesor_email']);
        } else {
            error_log("⚠️ notificarCambioEstado #$reclamo_id: error al enviar a " . $reclamo['asesor_email']);
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("❌ Error en notificarCambioEstado: " . $e->getMessage());
        return false;
    }
}