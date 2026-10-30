<?php

declare(strict_types=1);

require_once __DIR__ . '/autenticacion.php';
iniciarSesionSegura();
exigirAutenticacion($pdo);
$usuarioActual = $_SESSION['usuario'];

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#10233f">
  <meta name="description" content="Sistema para gestionar equipos y hacer seguimiento a sus mantenimientos.">
  <title>Panel de mantenimiento | Mantix</title>
  <link rel="stylesheet" href="css/estilos.css">
  <script>window.MANTIX_USER = <?= json_encode(['id' => (string) $usuarioActual['id'], 'name' => $usuarioActual['nombre'], 'role' => $usuarioActual['rol_nombre'], 'roleCode' => $usuarioActual['rol_codigo']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>; window.MANTIX_CSRF_TOKEN = <?= json_encode(obtenerTokenCsrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;</script>
  <script src="js/app.js" defer></script>
</head>
<body class="app-body">
  <svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 8 9 5 9-5M3 16l9 5 9-5M3 8v8m18-8v8M12 13v8"/></symbol>
    <symbol id="i-tools" viewBox="0 0 24 24"><path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18l3 3 5.3-5.3a5 5 0 0 0 6.4-6.4L15 12l-3-3 2.7-2.7Z"/><path d="m16 8 3-3 2 2-3 3"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m18 9-5 5-4-4-5 5M18 9h-5m5 0v5"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M7 17 17 7M7 7h10v10"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></symbol>
  </svg>

  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="index.php" aria-label="Mantix, inicio">
        <span class="brand-mark"><svg class="icon"><use href="#i-tools"/></svg></span>
        <span class="brand-copy"><strong>mantix</strong><small>GESTIÓN DE ACTIVOS</small></span>
      </a>
      <div class="workspace-label">ESPACIO DE TRABAJO</div>
      <nav class="side-nav" aria-label="Navegación principal">
        <button class="nav-link active" data-view="dashboard"><svg class="icon"><use href="#i-grid"/></svg><span>Panel principal</span></button>
        <button class="nav-link" data-view="equipos"><svg class="icon"><use href="#i-box"/></svg><span>Equipos</span><span class="nav-count" id="equipment-count">0</span></button>
        <button class="nav-link" data-view="mantenimientos"><svg class="icon"><use href="#i-tools"/></svg><span>Mantenimientos</span></button>
        <button class="nav-link" data-view="historial"><svg class="icon"><use href="#i-clock"/></svg><span>Historial</span></button>
        <div class="nav-divider"></div>
        <div class="workspace-label nav-section-label">ADMINISTRACIÓN</div>
        <button class="nav-link" data-view="usuarios"><svg class="icon"><use href="#i-users"/></svg><span>Usuarios</span></button>
        <button class="nav-link" data-view="reportes"><svg class="icon"><use href="#i-chart"/></svg><span>Reportes</span></button>
      </nav>
      <div class="sidebar-bottom">
        <div class="sidebar-note"><span class="note-icon"><svg class="icon"><use href="#i-check"/></svg></span><div><strong>Todo bajo control</strong><small>Tu operación está al día.</small></div></div>
        <form action="logout.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obtenerTokenCsrf(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <button class="nav-link logout-link" id="logout-button" type="submit"><svg class="icon"><use href="#i-logout"/></svg><span>Cerrar sesión</span></button>
        </form>
        <div class="sidebar-foot">MANTIX <span>·</span> DEMOSTRACIÓN LOCAL</div>
      </div>
    </aside>
    <div class="sidebar-scrim" id="sidebar-scrim"></div>

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button mobile-menu-button" id="menu-toggle" aria-label="Abrir menú"><svg class="icon"><use href="#i-menu"/></svg></button>
          <div class="breadcrumb"><span>Gestión</span><span class="breadcrumb-slash">/</span><strong id="page-title">Panel principal</strong></div>
        </div>
        <div class="topbar-right">
          <span class="topbar-date" id="topbar-date"></span>
          <div class="topbar-divider"></div>
          <button class="icon-button notification-button" aria-label="Notificaciones"><svg class="icon"><use href="#i-bell"/></svg><i></i></button>
          <button class="profile-button" id="profile-button" type="button" aria-label="Cerrar sesión"><span class="avatar" id="profile-initials">AD</span><span class="profile-copy"><strong id="profile-name"><?= htmlspecialchars($usuarioActual['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><small id="profile-role"><?= htmlspecialchars($usuarioActual['rol_nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small></span><span class="profile-chevron">⌄</span></button>
        </div>
      </header>

      <div class="page-content">
        <section class="view active" id="view-dashboard">
          <div class="welcome-row">
            <div><p class="eyebrow">RESUMEN DE OPERACIONES</p><h1>Buenos días, <span id="welcome-name">Administrador</span> <span class="wave">✦</span></h1><p class="page-subtitle">Aquí tienes el estado general del mantenimiento de tus equipos.</p></div>
            <button class="button button-primary" data-action="new-maintenance"><svg class="icon"><use href="#i-plus"/></svg>Programar mantenimiento</button>
          </div>
          <div class="stats-grid" id="dashboard-stats"></div>
          <div class="dashboard-grid">
            <article class="card status-card">
              <div class="card-heading"><div><h2>Estado de mantenimientos</h2><p>Distribución de actividades registradas</p></div><span class="period-pill">Este periodo</span></div>
              <div class="status-content"><div class="donut-wrap"><div class="donut-chart" id="donut-chart"><div class="donut-center"><strong id="donut-total">0</strong><span>actividades</span></div></div></div><div class="chart-legend" id="chart-legend"></div></div>
            </article>
            <article class="card upcoming-card">
              <div class="card-heading"><div><h2>Próximos mantenimientos</h2><p>Actividades programadas para los próximos días</p></div><button class="text-link" data-goto="mantenimientos">Ver todos <svg class="icon"><use href="#i-arrow"/></svg></button></div>
              <div id="upcoming-list" class="upcoming-list"></div>
            </article>
          </div>
          <article class="card recent-card">
            <div class="card-heading"><div><h2>Actividad reciente</h2><p>Últimos movimientos del sistema</p></div><button class="text-link" data-goto="historial">Ver historial <svg class="icon"><use href="#i-arrow"/></svg></button></div>
            <div class="table-wrap"><table><thead><tr><th>EQUIPO</th><th>ACTIVIDAD</th><th>RESPONSABLE</th><th>FECHA</th><th>ESTADO</th></tr></thead><tbody id="recent-activity"></tbody></table></div>
          </article>
        </section>

        <section class="view" id="view-equipos">
          <div class="page-heading"><div><p class="eyebrow">INVENTARIO</p><h1>Gestión de equipos</h1><p class="page-subtitle">Administra tus activos y consulta su información.</p></div><button class="button button-primary" data-action="new-equipment"><svg class="icon"><use href="#i-plus"/></svg>Registrar equipo</button></div>
          <div class="card table-card"><div class="table-toolbar"><div class="search-box"><svg class="icon"><use href="#i-search"/></svg><input id="equipment-search" type="search" placeholder="Buscar por equipo o código..." aria-label="Buscar equipos"></div><select id="equipment-status-filter" class="select-control" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option>Operativo</option><option>En mantenimiento</option><option>Fuera de servicio</option></select><select id="equipment-category-filter" class="select-control" aria-label="Filtrar por categoría"><option value="">Todas las categorías</option></select><span class="result-count" id="equipment-result-count"></span></div><div class="table-wrap"><table><thead><tr><th>EQUIPO</th><th>CATEGORÍA</th><th>MARCA / MODELO</th><th>UBICACIÓN</th><th>ESTADO</th><th class="actions-heading">ACCIONES</th></tr></thead><tbody id="equipment-table"></tbody></table></div></div>
        </section>

        <section class="view" id="view-mantenimientos">
          <div class="page-heading"><div><p class="eyebrow">PLANIFICACIÓN Y SEGUIMIENTO</p><h1>Gestión de mantenimientos</h1><p class="page-subtitle">Programa, asigna y da seguimiento a cada actividad.</p></div><button class="button button-primary" data-action="new-maintenance"><svg class="icon"><use href="#i-plus"/></svg>Programar mantenimiento</button></div>
          <div class="card table-card"><div class="table-toolbar"><div class="search-box"><svg class="icon"><use href="#i-search"/></svg><input id="maintenance-search" type="search" placeholder="Buscar equipo, responsable o descripción..." aria-label="Buscar mantenimientos"></div><select id="maintenance-status-filter" class="select-control" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="Pendiente">Pendiente</option><option value="Completado">Completado</option><option value="Vencido">Vencido</option></select><select id="maintenance-type-filter" class="select-control" aria-label="Filtrar por tipo"><option value="">Todos los tipos</option><option>Preventivo</option><option>Correctivo</option></select></div><div class="table-wrap"><table><thead><tr><th>EQUIPO</th><th>TIPO</th><th>FECHA PROGRAMADA</th><th>RESPONSABLE</th><th>ESTADO</th><th class="actions-heading">ACCIONES</th></tr></thead><tbody id="maintenance-table"></tbody></table></div></div>
        </section>

        <section class="view" id="view-historial">
          <div class="page-heading"><div><p class="eyebrow">REGISTRO DE ACTIVIDADES</p><h1>Historial de mantenimiento</h1><p class="page-subtitle">Consulta los trabajos finalizados y sus responsables.</p></div></div>
          <div class="card table-card"><div class="table-toolbar"><div class="search-box"><svg class="icon"><use href="#i-search"/></svg><input id="history-search" type="search" placeholder="Buscar por equipo, responsable o trabajo..." aria-label="Buscar por equipo, responsable o trabajo"></div><input id="history-from" class="input-control" type="date" aria-label="Desde"><input id="history-to" class="input-control" type="date" aria-label="Hasta"><select id="history-equipment-filter" class="select-control" aria-label="Filtrar por equipo"><option value="">Todos los equipos</option></select><select id="history-type-filter" class="select-control" aria-label="Filtrar por tipo"><option value="">Todos los tipos</option><option value="preventivo">Preventivo</option><option value="correctivo">Correctivo</option></select></div><div class="table-wrap"><table><thead><tr><th>EQUIPO</th><th>FECHA PROGRAMADA</th><th>FECHA REALIZADA</th><th>TIPO</th><th>RESPONSABLE</th><th>TRABAJO REALIZADO</th><th>ESTADO</th></tr></thead><tbody id="history-table"></tbody></table></div></div>
        </section>

        <section class="view" id="view-usuarios">
          <div class="page-heading"><div><p class="eyebrow">CONTROL DE ACCESO</p><h1>Gestión de usuarios</h1><p class="page-subtitle">Administra las personas responsables de tus activos.</p></div><button class="button button-primary" data-action="new-user"><svg class="icon"><use href="#i-plus"/></svg>Agregar usuario</button></div>
          <div class="card table-card"><div class="table-toolbar"><div class="search-box"><svg class="icon"><use href="#i-search"/></svg><input id="user-search" type="search" placeholder="Buscar por nombre o correo..." aria-label="Buscar usuarios"></div><span class="result-count" id="user-result-count"></span></div><div class="table-wrap"><table><thead><tr><th>USUARIO</th><th>CORREO</th><th>ROL</th><th>ESTADO</th><th class="actions-heading">ACCIONES</th></tr></thead><tbody id="user-table"></tbody></table></div></div>
        </section>

        <section class="view" id="view-reportes">
          <div class="page-heading"><div><p class="eyebrow">ANÁLISIS Y RESULTADOS</p><h1>Reportes</h1><p class="page-subtitle">Explora el desempeño del mantenimiento de tus equipos.</p></div></div>
          <article class="card report-filters"><div class="filter-label"><svg class="icon"><use href="#i-calendar"/></svg><strong>Filtrar periodo</strong></div><label>Desde<input id="report-from" type="date" class="input-control"></label><label>Hasta<input id="report-to" type="date" class="input-control"></label><label>Estado<select id="report-status" class="select-control"><option value="">Todos los estados</option><option>Pendiente</option><option>Completado</option><option>Vencido</option></select></label><label>Tipo<select id="report-type" class="select-control"><option value="">Todos los tipos</option><option value="preventivo">Preventivo</option><option value="correctivo">Correctivo</option></select></label><label>Equipo<select id="report-equipment" class="select-control"><option value="">Todos los equipos</option></select></label><button class="button button-secondary" id="report-reset">Limpiar filtros</button></article>
          <div class="report-stats" id="report-stats"></div>
          <div class="dashboard-grid report-grid"><article class="card"><div class="card-heading"><div><h2>Actividades por estado</h2><p>Distribución en el periodo seleccionado</p></div></div><div class="report-bars" id="report-bars"></div></article><article class="card"><div class="card-heading"><div><h2>Equipos con más mantenimiento</h2><p>Activos con mayor número de intervenciones</p></div></div><div class="ranking-list" id="equipment-ranking"></div></article><article class="card"><div class="card-heading"><div><h2>Mantenimientos por responsable</h2><p>Actividades incluidas en los filtros</p></div></div><div class="ranking-list" id="responsible-ranking"></div></article></div>
        </section>
      </div>
    </main>
  </div>

  <div class="modal-layer" id="modal-layer" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-title"><div class="modal-header"><div><p class="eyebrow" id="modal-eyebrow">GESTIÓN</p><h2 id="modal-title"></h2></div><button class="icon-button modal-close" type="button" aria-label="Cerrar ventana" data-close-modal>×</button></div><div class="modal-body" id="modal-body"></div></section></div>
  <div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
