<?php
// includes/auth_check.php - FUNCIONES DE AUTENTICACIÓN

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// VERIFICAR SESIÓN POR INACTIVIDAD
// ============================================

// Incluir config para usar TIEMPO_INACTIVIDAD y la función verificarSesionActiva()
if (!function_exists('verificarSesionActiva')) {
    require_once 'config.php';
}

// ============================================
// EJECUTAR VERIFICACIÓN AL CARGAR
// ============================================

// Verificar sesión activa (esto redirige automáticamente si expiró)
if (function_exists('verificarSesionActiva')) {
    verificarSesionActiva();
}

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
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
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