<?php
// includes/email_config.php - VERSIÓN CON mail() DE PHP (SIN SMTP)

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
    
    // Obtener nombre del solicitante
    $stmt = $db->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
    $stmt->execute([$solicitud['usuario_id']]);
    $solicitante = $stmt->fetch(PDO::FETCH_ASSOC);
    $solicitante_nombre = $solicitante['nombre_completo'] ?? 'Usuario';
    
    // Tipos de permisos
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
    
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $url_base = $protocolo . $_SERVER['HTTP_HOST'] . '/';
    
    // ============================================
    // MENSAJE HTML
    // ============================================
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
        $mensaje_html .= '
                <p>El colaborador <strong>' . htmlspecialchars($solicitante_nombre) . '</strong> ha creado una nueva solicitud de permiso que requiere tu revisión y aprobación.</p>';
    } elseif ($tipo == 'aprobado') {
        $mensaje_html .= '
                <p>La solicitud de <strong>' . htmlspecialchars($solicitante_nombre) . '</strong> ha sido <strong style="color: #27ae60;">APROBADA</strong>.</p>';
    } elseif ($tipo == 'rechazado') {
        $mensaje_html .= '
                <p>La solicitud de <strong>' . htmlspecialchars($solicitante_nombre) . '</strong> ha sido <strong style="color: #e74c3c;">RECHAZADA</strong>.</p>';
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
                    <a href="' . $url_base . 'admin/gestion_permisos' . '" class="btn" style="color: white;">
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
    
    // ============================================
    // MENSAJE TEXTO PLANO
    // ============================================
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
?>