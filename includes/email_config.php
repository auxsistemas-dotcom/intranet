<?php
// includes/email_config.php - VERSIÓN CON mail() DE PHP (SIN SMTP)

// ============================================
// ✅ HELPER: OBTENER URL BASE FIJA
// ============================================
if (!function_exists('getUrlBase')) {
    function getUrlBase() {
        // 1) Si existe BASE_URL en config.php → usarla
        if (defined('BASE_URL') && BASE_URL !== '/' && BASE_URL !== '') {
            return rtrim(BASE_URL, '/') . '/';
        }
        // 2) Fallback: construir dinámicamente
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'intranet2.armotor.com';
        return $protocolo . $host . '/';
    }
}

function enviarCorreo($destinatario, $asunto, $mensaje_html, $mensaje_texto = '') {
    $remitente = 'informacion@armotor.com';
    $nombre_remitente = 'Sistema Intranet - ARMOTOR';
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . $nombre_remitente . " <" . $remitente . ">\r\n";
    $headers .= "Reply-To: " . $remitente . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($destinatario, $asunto, $mensaje_html, $headers);
}

function enviarNotificacionPermiso($solicitud, $tipo, $destinatario_email, $destinatario_nombre) {
    $db = $GLOBALS['pdo'] ?? null;
    
    if (!$db) {
        error_log("❌ Error: No hay conexión a la base de datos");
        return false;
    }
    
    $stmt = $db->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
    $stmt->execute([$solicitud['usuario_id']]);
    $solicitante = $stmt->fetch(PDO::FETCH_ASSOC);
    $solicitante_nombre = $solicitante['nombre_completo'] ?? 'Usuario';
    
    $tipos_permisos = [
        'Cita Medica' => 'Cita Médica',
        'Cita Medica Urgencias' => 'Cita Médica Urgencias',
        'Cita con Especialista' => 'Cita con Especialista',
        'Acudiente de Obligaciones Escolares' => 'Acudiente de Obligaciones Escolares',
        'Permiso para Atender Situaciones Judiciales o Administrativas' => 'Permiso para Atender Situaciones Judiciales o Administrativas',
        'Cumpleaños' => 'Cumpleaños',
        'Permiso Remunerado' => 'Permiso Remunerado',
        'Permiso Menos a 4 Horas' => 'Permiso Menos a 4 Horas',
        'Jurado de Votacion' => 'Jurado de Votación',
        'Permiso por Votacion' => 'Permiso por Votación',
        'Jornada Flexible' => 'Jornada Flexible'
    ];
    
    $tipo_nombre = $tipos_permisos[$solicitud['tipo']] ?? $solicitud['tipo'];
    $url_base = getUrlBase();
    
    $mensaje_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #2c3e50; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; border-radius: 10px; }
            .header { background: #12232b; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: #ffc107; margin: 0; font-size: 24px; }
            .header p { color: #8aa8b8; margin: 5px 0 0; }
            .content { background: white; padding: 25px; border-radius: 0 0 10px 10px; }
            .detalle { background: #f0f2f5; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .detalle-item { padding: 8px 0; border-bottom: 1px solid #e9ecef; }
            .detalle-item:last-child { border-bottom: none; }
            .detalle-item .label { font-weight: 600; color: #7f8c8d; font-size: 13px; }
            .detalle-item .value { font-size: 15px; }
            .btn { display: inline-block; padding: 10px 25px; background: #173742; color: white; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .btn:hover { background: #445960; }
            .footer { text-align: center; font-size: 12px; color: #7f8c8d; margin-top: 20px; border-top: 1px solid #e9ecef; padding-top: 15px; }
            .badge-pendiente { background: #fef9e7; color: #7f6000; border: 1px solid #f39c12; padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .badge-aprobado { background: #d5f5e3; color: #1a7a3a; border: 1px solid #27ae60; padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .badge-rechazado { background: #fadbd8; color: #922b21; border: 1px solid #e74c3c; padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📋 Solicitud de Permiso</h1>
                <p>Sistema Intranet - ARMOTOR</p>
            </div>
            <div class="content">
                <h2 style="color: #12232b; margin-top: 0;">' . ($tipo == 'nueva' ? 'Nueva Solicitud de Permiso' : 'Actualización de Solicitud') . '</h2>
                <p>Hola <strong>' . htmlspecialchars($destinatario_nombre) . '</strong>,</p>';

    if ($tipo == 'nueva') {
        $mensaje_html .= '<p>El colaborador <strong>' . htmlspecialchars($solicitante_nombre) . '</strong> ha creado una nueva solicitud de permiso que requiere tu revisión y aprobación.</p>';
    } elseif ($tipo == 'aprobado') {
        $mensaje_html .= '<p>La solicitud de <strong>' . htmlspecialchars($solicitante_nombre) . '</strong> ha sido <strong style="color: #27ae60;">APROBADA</strong>.</p>';
    } elseif ($tipo == 'rechazado') {
        $mensaje_html .= '<p>La solicitud de <strong>' . htmlspecialchars($solicitante_nombre) . '</strong> ha sido <strong style="color: #e74c3c;">RECHAZADA</strong>.</p>';
    }

    $mensaje_html .= '
                <div class="detalle">
                    <div class="detalle-item">
                        <div class="label">📌 Tipo de Permiso</div>
                        <div class="value"><strong>' . htmlspecialchars($tipo_nombre) . '</strong></div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">👤 Solicitante</div>
                        <div class="value">' . htmlspecialchars($solicitante_nombre) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📅 Fecha de Inicio</div>
                        <div class="value">' . date('d/m/Y H:i', strtotime($solicitud['fecha_inicio'])) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📅 Fecha de Fin</div>
                        <div class="value">' . date('d/m/Y H:i', strtotime($solicitud['fecha_fin'])) . '</div>
                    </div>';

    if (!empty($solicitud['descripcion'])) {
        $mensaje_html .= '
                    <div class="detalle-item">
                        <div class="label">📝 Descripción</div>
                        <div class="value">' . nl2br(htmlspecialchars($solicitud['descripcion'])) . '</div>
                    </div>';
    }

    $mensaje_html .= '
                    <div class="detalle-item">
                        <div class="label">📊 Estado</div>
                        <div class="value">
                            <span class="badge-' . $solicitud['estado'] . '">
                                ' . ($solicitud['estado'] == 'pendiente' ? '⏳ Pendiente' : ($solicitud['estado'] == 'aprobado' ? '✅ Aprobado' : '❌ Rechazado')) . '
                            </span>
                        </div>
                    </div>
                </div>';

    if ($tipo == 'nueva') {
        $mensaje_html .= '
                <p style="text-align: center;">
                    <a href="' . $url_base . 'admin" class="btn" style="color: white;">
                        🔍 Ver y Gestionar Solicitud
                    </a>
                </p>
                <p style="font-size: 13px; color: #7f8c8d; text-align: center;">
                    ⚡ Puedes aprobar o rechazar esta solicitud desde el panel de administración.
                </p>';
    } else {
        $mensaje_html .= '
                <p style="text-align: center;">
                    <a href="' . $url_base . 'admin/gestion_permisos/ver_detalle.php?id=' . $solicitud['id'] . '" class="btn">
                        🔍 Ver Detalle
                    </a>
                </p>';
    }

    $mensaje_html .= '
            </div>
            <div class="footer">
                <p>Este es un mensaje automático del Sistema Intranet de ARMOTOR.</p>
                <p>📧 Por favor no respondas a este correo.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $mensaje_texto = "Solicitud de Permiso - AR Motor\n";
    $mensaje_texto .= "================================\n\n";
    $mensaje_texto .= "Hola " . $destinatario_nombre . ",\n\n";
    
    if ($tipo == 'nueva') {
        $mensaje_texto .= "El colaborador " . $solicitante_nombre . " ha creado una nueva solicitud de permiso.\n\n";
    } elseif ($tipo == 'aprobado') {
        $mensaje_texto .= "La solicitud de " . $solicitante_nombre . " ha sido APROBADA.\n\n";
    } elseif ($tipo == 'rechazado') {
        $mensaje_texto .= "La solicitud de " . $solicitante_nombre . " ha sido RECHAZADA.\n\n";
    }
    
    $mensaje_texto .= "Detalles de la solicitud:\n";
    $mensaje_texto .= "- Tipo: " . $tipo_nombre . "\n";
    $mensaje_texto .= "- Solicitante: " . $solicitante_nombre . "\n";
    $mensaje_texto .= "- Fecha Inicio: " . date('d/m/Y H:i', strtotime($solicitud['fecha_inicio'])) . "\n";
    $mensaje_texto .= "- Fecha Fin: " . date('d/m/Y H:i', strtotime($solicitud['fecha_fin'])) . "\n";
    $mensaje_texto .= "- Estado: " . ($solicitud['estado'] == 'pendiente' ? 'Pendiente' : ($solicitud['estado'] == 'aprobado' ? 'Aprobado' : 'Rechazado')) . "\n";
    
    if (!empty($solicitud['descripcion'])) {
        $mensaje_texto .= "- Descripción: " . $solicitud['descripcion'] . "\n";
    }
    
    $mensaje_texto .= "\nPuedes gestionar esta solicitud en: " . $url_base . "admin/gestion_permisos/ver_detalle.php?id=" . $solicitud['id'] . "\n\n";
    $mensaje_texto .= "Este es un mensaje automático del Sistema Intranet de AR Motor.\n";

    return enviarCorreo($destinatario_email, "📋 Solicitud de Permiso - #" . str_pad($solicitud['id'], 5, '0', STR_PAD_LEFT), $mensaje_html, $mensaje_texto);
}

// ============================================
// CORREO: RADICADO DE PQRS (CREACIÓN)
// ============================================
function enviarCorreoRadicadoPQRS($email_contacto, $nombre_completo, $codigo, $datos_pqrs) {
    
    if (empty($email_contacto)) {
        error_log("⚠️ No se envía correo PQRS: email vacío para código $codigo");
        return false;
    }
    
    $url_base = getUrlBase();
    
    $tipo_solicitud = $datos_pqrs['tipo_solicitud'] ?? '';
    $asunto_pqrs = $datos_pqrs['asunto'] ?? '';
    $descripcion = $datos_pqrs['descripcion'] ?? '';
    $fecha_radicacion = $datos_pqrs['fecha_radicacion'] ?? date('Y-m-d');
    
    $tipos = [
        'PETICION'     => 'Petición',
        'QUEJA'        => 'Queja',
        'RECLAMO'      => 'Reclamo',
        'SUGERENCIA'   => 'Sugerencia',
        'FELICITACION' => 'Felicitación'
    ];
    $tipo_legible = $tipos[$tipo_solicitud] ?? $tipo_solicitud;
    
    $mensaje_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #2c3e50; line-height: 1.6; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; }
            .header { background: linear-gradient(135deg, #12232b, #173742); padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: #ffc107; margin: 0; font-size: 24px; }
            .header p { color: #8aa8b8; margin: 8px 0 0; font-size: 14px; }
            .content { background: white; padding: 30px 25px; border-radius: 0 0 10px 10px; }
            .radicado-box { background: linear-gradient(135deg, #d5f5e3, #eafaf1); border: 2px solid #27ae60; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
            .radicado-label { font-size: 12px; color: #1a7a3a; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 8px; }
            .radicado-codigo { font-size: 28px; font-weight: 800; color: #1e8449; letter-spacing: 2px; margin: 0; }
            .detalle { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .detalle-item { padding: 10px 0; border-bottom: 1px solid #e9ecef; }
            .detalle-item:last-child { border-bottom: none; }
            .detalle-item .label { font-weight: 600; color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
            .detalle-item .value { font-size: 15px; color: #12232b; margin-top: 3px; }
            .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; border-radius: 6px; margin: 20px 0; font-size: 14px; color: #1a5276; }
            .footer { text-align: center; font-size: 12px; color: #7f8c8d; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✅ PQRS Recibida</h1>
                <p>Sistema Intranet - ARMOTOR</p>
            </div>
            <div class="content">
                <p>Hola <strong>' . htmlspecialchars($nombre_completo) . '</strong>,</p>
                
                <p>Hemos recibido tu PQRSF correctamente. A continuación encontrarás el número de radicado con el cual podrás hacer seguimiento:</p>
                
                <div class="radicado-box">
                    <div class="radicado-label">Número de Radicado</div>
                    <p class="radicado-codigo">' . htmlspecialchars($codigo) . '</p>
                </div>
                
                <div class="detalle">
                    <div class="detalle-item">
                        <div class="label">📌 Tipo de solicitud</div>
                        <div class="value"><strong>' . htmlspecialchars($tipo_legible) . '</strong></div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📅 Fecha de radicación</div>
                        <div class="value">' . date('d/m/Y', strtotime($fecha_radicacion)) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📝 Asunto</div>
                        <div class="value">' . htmlspecialchars($asunto_pqrs) . '</div>
                    </div>';
    
    if (!empty($descripcion)) {
        $descripcion_corta = strlen($descripcion) > 300 
            ? substr($descripcion, 0, 300) . '...' 
            : $descripcion;
        $mensaje_html .= '
                    <div class="detalle-item">
                        <div class="label">📄 Descripción</div>
                        <div class="value">' . nl2br(htmlspecialchars($descripcion_corta)) . '</div>
                    </div>';
    }
    
    $mensaje_html .= '
                </div>
                
                <p style="margin-top: 25px;">
                    Nuestro equipo iniciará la gestión correspondiente y brindará una respuesta en un plazo no mayor a quince (15) días hábiles, de conformidad con los plazos legales vigentes.
                </p>
                
                <p>Gracias por contactarnos.</p>
            </div>
            <div class="footer">
                <p><strong>ARMOTOR</strong> - Sistema de Gestión PQRSF</p>
                <p>Este es un mensaje automático. Por favor no respondas a este correo.</p>
                <p style="margin-top: 8px;">📧 informacion@armotor.com</p>
            </div>
        </div>
    </body>
    </html>';
    
    $mensaje_texto = "PQRS Recibida - ARMOTOR\n";
    $mensaje_texto .= "================================\n\n";
    $mensaje_texto .= "Hola " . $nombre_completo . ",\n\n";
    $mensaje_texto .= "Hemos recibido tu PQRSF correctamente.\n\n";
    $mensaje_texto .= "NÚMERO DE RADICADO: " . $codigo . "\n\n";
    $mensaje_texto .= "Detalles:\n";
    $mensaje_texto .= "- Tipo: " . $tipo_legible . "\n";
    $mensaje_texto .= "- Fecha radicación: " . date('d/m/Y', strtotime($fecha_radicacion)) . "\n";
    $mensaje_texto .= "- Asunto: " . $asunto_pqrs . "\n\n";
    $mensaje_texto .= "Guarda este número de radicado para hacer seguimiento.\n\n";
    $mensaje_texto .= "Gracias por contactarnos.\n\n";
    $mensaje_texto .= "ARMOTOR - Sistema de Gestión PQRSF\n";
    $mensaje_texto .= "Este es un mensaje automático.\n";
    
    $asunto_email = "✅ PQRS Radicada: " . $codigo . " - ARMOTOR";
    
    return enviarCorreo($email_contacto, $asunto_email, $mensaje_html, $mensaje_texto);
}

// ============================================
// CORREO: ASIGNACIÓN DE PQRS A UN GESTOR
// ============================================
function enviarNotificacionAsignacionPQRS($email_gestor, $nombre_gestor, $datos_pqrs, $asignado_por = '') {

    if (empty($email_gestor)) {
        error_log("⚠️ No se envía correo de asignación PQRS: email vacío para gestor " . ($nombre_gestor ?: 'desconocido'));
        return false;
    }

    $url_base = getUrlBase();

    $codigo        = $datos_pqrs['codigo']            ?? '';
    $tipo          = $datos_pqrs['tipo_solicitud']    ?? '';
    $asunto        = $datos_pqrs['asunto']            ?? '';
    $descripcion   = $datos_pqrs['descripcion']       ?? '';
    $fecha_radica  = $datos_pqrs['fecha_radicacion']  ?? $datos_pqrs['marca_temporal'] ?? date('Y-m-d');
    $pqrs_id       = $datos_pqrs['id']                ?? 0;

    $tipos = [
        'PETICION'     => 'Petición',
        'QUEJA'        => 'Queja',
        'RECLAMO'      => 'Reclamo',
        'SUGERENCIA'   => 'Sugerencia',
        'FELICITACION' => 'Felicitación'
    ];
    $tipo_legible = $tipos[$tipo] ?? $tipo;

    $mensaje_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #2c3e50; line-height: 1.6; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; }
            .header { background: linear-gradient(135deg, #12232b, #173742); padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: #ffc107; margin: 0; font-size: 22px; }
            .header p { color: #8aa8b8; margin: 8px 0 0; font-size: 13px; }
            .content { background: white; padding: 30px 25px; border-radius: 0 0 10px 10px; }
            .radicado-box { background: #fef9e7; border: 2px solid #f39c12; border-radius: 10px; padding: 18px; text-align: center; margin: 20px 0; }
            .radicado-label { font-size: 11px; color: #7f6000; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 6px; }
            .radicado-codigo { font-size: 24px; font-weight: 800; color: #b9770e; letter-spacing: 1.5px; margin: 0; }
            .detalle { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .detalle-item { padding: 10px 0; border-bottom: 1px solid #e9ecef; }
            .detalle-item:last-child { border-bottom: none; }
            .detalle-item .label { font-weight: 600; color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
            .detalle-item .value { font-size: 15px; color: #12232b; margin-top: 3px; }
            .btn { display: inline-block; padding: 12px 28px; background: #173742; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { text-align: center; font-size: 12px; color: #7f8c8d; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📥 Nueva PQRS Asignada</h1>
                <p>Sistema Intranet - ARMOTOR</p>
            </div>
            <div class="content">
                <p>Hola <strong>' . htmlspecialchars($nombre_gestor) . '</strong>,</p>

                <p>Se te ha asignado una nueva PQRS para su gestión. Por favor revísala y dale el trámite correspondiente.</p>

                <div class="radicado-box">
                    <div class="radicado-label">Radicado asignado</div>
                    <p class="radicado-codigo">' . htmlspecialchars($codigo) . '</p>
                </div>

                <div class="detalle">
                    <div class="detalle-item">
                        <div class="label">📌 Tipo de solicitud</div>
                        <div class="value"><strong>' . htmlspecialchars($tipo_legible) . '</strong></div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📅 Fecha de radicación</div>
                        <div class="value">' . date('d/m/Y', strtotime($fecha_radica)) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📝 Asunto</div>
                        <div class="value">' . htmlspecialchars($asunto) . '</div>
                    </div>';

    if (!empty($descripcion)) {
        $desc_corta = strlen($descripcion) > 350 ? substr($descripcion, 0, 350) . '...' : $descripcion;
        $mensaje_html .= '
                    <div class="detalle-item">
                        <div class="label">📄 Descripción</div>
                        <div class="value">' . nl2br(htmlspecialchars($desc_corta)) . '</div>
                    </div>';
    }

    if (!empty($asignado_por)) {
        $mensaje_html .= '
                    <div class="detalle-item">
                        <div class="label">👤 Asignado por</div>
                        <div class="value">' . htmlspecialchars($asignado_por) . '</div>
                    </div>';
    }

    $mensaje_html .= '
                </div>

                <p style="text-align: center; margin-top: 25px;">
                    <a href="' . $url_base . 'aplicaciones/otros/pqrs/ver.php?id=' . $pqrs_id . '" class="btn">
                        🔍 Ver PQRS y Gestionar
                    </a>
                </p>
            </div>
            <div class="footer">
                <p><strong>ARMOTOR</strong> - Sistema de Gestión PQRSF</p>
                <p>Este es un mensaje automático. Por favor no respondas a este correo.</p>
            </div>
        </div>
    </body>
    </html>';

    $mensaje_texto  = "Nueva PQRS Asignada - ARMOTOR\n";
    $mensaje_texto .= "================================\n\n";
    $mensaje_texto .= "Hola " . $nombre_gestor . ",\n\n";
    $mensaje_texto .= "Se te ha asignado una nueva PQRS para su gestión.\n\n";
    $mensaje_texto .= "RADICADO: " . $codigo . "\n\n";
    $mensaje_texto .= "Detalles:\n";
    $mensaje_texto .= "- Tipo: " . $tipo_legible . "\n";
    $mensaje_texto .= "- Fecha radicación: " . date('d/m/Y', strtotime($fecha_radica)) . "\n";
    $mensaje_texto .= "- Asunto: " . $asunto . "\n";
    if (!empty($asignado_por)) {
        $mensaje_texto .= "- Asignado por: " . $asignado_por . "\n";
    }
    $mensaje_texto .= "\nGestionar en: " . $url_base . "aplicaciones/otros/pqrs/ver.php?id=" . $pqrs_id . "\n\n";
    $mensaje_texto .= "ARMOTOR - Sistema de Gestión PQRSF\n";
    $mensaje_texto .= "Este es un mensaje automático.\n";

    $asunto_email = "📥 Nueva PQRS asignada: " . $codigo . " - ARMOTOR";

    return enviarCorreo($email_gestor, $asunto_email, $mensaje_html, $mensaje_texto);
}

// ============================================
// CORREO: COLABORADOR AÑADIDO A UNA PQRS
// ============================================
function enviarNotificacionColaboradorPQRS($email_colaborador, $nombre_colaborador, $datos_pqrs, $nombre_principal = '') {

    if (empty($email_colaborador)) {
        error_log("⚠️ No se envía correo de colaborador PQRS: email vacío para " . ($nombre_colaborador ?: 'desconocido'));
        return false;
    }

    $url_base = getUrlBase();

    $codigo        = $datos_pqrs['codigo']            ?? '';
    $tipo          = $datos_pqrs['tipo_solicitud']    ?? '';
    $asunto        = $datos_pqrs['asunto']            ?? '';
    $descripcion   = $datos_pqrs['descripcion']       ?? '';
    $fecha_radica  = $datos_pqrs['fecha_radicacion']  ?? $datos_pqrs['marca_temporal'] ?? date('Y-m-d');
    $pqrs_id       = $datos_pqrs['id']                ?? 0;
    $estado        = $datos_pqrs['estado']            ?? 'abierto';
    $prioridad     = $datos_pqrs['prioridad']         ?? 'media';
    $cliente       = $datos_pqrs['nombre_completo']   ?? '';
    $marca_vh      = $datos_pqrs['marca_vh']          ?? '';
    $placa         = $datos_pqrs['placa']             ?? '';

    $tipos = [
        'PETICION'     => 'Petición',
        'QUEJA'        => 'Queja',
        'RECLAMO'      => 'Reclamo',
        'SUGERENCIA'   => 'Sugerencia',
        'FELICITACION' => 'Felicitación'
    ];
    $tipo_legible = $tipos[$tipo] ?? $tipo;

    $estados = [
        'abierto'                   => 'Abierto',
        'en_proceso'                => 'En Proceso',
        'analisis_servicio_cliente' => 'Análisis Serv. Cliente',
        'cerrado'                   => 'Cerrado'
    ];
    $estado_legible = $estados[$estado] ?? $estado;

    $prioridades = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
    $prioridad_legible = $prioridades[$prioridad] ?? $prioridad;

    $mensaje_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #2c3e50; line-height: 1.6; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; }
            .header { background: linear-gradient(135deg, #12232b, #173742); padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: #ffc107; margin: 0; font-size: 22px; }
            .header p { color: #8aa8b8; margin: 8px 0 0; font-size: 13px; }
            .content { background: white; padding: 30px 25px; border-radius: 0 0 10px 10px; }
            .radicado-box { background: #e8f4f8; border: 2px solid #3498db; border-radius: 10px; padding: 18px; text-align: center; margin: 20px 0; }
            .radicado-label { font-size: 11px; color: #1a5276; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 6px; }
            .radicado-codigo { font-size: 24px; font-weight: 800; color: #2980b9; letter-spacing: 1.5px; margin: 0; }
            .detalle { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .detalle-item { padding: 10px 0; border-bottom: 1px solid #e9ecef; }
            .detalle-item:last-child { border-bottom: none; }
            .detalle-item .label { font-weight: 600; color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
            .detalle-item .value { font-size: 15px; color: #12232b; margin-top: 3px; }
            .btn { display: inline-block; padding: 12px 28px; background: #173742; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { text-align: center; font-size: 12px; color: #7f8c8d; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef; }
            .banner-info { background: #fef9e7; border-left: 4px solid #f39c12; padding: 14px 18px; border-radius: 8px; margin: 15px 0; color: #7f6000; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🤝 Has sido añadido como colaborador</h1>
                <p>Sistema Intranet - ARMOTOR</p>
            </div>
            <div class="content">
                <p>Hola <strong>' . htmlspecialchars($nombre_colaborador) . '</strong>,</p>

                <div class="banner-info">
                    <strong>📢 Nuevo rol:</strong> Has sido añadido como <strong>colaborador</strong> a la siguiente PQRS.
                    ' . (!empty($nombre_principal) ? 'Fuiste agregado por <strong>' . htmlspecialchars($nombre_principal) . '</strong>.' : '') . '
                </div>

                <div class="radicado-box">
                    <div class="radicado-label">Radicado de la PQRS</div>
                    <p class="radicado-codigo">' . htmlspecialchars($codigo) . '</p>
                </div>

                <div class="detalle">
                    <div class="detalle-item">
                        <div class="label">📌 Tipo de solicitud</div>
                        <div class="value"><strong>' . htmlspecialchars($tipo_legible) . '</strong></div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📊 Estado actual</div>
                        <div class="value">' . htmlspecialchars($estado_legible) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">⚡ Prioridad</div>
                        <div class="value">' . htmlspecialchars($prioridad_legible) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">👤 Cliente</div>
                        <div class="value">' . htmlspecialchars($cliente) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📅 Fecha de radicación</div>
                        <div class="value">' . date('d/m/Y', strtotime($fecha_radica)) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📝 Asunto</div>
                        <div class="value">' . htmlspecialchars($asunto) . '</div>
                    </div>';

    if (!empty($descripcion)) {
        $desc_corta = strlen($descripcion) > 400 ? substr($descripcion, 0, 400) . '...' : $descripcion;
        $mensaje_html .= '
                    <div class="detalle-item">
                        <div class="label">📄 Descripción</div>
                        <div class="value">' . nl2br(htmlspecialchars($desc_corta)) . '</div>
                    </div>';
    }

    if (!empty($marca_vh) || !empty($placa)) {
        $mensaje_html .= '
                    <div class="detalle-item">
                        <div class="label">🚗 Vehículo</div>
                        <div class="value">' . htmlspecialchars(trim($marca_vh . ' ' . $placa)) . '</div>
                    </div>';
    }

    $mensaje_html .= '
                </div>

                <p style="text-align: center; margin-top: 25px;">
                    <a href="' . $url_base . 'aplicaciones/otros/pqrs/ver.php?id=' . $pqrs_id . '" class="btn">
                        🔍 Ver PQRS y Gestionar
                    </a>
                </p>

                <p style="font-size: 13px; color: #7f8c8d; text-align: center; margin-top: 15px;">
                    Como colaborador puedes comentar, adjuntar archivos y ver el detalle completo de esta PQRS.
                </p>
            </div>
            <div class="footer">
                <p><strong>ARMOTOR</strong> - Sistema de Gestión PQRSF</p>
                <p>Este es un mensaje automático. Por favor no respondas a este correo.</p>
            </div>
        </div>
    </body>
    </html>';

    $mensaje_texto  = "Has sido añadido como colaborador - ARMOTOR\n";
    $mensaje_texto .= "================================\n\n";
    $mensaje_texto .= "Hola " . $nombre_colaborador . ",\n\n";
    $mensaje_texto .= "Has sido añadido como COLABORADOR a la siguiente PQRS.\n";
    if (!empty($nombre_principal)) {
        $mensaje_texto .= "Fuiste agregado por: " . $nombre_principal . "\n";
    }
    $mensaje_texto .= "\nRADICADO: " . $codigo . "\n\n";
    $mensaje_texto .= "Detalles:\n";
    $mensaje_texto .= "- Tipo: " . $tipo_legible . "\n";
    $mensaje_texto .= "- Estado: " . $estado_legible . "\n";
    $mensaje_texto .= "- Prioridad: " . $prioridad_legible . "\n";
    $mensaje_texto .= "- Cliente: " . $cliente . "\n";
    $mensaje_texto .= "- Fecha radicación: " . date('d/m/Y', strtotime($fecha_radica)) . "\n";
    $mensaje_texto .= "- Asunto: " . $asunto . "\n";
    if (!empty($marca_vh) || !empty($placa)) {
        $mensaje_texto .= "- Vehículo: " . trim($marca_vh . ' ' . $placa) . "\n";
    }
    $mensaje_texto .= "\nGestionar en: " . $url_base . "aplicaciones/otros/pqrs/ver.php?id=" . $pqrs_id . "\n\n";
    $mensaje_texto .= "ARMOTOR - Sistema de Gestión PQRSF\n";
    $mensaje_texto .= "Este es un mensaje automático.\n";

    $asunto_email = "🤝 Colaborador añadido a PQRS: " . $codigo . " - ARMOTOR";

    return enviarCorreo($email_colaborador, $asunto_email, $mensaje_html, $mensaje_texto);
}

// ============================================
// CORREO: NOTIFICACIÓN DE NUEVA RUTA A SST
// ============================================
function enviarNotificacionRutaCreada($datos_ruta, $datos_conductor, $acompanantes = []) {
    
    $destinatario = 'saludocupacional@armotor.com';
    
    $ruta_id        = $datos_ruta['id']                 ?? 0;
    $placa          = $datos_ruta['placa']              ?? '';
    $medio          = $datos_ruta['medio_transporte']   ?? '';
    $modalidad      = $datos_ruta['modalidad_vehiculo'] ?? '';
    $ciudad_origen  = $datos_ruta['ciudad_origen']      ?? '';
    $ciudad_destino = $datos_ruta['ciudad_destino']     ?? '';
    $fecha_salida   = $datos_ruta['fecha_salida']       ?? '';
    $fecha_regreso  = $datos_ruta['fecha_regreso']      ?? '';
    $motivo_viaje   = $datos_ruta['motivo_viaje']       ?? '';
    
    $nombre_conductor = $datos_conductor['nombre_completo'] ?? '';
    $documento        = $datos_conductor['documento']       ?? '';
    $cargo            = $datos_conductor['cargo']           ?? '';
    $sede             = $datos_conductor['sede']            ?? '';
    
    // ✅ Tipo de vehículo legible según medio + modalidad
    if ($medio === 'PROPIO') {
        $tipo_vehiculo = 'Vehículo propio';
    } elseif ($medio === 'ORGANIZACION') {
        $modalidades_legibles = [
            'NUEVO'      => 'NUEVO',
            'USADO'      => 'USADO',
            'TEST_DRIVE' => 'TEST DRIVE',
            'PROPIO'     => 'PROPIO'
        ];
        $mod_texto = $modalidades_legibles[$modalidad] ?? $modalidad;
        $tipo_vehiculo = 'Vehículo corporativo ' . $mod_texto;
    } elseif ($medio === 'MOTO') {
        $tipo_vehiculo = 'Motocicleta';
    } elseif ($medio === 'NODRIZA') {
        $tipo_vehiculo = 'Vehículo nodriza';
    } else {
        $tipo_vehiculo = $medio;
    }
    
    // Fechas legibles
    $fecha_salida_fmt  = !empty($fecha_salida)  ? date('d/m/Y H:i', strtotime($fecha_salida))  : '—';
    $fecha_regreso_fmt = !empty($fecha_regreso) ? date('d/m/Y H:i', strtotime($fecha_regreso)) : '—';
    
    // HTML de acompañantes
    $html_acompanantes = '';
    if (!empty($acompanantes)) {
        $html_acompanantes .= '
                    <div class="detalle-item">
                        <div class="label">👥 Acompañantes (' . count($acompanantes) . ')</div>
                        <div class="value">
                            <ul style="margin: 5px 0 0 20px; padding: 0;">';
        foreach ($acompanantes as $a) {
            $nombre_a = htmlspecialchars($a['nombre'] ?? '');
            $cargo_a  = htmlspecialchars($a['cargo'] ?? 'Sin cargo');
            $sede_a   = htmlspecialchars($a['sede'] ?? '');
            $detalle_a = $cargo_a . ($sede_a ? ' · ' . $sede_a : '');
            $html_acompanantes .= '<li><strong>' . $nombre_a . '</strong> — ' . $detalle_a . '</li>';
        }
        $html_acompanantes .= '
                            </ul>
                        </div>
                    </div>';
    } else {
        $html_acompanantes = '
                    <div class="detalle-item">
                        <div class="label">👥 Acompañantes</div>
                        <div class="value" style="color: #7f8c8d;">Sin acompañantes</div>
                    </div>';
    }
    
    $mensaje_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #2c3e50; line-height: 1.6; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; }
            .header { background: linear-gradient(135deg, #12232b, #173742); padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: #ffc107; margin: 0; font-size: 22px; }
            .header p { color: #8aa8b8; margin: 8px 0 0; font-size: 13px; }
            .content { background: white; padding: 30px 25px; border-radius: 0 0 10px 10px; }
            .radicado-box { background: #e8f4f8; border: 2px solid #3498db; border-radius: 10px; padding: 15px; text-align: center; margin: 20px 0; }
            .radicado-label { font-size: 11px; color: #1a5276; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 5px; }
            .radicado-codigo { font-size: 22px; font-weight: 800; color: #2980b9; letter-spacing: 1.5px; margin: 0; }
            .detalle { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .detalle-item { padding: 10px 0; border-bottom: 1px solid #e9ecef; }
            .detalle-item:last-child { border-bottom: none; }
            .detalle-item .label { font-weight: 600; color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
            .detalle-item .value { font-size: 15px; color: #12232b; margin-top: 3px; }
            .ruta-banner { background: #d5f5e3; border-left: 4px solid #27ae60; border-radius: 8px; padding: 15px; margin: 15px 0; font-size: 16px; font-weight: 600; color: #1a7a3a; }
            .footer { text-align: center; font-size: 12px; color: #7f8c8d; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🚗 Nueva Ruta Iniciada</h1>
                <p>Sistema Intranet - ARMOTOR</p>
            </div>
            <div class="content">
                <p>Hola equipo de <strong>SST</strong>,</p>

                <p>Se ha registrado una nueva ruta en el sistema. A continuación los detalles:</p>

                <div class="radicado-box">
                    <div class="radicado-label">Ruta #</div>
                    <p class="radicado-codigo">' . str_pad($ruta_id, 6, '0', STR_PAD_LEFT) . '</p>
                </div>

                <div class="ruta-banner">
                    🗺️ ' . htmlspecialchars($ciudad_origen) . ' → ' . htmlspecialchars($ciudad_destino) . '
                </div>

                <div class="detalle">
                    <div class="detalle-item">
                        <div class="label">🚗 Vehículo</div>
                        <div class="value"><strong>' . htmlspecialchars($tipo_vehiculo) . ' (' . htmlspecialchars($placa) . ')</strong></div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">👤 Conductor</div>
                        <div class="value">' . htmlspecialchars($nombre_conductor) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">🪪 Documento</div>
                        <div class="value">' . htmlspecialchars($documento) . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">💼 Cargo</div>
                        <div class="value">' . htmlspecialchars($cargo) . ($sede ? ' · ' . htmlspecialchars($sede) : '') . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📅 Fecha de salida</div>
                        <div class="value">' . $fecha_salida_fmt . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📅 Regreso estimado</div>
                        <div class="value">' . $fecha_regreso_fmt . '</div>
                    </div>
                    <div class="detalle-item">
                        <div class="label">📝 Motivo del viaje</div>
                        <div class="value">' . nl2br(htmlspecialchars($motivo_viaje)) . '</div>
                    </div>
                    ' . $html_acompanantes . '
                </div>

                <p style="font-size: 13px; color: #7f8c8d; text-align: center; margin-top: 20px;">
                    Este es un correo informativo automático. No requiere acción.
                </p>
            </div>
            <div class="footer">
                <p><strong>ARMOTOR</strong> - Sistema de Operación Vehicular</p>
                <p>Este es un mensaje automático. Por favor no respondas a este correo.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Texto plano
    $mensaje_texto  = "Nueva Ruta Iniciada - ARMOTOR\n";
    $mensaje_texto .= "================================\n\n";
    $mensaje_texto .= "RUTA #" . str_pad($ruta_id, 6, '0', STR_PAD_LEFT) . "\n\n";
    $mensaje_texto .= "Vehículo: " . $tipo_vehiculo . " (" . $placa . ")\n";
    $mensaje_texto .= "Conductor: " . $nombre_conductor . "\n";
    $mensaje_texto .= "Documento: " . $documento . "\n";
    $mensaje_texto .= "Cargo: " . $cargo . ($sede ? ' · ' . $sede : '') . "\n";
    $mensaje_texto .= "Ruta: " . $ciudad_origen . " → " . $ciudad_destino . "\n";
    $mensaje_texto .= "Salida: " . $fecha_salida_fmt . "\n";
    $mensaje_texto .= "Regreso estimado: " . $fecha_regreso_fmt . "\n";
    $mensaje_texto .= "Motivo: " . $motivo_viaje . "\n";
    
    if (!empty($acompanantes)) {
        $mensaje_texto .= "\nAcompañantes (" . count($acompanantes) . "):\n";
        foreach ($acompanantes as $a) {
            $mensaje_texto .= "- " . ($a['nombre'] ?? '') . " (" . ($a['cargo'] ?? 'Sin cargo') . ")\n";
        }
    } else {
        $mensaje_texto .= "\nSin acompañantes.\n";
    }
    
    $mensaje_texto .= "\nARMOTOR - Sistema de Operación Vehicular\n";
    $mensaje_texto .= "Este es un mensaje automático.\n";
    
    $asunto_email = "🚗 Nueva ruta iniciada: " . $placa . " — " . $nombre_conductor;
    
    return enviarCorreo($destinatario, $asunto_email, $mensaje_html, $mensaje_texto);
}