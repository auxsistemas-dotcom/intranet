<?php
require_once 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si viene con un redirect específico
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $redirect = $_GET['redirect'];
    if ($redirect === 'documentacion') {
        $redirect = 'documentacion/index.php';
    }
    $_SESSION['redirect_after_login'] = $redirect;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($usuario) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $usuarioData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ✅ VERIFICACIÓN CORRECTA CON PASSWORD_VERIFY
        if ($usuarioData && password_verify($password, $usuarioData['password'])) {
            $_SESSION['usuario_id'] = $usuarioData['id'];
            $_SESSION['nombre_completo'] = $usuarioData['nombre_completo'];
            $_SESSION['usuario'] = $usuarioData['usuario'];
            $_SESSION['rol'] = $usuarioData['rol_id'];  // ← USAR 'rol_id'
            
            // Verificar si hay una URL guardada para redirigir
            $redirect = $_SESSION['redirect_after_login'] ?? null;
            
            if ($redirect) {
                unset($_SESSION['redirect_after_login']);
                header("Location: $redirect");
            } else {
                // Redirigir según el rol
                if ($usuarioData['rol_id'] == 1) {  // ← USAR 'rol_id'
                    header("Location: admin/index.php");
                } else {
                    header("Location: index.php");
                }
            }
            exit();
        } else {
            $error = '❌ Usuario o contraseña incorrectos';
        }
    } else {
        $error = '❌ Por favor completa todos los campos';
    }
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
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
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
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
        .login-icon i {
            font-size: 35px;
            color: white;
        }
        .login-header h1 {
            font-size: 24px;
            color: #12232b;
        }
        .login-header p {
            color: #7f8c8d;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
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
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Usuario</label>
                <input type="text" name="usuario" required placeholder="Nombre de usuario">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Contraseña</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
        <div class="info-text">
        </div>
    </div>
</body>
</html>