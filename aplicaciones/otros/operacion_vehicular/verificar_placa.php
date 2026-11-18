<?php
// aplicaciones/otros/operacion_vehicular/verificar_placa.php
// Endpoint AJAX para autocompletado y validación de placas

require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

// ✅ VERIFICAR ACCESO
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada']);
    exit();
}

// ============================================
// ✅ SANITIZAR BÚSQUEDA
// ============================================
$busqueda = strtoupper(trim($_GET['placa'] ?? ''));
$busqueda = preg_replace('/[^A-Z0-9]/', '', $busqueda);

// ============================================
// ✅ SANITIZAR MEDIO DE TRANSPORTE
// ============================================
$medio = strtoupper(trim($_GET['medio'] ?? ''));

// ============================================
// ✅ MAPEO: medio → tipos + modalidades permitidas
// ============================================
$mapa_medio_filtros = [
    'PROPIO' => [
        'tipos' => ['CARRO'],
        'modalidades' => ['PROPIO']
    ],
    'ORGANIZACION' => [
        'tipos' => ['CARRO'],
        'modalidades' => ['NUEVO', 'TEST_DRIVE', 'USADO']
    ],
    'MOTO' => [
        'tipos' => ['MOTO'],
        'modalidades' => []
    ],
    'NODRIZA' => [
        'tipos' => ['CAMION'],
        'modalidades' => []
    ]
];

$filtro = $mapa_medio_filtros[$medio] ?? [
    'tipos' => [],
    'modalidades' => []
];

$tipos_filtrados = $filtro['tipos'];
$modalidades_filtradas = $filtro['modalidades'];

// ============================================
// ✅ VALIDACIÓN INICIAL
// ============================================
if (empty($busqueda) || strlen($busqueda) < 2) {
    echo json_encode([
        'ok' => true,
        'sugerencias' => [],
        'existe_exacta' => false,
        'disponible_exacta' => false,
        'tipos_filtrados' => $tipos_filtrados,
        'modalidades_filtradas' => $modalidades_filtradas
    ]);
    exit();
}

