// TriTransportes · aplicación de una página conectada a la API REST.
// Enrutador por hash, menú según el rol, gestión del foco y del título en cada vista.
import { post, sesion } from './api.js';
import { html, anunciar, toast, validarFormulario, erroresDeApi, ocupado } from './ui.js';
import * as viajes from './views/viajes.js';
import * as flota from './views/flota.js';
import * as ia from './views/ia.js';

const A = 'Administrador', S = 'Supervisor', C = 'Conductor', G = 'Gerencia';
const TODOS = [A, S, C, G];

/** Rutas: patrón → vista y roles con acceso. */
const RUTAS = [
  { re: /^\/viajes$/, vista: viajes.lista, roles: TODOS, titulo: 'Viajes', menu: 'viajes' },
  { re: /^\/viajes\/nuevo$/, vista: viajes.formulario, roles: [A, S], titulo: 'Programar viaje', menu: 'nuevo' },
  { re: /^\/viajes\/(\d+)$/, vista: viajes.detalle, roles: TODOS, titulo: 'Detalle del viaje', menu: 'viajes' },
  { re: /^\/viajes\/(\d+)\/editar$/, vista: viajes.formulario, roles: [A, S], titulo: 'Modificar viaje', menu: 'viajes' },
  { re: /^\/cargas$/, vista: flota.cargas, roles: TODOS, titulo: 'Combustible', menu: 'cargas' },
  { re: /^\/mantenciones$/, vista: flota.mantenciones, roles: [A, S, G], titulo: 'Mantenciones', menu: 'mantenciones' },
  { re: /^\/flota$/, vista: flota.vehiculos, roles: TODOS, titulo: 'Flota', menu: 'flota' },
  { re: /^\/flota\/(\d+)$/, vista: flota.rendimiento, roles: [A, S, G], titulo: 'Rendimiento del vehículo', menu: 'flota' },
  { re: /^\/ia$/, vista: ia.resumen, roles: [A, S, G], titulo: 'Resumen con IA', menu: 'ia' },
];

const MENU = [
  { id: 'viajes', href: '#/viajes', texto: (r) => (r === C ? 'Mis viajes' : 'Viajes'), icono: '🚚', roles: TODOS },
  { id: 'nuevo', href: '#/viajes/nuevo', texto: () => 'Programar viaje', icono: '＋', roles: [A, S] },
  { id: 'cargas', href: '#/cargas', texto: () => 'Combustible', icono: '⛽', roles: TODOS },
  { id: 'mantenciones', href: '#/mantenciones', texto: () => 'Mantenciones', icono: '🔧', roles: [A, S, G] },
  { id: 'flota', href: '#/flota', texto: () => 'Flota', icono: '🚛', roles: TODOS },
  { id: 'ia', href: '#/ia', texto: () => 'Resumen con IA', icono: '✨', roles: [A, S, G] },
];

const $ = (s) => document.querySelector(s);
let controlador = null; // AbortController de la vista activa

function pintarShell() {
  const u = sesion.usuario;
  const app = $('#app');
  if (!u) { app.className = ''; return; }
  app.className = 'tt-app';
  $('#tt-sidebar').innerHTML = html`
    <a class="tt-brand" href="#/viajes">TRI<b>TRANSPORTES</b></a>
    <nav aria-label="Menú principal"><ul class="tt-nav">
      <li class="tt-nav__section" aria-hidden="true">Operación</li>
      ${MENU.filter((m) => m.roles.includes(u.rol)).map((m) => html`<li><a href="${m.href}" data-menu="${m.id}"><span aria-hidden="true">${m.icono}</span> ${m.texto(u.rol)}</a></li>`)}
    </ul></nav>
    <div class="tt-user">
      <p><strong>${u.nombre}</strong><br><span>${u.rol}</span></p>
      <button type="button" class="tt-btn tt-btn--secondary tt-btn--sm" id="salir">Cerrar sesión</button>
    </div>`;
  $('#salir').addEventListener('click', () => { sesion.cerrar(); toast('Sesión cerrada.', 'info'); location.hash = '#/login'; });
}

function marcarMenu(id) {
  document.querySelectorAll('.tt-nav a').forEach((a) => {
    a.dataset.menu === id ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current');
  });
}

/** Coloca el contenido, actualiza el título, mueve el foco al h1 y lo anuncia. */
export function montar(contenido, titulo) {
  const main = $('#contenido');
  main.innerHTML = '';
  if (typeof contenido === 'string' || contenido?.s !== undefined) main.innerHTML = String(contenido);
  else main.append(contenido);
  document.title = `${titulo} · TriTransportes`;
  const h1 = main.querySelector('h1');
  if (h1) { h1.tabIndex = -1; h1.focus({ preventScroll: false }); }
  anunciar(titulo);
}

