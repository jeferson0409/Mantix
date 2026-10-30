<?php

declare(strict_types=1);

require_once __DIR__ . '/autenticacion.php';
iniciarSesionSegura();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } else {
        $correoEnviado = $_POST['correo'] ?? '';
        $contrasenaEnviada = $_POST['contrasena'] ?? '';
        $correo = is_string($correoEnviado) ? strtolower(trim($correoEnviado)) : '';
        $contrasena = is_string($contrasenaEnviada) ? $contrasenaEnviada : '';

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 254 || $contrasena === '' || strlen($contrasena) > 4096) {
            $error = 'Ingresa un correo electrónico y una contraseña válidos.';
        } else {
            try {
                $consulta = $pdo->prepare(
                    'SELECT u.id, u.rol_codigo, u.nombre, u.correo, u.contrasena_hash, u.estado, r.nombre AS rol_nombre
                     FROM usuarios AS u
                     INNER JOIN roles AS r ON r.codigo = u.rol_codigo
                     WHERE u.correo = :correo
                     LIMIT 1'
                );
                $consulta->execute(['correo' => $correo]);
                $usuario = $consulta->fetch();

                if ($usuario && $usuario['estado'] === 'Activo' && password_verify($contrasena, $usuario['contrasena_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['usuario'] = [
                        'id' => (int) $usuario['id'],
                        'rol_codigo' => $usuario['rol_codigo'],
                        'rol_nombre' => $usuario['rol_nombre'],
                        'nombre' => $usuario['nombre'],
                        'correo' => $usuario['correo'],
                    ];
                    unset($_SESSION['csrf_token']);
                    header('Location: index.php');
                    exit;
                }

                $error = 'El correo o la contraseña no son correctos, o la cuenta está inactiva.';
            } catch (PDOException $exception) {
                error_log('Error al procesar el inicio de sesión de Mantix: ' . $exception->getMessage());
                http_response_code(500);
                $error = 'No fue posible iniciar sesión en este momento. Inténtalo más tarde.';
            }
        }
    }
}

$csrfToken = obtenerTokenCsrf();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#10233f">
  <title>Iniciar sesión | Mantix</title>
  <link rel="stylesheet" href="css/estilos.css">
  <script src="js/login.js" defer></script>
</head>
<body class="login-body">
  <svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="i-tools" viewBox="0 0 24 24"><path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18l3 3 5.3-5.3a5 5 0 0 0 6.4-6.4L15 12l-3-3 2.7-2.7Z"/><path d="m16 8 3-3 2 2-3 3"/></symbol>
  </svg>
  <main class="login-layout">
    <section class="login-brand-panel">
      <a class="brand brand-light" href="login.php"><span class="brand-mark"><svg class="icon"><use href="#i-tools"/></svg></span><span class="brand-copy"><strong>mantix</strong><small>GESTIÓN DE ACTIVOS</small></span></a>
      <div class="login-hero">
        <div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
        <div class="hero-symbol"><svg viewBox="0 0 140 140" aria-hidden="true"><circle cx="70" cy="70" r="53"/><path d="M85 39a24 24 0 0 0-31 31L31 93l16 16 23-23a24 24 0 0 0 31-31L85 71 69 55Z"/></svg></div>
        <p class="eyebrow">MANTENIMIENTO INTELIGENTE</p>
        <h1>Tus equipos.<br>Siempre en marcha.</h1>
        <p>Centraliza tus activos, anticipa cada mantenimiento y mantén tu operación funcionando.</p>
      </div>
      <div class="login-panel-foot">GESTIÓN CLARA. OPERACIÓN CONFIABLE.</div>
    </section>
    <section class="login-form-panel">
      <div class="login-form-wrap">
        <div class="mobile-login-brand"><span class="brand-mark"><svg class="icon"><use href="#i-tools"/></svg></span><span class="brand-copy"><strong>mantix</strong><small>GESTIÓN DE ACTIVOS</small></span></div>
        <p class="eyebrow">BIENVENIDO DE NUEVO</p>
        <h2>Inicia sesión</h2>
        <p class="login-subtitle">Ingresa tus datos para acceder a tu espacio de trabajo.</p>
        <form id="login-form" method="post" action="login.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <label class="form-field">Correo electrónico<input type="email" name="correo" id="login-user" autocomplete="username" placeholder="admin@empresa.com" maxlength="254" required></label>
          <label class="form-field">Contraseña<div class="password-field"><input type="password" name="contrasena" id="login-password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required><button type="button" id="toggle-password" aria-label="Mostrar contraseña">Mostrar</button></div></label>
          <p class="login-error" id="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          <button type="submit" class="button button-primary login-submit">Ingresar al sistema <span>→</span></button>
        </form>
        <p class="login-legal">Acceso protegido para usuarios registrados en Mantix.</p>
      </div>
    </section>
  </main>
</body>
</html>
