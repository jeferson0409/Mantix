# Mantix

Mantix es una aplicación web para administrar equipos y planificar, registrar y dar seguimiento a sus mantenimientos. Está pensada para centralizar el inventario, las tareas programadas y el historial de trabajo en una interfaz sencilla.

## Funcionalidades

- Panel con resumen de operaciones y próximos mantenimientos.
- Registro y administración del inventario de equipos.
- Programación de mantenimientos preventivos y correctivos.
- Seguimiento de actividades pendientes, completadas y vencidas.
- Historial de mantenimientos realizados.
- Administración de usuarios con roles de administrador y responsable de mantenimiento.
- Informes con filtros para consultar la información registrada.
- Inicio de sesión, sesiones protegidas y validación de solicitudes CSRF.

## Tecnologías

- PHP 8.1 o posterior
- MySQL o MariaDB
- PDO con el controlador `pdo_mysql`
- HTML, CSS y JavaScript sin dependencias de paquetes

## Requisitos

- Servidor web Apache con PHP 8.1+ habilitado.
- MySQL/MariaDB y la extensión `pdo_mysql` de PHP.
- Extensión `mbstring` de PHP.

## Instalación local

1. Clona el repositorio dentro del directorio público de tu servidor local, por ejemplo `C:\xampp\htdocs\Mantix`:

   ```powershell
   git clone https://github.com/jeferson0409/Mantix.git
   ```

2. Crea la base de datos importando [`database.sql`](./database.sql) desde phpMyAdmin o desde la consola de MySQL.
3. Crea tu configuración local a partir de la plantilla:

   ```powershell
   Copy-Item config\conexion.example.php config\conexion.php
   ```

4. Edita **tu copia local** de `config/conexion.php` con el host, nombre de base de datos, usuario y contraseña de tu entorno. No compartas ni subas ese archivo; Git lo ignora.
5. Inicia Apache y MySQL, y abre `http://localhost/Mantix/login.php`.
6. Crea el primer usuario administrador: genera un hash con PHP CLI (PowerShell) y escribe la contraseña cuando se solicite:

   ```powershell
   php -r '$password = rtrim(fgets(STDIN), chr(13).chr(10)); echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;'
   ```

   En phpMyAdmin, ejecuta el siguiente `INSERT` después de reemplazar el correo, el nombre y `PEGA_AQUI_EL_HASH` por tus propios datos y el hash generado:

   ```sql
   INSERT INTO usuarios (rol_codigo, nombre, correo, contrasena_hash, estado)
   VALUES ('administrador', 'Administrador inicial', 'admin@ejemplo.com', 'PEGA_AQUI_EL_HASH', 'Activo');
   ```

   Después, inicia sesión con ese correo y la contraseña que escribiste al generar el hash. No guardes contraseñas en el repositorio.

## Configuración

La plantilla `config/conexion.example.php` usa `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` si están definidas en el entorno; sus valores locales por defecto son `localhost`, `mantix`, `root` y contraseña vacía. Ajusta tu copia local según la configuración de MySQL. La configuración de producción y las credenciales de alojamiento no forman parte del repositorio.

`database.sql` crea la base de datos, sus tablas y los catálogos iniciales de roles y tipos de mantenimiento. No incluye cuentas de usuario, contraseñas ni datos operativos.

## Estructura

```text
Mantix/
├── config/
│   └── conexion.example.php
├── css/
│   └── estilos.css
├── js/
│   ├── app.js
│   └── login.js
├── autenticacion.php
├── database.sql
├── equipos_api.php
├── index.php
├── login.php
├── logout.php
├── probar_conexion.php
└── sistema_api.php
```

Los archivos `index.html` y `login.html` conservan compatibilidad con las rutas antiguas y redirigen a las páginas PHP actuales.

## Seguridad

- `config/conexion.php` es una configuración local ignorada por Git. Usa la plantilla como punto de partida y nunca publiques credenciales.
- Mantén contraseñas y datos privados fuera del repositorio y de los ejemplos.
- Usa HTTPS y credenciales de base de datos con permisos limitados en cualquier despliegue público.
- No habilites errores detallados de PHP para los usuarios de producción.
