<?php
// includes/check_session.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'logueado' => isset($_SESSION['usuario_id']),
    'usuario' => $_SESSION['usuario'] ?? null,
    'rol' => $_SESSION['rol'] ?? null
];

echo json_encode($response);
?>