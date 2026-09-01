<?php
// admin/politicas_armotor/guardar.php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO: SOLO Administradores (rol_id = 1)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;

if ($rol_usuario != 1) {
    $_SESSION['error'] = "❌ No tienes permiso para realizar esta acción";
    header('Location: index.php');
    exit();
}

$error = '';
$mensaje = '';

// ============================================
// CONFIGURACIÓN DE ARCHIVOS
// ============================================

$extensiones_permitidas = [
    'pdf'  => 'PDF',
    'doc'  => 'Word',
    'docx' => 'Word',
    'xls'  => 'Excel',
    'xlsx' => 'Excel'
];

$max_size = 10 * 1024 * 1024; // 10MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    // ============================================
    // VALIDAR TÍTULO
    // ============================================
    if (empty($titulo)) {
        $error = "❌ El título es obligatorio";
    } 
    // ============================================
    // VALIDAR ARCHIVO
    // ============================================
    elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $error = "❌ Debes seleccionar un archivo";
    } else {
        $archivo = $_FILES['archivo'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $nombre_original = $archivo['name'];
        $tamano = $archivo['size'];
        
        // ============================================
        // VALIDAR EXTENSIÓN
        // ============================================
        if (!array_key_exists($extension, $extensiones_permitidas)) {
            $error = "❌ Formato no permitido. Usa: PDF, Word (.doc, .docx) o Excel (.xls, .xlsx)";
        } 
        // ============================================
        // VALIDAR TAMAÑO
        // ============================================
        elseif ($tamano > $max_size) {
            $error = "❌ El archivo no puede superar los 10MB";
        } 
        // ============================================
        // GUARDAR ARCHIVO
        // ============================================
        else {
            // Crear carpeta si no existe
            $carpeta = '../../uploads/documentos/politicas/';
            if (!file_exists($carpeta)) {
                mkdir($carpeta, 0777, true);
            }
            
            // Generar nombre único
            $nombre_archivo = 'politica_' . time() . '_' . uniqid() . '.' . $extension;
            $ruta_destino = $carpeta . $nombre_archivo;
            $ruta_db = 'uploads/documentos/politicas/' . $nombre_archivo;
            
            // Mover archivo
            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO politicas 
                        (titulo, descripcion, archivo_url, tipo_archivo, nombre_original, creado_por, activo, creado_el) 
                        VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmt->execute([
                        $titulo,
                        $descripcion,
                        $ruta_db,
                        $extensiones_permitidas[$extension],
                        $nombre_original,
                        $_SESSION['usuario_id']
                    ]);
                    
                    $mensaje = "✅ Política subida correctamente";
                } catch (PDOException $e) {
                    $error = "❌ Error al guardar en la base de datos: " . $e->getMessage();
                }
            } else {
                $error = "❌ Error al subir el archivo";
            }
        }
    }
}

// ============================================
// REDIRECCIONAR CON MENSAJE
// ============================================
if ($mensaje) {
    $_SESSION['mensaje'] = $mensaje;
    header("Location: index.php");
} else {
    $_SESSION['error'] = $error;
    header("Location: index.php");
}
exit();
?>