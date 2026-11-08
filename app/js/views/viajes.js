// Viajes: listado con búsqueda, filtros, orden y paginación; ficha con la máquina de estados; formulario.
import { get, post, put, patch, del } from '../api.js';
import {
  html, raw, fmt, badge, ESTADOS, toast, anunciar, validarFormulario, erroresDeApi, ocupado, modal,
  paginacion, thOrden, cargando, vacio, errorCarga, debounce, mostrarErrores,
} from '../ui.js';

const GESTORES = ['Administrador', 'Supervisor'];

// ── Listado ────────────────────────────────────────────────────────────────
export async function lista({ montar, rol, signal }) {
  const esConductor = rol === 'Conductor';
  const q = new URLSearchParams(location.hash.split('?')[1] || '');
  const estado = { q: q.get('q') || '', estado: q.get('estado') || '', desde: q.get('desde') || '', hasta: q.get('hasta') || '', sort: q.get('sort') || '-fecha', page: Number(q.get('page') || 1) };

  const raiz = document.createElement('div');
  raiz.innerHTML = html`
    <div class="tt-page-head">
      <div><p class="tt-breadcrumb">Operación</p><h1>${esConductor ? 'Mis viajes' : 'Viajes'}</h1></div>
      ${GESTORES.includes(rol) ? html`<a class="tt-btn tt-btn--accent" href="#/viajes/nuevo"><span aria-hidden="true">＋</span> Programar viaje</a>` : ''}
    </div>
    <form class="tt-filters tt-card" role="search" aria-label="Filtrar viajes">
      <div class="tt-field"><label class="tt-label" for="f-q">Buscar</label>
        <input class="tt-input" id="f-q" name="q" type="search" maxlength="100" placeholder="Patente, origen, destino, conductor u obra" value="${estado.q}"></div>
      <div class="tt-field"><label class="tt-label" for="f-estado">Estado</label>
        <select class="tt-select" id="f-estado" name="estado">
          <option value="">Todos</option>
          ${Object.entries(ESTADOS).slice(0, 4).map(([v, t]) => html`<option value="${v}" ${raw(estado.estado === v ? 'selected' : '')}>${t}</option>`)}
        </select></div>
      <div class="tt-field"><label class="tt-label" for="f-desde">Desde</label>
        <input class="tt-input" id="f-desde" name="desde" type="date" value="${estado.desde}"></div>
      <div class="tt-field"><label class="tt-label" for="f-hasta">Hasta</label>
        <input class="tt-input" id="f-hasta" name="hasta" type="date" value="${estado.hasta}"></div>
      <button class="tt-btn tt-btn--secondary" type="reset">Limpiar filtros</button>
    </form>
    <div id="resultado" aria-live="polite" aria-busy="true"></div>`;
  montar(raiz);

  const form = raiz.querySelector('form');
  const zona = raiz.querySelector('#resultado');

  const sincronizarUrl = () => {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(estado)) if (v && !(k === 'sort' && v === '-fecha') && !(k === 'page' && v === 1)) p.set(k, v);
    history.replaceState(null, '', `#/viajes${p.toString() ? '?' + p : ''}`);
  };

  async function cargar({ anunciarTotal = true } = {}) {
    zona.setAttribute('aria-busy', 'true');
    zona.innerHTML = String(cargando(6));
    sincronizarUrl();
    try {
      const r = await get('viajes', { ...estado, per_page: 15 }, signal);
      zona.innerHTML = '';
      if (r.meta.total === 0) {
        const hayFiltros = estado.q || estado.estado || estado.desde || estado.hasta;
        zona.innerHTML = String(vacio(hayFiltros ? 'Sin resultados' : 'Aún no hay viajes',
          hayFiltros ? 'Ningún viaje coincide con los filtros. Pruebe con otros criterios o limpie los filtros.' : 'Cuando se programen viajes, aparecerán aquí.'));
      } else {
        zona.append(tabla(r.data));
        zona.append(paginacion(r.meta, (p) => { estado.page = p; cargar(); raiz.querySelector('h1').focus(); }));
      }
      if (anunciarTotal) anunciar(`${fmt.n(r.meta.total)} viajes encontrados`);
    } catch (err) {
      if (err.name !== 'AbortError') zona.innerHTML = String(errorCarga(err));
    } finally {
      zona.setAttribute('aria-busy', 'false');
    }
  }

  function tabla(filas) {
    const wrap = document.createElement('div');
    wrap.className = 'tt-table-wrap';
    wrap.innerHTML = html`<table class="tt-table tt-table--stack">
      <caption>Viajes ordenados por ${estado.sort.replace('-', '')} (${estado.sort.startsWith('-') ? 'descendente' : 'ascendente'})</caption>
      <thead><tr>
        <th scope="col">N.º</th>${thOrden('Fecha', 'fecha', estado.sort)}${thOrden('Vehículo', 'vehiculo', estado.sort)}
        <th scope="col">Conductor</th><th scope="col">Ruta</th><th scope="col">Obra</th>${thOrden('Estado', 'estado', estado.sort)}
      </tr></thead>
      <tbody>${filas.map((v) => html`<tr>
        <td data-label="N.º"><a href="#/viajes/${v.id}" aria-label="Ver viaje ${v.id}">#${v.id}</a></td>
        <td data-label="Fecha">${fmt.fecha(v.fecha)}</td>
        <td data-label="Vehículo">${v.vehiculo.patente}</td>
        <td data-label="Conductor">${v.conductor.nombre}</td>
        <td data-label="Ruta">${v.origen || '—'} → ${v.destino || '—'}</td>
        <td data-label="Obra">${v.obra?.nombre || '—'}</td>
        <td data-label="Estado">${badge(v.estado)}</td></tr>`)}</tbody></table>`;
    wrap.addEventListener('click', (e) => {
      const b = e.target.closest('button[data-sort]');
      if (!b) return;
      const c = b.dataset.sort;
      estado.sort = estado.sort === c ? `-${c}` : estado.sort === `-${c}` ? c : (c === 'fecha' ? '-fecha' : c);
      estado.page = 1;
      cargar().then(() => zona.querySelector(`button[data-sort="${c}"]`)?.focus());
    });
    return wrap;
  }

  const aplicar = () => {
    const fd = new FormData(form);
    for (const k of ['q', 'estado', 'desde', 'hasta']) estado[k] = (fd.get(k) || '').toString().trim();
    estado.page = 1;
    cargar();
  };
  form.addEventListener('submit', (e) => { e.preventDefault(); aplicar(); });
  form.q.addEventListener('input', debounce(aplicar));
  form.addEventListener('change', (e) => { if (e.target.name !== 'q') aplicar(); });
  form.addEventListener('reset', () => setTimeout(aplicar));
  await cargar({ anunciarTotal: false });
}

