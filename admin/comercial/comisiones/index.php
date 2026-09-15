<?php
// admin/comercial/comisiones/index.php - Gestión de Comisiones
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
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
        WHERE pu.usuario_id = ? AND m.nombre = 'comercial' AND m.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso || $permiso['tiene_permiso'] == 0) {
        $_SESSION['error'] = "❌ No tienes permiso para acceder a comisiones";
        header('Location: ../../index.php');
        exit();
    }
} 
// Usuario normal: sin acceso
else {
    header('Location: ../../index.php');
    exit();
}

// ✅ Recuperar mensajes de sesión
$mensaje = '';
$error = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ============================================
// TIPOS DE COMISIONES
// ============================================
$tipos_comisiones = [
    'matriculas' => 'Matrículas',
    'usados' => 'Usados',
    'financieras' => 'Financieras',
    'accesorios' => 'Accesorios',
    'incentivos' => 'Incentivos'
];

// ============================================
// MESES
// ============================================
$meses = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

// ============================================
// AÑOS DISPONIBLES
// ============================================
$año_actual = date('Y');  // Año del servidor
$año_minimo = 2026;        // Año mínimo fijo

$años = [];
for ($i = $año_minimo; $i <= $año_actual; $i++) {
    $años[] = $i;
}

// ============================================
// ESTADÍSTICAS (sumando todas las tablas disponibles)
// ============================================

// Tablas disponibles para estadísticas
$tablas_estadisticas = ['com_matriculas', 'com_usados'];

// Total de meses cargados
$total_meses_cargados = 0;
foreach ($tablas_estadisticas as $tabla) {
    $stmt = $pdo->query("SELECT COUNT(DISTINCT DATE_FORMAT(fecha_comision, '%Y-%m')) as total FROM $tabla");
    $total_meses_cargados += $stmt->fetchColumn() ?? 0;
}

// Total de registros
$total_registros = 0;
foreach ($tablas_estadisticas as $tabla) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM $tabla");
    $total_registros += $stmt->fetchColumn() ?? 0;
}

// Total comisiones
$total_comisiones = 0;
foreach ($tablas_estadisticas as $tabla) {
    $stmt = $pdo->query("SELECT COALESCE(SUM(comision_real), 0) FROM $tabla");
    $total_comisiones += $stmt->fetchColumn() ?? 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comisiones | INTRANET</title>
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

        /* ===== DASHBOARD CARDS ===== */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card-dashboard {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card-dashboard:hover { transform: translateY(-5px); }
        .card-dashboard i {
            font-size: 42px;
            color: #173742;
            margin-bottom: 12px;
        }
        .card-dashboard h3 {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .card-dashboard .numero {
            font-size: 28px;
            font-weight: 700;
            color: #12232b;
        }

        /* ===== CARD PRINCIPAL ===== */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-bottom: 20px;
            color: #12232b;
            border-left: 4px solid #173742;
            padding-left: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== FORMULARIO ===== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 5px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #2c3e50;
        }
        .form-group label .obligatorio { color: #e74c3c; }
        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d3d3d3;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        .form-group select:focus,
        .form-group input[type="file"]:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }
        .form-group input[type="file"] {
            padding: 8px;
            cursor: pointer;
        }

        .btn-subir {
            background: linear-gradient(135deg, #173742, #1a4a55);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-subir:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23,55,66,0.3);
        }

        .info-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        /* ===== MENSAJES ===== */
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            transition: opacity 0.5s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            transition: opacity 0.5s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header .container { flex-direction: column; gap: 15px; text-align: center; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Comisiones</small>
            </div>
            <div class="user-info">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- ===== MENSAJES ===== -->
        <?php if ($mensaje): ?>
            <div class="mensaje-exito" id="mensajeExito">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje-error" id="mensajeError">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ===== FORMULARIO DE CARGA ===== -->
        <div class="card">
            <h2><i class="fas fa-cloud-upload-alt"></i> Subir archivo de comisiones</h2>
            
            <form action="guardar.php" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    
                    <!-- Tipo de comisión -->
                    <div class="form-group">
                        <label>Tipo de comisión <span class="obligatorio">*</span></label>
                        <select name="tipo" required>
                            <option value="">Seleccione un tipo</option>
                            <?php foreach ($tipos_comisiones as $key => $nombre): ?>
                                <option value="<?php echo $key; ?>"><?php echo $nombre; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Año -->
                    <div class="form-group">
                        <label>Año <span class="obligatorio">*</span></label>
                        <select name="año" required>
                            <option value="">Seleccione un año</option>
                            <?php foreach ($años as $año): ?>
                                <option value="<?php echo $año; ?>" <?php echo $año == $año_actual ? 'selected' : ''; ?>>
                                    <?php echo $año; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Mes -->
                    <div class="form-group">
                        <label>Mes <span class="obligatorio">*</span></label>
                        <select name="mes" required>
                            <option value="">Seleccione un mes</option>
                            <?php foreach ($meses as $num => $nombre): ?>
                                <option value="<?php echo $num; ?>" <?php echo $num == date('n') ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Archivo -->
                    <div class="form-group">
                        <label>Archivo Excel <span class="obligatorio">*</span></label>
                        <input type="file" name="archivo" accept=".xls,.xlsx" required>
                        <div class="info-text">
                            <i class="fas fa-info-circle"></i> Formatos permitidos: XLS, XLSX. Máximo 5MB
                        </div>
                    </div>

                </div>

                <button type="submit" class="btn-subir">
                    <i class="fas fa-upload"></i> Subir archivo
                </button>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // OCULTAR MENSAJES A LOS 2 SEGUNDOS
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const mensajeExito = document.getElementById('mensajeExito');
            if (mensajeExito) {
                setTimeout(function() {
                    mensajeExito.style.opacity = '0';
                    setTimeout(function() {
                        mensajeExito.style.display = 'none';
                    }, 500);
                }, 2000);
            }

            const mensajeError = document.getElementById('mensajeError');
            if (mensajeError) {
                setTimeout(function() {
                    mensajeError.style.opacity = '0';
                    setTimeout(function() {
                        mensajeError.style.display = 'none';
                    }, 500);
                }, 2000);
            }
        });
    </script>
</body>
</html>