<?php
// includes/config.php - CONEXIÓN A BD + CONFIGURACIÓN DE SESIÓN

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'intranet';
$user = 'root';
$pass = 'Armotor2026*';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ============================================
// CONFIGURACIÓN DE SESIÓN - TIEMPO DE INACTIVIDAD
// ============================================

// Tiempo de inactividad en segundos (30 minutos = 1800 segundos)
define('TIEMPO_INACTIVIDAD', 1800);

// Definir la URL base del proyecto
define('BASE_URL', '/intranet/');  // Cambia según tu estructura

// Función para verificar si la sesión ha expirado
function verificarSesionActiva() {
    // Si no hay sesión, no hacer nada
    if (!isset($_SESSION['usuario_id'])) {
        return true;
    }
    
    // Si no hay tiempo de actividad registrado, registrar ahora
    if (!isset($_SESSION['ultima_actividad'])) {
        $_SESSION['ultima_actividad'] = time();
        return true;
    }
    
    // Calcular tiempo de inactividad
    $tiempo_inactivo = time() - $_SESSION['ultima_actividad'];
    
    // Si supera el tiempo permitido, cerrar sesión
    if ($tiempo_inactivo > TIEMPO_INACTIVIDAD) {
        // Limpiar sesión
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        // Redirigir al login con mensaje
        header('Location: ' . BASE_URL . 'login.php?mensaje=⏰ Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.');
        exit();
    }
    
    // Actualizar tiempo de actividad
    $_SESSION['ultima_actividad'] = time();
    return true;
}
?>