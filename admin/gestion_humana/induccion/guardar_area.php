<?php
// admin/gestion_humana/induccion/guardar_area.php - Guardar área de Inducción
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
    $orden = intval($_POST['orden'] ?? 0);
    
    if (empty($nombre)) {
        $_SESSION['error'] = "❌ El nombre del área es obligatorio";
        header("Location: index.php");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO categorias_gestion_humana (nombre, descripcion, tipo, orden, activo) VALUES (?, ?, 'area', ?, 1)");
        $stmt->execute([$nombre, $descripcion, $orden]);
        
        $_SESSION['mensaje'] = "✅ Área creada correctamente";
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Error al crear el área: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Si no es POST, redirigir
header("Location: index.php");
exit();
?>