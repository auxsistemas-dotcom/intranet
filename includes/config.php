<?php
// includes/config.php - CONEXIÓN A BD + CONFIGURACIÓN DE SESIÓN

// ============================================
// ZONA HORARIA (Colombia)
// ============================================
date_default_timezone_set('America/Bogota');

// ============================================
// INICIAR SESIÓN
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================
// ⚠️ TODO: mover credenciales a variables de entorno (.env) en producción
$host   = 'localhost';
$dbname = 'intranet';
$user   = 'root';
$pass   = 'Armotor2026*';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // ✅ Seguridad extra
} catch (PDOException $e) {
    // ✅ Log interno (no se muestra al usuario)
    error_log("DB CONNECTION ERROR: " . $e->getMessage());
    // ✅ Mensaje genérico al usuario (no revela detalles internos)
    http_response_code(500);
    die("Error interno del servidor. Contacte al administrador.");
}

// ============================================
// CONFIGURACIÓN DE SESIÓN - TIEMPO DE INACTIVIDAD
// ============================================

// Tiempo de inactividad en segundos (30 minutos = 1800 segundos)
define('TIEMPO_INACTIVIDAD', 1800);

// ✅ URL base del proyecto (sitio corre en la raíz del dominio)
define('BASE_URL', '/');

// ============================================
// FUNCIÓN: VERIFICAR SESIÓN ACTIVA
// ============================================
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
        
        // ✅ Redirigir al login con flag (sin emoji, más limpio)
        header('Location: ' . BASE_URL . 'login.php?expirada=1');
        exit();
    }
    
    // Actualizar tiempo de actividad
    $_SESSION['ultima_actividad'] = time();
    return true;
}
?>