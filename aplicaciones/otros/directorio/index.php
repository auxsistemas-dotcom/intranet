<?php
// aplicaciones/otros/directorio/index.php - Vista Pública del Directorio Corporativo
require_once '../../../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Consultar las personas habilitadas en el directorio corporativo
$personas_directorio = [];
$cargos_unicos = [];
$error_carga = '';

try {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.nombre_completo,
            COALESCE(NULLIF(TRIM(u.cargo), ''), 'Sin cargo asignado') AS cargo,
            COALESCE(NULLIF(TRIM(u.email), ''), '') AS email,
            COALESCE(NULLIF(TRIM(u.telefono), ''), '') AS telefono
        FROM directorio d
        INNER JOIN usuarios u ON d.usuario_id = u.id
        WHERE u.activo = 1
        ORDER BY u.nombre_completo ASC
    ");
    $personas_directorio = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Extraer cargos únicos ordenados alfabéticamente para el filtro
    $cargos_unicos = array_unique(array_column($personas_directorio, 'cargo'));
    sort($cargos_unicos, SORT_LOCALE_STRING);
} catch (PDOException $e) {
    error_log("Error al cargar directorio público: " . $e->getMessage());
    $error_carga = 'No se pudo cargar la información del directorio en este momento.';
}

// Helper para obtener iniciales del nombre
function obtenerIniciales($nombre) {
    $partes = preg_split('/\s+/', trim($nombre));
    if (count($partes) >= 2) {
        return mb_strtoupper(mb_substr($partes[0], 0, 1) . mb_substr($partes[1], 0, 1));
    }
    return mb_strtoupper(mb_substr($nombre, 0, 2));
}

