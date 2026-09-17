<?php
// aplicaciones/otros/comercial/index.php - Panel del módulo Comercial
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$usuario = obtenerUsuario();
$usuario_id = $usuario['id'];
$nombre_usuario = $usuario['nombre_completo'];

// ✅ CONSULTAR cod_asesor DIRECTAMENTE EN LA BD
$stmt = $pdo->prepare("SELECT cod_asesor FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$cod_asesor = $stmt->fetchColumn();

// ============================================
// SI NO TIENE CÓDIGO DE ASESOR
// ============================================
if (empty($cod_asesor)) {
    $sin_codigo = true;
} else {
    $sin_codigo = false;
    
    // ============================================
    // ESTADÍSTICAS DEL MES VENCIDO
    // ============================================
    // El mes vencido = mes anterior al actual
    $mes_vencido = date('n') - 1;
    $anio_vencido = date('Y');
    
    // Si el mes es enero (1), el mes vencido es diciembre (12) del año anterior
    if ($mes_vencido < 1) {
        $mes_vencido = 12;
        $anio_vencido = date('Y') - 1;
    }
    
    $mes_actual = $mes_vencido;
    $anio_actual = $anio_vencido;
    
    // Total comisiones del mes vencido (matriculas)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            COALESCE(SUM(comision_real), 0) as total_comisiones,
            COALESCE(SUM(bono), 0) as total_bonos
        FROM com_matriculas 
        WHERE cod_vendedor = ? 
        AND MONTH(fecha_comision) = ? 
        AND YEAR(fecha_comision) = ?
    ");
    $stmt->execute([$cod_asesor, $mes_actual, $anio_actual]);
    $stats_mes = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_ventas = $stats_mes['total_ventas'] ?? 0;
    $total_comisiones = $stats_mes['total_comisiones'] ?? 0;
    $total_bonos = $stats_mes['total_bonos'] ?? 0;
    
    // Ranking: cuántos asesores vendieron más que él este mes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as ranking
        FROM (
            SELECT cod_vendedor, SUM(comision_real) as total
            FROM com_matriculas 
            WHERE MONTH(fecha_comision) = ? 
            AND YEAR(fecha_comision) = ?
            GROUP BY cod_vendedor
            HAVING total > ?
        ) as ranking_temp
    ");
    $stmt->execute([$mes_actual, $anio_actual, $total_comisiones]);
    $ranking = $stmt->fetchColumn() ?? 1;
    
    // Total de asesores con ventas este mes
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT cod_vendedor) 
        FROM com_matriculas 
        WHERE MONTH(fecha_comision) = ? 
        AND YEAR(fecha_comision) = ?
    ");
    $stmt->execute([$mes_actual, $anio_actual]);
    $total_asesores = $stmt->fetchColumn() ?? 0;
}