try {
    // ============================================
    // CONSTRUIR WHERE DINÁMICO CON TIPO + MODALIDAD
    // ============================================
    $where_filtros = '';
    $params_filtros = [];
    
    if (!empty($tipos_filtrados)) {
        $placeholders = implode(',', array_fill(0, count($tipos_filtrados), '?'));
        $where_filtros .= " AND v.tipo_vehiculo IN ($placeholders)";
        $params_filtros = array_merge($params_filtros, $tipos_filtrados);
    }
    
    if (!empty($modalidades_filtradas)) {
        $placeholders = implode(',', array_fill(0, count($modalidades_filtradas), '?'));
        $where_filtros .= " AND v.modalidad_vehiculo IN ($placeholders)";
        $params_filtros = array_merge($params_filtros, $modalidades_filtradas);
    }
    
    // ============================================
    // BUSCAR COINCIDENCIAS
    // ============================================
    $like = '%' . $busqueda . '%';
    $stmt = $pdo->prepare("
        SELECT v.id, v.placa, v.marca, v.modelo, v.referencia, v.activo, 
               v.tipo_vehiculo, v.modalidad_vehiculo, v.kilometraje,
               v.fecha_ultimo_mantenimiento
        FROM ope_vehiculos v
        WHERE (
            UPPER(v.placa) LIKE ? 
            OR UPPER(v.marca) LIKE ? 
            OR UPPER(v.modelo) LIKE ? 
            OR UPPER(v.referencia) LIKE ?
        )
        $where_filtros
        ORDER BY 
            CASE WHEN UPPER(v.placa) = ? THEN 0 ELSE 1 END,
            CASE WHEN UPPER(v.placa) LIKE ? THEN 0 ELSE 1 END,
            v.placa ASC
        LIMIT 8
    ");
    
    $params = array_merge(
        [$like, $like, $like, $like],
        $params_filtros,
        [$busqueda, $busqueda . '%']
    );
    
    $stmt->execute($params);
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR ESTADO DE CADA SUGERENCIA
    // ============================================
    $hoy = new DateTime();
    $hoy->setTime(0, 0, 0);
    
    $sugerencias = [];
    $existe_exacta = false;
    $disponible_exacta = false;

    foreach ($vehiculos as $v) {
        $puede_usar = true;
        $motivo = null;
        $estado_visual = 'disponible';

        if (!$v['activo']) {
            $puede_usar = false;
            $motivo = 'Inactivo';
            $estado_visual = 'inactivo';
        } else {
            // Verificar ruta en curso
            $stmt2 = $pdo->prepare("
                SELECT COUNT(*) FROM ope_ruta 
                WHERE vehiculo_id = ? AND estado = 'EN_CURSO'
            ");
            $stmt2->execute([$v['id']]);
            $en_ruta = $stmt2->fetchColumn() > 0;
            
            if ($en_ruta) {
                $puede_usar = false;
                $motivo = 'En movilización';
                $estado_visual = 'en_ruta';
            } else {
                // Verificar documentos vencidos
                $stmt2 = $pdo->prepare("
                    SELECT tipo_documento, fecha_vencimiento 
                    FROM ope_documentos_vh 
                    WHERE vehiculo_id = ? AND activo = 1
                ");
                $stmt2->execute([$v['id']]);
                $docs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                $docs_vencidos = [];
                foreach ($docs as $doc) {
                    $vence = new DateTime($doc['fecha_vencimiento']);
                    $vence->setTime(0, 0, 0);
                    if ($vence < $hoy) {
                        $docs_vencidos[] = $doc['tipo_documento'];
                    }
                }
                
                if (!empty($docs_vencidos)) {
                    $puede_usar = false;
                    $motivo = 'Docs vencidos: ' . implode(', ', $docs_vencidos);
                    $estado_visual = 'docs_vencidos';
                } else {
                    // ✅ NUEVO: Verificar mantenimiento vigente (cada 6 meses)
                    $fecha_mant = $v['fecha_ultimo_mantenimiento'] ?? null;
                    
                    if (empty($fecha_mant)) {
                        $puede_usar = false;
                        $motivo = 'Sin mantenimiento registrado';
                        $estado_visual = 'mantenimiento_vencido';
                    } else {
                        $proximo_mant = new DateTime($fecha_mant);
                        $proximo_mant->modify('+6 months');
                        $proximo_mant->setTime(0, 0, 0);
                        
                        if ($proximo_mant < $hoy) {
                            $dias_vencido = (int) $proximo_mant->diff($hoy)->days;
                            $puede_usar = false;
                            $motivo = 'Mantenimiento vencido hace ' . $dias_vencido . ' días';
                            $estado_visual = 'mantenimiento_vencido';
                        }
                    }
                }
            }
        }

        // Verificar si la placa coincide exactamente
        if ($v['placa'] === $busqueda) {
            $existe_exacta = true;
            $disponible_exacta = $puede_usar;
        }

        $sugerencias[] = [
            'id' => (int)$v['id'],
            'placa' => $v['placa'],
            'marca' => $v['marca'],
            'modelo' => $v['modelo'],
            'referencia' => $v['referencia'],
            'tipo_vehiculo' => $v['tipo_vehiculo'],
            'modalidad_vehiculo' => $v['modalidad_vehiculo'],
            'kilometraje' => (int)$v['kilometraje'],
            'fecha_ultimo_mantenimiento' => $v['fecha_ultimo_mantenimiento'],
            'puede_usar' => $puede_usar,
            'motivo' => $motivo,
            'estado_visual' => $estado_visual
        ];
    }

    echo json_encode([
        'ok' => true,
        'sugerencias' => $sugerencias,
        'existe_exacta' => $existe_exacta,
        'disponible_exacta' => $disponible_exacta,
        'tipos_filtrados' => $tipos_filtrados,
        'modalidades_filtradas' => $modalidades_filtradas,
        'medio' => $medio
    ]);

} catch (PDOException $e) {
    error_log("Error verificando placa: " . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Error al verificar la placa',
        'sugerencias' => [],
        'existe_exacta' => false,
        'disponible_exacta' => false
    ]);
}