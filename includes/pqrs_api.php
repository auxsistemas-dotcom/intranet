<?php
// includes/pqrs_api.php
// Helper para consumir la API de Google Apps Script

require_once __DIR__ . '/pqrs_config.php';

/**
 * Llama a la API de PQRS y devuelve el array de PQRS.
 * 
 * @param string|null $desde Fecha ISO opcional para filtrar desde (ej: '2026-09-01')
 * @param int|null $limit Límite opcional de filas
 * @return array ['status' => 'ok|error', 'total' => int, 'pqrs' => array, 'mensaje' => string]
 */
function obtenerPQRSDesdeAPI($desde = null, $limit = null) {
    global $PQRS_API_URL, $PQRS_API_TOKEN;
    
    // Construir URL con parámetros
    $params = ['token' => $PQRS_API_TOKEN];
    if ($desde) $params['desde'] = $desde;
    if ($limit) $params['limit'] = $limit;
    
    $url = $PQRS_API_URL . '?' . http_build_query($params);
    
    // Hacer la petición (cURL si está disponible, sino file_get_contents)
    $response = null;
    $http_code = 0;
    $error_conexion = '';
    
    if (function_exists('curl_init')) {
        // ============================================
        // MÉTODO 1: cURL (recomendado)
        // ============================================
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Intranet-Armotor-PQRS/1.0');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error_conexion = curl_error($ch);
        curl_close($ch);
        
        if ($error_conexion) {
            return [
                'status' => 'error',
                'mensaje' => 'Error de conexión (cURL): ' . $error_conexion,
                'pqrs' => []
            ];
        }
        
    } elseif (ini_get('allow_url_fopen')) {
        // ============================================
        // MÉTODO 2: file_get_contents (fallback)
        // ============================================
        $contexto = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'user_agent' => 'Intranet-Armotor-PQRS/1.0',
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        
        $response = @file_get_contents($url, false, $contexto);
        
        if ($response === false) {
            return [
                'status' => 'error',
                'mensaje' => 'Error de conexión (file_get_contents)',
                'pqrs' => []
            ];
        }
        
        // Extraer código HTTP del response
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $matches);
            $http_code = isset($matches[1]) ? (int)$matches[1] : 200;
        }
        
    } else {
        return [
            'status' => 'error',
            'mensaje' => 'Ni cURL ni allow_url_fopen están disponibles',
            'pqrs' => []
        ];
    }
    
    // Validar código HTTP
    if ($http_code !== 0 && $http_code !== 200) {
        return [
            'status' => 'error',
            'mensaje' => "HTTP $http_code",
            'pqrs' => []
        ];
    }
    
    // Decodificar JSON
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => 'error',
            'mensaje' => 'JSON inválido: ' . json_last_error_msg(),
            'pqrs' => []
        ];
    }
    
    // Validar que sea un array
    if (!is_array($data)) {
        return [
            'status' => 'error',
            'mensaje' => 'Respuesta inesperada de la API',
            'pqrs' => []
        ];
    }
    
    return $data;
}