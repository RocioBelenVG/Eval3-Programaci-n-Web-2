// Combustible, mantenciones y flota.
import { get, post, patch } from '../api.js';
import {
  html, raw, fmt, badge, ESTADOS, toast, anunciar, validarFormulario, erroresDeApi, ocupado, modal,
  paginacion, cargando, vacio, errorCarga, debounce,
} from '../ui.js';

/** Listado paginado genérico: pinta filas con `fila()` y vuelve a cargar al cambiar de página. */
function listado(zona, { ruta, query, cabecera, fila, caption, vacioTxt, signal }) {
  const estado = { page: 1 };
  async function cargar() {
    zona.setAttribute('aria-busy', 'true');
    zona.innerHTML = String(cargando(5));
    try {
      const r = await get(ruta, { ...query(), page: estado.page, per_page: 15 }, signal);
      zona.innerHTML = '';
      if (r.meta.total === 0) { zona.innerHTML = String(vacio(...vacioTxt)); return r; }
      const w = document.createElement('div');
      w.className = 'tt-table-wrap';
      w.innerHTML = html`<table class="tt-table tt-table--stack"><caption>${caption}</caption>
        <thead><tr>${cabecera.map((c) => html`<th scope="col" ${raw(c.num ? 'class="tt-num"' : '')}>${c.t}</th>`)}</tr></thead>
        <tbody>${r.data.map(fila)}</tbody></table>`;
      zona.append(w, paginacion(r.meta, (p) => { estado.page = p; cargar(); }));
      return r;
    } catch (err) {
      if (err.name !== 'AbortError') zona.innerHTML = String(errorCarga(err));
    } finally { zona.setAttribute('aria-busy', 'false'); }
  }
  return { cargar, reiniciar: () => { estado.page = 1; return cargar(); } };
}

