<?php
// admin/operacion_vehicular/conductores/agregar.php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para acceder a Operación Vehicular";
    header('Location: ../../index.php');
    exit();
}

// ============================================
// ✅ PROCESAR SELECCIÓN DE USUARIO
// (guarda en sesión temporal, NO en BD)
// ============================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id'])) {
    $usuario_id = (int) $_POST['usuario_id'];
    
    // Validar que el usuario exista y esté activo
    $stmt = $pdo->prepare("SELECT id, nombre_completo FROM usuarios WHERE id = ? AND activo = 1");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        $error = "❌ Usuario no válido o inactivo";
    } else {
        // Verificar que NO sea ya conductor
        $stmt = $pdo->prepare("SELECT id FROM ope_conductores WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        
        if ($stmt->fetch()) {
            $error = "❌ Este usuario ya está registrado como conductor";
        } else {
            // ✅ GUARDAR EN SESIÓN TEMPORAL (no en BD)
            $_SESSION['conductor_temp'] = [
                'usuario_id' => $usuario_id,
                'nombre_completo' => $usuario['nombre_completo'],
                'añadido_por' => $_SESSION['usuario_id'],
                'licencias_temp' => []   // Array de licencias temporales
            ];
            
            // Redirigir a licencias (sin ID, porque aún no está en BD)
            header("Location: licencias.php");
            exit();
        }
    }
}

// ============================================
// ✅ BUSCAR USUARIOS (o mostrar todos)
// ============================================
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$usuarios = [];

if (!empty($busqueda)) {
    $sql = "
        SELECT u.id, u.nombre_completo, u.usuario, u.cargo, u.sede, u.perfil_usuario, u.email
        FROM usuarios u
        WHERE u.activo = 1
          AND NOT EXISTS (
              SELECT 1 FROM ope_conductores oc WHERE oc.usuario_id = u.id
          )
          AND (
              u.nombre_completo LIKE :busqueda1 
              OR u.usuario LIKE :busqueda2
              OR u.email LIKE :busqueda3
          )
        ORDER BY u.nombre_completo ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':busqueda1', '%' . $busqueda . '%');
    $stmt->bindValue(':busqueda2', '%' . $busqueda . '%');
    $stmt->bindValue(':busqueda3', '%' . $busqueda . '%');
    $stmt->execute();
    
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    $sql = "
        SELECT u.id, u.nombre_completo, u.usuario, u.cargo, u.sede, u.perfil_usuario, u.email
        FROM usuarios u
        WHERE u.activo = 1
          AND NOT EXISTS (
              SELECT 1 FROM ope_conductores oc WHERE oc.usuario_id = u.id
          )
        ORDER BY u.nombre_completo ASC
    ";
    
    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Conductor | Operación Vehicular</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #d3d3d3;
            color: #2c3e50;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 40px;
        }
        
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo { display: flex; flex-direction: column; gap: 5px; }
        .logo img { display: block; width: 130px; }
        
        .logo-titulo {
            color: #8aa8b8;
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0.5px;
            padding-left: 2px;
        }
        
        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .card-header {
            border-left: 4px solid #173742;
            padding-left: 15px;
            margin-bottom: 25px;
        }
        
        .card-header h1 {
            font-size: 22px;
            color: #12232b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header h1 i { color: #173742; }
        
        .card-header p {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .mensaje-error {
            background: #fadbd8;
            color: #922b21;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .buscador-form {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .buscador-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 250px;
        }
        
        .buscador-input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 16px;
            pointer-events: none;
        }
        
        .buscador-input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #d3d3d3;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .buscador-input:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        
        .btn-buscar {
            background: #173742;
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-buscar:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        
        .btn-limpiar {
            background: #7f8c8d;
            color: white;
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
            text-decoration: none;
        }
        
        .btn-limpiar:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }
        
        .resultados-info {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 12px;
            padding-left: 5px;
        }
        
        .resultados-info strong { color: #12232b; }
        
        .resultados-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .usuario-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            transition: all 0.2s ease;
            border-left: 4px solid #173742;
        }
        
        .usuario-item:hover {
            background: #f0f2f4;
            transform: translateX(3px);
        }
        
        .usuario-info { flex: 1; min-width: 0; }
        
        .usuario-nombre {
            font-weight: 600;
            color: #12232b;
            font-size: 15px;
            margin-bottom: 3px;
        }
        
        .usuario-meta {
            font-size: 12px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .usuario-meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .usuario-meta i { font-size: 11px; }
        
        .btn-añadir {
            background: #27ae60;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-añadir:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        
        .sin-resultados {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        
        .sin-resultados i {
            font-size: 42px;
            color: #d3d3d3;
            margin-bottom: 15px;
        }
        
        .sin-resultados h3 {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .sin-resultados p { font-size: 13px; }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo">
            <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet">
            <div class="logo-titulo">Operación Vehicular</div>
        </div>
        <a href="../index.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>
</header>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>
                <i class="fas fa-user-plus"></i>
                Añadir Conductor
            </h1>
            <p>Selecciona un usuario del sistema para habilitarlo como conductor de vehículos.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="mensaje-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="GET" action="" class="buscador-form">
            <div class="buscador-input-wrapper">
                <i class="fas fa-search"></i>
                <input 
                    type="text" 
                    name="buscar" 
                    class="buscador-input" 
                    placeholder="Filtrar por nombre, usuario o email..."
                    value="<?php echo htmlspecialchars($busqueda); ?>"
                    autocomplete="off"
                    autofocus
                >
            </div>
            <button type="submit" class="btn-buscar">
                <i class="fas fa-search"></i> Buscar
            </button>
            <?php if (!empty($busqueda)): ?>
                <a href="agregar.php" class="btn-limpiar">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            <?php endif; ?>
        </form>
        
        <?php if (count($usuarios) > 0): ?>
            <div class="resultados-info">
                <i class="fas fa-info-circle"></i> 
                <strong><?php echo count($usuarios); ?></strong> 
                usuario<?php echo count($usuarios) !== 1 ? 's' : ''; ?>
                <?php if (!empty($busqueda)): ?>
                    encontrado<?php echo count($usuarios) !== 1 ? 's' : ''; ?> para: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong>
                <?php else: ?>
                    disponible<?php echo count($usuarios) !== 1 ? 's' : ''; ?> para agregar como conductor
                <?php endif; ?>
            </div>
            
            <div class="resultados-lista">
                <?php foreach ($usuarios as $u): ?>
                    <div class="usuario-item">
                        <div class="usuario-info">
                            <div class="usuario-nombre">
                                <?php echo htmlspecialchars($u['nombre_completo']); ?>
                            </div>
                            <div class="usuario-meta">
                                <?php if (!empty($u['sede'])): ?>
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($u['sede']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- ✅ SIN confirm(), añade directo -->
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="usuario_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn-añadir">
                                <i class="fas fa-plus"></i> Añadir
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
        <?php else: ?>
            <div class="sin-resultados">
                <i class="fas fa-user-slash"></i>
                <?php if (!empty($busqueda)): ?>
                    <h3>Sin resultados</h3>
                    <p>No se encontraron usuarios que coincidan con: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong></p>
                <?php else: ?>
                    <h3>Sin usuarios disponibles</h3>
                    <p>Todos los usuarios activos ya están registrados como conductores.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>

</body>
</html>