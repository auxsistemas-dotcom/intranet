<?php
// includes/pqrs_config.php
// Configuración del módulo PQRS

/**
 * URL del Web App de Google Apps Script
 * Reemplaza esto por tu URL real
 */
$PQRS_API_URL = 'https://script.google.com/macros/s/AKfycbxdcI2JKkOzLKsPI94EyXou9d7RgU1IYDx-L3aitydJbw2gYpovADM1s3C0JjGESfA5bQ/exec';

/**
 * Token secreto de la API
 * Debe coincidir EXACTAMENTE con el de tu Apps Script
 */
$PQRS_API_TOKEN = 'armotor_pqrs_abcde12345xyzwp';

/**
 * Fecha de última sincronización guardada aquí para no repetir procesos
 * (se actualiza automáticamente cada vez que se sincroniza)
 */
$PQRS_ULTIMA_SINCRONIZACION = null;