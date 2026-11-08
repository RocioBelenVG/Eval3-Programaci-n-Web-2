// Utilidades de interfaz: plantillas con escape automático, formatos es-CL, avisos accesibles,
// errores de formulario vinculados al campo, modal con <dialog>, tabla y paginación.

const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

class Raw { constructor(s) { this.s = s; } toString() { return this.s; } }
/** Marca HTML ya seguro para que html`` no lo escape. */
export const raw = (s) => new Raw(String(s));

/** Plantilla: todo valor interpolado se escapa salvo raw(...) o arreglos de raw. */
export function html(partes, ...valores) {
  let out = partes[0];
  valores.forEach((v, i) => {
    if (Array.isArray(v)) out += v.map((x) => (x instanceof Raw ? x.s : esc(x))).join('');
    else out += v instanceof Raw ? v.s : esc(v);
    out += partes[i + 1];
  });
  return raw(out);
}

// ── Formatos chilenos ──────────────────────────────────────────────────────
const nf = new Intl.NumberFormat('es-CL');
const nf1 = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 1 });
const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });
export const fmt = {
  n: (v) => (v === null || v === undefined ? '—' : nf.format(v)),
  n1: (v) => (v === null || v === undefined ? '—' : nf1.format(v)),
  clp: (v) => (v === null || v === undefined ? '—' : clp.format(v)),
  fecha: (iso) => {
    if (!iso) return '—';
    const [a, m, d] = iso.slice(0, 10).split('-');
    return `${d}-${m}-${a}`;
  },
  hoy: () => new Date().toLocaleDateString('sv-SE'),
};

export const ESTADOS = {
  programado: 'Programado', en_ruta: 'En ruta', completado: 'Completado', cancelado: 'Cancelado',
  pendiente: 'Pendiente', en_proceso: 'En proceso', completada: 'Completada',
};
const BADGE_MANT = { pendiente: 'programado', en_proceso: 'en_ruta', completada: 'completado' };
export const badge = (estado) =>
  html`<span class="tt-badge tt-badge--${BADGE_MANT[estado] || estado}">${ESTADOS[estado] || estado}</span>`;

// ── Avisos para lectores de pantalla y toasts visibles ─────────────────────
export function anunciar(msg) {
  const r = document.getElementById('tt-live');
  r.textContent = '';
  setTimeout(() => { r.textContent = msg; }, 50);
}

export function toast(msg, tipo = 'success') {
  const zona = document.getElementById('tt-toasts');
  const el = document.createElement('div');
  el.className = `tt-alert tt-alert--${tipo} tt-toast`;
  el.setAttribute('role', tipo === 'danger' ? 'alert' : 'status');
  el.textContent = msg;
  zona.append(el);
  setTimeout(() => el.remove(), 6000);
}

