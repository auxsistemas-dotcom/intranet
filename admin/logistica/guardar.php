<?php
// admin/logistica/guardar.php - Guardar archivo de logística
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
        WHERE pu.usuario_id = ? AND m.nombre = 'logistica' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para guardar archivos de logística";
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
    $marca = $_POST['marca'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if (empty($marca) || empty($titulo)) {
        $error = "❌ La marca y el título son obligatorios";
    } else {
        // Verificar si ya existe un archivo activo para esta marca
        $stmt = $pdo->prepare("SELECT id FROM logistica WHERE marca = ? AND activo = 1");
        $stmt->execute([$marca]);
        if ($stmt->fetch()) {
            $error = "❌ Ya existe un archivo activo para esta marca. Debes eliminar o inactivar el actual primero.";
        } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $error = "❌ Debes seleccionar un archivo";
        } else {
            $archivo = $_FILES['archivo'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $extensiones_permitidas = ['xls', 'xlsx'];
            
            if (!in_array($extension, $extensiones_permitidas)) {
                $error = "❌ Formatos permitidos: XLS, XLSX";
            } elseif ($archivo['size'] > 6 * 1024 * 1024) {
                $error = "❌ El archivo no puede superar los 6MB";
            } else {
                $carpeta = '../../uploads/logistica/';
                if (!file_exists($carpeta)) {
                    mkdir($carpeta, 0777, true);
                }
                
                $nombre_archivo = time() . '_' . uniqid() . '.' . $extension;
                $ruta_destino = $carpeta . $nombre_archivo;
                $ruta_db = 'uploads/logistica/' . $nombre_archivo;
                
                if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                    // ✅ CORREGIDO: Usar $usuario_id en lugar de $_SESSION['usuario_id']
                    $stmt = $pdo->prepare("INSERT INTO logistica (marca, titulo, descripcion, archivo_url, creado_por) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$marca, $titulo, $descripcion, $ruta_db, $usuario_id]);
                    $mensaje = "✅ Archivo subido correctamente";
                } else {
                    $error = "❌ Error al subir el archivo";
                }
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