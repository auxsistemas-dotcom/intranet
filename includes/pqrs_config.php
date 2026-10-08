<?php
// includes/pqrs_config.php
// Configuración del módulo PQRS

/**
 * URL del Web App de Google Apps Script
 */
$PQRS_API_URL = 'https://script.google.com/macros/s/AKfycbxdcI2JKkOzLKsPI94EyXou9d7RgU1IYDx-L3aitydJbw2gYpovADM1s3C0JjGESfA5bQ/exec';

/**
 * Token secreto de la API
 * Debe coincidir EXACTAMENTE con el de tu Apps Script
 */
$PQRS_API_TOKEN = 'armotor_pqrs_abcde12345xyzwp';

/**
 * Asunto del correo que se envía AL GESTOR cuando se le asigna una PQRS.
 */
$ASUNTO_ASIGNACION_PQRS = '📋 Tienes una PQRS por resolver - Sistema Intranet ARMOTOR';

/**
 * Asunto del correo que se envía AL CLIENTE cuando su PQRS ha sido cerrada.
 */
$ASUNTO_CIERRE_PQRS = '✅ Su PQRS ha sido resuelta - Sistema Intranet ARMOTOR';

/**
 * Switch general: activar o desactivar notificaciones
 * (útil para pruebas o mantenimiento)
 */
$NOTIFICACIONES_PQRS_ACTIVAS = true;