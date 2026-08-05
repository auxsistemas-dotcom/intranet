<?php
// admin/documentos_sig/guardar.php - Guardar documento SIG
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id = $_SESSION['usuario_id'];

// Admin: acceso total
if ($rol_usuario == 1) {
    // Tiene acceso
} 
// Supervisor: verificar permiso
elseif ($rol_usuario == 2) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as tiene_permiso 
        FROM permisos_usuarios pu
        JOIN modulos m ON pu.modulo_id = m.id
        WHERE pu.usuario_id = ? AND m.nombre = 'documentos_sig' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para guardar documentos SIG";
        header('Location: ../index.php');
        exit();
    }
} 
// Usuario normal: sin acceso
else {
    header('Location: ../index.php');
    exit();
}

$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if (empty($titulo)) {
        $error = "❌ El título es obligatorio";
    } elseif (!isset($_FILES['archivo_pdf']) || $_FILES['archivo_pdf']['error'] !== UPLOAD_ERR_OK) {
        $error = "❌ Debes seleccionar un archivo";
    } else {
        $archivo = $_FILES['archivo_pdf'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $extensiones_permitidas = ['pdf', 'xls', 'xlsx'];
        
        if (!in_array($extension, $extensiones_permitidas)) {
            $error = "❌ Formatos permitidos: PDF, XLS, XLSX";
        } elseif ($archivo['size'] > 6 * 1024 * 1024) {
            $error = "❌ El archivo no puede superar los 6MB";
        } else {
            // Ruta: uploads/documentos/documentos_sig/
            $carpeta = '../../uploads/documentos/documentos_sig/';
            if (!file_exists($carpeta)) {
                mkdir($carpeta, 0777, true);
            }
            
            // Mantener la extensión original
            $nombre_archivo = time() . '_' . uniqid() . '.' . $extension;
            $ruta_destino = $carpeta . $nombre_archivo;
            $ruta_db = 'uploads/documentos/documentos_sig/' . $nombre_archivo;
            
            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                // ✅ CORREGIDO: Insertar con todos los campos
                $stmt = $pdo->prepare("INSERT INTO documentos_sig (titulo, descripcion, archivo_url, creado_por) VALUES (?, ?, ?, ?)");
                $stmt->execute([$titulo, $descripcion, $ruta_db, $usuario_id]);
                $mensaje = "✅ Documento subido correctamente";
            } else {
                $error = "❌ Error al subir el archivo";
            }
        }
    }
}

// ✅ CORREGIDO: Usar sesión para mensajes
if ($mensaje) {
    $_SESSION['mensaje'] = $mensaje;
    header("Location: index.php");
} else {
    $_SESSION['error'] = $error;
    header("Location: index.php");
}
exit();
?>