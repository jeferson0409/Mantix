const STORAGE_KEY = "mantix-data-v1";
const EQUIPMENT_MIGRATION_KEY = "mantix-equipment-migrated-v1";
const EQUIPMENT_LEGACY_MAP_KEY = "mantix-equipment-legacy-map-v1";
const SYSTEM_MIGRATION_KEY = "mantix-system-migrated-v1";
const currentUser = window.MANTIX_USER;

initializeApp();

function initializeApp() {
  const today = new Date();
  const localDate = (value) => `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}-${String(value.getDate()).padStart(2, "0")}`;
  const storedData = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
  let data = {
    equipment: Array.isArray(storedData?.equipment) ? storedData.equipment : [],
    maintenance: Array.isArray(storedData?.maintenance) ? storedData.maintenance : [],
    users: Array.isArray(storedData?.users) ? storedData.users : []
  };
  const legacyEquipment = Array.isArray(storedData?.equipment) ? storedData.equipment : [];
  const legacyMaintenance = Array.isArray(storedData?.maintenance) ? storedData.maintenance : [];
  const legacyUsers = Array.isArray(storedData?.users) ? storedData.users : [];
  let activeView = "dashboard";
  const viewTitles = { dashboard: "Panel principal", equipos: "Equipos", mantenimientos: "Mantenimientos", historial: "Historial", usuarios: "Usuarios", reportes: "Reportes" };
  let visibleEquipment = [];
  let maintenanceTypes = [];
  const $ = (selector) => document.querySelector(selector);
  const escapeHtml = (value = "") => String(value).replace(/[&<>"']/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[char]);
  const icon = (id) => `<svg class="icon" aria-hidden="true"><use href="#i-${id}"></use></svg>`;
  const todayKey = () => localDate(new Date());
  const formatDate = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString("es-CO", { day: "2-digit", month: "short", year: "numeric" }) : "—";
  const equipmentFor = (id) => data.equipment.find((item) => String(item.id) === String(id) || String(item.legacyId) === String(id));
  const maintenanceStatus = (item) => item.status !== "Completado" && item.date < todayKey() ? "Vencido" : item.status;
  const statusBadge = (status) => `<span class="badge badge-${status.toLowerCase().replaceAll(" ", "-")}"><i></i>${escapeHtml(status)}</span>`;
  const emptyRow = (columns, text) => `<tr><td colspan="${columns}" class="empty-state"><span class="empty-icon">${icon("search")}</span><strong>${escapeHtml(text)}</strong><small>Prueba cambiando los filtros o registra un nuevo elemento.</small></td></tr>`;
  const isAdministrator = currentUser.roleCode === "administrador";

  $("#topbar-date").textContent = new Date().toLocaleDateString("es-CO", { weekday: "long", day: "numeric", month: "long" });
  $("#profile-name").textContent = currentUser.name;
  $("#profile-role").textContent = currentUser.role;
  $("#welcome-name").textContent = currentUser.name;
  $("#profile-initials").textContent = currentUser.name.split(" ").map((word) => word[0]).slice(0, 2).join("").toUpperCase();

  function showToast(message, type = "success") {
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `${icon(type === "success" ? "check" : "tools")}<span>${escapeHtml(message)}</span>`;
    $("#toast-region").append(toast);
    window.setTimeout(() => toast.remove(), 3500);
  }

  async function solicitarSistema(recurso, metodo = "GET", datos = {}) {
    const opciones = {
      method: metodo,
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    };
    if (metodo !== "GET") {
      opciones.headers["Content-Type"] = "application/json";
      opciones.headers["X-CSRF-Token"] = window.MANTIX_CSRF_TOKEN;
      opciones.body = JSON.stringify({ recurso, ...datos });
    } else if (recurso) {
      opciones.headers.Accept = "application/json";
    }

    const response = await fetch("sistema_api.php", opciones);
    const resultado = await response.json();
    if (!response.ok) throw new Error(resultado.error || "No fue posible completar la operación.");
    return resultado;
  }

  function updateView(view) {
    if (view === "usuarios" && !isAdministrator) {
      showToast("No tienes permisos para consultar la gestión de usuarios.", "error");
      return;
    }

    activeView = view;
    document.querySelectorAll(".view").forEach((section) => section.classList.toggle("active", section.id === `view-${view}`));
    document.querySelectorAll(".nav-link[data-view]").forEach((button) => button.classList.toggle("active", button.dataset.view === view));
    $("#page-title").textContent = viewTitles[view];
    $("#sidebar").classList.remove("sidebar-open");
    $("#sidebar-scrim").classList.remove("visible");
    renderAll();
  }

  function renderDashboard() {
    const counts = { pending: 0, completed: 0, overdue: 0 };
    data.maintenance.forEach((item) => {
      const state = maintenanceStatus(item);
      if (state === "Completado") counts.completed += 1;
      else if (state === "Vencido") counts.overdue += 1;
      else counts.pending += 1;
    });
    const stats = [
      { label: "Equipos registrados", value: data.equipment.length, icon: "box", tone: "blue", foot: "Activos en inventario", trend: "Inventario" },
      { label: "Mantenimientos pendientes", value: counts.pending, icon: "calendar", tone: "amber", foot: "Programados por realizar", trend: "Pendientes" },
      { label: "Mantenimientos completados", value: counts.completed, icon: "check", tone: "green", foot: "Trabajos finalizados", trend: "Completados" },
      { label: "Mantenimientos vencidos", value: counts.overdue, icon: "clock", tone: "red", foot: "Requieren atención", trend: "Atención" }
    ];
    $("#dashboard-stats").innerHTML = stats.map((item) => `<article class="stat-card"><div class="stat-top"><span class="stat-icon ${item.tone}">${icon(item.icon)}</span><span class="stat-trend ${item.tone}">${item.trend}</span></div><p>${item.label}</p><div class="stat-bottom"><strong>${item.value}</strong><span>${item.foot}</span></div></article>`).join("");
    const total = data.maintenance.length || 1;
    $("#donut-total").textContent = data.maintenance.length;
    const completedDegrees = counts.completed / total * 360;
    const pendingDegrees = counts.pending / total * 360;
    const overdueDegrees = counts.overdue / total * 360;
    $("#donut-chart").style.background = `conic-gradient(#2c8a67 0deg ${completedDegrees}deg, #e6a23c ${completedDegrees}deg ${completedDegrees + pendingDegrees}deg, #d86963 ${completedDegrees + pendingDegrees}deg ${completedDegrees + pendingDegrees + overdueDegrees}deg, #edf0f5 ${completedDegrees + pendingDegrees + overdueDegrees}deg 360deg)`;
    $("#chart-legend").innerHTML = [["Completados", counts.completed, "green"], ["Pendientes", counts.pending, "amber"], ["Vencidos", counts.overdue, "red"]].map(([label, count, tone]) => `<div class="legend-item"><span class="legend-dot ${tone}"></span><span>${label}</span><strong>${count}</strong></div>`).join("");
    const upcoming = data.maintenance.filter((item) => maintenanceStatus(item) === "Pendiente").sort((a, b) => a.date.localeCompare(b.date)).slice(0, 3);
    $("#upcoming-list").innerHTML = upcoming.length ? upcoming.map((item) => {
      const equipment = equipmentFor(item.equipmentId);
      return `<div class="upcoming-item"><div class="date-tile"><strong>${new Date(`${item.date}T00:00:00`).getDate()}</strong><span>${new Date(`${item.date}T00:00:00`).toLocaleDateString("es-CO", { month: "short" }).replace(".", "")}</span></div><div class="upcoming-info"><strong>${escapeHtml(equipment?.name || item.equipmentName || "Equipo eliminado")}</strong><span>${escapeHtml(item.description)}</span></div><span class="type-mark ${item.type.toLowerCase()}">${item.type === "Preventivo" ? "PREV." : "CORR."}</span></div>`;
    }).join("") : `<div class="inline-empty">No hay mantenimientos próximos.</div>`;
    const latest = [...data.maintenance].sort((a, b) => b.date.localeCompare(a.date)).slice(0, 4);
    $("#recent-activity").innerHTML = latest.length ? latest.map((item) => `<tr><td><div class="table-entity"><span class="entity-icon">${icon("box")}</span><span><strong>${escapeHtml(equipmentFor(item.equipmentId)?.name || item.equipmentName || "Equipo eliminado")}</strong><small>${escapeHtml(equipmentFor(item.equipmentId)?.code || item.equipmentCode || "—")}</small></span></div></td><td>${escapeHtml(item.type)} · ${escapeHtml(item.description)}</td><td>${escapeHtml(item.responsible)}</td><td>${formatDate(item.completedDate || item.date)}</td><td>${statusBadge(maintenanceStatus(item))}</td></tr>`).join("") : emptyRow(5, "Aún no hay actividad registrada");
  }

  function renderEquipment() {
    const rows = visibleEquipment;
    $("#equipment-result-count").textContent = `${rows.length} ${rows.length === 1 ? "equipo" : "equipos"}`;
    $("#equipment-table").innerHTML = rows.length ? rows.map((item) => `<tr><td><div class="table-entity"><span class="entity-icon">${icon("box")}</span><span><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.code)}</small></span></div></td><td>${escapeHtml(item.category)}</td><td><strong class="cell-primary">${escapeHtml(item.brand)}</strong><small class="cell-sub">${escapeHtml(item.model)}</small></td><td>${escapeHtml(item.location)}</td><td>${statusBadge(item.status)}</td><td class="row-actions"><button class="action-button" data-action="edit-equipment" data-id="${item.id}" aria-label="Editar ${escapeHtml(item.name)}">${icon("edit")}</button><button class="action-button danger" data-action="delete-equipment" data-id="${item.id}" aria-label="Eliminar ${escapeHtml(item.name)}">${icon("trash")}</button></td></tr>`).join("") : emptyRow(6, "No se encontraron equipos");
  }

  async function cargarEquipos() {
    const params = new URLSearchParams();
    const busqueda = $("#equipment-search").value.trim();
    const estado = $("#equipment-status-filter").value;
    const categoria = $("#equipment-category-filter").value;
    if (busqueda) params.set("busqueda", busqueda);
    if (estado) params.set("estado", estado);
    if (categoria) params.set("categoria", categoria);

    const response = await fetch(`equipos_api.php?${params.toString()}`, {
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    });
    const resultado = await response.json();
    if (!response.ok) throw new Error(resultado.error || "No fue posible consultar los equipos.");

    const mapaGuardado = JSON.parse(localStorage.getItem(EQUIPMENT_LEGACY_MAP_KEY) || "{}");
    const mapaLegado = Object.fromEntries(Object.entries(mapaGuardado).map(([id, legacyId]) => [legacyId, id]));
    data.equipment = resultado.catalogo.map((equipo) => ({
      ...equipo,
      legacyId: mapaGuardado[equipo.id] ?? null
    }));
    visibleEquipment = resultado.equipos;
    $("#equipment-count").textContent = resultado.total;
    const filtroCategoria = $("#equipment-category-filter");
    const categoriaSeleccionada = filtroCategoria.value;
    filtroCategoria.innerHTML = `<option value="">Todas las categorías</option>${resultado.categorias.map((item) => `<option value="${escapeHtml(item)}">${escapeHtml(item)}</option>`).join("")}`;
    if (resultado.categorias.includes(categoriaSeleccionada)) filtroCategoria.value = categoriaSeleccionada;
    ["#history-equipment-filter", "#report-equipment"].forEach((selector) => {
      const filtro = $(selector);
      const valorSeleccionado = filtro.value;
      filtro.innerHTML = `<option value="">Todos los equipos</option>${data.equipment.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.code)} · ${escapeHtml(item.name)}</option>`).join("")}`;
      if (data.equipment.some((item) => item.id === valorSeleccionado)) filtro.value = valorSeleccionado;
    });
    renderEquipment();
  }

  async function migrarEquiposLocales() {
    if (localStorage.getItem(EQUIPMENT_MIGRATION_KEY) === "complete") return;
    if (legacyEquipment.length === 0) {
      localStorage.setItem(EQUIPMENT_MIGRATION_KEY, "complete");
      return;
    }

    const response = await fetch("equipos_api.php", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-Token": window.MANTIX_CSRF_TOKEN
      },
      body: JSON.stringify({ accion: "migrar_localstorage", equipos: legacyEquipment })
    });
    const resultado = await response.json();
    if (!response.ok) throw new Error(resultado.error || "No se pudieron migrar los equipos guardados localmente.");

    const mapaLegado = JSON.parse(localStorage.getItem(EQUIPMENT_LEGACY_MAP_KEY) || "{}");
    resultado.mapeo_legado.forEach(({ id, legacy_id }) => {
      mapaLegado[id] = legacy_id;
    });
    localStorage.setItem(EQUIPMENT_LEGACY_MAP_KEY, JSON.stringify(mapaLegado));

    const datosGuardados = JSON.parse(localStorage.getItem(STORAGE_KEY) || "{}");
    delete datosGuardados.equipment;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(datosGuardados));
    localStorage.setItem(EQUIPMENT_MIGRATION_KEY, "complete");
  }

  async function migrarOtrosDatosLocales() {
    if (localStorage.getItem(SYSTEM_MIGRATION_KEY) === "complete") return;
    if (legacyMaintenance.length === 0 && legacyUsers.length === 0) {
      localStorage.setItem(SYSTEM_MIGRATION_KEY, "complete");
      return;
    }

    const legacyMap = JSON.parse(localStorage.getItem(EQUIPMENT_LEGACY_MAP_KEY) || "{}");
    const equiposMapeados = Object.fromEntries(
      Object.entries(legacyMap).map(([id, legacyId]) => [legacyId, id])
    );
    const response = await fetch("sistema_api.php", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-Token": window.MANTIX_CSRF_TOKEN
      },
      body: JSON.stringify({
        recurso: "migracion_localstorage",
        usuarios: legacyUsers,
        mantenimientos: legacyMaintenance,
        equipos_map: equiposMapeados
      })
    });
    const resultado = await response.json();
    if (!response.ok) throw new Error(resultado.error || "No se pudieron migrar los datos guardados localmente.");

    const datosGuardados = JSON.parse(localStorage.getItem(STORAGE_KEY) || "{}");
    delete datosGuardados.maintenance;
    delete datosGuardados.users;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(datosGuardados));
    localStorage.setItem(SYSTEM_MIGRATION_KEY, "complete");
    if (resultado.usuarios_importados > 0) {
      showToast(`${resultado.usuarios_importados} cuenta(s) local(es) se importaron inactivas. Asigna una contraseña antes de activarlas.`, "error");
    }
  }

  async function cargarSistema() {
    const response = await fetch("sistema_api.php", {
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    });
    const resultado = await response.json();
    if (!response.ok) throw new Error(resultado.error || "No fue posible consultar los datos del sistema.");
    data.maintenance = resultado.mantenimientos;
    data.users = resultado.usuarios;
    maintenanceTypes = resultado.tipos_mantenimiento;
  }

  async function recargarDatosSistema() {
    await Promise.all([cargarSistema(), cargarEquipos()]);
  }

  async function guardarMantenimiento(values, id) {
    const resultado = await solicitarSistema("mantenimiento", id ? "PUT" : "POST", {
      id,
      equipo_id: values.equipmentId,
      tipo_codigo: values.typeCode,
      responsable_usuario_id: values.responsibleId,
      fecha_programada: values.date,
      descripcion: values.description.trim(),
      observaciones: values.observations.trim()
    });
    await cargarSistema();
    return resultado.error || "";
  }

  async function completarMantenimiento(id) {
    await solicitarSistema("mantenimiento", "POST", { accion: "completar", id });
    await cargarSistema();
    renderAll();
    showToast("Mantenimiento marcado como completado.");
  }

  async function eliminarMantenimiento(id) {
    await solicitarSistema("mantenimiento", "DELETE", { id });
    await cargarSistema();
    renderAll();
    showToast("El mantenimiento se eliminó correctamente.");
  }

  async function guardarUsuario(values, id) {
    const resultado = await solicitarSistema("usuario", id ? "PUT" : "POST", {
      id,
      nombre: values.name.trim(),
      correo: values.email.trim(),
      rol_codigo: values.roleCode,
      estado: values.status,
      contrasena: values.password || ""
    });
    await cargarSistema();
    return resultado.error || "";
  }

  async function eliminarUsuario(id) {
    await solicitarSistema("usuario", "DELETE", { id });
    await cargarSistema();
    renderAll();
    showToast("El usuario se eliminó correctamente.");
  }

  async function guardarEquipo(values, id) {
    const limites = { code: 50, name: 150, category: 100, brand: 100, model: 100, location: 180 };
    for (const [campo, limite] of Object.entries(limites)) {
      if (!values[campo]?.trim()) return "Completa todos los campos del equipo.";
      if (values[campo].trim().length > limite) return "Uno o más campos superan la longitud permitida.";
    }
    if (!["Operativo", "En mantenimiento", "Fuera de servicio"].includes(values.status)) {
      return "Selecciona un estado válido para el equipo.";
    }

    const payload = {
      id,
      codigo: values.code.trim(),
      nombre: values.name.trim(),
      categoria: values.category.trim(),
      marca: values.brand.trim(),
      modelo: values.model.trim(),
      ubicacion: values.location.trim(),
      estado: values.status
    };
    const response = await fetch("equipos_api.php", {
      method: id ? "PUT" : "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-Token": window.MANTIX_CSRF_TOKEN
      },
      body: JSON.stringify(payload)
    });
    const resultado = await response.json();
    if (!response.ok) return resultado.error || "No se pudo guardar el equipo.";

    await cargarEquipos();
    return "";
  }

  function renderMaintenance() {
    const term = $("#maintenance-search").value.toLowerCase().trim();
    const status = $("#maintenance-status-filter").value;
    const type = $("#maintenance-type-filter").value;
    const rows = data.maintenance.filter((item) => {
      const equipment = equipmentFor(item.equipmentId);
      return `${equipment?.name || item.equipmentName || ""} ${equipment?.code || item.equipmentCode || ""} ${item.responsible} ${item.description}`.toLowerCase().includes(term) && (!status || maintenanceStatus(item) === status) && (!type || item.type === type);
    }).sort((a, b) => a.date.localeCompare(b.date));
    $("#maintenance-table").innerHTML = rows.length ? rows.map((item) => `<tr><td><div class="table-entity"><span class="entity-icon">${icon("tools")}</span><span><strong>${escapeHtml(equipmentFor(item.equipmentId)?.name || item.equipmentName || "Equipo eliminado")}</strong><small>${escapeHtml(equipmentFor(item.equipmentId)?.code || item.equipmentCode || "—")}</small></span></div></td><td><span class="type-label ${item.type.toLowerCase()}">${escapeHtml(item.type)}</span></td><td>${formatDate(item.date)}</td><td>${escapeHtml(item.responsible)}</td><td>${statusBadge(maintenanceStatus(item))}</td><td class="row-actions">${maintenanceStatus(item) !== "Completado" ? `<button class="action-button complete-action" data-action="complete-maintenance" data-id="${item.id}" aria-label="Marcar como completado" title="Marcar como completado">${icon("check")}</button><button class="action-button" data-action="edit-maintenance" data-id="${item.id}" aria-label="Editar mantenimiento">${icon("edit")}</button>` : ""}<button class="action-button danger" data-action="delete-maintenance" data-id="${item.id}" aria-label="Eliminar mantenimiento">${icon("trash")}</button></td></tr>`).join("") : emptyRow(6, "No se encontraron mantenimientos");
  }

  function renderHistory() {
    const term = $("#history-search").value.toLowerCase().trim();
    const type = $("#history-type-filter").value;
    const from = $("#history-from").value;
    const to = $("#history-to").value;
    const equipmentId = $("#history-equipment-filter").value;
    const rows = data.maintenance.filter((item) => {
      const date = item.completedDate || item.date;
      return maintenanceStatus(item) === "Completado"
        && (!type || item.typeCode === type)
        && (!from || date >= from)
        && (!to || date <= to)
        && (!equipmentId || String(item.equipmentId) === equipmentId)
        && `${equipmentFor(item.equipmentId)?.name || item.equipmentName || ""} ${item.responsible} ${item.description} ${item.observations}`.toLowerCase().includes(term);
    }).sort((a, b) => (b.completedDate || b.date).localeCompare(a.completedDate || a.date));
    $("#history-table").innerHTML = rows.length ? rows.map((item) => `<tr><td><div class="table-entity"><span class="entity-icon">${icon("box")}</span><span><strong>${escapeHtml(equipmentFor(item.equipmentId)?.name || item.equipmentName || "Equipo eliminado")}</strong><small>${escapeHtml(equipmentFor(item.equipmentId)?.code || item.equipmentCode || "—")}</small></span></div></td><td>${formatDate(item.date)}</td><td>${formatDate(item.completedDate)}</td><td><span class="type-label ${item.type.toLowerCase()}">${escapeHtml(item.type)}</span></td><td>${escapeHtml(item.responsible)}</td><td><strong class="cell-primary">${escapeHtml(item.description)}</strong><small class="cell-sub">${escapeHtml(item.observations || "Sin observaciones")}</small></td><td>${statusBadge(item.status)}</td></tr>`).join("") : emptyRow(7, "No hay trabajos finalizados para mostrar");
  }

  function renderUsers() {
    if (!isAdministrator) {
      $("#user-result-count").textContent = "";
      $("#user-table").innerHTML = emptyRow(5, "No tienes permisos para consultar usuarios");
      return;
    }

    const term = $("#user-search").value.toLowerCase().trim();
    const rows = data.users.filter((item) => `${item.name} ${item.email} ${item.role}`.toLowerCase().includes(term));
    $("#user-result-count").textContent = `${rows.length} ${rows.length === 1 ? "usuario" : "usuarios"}`;
    document.querySelector('#view-usuarios [data-action="new-user"]').hidden = !isAdministrator;
    document.querySelector('[data-view="usuarios"]').hidden = !isAdministrator;
    $("#user-table").innerHTML = rows.length ? rows.map((item) => `<tr><td><div class="table-entity"><span class="avatar table-avatar">${escapeHtml(item.name.split(" ").map((word) => word[0]).slice(0, 2).join("").toUpperCase())}</span><span><strong>${escapeHtml(item.name)}</strong><small>Miembro del equipo</small></span></div></td><td>${escapeHtml(item.email)}</td><td><span class="role-pill">${escapeHtml(item.role)}</span></td><td>${statusBadge(item.status)}</td><td class="row-actions">${isAdministrator ? `<button class="action-button" data-action="edit-user" data-id="${item.id}" aria-label="Editar usuario">${icon("edit")}</button><button class="action-button danger" data-action="delete-user" data-id="${item.id}" aria-label="Eliminar usuario">${icon("trash")}</button>` : "—"}</td></tr>`).join("") : emptyRow(5, "No se encontraron usuarios");
  }

  function renderReports() {
    const from = $("#report-from").value;
    const to = $("#report-to").value;
    const status = $("#report-status").value;
    const type = $("#report-type").value;
    const equipmentId = $("#report-equipment").value;
    const rows = data.maintenance.filter((item) => (!from || item.date >= from) && (!to || item.date <= to) && (!type || item.typeCode === type) && (!status || maintenanceStatus(item) === status) && (!equipmentId || String(item.equipmentId) === equipmentId));
    const counts = { Pendiente: 0, Completado: 0, Vencido: 0 };
    rows.forEach((item) => { counts[maintenanceStatus(item)] += 1; });
    const equiposPorEstado = {
      "Total de equipos": data.equipment.length,
      Operativos: data.equipment.filter((item) => item.status === "Operativo").length,
      "En mantenimiento": data.equipment.filter((item) => item.status === "En mantenimiento").length,
      "Fuera de servicio": data.equipment.filter((item) => item.status === "Fuera de servicio").length
    };
    $("#report-stats").innerHTML = `<article class="report-stat"><span>Total de equipos</span><strong>${equiposPorEstado["Total de equipos"]}</strong><small>Inventario actual</small></article><article class="report-stat"><span>Operativos</span><strong class="text-green">${equiposPorEstado.Operativos}</strong><small>Equipos disponibles</small></article><article class="report-stat"><span>En mantenimiento</span><strong class="text-amber">${equiposPorEstado["En mantenimiento"]}</strong><small>Estado del inventario</small></article><article class="report-stat"><span>Fuera de servicio</span><strong class="text-red">${equiposPorEstado["Fuera de servicio"]}</strong><small>Estado del inventario</small></article><article class="report-stat"><span>Total de mantenimientos</span><strong>${rows.length}</strong><small>Con filtros aplicados</small></article><article class="report-stat"><span>Pendientes</span><strong class="text-amber">${counts.Pendiente}</strong><small>Por realizar</small></article><article class="report-stat"><span>Completados</span><strong class="text-green">${counts.Completado}</strong><small>Trabajos finalizados</small></article><article class="report-stat"><span>Vencidos</span><strong class="text-red">${counts.Vencido}</strong><small>Requieren atención</small></article>`;
    const max = Math.max(1, ...Object.values(counts));
    $("#report-bars").innerHTML = Object.entries(counts).map(([label, value]) => `<div class="bar-row"><div class="bar-label"><span>${label}</span><strong>${value}</strong></div><div class="bar-track"><span class="bar-fill ${label.toLowerCase()}" style="width:${Math.max(value ? 8 : 0, value / max * 100)}%"></span></div></div>`).join("");
    const ranking = data.equipment.map((equipment) => ({ equipment, count: rows.filter((item) => String(item.equipmentId) === String(equipment.id) || String(item.equipmentId) === String(equipment.legacyId)).length })).filter((item) => item.count).sort((a, b) => b.count - a.count).slice(0, 5);
    $("#equipment-ranking").innerHTML = ranking.length ? ranking.map(({ equipment, count }, index) => `<div class="ranking-row"><span class="ranking-number">${String(index + 1).padStart(2, "0")}</span><span class="ranking-name"><strong>${escapeHtml(equipment.name)}</strong><small>${escapeHtml(equipment.code)}</small></span><span class="ranking-count">${count} ${count === 1 ? "actividad" : "actividades"}</span></div>`).join("") : `<div class="inline-empty">No hay actividad para los filtros seleccionados.</div>`;
    const responsables = Object.values(rows.reduce((grupos, item) => {
      const nombre = item.responsible || "Sin responsable";
      grupos[nombre] ||= { nombre, cantidad: 0 };
      grupos[nombre].cantidad += 1;
      return grupos;
    }, {})).sort((a, b) => b.cantidad - a.cantidad).slice(0, 5);
    $("#responsible-ranking").innerHTML = responsables.length ? responsables.map((item, index) => `<div class="ranking-row"><span class="ranking-number">${String(index + 1).padStart(2, "0")}</span><span class="ranking-name"><strong>${escapeHtml(item.nombre)}</strong></span><span class="ranking-count">${item.cantidad} ${item.cantidad === 1 ? "actividad" : "actividades"}</span></div>`).join("") : `<div class="inline-empty">No hay actividad para los filtros seleccionados.</div>`;
  }

  function renderAll() {
    renderDashboard();
    renderEquipment();
    renderMaintenance();
    renderHistory();
    renderUsers();
    renderReports();
  }

  function openModal(title, eyebrow, fields, record, onSave) {
    $("#modal-title").textContent = title;
    $("#modal-eyebrow").textContent = eyebrow;
    $("#modal-body").innerHTML = `<form id="record-form" novalidate><div class="form-grid">${fields.map((field) => {
      const value = record?.[field.name] ?? field.default ?? "";
      const required = field.required === false ? "" : "required";
      if (field.type === "select") return `<label class="form-field ${field.wide ? "field-wide" : ""}">${field.label}<select class="input-control" name="${field.name}" ${required}><option value="">Selecciona una opción</option>${field.options.map((option) => `<option value="${escapeHtml(option.value)}" ${String(value) === String(option.value) ? "selected" : ""}>${escapeHtml(option.label)}</option>`).join("")}</select></label>`;
      return `<label class="form-field ${field.wide ? "field-wide" : ""}">${field.label}<input class="input-control" type="${field.type || "text"}" name="${field.name}" value="${escapeHtml(value)}" placeholder="${escapeHtml(field.placeholder || "")}" ${field.maxLength ? `maxlength="${field.maxLength}"` : ""} ${field.minLength ? `minlength="${field.minLength}"` : ""} ${required} ${field.type === "email" ? 'autocomplete="email"' : ""}></label>`;
    }).join("")}</div><div class="modal-footer"><button type="button" class="button button-secondary" data-close-modal>Cancelar</button><button type="submit" class="button button-primary">${record ? "Guardar cambios" : "Guardar registro"}</button></div></form>`;
    $("#modal-layer").classList.add("open");
    $("#modal-layer").setAttribute("aria-hidden", "false");
    $("#modal-body").querySelector("input, select")?.focus();
    $("#record-form").addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      if (!form.reportValidity()) return;
      const values = Object.fromEntries(new FormData(form).entries());
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        const error = await onSave(values);
        if (error) {
          showToast(error, "error");
          return;
        }
        closeModal();
        renderAll();
        showToast(record ? "Los cambios se guardaron correctamente." : "El registro se agregó correctamente.");
      } catch (error) {
        showToast(error.message || "No se pudo completar la operación.", "error");
      } finally {
        submit.disabled = false;
      }
    });
  }

  function closeModal() {
    $("#modal-layer").classList.remove("open");
    $("#modal-layer").setAttribute("aria-hidden", "true");
  }

  function equipmentFields() {
    return [
      { kind: "equipment", name: "code", label: "Código del equipo", placeholder: "Ej. EQ-1007", maxLength: 50 },
      { kind: "equipment", name: "name", label: "Nombre del equipo", placeholder: "Ej. Compresor industrial", maxLength: 150 },
      { kind: "equipment", name: "category", label: "Categoría", placeholder: "Ej. Maquinaria", maxLength: 100 },
      { kind: "equipment", name: "brand", label: "Marca", placeholder: "Ej. Atlas Copco", maxLength: 100 },
      { kind: "equipment", name: "model", label: "Modelo", placeholder: "Ej. GA 30", maxLength: 100 },
      { kind: "equipment", name: "location", label: "Ubicación", placeholder: "Ej. Planta · Zona A", maxLength: 180 },
      { name: "status", label: "Estado", type: "select", options: ["Operativo", "En mantenimiento", "Fuera de servicio"].map((item) => ({ value: item, label: item })) }
    ];
  }

  function maintenanceFields() {
    return [
      { name: "equipmentId", label: "Equipo", type: "select", wide: true, options: data.equipment.map((item) => ({ value: item.id, label: `${item.code} · ${item.name}` })) },
      { name: "typeCode", label: "Tipo de mantenimiento", type: "select", options: maintenanceTypes.map((item) => ({ value: item.codigo, label: item.nombre })) },
      { name: "date", label: "Fecha programada", type: "date" },
      { name: "responsibleId", label: "Responsable", type: "select", options: data.users.filter((item) => item.status === "Activo").map((item) => ({ value: item.id, label: item.name })) },
      { name: "description", label: "Descripción del trabajo", wide: true, placeholder: "Describe la actividad de mantenimiento" },
      { name: "observations", label: "Observaciones", wide: true, required: false, placeholder: "Notas adicionales (opcional)" }
    ];
  }

  function userFields(record) {
    return [
      { name: "name", label: "Nombre completo", placeholder: "Ej. María Gómez", maxLength: 150 },
      { name: "email", label: "Correo electrónico", type: "email", placeholder: "nombre@empresa.com", maxLength: 254 },
      { name: "roleCode", label: "Rol", type: "select", options: [{ value: "administrador", label: "Administrador" }, { value: "responsable_mantenimiento", label: "Responsable de mantenimiento" }] },
      { name: "password", label: record ? "Nueva contraseña (opcional)" : "Contraseña inicial", type: "password", required: Boolean(!record), maxLength: 72, minLength: record ? 0 : 12, default: "", wide: true, placeholder: "Mínimo 12 caracteres" },
      { name: "status", label: "Estado", type: "select", default: "Activo", options: ["Activo", "Inactivo"].map((item) => ({ value: item, label: item })) }
    ];
  }

  function startCreate(kind, record = null) {
    if (kind === "equipment") {
      openModal(record ? "Editar equipo" : "Registrar equipo", "INVENTARIO DE ACTIVOS", equipmentFields(), record, (values) => guardarEquipo(values, record?.id));
      return;
    }
    if (kind === "maintenance") {
      if (!data.equipment.length) {
        showToast("Registra un equipo antes de programar un mantenimiento.", "error");
        return;
      }
      const fields = maintenanceFields();
      if (!record) fields.find((item) => item.name === "date").default = todayKey();
      openModal(record ? "Editar mantenimiento" : "Programar mantenimiento", "PLANIFICACIÓN", fields, record, (values) => guardarMantenimiento(values, record?.id));
      return;
    }
    openModal(record ? "Editar usuario" : "Agregar usuario", "GESTIÓN DE ACCESOS", userFields(record), record, (values) => guardarUsuario(values, record?.id));
  }

  function performAction(action, id) {
    const record = (collection) => data[collection].find((item) => item.id === id);
    if (action === "new-equipment") startCreate("equipment");
    if (action === "new-maintenance") startCreate("maintenance");
    if (action === "new-user") startCreate("user");
    if (action === "edit-equipment") startCreate("equipment", record("equipment"));
    if (action === "edit-maintenance") startCreate("maintenance", record("maintenance"));
    if (action === "edit-user") startCreate("user", record("users"));
    if (action === "delete-equipment") {
      const equipment = record("equipment");
      if (!equipment || !window.confirm(`¿Eliminar "${equipment.name}"? Los registros relacionados conservarán la información histórica del equipo.`)) return;
      eliminarEquipo(id).catch((error) => showToast(error.message, "error"));
    }
    if (action === "delete-maintenance") {
      if (!window.confirm("¿Eliminar este registro de mantenimiento?")) return;
      eliminarMantenimiento(id).catch((error) => showToast(error.message, "error"));
    }
    if (action === "delete-user") {
      const user = record("users");
      if (!user || user.id === String(currentUser.id)) {
        showToast("No puedes eliminar tu propia cuenta.", "error");
        return;
      }
      if (!window.confirm(`¿Eliminar al usuario "${user.name}"?`)) return;
      eliminarUsuario(id).catch((error) => showToast(error.message, "error"));
    }
    if (action === "complete-maintenance") {
      const item = record("maintenance");
      if (!item || item.status === "Completado") return;
      completarMantenimiento(id).catch((error) => showToast(error.message, "error"));
    }
  }

  async function eliminarEquipo(id) {
    const response = await fetch("equipos_api.php", {
      method: "DELETE",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-Token": window.MANTIX_CSRF_TOKEN
      },
      body: JSON.stringify({ id })
    });
    const resultado = await response.json();
    if (!response.ok) throw new Error(resultado.error || "No se pudo eliminar el equipo.");
    await cargarEquipos();
    renderAll();
    showToast("El equipo se eliminó; el historial relacionado se conserva.");
  }

  document.querySelectorAll(".nav-link[data-view]").forEach((button) => button.addEventListener("click", () => updateView(button.dataset.view)));
  document.addEventListener("click", (event) => {
    const actionButton = event.target.closest("[data-action]");
    if (actionButton) performAction(actionButton.dataset.action, actionButton.dataset.id);
    const goto = event.target.closest("[data-goto]");
    if (goto) updateView(goto.dataset.goto);
    if (event.target.closest("[data-close-modal]")) closeModal();
  });
  $("#modal-layer").addEventListener("click", (event) => {
    if (event.target.classList.contains("modal-backdrop")) closeModal();
  });
  $("#menu-toggle").addEventListener("click", () => {
    $("#sidebar").classList.toggle("sidebar-open");
    $("#sidebar-scrim").classList.toggle("visible");
  });
  $("#sidebar-scrim").addEventListener("click", () => {
    $("#sidebar").classList.remove("sidebar-open");
    $("#sidebar-scrim").classList.remove("visible");
  });
  $("#profile-button").addEventListener("click", () => $("#logout-button").click());
  $(".notification-button").addEventListener("click", () => {
    const overdue = data.maintenance.filter((item) => maintenanceStatus(item) === "Vencido").length;
    showToast(overdue ? `Tienes ${overdue} mantenimiento${overdue === 1 ? "" : "s"} vencido${overdue === 1 ? "" : "s"} por atender.` : "No tienes mantenimientos vencidos.", overdue ? "error" : "success");
  });
  let equipmentSearchTimer;
  $("#equipment-search").addEventListener("input", () => {
    window.clearTimeout(equipmentSearchTimer);
    equipmentSearchTimer = window.setTimeout(() => cargarEquipos().catch((error) => showToast(error.message, "error")), 250);
  });
  $("#equipment-status-filter").addEventListener("change", () => cargarEquipos().catch((error) => showToast(error.message, "error")));
  $("#equipment-category-filter").addEventListener("change", () => cargarEquipos().catch((error) => showToast(error.message, "error")));
  $("#maintenance-search").addEventListener("input", renderMaintenance);
  $("#maintenance-status-filter").addEventListener("change", renderMaintenance);
  $("#maintenance-type-filter").addEventListener("change", renderMaintenance);
  $("#history-search").addEventListener("input", renderHistory);
  ["#history-from", "#history-to", "#history-equipment-filter", "#history-type-filter"].forEach((selector) => $(selector).addEventListener("change", renderHistory));
  $("#user-search").addEventListener("input", renderUsers);
  ["#report-from", "#report-to", "#report-status", "#report-type", "#report-equipment"].forEach((selector) => $(selector).addEventListener("change", renderReports));
  $("#report-reset").addEventListener("click", () => {
    $("#report-from").value = "";
    $("#report-to").value = "";
    $("#report-status").value = "";
    $("#report-type").value = "";
    $("#report-equipment").value = "";
    renderReports();
  });

  migrarEquiposLocales()
    .catch((error) => {
      showToast(error.message || "No se pudieron migrar los equipos locales.", "error");
      throw error;
    })
    .then(() => migrarOtrosDatosLocales())
    .then(() => recargarDatosSistema())
    .then(() => renderAll())
    .catch((error) => {
      renderAll();
      showToast(error.message || "No se pudieron cargar los equipos.", "error");
    });
}
