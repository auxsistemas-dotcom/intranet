<?php
// admin/documentos_sig/guardar.php - Guardar documento SIG
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// ✅ VERIFICAR SESIÓN
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 3;
$usuario_id  = $_SESSION['usuario_id'];

// ============================================
// ✅ CONTROL DE ACCESO
// ============================================
if ($rol_usuario != 1 && $rol_usuario != 2) {
    $_SESSION['error'] = "❌ No tienes permiso para gestionar documentos SIG";
    header('Location: ../../index.php');
    exit();
}

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

$max_size = 10 * 1024 * 1024; // 10 MB

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

$error   = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo      = trim($_POST['titulo']      ?? '');
    $categoria   = trim($_POST['categoria']   ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    // ============================================
    // VALIDACIONES
    // ============================================
    if (empty($titulo)) {
        $error = "❌ El título es obligatorio";
    } elseif (empty($categoria)) {
        $error = "❌ Debes seleccionar una categoría";
    } elseif (!in_array($categoria, $categorias_validas, true)) {
        $error = "❌ Categoría no válida";
    } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $error = "❌ Debes seleccionar un archivo";
    } else {
        $archivo         = $_FILES['archivo'];
        $extension       = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $nombre_original = $archivo['name'];
        $tamano          = $archivo['size'];

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
            // GUARDAR ARCHIVO EN DISCO
            // ============================================
            $carpeta = '../../uploads/documentos/documentos_sig/';
            if (!file_exists($carpeta)) {
                mkdir($carpeta, 0777, true);
            }

            $nombre_archivo = 'sig_' . time() . '_' . uniqid() . '.' . $extension;
            $ruta_destino   = $carpeta . $nombre_archivo;
            $ruta_db        = 'uploads/documentos/documentos_sig/' . $nombre_archivo;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                try {
                    // ============================================
                    // INSERT con columnas reales de la tabla:
                    // titulo, categoria, descripcion, archivo_url,
                    // orden, activo, creado_por, fecha_creacion
                    // ============================================
                    $stmt = $pdo->prepare("
                        INSERT INTO documentos_sig 
                            (titulo, categoria, descripcion, archivo_url, creado_por, activo, fecha_creacion) 
                        VALUES 
                            (?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmt->execute([
                        $titulo,
                        $categoria,
                        $descripcion,
                        $ruta_db,
                        $usuario_id
                    ]);

                    $mensaje = "✅ Documento subido correctamente";
                } catch (PDOException $e) {
                    // Si falla el INSERT, borrar el archivo ya subido
                    if (file_exists($ruta_destino)) {
                        unlink($ruta_destino);
                    }
                    $error = "❌ Error al guardar en la base de datos: " . $e->getMessage();
                    error_log("INSERT documentos_sig ERROR: " . $e->getMessage());
                }
            } else {
                $error = "❌ Error al subir el archivo al servidor";
            }
        }
    }
}

// ============================================
// REDIRECCIONAR CON MENSAJE FLASH
// ============================================
if ($mensaje) {
    $_SESSION['mensaje'] = $mensaje;
    header("Location: index.php");
} else {
    $_SESSION['error'] = $error ?: "❌ No se pudo guardar el documento";
    header("Location: index.php");
}
exit();
?>