// Helper para limpiar número de celular para WhatsApp (Colombia +57 por defecto si tiene 10 dígitos)
function formatearWhatsApp($telefono) {
    $soloNumeros = preg_replace('/\D+/', '', $telefono);
    if (strlen($soloNumeros) === 10 && str_starts_with($soloNumeros, '3')) {
        return '57' . $soloNumeros;
    }
    return $soloNumeros;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio Corporativo | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #d3d3d3;
            color: #2c3e50;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: #12232b;
            margin-bottom: 25px;
            border-bottom: 3px solid #173742;
        }

        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo {
            align-items: center;
            gap: 12px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-volver {
            background: #445960;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .btn-volver:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }

        /* Panel superior de búsqueda y filtros */
        .search-panel {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .panel-header h1 {
            color: #12232b;
            border-left: 4px solid #173742;
            padding-left: 15px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-header-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .contador-badge {
            background: #173742;
            color: white;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
        }

        /* Switch de vista (Tarjetas / Lista) */
        .view-toggle {
            display: inline-flex;
            background: #eceff1;
            border-radius: 8px;
            padding: 3px;
        }

        .view-btn {
            border: none;
            background: transparent;
            color: #455a64;
            padding: 6px 12px;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .view-btn.activo {
            background: #12232b;
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }

        /* Barra de controles */
        .filtros-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            margin-bottom: 18px;
        }

        @media (max-width: 768px) {
            .filtros-grid {
                grid-template-columns: 1fr;
            }
        }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #cfd8dc;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #2c3e50;
            background: #fafafa;
            transition: all 0.3s;
        }

        .input-icon-wrap .form-control {
            padding-left: 42px;
        }

        .form-control:focus {
            outline: none;
            border-color: #173742;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(23, 55, 66, 0.1);
        }

        .btn-limpiar {
            background: #cfd8dc;
            color: #2c3e50;
            border: none;
            padding: 11px 18px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .btn-limpiar:hover {
            background: #b0bec5;
        }

        /* Filtro alfabético rápido */
        .alpha-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding-top: 14px;
            border-top: 1px solid #eceff1;
            align-items: center;
        }

        .alpha-label {
            font-size: 12px;
            font-weight: 600;
            color: #607d8b;
            margin-right: 6px;
        }

        .alpha-btn {
            border: 1px solid #cfd8dc;
            background: #f8f9fa;
            color: #2c3e50;
            min-width: 30px;
            height: 30px;
            padding: 0 8px;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .alpha-btn:hover {
            border-color: #173742;
            color: #173742;
        }

        .alpha-btn.activo {
            background: #173742;
            color: white;
            border-color: #173742;
        }

        /* Grid de Tarjetas */
        .directorio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 20px;
        }

        .persona-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-top: 4px solid #173742;
            transition: transform 0.25s, box-shadow 0.25s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .persona-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(18, 35, 43, 0.16);
        }

        .card-top {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .avatar-circle {
            width: 54px;
            height: 54px;
            min-width: 54px;
            border-radius: 50%;
            background: linear-gradient(135deg, #12232b, #173742);
            color: #ffc107;
            font-weight: 700;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.5px;
        }

        .persona-identidad h3 {
            font-size: 16px;
            font-weight: 600;
            color: #12232b;
            line-height: 1.3;
            margin-bottom: 5px;
        }

        .cargo-badge {
            display: inline-block;
            background: #e8eef0;
            color: #173742;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 6px;
        }

        .card-datos {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-top: 14px;
            border-top: 1px solid #eceff1;
        }

        .dato-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 13.5px;
            color: #37474f;
        }

        .dato-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .dato-info i {
            color: #173742;
            width: 16px;
            text-align: center;
            font-size: 14px;
        }

        .dato-texto {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dato-texto a {
            color: #2c3e50;
            text-decoration: none;
            transition: color 0.2s;
        }

        .dato-texto a:hover {
            color: #173742;
            text-decoration: underline;
        }

        .dato-acciones {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .mini-btn {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 1px solid #cfd8dc;
            background: #f8f9fa;
            color: #455a64;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }

        .mini-btn:hover {
            background: #173742;
            color: white;
            border-color: #173742;
        }

        .mini-btn.whatsapp:hover {
            background: #25d366;
            color: white;
            border-color: #25d366;
        }

        /* Vista Tabla */
        .tabla-box {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }

        .tabla-box.activa {
            display: block;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead {
            background: #12232b;
            color: white;
        }

        th, td {
            padding: 13px 15px;
            text-align: left;
            border-bottom: 1px solid #eceff1;
        }

        th {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        tbody tr:hover {
            background: #f5f7f8;
        }

        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 50px 20px;
            text-align: center;
            color: #7f8c8d;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 46px;
            color: #b0bec5;
            margin-bottom: 12px;
        }

        .empty-state h3 {
            color: #12232b;
            margin-bottom: 6px;
        }

        /* Toast de copiado */
        .toast-copy {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #12232b;
            color: white;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border-left: 4px solid #ffc107;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
            opacity: 0;
            transform: translateY(15px);
            pointer-events: none;
            transition: all 0.3s ease;
            z-index: 999;
        }

        .toast-copy.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <small style="color: #8aa8b8;">| Directorio Corporativo</small>
            </div>
            <div class="header-actions">
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver al Inicio
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Panel de Búsqueda y Filtros -->
        <div class="search-panel">
            <div class="panel-header">
                <h1><i class="fas fa-address-book"></i> Directorio Corporativo</h1>
                <div class="panel-header-right">
                    <span class="contador-badge" id="contadorResultados">
                        <?php echo count($personas_directorio); ?> <?php echo count($personas_directorio) === 1 ? 'contacto' : 'contactos'; ?>
                    </span>
                    <div class="view-toggle">
                        <button type="button" class="view-btn activo" id="btnVistaTarjetas" title="Vista en tarjetas">
                            <i class="fas fa-th-large"></i> Tarjetas
                        </button>
                        <button type="button" class="view-btn" id="btnVistaTabla" title="Vista en lista">
                            <i class="fas fa-list"></i> Lista
                        </button>
                    </div>
                </div>
            </div>

            <div class="filtros-grid">
                <div class="input-icon-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscadorInput" class="form-control" placeholder="Buscar por nombre, cargo, correo o celular...">
                </div>

                <div>
                    <select id="filtroCargo" class="form-control">
                        <option value="">Todos los cargos</option>
                        <?php foreach ($cargos_unicos as $c): ?>
                            <option value="<?php echo htmlspecialchars(mb_strtolower($c)); ?>">
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" id="btnLimpiarFiltros" class="btn-limpiar">
                    <i class="fas fa-eraser"></i> Limpiar
                </button>
            </div>

            <!-- Filtro alfabético por inicial del nombre -->
            <div class="alpha-bar" id="alphaBar">
                <span class="alpha-label">Inicial:</span>
                <button type="button" class="alpha-btn activo" data-letra="">Todas</button>
                <?php
                    $letras_disponibles = [];
                    foreach ($personas_directorio as $p) {
                        $primera = mb_strtoupper(mb_substr(trim($p['nombre_completo']), 0, 1));
                        if ($primera !== '') {
                            $letras_disponibles[$primera] = true;
                        }
                    }
                    ksort($letras_disponibles, SORT_LOCALE_STRING);
                    foreach (array_keys($letras_disponibles) as $letra):
                ?>
                    <button type="button" class="alpha-btn" data-letra="<?php echo htmlspecialchars(mb_strtolower($letra)); ?>">
                        <?php echo htmlspecialchars($letra); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($error_carga !== ''): ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Ocurrió un inconveniente</h3>
                <p><?php echo htmlspecialchars($error_carga); ?></p>
            </div>
        <?php elseif (empty($personas_directorio)): ?>
            <div class="empty-state">
                <i class="fas fa-address-book"></i>
                <h3>Directorio sin contactos aún</h3>
                <p>Pronto estarán disponibles los contactos corporativos en esta sección.</p>
            </div>
        <?php else: ?>
            <!-- Vista 1: Tarjetas -->
            <div class="directorio-grid" id="vistaTarjetas">
                <?php foreach ($personas_directorio as $p): ?>
                    <?php
                        $inicial = mb_strtolower(mb_substr(trim($p['nombre_completo']), 0, 1));
                        $tieneEmail = $p['email'] !== '';
                        $tieneTel = $p['telefono'] !== '';
                        $waNumero = $tieneTel ? formatearWhatsApp($p['telefono']) : '';
                    ?>
                    <div class="persona-card item-directorio"
                         data-nombre="<?php echo htmlspecialchars(mb_strtolower($p['nombre_completo'])); ?>"
                         data-cargo="<?php echo htmlspecialchars(mb_strtolower($p['cargo'])); ?>"
                         data-correo="<?php echo htmlspecialchars(mb_strtolower($p['email'])); ?>"
                         data-celular="<?php echo htmlspecialchars(mb_strtolower($p['telefono'])); ?>"
                         data-letra="<?php echo htmlspecialchars($inicial); ?>">

                        <div class="card-top">
                            <div class="avatar-circle">
                                <?php echo htmlspecialchars(obtenerIniciales($p['nombre_completo'])); ?>
                            </div>
                            <div class="persona-identidad">
                                <h3><?php echo htmlspecialchars($p['nombre_completo']); ?></h3>
                                <span class="cargo-badge"><?php echo htmlspecialchars($p['cargo']); ?></span>
                            </div>
                        </div>

                        <div class="card-datos">
                            <div class="dato-item">
                                <div class="dato-info">
                                    <i class="fas fa-envelope"></i>
                                    <span class="dato-texto" title="<?php echo htmlspecialchars($p['email']); ?>">
                                        <?php if ($tieneEmail): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($p['email']); ?>">
                                                <?php echo htmlspecialchars($p['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#90a4ae;">Sin correo registrado</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($tieneEmail): ?>
                                    <div class="dato-acciones">
                                        <button type="button" class="mini-btn" title="Copiar correo"
                                                onclick="copiarTexto(<?php echo htmlspecialchars(json_encode($p['email']), ENT_QUOTES, 'UTF-8'); ?>, 'Correo copiado al portapapeles')">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="dato-item">
                                <div class="dato-info">
                                    <i class="fas fa-mobile-alt"></i>
                                    <span class="dato-texto">
                                        <?php if ($tieneTel): ?>
                                            <a href="tel:<?php echo htmlspecialchars($p['telefono']); ?>">
                                                <?php echo htmlspecialchars($p['telefono']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#90a4ae;">Sin celular registrado</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($tieneTel): ?>
                                    <div class="dato-acciones">
                                        <button type="button" class="mini-btn" title="Copiar celular"
                                                onclick="copiarTexto(<?php echo htmlspecialchars(json_encode($p['telefono']), ENT_QUOTES, 'UTF-8'); ?>, 'Celular copiado al portapapeles')">
                                            <i class="far fa-copy"></i>
                                        </button>
                                        <?php if ($waNumero !== ''): ?>
                                            <a href="https://wa.me/<?php echo htmlspecialchars($waNumero); ?>" target="_blank" rel="noopener" class="mini-btn whatsapp" title="Escribir por WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Vista 2: Tabla / Lista -->
            <div class="tabla-box" id="vistaTabla">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cargo</th>
                            <th>Correo</th>
                            <th>Celular</th>
                            <th style="width: 110px;">Contacto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personas_directorio as $p): ?>
                            <?php
                                $inicial = mb_strtolower(mb_substr(trim($p['nombre_completo']), 0, 1));
                                $tieneEmail = $p['email'] !== '';
                                $tieneTel = $p['telefono'] !== '';
                                $waNumero = $tieneTel ? formatearWhatsApp($p['telefono']) : '';
                            ?>
                            <tr class="fila-directorio"
                                data-nombre="<?php echo htmlspecialchars(mb_strtolower($p['nombre_completo'])); ?>"
                                data-cargo="<?php echo htmlspecialchars(mb_strtolower($p['cargo'])); ?>"
                                data-correo="<?php echo htmlspecialchars(mb_strtolower($p['email'])); ?>"
                                data-celular="<?php echo htmlspecialchars(mb_strtolower($p['telefono'])); ?>"
                                data-letra="<?php echo htmlspecialchars($inicial); ?>">
                                <td><strong><?php echo htmlspecialchars($p['nombre_completo']); ?></strong></td>
                                <td><span class="cargo-badge"><?php echo htmlspecialchars($p['cargo']); ?></span></td>
                                <td>
                                    <?php if ($tieneEmail): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($p['email']); ?>" style="color:#2c3e50; text-decoration:none;">
                                            <?php echo htmlspecialchars($p['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#90a4ae;">Sin correo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tieneTel): ?>
                                        <a href="tel:<?php echo htmlspecialchars($p['telefono']); ?>" style="color:#2c3e50; text-decoration:none;">
                                            <?php echo htmlspecialchars($p['telefono']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#90a4ae;">Sin celular</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dato-acciones">
                                        <?php if ($tieneEmail): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($p['email']); ?>" class="mini-btn" title="Enviar correo">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($waNumero !== ''): ?>
                                            <a href="https://wa.me/<?php echo htmlspecialchars($waNumero); ?>" target="_blank" rel="noopener" class="mini-btn whatsapp" title="WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mensaje cuando no hay coincidencias -->
            <div class="empty-state" id="sinResultadosBox" style="display: none;">
                <i class="fas fa-search"></i>
                <h3>Sin resultados</h3>
                <p>No encontramos colaboradores que coincidan con los filtros seleccionados.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="toast-copy" id="toastCopy"></div>

    <script>
        const buscadorInput = document.getElementById('buscadorInput');
        const filtroCargo = document.getElementById('filtroCargo');
        const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
        const alphaBtns = document.querySelectorAll('.alpha-btn');

        const tarjetas = document.querySelectorAll('.item-directorio');
        const filasTabla = document.querySelectorAll('.fila-directorio');
        const sinResultadosBox = document.getElementById('sinResultadosBox');
        const contadorResultados = document.getElementById('contadorResultados');

        const btnVistaTarjetas = document.getElementById('btnVistaTarjetas');
        const btnVistaTabla = document.getElementById('btnVistaTabla');
        const vistaTarjetas = document.getElementById('vistaTarjetas');
        const vistaTabla = document.getElementById('vistaTabla');

        let letraSeleccionada = '';
        let modoVista = 'tarjetas';

        function aplicarFiltros() {
            const texto = (buscadorInput?.value || '').toLowerCase().trim();
            const cargo = (filtroCargo?.value || '').toLowerCase().trim();
            let visibles = 0;

            const coincide = (el) => {
                const nombre = el.dataset.nombre || '';
                const cargoItem = el.dataset.cargo || '';
                const correo = el.dataset.correo || '';
                const celular = el.dataset.celular || '';
                const letra = el.dataset.letra || '';

                const matchTexto = !texto ||
                    nombre.includes(texto) ||
                    cargoItem.includes(texto) ||
                    correo.includes(texto) ||
                    celular.includes(texto);

                const matchCargo = !cargo || cargoItem === cargo;
                const matchLetra = !letraSeleccionada || letra === letraSeleccionada;

                return matchTexto && matchCargo && matchLetra;
            };

            tarjetas.forEach(card => {
                if (coincide(card)) {
                    card.style.display = '';
                    visibles++;
                } else {
                    card.style.display = 'none';
                }
            });

            filasTabla.forEach(fila => {
                fila.style.display = coincide(fila) ? '' : 'none';
            });

            if (contadorResultados) {
                contadorResultados.textContent = `${visibles} ${visibles === 1 ? 'contacto' : 'contactos'}`;
            }

            if (sinResultadosBox) {
                sinResultadosBox.style.display = (tarjetas.length > 0 && visibles === 0) ? 'block' : 'none';
            }

            if (vistaTarjetas && vistaTabla) {
                if (visibles === 0) {
                    vistaTarjetas.style.display = 'none';
                    vistaTabla.style.display = 'none';
                } else {
                    vistaTarjetas.style.display = modoVista === 'tarjetas' ? 'grid' : 'none';
                    vistaTabla.style.display = modoVista === 'tabla' ? 'block' : 'none';
                }
            }
        }

        buscadorInput?.addEventListener('input', aplicarFiltros);
        filtroCargo?.addEventListener('change', aplicarFiltros);

        alphaBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                alphaBtns.forEach(b => b.classList.remove('activo'));
                btn.classList.add('activo');
                letraSeleccionada = btn.dataset.letra || '';
                aplicarFiltros();
            });
        });

        btnLimpiarFiltros?.addEventListener('click', () => {
            if (buscadorInput) buscadorInput.value = '';
            if (filtroCargo) filtroCargo.value = '';
            letraSeleccionada = '';
            alphaBtns.forEach(b => b.classList.remove('activo'));
            document.querySelector('.alpha-btn[data-letra=""]')?.classList.add('activo');
            aplicarFiltros();
        });

        // Cambio entre vista Tarjetas y Tabla
        btnVistaTarjetas?.addEventListener('click', () => {
            modoVista = 'tarjetas';
            btnVistaTarjetas.classList.add('activo');
            btnVistaTabla.classList.remove('activo');
            aplicarFiltros();
        });

        btnVistaTabla?.addEventListener('click', () => {
            modoVista = 'tabla';
            btnVistaTabla.classList.add('activo');
            btnVistaTarjetas.classList.remove('activo');
            aplicarFiltros();
        });

        // Copiar al portapapeles con notificación
        let toastTimer;
        function copiarTexto(texto, mensaje) {
            navigator.clipboard.writeText(texto).then(() => {
                const toast = document.getElementById('toastCopy');
                toast.textContent = mensaje;
                toast.classList.add('visible');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => {
                    toast.classList.remove('visible');
                }, 2500);
            });
        }
    </script>
</body>
</html>