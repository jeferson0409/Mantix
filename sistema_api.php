<?php

declare(strict_types=1);

require_once __DIR__ . '/autenticacion.php';
iniciarSesionSegura();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function responderSistema(int $estado, array $respuesta): never
{
    http_response_code($estado);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function usuarioSistema(PDO $pdo): array
{
    $sesion = $_SESSION['usuario'] ?? null;
    if (!is_array($sesion) || !isset($sesion['id'], $sesion['rol_codigo'])) {
        responderSistema(401, ['error' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
    }

    $consulta = $pdo->prepare(
        'SELECT u.id, u.estado, r.codigo AS rol_codigo, r.nombre AS rol_nombre
         FROM usuarios AS u
         INNER JOIN roles AS r ON r.codigo = u.rol_codigo
         WHERE u.id = :id
         LIMIT 1'
    );
    $consulta->execute(['id' => $sesion['id']]);
    $usuario = $consulta->fetch();
    if (
        !$usuario
        || $usuario['estado'] !== 'Activo'
        || $usuario['rol_codigo'] !== $sesion['rol_codigo']
        || !in_array($usuario['rol_codigo'], ['administrador', 'responsable_mantenimiento'], true)
    ) {
        cerrarSesionActual();
        responderSistema(401, ['error' => 'Tu sesión ya no es válida. Inicia sesión nuevamente.']);
    }

    return $usuario;
}

function exigirAdministradorSistema(array $usuario): void
{
    if ($usuario['rol_codigo'] !== 'administrador') {
        responderSistema(403, ['error' => 'No tienes permisos para administrar usuarios.']);
    }
}

function cuerpoSistema(): array
{
    $contenido = file_get_contents('php://input');
    $entrada = json_decode($contenido === false ? '' : $contenido, true);
    if (!is_array($entrada)) {
        responderSistema(400, ['error' => 'La solicitud no contiene datos válidos.']);
    }

    return $entrada;
}

function idSistema(mixed $valor): ?int
{
    if (!is_int($valor) && (!is_string($valor) || !preg_match('/^[1-9][0-9]*$/D', $valor))) {
        return null;
    }
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function fechaValidaSistema(string $fecha): bool
{
    $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $valor !== false && $valor->format('Y-m-d') === $fecha;
}

function datosMantenimiento(array $entrada, PDO $pdo, bool $edicion, array $actual = []): array
{
    if ($edicion && ($actual['estado'] ?? '') === 'Completado') {
        responderSistema(409, ['error' => 'Un mantenimiento completado es histórico y no se puede editar.']);
    }

    $equipoId = idSistema($entrada['equipo_id'] ?? null);
    $tipo = $entrada['tipo_codigo'] ?? null;
    $responsableId = idSistema($entrada['responsable_usuario_id'] ?? null);
    $fecha = $entrada['fecha_programada'] ?? null;
    $descripcion = $entrada['descripcion'] ?? null;
    $observaciones = $entrada['observaciones'] ?? '';

    if ($equipoId === null || !is_string($tipo) || !is_string($fecha) || !is_string($descripcion) || !is_string($observaciones)) {
        responderSistema(422, ['error' => 'Completa los datos obligatorios del mantenimiento.']);
    }
    if (!fechaValidaSistema($fecha)) {
        responderSistema(422, ['error' => 'La fecha programada no es válida.']);
    }
    $descripcion = trim($descripcion);
    $observaciones = trim($observaciones);
    if ($descripcion === '' || mb_strlen($descripcion, 'UTF-8') > 65535 || mb_strlen($observaciones, 'UTF-8') > 65535) {
        responderSistema(422, ['error' => 'La descripción es obligatoria y las observaciones no pueden superar el límite permitido.']);
    }

    $equipoConsulta = $pdo->prepare('SELECT id, codigo, nombre FROM equipos WHERE id = :id LIMIT 1');
    $equipoConsulta->execute(['id' => $equipoId]);
    $equipo = $equipoConsulta->fetch();
    if (!$equipo) {
        responderSistema(422, ['error' => 'El equipo seleccionado no existe.']);
    }

    $tipoConsulta = $pdo->prepare('SELECT codigo FROM tipos_mantenimiento WHERE codigo = :codigo LIMIT 1');
    $tipoConsulta->execute(['codigo' => $tipo]);
    if (!$tipoConsulta->fetch()) {
        responderSistema(422, ['error' => 'Selecciona un tipo de mantenimiento válido.']);
    }

    if ($responsableId === null) {
        responderSistema(422, ['error' => 'Selecciona un responsable activo.']);
    }
    $responsableConsulta = $pdo->prepare(
        "SELECT id, nombre FROM usuarios WHERE id = :id AND estado = 'Activo' LIMIT 1"
    );
    $responsableConsulta->execute(['id' => $responsableId]);
    $responsable = $responsableConsulta->fetch();
    if (!$responsable) {
        responderSistema(422, ['error' => 'El responsable seleccionado no existe o está inactivo.']);
    }

    $estado = $fecha < date('Y-m-d') ? 'Vencido' : 'Pendiente';
    $completada = null;
    if (isset($entrada['estado']) && $entrada['estado'] === 'Completado') {
        $estado = 'Completado';
        $completadaEntrada = $entrada['fecha_completada'] ?? date('Y-m-d');
        if (!is_string($completadaEntrada) || !fechaValidaSistema($completadaEntrada)) {
            responderSistema(422, ['error' => 'La fecha de finalización no es válida.']);
        }
        $completada = $completadaEntrada;
    }

    return [
        'equipo_id' => $equipoId,
        'tipo_codigo' => $tipo,
        'responsable_usuario_id' => $responsableId,
        'equipo_codigo_snapshot' => $equipo['codigo'],
        'equipo_nombre_snapshot' => $equipo['nombre'],
        'responsable_nombre_snapshot' => $responsable['nombre'],
        'fecha_programada' => $fecha,
        'fecha_completada' => $completada,
        'descripcion' => $descripcion,
        'observaciones' => $observaciones === '' ? null : $observaciones,
        'estado' => $estado,
    ];
}

function listarSistema(PDO $pdo, bool $esAdministrador): array
{
    $equipos = array_map(
        static fn (array $row): array => [
            'id' => (string) $row['id'],
            'code' => $row['codigo'],
            'name' => $row['nombre'],
            'category' => $row['categoria'],
            'brand' => $row['marca'],
            'model' => $row['modelo'],
            'location' => $row['ubicacion'],
            'status' => $row['estado'],
        ],
        $pdo->query('SELECT id,codigo,nombre,categoria,marca,modelo,ubicacion,estado FROM equipos ORDER BY nombre,codigo')->fetchAll()
    );
    $mantenimientos = array_map(
        static fn (array $row): array => [
            'id' => (string) $row['id'],
            'equipmentId' => $row['equipo_id'] === null ? null : (string) $row['equipo_id'],
            'equipmentCode' => $row['equipo_codigo_snapshot'],
            'equipmentName' => $row['equipo_nombre_snapshot'],
            'typeCode' => $row['tipo_codigo'],
            'type' => $row['tipo_nombre'],
            'date' => $row['fecha_programada'],
            'description' => $row['descripcion'],
            'observations' => $row['observaciones'] ?? '',
            'responsibleId' => $row['responsable_usuario_id'] === null ? null : (string) $row['responsable_usuario_id'],
            'responsible' => $row['responsable_nombre_snapshot'],
            'status' => $row['estado'] === 'Completado' ? 'Completado' : ($row['fecha_programada'] < date('Y-m-d') ? 'Vencido' : 'Pendiente'),
            'completedDate' => $row['fecha_completada'] ?? '',
        ],
        $pdo->query(
            'SELECT m.id,m.equipo_id,m.tipo_codigo,t.nombre AS tipo_nombre,
                    m.responsable_usuario_id,m.equipo_codigo_snapshot,m.equipo_nombre_snapshot,
                    m.responsable_nombre_snapshot,m.fecha_programada,m.fecha_completada,
                    m.descripcion,m.observaciones,m.estado
             FROM mantenimientos AS m
             INNER JOIN tipos_mantenimiento AS t ON t.codigo=m.tipo_codigo
             ORDER BY m.fecha_programada DESC,m.id DESC'
        )->fetchAll()
    );
    $consultaUsuarios = $esAdministrador
        ? 'SELECT u.id,u.nombre,u.correo,u.estado,r.codigo AS rol_codigo,r.nombre AS rol_nombre
           FROM usuarios AS u INNER JOIN roles AS r ON r.codigo=u.rol_codigo
           ORDER BY u.nombre,u.correo'
        : "SELECT u.id,u.nombre,NULL AS correo,'Activo' AS estado,NULL AS rol_codigo,NULL AS rol_nombre
           FROM usuarios AS u WHERE u.estado='Activo' ORDER BY u.nombre";
    $usuarios = array_map(
        static fn (array $row): array => [
            'id' => (string) $row['id'],
            'name' => $row['nombre'],
            'email' => $row['correo'],
            'role' => $row['rol_nombre'],
            'roleCode' => $row['rol_codigo'],
            'status' => $row['estado'],
        ],
        $pdo->query($consultaUsuarios)->fetchAll()
    );
    $tipos = $pdo->query('SELECT codigo,nombre FROM tipos_mantenimiento ORDER BY nombre')->fetchAll();

    return [
        'equipos' => $equipos,
        'mantenimientos' => $mantenimientos,
        'usuarios' => $usuarios,
        'tipos_mantenimiento' => $tipos,
    ];
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $usuarioActual = usuarioSistema($pdo);
    if ($metodo === 'GET') {
        responderSistema(200, listarSistema($pdo, $usuarioActual['rol_codigo'] === 'administrador'));
    }

    if (!in_array($metodo, ['POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        responderSistema(405, ['error' => 'Método no permitido.']);
    }
    if (!validarTokenCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        responderSistema(403, ['error' => 'La solicitud expiró o no es válida. Recarga la página e inténtalo nuevamente.']);
    }

    $entrada = cuerpoSistema();
    $recurso = $entrada['recurso'] ?? null;

    if ($recurso === 'migracion_localstorage' && $metodo === 'POST') {
        $usuariosLegados = $entrada['usuarios'] ?? [];
        $mantenimientosLegados = $entrada['mantenimientos'] ?? [];
        $equiposMapeados = $entrada['equipos_map'] ?? [];
        if (
            !is_array($usuariosLegados)
            || !is_array($mantenimientosLegados)
            || !is_array($equiposMapeados)
            || count($usuariosLegados) > 200
            || count($mantenimientosLegados) > 1000
        ) {
            responderSistema(422, ['error' => 'Los datos locales exceden el límite permitido o no son válidos.']);
        }

        $pdo->beginTransaction();
        $mapaUsuarios = [];
        $usuariosImportados = 0;
        $buscarUsuario = $pdo->prepare('SELECT id FROM usuarios WHERE correo=:correo LIMIT 1');
        $insertarUsuario = $pdo->prepare(
            'INSERT INTO usuarios (rol_codigo,nombre,correo,contrasena_hash,estado)
             VALUES (:rol,:nombre,:correo,:hash,:estado)'
        );
        foreach ($usuariosLegados as $legado) {
            if (!is_array($legado)) responderSistema(422, ['error' => 'Uno de los usuarios locales no tiene un formato válido.']);
            $legacyId = $legado['id'] ?? null;
            $nombre = $legado['name'] ?? null;
            $correo = $legado['email'] ?? null;
            $rolLegado = $legado['role'] ?? '';
            if (
                (!is_string($legacyId) && !is_int($legacyId))
                || trim((string) $legacyId) === ''
                || !is_string($nombre)
                || trim($nombre) === ''
                || mb_strlen(trim($nombre), 'UTF-8') > 150
                || !is_string($correo)
                || !filter_var(strtolower(trim($correo)), FILTER_VALIDATE_EMAIL)
                || strlen(trim($correo)) > 254
            ) {
                responderSistema(422, ['error' => 'Uno de los usuarios locales no supera la validación.']);
            }
            $correo = strtolower(trim($correo));
            $rol = $rolLegado === 'Administrador' ? 'administrador' : 'responsable_mantenimiento';
            $buscarUsuario->execute(['correo' => $correo]);
            $usuarioId = $buscarUsuario->fetchColumn();
            if ($usuarioId === false) {
                $insertarUsuario->execute([
                    'rol' => $rol,
                    'nombre' => trim($nombre),
                    'correo' => $correo,
                    'hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'estado' => 'Inactivo',
                ]);
                $usuarioId = $pdo->lastInsertId();
                $usuariosImportados++;
            }
            $mapaUsuarios[(string) $legacyId] = (string) $usuarioId;
        }

        $buscarEquipo = $pdo->prepare('SELECT id,codigo,nombre FROM equipos WHERE codigo=:codigo LIMIT 1');
        $buscarEquipoId = $pdo->prepare('SELECT id,codigo,nombre FROM equipos WHERE id=:id LIMIT 1');
        $buscarResponsable = $pdo->prepare('SELECT id,nombre FROM usuarios WHERE id=:id LIMIT 1');
        $buscarMantenimiento = $pdo->prepare(
            'SELECT id FROM mantenimientos
             WHERE equipo_codigo_snapshot=:codigo AND equipo_nombre_snapshot=:equipo
               AND tipo_codigo=:tipo AND responsable_nombre_snapshot=:responsable
               AND fecha_programada=:fecha AND descripcion=:descripcion
               AND COALESCE(observaciones, \'\')=:observaciones
               AND COALESCE(fecha_completada, \'\')=:completada
             LIMIT 1'
        );
        $insertarMantenimiento = $pdo->prepare(
            'INSERT INTO mantenimientos
             (equipo_id,tipo_codigo,responsable_usuario_id,equipo_codigo_snapshot,equipo_nombre_snapshot,
              responsable_nombre_snapshot,fecha_programada,fecha_completada,descripcion,observaciones,estado)
             VALUES (:equipo_id,:tipo_codigo,:responsable_id,:equipo_codigo,:equipo_nombre,
                     :responsable_nombre,:fecha,:completada,:descripcion,:observaciones,:estado)'
        );
        $mantenimientosImportados = 0;
        foreach ($mantenimientosLegados as $legado) {
            if (!is_array($legado)) responderSistema(422, ['error' => 'Uno de los mantenimientos locales no tiene un formato válido.']);
            $tipoNombre = $legado['type'] ?? null;
            $tipo = $tipoNombre === 'Correctivo' ? 'correctivo' : 'preventivo';
            $codigoEquipo = $legado['equipmentCode'] ?? '';
            $nombreEquipo = $legado['equipmentName'] ?? '';
            $idEquipoLegado = $legado['equipmentId'] ?? null;
            $equipo = false;
            if (
                (is_string($idEquipoLegado) || is_int($idEquipoLegado))
                && isset($equiposMapeados[(string) $idEquipoLegado])
            ) {
                $equipoIdMapeado = idSistema($equiposMapeados[(string) $idEquipoLegado]);
                if ($equipoIdMapeado !== null) {
                    $buscarEquipoId->execute(['id' => $equipoIdMapeado]);
                    $equipo = $buscarEquipoId->fetch();
                }
            }
            if ($equipo === false && is_string($codigoEquipo) && $codigoEquipo !== '') {
                $buscarEquipo->execute(['codigo' => $codigoEquipo]);
                $equipo = $buscarEquipo->fetch();
            }
            if ($equipo) {
                $equipoId = (int) $equipo['id'];
                $codigoEquipo = $equipo['codigo'];
                $nombreEquipo = $equipo['nombre'];
            } else {
                $equipoId = null;
                $codigoEquipo = is_string($codigoEquipo) && $codigoEquipo !== '' ? $codigoEquipo : 'LEGADO';
                $nombreEquipo = is_string($nombreEquipo) && $nombreEquipo !== '' ? $nombreEquipo : 'Equipo legado';
            }

            $responsableNombre = $legado['responsible'] ?? 'Responsable no disponible';
            if (!is_string($responsableNombre) || trim($responsableNombre) === '') $responsableNombre = 'Responsable no disponible';
            $responsableNombre = trim($responsableNombre);
            $usuarioResponsableId = null;
            $responsableLegadoId = $legado['responsibleId'] ?? null;
            if ((is_string($responsableLegadoId) || is_int($responsableLegadoId)) && isset($mapaUsuarios[(string) $responsableLegadoId])) {
                $buscarResponsable->execute(['id' => $mapaUsuarios[(string) $responsableLegadoId]]);
                $responsableActual = $buscarResponsable->fetch();
                if ($responsableActual) {
                    $usuarioResponsableId = (int) $responsableActual['id'];
                    $responsableNombre = $responsableActual['nombre'];
                }
            }

            $fecha = $legado['date'] ?? '';
            $descripcion = $legado['description'] ?? '';
            $observaciones = $legado['observations'] ?? '';
            if (
                !is_string($fecha) || !fechaValidaSistema($fecha)
                || !is_string($descripcion) || trim($descripcion) === ''
                || !is_string($observaciones)
            ) {
                responderSistema(422, ['error' => 'Uno de los mantenimientos locales no supera la validación.']);
            }
            $estadoLegado = $legado['status'] ?? '';
            $fechaCompletada = $legado['completedDate'] ?? '';
            $estado = $estadoLegado === 'Completado'
                ? 'Completado'
                : ($fecha < date('Y-m-d') ? 'Vencido' : 'Pendiente');
            $fechaCompletada = is_string($fechaCompletada) && fechaValidaSistema($fechaCompletada) ? $fechaCompletada : null;
            if ($estado === 'Completado' && $fechaCompletada === null) $fechaCompletada = $fecha;

            $valoresBusqueda = [
                'codigo' => $codigoEquipo,
                'equipo' => $nombreEquipo,
                'tipo' => $tipo,
                'responsable' => $responsableNombre,
                'fecha' => $fecha,
                'descripcion' => trim($descripcion),
                'observaciones' => trim($observaciones),
                'completada' => $fechaCompletada ?? '',
            ];
            $buscarMantenimiento->execute($valoresBusqueda);
            if ($buscarMantenimiento->fetchColumn() !== false) continue;

            $insertarMantenimiento->execute([
                'equipo_id' => $equipoId,
                'tipo_codigo' => $tipo,
                'responsable_id' => $usuarioResponsableId,
                'equipo_codigo' => $codigoEquipo,
                'equipo_nombre' => $nombreEquipo,
                'responsable_nombre' => $responsableNombre,
                'fecha' => $fecha,
                'completada' => $fechaCompletada,
                'descripcion' => trim($descripcion),
                'observaciones' => trim($observaciones) === '' ? null : trim($observaciones),
                'estado' => $estado,
            ]);
            $mantenimientosImportados++;
        }

        $pdo->commit();
        responderSistema(200, [
            'usuarios_importados' => $usuariosImportados,
            'mantenimientos_importados' => $mantenimientosImportados,
            'nota' => 'Las cuentas locales sin contraseña se importaron inactivas y requieren que un administrador asigne una contraseña antes de activarlas.',
        ]);
    }

    if ($recurso === 'mantenimiento') {
        if ($metodo === 'POST' && ($entrada['accion'] ?? null) === 'completar') {
            $id = idSistema($entrada['id'] ?? null);
            if ($id === null) responderSistema(422, ['error' => 'El identificador del mantenimiento no es válido.']);
            $actualQuery = $pdo->prepare('SELECT id,estado,fecha_programada FROM mantenimientos WHERE id=:id LIMIT 1');
            $actualQuery->execute(['id' => $id]);
            $actual = $actualQuery->fetch();
            if (!$actual) responderSistema(404, ['error' => 'El mantenimiento no existe.']);
            if ($actual['estado'] === 'Completado') responderSistema(409, ['error' => 'El mantenimiento ya está completado.']);
            $actualizar = $pdo->prepare(
                "UPDATE mantenimientos SET estado='Completado',fecha_completada=:fecha WHERE id=:id"
            );
            $actualizar->execute(['fecha' => date('Y-m-d'), 'id' => $id]);
            responderSistema(200, ['mensaje' => 'Mantenimiento completado.']);
        }

        if ($metodo === 'DELETE') {
            $id = idSistema($entrada['id'] ?? null);
            if ($id === null) responderSistema(422, ['error' => 'El identificador del mantenimiento no es válido.']);
            $eliminar = $pdo->prepare('DELETE FROM mantenimientos WHERE id=:id');
            $eliminar->execute(['id' => $id]);
            if ($eliminar->rowCount() === 0) responderSistema(404, ['error' => 'El mantenimiento no existe.']);
            responderSistema(200, ['mensaje' => 'Mantenimiento eliminado.']);
        }

        $id = null;
        $actual = [];
        if ($metodo === 'PUT') {
            $id = idSistema($entrada['id'] ?? null);
            if ($id === null) responderSistema(422, ['error' => 'El identificador del mantenimiento no es válido.']);
            $actualQuery = $pdo->prepare('SELECT id,estado FROM mantenimientos WHERE id=:id LIMIT 1');
            $actualQuery->execute(['id' => $id]);
            $actual = $actualQuery->fetch() ?: [];
            if ($actual === []) responderSistema(404, ['error' => 'El mantenimiento no existe.']);
        }
        $datos = datosMantenimiento($entrada, $pdo, $metodo === 'PUT', $actual);
        if ($metodo === 'POST') {
            $insertar = $pdo->prepare(
                'INSERT INTO mantenimientos
                 (equipo_id,tipo_codigo,responsable_usuario_id,equipo_codigo_snapshot,equipo_nombre_snapshot,
                  responsable_nombre_snapshot,fecha_programada,fecha_completada,descripcion,observaciones,estado)
                 VALUES (:equipo_id,:tipo_codigo,:responsable_usuario_id,:equipo_codigo_snapshot,:equipo_nombre_snapshot,
                         :responsable_nombre_snapshot,:fecha_programada,:fecha_completada,:descripcion,:observaciones,:estado)'
            );
            $insertar->execute($datos);
            responderSistema(201, ['id' => (string) $pdo->lastInsertId()]);
        }
        $datos['id'] = $id;
        $actualizar = $pdo->prepare(
            'UPDATE mantenimientos
             SET equipo_id=:equipo_id,tipo_codigo=:tipo_codigo,responsable_usuario_id=:responsable_usuario_id,
                 equipo_codigo_snapshot=:equipo_codigo_snapshot,equipo_nombre_snapshot=:equipo_nombre_snapshot,
                 responsable_nombre_snapshot=:responsable_nombre_snapshot,fecha_programada=:fecha_programada,
                 fecha_completada=:fecha_completada,descripcion=:descripcion,observaciones=:observaciones,estado=:estado
             WHERE id=:id'
        );
        $actualizar->execute($datos);
        responderSistema(200, ['id' => (string) $id]);
    }

    if ($recurso === 'usuario') {
        exigirAdministradorSistema($usuarioActual);
        $pdo->beginTransaction();
        $bloquearRol = $pdo->prepare('SELECT codigo FROM roles WHERE codigo=:rol FOR UPDATE');
        $bloquearRol->execute(['rol' => 'administrador']);
        if ($bloquearRol->fetchColumn() === false) {
            responderSistema(500, ['error' => 'No fue posible validar el rol administrador.']);
        }

        if ($metodo === 'DELETE') {
            $id = idSistema($entrada['id'] ?? null);
            if ($id === null) responderSistema(422, ['error' => 'El identificador del usuario no es válido.']);
            if ($id === (int) $usuarioActual['id']) responderSistema(409, ['error' => 'No puedes eliminar tu propia cuenta.']);
            $cuenta = $pdo->prepare('SELECT rol_codigo,estado FROM usuarios WHERE id=:id LIMIT 1');
            $cuenta->execute(['id' => $id]);
            $cuenta = $cuenta->fetch();
            if (!$cuenta) responderSistema(404, ['error' => 'El usuario no existe.']);
            if ($cuenta['rol_codigo'] === 'administrador' && $cuenta['estado'] === 'Activo') {
                $activos = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='administrador' AND estado='Activo'")->fetchColumn();
                if ($activos <= 1) responderSistema(409, ['error' => 'No se puede eliminar al último administrador activo.']);
            }
            $borrar = $pdo->prepare('DELETE FROM usuarios WHERE id=:id');
            $borrar->execute(['id' => $id]);
            $pdo->commit();
            responderSistema(200, ['mensaje' => 'Usuario eliminado.']);
        }

        $nombre = $entrada['nombre'] ?? null;
        $correo = $entrada['correo'] ?? null;
        $rol = $entrada['rol_codigo'] ?? null;
        $estado = $entrada['estado'] ?? 'Activo';
        $contrasena = $entrada['contrasena'] ?? '';
        if (!is_string($nombre) || !is_string($correo) || !is_string($rol) || !is_string($estado) || !is_string($contrasena)) {
            responderSistema(422, ['error' => 'Los datos del usuario no son válidos.']);
        }
        $nombre = trim($nombre);
        $correo = strtolower(trim($correo));
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 150) responderSistema(422, ['error' => 'El nombre es obligatorio y no puede superar 150 caracteres.']);
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 254) responderSistema(422, ['error' => 'Ingresa un correo electrónico válido.']);
        if (!in_array($rol, ['administrador', 'responsable_mantenimiento'], true)) responderSistema(422, ['error' => 'Selecciona un rol válido.']);
        if (!in_array($estado, ['Activo', 'Inactivo'], true)) responderSistema(422, ['error' => 'Selecciona un estado válido.']);

        $id = null;
        $actual = null;
        if ($metodo === 'PUT') {
            $id = idSistema($entrada['id'] ?? null);
            if ($id === null) responderSistema(422, ['error' => 'El identificador del usuario no es válido.']);
            $query = $pdo->prepare('SELECT id,rol_codigo,estado FROM usuarios WHERE id=:id LIMIT 1');
            $query->execute(['id' => $id]);
            $actual = $query->fetch();
            if (!$actual) responderSistema(404, ['error' => 'El usuario no existe.']);
            if ($id === (int) $usuarioActual['id'] && ($estado !== 'Activo' || $rol !== 'administrador')) {
                responderSistema(409, ['error' => 'No puedes desactivar ni quitar el rol Administrador de tu propia cuenta.']);
            }
        } elseif ($metodo !== 'POST') {
            header('Allow: GET, POST, PUT, DELETE');
            responderSistema(405, ['error' => 'Método no permitido.']);
        }
        if ($metodo === 'POST' && (mb_strlen($contrasena, 'UTF-8') < 12 || strlen($contrasena) > 72)) {
            responderSistema(422, ['error' => 'La contraseña debe tener al menos 12 caracteres y no superar 72 bytes.']);
        }
        if ($metodo === 'PUT' && $contrasena !== '' && (mb_strlen($contrasena, 'UTF-8') < 12 || strlen($contrasena) > 72)) {
            responderSistema(422, ['error' => 'La nueva contraseña debe tener al menos 12 caracteres y no superar 72 bytes.']);
        }
        if ($actual && $actual['rol_codigo'] === 'administrador' && $actual['estado'] === 'Activo' && ($rol !== 'administrador' || $estado !== 'Activo')) {
            $activos = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='administrador' AND estado='Activo'")->fetchColumn();
            if ($activos <= 1) responderSistema(409, ['error' => 'No se puede cambiar el rol o estado del último administrador activo.']);
        }

        if ($metodo === 'POST') {
            $insertar = $pdo->prepare(
                'INSERT INTO usuarios (rol_codigo,nombre,correo,contrasena_hash,estado)
                 VALUES (:rol,:nombre,:correo,:hash,:estado)'
            );
            $insertar->execute([
                'rol' => $rol,
                'nombre' => $nombre,
                'correo' => $correo,
                'hash' => password_hash($contrasena, PASSWORD_DEFAULT),
                'estado' => $estado,
            ]);
            $nuevoId = (string) $pdo->lastInsertId();
            $pdo->commit();
            responderSistema(201, ['id' => $nuevoId]);
        }

        $parametros = ['rol' => $rol, 'nombre' => $nombre, 'correo' => $correo, 'estado' => $estado, 'id' => $id];
        $sql = 'UPDATE usuarios SET rol_codigo=:rol,nombre=:nombre,correo=:correo,estado=:estado';
        if ($contrasena !== '') {
            $sql .= ',contrasena_hash=:hash';
            $parametros['hash'] = password_hash($contrasena, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id=:id';
        $actualizar = $pdo->prepare($sql);
        $actualizar->execute($parametros);
        $pdo->commit();
        responderSistema(200, ['id' => (string) $id]);
    }

    responderSistema(422, ['error' => 'El recurso solicitado no es válido.']);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Error en la API de sistema Mantix: ' . $exception->getMessage());
    if ($exception->getCode() === '23000') responderSistema(409, ['error' => 'El código de equipo o correo ya está registrado, o hay una relación que impide la operación.']);
    responderSistema(500, ['error' => 'No se pudo completar la operación. Inténtalo más tarde.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Error inesperado en la API de sistema Mantix: ' . $exception->getMessage());
    responderSistema(500, ['error' => 'No se pudo completar la operación. Inténtalo más tarde.']);
}
