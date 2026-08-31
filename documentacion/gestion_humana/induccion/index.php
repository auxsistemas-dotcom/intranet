<?php
// documentacion/gestion_humana/introduccion/index.php - Módulo de Inducción con bloqueo y Quiz
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// Requiere login
requiereLogin();

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];
$rol_usuario = $usuario['rol'];

// ✅ VERIFICAR ACCESO BÁSICO - Permitir a todos los usuarios autenticados
if ($rol_usuario == 3) {
    // Usuario normal - tiene acceso
} elseif ($rol_usuario == 1 || $rol_usuario == 2) {
    // Admin o supervisor - también tienen acceso
} else {
    $_SESSION['error_permiso'] = "No tienes permiso para acceder a Inducción.";
    header('Location: ../../../index.php');
    exit();
}

// ✅ FUNCIÓN PARA VERIFICAR SI UNA CARPETA ESTÁ COMPLETADA (documentos + quiz)
function carpetaCompletada($usuario_id, $carpeta_id) {
    global $pdo;
    
    // Contar documentos vistos
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN p.visto = 1 THEN 1 ELSE 0 END) as vistos
        FROM documentos_gestion_humana d
        LEFT JOIN progreso_gestion_humana p ON p.documento_id = d.id AND p.usuario_id = ?
        WHERE d.categoria_id = ? AND d.activo = 1
    ");
    $stmt->execute([$usuario_id, $carpeta_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_docs = intval($result['total'] ?? 0);
    $vistos = intval($result['vistos'] ?? 0);
    
    // Verificar si el quiz está completado
    $stmt = $pdo->prepare("
        SELECT completado FROM quiz_gestion_humana 
        WHERE usuario_id = ? AND categoria_id = ? AND completado = 1
    ");
    $stmt->execute([$usuario_id, $carpeta_id]);
    $quiz_completado = $stmt->fetch() ? true : false;
    
    return $total_docs > 0 && $vistos == $total_docs && $quiz_completado;
}

// ✅ FUNCIÓN PARA VERIFICAR SI EL BOTÓN QUIZ ESTÁ DESBLOQUEADO (documentos vistos)
function quizDesbloqueado($usuario_id, $carpeta_id) {
    global $pdo;
    
    // Contar documentos vistos
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN p.visto = 1 THEN 1 ELSE 0 END) as vistos
        FROM documentos_gestion_humana d
        LEFT JOIN progreso_gestion_humana p ON p.documento_id = d.id AND p.usuario_id = ?
        WHERE d.categoria_id = ? AND d.activo = 1
    ");
    $stmt->execute([$usuario_id, $carpeta_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_docs = intval($result['total'] ?? 0);
    $vistos = intval($result['vistos'] ?? 0);
    
    // Verificar si el quiz ya está completado
    $stmt = $pdo->prepare("
        SELECT completado FROM quiz_gestion_humana 
        WHERE usuario_id = ? AND categoria_id = ? AND completado = 1
    ");
    $stmt->execute([$usuario_id, $carpeta_id]);
    $quiz_completado = $stmt->fetch() ? true : false;
    
    // Verificar si la carpeta tiene quiz_url configurado
    $stmt = $pdo->prepare("SELECT quiz_url FROM categorias_gestion_humana WHERE id = ?");
    $stmt->execute([$carpeta_id]);
    $carpeta = $stmt->fetch(PDO::FETCH_ASSOC);
    $tiene_quiz_url = !empty($carpeta['quiz_url']);
    
    // ✅ Botón desbloqueado si: documentos vistos + NO completado + tiene URL
    return $total_docs > 0 && $vistos == $total_docs && !$quiz_completado && $tiene_quiz_url;
}

// ✅ FUNCIÓN PARA VERIFICAR SI UNA CARPETA ESTÁ DESBLOQUEADA
function carpetaDesbloqueada($usuario_id, $carpetas, $index, $rol_usuario) {
    if ($rol_usuario == 1) return true;
    if ($index == 0) return true;
    
    $anterior = $carpetas[$index - 1] ?? null;
    if ($anterior) {
        return carpetaCompletada($usuario_id, $anterior['id']);
    }
    return false;
}

// ============================================
// FUNCIONES PARA CERTIFICADO
// ============================================

// Función para verificar si la inducción está completada al 100%
function induccionCompletadaCert($usuario_id) {
    global $pdo;
    
    // Obtener todas las áreas
    $stmt = $pdo->prepare("SELECT id FROM categorias_gestion_humana WHERE tipo = 'area' AND activo = 1 ORDER BY orden ASC");
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($areas)) {
        return false;
    }
    
    // Verificar cada área
    foreach ($areas as $area_id) {
        // Obtener carpetas del área
        $stmt = $pdo->prepare("SELECT id FROM categorias_gestion_humana WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1");
        $stmt->execute([$area_id]);
        $carpetas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($carpetas as $carpeta_id) {
            // Verificar si la carpeta está completada usando la función existente
            if (!carpetaCompletada($usuario_id, $carpeta_id)) {
                return false;
            }
        }
    }
    
    return true;
}

// Función para obtener el progreso de la inducción
function obtenerProgresoCert($usuario_id) {
    global $pdo;
    
    $total_areas = 0;
    $areas_completadas = 0;
    $detalle = [];
    
    // Obtener todas las áreas
    $stmt = $pdo->prepare("SELECT id, nombre FROM categorias_gestion_humana WHERE tipo = 'area' AND activo = 1 ORDER BY orden ASC");
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($areas as $area) {
        $total_areas++;
        $area_completada = true;
        $carpetas_detalle = [];
        
        // Obtener carpetas del área
        $stmt = $pdo->prepare("SELECT id, nombre FROM categorias_gestion_humana WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1 ORDER BY orden ASC");
        $stmt->execute([$area['id']]);
        $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($carpetas as $carpeta) {
            $completada = carpetaCompletada($usuario_id, $carpeta['id']);
            $carpetas_detalle[] = [
                'nombre' => $carpeta['nombre'],
                'completada' => $completada
            ];
            
            if (!$completada) {
                $area_completada = false;
            }
        }
        
        if ($area_completada) {
            $areas_completadas++;
        }
        
        $detalle[] = [
            'nombre' => $area['nombre'],
            'completada' => $area_completada,
            'carpetas' => $carpetas_detalle
        ];
    }
    
    $porcentaje = $total_areas > 0 ? round(($areas_completadas / $total_areas) * 100) : 0;
    
    return [
        'total_areas' => $total_areas,
        'areas_completadas' => $areas_completadas,
        'porcentaje' => $porcentaje,
        'completada' => ($total_areas > 0 && $areas_completadas == $total_areas),
        'detalle' => $detalle
    ];
}

// ✅ OBTENER ÁREAS DE INDUCCIÓN
$stmt = $pdo->prepare("
    SELECT * FROM categorias_gestion_humana 
    WHERE tipo = 'area' AND activo = 1 
    ORDER BY orden ASC
");
$stmt->execute();
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Para cada área, obtener sus carpetas y documentos
foreach ($areas as $key => $area) {
    $stmt = $pdo->prepare("
        SELECT * FROM categorias_gestion_humana 
        WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1 
        ORDER BY orden ASC
    ");
    $stmt->execute([$area['id']]);
    $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Verificar si el área está desbloqueada
    if ($key == 0) {
        $area_desbloqueada = true;
    } else {
        $area_anterior = $areas[$key - 1] ?? null;
        if ($area_anterior) {
            // Verificar si todas las carpetas del área anterior están completadas
            $area_desbloqueada = true;
            $stmt = $pdo->prepare("
                SELECT id FROM categorias_gestion_humana 
                WHERE padre_id = ? AND tipo = 'carpeta' AND activo = 1
            ");
            $stmt->execute([$area_anterior['id']]);
            $carpetas_anterior = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($carpetas_anterior as $carpeta_id) {
                if (!carpetaCompletada($usuario_id, $carpeta_id)) {
                    $area_desbloqueada = false;
                    break;
                }
            }
        } else {
            $area_desbloqueada = true;
        }
    }
    
    foreach ($carpetas as $subkey => $carpeta) {
        $stmt = $pdo->prepare("
            SELECT d.*, 
            (SELECT COUNT(*) FROM progreso_gestion_humana WHERE usuario_id = ? AND documento_id = d.id) as visto
            FROM documentos_gestion_humana d
            WHERE d.categoria_id = ? AND d.activo = 1 
            ORDER BY d.orden ASC, d.titulo ASC
        ");
        $stmt->execute([$usuario_id, $carpeta['id']]);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_docs = count($documentos);
        $vistos = 0;
        foreach ($documentos as $doc) {
            if ($doc['visto'] > 0) $vistos++;
        }
        $progreso_docs = $total_docs > 0 ? round(($vistos / $total_docs) * 100) : 0;
        
        // Verificar si el quiz está completado
        $stmt_quiz = $pdo->prepare("
            SELECT completado FROM quiz_gestion_humana 
            WHERE usuario_id = ? AND categoria_id = ? AND completado = 1
        ");
        $stmt_quiz->execute([$usuario_id, $carpeta['id']]);
        $quiz_completado = $stmt_quiz->fetch() ? true : false;
        
        // Verificar si la carpeta tiene quiz_url
        $tiene_quiz_url = !empty($carpeta['quiz_url']);
        
        // ✅ ESTADOS:
        // - Botón desbloqueado (documentos al 100% + quiz NO completado + tiene URL)
        $boton_quiz_desbloqueado = ($progreso_docs == 100 && !$quiz_completado && $tiene_quiz_url);
        
        // - Carpeta completada (documentos al 100% + quiz completado)
        $completado = ($progreso_docs == 100 && $quiz_completado);
        
        // - Carpeta desbloqueada (carpeta anterior completada)
        $desbloqueado = carpetaDesbloqueada($usuario_id, $carpetas, $subkey, $rol_usuario);
        
        $carpetas[$subkey]['documentos'] = $documentos;
        $carpetas[$subkey]['progreso_docs'] = $progreso_docs;
        $carpetas[$subkey]['progreso'] = $progreso_docs;
        $carpetas[$subkey]['total_docs'] = $total_docs;
        $carpetas[$subkey]['vistos'] = $vistos;
        $carpetas[$subkey]['completado'] = $completado;
        $carpetas[$subkey]['boton_quiz_desbloqueado'] = $boton_quiz_desbloqueado;
        $carpetas[$subkey]['quiz_completado'] = $quiz_completado;
        $carpetas[$subkey]['tiene_quiz_url'] = $tiene_quiz_url;
        $carpetas[$subkey]['quiz_url'] = $carpeta['quiz_url'] ?? null;
        $carpetas[$subkey]['desbloqueado'] = $desbloqueado;
    }
    
    $area['bloqueada'] = !$area_desbloqueada;
    $area['desbloqueada'] = $area_desbloqueada;
    $areas[$key] = $area;
    $areas[$key]['carpetas'] = $carpetas;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inducción | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../css/styles.css">
    <style>
        /* ===== ESTILOS GENERALES ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #2c3e50; }
        
        .induccion-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* ===== HEADER ===== */
        .header {
            background: #12232b;
            margin-bottom: 30px;
            border-bottom: 3px solid #173742;
        }
        .header .container { 
            display: flex;
            justify-content: space-between;
            align-items: center; 
        }
        .logo-container { display: flex; align-items: center; gap: 15px; }
        .logo-icon { background: #445960; width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { font-size: 24px; color: white; }
        .logo-text { color: white; font-size: 24px; font-weight: 700; margin: 0; }
        .logo-subtitle { color: #8aa8b8; font-size: 12px; margin: 0; }
        
        .header-actions { display: flex; align-items: center; gap: 20px; }
        .welcome-message { color: white; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .welcome-message i { color: #445960; }
        .welcome-message strong { color: #ffc107; }
        
        /* ===== HERO ===== */
        .hero {
            background: linear-gradient(135deg, #173742, #12232b);
            border-radius: 24px;
            padding: 50px 40px;
            margin-bottom: 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255,193,7,0.05);
            border-radius: 50%;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,193,7,0.03);
            border-radius: 50%;
        }
        .hero-content { position: relative; z-index: 1; }
        .hero h1 {
            font-size: 38px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .hero h1 i { color: #ffc107; margin-right: 15px; }
        .hero p { font-size: 18px; opacity: 0.9; max-width: 600px; }
        .hero .user-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 8px 20px;
            border-radius: 50px;
            margin-top: 15px;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        .hero .user-badge i { color: #ffc107; margin-right: 8px; }
        
        /* ===== ÁREAS ===== */
        .area-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
            transition: all 0.3s;
        }
        .area-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        
        .area-header {
            background: linear-gradient(135deg, #173742, #1a4a5a);
            color: white;
            padding: 18px 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
            transition: all 0.3s;
        }
        .area-header:hover { background: linear-gradient(135deg, #1a4a5a, #173742); }
        .area-header .area-title {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .area-header .area-title .icon-area {
            width: 42px;
            height: 42px;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .area-header .area-title .icon-area i { font-size: 20px; color: #ffc107; }
        .area-header .area-title h2 { font-size: 20px; margin: 0; }
        .area-header .area-title p { margin: 0; opacity: 0.8; font-size: 13px; font-weight: 300; }
        .area-header .area-toggle {
            font-size: 20px;
            color: #ffc107;
            transition: transform 0.3s;
            background: rgba(255,255,255,0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .area-header .area-toggle.rotated { transform: rotate(0deg); }
        .area-content { display: none; padding: 20px 25px; }
        .area-content.visible { display: block; }
        
        /* ===== ÁREA BLOQUEADA ===== */
        .area-bloqueada { opacity: 0.85; }
        .area-bloqueada .area-header {
            background: linear-gradient(135deg, #6c7a89, #7f8c8d);
            cursor: not-allowed;
        }
        .area-bloqueada .area-header:hover {
            background: linear-gradient(135deg, #6c7a89, #7f8c8d);
        }
        .area-bloqueada .icon-area i { color: #95a5a6 !important; }
        .badge-area-bloqueada {
            background: #e74c3c;
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
            display: inline-block;
        }
        .area-content-bloqueada {
            padding: 30px 20px;
            text-align: center;
            background: #f9f9f9;
        }
        .area-content-bloqueada .mensaje-bloqueo { color: #7f8c8d; }
        .area-content-bloqueada .mensaje-bloqueo i {
            font-size: 48px;
            color: #d3d3d3;
            display: block;
            margin-bottom: 15px;
        }
        .area-content-bloqueada .mensaje-bloqueo p { font-size: 16px; }
        
        /* ===== CARPETAS ===== */
        .carpeta-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            border-left: 4px solid #ffc107;
            position: relative;
        }
        .carpeta-container:last-child { margin-bottom: 0; }
        .carpeta-container:hover { background: #f0f2f5; }
        
        .carpeta-container.bloqueado {
            background: #f5f5f5;
            border-left-color: #e74c3c;
            opacity: 0.8;
            cursor: not-allowed;
        }
        .carpeta-container.bloqueado .carpeta-header { cursor: not-allowed; }
        .carpeta-container.bloqueado .carpeta-header h3 { color: #95a5a6; }
        .carpeta-container.bloqueado .carpeta-header h3 i { color: #95a5a6; }
        .carpeta-container.bloqueado .carpeta-header p { color: #bdc3c7; }
        .carpeta-container.bloqueado .bloqueo-overlay { display: flex; }
        
        .bloqueo-overlay {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: #fadbd8;
            border-radius: 8px;
            color: #922b21;
            font-size: 13px;
            font-weight: 500;
            margin-top: 10px;
        }
        .bloqueo-overlay i { color: #e74c3c; font-size: 16px; }
        
        .carpeta-header {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            user-select: none;
            flex-wrap: wrap;
        }
        .carpeta-header .toggle-icon {
            font-size: 14px;
            color: #173742;
            transition: transform 0.3s;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f0fe;
        }
        .carpeta-header .toggle-icon:hover { background: #d4e6f1; }
        .carpeta-header h3 {
            font-size: 17px;
            color: #173742;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        .carpeta-header h3 i { color: #f39c12; }
        .carpeta-header p {
            font-size: 13px;
            color: #7f8c8d;
            margin: 0;
        }
        
        .badge-completado {
            background: #27ae60;
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-left: 8px;
        }
        .badge-bloqueado {
            background: #e74c3c;
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-left: 8px;
        }
        .badge-desbloqueado {
            background: #3498db;
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-left: 8px;
        }
        
        .progress-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 12px;
            padding: 6px 0;
        }
        .progress-wrapper .label-progreso {
            font-size: 13px;
            color: #7f8c8d;
            min-width: 85px;
        }
        .progress-bar-container {
            flex: 1;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
            background: linear-gradient(90deg, #173742, #445960);
        }
        .progress-bar.completed { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .progress-text {
            font-size: 13px;
            font-weight: 600;
            color: #173742;
            min-width: 45px;
            text-align: right;
        }
        .progress-text.completed { color: #27ae60; }
        
        /* ===== DOCUMENTOS ===== */
        .documentos-list {
            display: none;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
            padding-left: 30px;
        }
        .documentos-list.visible { display: flex; }
        
        .documento-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 18px;
            background: white;
            border-radius: 10px;
            transition: all 0.3s;
            flex-wrap: wrap;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .documento-item:hover {
            background: #f0f4f8;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .documento-item.visto {
            background: #d4edda;
            border-left: 4px solid #27ae60;
        }
        .documento-item.visto:hover { background: #c3e6cb; }
        .documento-item.bloqueado-item {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .documento-item.bloqueado-item:hover {
            transform: none;
            background: #f9f9f9;
        }
        
        .documento-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .documento-icon.pdf { background: #fee2e2; color: #dc2626; }
        .documento-icon.video { background: #e0f2fe; color: #0284c7; }
        .documento-icon.word { background: #d4e6f1; color: #1a4a7a; }
        .documento-icon.excel { background: #d5f5e3; color: #1a7a3a; }
        .documento-icon.link { background: #e8d5f5; color: #6c3483; }
        .documento-icon.imagen { background: #fdebd0; color: #a04000; }
        
        .documento-info { flex: 1; min-width: 150px; }
        .documento-info h4 { font-size: 14px; margin-bottom: 3px; color: #2c3e50; }
        .documento-info p { font-size: 12px; color: #7f8c8d; margin: 0; }
        .documento-check { color: #27ae60; font-size: 18px; }
        
        .btn-documento {
            padding: 6px 18px;
            background: #173742;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-documento:hover {
            background: #445960;
            transform: translateY(-2px);
        }
        .btn-documento.bloqueado-btn {
            background: #95a5a6;
            cursor: not-allowed;
        }
        .btn-documento.bloqueado-btn:hover {
            background: #95a5a6;
            transform: none;
        }
        
        /* ===== BOTÓN QUIZ ===== */
        .btn-quiz {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(243,156,18,0.3);
            border: none;
            cursor: pointer;
        }
        .btn-quiz:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(243,156,18,0.4);
            color: white;
        }
        .btn-quiz i { margin-right: 8px; }
        
        .badge-quiz-completado {
            background: #27ae60;
            color: white;
            padding: 4px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .info-quiz-pendiente {
            margin-top: 10px;
            text-align: center;
            font-size: 13px;
            color: #7f8c8d;
        }
        .info-quiz-pendiente i { margin-right: 5px; }
        
        /* ===== ESTADOS VACÍOS ===== */
        .sin-carpetas {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .sin-carpetas i { font-size: 48px; color: #d3d3d3; margin-bottom: 15px; }
        .sin-carpetas p { font-size: 14px; }
        
        .sin-documentos {
            text-align: center;
            padding: 15px;
            color: #7f8c8d;
            font-style: italic;
            font-size: 13px;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            background: linear-gradient(135deg, #12232b 0%, #173742 100%);
            color: white;
            padding: 20px 0;
            margin-top: 40px;
        }
        .footer .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .footer-logo { display: flex; align-items: center; gap: 10px; }
        .footer-logo i { color: #ffc107; font-size: 24px; }
        .footer-logo span { font-weight: 700; font-size: 18px; }
        .footer-copyright p { font-size: 14px; opacity: 0.7; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .header-actions { flex-wrap: wrap; justify-content: center; }
            .welcome-message { flex-wrap: wrap; justify-content: center; }
            .hero { padding: 30px 20px; }
            .hero h1 { font-size: 28px; }
            .hero p { font-size: 15px; }
            .area-header { flex-direction: column; text-align: center; }
            .area-header .area-title { justify-content: center; }
            .carpeta-header { justify-content: center; text-align: center; }
            .documento-item { justify-content: center; text-align: center; }
            .documento-info { text-align: center; }
            .progress-wrapper { flex-wrap: wrap; justify-content: center; }
            .documentos-list { padding-left: 0; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo-container">
            <div>
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <p class="logo-subtitle" style="font-size: 14px;">| Inducción</p>
            </div>
        </div>
        <div class="header-actions">
            <div class="welcome-message">
                <i class="fas fa-user-check"></i>
                <span>Bienvenido, <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong></span>
                <a href="../../../logout.php" style="
                    color: #445960;
                    margin-left: 10px;
                    text-decoration: none;
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    padding: 5px 12px;
                    border-radius: 8px;
                    font-weight: 500;
                "
                onmouseover="this.style.background='#ffc107'; this.style.color='#12232b'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(255,193,7,0.3)';"
                onmouseout="this.style.background='transparent'; this.style.color='#445960'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </div>
</header>

<!-- ===== BOTÓN VOLVER A GESTIÓN HUMANA ===== -->
<div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
    <a href="../index.php" style="
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #445960;
        color: white;
        padding: 12px 22px;
        text-decoration: none;
        border-radius: 8px;
        margin-top: 15px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
        font-size: 14px;
    "
    onmouseover="this.style.background='#ffc107'; this.style.color='#12232b'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(255,193,7,0.3)';"
    onmouseout="this.style.background='#445960'; this.style.color='white'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
        <i class="fas fa-arrow-left"></i> Volver a Gestión Humana
    </a>
</div>

<!-- ===== CONTENIDO PRINCIPAL ===== -->
<main>
    <div class="induccion-container">
        <!-- HERO -->
        <div class="hero">
            <div class="hero-content">
                <h1><i class="fas fa-user-graduate"></i> Inducción</h1>
                <p>Completa tu proceso de inducción. Cada módulo se desbloqueará al completar el anterior.</p>
            </div>
        </div>
        
        <?php if (empty($areas)): ?>
            <div class="sin-carpetas">
                <i class="fas fa-folder-open"></i>
                <p>No hay módulos de inducción disponibles.</p>
                <p style="font-size: 13px;">Contacta al administrador para más información.</p>
            </div>
        <?php else: ?>
            <?php foreach ($areas as $area): ?>
                <!-- ÁREA -->
                <div class="area-card <?php echo $area['bloqueada'] ? 'area-bloqueada' : ''; ?>">
                    <div class="area-header" onclick="<?php echo $area['bloqueada'] ? '' : "toggleArea({$area['id']})"; ?>">
                        <div class="area-title">
                            <div class="icon-area">
                                <i class="fas <?php echo $area['bloqueada'] ? 'fa-lock' : 'fa-layer-group'; ?>"></i>
                            </div>
                            <div>
                                <h2>
                                    <?php echo htmlspecialchars($area['nombre']); ?>
                                    <?php if ($area['bloqueada']): ?>
                                        <span class="badge-area-bloqueada"><i class="fas fa-lock"></i> Bloqueado</span>
                                    <?php endif; ?>
                                </h2>
                                <p><?php echo htmlspecialchars($area['descripcion']); ?></p>
                            </div>
                        </div>
                        <?php if (!$area['bloqueada']): ?>
                            <span class="area-toggle" id="area_icon_<?php echo $area['id']; ?>">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        <?php else: ?>
                            <span class="area-toggle" style="opacity: 0.5; cursor: not-allowed;">
                                <i class="fas fa-lock"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$area['bloqueada']): ?>
                        <div class="area-content" id="area_content_<?php echo $area['id']; ?>">
                            <?php if (empty($area['carpetas'])): ?>
                                <div class="sin-carpetas">
                                    <i class="fas fa-folder" style="font-size: 32px;"></i>
                                    <p>No hay módulos disponibles en esta área.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($area['carpetas'] as $index => $carpeta): ?>
                                    <!-- CARPETA con data attributes -->
                                    <div class="carpeta-container <?php echo !$carpeta['desbloqueado'] ? 'bloqueado' : ''; ?>" 
                                         data-categoria-id="<?php echo $carpeta['id']; ?>"
                                         data-tiene-quiz="<?php echo $carpeta['tiene_quiz_url'] ? 'true' : 'false'; ?>">
                                        
                                        <div class="carpeta-header" onclick="<?php echo $carpeta['desbloqueado'] ? "toggleCarpeta({$area['id']}, {$index})" : ''; ?>">
                                            <span class="toggle-icon" id="icon_<?php echo $area['id']; ?>_<?php echo $index; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </span>
                                            <h3>
                                                <i class="fas <?php echo $carpeta['desbloqueado'] ? 'fa-folder-open' : 'fa-lock'; ?>"></i>
                                                <?php echo htmlspecialchars($carpeta['nombre']); ?>
                                                <?php if ($carpeta['completado']): ?>
                                                    <span class="badge-completado"><i class="fas fa-check-circle"></i> Completado</span>
                                                <?php elseif ($carpeta['desbloqueado'] && !$carpeta['completado']): ?>
                                                    <span class="badge-desbloqueado"><i class="fas fa-unlock"></i> Disponible</span>
                                                <?php elseif (!$carpeta['desbloqueado']): ?>
                                                    <span class="badge-bloqueado"><i class="fas fa-lock"></i> Bloqueado</span>
                                                <?php endif; ?>
                                            </h3>
                                            <p><?php echo htmlspecialchars($carpeta['descripcion']); ?></p>
                                        </div>
                                        
                                        <?php if ($carpeta['desbloqueado']): ?>
                                            <!-- ===== PROGRESO ===== -->
                                            <div class="progress-wrapper">
                                                <span class="label-progreso">Progreso: <?php echo $carpeta['vistos'] . '/' . $carpeta['total_docs']; ?></span>
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar <?php echo $carpeta['progreso'] == 100 ? 'completed' : ''; ?>" 
                                                        style="width: <?php echo $carpeta['progreso']; ?>%;">
                                                    </div>
                                                </div>
                                                <span class="progress-text <?php echo $carpeta['progreso'] == 100 ? 'completed' : ''; ?>">
                                                    <?php echo $carpeta['progreso']; ?>%
                                                </span>
                                            </div>
                                            
                                            <!-- ===== DOCUMENTOS ===== -->
                                            <div class="documentos-list" id="docs_<?php echo $area['id']; ?>_<?php echo $index; ?>">
                                                <?php if (empty($carpeta['documentos'])): ?>
                                                    <div class="sin-documentos">
                                                        <i class="fas fa-file-alt"></i> No hay documentos en esta carpeta.
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($carpeta['documentos'] as $doc): ?>
                                                        <div class="documento-item <?php echo $doc['visto'] > 0 ? 'visto' : ''; ?>" data-doc-id="<?php echo $doc['id']; ?>">
                                                            <div class="documento-icon <?php echo $doc['tipo_archivo']; ?>">
                                                                <i class="fas <?php 
                                                                    echo $doc['tipo_archivo'] === 'pdf' ? 'fa-file-pdf' : 
                                                                         ($doc['tipo_archivo'] === 'video' ? 'fa-video' :
                                                                         ($doc['tipo_archivo'] === 'word' ? 'fa-file-word' :
                                                                         ($doc['tipo_archivo'] === 'excel' ? 'fa-file-excel' :
                                                                         ($doc['tipo_archivo'] === 'link' ? 'fa-link' : 'fa-file-image')))); 
                                                                ?>"></i>
                                                            </div>
                                                            <div class="documento-info">
                                                                <h4><?php echo htmlspecialchars($doc['titulo']); ?></h4>
                                                                <p><?php echo htmlspecialchars($doc['descripcion']); ?></p>
                                                            </div>
                                                            <?php if ($doc['visto'] > 0): ?>
                                                                <span class="documento-check"><i class="fas fa-check-circle"></i></span>
                                                            <?php endif; ?>
                                                            <a href="javascript:void(0)" onclick="abrirYMarcar('<?php echo $doc['url']; ?>', <?php echo $doc['id']; ?>)" class="btn-documento">
                                                                <i class="fas fa-external-link-alt"></i> Ver
                                                            </a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- ===== SECCIÓN QUIZ (se actualizará dinámicamente) ===== -->
                                            <div class="quiz-section" style="margin-top: 10px;">
                                                <?php if ($carpeta['tiene_quiz_url']): ?>
                                                    <?php if ($carpeta['boton_quiz_desbloqueado']): ?>
                                                        <!-- ✅ QUIZ DESBLOQUEADO - Marca automáticamente al hacer clic -->
                                                        <div style="text-align: center; padding-top: 15px; border-top: 2px dashed #e0e0e0;">
                                                            <button type="button" 
                                                                onclick="marcarYAbirQuiz('<?php echo htmlspecialchars($carpeta['quiz_url'], ENT_QUOTES); ?>', <?php echo $carpeta['id']; ?>)" 
                                                                class="btn-quiz" style="
                                                                    background: linear-gradient(135deg, #27ae60, #2ecc71);
                                                                    color: white;
                                                                    padding: 10px 25px;
                                                                    border-radius: 8px;
                                                                    text-decoration: none;
                                                                    display: inline-block;
                                                                    font-weight: 600;
                                                                    font-size: 14px;
                                                                    transition: all 0.3s ease;
                                                                    box-shadow: 0 4px 15px rgba(39,174,96,0.3);
                                                                    cursor: pointer;
                                                                    border: none;
                                                                "
                                                                onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 25px rgba(39,174,96,0.4)';"
                                                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(39,174,96,0.3)';">
                                                                <i class="fas fa-external-link-alt"></i> Realizar Quiz
                                                            </button>
                                                            <div style="margin-top: 5px; font-size: 12px; color: #27ae60;">
                                                                <i class="fas fa-check-circle"></i> Todos los documentos completados
                                                            </div>
                                                        </div>
                                                        
                                                    <?php elseif ($carpeta['quiz_completado']): ?>
                                                        <!-- ✅ QUIZ COMPLETADO -->
                                                        <div style="text-align: center;">
                                                            <span style="background: #27ae60; color: white; padding: 6px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; display: inline-block;">
                                                                <i class="fas fa-check-circle"></i> Quiz completado ✅
                                                            </span>
                                                        </div>
                                                        
                                                    <?php else: ?>
                                                        <!-- ⏳ QUIZ BLOQUEADO (documentos pendientes) -->
                                                        <div style="text-align: center;">
                                                            <button type="button" class="btn-quiz" style="
                                                                    background: #95a5a6;
                                                                    color: white;
                                                                    padding: 10px 25px;
                                                                    border-radius: 8px;
                                                                    display: inline-block;
                                                                    font-weight: 600;
                                                                    font-size: 14px;
                                                                    border: none;
                                                                    cursor: not-allowed;
                                                                    opacity: 0.6;
                                                                ">
                                                                <i class="fas fa-lock"></i> Quiz bloqueado
                                                            </button>
                                                            <div style="margin-top: 5px; font-size: 12px; color: #7f8c8d;">
                                                                <i class="fas fa-info-circle"></i> Completa todos los documentos para desbloquear
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <!-- Sin quiz configurado -->
                                                    <div style="text-align: center; font-size: 12px; color: #95a5a6; padding: 8px 0;">
                                                        <i class="fas fa-info-circle"></i> Este módulo no tiene quiz configurado
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                        <?php else: ?>
                                            <!-- CARPETA BLOQUEADA -->
                                            <div class="bloqueo-overlay" style="display: flex;">
                                                <i class="fas fa-lock"></i>
                                                <span>Completa el módulo anterior para desbloquear este contenido.</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- ÁREA BLOQUEADA -->
                        <div class="area-content-bloqueada">
                            <div class="mensaje-bloqueo">
                                <i class="fas fa-lock"></i>
                                <p>Completa los módulos anteriores para desbloquear esta área.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- ============================================ -->
            <!-- SECCIÓN DE CERTIFICADO - VERSIÓN ELEGANTE    -->
            <!-- ============================================ -->
            <?php 
            $induccion_completada = induccionCompletadaCert($usuario_id);
            $progreso_total = obtenerProgresoCert($usuario_id);
            ?>

            <div style="
                max-width: 1200px;
                margin: 30px auto 0;
                padding: 16px 24px;
                border-radius: 12px;
                background: <?php echo $induccion_completada ? 'linear-gradient(135deg, #fef9e7, #fdebd0)' : '#f8f9fa'; ?>;
                border: 1px solid <?php echo $induccion_completada ? '#f39c12' : '#e9ecef'; ?>;
                text-align: center;
                position: relative;
                overflow: hidden;
            ">

                <?php if ($induccion_completada): ?>
                    <!-- Fondo decorativo -->
                    <div style="
                        position: absolute;
                        top: -30px;
                        right: -30px;
                        width: 100px;
                        height: 100px;
                        background: rgba(243, 156, 18, 0.05);
                        border-radius: 50%;
                    "></div>
                    <div style="
                        position: absolute;
                        bottom: -40px;
                        left: -40px;
                        width: 120px;
                        height: 120px;
                        background: rgba(243, 156, 18, 0.05);
                        border-radius: 50%;
                    "></div>

                    <!-- Contenido -->
                    <div style="position: relative; z-index: 1;">
                        <div style="
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 12px;
                            margin-bottom: 6px;
                        ">
                            <i class="fas fa-trophy" style="font-size: 24px; color: #f39c12;"></i>
                            <span style="
                                font-size: 18px;
                                font-weight: 600;
                                color: #12232b;
                            ">¡Felicidades!</span>
                        </div>
                        <p style="
                            margin: 0 0 12px 0;
                            font-size: 13px;
                            color: #7f8c8d;
                        ">
                            Has completado la inducción
                        </p>
                        <a href="generar_certificado.php" target="_blank" style="
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            background: linear-gradient(135deg, #173742, #1a4a5a);
                            color: white;
                            padding: 8px 24px;
                            border-radius: 8px;
                            text-decoration: none;
                            font-size: 13px;
                            font-weight: 500;
                            transition: all 0.3s ease;
                            border: none;
                            cursor: pointer;
                        "
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(23,55,66,0.3)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fas fa-certificate"></i> 
                            Generar Certificado
                        </a>
                    </div>

                <?php else: ?>

                    <!-- Estado: No completado -->
                    <div style="display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap;">
                        <div style="
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            color: #7f8c8d;
                            font-size: 13px;
                        ">
                            <i class="fas fa-clock" style="color: #f39c12;"></i>
                            <span>Progreso: <strong><?php echo $progreso_total['porcentaje']; ?>%</strong></span>
                        </div>
                        <div style="
                            width: 1px;
                            height: 20px;
                            background: #d3d3d3;
                        "></div>
                        <span style="font-size: 12px; color: #95a5a6;">
                            <i class="fas fa-lock" style="margin-right: 4px;"></i>
                            Completa el 100% para tu certificado
                        </span>
                    </div>

                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<script>
    // ============================================
    // FUNCIONES DE UI - TOGGLE
    // ============================================
    function toggleArea(areaId) {
        const content = document.getElementById('area_content_' + areaId);
        const icon = document.getElementById('area_icon_' + areaId);
        
        if (content.classList.contains('visible')) {
            content.classList.remove('visible');
            icon.innerHTML = '<i class="fas fa-chevron-down"></i>';
            icon.classList.remove('rotated');
        } else {
            content.classList.add('visible');
            icon.innerHTML = '<i class="fas fa-chevron-up"></i>';
            icon.classList.add('rotated');
        }
    }

    function toggleCarpeta(areaId, index) {
        const docs = document.getElementById('docs_' + areaId + '_' + index);
        const icon = document.getElementById('icon_' + areaId + '_' + index);
        
        if (docs.classList.contains('visible')) {
            docs.classList.remove('visible');
            icon.innerHTML = '<i class="fas fa-chevron-right"></i>';
        } else {
            docs.classList.add('visible');
            icon.innerHTML = '<i class="fas fa-chevron-down"></i>';
        }
    }

    // ============================================
    // FUNCIÓN PRINCIPAL - ABRIR Y MARCAR DOCUMENTO
    // ============================================
    function abrirYMarcar(url, documentoId) {
        marcarVisto(documentoId, function() {
            setTimeout(function() {
                if (url.startsWith('http://') || url.startsWith('https://')) {
                    window.open(url, '_blank');
                } else {
                    window.open('../../../' + url, '_blank');
                }
            }, 500);
        });
    }

    // ============================================
    // MARCAR DOCUMENTO COMO VISTO
    // ============================================
    function marcarVisto(documentoId, callback) {
        const docElement = document.querySelector(`[data-doc-id="${documentoId}"]`);
        if (docElement) {
            const btn = docElement.querySelector('.btn-documento');
            if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                btn.disabled = true;
                btn.dataset.originalHtml = originalHtml;
            }
        }
        
        fetch('marcar_visto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ documento_id: documentoId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const docElement = document.querySelector(`[data-doc-id="${documentoId}"]`);
                if (docElement) {
                    docElement.classList.add('visto');
                    
                    if (!docElement.querySelector('.documento-check')) {
                        const check = document.createElement('span');
                        check.className = 'documento-check';
                        check.innerHTML = '<i class="fas fa-check-circle"></i>';
                        docElement.insertBefore(check, docElement.lastChild);
                    }
                    
                    const btn = docElement.querySelector('.btn-documento');
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Ver';
                        btn.disabled = false;
                    }
                    
                    const carpetaContainer = docElement.closest('.carpeta-container');
                    if (carpetaContainer) {
                        const categoriaId = carpetaContainer.dataset.categoriaId;
                        if (categoriaId) {
                            actualizarProgresoCarpeta(carpetaContainer);
                            verificarYMostrarQuiz(carpetaContainer);
                        }
                    }
                }
            } else {
                const docElement = document.querySelector(`[data-doc-id="${documentoId}"]`);
                if (docElement) {
                    const btn = docElement.querySelector('.btn-documento');
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Ver';
                        btn.disabled = false;
                    }
                }
                alert('Error al marcar el documento como visto');
            }
            
            if (typeof callback === 'function') {
                callback();
            }
        })
        .catch(error => {
            const docElement = document.querySelector(`[data-doc-id="${documentoId}"]`);
            if (docElement) {
                const btn = docElement.querySelector('.btn-documento');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Ver';
                    btn.disabled = false;
                }
            }
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    // ============================================
    // ACTUALIZAR PROGRESO DE LA CARPETA
    // ============================================
    function actualizarProgresoCarpeta(carpetaContainer) {
        if (!carpetaContainer) return;
        if (carpetaContainer.classList.contains('bloqueado')) return;
        
        const allDocs = carpetaContainer.querySelectorAll('.documento-item');
        const vistos = carpetaContainer.querySelectorAll('.documento-item.visto').length;
        const total = allDocs.length;
        const porcentaje = total > 0 ? Math.round((vistos / total) * 100) : 0;
        const completado = porcentaje === 100 && total > 0;
        
        const progressBar = carpetaContainer.querySelector('.progress-bar');
        const progressText = carpetaContainer.querySelector('.progress-text');
        const labelProgreso = carpetaContainer.querySelector('.progress-wrapper .label-progreso');
        
        if (progressBar) {
            progressBar.style.width = porcentaje + '%';
            if (completado) {
                progressBar.classList.add('completed');
            } else {
                progressBar.classList.remove('completed');
            }
        }
        
        if (progressText) {
            progressText.textContent = porcentaje + '%';
            if (completado) {
                progressText.classList.add('completed');
            } else {
                progressText.classList.remove('completed');
            }
        }
        
        if (labelProgreso) {
            labelProgreso.textContent = 'Progreso: ' + vistos + '/' + total;
        }
        
        const badgeCompletado = carpetaContainer.querySelector('.badge-completado');
        const badgeDisponible = carpetaContainer.querySelector('.badge-desbloqueado');
        
        if (completado) {
            if (badgeCompletado) {
                badgeCompletado.style.display = 'inline-block';
                badgeCompletado.innerHTML = '<i class="fas fa-check-circle"></i> Completado';
            }
            if (badgeDisponible) {
                badgeDisponible.style.display = 'none';
            }
        } else {
            if (badgeCompletado) {
                badgeCompletado.style.display = 'none';
            }
            if (badgeDisponible && !carpetaContainer.classList.contains('bloqueado')) {
                badgeDisponible.style.display = 'inline-block';
                badgeDisponible.innerHTML = '📖 En progreso (' + vistos + '/' + total + ')';
            }
        }
    }

    // ============================================
    // VERIFICAR Y MOSTRAR QUIZ
    // ============================================
    function verificarYMostrarQuiz(carpetaContainer) {
        if (!carpetaContainer) return;
        
        const categoriaId = carpetaContainer.dataset.categoriaId;
        if (!categoriaId) return;
        
        const tieneQuiz = carpetaContainer.dataset.tieneQuiz === 'true';
        if (!tieneQuiz) return;
        
        fetch(`estado_carpeta.php?categoria_id=${categoriaId}&t=${Date.now()}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) return;
                actualizarSeccionQuiz(carpetaContainer, data);
            })
            .catch(error => {});
    }

    // ============================================
    // ACTUALIZAR SECCIÓN DEL QUIZ
    // ============================================
    function actualizarSeccionQuiz(carpetaContainer, data) {
        let quizSection = carpetaContainer.querySelector('.quiz-section');
        
        if (!quizSection) {
            quizSection = document.createElement('div');
            quizSection.className = 'quiz-section';
            quizSection.style.marginTop = '10px';
            carpetaContainer.appendChild(quizSection);
        }
        
        if (!data.tiene_quiz_url) {
            quizSection.innerHTML = `
                <div style="text-align: center; font-size: 12px; color: #95a5a6;">
                    <i class="fas fa-info-circle"></i> Este módulo no tiene quiz configurado
                </div>
            `;
            quizSection.style.display = 'block';
            return;
        }
        
        if (data.quiz_completado) {
            quizSection.innerHTML = `
                <div style="text-align: center;">
                    <span style="background: #27ae60; color: white; padding: 6px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; display: inline-block;">
                        <i class="fas fa-check-circle"></i> Quiz completado ✅
                    </span>
                </div>
            `;
            quizSection.style.display = 'block';
            return;
        }
        
        if (data.boton_quiz_desbloqueado) {
            quizSection.innerHTML = `
                <div style="text-align: center; padding-top: 15px; border-top: 2px dashed #e0e0e0;">
                    <button type="button" 
                        onclick="marcarYAbirQuiz('${data.quiz_url}', ${carpetaContainer.dataset.categoriaId})" 
                        class="btn-quiz" style="
                            background: linear-gradient(135deg, #27ae60, #2ecc71);
                            color: white;
                            padding: 10px 25px;
                            border-radius: 8px;
                            text-decoration: none;
                            display: inline-block;
                            font-weight: 600;
                            font-size: 14px;
                            transition: all 0.3s ease;
                            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
                            cursor: pointer;
                            border: none;
                        "
                        onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 25px rgba(39,174,96,0.4)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(39,174,96,0.3)';">
                        <i class="fas fa-external-link-alt"></i> Realizar Quiz
                    </button>
                    <div style="margin-top: 5px; font-size: 12px; color: #27ae60;">
                        <i class="fas fa-check-circle"></i> Todos los documentos completados
                    </div>
                </div>
            `;
            quizSection.style.display = 'block';
            return;
        }
        
        quizSection.innerHTML = `
            <div style="text-align: center;">
                <button class="btn-quiz" style="
                        background: #95a5a6;
                        color: white;
                        padding: 10px 25px;
                        border-radius: 8px;
                        display: inline-block;
                        font-weight: 600;
                        font-size: 14px;
                        border: none;
                        cursor: not-allowed;
                        opacity: 0.6;
                    ">
                    <i class="fas fa-lock"></i> Quiz bloqueado
                </button>
                <div style="margin-top: 5px; font-size: 12px; color: #7f8c8d;">
                    <i class="fas fa-info-circle"></i> Completa todos los documentos (${data.vistos}/${data.total_docs}) para desbloquear
                </div>
            </div>
        `;
        quizSection.style.display = 'block';
    }

    // ============================================
    // ACTUALIZAR PROGRESO UI
    // ============================================
    function actualizarProgresoUI(carpetaContainer, data) {
        const progressBar = carpetaContainer.querySelector('.progress-bar');
        const progressText = carpetaContainer.querySelector('.progress-text');
        const labelProgreso = carpetaContainer.querySelector('.progress-wrapper .label-progreso');
        
        if (progressBar) {
            progressBar.style.width = data.progreso + '%';
            progressBar.classList.toggle('completed', data.progreso == 100);
        }
        
        if (progressText) {
            progressText.textContent = data.progreso + '%';
            progressText.classList.toggle('completed', data.progreso == 100);
        }
        
        if (labelProgreso) {
            labelProgreso.textContent = 'Progreso: ' + data.vistos + '/' + data.total_docs;
        }
        
        const badgeCompletado = carpetaContainer.querySelector('.badge-completado');
        const badgeDisponible = carpetaContainer.querySelector('.badge-desbloqueado');
        
        if (data.progreso == 100 && data.quiz_completado) {
            if (badgeCompletado) badgeCompletado.style.display = 'inline-block';
            if (badgeDisponible) badgeDisponible.style.display = 'none';
        } else if (data.progreso == 100 && !data.quiz_completado) {
            if (badgeCompletado) badgeCompletado.style.display = 'none';
            if (badgeDisponible) {
                badgeDisponible.style.display = 'inline-block';
                badgeDisponible.textContent = '✅ Listo para quiz';
            }
        } else {
            if (badgeCompletado) badgeCompletado.style.display = 'none';
            if (badgeDisponible && !carpetaContainer.classList.contains('bloqueado')) {
                badgeDisponible.style.display = 'inline-block';
                badgeDisponible.textContent = '📖 En progreso (' + data.vistos + '/' + data.total_docs + ')';
            }
        }
    }

    // ============================================
    // MARCAR QUIZ Y ABRIR ENLACE
    // ============================================
    function marcarYAbirQuiz(quizUrl, categoriaId) {
        let btn = null;
        if (window.event) {
            btn = window.event.currentTarget || window.event.target;
        }
        
        if (!btn) {
            const buttons = document.querySelectorAll('.btn-quiz');
            for (let b of buttons) {
                const onclick = b.getAttribute('onclick') || '';
                if (onclick.includes(`marcarYAbirQuiz('${quizUrl}', ${categoriaId})`)) {
                    btn = b;
                    break;
                }
            }
        }
        
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btn.disabled = true;
            btn.style.opacity = '0.7';
            btn.style.cursor = 'wait';
        }
        
        fetch(`completar_quiz.php?categoria_id=${categoriaId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-check-circle"></i> Quiz Completado ✅';
                        btn.style.background = 'linear-gradient(135deg, #27ae60, #2ecc71)';
                        btn.disabled = true;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'default';
                    }
                    
                    const nuevaVentana = window.open(quizUrl, '_blank');
                    if (!nuevaVentana) {
                        alert('Por favor, permite las ventanas emergentes para abrir el quiz');
                    }
                    
                    mostrarNotificacion('✅ Quiz completado correctamente. La página se recargará.', 'success');
                    
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                    
                } else {
                    alert('❌ Error al completar el quiz: ' + (data.error || 'Error desconocido'));
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Realizar Quiz';
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    }
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error.message);
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Realizar Quiz';
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                }
            });
    }

    // ============================================
    // MOSTRAR NOTIFICACIÓN
    // ============================================
    function mostrarNotificacion(mensaje, tipo = 'info') {
        const notificacion = document.createElement('div');
        notificacion.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            background: ${tipo === 'success' ? '#27ae60' : tipo === 'error' ? '#e74c3c' : '#3498db'};
            color: white;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: slideIn 0.5s ease;
            max-width: 400px;
        `;
        notificacion.textContent = mensaje;
        
        document.body.appendChild(notificacion);
        
        setTimeout(function() {
            notificacion.style.opacity = '0';
            notificacion.style.transition = 'opacity 0.5s ease';
            setTimeout(function() {
                notificacion.remove();
            }, 500);
        }, 4000);
    }

    // ============================================
    // FUNCIÓN PARA VERIFICAR DESBLOQUEO DE CARPETA SIGUIENTE
    // ============================================
    function verificarDesbloqueoSiguienteCarpeta(carpetaContainer) {
        const categoriaId = carpetaContainer.dataset.categoriaId;
        if (!categoriaId) return;
        
        const areaCard = carpetaContainer.closest('.area-card');
        if (!areaCard) return;
        
        const todasLasCarpetas = areaCard.querySelectorAll('.carpeta-container');
        let indiceActual = -1;
        
        todasLasCarpetas.forEach((c, i) => {
            if (c.dataset.categoriaId === categoriaId) {
                indiceActual = i;
            }
        });
        
        if (indiceActual === -1) return;
        
        const siguienteCarpeta = todasLasCarpetas[indiceActual + 1];
        if (!siguienteCarpeta) return;
        
        if (!siguienteCarpeta.classList.contains('bloqueado')) {
            return;
        }
        
        fetch(`estado_carpeta.php?categoria_id=${categoriaId}&t=${Date.now()}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) return;
                
                if (data.progreso === 100 && data.quiz_completado) {
                    location.reload();
                }
            })
            .catch(error => {});
    }

    // ============================================
    // INICIALIZACIÓN - AL CARGAR LA PÁGINA
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.carpeta-container:not(.bloqueado)').forEach(function(container) {
            const tieneQuiz = container.dataset.tieneQuiz === 'true';
            if (tieneQuiz) {
                setTimeout(function() {
                    verificarYMostrarQuiz(container);
                }, 500);
            }
        });
    });

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            document.querySelectorAll('.carpeta-container:not(.bloqueado)').forEach(function(container) {
                const tieneQuiz = container.dataset.tieneQuiz === 'true';
                if (tieneQuiz) {
                    verificarYMostrarQuiz(container);
                }
            });
        }
    });
</script>

</body>
</html>