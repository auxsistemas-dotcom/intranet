<?php
// admin/gestion_humana/induccion/guardar_documento.php - Guardar documento de Inducción
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$categoria_id = intval($_POST['categoria_id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$tipo_archivo = $_POST['tipo_archivo'] ?? 'pdf';
$url = trim($_POST['url'] ?? '');
$orden = intval($_POST['orden'] ?? 0);

if (empty($titulo) || $categoria_id == 0) {
    $_SESSION['error'] = "❌ El título y la categoría son obligatorios";
    header("Location: documentos.php?categoria_id=" . $categoria_id);
    exit();
}

try {
    $ruta_db = '';
    
    // Procesar archivo subido
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        $extensiones_permitidas = [
            'pdf' => ['pdf'],
            'word' => ['doc', 'docx'],
            'excel' => ['xls', 'xlsx'],
            'imagen' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
        ];
        
        if (!in_array($extension, $extensiones_permitidas[$tipo_archivo] ?? ['pdf'])) {
            $_SESSION['error'] = "❌ Extensión no permitida para el tipo seleccionado";
            header("Location: documentos.php?categoria_id=" . $categoria_id);
            exit();
        }
        
        if ($archivo['size'] > 10 * 1024 * 1024) {
            $_SESSION['error'] = "❌ El archivo no puede superar los 10MB";
            header("Location: documentos.php?categoria_id=" . $categoria_id);
            exit();
        }
        
        $carpeta_destino = '../../../uploads/documentos/gestion_humana/';
        if (!file_exists($carpeta_destino)) {
            mkdir($carpeta_destino, 0777, true);
        }
        
        $nombre_archivo = time() . '_' . uniqid() . '.' . $extension;
        $ruta_destino = $carpeta_destino . $nombre_archivo;
        $ruta_db = 'uploads/documentos/gestion_humana/' . $nombre_archivo;
        
        if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
            $_SESSION['error'] = "❌ Error al subir el archivo";
            header("Location: documentos.php?categoria_id=" . $categoria_id);
            exit();
        }
    } elseif (!empty($url)) {
        $ruta_db = $url;
    } else {
        $_SESSION['error'] = "❌ Debes seleccionar un archivo o proporcionar una URL";
        header("Location: documentos.php?categoria_id=" . $categoria_id);
        exit();
    }
    
    $stmt = $pdo->prepare("INSERT INTO documentos_gestion_humana (categoria_id, titulo, descripcion, tipo_archivo, url, orden, activo, creado_por) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
    $stmt->execute([$categoria_id, $titulo, $descripcion, $tipo_archivo, $ruta_db, $orden, $_SESSION['usuario_id']]);
    
    $_SESSION['mensaje'] = "✅ Documento guardado correctamente";
    header("Location: documentos.php?categoria_id=" . $categoria_id);
    exit();
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Error al guardar: " . $e->getMessage();
    header("Location: documentos.php?categoria_id=" . $categoria_id);
    exit();
}
?>