function vistaError(codigo) {
  const t = codigo === 403
    ? { h: 'Sin permisos', p: 'Su rol no tiene acceso a esta sección. Si cree que es un error, contacte al administrador.' }
    : { h: 'Página no encontrada', p: 'La dirección no existe o fue movida.' };
  montar(html`<section class="tt-card tt-empty" aria-labelledby="h-err">
      <div class="tt-empty__icon" aria-hidden="true">${codigo === 403 ? '🔒' : '🧭'}</div>
      <p class="tt-error-code" aria-hidden="true">${codigo}</p>
      <h1 id="h-err">${t.h}</h1><p>${t.p}</p>
      <a class="tt-btn tt-btn--primary" href="#/viajes">Ir a viajes</a></section>`, t.h);
}

function vistaLogin(msg) {
  $('#app').className = '';
  $('#tt-sidebar').innerHTML = '';
  montar(html`<section class="tt-login" aria-labelledby="h-login">
    <div class="tt-card">
      <p class="tt-brand tt-brand--lg" aria-hidden="true">TRI<b>TRANSPORTES</b></p>
      <h1 id="h-login">Iniciar sesión</h1>
      ${msg ? html`<div class="tt-alert tt-alert--warning" role="status">${msg}</div>` : ''}
      <form id="f-login">
        <div class="tt-field"><label class="tt-label" for="email">Correo <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="email" name="email" type="email" autocomplete="username" required maxlength="120"></div>
        <div class="tt-field"><label class="tt-label" for="password">Contraseña <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="password" name="password" type="password" autocomplete="current-password" required minlength="8" maxlength="128"></div>
        <button class="tt-btn tt-btn--primary tt-btn--block" type="submit">Ingresar</button>
      </form>
      <p class="tt-hint">Los campos con <span aria-hidden="true">*</span><span class="tt-sr-only">asterisco</span> son obligatorios.</p>
    </div></section>`, 'Iniciar sesión');
  const f = $('#f-login');
  const valido = validarFormulario(f);
  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!valido()) return;
    try {
      const r = await ocupado(f.querySelector('[type=submit]'), post('auth/login', { email: f.email.value.trim(), password: f.password.value }));
      sesion.guardar(r);
      pintarShell();
      toast(`Bienvenido, ${r.usuario.nombre}.`);
      location.hash = '#/viajes';
    } catch (err) {
      f.password.value = '';
      erroresDeApi(f, err.status === 422 ? err : { ...err, status: 0, message: err.message });
    }
  });
}

async function enrutar() {
  if (controlador) controlador.abort();
  controlador = new AbortController();
  const ruta = (location.hash.replace(/^#/, '') || '/viajes').split('?')[0];
  document.getElementById('tt-sidebar').hidden = window.matchMedia('(max-width: 63.99em)').matches;
  $('#menu-toggle')?.setAttribute('aria-expanded', 'false');

  if (!sesion.get()) {
    if (ruta !== '/login') { location.replace('#/login'); return; }
    return vistaLogin();
  }
  if (ruta === '/login') { location.replace('#/viajes'); return; }

  for (const r of RUTAS) {
    const m = ruta.match(r.re);
    if (!m) continue;
    marcarMenu(r.menu);
    if (!r.roles.includes(sesion.rol)) return vistaError(403);
    try {
      await r.vista({ params: m.slice(1), montar: (c, t) => montar(c, t || r.titulo), signal: controlador.signal, rol: sesion.rol, usuario: sesion.usuario });
    } catch (err) {
      if (err.name === 'AbortError') return;
      if (err.status === 404) return vistaError(404);
      if (err.status === 403) return vistaError(403);
      if (err.status === 401) return;
      montar(html`<h1>${r.titulo}</h1><div class="tt-alert tt-alert--danger" role="alert">${err.message}</div>`, r.titulo);
    }
    return;
  }
  marcarMenu(null);
  vistaError(404);
}

// Menú colapsable en celular y tablet
$('#menu-toggle').addEventListener('click', (e) => {
  const sb = $('#tt-sidebar');
  sb.hidden = !sb.hidden;
  e.currentTarget.setAttribute('aria-expanded', String(!sb.hidden));
  if (!sb.hidden) sb.querySelector('a')?.focus();
});
document.addEventListener('keydown', (e) => {
  const sb = $('#tt-sidebar');
  if (e.key === 'Escape' && !sb.hidden && window.matchMedia('(max-width: 63.99em)').matches && !document.querySelector('dialog[open]')) {
    sb.hidden = true;
    $('#menu-toggle').setAttribute('aria-expanded', 'false');
    $('#menu-toggle').focus();
  }
});
window.addEventListener('tt:sesion-expirada', () => vistaLogin('Su sesión expiró o ya no es válida. Ingrese nuevamente.'));
window.addEventListener('hashchange', enrutar);
pintarShell();
enrutar();