// ── Formularios ─────────────────────────────────────────────────────────────
/** Muestra un error bajo el campo, lo vincula con aria-describedby y marca aria-invalid. */
export function errorCampo(form, nombre, mensaje) {
  let campo = form.elements[nombre];
  if (typeof RadioNodeList !== 'undefined' && campo instanceof RadioNodeList) campo = campo[0]; // grupo de radios
  if (!campo) return false;
  const id = `err-${campo.id || nombre}`;
  let p = form.querySelector(`#${CSS.escape(id)}`);
  if (mensaje) {
    if (!p) {
      p = document.createElement('p');
      p.id = id;
      p.className = 'tt-error';
      (campo.closest('.tt-field') || campo.parentElement).append(p);
    }
    p.textContent = mensaje;
    campo.setAttribute('aria-invalid', 'true');
    const desc = new Set((campo.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
    desc.add(id);
    campo.setAttribute('aria-describedby', [...desc].join(' '));
  } else {
    p?.remove();
    campo.removeAttribute('aria-invalid');
    const desc = (campo.getAttribute('aria-describedby') || '').split(' ').filter((x) => x && x !== id);
    desc.length ? campo.setAttribute('aria-describedby', desc.join(' ')) : campo.removeAttribute('aria-describedby');
  }
  return true;
}

/** Mensaje de validación nativo en español para un campo (validación inmediata al salir del campo). */
export function mensajeNativo(c) {
  const v = c.validity;
  if (v.valid) return '';
  const etiqueta = c.labels?.[0]?.textContent.replace('*', '').trim() || 'Este campo';
  if (v.valueMissing) return `Complete el campo «${etiqueta}».`;
  if (v.typeMismatch && c.type === 'email') return 'Ingrese un correo válido, por ejemplo nombre@empresa.cl.';
  if (v.badInput) return `${etiqueta}: ingrese un número válido.`;
  if (v.rangeUnderflow) return `${etiqueta} debe ser mayor o igual a ${c.min}.`;
  if (v.rangeOverflow) return `${etiqueta} debe ser menor o igual a ${c.max}.`;
  if (v.tooShort) return `${etiqueta} debe tener al menos ${c.minLength} caracteres.`;
  if (v.patternMismatch) return c.dataset.patron || `${etiqueta} no tiene el formato correcto.`;
  if (v.stepMismatch) return `${etiqueta}: revise los decimales.`;
  return c.validationMessage;
}

/**
 * Activa la validación inmediata (al salir de cada campo) y, al enviar, el resumen de errores
 * con enlaces a cada campo. reglasExtra(form) devuelve {campo: mensaje} para reglas entre campos.
 */
export function validarFormulario(form, reglasExtra = () => ({})) {
  form.noValidate = true;
  const revisar = (c) => errorCampo(form, c.name, mensajeNativo(c) || reglasExtra(form)[c.name] || '');
  form.addEventListener('focusout', (e) => { if (e.target.name && e.target.dataset.tocado) revisar(e.target); });
  form.addEventListener('input', (e) => {
    e.target.dataset.tocado = '1';
    if (e.target.getAttribute('aria-invalid') === 'true') revisar(e.target);
  });
  form.addEventListener('change', (e) => { e.target.dataset.tocado = '1'; });
  return () => {
    const errores = {};
    for (const c of form.elements) {
      if (!c.name || c.disabled) continue;
      const m = mensajeNativo(c);
      if (m) errores[c.name] = m;
    }
    Object.assign(errores, Object.fromEntries(Object.entries(reglasExtra(form)).filter(([k, v]) => v && !errores[k])));
    mostrarErrores(form, errores);
    return Object.keys(errores).length === 0;
  };
}

/** Pinta los errores (del navegador o del 422 de la API) y lleva el foco al resumen. */
export function mostrarErrores(form, errores, titulo = 'Revise los siguientes campos:') {
  for (const c of form.elements) if (c.name) errorCampo(form, c.name, '');
  form.querySelector('.tt-error-summary')?.remove();
  const lista = Object.entries(errores);
  if (!lista.length) return;
  const items = lista.map(([campo, msg]) => {
    const el = form.elements[campo];
    errorCampo(form, campo, msg);
    return el ? html`<li><a href="#${el.id}">${msg}</a></li>` : html`<li>${msg}</li>`;
  });
  const div = document.createElement('div');
  div.className = 'tt-error-summary';
  div.tabIndex = -1;
  div.setAttribute('role', 'alert');
  div.innerHTML = html`<strong>${titulo}</strong><ul>${items}</ul>`;
  div.addEventListener('click', (e) => {
    const a = e.target.closest('a[href^="#"]');
    if (a) { e.preventDefault(); document.getElementById(a.getAttribute('href').slice(1))?.focus(); }
  });
  form.prepend(div);
  div.focus();
}

/** Traduce un ApiError 422 a errores por campo; otros errores van como mensaje general. */
export function erroresDeApi(form, err) {
  if (err.status === 422 && err.details?.length) {
    const map = {};
    for (const d of err.details) map[d.field] = d.message;
    mostrarErrores(form, map, err.message);
  } else {
    mostrarErrores(form, { _general: err.message }, 'No se pudo guardar');
  }
}

/** Botón ocupado mientras dura una promesa (evita doble envío y anuncia el estado). */
export async function ocupado(boton, promesa) {
  boton.setAttribute('aria-busy', 'true');
  boton.disabled = true;
  try { return await promesa; } finally { boton.removeAttribute('aria-busy'); boton.disabled = false; }
}

// ── Modal accesible con <dialog> (foco atrapado por el navegador y cierre con Esc) ──
export function modal({ titulo, cuerpo, confirmar = 'Confirmar', peligro = false, alAceptar }) {
  const d = document.createElement('dialog');
  d.className = 'tt-modal';
  d.setAttribute('aria-labelledby', 'modal-titulo');
  d.innerHTML = html`<form method="dialog" class="tt-modal-form">
      <div class="tt-modal__body"><h2 id="modal-titulo">${titulo}</h2>${cuerpo}</div>
      <div class="tt-modal__actions">
        <button type="button" class="tt-btn tt-btn--secondary" value="cancelar">Cancelar</button>
        <button type="submit" class="tt-btn ${peligro ? 'tt-btn--danger' : 'tt-btn--primary'}" value="ok">${confirmar}</button>
      </div></form>`;
  const volver = document.activeElement;
  document.body.append(d);
  const form = d.querySelector('form');
  const validar = validarFormulario(form); // errores propios, accesibles, en lugar del globo del navegador
  d.querySelector('[value="cancelar"]').addEventListener('click', () => d.close());
  d.addEventListener('close', () => { d.remove(); volver?.focus?.(); });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!validar()) return;
    const boton = form.querySelector('[type="submit"]');
    try {
      await ocupado(boton, alAceptar(form));
      d.close();
    } catch (err) {
      if (err?.status) erroresDeApi(form, err);
      else if (err !== false) throw err;
    }
  });
  d.showModal();
  (d.querySelector('input, select, textarea') || d.querySelector('[type="submit"]')).focus();
  return d;
}

