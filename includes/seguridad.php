<?php
// includes/seguridad.php
// Configuración de sesión ultra-segura.
// DEBE incluirse AL INICIO de TODO archivo PHP, antes de cualquier salida.

// ============================================
// 1. Detectar si estamos en LOCAL
// ============================================
$es_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
         || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0
         || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1:') === 0;

// ============================================
// 2. Forzar HTTPS (solo en producción)
// ============================================
if (!$es_local && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    $url_segura = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $url_segura, true, 301);
    exit();
}

// ============================================
// 3. Configuración de Sesión Segura
// ⚠️ DEBE hacerse ANTES de session_start()
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    // Cookie Secure solo si NO es local (local usa HTTP)
    if (!$es_local) {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.sid_length', 64);
    ini_set('session.sid_bits_per_character', 6);
    
    // ✅ Iniciar sesión (después de configurar)
    session_start();
}

// ============================================
// 4. Control de Inactividad de 30 Minutos
// ============================================
$tiempo_inactividad = 1800; // 30 minutos
if (isset($_SESSION['ultima_actividad'])) {
    if ((time() - $_SESSION['ultima_actividad']) > $tiempo_inactividad) {
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        
        header("Location: /login.php?expirada=1");
        exit();
    }
}
$_SESSION['ultima_actividad'] = time();

// ============================================
// 5. Regeneración de ID de Sesión (Anti-Fixation)
// ============================================
if (!isset($_SESSION['ultima_rotacion']) || (time() - $_SESSION['ultima_rotacion'] > 900)) {
    session_regenerate_id(true);
    $_SESSION['ultima_rotacion'] = time();
}
?>