// ── Ficha y máquina de estados ─────────────────────────────────────────────
const ACCIONES = {
  en_ruta: { texto: 'Iniciar viaje', clase: 'tt-btn--primary' },
  completado: { texto: 'Completar viaje', clase: 'tt-btn--accent' },
  cancelado: { texto: 'Cancelar viaje', clase: 'tt-btn--danger' },
};

export async function detalle({ params: [id], montar, rol, signal }) {
  const v = await get(`viajes/${id}`, {}, signal);
  const raiz = document.createElement('div');
  const dato = (t, val) => html`<div><dt>${t}</dt><dd>${val ?? '—'}</dd></div>`;
  raiz.innerHTML = html`
    <div class="tt-page-head">
      <div><p class="tt-breadcrumb"><a href="#/viajes">Viajes</a> / #${v.id}</p>
        <h1>Viaje #${v.id} ${badge(v.estado)}</h1></div>
      <div class="tt-acciones">
        ${v.transiciones_permitidas.map((t) => html`<button type="button" class="tt-btn ${ACCIONES[t].clase}" data-transicion="${t}">${ACCIONES[t].texto}</button>`)}
        ${GESTORES.includes(rol) && v.estado === 'programado' ? html`<a class="tt-btn tt-btn--secondary" href="#/viajes/${v.id}/editar">Modificar</a>` : ''}
        ${rol === 'Administrador' && v.estado === 'programado' ? html`<button type="button" class="tt-btn tt-btn--ghost" id="eliminar">Eliminar</button>` : ''}
        ${v.estado === 'completado' && rol !== 'Gerencia' ? html`<button type="button" class="tt-btn tt-btn--secondary" id="guia">Emitir guía de despacho</button>` : ''}
      </div>
    </div>
    <section class="tt-card" aria-labelledby="h-datos">
      <h2 id="h-datos" class="tt-card__title">Datos del viaje</h2><br>
      <dl class="tt-ficha">
        ${dato('Fecha', fmt.fecha(v.fecha))}${dato('Hora de salida', v.hora_salida)}${dato('Hora de llegada', v.hora_llegada)}
        ${dato('Vehículo', v.vehiculo.patente)}${dato('Conductor', v.conductor.nombre)}${dato('Obra', v.obra?.nombre)}
        ${dato('Origen', v.origen)}${dato('Destino', v.destino)}${dato('Carga', v.cantidad !== null ? `${fmt.n1(v.cantidad)} ${v.unidad || ''}` : null)}
        ${dato('Km inicial', fmt.n(v.km_inicio))}${dato('Km final', fmt.n(v.km_fin))}
        ${dato('Km recorridos', v.km_fin !== null && v.km_inicio !== null ? fmt.n(v.km_fin - v.km_inicio) : null)}
      </dl>
      ${v.observaciones ? html`<h3 style="margin-top:1rem">Observaciones</h3><p style="white-space:pre-line">${v.observaciones}</p>` : ''}
    </section>
    ${v.transiciones_permitidas.length === 0 && v.estado !== 'completado' && v.estado !== 'cancelado'
      ? html`<p class="tt-nota">Su rol puede consultar este viaje, pero no cambiar su estado.</p>` : ''}`;
  montar(raiz, `Viaje #${v.id}`);

  const recargar = (msg) => { toast(msg); detalle({ params: [id], montar, rol, signal }); };

  raiz.querySelectorAll('[data-transicion]').forEach((b) => b.addEventListener('click', () => {
    const t = b.dataset.transicion;
    let cuerpo = html`<p>El viaje pasará de <strong>${ESTADOS[v.estado]}</strong> a <strong>${ESTADOS[t]}</strong>.</p>`;
    if (t === 'completado') {
      cuerpo = html`${cuerpo}<div class="tt-field"><label class="tt-label" for="m-km">Kilometraje final <span class="tt-req" aria-hidden="true">*</span></label>
        <input class="tt-input" id="m-km" name="km_fin" type="number" inputmode="numeric" required min="${v.km_inicio ?? 0}" step="1">
        <p class="tt-hint" id="m-km-ayuda">Debe ser mayor o igual al inicial (${fmt.n(v.km_inicio ?? 0)} km).</p></div>`;
    }
    if (t === 'cancelado') {
      cuerpo = html`${cuerpo}<div class="tt-field"><label class="tt-label" for="m-motivo">Motivo de la cancelación <span class="tt-req" aria-hidden="true">*</span></label>
        <textarea class="tt-textarea" id="m-motivo" name="motivo" required minlength="3" maxlength="255" rows="3"></textarea></div>`;
    }
    modal({
      titulo: ACCIONES[t].texto, cuerpo, confirmar: ACCIONES[t].texto, peligro: t === 'cancelado',
      alAceptar: async (f) => {
        if (f.km_fin) f.km_fin.setAttribute('aria-describedby', 'm-km-ayuda');
        const valido = validarFormulario(f);
        if (!valido()) throw false;
        await patch(`viajes/${v.id}/estado`, { estado: t, km_fin: f.km_fin ? Number(f.km_fin.value) : undefined, motivo: f.motivo?.value.trim() || undefined });
        recargar(`Viaje #${v.id}: ${ESTADOS[t].toLowerCase()}.`);
      },
    });
  }));

  raiz.querySelector('#eliminar')?.addEventListener('click', () => modal({
    titulo: `¿Eliminar el viaje #${v.id}?`, cuerpo: html`<p>Esta acción no se puede deshacer. Un viaje iniciado no se elimina: se cancela.</p>`,
    confirmar: 'Eliminar', peligro: true,
    alAceptar: async () => { await del(`viajes/${v.id}`); toast(`Viaje #${v.id} eliminado.`); location.hash = '#/viajes'; },
  }));

  raiz.querySelector('#guia')?.addEventListener('click', () => modal({
    titulo: 'Emitir guía de despacho', confirmar: 'Emitir guía',
    cuerpo: html`<p class="tt-nota">La guía copia vehículo, conductor, ruta y carga del viaje.</p>
      <div class="tt-field"><label class="tt-label" for="g-num">Número de guía <span class="tt-req" aria-hidden="true">*</span></label>
        <input class="tt-input" id="g-num" name="numero_guia" required maxlength="50" pattern="[A-Za-z0-9\\-]+" data-patron="Use solo letras, números y guiones."></div>
      <div class="tt-field"><label class="tt-label" for="g-rec">Receptor <span class="tt-req" aria-hidden="true">*</span></label>
        <input class="tt-input" id="g-rec" name="receptor" required minlength="2" maxlength="150" autocomplete="name"></div>
      <div class="tt-field"><label class="tt-label" for="g-rut">RUT del receptor</label>
        <input class="tt-input" id="g-rut" name="rut_receptor" maxlength="10" pattern="[0-9]{7,8}-[0-9kK]" data-patron="Use el formato 12345678-9, sin puntos." aria-describedby="g-rut-ayuda">
        <p class="tt-hint" id="g-rut-ayuda">Sin puntos y con guion, por ejemplo 12345678-5.</p></div>`,
    alAceptar: async (f) => {
      if (!validarFormulario(f)()) throw false;
      const g = await post(`viajes/${v.id}/guias`, { numero_guia: f.numero_guia.value.trim(), receptor: f.receptor.value.trim(), rut_receptor: f.rut_receptor.value.trim() || undefined });
      toast(`Guía ${g.numero_guia} emitida.`);
    },
  }));
}

