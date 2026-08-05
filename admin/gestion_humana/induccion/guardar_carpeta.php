<?php
// admin/gestion_humana/induccion/guardar_carpeta.php - Guardar carpeta de Inducción
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: Administradores (rol_id = 1) o Supervisores (rol_id = 2)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar permisos de capacitaciones";
    header('Location: ../../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $padre_id = intval($_POST['padre_id'] ?? 0);
    $orden = intval($_POST['orden'] ?? 0);
    $quiz_url = trim($_POST['quiz_url'] ?? ''); // ✅ CAMPO OPCIONAL
    
    if (empty($nombre)) {
        $_SESSION['error'] = "❌ El nombre de la carpeta es obligatorio";
        header("Location: index.php");
        exit();
    }
    
    if ($padre_id == 0) {
        $_SESSION['error'] = "❌ Debes seleccionar un área padre";
        header("Location: index.php");
        exit();
    }
    
    try {
        // ✅ Incluir quiz_url en la inserción (puede ser NULL o vacío)
        $stmt = $pdo->prepare("
            INSERT INTO categorias_gestion_humana 
            (nombre, descripcion, tipo, padre_id, orden, quiz_url, activo) 
            VALUES (?, ?, 'carpeta', ?, ?, ?, 1)
        ");
        $stmt->execute([$nombre, $descripcion, $padre_id, $orden, $quiz_url]);
        
        $_SESSION['mensaje'] = "✅ Carpeta creada correctamente";
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Error al crear la carpeta: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Si no es POST, redirigir
header("Location: index.php");
exit();
?>