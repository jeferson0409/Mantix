<?php

declare(strict_types=1);

require_once __DIR__ . '/autenticacion.php';
iniciarSesionSegura();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "La solicitud no es válida. Recarga el panel e inténtalo nuevamente.\n";
    exit;
}

cerrarSesionActual();
header('Location: login.php');
exit;