// ── Combustible ─────────────────────────────────────────────────────────────
export async function cargas({ montar, rol, signal }) {
  const puedeRegistrar = rol !== 'Gerencia';
  const vehiculos = (await get('vehiculos', { per_page: 100 }, signal)).data.filter((v) => v.activo);
  const raiz = document.createElement('div');
  raiz.innerHTML = html`
    <div class="tt-page-head"><div><p class="tt-breadcrumb">Operación</p><h1>Combustible</h1></div>
      ${puedeRegistrar ? html`<button type="button" class="tt-btn tt-btn--accent" id="nueva"><span aria-hidden="true">＋</span> Registrar carga</button>` : ''}</div>
    ${rol === 'Conductor' ? html`<p class="tt-nota">Se muestran solo las cargas que usted registró.</p>` : ''}
    <form class="tt-filters tt-card" role="search" aria-label="Filtrar cargas">
      <div class="tt-field"><label class="tt-label" for="c-veh">Vehículo</label>
        <select class="tt-select" id="c-veh" name="vehiculo_id"><option value="">Todos</option>
          ${vehiculos.map((v) => html`<option value="${v.id}">${v.patente}</option>`)}</select></div>
      <div class="tt-field"><label class="tt-label" for="c-desde">Desde</label><input class="tt-input" id="c-desde" name="desde" type="date"></div>
      <div class="tt-field"><label class="tt-label" for="c-hasta">Hasta</label><input class="tt-input" id="c-hasta" name="hasta" type="date"></div>
      <button class="tt-btn tt-btn--secondary" type="reset">Limpiar filtros</button>
    </form>
    <div id="lista" aria-live="polite"></div>`;
  montar(raiz);
  const f = raiz.querySelector('form');
  const l = listado(raiz.querySelector('#lista'), {
    ruta: 'cargas', signal, caption: 'Cargas de combustible, de la más reciente a la más antigua',
    query: () => Object.fromEntries(new FormData(f)),
    vacioTxt: ['Sin cargas', 'No hay cargas de combustible para los filtros elegidos.'],
    cabecera: [{ t: 'Fecha' }, { t: 'Unidad' }, { t: 'Litros', num: 1 }, { t: 'Precio por litro', num: 1 }, { t: 'Costo', num: 1 }, { t: 'Odómetro / horómetro', num: 1 }],
    fila: (c) => html`<tr><td data-label="Fecha">${fmt.fecha(c.fecha)}</td><td data-label="Unidad">${c.unidad} <span class="tt-nota">(${c.tipo_unidad})</span></td>
      <td data-label="Litros" class="tt-num">${fmt.n1(c.litros)}</td><td data-label="Precio por litro" class="tt-num">${fmt.clp(c.precio_litro)}</td>
      <td data-label="Costo" class="tt-num">${fmt.clp(c.costo_total)}</td>
      <td data-label="Odómetro / horómetro" class="tt-num">${c.odometro !== null ? `${fmt.n(c.odometro)} km` : c.horometro !== null ? `${fmt.n1(c.horometro)} h` : '—'}</td></tr>`,
  });
  f.addEventListener('change', () => l.reiniciar());
  f.addEventListener('reset', () => setTimeout(() => l.reiniciar()));
  await l.cargar();

  raiz.querySelector('#nueva')?.addEventListener('click', async () => {
    const equipos = await get('equipos');
    modal({
      titulo: 'Registrar carga de combustible', confirmar: 'Registrar',
      cuerpo: html`
        <fieldset class="tt-field" style="border:0;padding:0;margin:0 0 1rem"><legend class="tt-label">Tipo de unidad</legend>
          <label><input type="radio" name="tipo_unidad" value="vehiculo" checked> Vehículo (máx. 600 L)</label>
          <label style="margin-left:1rem"><input type="radio" name="tipo_unidad" value="equipo"> Equipo (máx. 1.500 L)</label></fieldset>
        <div class="tt-field" data-para="vehiculo"><label class="tt-label" for="n-veh">Vehículo <span class="tt-req" aria-hidden="true">*</span></label>
          <select class="tt-select" id="n-veh" name="vehiculo_id" required><option value="">Seleccione…</option>${vehiculos.map((v) => html`<option value="${v.id}">${v.patente}</option>`)}</select></div>
        <div class="tt-field" data-para="equipo" hidden><label class="tt-label" for="n-eq">Equipo <span class="tt-req" aria-hidden="true">*</span></label>
          <select class="tt-select" id="n-eq" name="equipo_id" required disabled><option value="">Seleccione…</option>${equipos.map((e) => html`<option value="${e.id}">${e.codigo} · ${e.nombre}</option>`)}</select></div>
        <div class="tt-field"><label class="tt-label" for="n-fecha">Fecha <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="n-fecha" name="fecha" type="date" required max="${fmt.hoy()}" value="${fmt.hoy()}"></div>
        <div class="tt-field"><label class="tt-label" for="n-lit">Litros <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="n-lit" name="litros" type="number" inputmode="decimal" required min="0.01" max="600" step="0.01"></div>
        <div class="tt-field"><label class="tt-label" for="n-pre">Precio por litro (CLP)</label>
          <input class="tt-input" id="n-pre" name="precio_litro" type="number" inputmode="decimal" min="1" max="10000" step="0.01"></div>
        <div class="tt-field" data-para="vehiculo"><label class="tt-label" for="n-odo">Odómetro (km)</label>
          <input class="tt-input" id="n-odo" name="odometro" type="number" inputmode="numeric" min="0" step="1"></div>
        <div class="tt-field" data-para="equipo" hidden><label class="tt-label" for="n-hor">Horómetro (h)</label>
          <input class="tt-input" id="n-hor" name="horometro" type="number" inputmode="decimal" min="0" step="0.1" disabled></div>`,
      alAceptar: async (form) => {
        const fd = new FormData(form);
        const n = (k) => (fd.get(k) ? Number(fd.get(k)) : null);
        const r = await post('cargas', {
          tipo_unidad: fd.get('tipo_unidad'), vehiculo_id: n('vehiculo_id'), equipo_id: n('equipo_id'), fecha: fd.get('fecha'),
          litros: n('litros'), precio_litro: n('precio_litro'), odometro: n('odometro'), horometro: n('horometro'),
        });
        toast(`Carga registrada: ${fmt.n1(r.litros)} L en ${r.unidad}.`);
        l.reiniciar();
      },
    });
    // Mostrar solo los campos del tipo elegido (los ocultos se deshabilitan para no validarlos ni enviarlos)
    const d = document.querySelector('dialog[open]');
    d.addEventListener('change', (e) => {
      if (e.target.name !== 'tipo_unidad') return;
      const tipo = e.target.value;
      d.querySelectorAll('[data-para]').forEach((div) => {
        const ver = div.dataset.para === tipo;
        div.hidden = !ver;
        div.querySelectorAll('input, select').forEach((c) => { c.disabled = !ver; });
      });
      d.querySelector('#n-lit').max = tipo === 'vehiculo' ? '600' : '1500';
    });
  });
}

