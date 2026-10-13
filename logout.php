<?php
// logout.php - Cierre de sesión seguro

require_once 'includes/seguridad.php'; // HTTPS + cookies seguras + session_start

// ============================================
// CERRAR SESIÓN DE FORMA SEGURA
// ============================================

// 1. Vaciar todas las variables de sesión
$_SESSION = array();

// 2. Borrar la cookie de sesión del navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destruir la sesión en el servidor
session_destroy();

// 4. Redirigir al login con flag de logout exitoso
header('Location: /intranet/login.php?logout=1');
exit();
?>