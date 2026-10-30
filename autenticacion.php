<?php

declare(strict_types=1);

require_once __DIR__ . '/config/conexion.php';

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('MANTIXSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function obtenerTokenCsrf(): string
{
    iniciarSesionSegura();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validarTokenCsrf(mixed $token): bool
{
    iniciarSesionSegura();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function cerrarSesionActual(): void
{
    iniciarSesionSegura();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function exigirAutenticacion(PDO $pdo): void
{
    iniciarSesionSegura();
    $usuario = $_SESSION['usuario'] ?? null;

    if (!is_array($usuario) || !isset($usuario['id'], $usuario['rol_codigo'])) {
        header('Location: login.php');
        exit;
    }

    try {
        $consulta = $pdo->prepare(
            'SELECT u.estado, r.codigo, r.nombre
             FROM usuarios AS u
             INNER JOIN roles AS r ON r.codigo = u.rol_codigo
             WHERE u.id = :id
             LIMIT 1'
        );
        $consulta->execute(['id' => $usuario['id']]);
        $registro = $consulta->fetch();
    } catch (PDOException $exception) {
        error_log('Error al validar la sesión de Mantix: ' . $exception->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "No fue posible validar la sesión en este momento.\n";
        exit;
    }

    $rolesPermitidos = ['administrador', 'responsable_mantenimiento'];

    if (
        !$registro
        || $registro['estado'] !== 'Activo'
        || !in_array($registro['codigo'], $rolesPermitidos, true)
        || $registro['codigo'] !== $usuario['rol_codigo']
    ) {
        cerrarSesionActual();
        header('Location: login.php');
        exit;
    }
}