// ── Listados ────────────────────────────────────────────────────────────────
export function paginacion(meta, alCambiar) {
  const nav = document.createElement('nav');
  nav.className = 'tt-pagination';
  nav.setAttribute('aria-label', 'Paginación');
  if (!meta || meta.total === 0) return nav;
  const { page, total_pages: tp, total, per_page: pp } = meta;
  const desde = (page - 1) * pp + 1;
  const hasta = Math.min(page * pp, total);
  const paginas = [...new Set([1, page - 1, page, page + 1, tp])].filter((p) => p >= 1 && p <= tp).sort((a, b) => a - b);
  const items = [];
  items.push(html`<li><button type="button" class="tt-btn tt-btn--secondary tt-btn--sm" data-p="${page - 1}" ${raw(page <= 1 ? 'disabled' : '')}>Anterior</button></li>`);
  let previa = 0;
  for (const p of paginas) {
    if (p - previa > 1) items.push(html`<li aria-hidden="true">…</li>`);
    items.push(html`<li><button type="button" class="tt-btn tt-btn--ghost tt-btn--sm" data-p="${p}" ${raw(p === page ? 'aria-current="page"' : '')} aria-label="Página ${p}">${p}</button></li>`);
    previa = p;
  }
  items.push(html`<li><button type="button" class="tt-btn tt-btn--secondary tt-btn--sm" data-p="${page + 1}" ${raw(page >= tp ? 'disabled' : '')}>Siguiente</button></li>`);
  nav.innerHTML = html`<p class="tt-hint" style="margin:0">Mostrando ${fmt.n(desde)}–${fmt.n(hasta)} de ${fmt.n(total)}</p><ul>${items}</ul>`;
  nav.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-p]');
    if (b && !b.disabled && b.getAttribute('aria-current') !== 'page') alCambiar(Number(b.dataset.p));
  });
  return nav;
}

/** Encabezado ordenable: un botón dentro de <th> con aria-sort. */
export function thOrden(etiqueta, campo, sortActual) {
  const activo = sortActual.replace('-', '') === campo;
  const desc = sortActual.startsWith('-');
  const ariaSort = activo ? (desc ? 'descending' : 'ascending') : 'none';
  const flecha = activo ? (desc ? '▼' : '▲') : '↕';
  return html`<th scope="col" aria-sort="${ariaSort}"><button type="button" class="tt-sort" data-sort="${campo}">${etiqueta} <span aria-hidden="true">${flecha}</span></button></th>`;
}

export const cargando = (filas = 5) =>
  html`<div class="tt-card" aria-busy="true" aria-label="Cargando">${Array.from({ length: filas }, () => raw('<div class="tt-skeleton" style="height:1.5rem;margin-bottom:.75rem"></div>'))}</div>`;

export const vacio = (titulo, texto, accion = '') =>
  html`<div class="tt-empty"><div class="tt-empty__icon" aria-hidden="true">🗂️</div><h2>${titulo}</h2><p>${texto}</p>${raw(accion)}</div>`;

export const errorCarga = (err) =>
  html`<div class="tt-alert tt-alert--danger" role="alert"><div><strong>No se pudo cargar la información.</strong> ${err.message}${err.requestId ? ` (código ${err.requestId})` : ''}</div></div>`;

/** Debounce para la búsqueda mientras se escribe. */
export function debounce(fn, ms = 350) {
  let t;
  return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}