// ============================================
// MESES EN ESPAÑOL
// ============================================
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$nombre_mes = $meses_espanol[$mes_actual ?? date('n')];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo Comercial | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #d3d3d3; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

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
        .logo h1 { color: white; font-size: 24px; }
        .logo span { color: #445960; }
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            color: white;
        }
        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* ===== BIENVENIDA ===== */
        .welcome-card {
            background: linear-gradient(135deg, #12232b 0%, #173742 100%);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            display: flex;
            align-items: center;
            gap: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .welcome-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.15);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            color: #ffc107;
            flex-shrink: 0;
        }
        .welcome-text h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .welcome-text p {
            color: #8aa8b8;
            font-size: 14px;
        }
        .cod-badge {
            display: inline-block;
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        /* ===== SIN CÓDIGO ===== */
        .sin-codigo {
            background: #fef9e7;
            border: 2px solid #f39c12;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        .sin-codigo i {
            font-size: 56px;
            color: #f39c12;
            margin-bottom: 15px;
        }
        .sin-codigo h3 {
            color: #7f6000;
            font-size: 20px;
            margin-bottom: 10px;
        }
        .sin-codigo p {
            color: #7f6000;
            font-size: 14px;
        }

        /* ===== SECCIÓN TÍTULO ===== */
        .section-title {
            font-size: 18px;
            color: #12232b;
            margin-bottom: 20px;
            border-left: 4px solid #173742;
            padding-left: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== MÓDULOS ===== */
        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .modulo-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        .modulo-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #173742, #445960);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .modulo-card:hover::before {
            transform: scaleX(1);
        }
        .modulo-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            border-color: #173742;
        }
        .modulo-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .modulo-card:hover .modulo-icon {
            transform: scale(1.1);
        }
        .modulo-icon.comisiones {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }
        .modulo-icon.estadisticas {
            background: linear-gradient(135deg, #3498db, #5dade2);
        }
        .modulo-icon.reclamos {
            background: linear-gradient(135deg, #e67e22, #f39c12);
        }
        .modulo-info h3 {
            font-size: 19px;
            color: #12232b;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .modulo-info p {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        .btn-entrar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #173742;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .modulo-card:hover .btn-entrar {
            background: #ffc107;
            color: #12232b;
        }

        /* ===== RESUMEN DEL MES ===== */
        .resumen-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .resumen-card h3 {
            font-size: 16px;
            color: #12232b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px;
        }
        .resumen-card h3 i {
            color: #173742;
        }
        .resumen-card h3 .badge-vencido {
            background: #fef9e7;
            color: #7f6000;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #f39c12;
            margin-left: 8px;
        }
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .resumen-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .resumen-item:hover {
            background: #e8f0fe;
            transform: translateY(-3px);
        }
        .resumen-item i {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }
        .resumen-item .icon-money { color: #27ae60; }
        .resumen-item .icon-bono { color: #f39c12; }
        .resumen-item .icon-ventas { color: #3498db; }
        .resumen-item .icon-ranking { color: #e74c3c; }
        .resumen-item .numero {
            font-size: 22px;
            font-weight: 700;
            color: #12232b;
            margin-bottom: 4px;
        }
        .resumen-item .label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .welcome-card { flex-direction: column; text-align: center; }
            .modulos-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Comercial</small>
            </div>
            <div class="user-info">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- ===== BIENVENIDA ===== -->
        <div class="welcome-card">
            <div class="welcome-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="welcome-text">
                <h2>Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?></h2>
                <p>Este es tu panel comercial. Consulta tus comisiones y estadísticas aquí.</p>
                <?php if (!$sin_codigo): ?>
                    <span class="cod-badge">
                        <i class="fas fa-id-card"></i> Código de Asesor: <?php echo htmlspecialchars($cod_asesor); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sin_codigo): ?>
            <!-- ===== SIN CÓDIGO DE ASESOR ===== -->
            <div class="sin-codigo">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Sin código de asesor asignado</h3>
                <p>Tu usuario no tiene un código de asesor configurado. Contacta al área de Sistemas para que te asignen uno.</p>
            </div>
        <?php else: ?>
            
            <!-- ===== MÓDULOS ===== -->
            <h2 class="section-title">
                <i class="fas fa-th-large"></i> Módulos disponibles
            </h2>

            <div class="modulos-grid">
                <!-- Comisiones -->
                <a href="comisiones/index.php" class="modulo-card">
                    <div class="modulo-icon comisiones">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="modulo-info">
                        <h3>Comisiones</h3>
                        <p>Consulta el detalle de tus comisiones por mes y año</p>
                    </div>
                    <span class="btn-entrar">
                        Entrar <i class="fas fa-arrow-right"></i>
                    </span>
                </a>

                <!-- Estadísticas -->
                <a href="estadisticas/index.php" class="modulo-card">
                    <div class="modulo-icon estadisticas">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="modulo-info">
                        <h3>Estadísticas</h3>
                        <p>Mira cómo te fue este mes y tu evolución</p>
                    </div>
                    <span class="btn-entrar">
                        Entrar <i class="fas fa-arrow-right"></i>
                    </span>
                </a>

                <!-- ✅ NUEVO: Reclamos -->
                <a href="reclamos/index.php" class="modulo-card">
                    <div class="modulo-icon reclamos">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="modulo-info">
                        <h3>Reclamos</h3>
                        <p>Reporta inconformidades sobre tus comisiones</p>
                    </div>
                    <span class="btn-entrar">
                        Entrar <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>