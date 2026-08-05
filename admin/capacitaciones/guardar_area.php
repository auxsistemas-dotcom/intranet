<?php
// admin/documentacion/guardar_area.php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

requiereRol('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $orden = intval($_POST['orden'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'area';
    
    if (!empty($nombre)) {
        if ($tipo === 'carpeta') {
            $padre_id = intval($_POST['padre_id'] ?? 0);
            
            if ($padre_id > 0) {
                $stmt = $pdo->prepare("INSERT INTO categorias_capacitaciones (nombre, descripcion, orden, tipo, padre_id) VALUES (?, ?, ?, 'carpeta', ?)");
                $stmt->execute([$nombre, $descripcion, $orden, $padre_id]);
            } else {
                header("Location: index.php?error=" . urlencode("❌ Debes seleccionar un área padre"));
                exit();
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO categorias_capacitaciones (nombre, descripcion, orden, tipo) VALUES (?, ?, ?, 'area')");
            $stmt->execute([$nombre, $descripcion, $orden]);
        }
        
        header("Location: index.php?mensaje=" . urlencode("✅ Elemento creado correctamente"));
    } else {
        header("Location: index.php?error=" . urlencode("❌ El nombre es obligatorio"));
    }
    exit();
}
?>