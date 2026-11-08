// Dashboard (6 indicadores y 4 gráficos accesibles) y reportes con exportación e impresión.
import { get, post, descargar } from '../api.js';
import { html, raw, fmt, toast, anunciar, cargando, errorCarga, vacio, paginacion, ocupado, validarFormulario } from '../ui.js';

// ── Chart.js bajo demanda (CDN permitido por la CSP) ───────────────────────
let chartJs = null;
function cargarChart() {
  chartJs ??= new Promise((ok, ko) => {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
    s.integrity = 'sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC';
    s.crossOrigin = 'anonymous';
    s.onload = () => ok(window.Chart);
    s.onerror = () => { chartJs = null; ko(new Error('No se pudo cargar la librería de gráficos.')); };
    document.head.append(s);
  });
  return chartJs;
}

// Paleta del sistema de diseño (contraste ≥ 3:1 sobre blanco para elementos gráficos)
const C = { navy: '#0f172a', ambar: '#b45309', azul: '#1d4ed8', rojo: '#b91c1c', gris: '#64748b', verde: '#15803d' };
const sinMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const mesCorto = (m) => { const [a, n] = m.split('-'); return `${['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][Number(n) - 1]} ${a.slice(2)}`; };
const hace12 = () => { const d = new Date(); d.setMonth(d.getMonth() - 12); d.setDate(1); return d.toLocaleDateString('sv-SE'); };

function valorKpi(k) {
  if (k.valor === null) return '—';
  return { 'km/L': `${fmt.n1(k.valor)} km/L`, CLP: fmt.clp(k.valor), '%': `${fmt.n1(k.valor)} %`, h: `${fmt.n(k.valor)} h` }[k.unidad] ?? fmt.n(k.valor);
}

function tarjetaKpi(k) {
  const v = k.variacion_vs_periodo_anterior;
  let delta = html`<span class="tt-kpi__delta tt-nota">Sin período anterior para comparar</span>`;
  if (v !== null) {
    const bueno = v === 0 ? null : (v > 0) === (k.mejor_si === 'sube');
    // La flecha indica la dirección; el color y la palabra, si es una mejora o no (nunca solo el color)
    const clase = bueno === null ? '' : bueno ? 'tt-delta--mejora' : 'tt-delta--empeora';
    const palabra = v === 0 ? 'Sin cambio' : `${v > 0 ? '▲ Sube' : '▼ Baja'} ${fmt.n1(Math.abs(v))} %`;
    delta = html`<span class="tt-kpi__delta ${clase}">${palabra} vs. período anterior${bueno === null ? '' : bueno ? ' (mejora)' : ' (empeora)'}</span>`;
  }
  return html`<article class="tt-card tt-kpi tt-kpi--accent" aria-label="${k.titulo}">
    <h2 class="tt-kpi__label">${k.titulo}</h2>
    <p class="tt-kpi__value">${valorKpi(k)}</p>${delta}
    <p class="tt-nota tt-kpi__formula">${k.formula}: ${fmt.n(k.numerador)} ÷ ${fmt.n(k.denominador)}</p>
  </article>`;
}

/** Figura accesible: título, descripción, gráfico con aria-label y tabla de datos desplegable. */
function figuraGrafico(id, serie, formato = fmt.n) {
  const cab = ['', ...serie.datasets.map((d) => d.label)];
  return html`<figure class="tt-card tt-grafico" aria-labelledby="${id}-t" aria-describedby="${id}-d">
    <h2 id="${id}-t" class="tt-card__title">${serie.titulo}</h2>
    <p id="${id}-d" class="tt-nota">${serie.descripcion}</p>
    <div class="tt-grafico__lienzo"><canvas id="${id}" role="img" aria-label="${serie.titulo}. ${serie.resumen || ''} Los valores están en la tabla de datos."></canvas></div>
    <details><summary>Ver tabla de datos</summary>
      <div class="tt-table-wrap"><table class="tt-table"><caption>${serie.titulo}</caption>
        <thead><tr>${cab.map((c, i) => html`<th scope="col" ${raw(i ? 'class="tt-num"' : '')}>${c || 'Categoría'}</th>`)}</tr></thead>
        <tbody>${serie.labels.map((l, i) => html`<tr><th scope="row">${l}</th>${serie.datasets.map((d) => html`<td class="tt-num">${formato(d.data[i])}</td>`)}</tr>`)}</tbody>
      </table></div></details>
  </figure>`;
}

