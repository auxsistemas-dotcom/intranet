<?php
// documentacion/documentos_sig/index.php - Página pública de Documentos SIG
require_once '../../includes/config.php';

// No requiere login - Página pública

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_nombre = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : null;
$rol_usuario    = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;

// ============================================
// OBTENER DOCUMENTOS ACTIVOS (ordenados por categoría y título)
// ============================================
$stmt = $pdo->prepare("
    SELECT * FROM documentos_sig 
    WHERE activo = 1 
    ORDER BY categoria ASC, titulo ASC
");
$stmt->execute();
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// CATEGORÍAS ÚNICAS PARA EL FILTRO
// ============================================
$categorias_disponibles = [];
foreach ($documentos as $doc) {
    if (!empty($doc['categoria'])) {
        $categorias_disponibles[$doc['categoria']] = $doc['categoria'];
    }
}
ksort($categorias_disponibles);

// ============================================
// FUNCIÓN: info según extensión
// ============================================
function getFileInfo($archivo_url) {
    $extension = strtolower(pathinfo($archivo_url, PATHINFO_EXTENSION));

    switch ($extension) {
        case 'pdf':
            return [
                'icono'  => 'fa-file-pdf',
                'color'  => '#dc2626',
                'boton'  => 'Ver PDF',
                'clase'  => 'pdf',
                'abrir'  => true   // ✅ se abre en el navegador
            ];
        case 'doc':
        case 'docx':
            return [
                'icono'  => 'fa-file-word',
                'color'  => '#2980b9',
                'boton'  => 'Descargar Word',
                'clase'  => 'word',
                'abrir'  => false
            ];
        case 'xls':
        case 'xlsx':
            return [
                'icono'  => 'fa-file-excel',
                'color'  => '#1d6f42',
                'boton'  => 'Descargar Excel',
                'clase'  => 'excel',
                'abrir'  => false
            ];
        default:
            return [
                'icono'  => 'fa-file-alt',
                'color'  => '#7f8c8d',
                'boton'  => 'Descargar',
                'clase'  => 'other',
                'abrir'  => false
            ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos SIG | INTRANET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .documentos-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .welcome-header {
            background: linear-gradient(135deg, #173742, #12232b);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            color: white;
            text-align: center;
        }

        .welcome-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-header h1 i {
            margin-right: 15px;
            color: #ffc107;
        }

        .welcome-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .user-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 50px;
            margin-top: 15px;
            font-size: 14px;
        }

        /* ========== BARRA DE FILTROS ========== */
        .filtros-bar {
            background: white;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filtro-buscador {
            flex: 2;
            min-width: 220px;
            position: relative;
        }

        .filtro-buscador i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 14px;
            pointer-events: none;
        }

        .filtro-buscador input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #2c3e50;
            transition: all 0.25s ease;
        }

        .filtro-buscador input:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }

        .filtro-categoria {
            flex: 1;
            min-width: 200px;
        }

        .filtro-categoria select {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #2c3e50;
            background: white;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .filtro-categoria select:focus {
            outline: none;
            border-color: #173742;
            box-shadow: 0 0 0 3px rgba(23,55,66,0.1);
        }

        .btn-limpiar-filtros {
            background: #ecf0f1;
            color: #2c3e50;
            border: none;
            padding: 11px 18px;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.25s ease;
        }

        .btn-limpiar-filtros:hover {
            background: #e74c3c;
            color: white;
        }

        .contador-resultados {
            width: 100%;
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .contador-resultados strong {
            color: #173742;
        }

        /* ========== LISTA DOCUMENTOS ========== */
        .documentos-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .documento-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            flex-wrap: wrap;
        }

        .documento-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }

        .documento-item.oculto {
            display: none;
        }

        .documento-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .documento-icon.pdf   { background: #fee2e2; color: #dc2626; }
        .documento-icon.word  { background: #dbeafe; color: #2980b9; }
        .documento-icon.excel { background: #d4edda; color: #1d6f42; }
        .documento-icon.other { background: #f0f0f0; color: #7f8c8d; }

        .documento-info {
            flex: 1;
            min-width: 200px;
        }

        .documento-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .documento-info p {
            font-size: 14px;
            color: #7f8c8d;
        }

        .documento-info .badge-categoria {
            display: inline-block;
            background: #e8f0fe;
            color: #173742;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 12px;
            font-weight: 600;
            margin-top: 6px;
        }

        .btn-descargar {
            padding: 8px 20px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-descargar:hover {
            transform: translateY(-2px);
        }

        .btn-descargar.pdf   { background: #dc2626; }
        .btn-descargar.pdf:hover { background: #b91c1c; }
        .btn-descargar.word  { background: #2980b9; }
        .btn-descargar.word:hover { background: #1f6392; }
        .btn-descargar.excel { background: #1d6f42; }
        .btn-descargar.excel:hover { background: #145c34; }
        .btn-descargar.other { background: #7f8c8d; }
        .btn-descargar.other:hover { background: #5a6a72; }

        /* ========== EMPTY STATES ========== */
        .sin-documentos {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .sin-documentos i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #d3d3d3;
        }

        .sin-documentos h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .btn-volver-doc {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #445960;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .btn-volver-doc:hover {
            background: #ffc107;
            color: #12232b;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <div class="logo-container">
            <div>
                <img src="../../uploads/logo/30-años-blanco.webp" alt="logo intranet" style="display: flex; width: 130px;">
                <p class="logo-subtitle" style="color: #8aa8b8; font-size: 14px;">| Plataforma Corporativa</p>
            </div>
        </div>
        <div class="header-actions">
            <div class="welcome-message">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <i class="fas fa-user-check"></i>
                    <span>Bienvenido, <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong></span>
                    <?php if ($rol_usuario === 'admin'): ?>
                        <a href="../../admin/index.php" style="
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
                            <i class="fas fa-cog"></i> Panel Administrador
                        </a>
                    <?php endif; ?>
                    <a href="../../logout.php" style="
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
                <?php else: ?>
                    <i class="fas fa-user"></i>
                    <span>Bienvenido, <strong>Visitante</strong></span>
                    <a href="../../login.php" class="login-link">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main>
    <div class="documentos-container">
        <a href="../../index.php" class="btn-volver-doc">
            <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>

        <div class="welcome-header">
            <h1><i class="fas fa-file-alt"></i> Documentos SIG</h1>
            <p>Documentos del Sistema de Gestión</p>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <div class="user-badge">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($usuario_nombre); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($documentos)): ?>
            <div class="sin-documentos">
                <i class="fas fa-file-alt"></i>
                <h3>No hay documentos disponibles</h3>
                <p>Contacta al administrador para más información.</p>
            </div>
        <?php else: ?>

            <!-- ========== BARRA DE FILTROS ========== -->
            <div class="filtros-bar">
                <div class="filtro-buscador">
                    <i class="fas fa-search"></i>
                    <input type="text"
                           id="buscador"
                           placeholder="Buscar por nombre de archivo..."
                           autocomplete="off">
                </div>

                <div class="filtro-categoria">
                    <select id="filtroCategoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias_disponibles as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" class="btn-limpiar-filtros" id="btnLimpiar">
                    <i class="fas fa-times"></i> Limpiar
                </button>

                <div class="contador-resultados" id="contadorResultados">
                    Mostrando <strong><?php echo count($documentos); ?></strong> documento(s)
                </div>
            </div>

            <!-- ========== LISTA DE DOCUMENTOS ========== -->
            <div class="documentos-grid" id="listaDocumentos">
                <?php foreach ($documentos as $doc): ?>
                    <?php
                    $info = getFileInfo($doc['archivo_url']);
                    $ruta = '../../' . $doc['archivo_url'];
                    $titulo_lower   = mb_strtolower($doc['titulo'], 'UTF-8');
                    $categoria_lower = mb_strtolower($doc['categoria'] ?? '', 'UTF-8');
                    ?>
                    <div class="documento-item"
                         data-titulo="<?php echo htmlspecialchars($titulo_lower); ?>"
                         data-categoria="<?php echo htmlspecialchars($doc['categoria'] ?? ''); ?>">

                        <div class="documento-icon <?php echo $info['clase']; ?>">
                            <i class="fas <?php echo $info['icono']; ?>"></i>
                        </div>

                        <div class="documento-info">
                            <h3><?php echo htmlspecialchars($doc['titulo']); ?></h3>
                            <?php if (!empty($doc['descripcion'])): ?>
                                <p><?php echo htmlspecialchars($doc['descripcion']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($doc['categoria'])): ?>
                                <span class="badge-categoria">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($doc['categoria']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <a href="<?php echo $ruta; ?>"
                           class="btn-descargar <?php echo $info['clase']; ?>"
                           <?php if ($info['abrir']): ?>
                               target="_blank" rel="noopener"
                           <?php else: ?>
                               download
                           <?php endif; ?>>
                            <i class="fas <?php echo $info['abrir'] ? 'fa-external-link-alt' : 'fa-download'; ?>"></i>
                            <?php echo $info['boton']; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty state para cuando el filtro no encuentra nada -->
            <div class="sin-documentos" id="sinResultados" style="display:none;">
                <i class="fas fa-search"></i>
                <h3>No se encontraron documentos</h3>
                <p>Prueba con otro nombre o cambia la categoría.</p>
            </div>

        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscador        = document.getElementById('buscador');
    const filtroCategoria = document.getElementById('filtroCategoria');
    const btnLimpiar      = document.getElementById('btnLimpiar');
    const contador        = document.getElementById('contadorResultados');
    const lista           = document.getElementById('listaDocumentos');
    const sinResultados   = document.getElementById('sinResultados');

    if (!lista) return; // si no hay documentos, no hay nada que filtrar

    const items = lista.querySelectorAll('.documento-item');

    function aplicarFiltros() {
        const textoBusqueda    = (buscador?.value || '').trim().toLowerCase();
        const categoriaSelecc  = filtroCategoria?.value || '';
        let visibles = 0;

        items.forEach(item => {
            const titulo    = item.dataset.titulo || '';
            const categoria = item.dataset.categoria || '';

            const coincideTexto     = textoBusqueda === '' || titulo.includes(textoBusqueda);
            const coincideCategoria = categoriaSelecc === '' || categoria === categoriaSelecc;

            if (coincideTexto && coincideCategoria) {
                item.classList.remove('oculto');
                visibles++;
            } else {
                item.classList.add('oculto');
            }
        });

        if (contador) {
            contador.innerHTML = `Mostrando <strong>${visibles}</strong> documento(s)`;
        }

        if (sinResultados) {
            sinResultados.style.display = visibles === 0 ? 'block' : 'none';
        }
    }

    if (buscador)        buscador.addEventListener('input', aplicarFiltros);
    if (filtroCategoria) filtroCategoria.addEventListener('change', aplicarFiltros);

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function() {
            if (buscador)        buscador.value = '';
            if (filtroCategoria) filtroCategoria.value = '';
            aplicarFiltros();
            if (buscador) buscador.focus();
        });
    }
});
</script>

</body>
</html>