// ── Formulario (programar o modificar) ─────────────────────────────────────
export async function formulario({ params: [id], montar, signal }) {
  const [vehiculos, conductores, obras, viaje] = await Promise.all([
    get('vehiculos', { per_page: 100 }, signal), get('conductores', {}, signal), get('obras', {}, signal),
    id ? get(`viajes/${id}`, {}, signal) : null,
  ]);
  if (viaje && viaje.estado !== 'programado') {
    const e = new Error(); e.status = 404; throw e; // solo se modifica un viaje programado
  }
  const v = viaje || { fecha: fmt.hoy(), unidad: 'm3' };
  const opt = (lista, sel, texto) => lista.map((x) => html`<option value="${x.id}" ${raw(Number(sel) === x.id ? 'selected' : '')}>${texto(x)}</option>`);
  const titulo = viaje ? `Modificar viaje #${viaje.id}` : 'Programar viaje';

  const raiz = document.createElement('div');
  raiz.innerHTML = html`
    <div class="tt-page-head"><div><p class="tt-breadcrumb"><a href="#/viajes">Viajes</a> / ${viaje ? `#${viaje.id}` : 'Nuevo'}</p><h1>${titulo}</h1></div></div>
    <form class="tt-card" id="f-viaje" novalidate>
      <p class="tt-hint">Los campos marcados con <span aria-hidden="true">*</span><span class="tt-sr-only">asterisco</span> son obligatorios.</p>
      <div class="tt-form-grid">
        <div class="tt-field"><label class="tt-label" for="v-veh">Vehículo <span class="tt-req" aria-hidden="true">*</span></label>
          <select class="tt-select" id="v-veh" name="vehiculo_id" required><option value="">Seleccione…</option>
            ${opt(vehiculos.data.filter((x) => x.activo), v.vehiculo_id, (x) => `${x.patente} · ${x.marca || ''} ${x.modelo || ''} (${fmt.n(x.odometro)} km)`)}</select></div>
        <div class="tt-field"><label class="tt-label" for="v-con">Conductor <span class="tt-req" aria-hidden="true">*</span></label>
          <select class="tt-select" id="v-con" name="conductor_id" required aria-describedby="v-con-ayuda"><option value="">Seleccione…</option>
            ${opt(conductores, v.conductor_id, (x) => `${x.nombre}${x.licencia_vence ? ` · licencia vence ${fmt.fecha(x.licencia_vence)}` : ''}`)}</select>
          <p class="tt-hint" id="v-con-ayuda">La licencia debe estar vigente en la fecha del viaje.</p></div>
        <div class="tt-field"><label class="tt-label" for="v-fecha">Fecha <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="v-fecha" name="fecha" type="date" required value="${v.fecha}"></div>
        <div class="tt-field"><label class="tt-label" for="v-hora">Hora de salida</label>
          <input class="tt-input" id="v-hora" name="hora_salida" type="time" value="${v.hora_salida || ''}"></div>
        <div class="tt-field"><label class="tt-label" for="v-ori">Origen <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="v-ori" name="origen" required minlength="2" maxlength="200" value="${v.origen || ''}"></div>
        <div class="tt-field"><label class="tt-label" for="v-des">Destino <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="v-des" name="destino" required minlength="2" maxlength="200" value="${v.destino || ''}"></div>
        <div class="tt-field"><label class="tt-label" for="v-obra">Obra</label>
          <select class="tt-select" id="v-obra" name="obra_id"><option value="">Sin obra</option>${opt(obras, v.obra_id, (x) => x.nombre)}</select></div>
        <div class="tt-field"><label class="tt-label" for="v-km">Km inicial</label>
          <input class="tt-input" id="v-km" name="km_inicio" type="number" inputmode="numeric" min="0" step="1" value="${v.km_inicio ?? ''}" aria-describedby="v-km-ayuda">
          <p class="tt-hint" id="v-km-ayuda">Si lo deja vacío, se usa el odómetro actual del vehículo.</p></div>
        <div class="tt-field"><label class="tt-label" for="v-cant">Cantidad</label>
          <input class="tt-input" id="v-cant" name="cantidad" type="number" inputmode="decimal" min="0" step="0.01" value="${v.cantidad ?? ''}"></div>
        <div class="tt-field"><label class="tt-label" for="v-uni">Unidad</label>
          <select class="tt-select" id="v-uni" name="unidad">${['m3', 'ton', 'viaje'].map((u) => html`<option value="${u}" ${raw(v.unidad === u ? 'selected' : '')}>${u === 'm3' ? 'm³' : u}</option>`)}</select></div>
        <div class="tt-field tt-span-2"><label class="tt-label" for="v-obs">Observaciones</label>
          <textarea class="tt-textarea" id="v-obs" name="observaciones" maxlength="1000" rows="3">${v.observaciones || ''}</textarea></div>
      </div>
      <div class="tt-acciones">
        <button type="submit" class="tt-btn tt-btn--primary">${viaje ? 'Guardar cambios' : 'Programar viaje'}</button>
        <a class="tt-btn tt-btn--secondary" href="${viaje ? `#/viajes/${viaje.id}` : '#/viajes'}">Cancelar</a>
      </div>
    </form>`;
  montar(raiz, titulo);

  const f = raiz.querySelector('form');
  const valido = validarFormulario(f, (form) => ({
    destino: form.destino.value.trim() && form.destino.value.trim().toLowerCase() === form.origen.value.trim().toLowerCase()
      ? 'El destino debe ser distinto del origen.' : '',
  }));
  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!valido()) return;
    const fd = new FormData(f);
    const num = (k) => (fd.get(k) === '' ? null : Number(fd.get(k)));
    const datos = {
      vehiculo_id: num('vehiculo_id'), conductor_id: num('conductor_id'), obra_id: num('obra_id'),
      fecha: fd.get('fecha'), hora_salida: fd.get('hora_salida') || null, origen: fd.get('origen').trim(), destino: fd.get('destino').trim(),
      km_inicio: num('km_inicio'), cantidad: num('cantidad'), unidad: fd.get('unidad'), observaciones: fd.get('observaciones').trim() || null,
    };
    try {
      const r = await ocupado(f.querySelector('[type=submit]'), viaje ? put(`viajes/${viaje.id}`, datos) : post('viajes', datos));
      toast(viaje ? `Viaje #${r.id} actualizado.` : `Viaje #${r.id} programado.`);
      location.hash = `#/viajes/${r.id}`;
    } catch (err) {
      if (err.status === 409) mostrarErrores(f, { _general: err.message }, 'No se pudo guardar');
      else erroresDeApi(f, err);
    }
  });
}
