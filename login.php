<?php
// login.php - Login blindado con seguridad extrema
// Incluye: CSRF, anti-fixation, anti open-redirect, mensajes genéricos, IP+UA binding

require_once 'includes/seguridad.php'; // ⚠️ DEBE ir PRIMERO - configura HTTPS, cookies, sesión
require_once 'includes/config.php';

// Si ya está logueado, redirigir según su rol
if (isset($_SESSION['usuario_id'])) {
    if (($_SESSION['rol'] ?? 3) == 1) {
        header('Location: admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// ============================================
// GENERAR TOKEN CSRF
// ============================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// ============================================
// CAPTURAR REDIRECT (validado)
// ============================================
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $redirect_raw = $_GET['redirect'];
    
    // Alias amigables
    if ($redirect_raw === 'documentacion') {
        $redirect_raw = 'documentacion/index.php';
    }
    
    // ✅ VALIDACIÓN ANTI OPEN-REDIRECT
    // Solo permitir rutas RELATIVAS internas. Bloquear http://, https://, //, ../
    $redirect_limpio = null;
    if (
        is_string($redirect_raw) &&
        strlen($redirect_raw) < 500 &&
        strpos($redirect_raw, '://') === false &&
        strpos($redirect_raw, '//') === false &&
        strpos($redirect_raw, '..') === false &&
        preg_match('/^[a-zA-Z0-9_\-\/\.\?=&]+$/', $redirect_raw)
    ) {
        $redirect_limpio = $redirect_raw;
    }
    
    if ($redirect_limpio !== null) {
        $_SESSION['redirect_after_login'] = $redirect_limpio;
    }
}

// ============================================
// PROCESAR LOGIN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ✅ 1. VALIDAR CSRF
    $csrf_ok = isset($_POST['csrf_token']) 
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    
    if (!$csrf_ok) {
        $error = '❌ Error de seguridad. Recarga la página e intenta de nuevo.';
        // Regenerar token por si acaso
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        
        // ✅ 2. RATE LIMITING (por sesión, 5 intentos en 15 min)
        $ahora = time();
        if (!isset($_SESSION['intentos_login'])) {
            $_SESSION['intentos_login'] = [];
        }
        // Limpiar intentos viejos (>15 min)
        $_SESSION['intentos_login'] = array_filter(
            $_SESSION['intentos_login'],
            fn($t) => ($ahora - $t) < 900
        );
        
        if (count($_SESSION['intentos_login']) >= 5) {
            $error = '❌ Demasiados intentos fallidos. Espera 15 minutos.';
            error_log("LOGIN BLOCKED: IP=" . ($_SERVER['REMOTE_ADDR'] ?? '?') . " intentos=" . count($_SESSION['intentos_login']));
        } else {
            
            // ✅ 3. VALIDAR CAMPOS
            $usuario  = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Límite de longitud (evita DoS con passwords gigantes)
            if (strlen($usuario) > 100 || strlen($password) > 200) {
                $error = '❌ Credenciales inválidas';
                $_SESSION['intentos_login'][] = $ahora;
            } elseif (empty($usuario) || empty($password)) {
                $error = '❌ Por favor completa todos los campos';
            } else {
                
                // ✅ 4. CONSULTA PARAMETRIZADA - Solo columnas necesarias
                $stmt = $pdo->prepare(
                    "SELECT id, nombre_completo, usuario, password, rol_id 
                     FROM usuarios 
                     WHERE usuario = ? AND activo = 1 
                     LIMIT 1"
                );
                $stmt->execute([$usuario]);
                $usuarioData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // ✅ 5. VERIFICAR PASSWORD (con timing-safe)
                // Siempre llamar a password_verify, incluso si no hay usuario,
                // para evitar timing attacks que revelen si el usuario existe.
                $hash_dummy = '$argon2id$v=19$m=65536,t=4,p=1$ZHVtbXlkdW1teWR1bW15$0000000000000000000000000000000000000000000';
                $hash_a_usar = $usuarioData['password'] ?? $hash_dummy;
                $password_ok = password_verify($password, $hash_a_usar);
                
                if ($usuarioData && $password_ok) {
                    // ✅ ¡ÉXITO!
                    
                    // 6. ANTI SESSION FIXATION
                    session_regenerate_id(true);
                    
                    // 7. GUARDAR DATOS EN SESIÓN
                    $_SESSION['usuario_id']      = $usuarioData['id'];
                    $_SESSION['nombre_completo'] = $usuarioData['nombre_completo'];
                    $_SESSION['usuario']         = $usuarioData['usuario'];
                    $_SESSION['rol']             = $usuarioData['rol_id'];
                    
                    // 8. BINDING IP + USER-AGENT (detecta robo de sesión)
                    $_SESSION['ip']         = $_SERVER['REMOTE_ADDR'] ?? '';
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $_SESSION['ultima_actividad'] = time();
                    $_SESSION['ultima_rotacion']  = time();
                    
                    // 9. LIMPIAR CSRF Y INTENTOS
                    unset($_SESSION['csrf_token'], $_SESSION['intentos_login']);
                    
                    // 10. REGENERAR NUEVO CSRF para la próxima sesión
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // 11. REDIRIGIR (validando destino)
                    $redirect = $_SESSION['redirect_after_login'] ?? null;
                    unset($_SESSION['redirect_after_login']);
                    
                    if ($redirect && strpos($redirect, '://') === false && strpos($redirect, '//') === false) {
                        header("Location: $redirect");
                    } elseif ($usuarioData['rol_id'] == 1) {
                        header("Location: admin/index.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                    
                } else {
                    // ✅ 12. MENSAJE GENÉRICO (no revela si el usuario existe)
                    $error = '❌ Credenciales inválidas';
                    $_SESSION['intentos_login'][] = $ahora;
                    
                    // Log interno para auditoría
                    error_log("LOGIN FAIL: IP=" . ($_SERVER['REMOTE_ADDR'] ?? '?') 
                        . " user=" . substr($usuario, 0, 20));
                }
            }
        }
    }
}

// Refrescar token CSRF si por alguna razón no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INTRANET - Iniciar Sesión</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #12232b 0%, #173742 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-icon {
            width: 130px;
            height: 80px;
            background: linear-gradient(135deg, #173742, #445960);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .login-icon i { font-size: 35px; color: white; }
        .login-header h1 { font-size: 24px; color: #12232b; }
        .login-header p { color: #7f8c8d; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #173742;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info-text {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <img src="uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
            </div>
            <h1>INTRANET</h1>
            <p>Inicia sesión para acceder a tus herramientas</p>
        </div>
        
        <!-- ✅ MENSAJE DE LOGOUT EXITOSO (auto-cierre en 1.5s) -->
        <?php if (isset($_GET['logout'])): ?>
            <div class="error-message" id="msgLogout" style="background: #d1fae5; color: #065f46; transition: opacity 0.3s ease;">
                ✅ Sesión cerrada correctamente. Hasta pronto.
            </div>
            <script>
                setTimeout(function() {
                    var msg = document.getElementById('msgLogout');
                    if (msg) {
                        msg.style.opacity = '0';
                        setTimeout(function() {
                            msg.style.display = 'none';
                        }, 300); // Esperar a que termine la transición
                    }
                }, 1500); // 1.5 segundos
            </script>
        <?php endif; ?>
        
        <!-- ✅ MENSAJE DE SESIÓN EXPIRADA -->
        <?php if (isset($_GET['expirada'])): ?>
            <div class="error-message" style="background: #fef3c7; color: #92400e;">
                ⏰ Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.
            </div>
        <?php endif; ?>
        
        <!-- ✅ MENSAJE DE ERROR DE LOGIN -->
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <!-- ✅ TOKEN CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> Usuario</label>
                <input type="text" name="usuario" required placeholder="Nombre de usuario" autocomplete="username" maxlength="100">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Contraseña</label>
                <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password" maxlength="200">
            </div>
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
        <div class="info-text">
        </div>
    </div>
</body>
</html>