// ── Mantenciones ────────────────────────────────────────────────────────────
export async function mantenciones({ montar, rol, signal }) {
  const gestiona = rol !== 'Gerencia';
  const raiz = document.createElement('div');
  raiz.innerHTML = html`
    <div class="tt-page-head"><div><p class="tt-breadcrumb">Flota</p><h1>Mantenciones</h1></div>
      ${gestiona ? html`<button type="button" class="tt-btn tt-btn--accent" id="nueva"><span aria-hidden="true">＋</span> Registrar mantención</button>` : ''}</div>
    <form class="tt-filters tt-filters--3 tt-card" role="search" aria-label="Filtrar mantenciones">
      <div class="tt-field"><label class="tt-label" for="m-est">Estado</label>
        <select class="tt-select" id="m-est" name="estado"><option value="">Todos</option>
          ${['pendiente', 'en_proceso', 'completada'].map((e) => html`<option value="${e}">${ESTADOS[e]}</option>`)}</select></div>
    </form>
    <div id="lista" aria-live="polite"></div>`;
  montar(raiz);
  const f = raiz.querySelector('form');
  const SIG = { pendiente: ['en_proceso', 'Iniciar'], en_proceso: ['completada', 'Completar'] };
  const l = listado(raiz.querySelector('#lista'), {
    ruta: 'mantenciones', signal, caption: 'Mantenciones, de la más reciente a la más antigua',
    query: () => Object.fromEntries(new FormData(f)),
    vacioTxt: ['Sin mantenciones', 'No hay mantenciones con ese estado.'],
    cabecera: [{ t: 'Fecha' }, { t: 'Equipo' }, { t: 'Tipo' }, { t: 'Costo', num: 1 }, { t: 'Estado' }, ...(gestiona ? [{ t: 'Acción' }] : [])],
    fila: (m) => html`<tr><td data-label="Fecha">${fmt.fecha(m.fecha)}</td><td data-label="Equipo">${m.equipo.codigo} · ${m.equipo.nombre}</td>
      <td data-label="Tipo">${m.tipo === 'correctiva' ? 'Correctiva' : 'Preventiva'}</td><td data-label="Costo" class="tt-num">${fmt.clp(m.costo_total)}</td>
      <td data-label="Estado">${badge(m.estado)}</td>
      ${gestiona ? html`<td data-label="Acción">${SIG[m.estado] ? html`<button type="button" class="tt-btn tt-btn--secondary tt-btn--sm" data-id="${m.id}" data-estado="${SIG[m.estado][0]}" aria-label="${SIG[m.estado][1]} mantención de ${m.equipo.codigo} del ${fmt.fecha(m.fecha)}">${SIG[m.estado][1]}</button>` : '—'}</td>` : ''}</tr>`,
  });
  f.addEventListener('change', () => l.reiniciar());
  raiz.querySelector('#lista').addEventListener('click', async (e) => {
    const b = e.target.closest('button[data-estado]');
    if (!b) return;
    try {
      await ocupado(b, patch(`mantenciones/${b.dataset.id}/estado`, { estado: b.dataset.estado }));
      toast(`Mantención en estado «${ESTADOS[b.dataset.estado]}».`);
      await l.cargar();
      raiz.querySelector('h1').focus();
    } catch (err) { toast(err.message, 'danger'); }
  });
  await l.cargar();

  raiz.querySelector('#nueva')?.addEventListener('click', async () => {
    const equipos = await get('equipos');
    modal({
      titulo: 'Registrar mantención', confirmar: 'Registrar',
      cuerpo: html`
        <div class="tt-field"><label class="tt-label" for="r-eq">Equipo <span class="tt-req" aria-hidden="true">*</span></label>
          <select class="tt-select" id="r-eq" name="equipo_id" required><option value="">Seleccione…</option>${equipos.map((e) => html`<option value="${e.id}">${e.codigo} · ${e.nombre}</option>`)}</select></div>
        <div class="tt-field"><label class="tt-label" for="r-tipo">Tipo <span class="tt-req" aria-hidden="true">*</span></label>
          <select class="tt-select" id="r-tipo" name="tipo" required><option value="preventiva">Preventiva</option><option value="correctiva">Correctiva</option></select></div>
        <div class="tt-field"><label class="tt-label" for="r-fecha">Fecha <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="r-fecha" name="fecha" type="date" required value="${fmt.hoy()}"></div>
        <div class="tt-field"><label class="tt-label" for="r-costo">Costo estimado (CLP)</label>
          <input class="tt-input" id="r-costo" name="costo_total" type="number" inputmode="numeric" min="0" step="1"></div>
        <div class="tt-field"><label class="tt-label" for="r-desc">Descripción</label>
          <textarea class="tt-textarea" id="r-desc" name="descripcion" maxlength="2000" rows="3"></textarea></div>`,
      alAceptar: async (form) => {
        const fd = new FormData(form);
        await post('mantenciones', {
          equipo_id: Number(fd.get('equipo_id')), tipo: fd.get('tipo'), fecha: fd.get('fecha'),
          costo_total: fd.get('costo_total') ? Number(fd.get('costo_total')) : null, descripcion: fd.get('descripcion').trim() || null,
        });
        toast('Mantención registrada como pendiente.');
        l.reiniciar();
      },
    });
  });
}

