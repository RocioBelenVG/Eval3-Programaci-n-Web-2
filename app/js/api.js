// Cliente de la API REST /api/v1 y manejo de la sesión (JWT en sessionStorage: se borra al cerrar la pestaña).
const BASE = new URL('../api/v1/', document.baseURI).pathname;
const CLAVE = 'tt.sesion';

export class ApiError extends Error {
  constructor(status, body) {
    const e = body?.error ?? {};
    super(e.message || `Error ${status}`);
    this.status = status;
    this.code = e.code || 'ERROR';
    this.details = e.details || [];
    this.requestId = e.request_id || null;
  }
}

export const sesion = {
  get() {
    try {
      const s = JSON.parse(sessionStorage.getItem(CLAVE) || 'null');
      if (!s || !s.token || Date.now() >= s.expira) return null;
      return s;
    } catch { return null; }
  },
  guardar({ token, expires_in, usuario }) {
    sessionStorage.setItem(CLAVE, JSON.stringify({ token, usuario, expira: Date.now() + (expires_in - 30) * 1000 }));
  },
  cerrar() { sessionStorage.removeItem(CLAVE); },
  get usuario() { return this.get()?.usuario ?? null; },
  get rol() { return this.get()?.usuario?.rol ?? null; },
};

/** Llama a la API. Lanza ApiError con el esquema de error del contrato; 401 cierra la sesión. */
export async function api(metodo, ruta, { body, query, signal } = {}) {
  const url = new URL(BASE + ruta.replace(/^\//, ''), location.origin);
  for (const [k, v] of Object.entries(query || {})) {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
  }
  const headers = { Accept: 'application/json' };
  const s = sesion.get();
  if (s) headers.Authorization = `Bearer ${s.token}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  let res;
  try {
    res = await fetch(url, { method: metodo, headers, body: body !== undefined ? JSON.stringify(body) : undefined, signal });
  } catch (e) {
    if (e.name === 'AbortError') throw e;
    throw new ApiError(0, { error: { code: 'SIN_CONEXION', message: 'No hay conexión con el servidor. Revise su red e intente de nuevo.' } });
  }
  const data = res.status === 204 ? null : await res.json().catch(() => null);
  if (!res.ok) {
    const err = new ApiError(res.status, data);
    if (res.status === 401 && ruta !== 'auth/login') {
      sesion.cerrar();
      window.dispatchEvent(new CustomEvent('tt:sesion-expirada', { detail: err.message }));
    }
    throw err;
  }
  return data;
}

export const get = (ruta, query, signal) => api('GET', ruta, { query, signal });
export const post = (ruta, body) => api('POST', ruta, { body });
export const put = (ruta, body) => api('PUT', ruta, { body });
export const patch = (ruta, body) => api('PATCH', ruta, { body });
export const del = (ruta) => api('DELETE', ruta);
