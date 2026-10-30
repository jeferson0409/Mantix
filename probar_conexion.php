<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

try {
    require_once __DIR__ . '/autenticacion.php';
    iniciarSesionSegura();
    exigirAutenticacion($pdo);

    if (($_SESSION['usuario']['rol_codigo'] ?? null) !== 'administrador') {
        http_response_code(403);
        echo "No tienes permisos para comprobar la conexión.\n";
        exit;
    }

    echo "Conexión a la base de datos exitosa.\n";
} catch (PDOException $exception) {
    error_log('Error al conectar Mantix con MySQL: ' . $exception->getMessage());
    http_response_code(500);
    echo "No fue posible conectar con la base de datos. Revisa la configuración del servidor.\n";
}
