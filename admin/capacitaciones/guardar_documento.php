<?php
// admin/capacitaciones/guardar_documento.php - Guardar documento de capacitación
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
        WHERE pu.usuario_id = ? AND m.nombre = 'capacitaciones' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para guardar documentos de capacitación";
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
    // ✅ CORREGIDO: usar categoria_id en lugar de area_id
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo_archivo = $_POST['tipo_archivo'] ?? 'pdf';
    $orden = intval($_POST['orden'] ?? 0);
    $url = trim($_POST['url'] ?? '');
    
    // Validaciones
    if (empty($titulo) || $categoria_id == 0) {
        $error = "❌ El título y la categoría son obligatorios";
    } else {
        try {
            $ruta_db = '';
            
            // Procesar según tipo de archivo
            $tipos_con_archivo = ['pdf', 'word', 'excel', 'imagen'];
            $tipos_con_url = ['video', 'link'];
            
            if (in_array($tipo_archivo, $tipos_con_archivo)) {
                // Procesar archivo subido
                if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                    $archivo = $_FILES['archivo'];
                    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                    
                    // Validar extensión según tipo
                    $extensiones_permitidas = [
                        'pdf' => ['pdf'],
                        'word' => ['doc', 'docx'],
                        'excel' => ['xls', 'xlsx'],
                        'imagen' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
                    ];
                    
                    if (!in_array($extension, $extensiones_permitidas[$tipo_archivo] ?? ['pdf'])) {
                        $error = "❌ Extensión no permitida para el tipo seleccionado";
                    } elseif ($archivo['size'] > 6 * 1024 * 1024) {
                        $error = "❌ El archivo no puede superar los 6MB";
                    } else {
                        // Crear carpeta si no existe
                        $carpeta = '../../uploads/documentos/capacitaciones/';
                        if (!file_exists($carpeta)) {
                            mkdir($carpeta, 0777, true);
                        }
                        
                        $nombre_archivo = time() . '_' . uniqid() . '.' . $extension;
                        $ruta_destino = $carpeta . $nombre_archivo;
                        $ruta_db = 'uploads/documentos/capacitaciones/' . $nombre_archivo;
                        
                        if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                            // Guardar en BD con categoria_id
                            $stmt = $pdo->prepare("INSERT INTO documentos_capacitaciones (categoria_id, titulo, descripcion, tipo_archivo, url, orden, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$categoria_id, $titulo, $descripcion, $tipo_archivo, $ruta_db, $orden, $usuario_id]);
                            $mensaje = "✅ Documento guardado correctamente";
                        } else {
                            $error = "❌ Error al subir el archivo";
                        }
                    }
                } else {
                    $error = "❌ Debes seleccionar un archivo";
                }
            } elseif (in_array($tipo_archivo, $tipos_con_url)) {
                // Procesar URL
                if (empty($url)) {
                    $error = "❌ Debes ingresar una URL";
                } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $error = "❌ La URL no es válida";
                } else {
                    // Guardar en BD con categoria_id
                    $stmt = $pdo->prepare("INSERT INTO documentos_capacitaciones (categoria_id, titulo, descripcion, tipo_archivo, url, orden, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$categoria_id, $titulo, $descripcion, $tipo_archivo, $url, $orden, $usuario_id]);
                    $mensaje = "✅ Documento guardado correctamente";
                }
            } else {
                $error = "❌ Tipo de archivo no válido";
            }
            
        } catch (PDOException $e) {
            $error = "❌ Error al guardar: " . $e->getMessage();
        }
    }
}

// Redirigir con mensaje
if ($mensaje) {
    // Redirigir a la misma categoría si existe
    if ($categoria_id > 0) {
        header("Location: documentos.php?categoria_id=" . $categoria_id . "&mensaje=" . urlencode($mensaje));
    } else {
        header("Location: documentos.php?mensaje=" . urlencode($mensaje));
    }
} else {
    // Redirigir con error
    if ($categoria_id > 0) {
        header("Location: documentos.php?categoria_id=" . $categoria_id . "&error=" . urlencode($error));
    } else {
        header("Location: documentos.php?error=" . urlencode($error));
    }
}
exit();
?>