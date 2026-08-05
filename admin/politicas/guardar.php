<?php
// admin/politicas_armotor/guardar.php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

requiereRol('admin');

$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if (empty($titulo)) {
        $error = "❌ El título es obligatorio";
    } elseif (!isset($_FILES['archivo_pdf']) || $_FILES['archivo_pdf']['error'] !== UPLOAD_ERR_OK) {
        $error = "❌ Debes seleccionar un archivo PDF";
    } else {
        $archivo = $_FILES['archivo_pdf'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if ($extension !== 'pdf') {
            $error = "❌ Solo se permiten archivos PDF";
        } elseif ($archivo['size'] > 6 * 1024 * 1024) {
            $error = "❌ El PDF no puede superar los 6MB";
        } else {
            // Ruta: uploads/documentos/politicas_armotor/
            $carpeta = '../../uploads/documentos/politicas/';
            if (!file_exists($carpeta)) {
                mkdir($carpeta, 0777, true);
            }
            
            $nombre_archivo = time() . '_' . uniqid() . '.pdf';
            $ruta_destino = $carpeta . $nombre_archivo;
            $ruta_db = 'uploads/documentos/politicas/' . $nombre_archivo;
            
            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                $stmt = $pdo->prepare("INSERT INTO politicas (titulo, descripcion, archivo_url, creado_por) VALUES (?, ?, ?, ?)");
                $stmt->execute([$titulo, $descripcion, $ruta_db, $_SESSION['usuario_id']]);
                $mensaje = "✅ Política subida correctamente";
            } else {
                $error = "❌ Error al subir el archivo";
            }
        }
    }
}

if ($mensaje) {
    header("Location: index.php?mensaje=" . urlencode($mensaje));
} else {
    header("Location: index.php?error=" . urlencode($error));
}
exit();
?>