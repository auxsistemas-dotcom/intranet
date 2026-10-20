<?php
// includes/config.php - CONEXIÓN A BD + CONFIGURACIÓN DE SESIÓN

// ============================================
// ZONA HORARIA (Colombia)
// ============================================
date_default_timezone_set('America/Bogota');

// ============================================
// ✅ INCLUIR SEGURIDAD PRIMERO
// (configura cookies, HTTPS y hace session_start)
// ============================================
require_once __DIR__ . '/seguridad.php';

// ⚠️ NO llamar a session_start() aquí — seguridad.php ya lo hizo

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================
$host   = 'localhost';
$dbname = 'intranet';
$user   = 'root';
$pass   = 'Armotor2026*';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // ✅ Seguridad extra
} catch (PDOException $e) {
    error_log("DB CONNECTION ERROR: " . $e->getMessage());
    http_response_code(500);
    die("Error interno del servidor. Contacte al administrador.");
}

// ============================================
// CONFIGURACIÓN DE SESIÓN - TIEMPO DE INACTIVIDAD
// ============================================
define('TIEMPO_INACTIVIDAD', 1800);

// ✅ URL base del proyecto (sitio corre en la raíz del dominio)
define('BASE_URL', '/');

// ============================================
// FUNCIÓN: VERIFICAR SESIÓN ACTIVA
// ============================================
function verificarSesionActiva() {
    if (!isset($_SESSION['usuario_id'])) {
        return true;
    }
    
    if (!isset($_SESSION['ultima_actividad'])) {
        $_SESSION['ultima_actividad'] = time();
        return true;
    }
    
    $tiempo_inactivo = time() - $_SESSION['ultima_actividad'];
    
    if ($tiempo_inactivo > TIEMPO_INACTIVIDAD) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        header('Location: ' . BASE_URL . 'login.php?expirada=1');
        exit();
    }
    
    $_SESSION['ultima_actividad'] = time();
    return true;
}
?>