// ── Dashboard ───────────────────────────────────────────────────────────────
export async function dashboard({ montar, signal }) {
  const [obras, vehiculos] = await Promise.all([get('obras', {}, signal), get('vehiculos', { per_page: 100 }, signal)]);
  const raiz = document.createElement('div');
  raiz.innerHTML = html`
    <div class="tt-page-head"><div><p class="tt-breadcrumb">Análisis</p><h1>Dashboard</h1></div>
      <a class="tt-btn tt-btn--secondary" href="#/reportes">Ver reportes</a></div>
    <form class="tt-filters tt-card" role="search" aria-label="Filtrar el dashboard">
      <div class="tt-field"><label class="tt-label" for="d-desde">Desde</label><input class="tt-input" id="d-desde" name="desde" type="date" required value="${hace12()}"></div>
      <div class="tt-field"><label class="tt-label" for="d-hasta">Hasta</label><input class="tt-input" id="d-hasta" name="hasta" type="date" required value="${fmt.hoy()}"></div>
      <div class="tt-field"><label class="tt-label" for="d-obra">Obra</label><select class="tt-select" id="d-obra" name="obra_id"><option value="">Todas</option>${obras.map((o) => html`<option value="${o.id}">${o.nombre}</option>`)}</select></div>
      <div class="tt-field"><label class="tt-label" for="d-veh">Vehículo</label><select class="tt-select" id="d-veh" name="vehiculo_id"><option value="">Todos</option>${vehiculos.data.map((v) => html`<option value="${v.id}">${v.patente}</option>`)}</select></div>
      <button class="tt-btn tt-btn--primary" type="submit">Aplicar</button>
    </form>
    <div id="d-res" aria-live="polite"></div>`;
  montar(raiz);
  const f = raiz.querySelector('form');
  const zona = raiz.querySelector('#d-res');
  const graficos = [];
  const valido = validarFormulario(f, (form) => (form.desde.value && form.hasta.value && form.hasta.value < form.desde.value ? { hasta: 'La fecha final debe ser igual o posterior a la inicial.' } : {}));

  async function cargar() {
    const t0 = performance.now();
    graficos.splice(0).forEach((g) => g.destroy());
    zona.setAttribute('aria-busy', 'true');
    zona.innerHTML = String(cargando(6));
    const q = Object.fromEntries(new FormData(f));
    try {
      const [k, ...series] = await Promise.all([
        get('dashboard/kpis', q, signal),
        ...['viajes_mes', 'costo_combustible_mes', 'rendimiento_vehiculo', 'viajes_por_estado'].map((s) => get('dashboard/series', { ...q, serie: s }, signal)),
      ]);
      const [vm, cm, rv, ve] = series;
      vm.labels = vm.labels.map(mesCorto);
      cm.labels = cm.labels.map(mesCorto);
      const I = k.indicadores;
      const totalViajes = ve.datasets[0].data.reduce((a, b) => a + b, 0);
      if (!totalViajes) {
        zona.innerHTML = String(vacio('Sin datos en el período', 'No hay viajes para los filtros elegidos. Amplíe el rango de fechas o quite filtros.'));
        return;
      }
      // Resumen textual de cada gráfico (para el lector de pantalla y la narrativa)
      const peor = rv.labels.at(-1), mejor = rv.labels[0];
      rv.resumen = rv.labels.length ? `El mejor rendimiento es de ${mejor} (${fmt.n1(rv.datasets[0].data[0])} km/L) y el más bajo de ${peor} (${fmt.n1(rv.datasets[0].data.at(-1))} km/L).` : '';
      const cpv = cm.datasets[0].data.filter((x) => x !== null);
      cm.resumen = cpv.length > 1 ? `El costo por viaje pasó de ${fmt.clp(cpv[0])} a ${fmt.clp(cpv.at(-1))}.` : '';
      vm.resumen = `En total hubo ${fmt.n(vm.datasets[0].data.reduce((a, b) => a + b, 0))} viajes completados.`;
      const pct = ve.datasets[0].data.map((n) => (totalViajes ? (100 * n) / totalViajes : 0));
      ve.resumen = ve.labels.map((l, i) => `${l} ${fmt.n1(pct[i])} %`).join(', ') + '.';

      zona.innerHTML = String(html`
        <section aria-labelledby="h-kpi"><h2 id="h-kpi" class="tt-sr-only">Indicadores</h2>
          <div class="tt-grid tt-grid--kpi">${Object.values(I).map(tarjetaKpi)}</div></section>
        <section class="tt-card tt-narrativa" aria-labelledby="h-nar">
          <h2 id="h-nar" class="tt-card__title">Lectura del período</h2>
          <ul>
            <li>Se completó el <strong>${fmt.n1(I.cumplimiento_viajes.valor)} %</strong> de los viajes; ${raw(I.tasa_cancelacion.valor !== null ? html`se canceló el <strong>${fmt.n1(I.tasa_cancelacion.valor)} %</strong>.` : '')}</li>
            <li>Cada viaje costó en promedio <strong>${fmt.clp(I.costo_combustible_por_viaje.valor)}</strong> en combustible. ${cm.resumen}</li>
            ${rv.labels.length > 1 ? html`<li>${rv.resumen} Revisar los camiones con menor rendimiento es la palanca más directa para bajar el costo.</li>` : ''}
            <li>La disponibilidad de los equipos fue de <strong>${fmt.n1(I.disponibilidad_flota.valor)} %</strong>${I.mtbf_horas.valor !== null ? html` y fallan, en promedio, cada <strong>${fmt.n(I.mtbf_horas.valor)} h</strong> de operación` : ''}.</li>
          </ul>
          <div class="tt-acciones"><button type="button" class="tt-btn tt-btn--secondary" id="d-ia"><span aria-hidden="true">✨</span> Resumen con IA de este período</button></div>
          <div id="d-ia-res" aria-live="polite"></div>
        </section>
        <div class="tt-grid tt-grid--2">
          ${figuraGrafico('g-viajes', vm)}${figuraGrafico('g-costo', cm, fmt.clp)}
          ${figuraGrafico('g-rend', rv, fmt.n1)}${figuraGrafico('g-estado', ve)}
        </div>
        <p class="tt-nota" id="d-tiempo"></p>`);

      const Chart = await cargarChart();
      Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
      Chart.defaults.color = '#334155';
      const base = { responsive: true, maintainAspectRatio: false, animation: sinMovimiento() ? false : { duration: 400 }, plugins: { legend: { position: 'bottom' } } };
      graficos.push(new Chart(document.getElementById('g-viajes'), { type: 'bar', data: { labels: vm.labels, datasets: vm.datasets.map((d, i) => ({ ...d, backgroundColor: [C.navy, C.rojo, C.ambar][i] })) },
        options: { ...base, scales: { x: { stacked: true }, y: { stacked: true, title: { display: true, text: 'Viajes' } } } } }));
      graficos.push(new Chart(document.getElementById('g-costo'), { type: 'line', data: { labels: cm.labels, datasets: cm.datasets.map((d, i) => ({ ...d, borderColor: [C.ambar, C.gris][i], backgroundColor: [C.ambar, C.gris][i], borderDash: i ? [6, 4] : [], tension: 0.2, pointStyle: i ? 'rect' : 'circle', yAxisID: i ? 'y1' : 'y' })) },
        options: { ...base, scales: {
          y: { title: { display: true, text: 'Costo por viaje (CLP)' }, ticks: { callback: (v) => fmt.clp(v) } },
          y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Precio por litro (CLP)' }, ticks: { callback: (v) => fmt.clp(v) } } }, plugins: { ...base.plugins, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${fmt.clp(c.parsed.y)}` } } } } }));
      graficos.push(new Chart(document.getElementById('g-rend'), { type: 'bar', data: { labels: rv.labels, datasets: [{ ...rv.datasets[0], backgroundColor: rv.labels.map((_, i) => (i === rv.labels.length - 1 ? C.rojo : C.navy)) }] },
        options: { ...base, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { title: { display: true, text: 'km por litro' } } } } }));
      graficos.push(new Chart(document.getElementById('g-estado'), { type: 'bar',
        data: { labels: ['Viajes del período'], datasets: ve.labels.map((l, i) => ({ label: `${l} (${fmt.n1(pct[i])} %)`, data: [pct[i]], backgroundColor: [C.verde, C.ambar, C.azul, C.gris][i] })) },
        options: { ...base, indexAxis: 'y', scales: { x: { stacked: true, max: 100, ticks: { callback: (v) => `${v} %` } }, y: { stacked: true } },
          plugins: { ...base.plugins, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${fmt.n(ve.datasets[0].data[c.datasetIndex])} viajes` } } } } }));

      const ms = Math.round(performance.now() - t0);
      raiz.querySelector('#d-tiempo').textContent = `Datos del ${fmt.fecha(k.periodo.desde)} al ${fmt.fecha(k.periodo.hasta)}, comparados con el ${fmt.fecha(k.periodo_anterior.desde)} al ${fmt.fecha(k.periodo_anterior.hasta)}. Actualizado en ${fmt.n(ms)} ms.`;
      anunciar(`Dashboard actualizado: ${fmt.n(totalViajes)} viajes en el período.`);
      raiz.querySelector('#d-ia').addEventListener('click', (e) => resumenIa(e.currentTarget, q));
    } catch (err) {
      if (err.name !== 'AbortError') zona.innerHTML = String(errorCarga(err));
    } finally { zona.setAttribute('aria-busy', 'false'); }
  }

  async function resumenIa(boton, q) {
    const z = raiz.querySelector('#d-ia-res');
    try {
      const r = await ocupado(boton, post('ia/resumen', { desde: q.desde, hasta: q.hasta }));
      z.innerHTML = String(html`<div class="tt-alert tt-alert--info"><div><p class="tt-ia-texto">${r.resumen}</p><ol class="tt-ia-recs">${r.recomendaciones.map((x) => html`<li>${x}</li>`)}</ol><p class="tt-nota">Generado por ${r.modelo}; verifique las cifras en los indicadores.</p></div></div>`);
    } catch (err) {
      z.innerHTML = String(html`<div class="tt-alert tt-alert--warning" role="status">${err.message} El dashboard sigue funcionando sin la IA.</div>`);
    }
  }

  f.addEventListener('submit', (e) => { e.preventDefault(); if (valido()) cargar(); });
  f.addEventListener('change', (e) => { if (e.target.tagName === 'SELECT' && valido()) cargar(); });
  await cargar();
}

// ── Reportes ────────────────────────────────────────────────────────────────
const REPORTES = {
  'operativo-viajes': { nombre: 'Detalle de viajes', tipo: 'Operativo', roles: ['Administrador', 'Supervisor', 'Gerencia'], filtros: true },
  'gerencial-mensual': { nombre: 'Resumen gerencial mensual', tipo: 'Gerencial', roles: ['Administrador', 'Gerencia'] },
  'combustible-periodo': { nombre: 'Combustible por vehículo', tipo: 'Parametrizable por fechas', roles: ['Administrador', 'Supervisor', 'Gerencia'] },
};
const celda = (v, tipo) => {
  if (v === null || v === undefined || v === '') return '—';
  return { fecha: fmt.fecha, entero: fmt.n, decimal: (x) => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(x), clp: fmt.clp, pct: (x) => `${fmt.n1(x)} %` }[tipo]?.(v) ?? v;
};

export async function reportes({ montar, rol, usuario, signal }) {
  const disponibles = Object.entries(REPORTES).filter(([, r]) => r.roles.includes(rol));
  const q = new URLSearchParams(location.hash.split('?')[1] || '');
  let actual = disponibles.find(([k]) => k === q.get('r'))?.[0] ?? disponibles[0][0];
  const [obras, vehiculos] = await Promise.all([get('obras', {}, signal), get('vehiculos', { per_page: 100 }, signal)]);
  const ini = new Date(new Date().getFullYear(), new Date().getMonth() - 3, 1).toLocaleDateString('sv-SE');

  const raiz = document.createElement('div');
  raiz.innerHTML = html`
    <div class="tt-page-head"><div><p class="tt-breadcrumb">Análisis</p><h1>Reportes</h1></div></div>
    <nav class="tt-tabs tt-no-print" aria-label="Elegir reporte"><ul>
      ${disponibles.map(([k, r]) => html`<li><a href="#/reportes?r=${k}" data-r="${k}">${r.nombre}<span class="tt-nota"> · ${r.tipo}</span></a></li>`)}
    </ul></nav>
    <form class="tt-filters tt-card tt-no-print" role="search" aria-label="Filtros del reporte">
      <div class="tt-field"><label class="tt-label" for="r-desde">Desde</label><input class="tt-input" id="r-desde" name="desde" type="date" required value="${ini}"></div>
      <div class="tt-field"><label class="tt-label" for="r-hasta">Hasta</label><input class="tt-input" id="r-hasta" name="hasta" type="date" required value="${fmt.hoy()}"></div>
      <div class="tt-field" data-op><label class="tt-label" for="r-estado">Estado</label><select class="tt-select" id="r-estado" name="estado"><option value="">Todos</option>
        ${[['completado', 'Completado'], ['en_ruta', 'En ruta'], ['programado', 'Programado'], ['cancelado', 'Cancelado']].map(([v, t]) => html`<option value="${v}">${t}</option>`)}</select></div>
      <div class="tt-field" data-op><label class="tt-label" for="r-veh">Vehículo</label><select class="tt-select" id="r-veh" name="vehiculo_id"><option value="">Todos</option>${vehiculos.data.map((v) => html`<option value="${v.id}">${v.patente}</option>`)}</select></div>
      <div class="tt-field" data-op><label class="tt-label" for="r-obra">Obra</label><select class="tt-select" id="r-obra" name="obra_id"><option value="">Todas</option>${obras.map((o) => html`<option value="${o.id}">${o.nombre}</option>`)}</select></div>
      <button class="tt-btn tt-btn--primary" type="submit">Generar</button>
    </form>
    <div class="tt-acciones tt-no-print tt-exportar" role="group" aria-label="Exportar o imprimir el reporte">
      <button type="button" class="tt-btn tt-btn--secondary" data-formato="xlsx">Excel (.xlsx)</button>
      <button type="button" class="tt-btn tt-btn--secondary" data-formato="pdf">PDF</button>
      <button type="button" class="tt-btn tt-btn--secondary" data-formato="csv" aria-describedby="r-csv">CSV</button>
      <button type="button" class="tt-btn tt-btn--ghost" id="r-imprimir"><span aria-hidden="true">🖨</span> Imprimir</button>
      <p class="tt-nota" id="r-csv">CSV en UTF-8, separador punto y coma (;) y coma decimal. Las exportaciones aplican los filtros elegidos y quedan registradas.</p>
    </div>
    <div id="r-res" aria-live="polite"></div>`;
  montar(raiz);
  const f = raiz.querySelector('form');
  const zona = raiz.querySelector('#r-res');
  const valido = validarFormulario(f, (form) => (form.desde.value && form.hasta.value && form.hasta.value < form.desde.value ? { hasta: 'La fecha final debe ser igual o posterior a la inicial.' } : {}));
  let page = 1;
  let grafico = null;

  const filtros = () => {
    const o = Object.fromEntries(new FormData(f));
    if (!REPORTES[actual].filtros) { delete o.estado; delete o.vehiculo_id; delete o.obra_id; }
    return o;
  };
  function marcar() {
    raiz.querySelectorAll('.tt-tabs a').forEach((a) => (a.dataset.r === actual ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current')));
    raiz.querySelectorAll('[data-op]').forEach((d) => { d.hidden = !REPORTES[actual].filtros; d.querySelector('select').disabled = !REPORTES[actual].filtros; });
  }

  async function cargar() {
    grafico?.destroy(); grafico = null;
    zona.setAttribute('aria-busy', 'true');
    zona.innerHTML = String(cargando(6));
    try {
      const r = await get(`reportes/${actual}`, { ...filtros(), page, per_page: 50 }, signal);
      const emitido = new Date().toLocaleString('es-CL');
      const filtrosTxt = Object.entries(r.filtros).map(([k, v]) => `${k}: ${v}`).join(' · ');
      if (!r.data.length) { zona.innerHTML = String(vacio('Sin datos', 'El reporte no tiene filas para los filtros elegidos.')); return; }
      const totales = Object.keys(r.totales).length
        ? html`<tfoot><tr>${r.columnas.map((c, i) => (i === 0 ? html`<th scope="row">Totales${r.meta.total_pages > 1 ? ` (${fmt.n(r.meta.total)} filas)` : ''}</th>` : html`<td class="${c.tipo === 'texto' ? '' : 'tt-num'}">${c.clave in r.totales ? celda(r.totales[c.clave], c.tipo) : ''}</td>`))}</tr></tfoot>` : '';
      zona.innerHTML = String(html`
        <div class="tt-print-only tt-print-head"><p class="tt-brand">TRI<b>TRANSPORTES</b></p><p>Emitido el ${emitido} por ${usuario.nombre}</p></div>
        <section class="tt-card" aria-labelledby="r-titulo">
          <h2 id="r-titulo" class="tt-card__title">${r.titulo}</h2>
          <p class="tt-nota">${filtrosTxt}</p>
          ${actual === 'gerencial-mensual' ? html`<figure class="tt-grafico" aria-labelledby="r-gt"><h3 id="r-gt">Costo de combustible por viaje y cumplimiento, por mes</h3>
            <div class="tt-grafico__lienzo"><canvas id="r-graf" role="img" aria-label="Costo por viaje mensual entre ${fmt.clp(r.data[0].costo_viaje)} y ${fmt.clp(r.data.at(-1).costo_viaje)}; los valores están en la tabla."></canvas></div></figure>` : ''}
          <div class="tt-table-wrap"><table class="tt-table tt-table--stack tt-reporte">
            <caption>${r.titulo}${r.meta.total_pages > 1 ? `, página ${r.meta.page} de ${r.meta.total_pages}` : ''}</caption>
            <thead><tr>${r.columnas.map((c) => html`<th scope="col" class="${c.tipo === 'texto' || c.tipo === 'fecha' ? '' : 'tt-num'}">${c.etiqueta}</th>`)}</tr></thead>
            <tbody>${r.data.map((fila) => html`<tr>${r.columnas.map((c) => html`<td data-label="${c.etiqueta}" class="${c.tipo === 'texto' || c.tipo === 'fecha' ? '' : 'tt-num'}">${celda(fila[c.clave], c.tipo)}</td>`)}</tr>`)}</tbody>
            ${totales}
          </table></div>
          ${r.meta.total_pages > 1 ? html`<p class="tt-nota tt-print-only">Esta impresión contiene la página ${r.meta.page} de ${r.meta.total_pages}; el reporte completo está en la exportación PDF.</p>` : ''}
        </section>`);
      if (r.meta.total_pages > 1) zona.append(paginacion(r.meta, (p) => { page = p; cargar(); }));
      if (actual === 'gerencial-mensual') {
        const Chart = await cargarChart();
        grafico = new Chart(document.getElementById('r-graf'), {
          data: { labels: r.data.map((m) => mesCorto(m.mes)), datasets: [
            { type: 'bar', label: 'Costo por viaje (CLP)', data: r.data.map((m) => m.costo_viaje), backgroundColor: C.navy, yAxisID: 'y' },
            { type: 'line', label: 'Cumplimiento (%)', data: r.data.map((m) => m.cumplimiento), borderColor: C.ambar, backgroundColor: C.ambar, yAxisID: 'y1' }] },
          options: { responsive: true, maintainAspectRatio: false, animation: sinMovimiento() ? false : { duration: 400 },
            scales: { y: { ticks: { callback: (v) => fmt.clp(v) } }, y1: { position: 'right', min: 80, max: 100, grid: { drawOnChartArea: false }, ticks: { callback: (v) => `${v} %` } } } },
        });
      }
      anunciar(`${r.titulo}: ${fmt.n(r.meta.total)} filas.`);
    } catch (err) {
      if (err.name !== 'AbortError') zona.innerHTML = String(errorCarga(err));
    } finally { zona.setAttribute('aria-busy', 'false'); }
  }

  raiz.querySelector('.tt-tabs').addEventListener('click', (e) => {
    const a = e.target.closest('a[data-r]');
    if (!a) return;
    e.preventDefault();
    actual = a.dataset.r; page = 1;
    history.replaceState(null, '', `#/reportes?r=${actual}`);
    marcar(); cargar();
  });
  f.addEventListener('submit', (e) => { e.preventDefault(); if (valido()) { page = 1; cargar(); } });
  raiz.querySelector('.tt-exportar').addEventListener('click', async (e) => {
    const b = e.target.closest('button[data-formato]');
    if (!b || !valido()) return;
    try {
      const { blob, nombre } = await ocupado(b, descargar(`reportes/${actual}/export`, { ...filtros(), formato: b.dataset.formato }));
      const url = URL.createObjectURL(blob);
      const a = Object.assign(document.createElement('a'), { href: url, download: nombre });
      document.body.append(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 5000);
      toast(`Descargado ${nombre} (${fmt.n(Math.round(blob.size / 1024))} KB).`);
    } catch (err) { toast(err.message, 'danger'); }
  });
  raiz.querySelector('#r-imprimir').addEventListener('click', () => window.print());
  // Chart.js no se redimensiona solo al imprimir: se ajusta al ancho de la hoja antes y se restaura después
  const alImprimir = () => grafico?.resize();
  window.addEventListener('beforeprint', alImprimir);
  window.addEventListener('afterprint', alImprimir);
  signal.addEventListener('abort', () => { window.removeEventListener('beforeprint', alImprimir); window.removeEventListener('afterprint', alImprimir); });
  marcar();
  await cargar();
}
