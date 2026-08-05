<?php
// admin/gestion_humana/regis_usuarios/index.php - Registro de usuarios con progreso de inducción
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores (rol_id = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

// Solo administradores pueden ver el registro de usuarios
if ($rol_usuario != 1) {
    $_SESSION['error'] = "❌ No tienes permiso para ver el registro de usuarios";
    header('Location: ../index.php');
    exit();
}

$mensaje = '';
$error = '';

// Recuperar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ============================================
// FUNCIONES DE PROGRESO
// ============================================

function carpetaCompletadaRegistro($usuario_id, $carpeta_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN p.visto = 1 THEN 1 ELSE 0 END) as vistos
        FROM documentos_gestion_humana d
        LEFT JOIN progreso_gestion_humana p ON p.documento_id = d.id AND p.usuario_id = ?
        WHERE d.categoria_id = ? AND d.activo = 1
    ");
    $stmt->execute([$usuario_id, $carpeta_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_docs = intval($result['total'] ?? 0);
    $vistos = intval($result['vistos'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT completado FROM quiz_gestion_humana 
        WHERE usuario_id = ? AND categoria_id = ? AND completado = 1
    ");
    $stmt->execute([$usuario_id, $carpeta_id]);
    $quiz_completado = $stmt->fetch() ? true : false;
    
    return $total_docs > 0 && $vistos == $total_docs && $quiz_completado;
}

function induccionCompletadaRegistro($usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM categorias_gestion_humana WHERE tipo = 'area' AND activo = 1 ORDER BY orden ASC");
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($areas)) {
        return false;
    }
    
    foreach ($areas as $area_id) {
        $stmt = $pdo->prepare("SELECT id FROM categorias_gestion_humana WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1");
        $stmt->execute([$area_id]);
        $carpetas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($carpetas as $carpeta_id) {
            if (!carpetaCompletadaRegistro($usuario_id, $carpeta_id)) {
                return false;
            }
        }
    }
    
    return true;
}

function obtenerProgresoRegistro($usuario_id) {
    global $pdo;
    
    $total_areas = 0;
    $areas_completadas = 0;
    $detalle = [];
    
    $stmt = $pdo->prepare("SELECT id, nombre FROM categorias_gestion_humana WHERE tipo = 'area' AND activo = 1 ORDER BY orden ASC");
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($areas as $area) {
        $total_areas++;
        $area_completada = true;
        $carpetas_detalle = [];
        
        $stmt = $pdo->prepare("SELECT id, nombre FROM categorias_gestion_humana WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1 ORDER BY orden ASC");
        $stmt->execute([$area['id']]);
        $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($carpetas as $carpeta) {
            $completada = carpetaCompletadaRegistro($usuario_id, $carpeta['id']);
            $carpetas_detalle[] = [
                'nombre' => $carpeta['nombre'],
                'completada' => $completada
            ];
            
            if (!$completada) {
                $area_completada = false;
            }
        }
        
        if ($area_completada) {
            $areas_completadas++;
        }
        
        $detalle[] = [
            'nombre' => $area['nombre'],
            'completada' => $area_completada,
            'carpetas' => $carpetas_detalle
        ];
    }
    
    $porcentaje = $total_areas > 0 ? round(($areas_completadas / $total_areas) * 100) : 0;
    
    return [
        'total_areas' => $total_areas,
        'areas_completadas' => $areas_completadas,
        'porcentaje' => $porcentaje,
        'completada' => ($total_areas > 0 && $areas_completadas == $total_areas),
        'detalle' => $detalle
    ];
}

// ============================================
// OBTENER USUARIOS CON PROGRESO
// ============================================

// Obtener todos los usuarios (excepto administradores)
$sql = "SELECT u.id, u.nombre_completo, u.usuario, u.email, u.rol_id, u.activo, r.nombre as rol_nombre
        FROM usuarios u
        LEFT JOIN roles r ON u.rol_id = r.id
        WHERE u.rol_id != 1
        GROUP BY u.id
        ORDER BY u.nombre_completo ASC";

$usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Calcular progreso de cada usuario
foreach ($usuarios as $key => $usuario) {
    $progreso = obtenerProgresoRegistro($usuario['id']);
    $usuarios[$key]['porcentaje'] = $progreso['porcentaje'];
    $usuarios[$key]['completada'] = $progreso['completada'];
    $usuarios[$key]['areas_completadas'] = $progreso['areas_completadas'];
    $usuarios[$key]['total_areas'] = $progreso['total_areas'];
    $usuarios[$key]['detalle'] = $progreso['detalle'];
}

$no_usuarios = empty($usuarios);

// ✅ Búsqueda
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
if (!empty($busqueda)) {
    $usuarios_filtrados = [];
    foreach ($usuarios as $usuario) {
        if (stripos($usuario['nombre_completo'], $busqueda) !== false || 
            stripos($usuario['usuario'], $busqueda) !== false) {
            $usuarios_filtrados[] = $usuario;
        }
    }
    $usuarios = $usuarios_filtrados;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuarios | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }

        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        .header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { color: white; font-size: 24px; }
        .logo span { color: #445960; }
        .btn-volver {
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* ===== BUSCADOR ===== */
        .buscador-container {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .buscador-container form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex: 1;
            flex-wrap: wrap;
        }
        .buscador-container input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .buscador-container input[type="text"]:focus {
            outline: none;
            border-color: #173742;
        }
        .btn-buscar {
            background: #173742;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-buscar:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        .btn-limpiar {
            background: #7f8c8d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-limpiar:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }

        /* ===== TABLA ===== */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #d3d3d3; }
        th { background: #12232b; color: white; font-weight: 600; white-space: nowrap; }
        tr:hover { background: #f8f9fa; }

        /* ===== BADGES ===== */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .badge-activo { background: #d5f5e3; color: #1a7a3a; }
        .badge-inactivo { background: #fadbd8; color: #922b21; }
        .badge-supervisor { background: #f39c12; color: white; }
        .badge-usuario { background: #445960; color: white; }

        /* ===== BARRA DE PROGRESO ===== */
        .progress-bar-container {
            width: 120px;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
        }
        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .progress-bar.completed { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .progress-bar.medio { background: linear-gradient(90deg, #f39c12, #e67e22); }
        .progress-bar.bajo { background: linear-gradient(90deg, #3498db, #5dade2); }

        /* ===== BOTONES ===== */
        .btn-accion {
            padding: 5px 10px;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-accion:hover { transform: translateY(-2px); }

        .btn-certificado {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        .btn-certificado:hover { box-shadow: 0 4px 15px rgba(243,156,18,0.3); }

        .btn-disabled {
            background: #95a5a6;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .btn-disabled:hover { transform: none; }

        /* ===== MENSAJES ===== */
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state i { font-size: 48px; color: #d3d3d3; margin-bottom: 15px; }
        .empty-state p { font-size: 14px; }

        .text-muted { color: #7f8c8d; font-size: 12px; }
        .td-min { white-space: nowrap; }
        .text-center { text-align: center; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header-actions { flex-direction: column; align-items: stretch; }
            table { font-size: 12px; }
            th, td { padding: 8px 10px; }
            .buscador-container form { flex-direction: column; }
            .buscador-container input[type="text"] { width: 100%; min-width: auto; }
            .buscador-container .btn-buscar,
            .buscador-container .btn-limpiar { width: 100%; text-align: center; }
            .progress-bar-container { width: 80px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <span style="color: #8aa8b8; font-size: 14px;">| Registro de Usuarios</span>
            </div>
            <a href="../index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="header-actions">
                <h2><i class="fas fa-users"></i> Progreso de Inducción</h2>
            </div>

            <!-- ===== BUSCADOR ===== -->
            <div class="buscador-container">
                <form method="GET" action="">
                    <input type="text" name="buscar" placeholder="Buscar por nombre o usuario..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    <button type="submit" class="btn-buscar">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="index.php" class="btn-limpiar">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($no_usuarios): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <p>No hay usuarios registrados.</p>
                    <p style="font-size: 13px; margin-top: 5px;">Los usuarios aparecerán aquí cuando comiencen la inducción.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Estado</th>
                                <th>Progreso</th>
                                <th class="text-center">Certificado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>
                                    </td>
                                    <td>
                                        <code style="background: #f0f0f0; padding: 2px 10px; border-radius: 4px; font-size: 14px;">
                                            <?php echo htmlspecialchars($usuario['usuario']); ?>
                                        </code>
                                    </td>
                                    <td class="td-min">
                                        <span class="badge <?php echo $usuario['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <?php echo $usuario['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="progress-bar-container">
                                                <div class="progress-bar <?php 
                                                    echo $usuario['completada'] ? 'completed' : 
                                                        ($usuario['porcentaje'] >= 50 ? 'medio' : 'bajo'); 
                                                ?>" 
                                                style="width: <?php echo $usuario['porcentaje']; ?>%;">
                                                </div>
                                            </div>
                                            <span style="font-weight: 600; font-size: 13px; min-width: 40px;">
                                                <?php echo $usuario['porcentaje']; ?>%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center td-min">
                                        <?php if ($usuario['completada']): ?>
                                            <a href="ver_pdf.php?usuario_id=<?php echo $usuario['id']; ?>" 
                                               target="_blank" 
                                               class="btn-accion btn-certificado" 
                                               title="Descargar certificado">
                                                <i class="fas fa-certificate"></i> PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="btn-accion btn-disabled" title="Usuario no ha completado la inducción">
                                                <i class="fas fa-lock"></i> Pendiente
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 15px; color: #7f8c8d; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> 
                    Total: <strong><?php echo count($usuarios); ?></strong> usuarios
                    <?php if (!empty($busqueda)): ?>
                        | Resultados para: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>