// ── Flota ───────────────────────────────────────────────────────────────────
export async function vehiculos({ montar, rol, signal }) {
  const verRend = rol !== 'Conductor';
  const raiz = document.createElement('div');
  raiz.innerHTML = html`
    <div class="tt-page-head"><div><p class="tt-breadcrumb">Flota</p><h1>Vehículos</h1></div></div>
    <form class="tt-filters tt-filters--3 tt-card" role="search" aria-label="Buscar vehículos">
      <div class="tt-field"><label class="tt-label" for="b-q">Buscar</label>
        <input class="tt-input" id="b-q" name="q" type="search" maxlength="100" placeholder="Patente, marca o modelo"></div>
    </form>
    <div id="lista" aria-live="polite"></div>`;
  montar(raiz);
  const f = raiz.querySelector('form');
  const l = listado(raiz.querySelector('#lista'), {
    ruta: 'vehiculos', signal, caption: 'Vehículos por patente',
    query: () => ({ q: f.q.value.trim() }),
    vacioTxt: ['Sin vehículos', 'Ningún vehículo coincide con la búsqueda.'],
    cabecera: [{ t: 'Patente' }, { t: 'Marca y modelo' }, { t: 'Año', num: 1 }, { t: 'Odómetro', num: 1 }, { t: 'Estado' }],
    fila: (v) => html`<tr><td data-label="Patente">${verRend ? html`<a href="#/flota/${v.id}" aria-label="Rendimiento de ${v.patente}">${v.patente}</a>` : v.patente}</td>
      <td data-label="Marca y modelo">${v.marca || ''} ${v.modelo || ''}</td><td data-label="Año" class="tt-num">${v.anio ?? '—'}</td>
      <td data-label="Odómetro" class="tt-num">${fmt.n(v.odometro)} km</td><td data-label="Estado">${v.activo ? 'Activo' : 'De baja'}</td></tr>`,
  });
  f.addEventListener('submit', (e) => { e.preventDefault(); l.reiniciar(); });
  f.q.addEventListener('input', debounce(() => l.reiniciar().then((r) => r && anunciar(`${r.meta.total} vehículos`))));
  await l.cargar();
}

export async function rendimiento({ params: [id], montar, signal }) {
  const [serie, lista] = await Promise.all([get(`vehiculos/${id}/rendimiento`, {}, signal), get('vehiculos', { per_page: 100 }, signal)]);
  const v = lista.data.find((x) => x.id === Number(id));
  const tot = serie.reduce((a, m) => ({ km: a.km + m.km, litros: a.litros + m.litros, costo: a.costo + m.costo }), { km: 0, litros: 0, costo: 0 });
  const kmL = tot.litros ? tot.km / tot.litros : null;
  montar(html`
    <div class="tt-page-head"><div><p class="tt-breadcrumb"><a href="#/flota">Flota</a> / ${v?.patente}</p><h1>Rendimiento de ${v?.patente}</h1></div></div>
    <div class="tt-grid tt-grid--3" style="margin-bottom:1.5rem">
      <div class="tt-card tt-kpi tt-kpi--accent"><span class="tt-kpi__label">Rendimiento promedio</span><span class="tt-kpi__value">${fmt.n1(kmL)} km/L</span></div>
      <div class="tt-card tt-kpi"><span class="tt-kpi__label">Km recorridos</span><span class="tt-kpi__value">${fmt.n(tot.km)}</span></div>
      <div class="tt-card tt-kpi"><span class="tt-kpi__label">Costo de combustible</span><span class="tt-kpi__value">${fmt.clp(tot.costo)}</span></div>
    </div>
    ${serie.length === 0 ? vacio('Sin datos', 'Este vehículo no tiene viajes completados con cargas registradas.') : html`
    <div class="tt-table-wrap"><table class="tt-table tt-table--stack"><caption>Rendimiento mensual de ${v?.patente}: km de viajes completados y litros cargados</caption>
      <thead><tr><th scope="col">Mes</th><th scope="col" class="tt-num">Km</th><th scope="col" class="tt-num">Litros</th><th scope="col" class="tt-num">km/L</th><th scope="col" class="tt-num">Costo</th></tr></thead>
      <tbody>${serie.map((m) => html`<tr><td data-label="Mes">${m.mes}</td><td data-label="Km" class="tt-num">${fmt.n(m.km)}</td>
        <td data-label="Litros" class="tt-num">${fmt.n1(m.litros)}</td><td data-label="km/L" class="tt-num">${fmt.n1(m.km_por_litro)}</td>
        <td data-label="Costo" class="tt-num">${fmt.clp(m.costo)}</td></tr>`)}</tbody></table></div>`}
    <p class="tt-nota">Los gráficos y la comparación entre camiones se incorporan en el dashboard (Hito 4).</p>`, `Rendimiento de ${v?.patente ?? 'vehículo'}`);
}
