<?php

declare(strict_types=1);

require_once __DIR__ . '/autenticacion.php';
iniciarSesionSegura();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function responderEquiposApi(int $estado, array $respuesta): never
{
    http_response_code($estado);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function exigirSesionEquipos(PDO $pdo): void
{
    $sesion = $_SESSION['usuario'] ?? null;

    if (!is_array($sesion) || !isset($sesion['id'], $sesion['rol_codigo'])) {
        responderEquiposApi(401, ['error' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
    }

    $consulta = $pdo->prepare(
        'SELECT u.estado, r.codigo AS rol_codigo
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
        responderEquiposApi(401, ['error' => 'Tu sesión ya no es válida. Inicia sesión nuevamente.']);
    }
}

function leerIdentificadorEquipo(mixed $valor): ?int
{
    if (!is_int($valor) && (!is_string($valor) || !preg_match('/^[1-9][0-9]*$/D', $valor))) {
        return null;
    }

    $identificador = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $identificador === false ? null : $identificador;
}

function validarDatosEquipo(mixed $entrada): array
{
    if (!is_array($entrada)) {
        responderEquiposApi(400, ['error' => 'Los datos del equipo no tienen un formato válido.']);
    }

    $limites = [
        'codigo' => 50,
        'nombre' => 150,
        'categoria' => 100,
        'marca' => 100,
        'modelo' => 100,
        'ubicacion' => 180,
    ];
    $datos = [];

    foreach ($limites as $campo => $limite) {
        $valor = $entrada[$campo] ?? null;

        if (!is_string($valor)) {
            responderEquiposApi(422, ['error' => 'Completa todos los campos del equipo.']);
        }

        $valor = trim($valor);
        if ($valor === '') {
            responderEquiposApi(422, ['error' => 'Completa todos los campos del equipo.']);
        }
        if (mb_strlen($valor, 'UTF-8') > $limite) {
            responderEquiposApi(422, ['error' => 'Uno o más campos superan la longitud permitida.']);
        }

        $datos[$campo] = $valor;
    }

    $datos['codigo'] = mb_strtoupper($datos['codigo'], 'UTF-8');
    if (mb_strlen($datos['codigo'], 'UTF-8') > 50) {
        responderEquiposApi(422, ['error' => 'El código supera la longitud permitida.']);
    }
    $estado = $entrada['estado'] ?? null;
    $estadosValidos = ['Operativo', 'En mantenimiento', 'Fuera de servicio'];

    if (!is_string($estado) || !in_array($estado, $estadosValidos, true)) {
        responderEquiposApi(422, ['error' => 'Selecciona un estado válido para el equipo.']);
    }

    $datos['estado'] = $estado;

    return $datos;
}

function presentarEquipo(int|string $id, array $equipo): array
{
    return [
        'id' => (string) $id,
        'code' => $equipo['codigo'],
        'name' => $equipo['nombre'],
        'category' => $equipo['categoria'],
        'brand' => $equipo['marca'],
        'model' => $equipo['modelo'],
        'location' => $equipo['ubicacion'],
        'status' => $equipo['estado'],
    ];
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    exigirSesionEquipos($pdo);

    if ($metodo === 'GET') {
        $terminoEnviado = $_GET['busqueda'] ?? '';
        $estadoEnviado = $_GET['estado'] ?? '';
        $categoriaEnviada = $_GET['categoria'] ?? '';

        if (!is_string($terminoEnviado) || !is_string($estadoEnviado) || !is_string($categoriaEnviada)) {
            responderEquiposApi(422, ['error' => 'Los filtros enviados no son válidos.']);
        }

        $termino = trim($terminoEnviado);
        $estado = $estadoEnviado;
        $categoria = trim($categoriaEnviada);
        $estadosValidos = ['', 'Operativo', 'En mantenimiento', 'Fuera de servicio'];

        if (mb_strlen($termino, 'UTF-8') > 200 || mb_strlen($categoria, 'UTF-8') > 100) {
            responderEquiposApi(422, ['error' => 'El filtro de búsqueda supera la longitud permitida.']);
        }
        if (!in_array($estado, $estadosValidos, true)) {
            responderEquiposApi(422, ['error' => 'El estado seleccionado no es válido.']);
        }

        $where = [];
        $parametros = [];

        if ($termino !== '') {
            $where[] = '(nombre LIKE :busqueda_nombre OR codigo LIKE :busqueda_codigo)';
            $patron = '%' . addcslashes($termino, '\\%_') . '%';
            $parametros['busqueda_nombre'] = $patron;
            $parametros['busqueda_codigo'] = $patron;
        }
        if ($estado !== '') {
            $where[] = 'estado = :estado';
            $parametros['estado'] = $estado;
        }
        if ($categoria !== '') {
            $where[] = 'categoria = :categoria';
            $parametros['categoria'] = $categoria;
        }

        $consulta = 'SELECT id, codigo, nombre, categoria, marca, modelo, ubicacion, estado FROM equipos';
        if ($where !== []) {
            $consulta .= ' WHERE ' . implode(' AND ', $where);
        }
        $consulta .= ' ORDER BY nombre ASC, codigo ASC';

        $listado = $pdo->prepare($consulta);
        $listado->execute($parametros);
        $mapearEquipo = static fn (array $equipo): array => presentarEquipo((int) $equipo['id'], $equipo);
        $equipos = array_map($mapearEquipo, $listado->fetchAll());
        $catalogo = array_map(
            $mapearEquipo,
            $pdo->query('SELECT id, codigo, nombre, categoria, marca, modelo, ubicacion, estado FROM equipos ORDER BY nombre ASC, codigo ASC')->fetchAll()
        );
        $categorias = $pdo->query('SELECT DISTINCT categoria FROM equipos ORDER BY categoria ASC')->fetchAll(PDO::FETCH_COLUMN);
        $total = (int) $pdo->query('SELECT COUNT(*) FROM equipos')->fetchColumn();

        responderEquiposApi(200, [
            'equipos' => $equipos,
            'catalogo' => $catalogo,
            'categorias' => $categorias,
            'total' => $total,
        ]);
    }

    if (!in_array($metodo, ['POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        responderEquiposApi(405, ['error' => 'Método no permitido.']);
    }

    if (!validarTokenCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        responderEquiposApi(403, ['error' => 'La solicitud expiró o no es válida. Recarga la página e inténtalo nuevamente.']);
    }

    $contenido = file_get_contents('php://input');
    $entrada = json_decode($contenido === false ? '' : $contenido, true);

    if (!is_array($entrada)) {
        responderEquiposApi(400, ['error' => 'La solicitud no contiene datos válidos.']);
    }

    if ($metodo === 'POST') {
        if (($entrada['accion'] ?? null) === 'migrar_localstorage') {
            $equiposLegados = $entrada['equipos'] ?? null;

            if (!is_array($equiposLegados) || count($equiposLegados) > 500) {
                responderEquiposApi(422, ['error' => 'El conjunto de equipos locales no es válido.']);
            }

            $equiposValidados = [];
            foreach ($equiposLegados as $equipoLegado) {
                if (!is_array($equipoLegado)) {
                    responderEquiposApi(422, ['error' => 'Uno de los equipos locales no tiene un formato válido.']);
                }

                $idLegado = $equipoLegado['id'] ?? null;
                if (
                    (!is_string($idLegado) && !is_int($idLegado))
                    || trim((string) $idLegado) === ''
                    || mb_strlen((string) $idLegado, 'UTF-8') > 100
                ) {
                    responderEquiposApi(422, ['error' => 'Uno de los identificadores locales no es válido.']);
                }

                $datos = validarDatosEquipo([
                    'codigo' => $equipoLegado['code'] ?? null,
                    'nombre' => $equipoLegado['name'] ?? null,
                    'categoria' => $equipoLegado['category'] ?? null,
                    'marca' => $equipoLegado['brand'] ?? null,
                    'modelo' => $equipoLegado['model'] ?? null,
                    'ubicacion' => $equipoLegado['location'] ?? null,
                    'estado' => $equipoLegado['status'] ?? null,
                ]);
                $equiposValidados[] = [
                    'legacy_id' => (string) $idLegado,
                    'datos' => $datos,
                ];
            }

            $pdo->beginTransaction();
            $guardarLegado = $pdo->prepare(
                'INSERT INTO equipos (codigo, nombre, categoria, marca, modelo, ubicacion, estado)
                 VALUES (:codigo, :nombre, :categoria, :marca, :modelo, :ubicacion, :estado)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $mapeoLegado = [];
            $importados = 0;
            $existentes = 0;

            foreach ($equiposValidados as $equipoLegado) {
                $datos = $equipoLegado['datos'];
                $buscarCodigo = $pdo->prepare('SELECT id FROM equipos WHERE codigo = :codigo LIMIT 1');
                $buscarCodigo->execute(['codigo' => $datos['codigo']]);
                $yaExistia = (bool) $buscarCodigo->fetch();

                $guardarLegado->execute($datos);
                $mapeoLegado[] = [
                    'id' => (string) $pdo->lastInsertId(),
                    'legacy_id' => $equipoLegado['legacy_id'],
                ];
                if ($yaExistia) {
                    $existentes++;
                } else {
                    $importados++;
                }
            }

            $pdo->commit();
            responderEquiposApi(200, [
                'importados' => $importados,
                'existentes' => $existentes,
                'mapeo_legado' => $mapeoLegado,
            ]);
        }

        $equipo = validarDatosEquipo($entrada);
        $insertar = $pdo->prepare(
            'INSERT INTO equipos (codigo, nombre, categoria, marca, modelo, ubicacion, estado)
             VALUES (:codigo, :nombre, :categoria, :marca, :modelo, :ubicacion, :estado)'
        );
        $insertar->execute($equipo);
        responderEquiposApi(201, ['equipo' => presentarEquipo((int) $pdo->lastInsertId(), $equipo)]);
    }

    $id = leerIdentificadorEquipo($entrada['id'] ?? null);
    if ($id === null) {
        responderEquiposApi(422, ['error' => 'El identificador del equipo no es válido.']);
    }

    $buscar = $pdo->prepare('SELECT id FROM equipos WHERE id = :id LIMIT 1');
    $buscar->execute(['id' => $id]);
    if (!$buscar->fetch()) {
        responderEquiposApi(404, ['error' => 'El equipo ya no existe o fue eliminado.']);
    }

    if ($metodo === 'PUT') {
        $equipo = validarDatosEquipo($entrada);
        $equipo['id'] = $id;
        $actualizar = $pdo->prepare(
            'UPDATE equipos
             SET codigo = :codigo, nombre = :nombre, categoria = :categoria,
                 marca = :marca, modelo = :modelo, ubicacion = :ubicacion, estado = :estado
             WHERE id = :id'
        );
        $actualizar->execute($equipo);

        responderEquiposApi(200, ['equipo' => presentarEquipo($id, $equipo)]);
    }

    $eliminar = $pdo->prepare('DELETE FROM equipos WHERE id = :id');
    $eliminar->execute(['id' => $id]);

    responderEquiposApi(200, ['mensaje' => 'Equipo eliminado.']);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Error en la API de equipos de Mantix: ' . $exception->getMessage());

    if ($exception->getCode() === '23000' && $metodo === 'POST') {
        responderEquiposApi(409, ['error' => 'Ya existe un equipo con ese código.']);
    }
    if ($exception->getCode() === '23000' && $metodo === 'PUT') {
        responderEquiposApi(409, ['error' => 'Ya existe otro equipo con ese código.']);
    }

    responderEquiposApi(500, ['error' => 'No se pudo completar la operación de equipos. Inténtalo más tarde.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Error inesperado en la API de equipos de Mantix: ' . $exception->getMessage());
    responderEquiposApi(500, ['error' => 'No se pudo completar la operación de equipos. Inténtalo más tarde.']);
}
