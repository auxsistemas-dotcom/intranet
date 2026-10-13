<?php
// includes/seguridad.php
// Configuración de sesión ultra-segura.
// DEBE incluirse AL INICIO de TODO archivo PHP, antes de cualquier salida.

// 1. Forzar HTTPS. Si no es HTTPS, redirigir y salir. 
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    // Asume que el dominio es intranet2.armotor.com
    $url_segura = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $url_segura, true, 301);
    exit();
}

// 2. Configuración de Sesión Segura (Runtime, por si no se puede editar php.ini)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.sid_length', 64);
ini_set('session.sid_bits_per_character', 6);

// 3. Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Control de Inactividad de 30 Minutos
$tiempo_inactividad = 1800; // 30 minutos en segundos
if (isset($_SESSION['ultima_actividad'])) {
    if ((time() - $_SESSION['ultima_actividad']) > $tiempo_inactividad) {
        // Destruir sesión por inactividad
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        
        // Redirigir al login con un mensaje
        header("Location: /login.php?expirada=1");
        exit();
    }
}
// Actualizar marca de tiempo en cada petición
$_SESSION['ultima_actividad'] = time();

// 5. Regeneración de ID de Sesión (Anti-Fixation)
// Regenerar cada 15 minutos para limitar la vida útil de un token robado
if (!isset($_SESSION['ultima_rotacion']) || (time() - $_SESSION['ultima_rotacion'] > 900)) {
    session_regenerate_id(true);
    $_SESSION['ultima_rotacion'] = time();
}
?>