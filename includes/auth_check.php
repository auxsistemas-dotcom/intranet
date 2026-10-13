<?php
// includes/auth_check.php - FUNCIONES DE AUTENTICACIÓN (REFORZADO)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// INCLUIR CONFIG (para TIEMPO_INACTIVIDAD y verificarSesionActiva)
// ============================================
if (!function_exists('verificarSesionActiva')) {
    require_once __DIR__ . '/config.php';
}

// ============================================
// ✅ FUNCIONES DE SEGURIDAD EXTRA
// ============================================

/**
 * Verifica que la IP y el User-Agent no hayan cambiado.
 * Detecta robo de cookie de sesión.
 */
function verificarBindingSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        return true; // No hay sesión aún, nada que validar
    }

    $ip_actual = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua_actual = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Verificar IP (solo si tenemos dato guardado - compatibilidad)
    if (!empty($_SESSION['ip']) && $_SESSION['ip'] !== $ip_actual) {
        error_log("SECURITY: IP mismatch. user_id=" . $_SESSION['usuario_id']
            . " esperada=" . $_SESSION['ip'] . " actual=" . $ip_actual);
        cerrarSesionPorSeguridad();
        return false;
    }

    // Verificar User-Agent (solo si tenemos dato guardado)
    if (!empty($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $ua_actual) {
        error_log("SECURITY: UA mismatch. user_id=" . $_SESSION['usuario_id']);
        cerrarSesionPorSeguridad();
        return false;
    }

    return true;
}

/**
 * Regenera el ID de sesión cada 15 minutos.
 * Limita la vida útil de una cookie robada.
 */
function rotarIdSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        return;
    }

    $ahora = time();
    if (!isset($_SESSION['ultima_rotacion']) || ($ahora - $_SESSION['ultima_rotacion']) > 900) {
        session_regenerate_id(true);
        $_SESSION['ultima_rotacion'] = $ahora;
    }
}

/**
 * Cierra la sesión por motivos de seguridad y redirige al login.
 */
function cerrarSesionPorSeguridad() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: /login.php?expirada=1');
    exit();
}

// ============================================
// EJECUTAR VERIFICACIONES AL CARGAR
// ============================================

// 1. Verificar inactividad (función del config)
if (function_exists('verificarSesionActiva')) {
    verificarSesionActiva();
}

// 2. Verificar binding IP/UA
verificarBindingSesion();

// 3. Rotar ID de sesión si aplica
rotarIdSesion();

// ============================================
// FUNCIONES DE AUTENTICACIÓN
// ============================================

function normalizarRol($rol) {
    if (is_string($rol)) {
        $rol = strtolower(trim($rol));
        $mapa = [
            'admin' => 1,
            'administrador' => 1,
            'supervisor' => 2,
            'usuario' => 3,
            'user' => 3,
            '1' => 1,
            '2' => 2,
            '3' => 3,
        ];

        return $mapa[$rol] ?? 3;
    }

    if (is_int($rol) || is_numeric($rol)) {
        $valor = (int) $rol;
        return in_array($valor, [1, 2, 3]) ? $valor : 3;
    }

    return 3;
}

function estaLogueado() {
    return isset($_SESSION['usuario_id']);
}

function obtenerUsuario() {
    if (!estaLogueado()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'] ?? null,
        'nombre_completo' => $_SESSION['nombre_completo'] ?? null,
        'usuario' => $_SESSION['usuario'] ?? null,
        'rol' => normalizarRol($_SESSION['rol'] ?? 3)
    ];
}

function tieneRol($roles) {
    if (!estaLogueado()) {
        return false;
    }
    
    $rolUsuario = normalizarRol($_SESSION['rol'] ?? 3);
    
    if (is_array($roles)) {
        $rolesNormalizados = array_map('normalizarRol', $roles);
        return in_array($rolUsuario, $rolesNormalizados);
    }
    
    return $rolUsuario === normalizarRol($roles);
}

function obtenerRutaRelativa($ruta) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $segmentos = explode('/', trim($script, '/'));
    $profundidad = max(0, count($segmentos) - 2);
    $prefijo = str_repeat('../', $profundidad);

    return $prefijo . ltrim($ruta, '/');
}

function redirigir($ruta) {
    header('Location: ' . obtenerRutaRelativa($ruta));
    exit();
}

function requiereLogin() {
    // Verificar sesión activa antes de continuar
    if (function_exists('verificarSesionActiva')) {
        verificarSesionActiva();
    }
    
    if (!estaLogueado()) {
        // ✅ VALIDACIÓN ANTI OPEN-REDIRECT
        // Solo guardar REQUEST_URI si es una ruta interna válida
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (
            is_string($uri) &&
            strlen($uri) < 500 &&
            strpos($uri, '://') === false &&
            strpos($uri, '//') === false &&
            strpos($uri, '..') === false
        ) {
            $_SESSION['redirect_after_login'] = $uri;
        }
        redirigir('login.php');
    }
}

function requiereRol($roles = 1) {
    requiereLogin();
    
    if (!tieneRol($roles)) {
        $destino = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false)
            ? 'admin/index.php?error=sin_permiso'
            : 'index.php?error=sin_permiso';

        redirigir($destino);
    }
}

// Verificar si el usuario tiene acceso a un módulo específico en el panel admin
function tieneAccesoModulo($modulo) {
    global $pdo;
    
    if (!estaLogueado()) {
        return false;
    }
    
    $rol = normalizarRol($_SESSION['rol'] ?? 3);
    
    // Administrador (1) tiene acceso a todo
    if ($rol == 1) {
        return true;
    }
    
    // Usuario (3) no tiene acceso a nada en admin
    if ($rol == 3) {
        return false;
    }
    
    // Supervisor (2): verificar permisos en la base de datos
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM roles_permisos rp
            JOIN usuarios_roles ur ON rp.rol_id = ur.rol_id
            JOIN permisos p ON rp.permiso_id = p.id
            WHERE ur.usuario_id = ? AND p.modulo = ? AND p.accion = 'acceso' AND p.activo = 1
        ");
        $stmt->execute([$_SESSION['usuario_id'], $modulo]);
        return $stmt->fetch() !== false;
    } catch(PDOException $e) {
        return false;
    }
}

// Obtener los módulos a los que el usuario tiene acceso (para supervisor)
function obtenerModulosAcceso() {
    global $pdo;
    
    if (!estaLogueado()) {
        return [];
    }
    
    $rol = normalizarRol($_SESSION['rol'] ?? 3);
    
    // Administrador (1): todos los módulos
    if ($rol == 1) {
        $stmt = $pdo->query("SELECT DISTINCT modulo FROM permisos WHERE accion = 'acceso' AND activo = 1");
        $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $resultados;
    }
    
    // Usuario (3): sin acceso
    if ($rol == 3) {
        return [];
    }
    
    // Supervisor (2): módulos asignados
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.modulo 
            FROM roles_permisos rp
            JOIN usuarios_roles ur ON rp.rol_id = ur.rol_id
            JOIN permisos p ON rp.permiso_id = p.id
            WHERE ur.usuario_id = ? AND p.accion = 'acceso' AND p.activo = 1
        ");
        $stmt->execute([$_SESSION['usuario_id']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        return [];
    }
}
?>