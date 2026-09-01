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

// ============================================
// LISTA DE CATEGORÍAS
// ============================================
$categorias_validas = [
    'Administracion De La Infraestructura',
    'Gestion Contable Y Financiera',
    'Gestion De La Relacion Con El Cliente',
    'Gestion Del Talento Humano',
    'Gestion Logistica',
    'Planeacion Y Seguimiento Organizacional',
    'Posventa',
    'Venta'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    // ============================================
    // VALIDACIONES
    // ============================================
    if (empty($titulo)) {
        $error = "❌ El título es obligatorio";
    } elseif (empty($categoria)) {
        $error = "❌ Debes seleccionar una categoría";
    } elseif (!in_array($categoria, $categorias_validas)) {
        $error = "❌ Categoría no válida";
    } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $error = "❌ Debes seleccionar un archivo";
    } else {
        $archivo = $_FILES['archivo'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $nombre_original = $archivo['name'];
        $tamano = $archivo['size'];
        
        // Validar extensión
        if (!array_key_exists($extension, $extensiones_permitidas)) {
            $error = "❌ Formato no permitido. Usa: PDF, Word (.doc, .docx) o Excel (.xls, .xlsx)";
        } 
        // Validar tamaño
        elseif ($tamano > $max_size) {
            $error = "❌ El archivo no puede superar los 10MB";
        } 
        else {
            // ============================================
            // GUARDAR ARCHIVO
            // ============================================
            $carpeta = '../../uploads/documentos/documentos_sig/';
            if (!file_exists($carpeta)) {
                mkdir($carpeta, 0777, true);
            }
            
            // Generar nombre único con la extensión original
            $nombre_archivo = 'sig_' . time() . '_' . uniqid() . '.' . $extension;
            $ruta_destino = $carpeta . $nombre_archivo;
            $ruta_db = 'uploads/documentos/documentos_sig/' . $nombre_archivo;
            
            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO documentos_sig 
                        (titulo, categoria, descripcion, archivo_url, tipo_archivo, nombre_original, creado_por, activo, creado_el) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmt->execute([
                        $titulo,
                        $categoria,
                        $descripcion,
                        $ruta_db,
                        $extensiones_permitidas[$extension],
                        $nombre_original,
                        $usuario_id
                    ]);
                    
                    $mensaje = "✅ Documento subido correctamente";
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