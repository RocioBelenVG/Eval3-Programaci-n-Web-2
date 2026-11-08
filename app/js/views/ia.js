// Resumen gerencial con IA (POST /ia/resumen). El texto del modelo se muestra como texto, nunca como HTML.
import { post } from '../api.js';
import { html, fmt, anunciar, validarFormulario, erroresDeApi, ocupado } from '../ui.js';

export async function resumen({ montar }) {
  const hoy = new Date();
  const ini = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1).toLocaleDateString('sv-SE');
  const fin = new Date(hoy.getFullYear(), hoy.getMonth(), 0).toLocaleDateString('sv-SE');
  const raiz = document.createElement('div');
  raiz.innerHTML = html`
    <div class="tt-page-head"><div><p class="tt-breadcrumb">Análisis</p><h1>Resumen con IA</h1></div></div>
    <p>Genera un resumen para la gerencia a partir de los indicadores del período, comparados con el período anterior de igual duración.
      Solo se envían totales al servicio de IA (Groq · Llama 3.3), nunca nombres ni datos personales.</p>
    <form class="tt-card" id="f-ia">
      <div class="tt-filters" style="margin:0">
        <div class="tt-field"><label class="tt-label" for="ia-desde">Desde <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="ia-desde" name="desde" type="date" required value="${ini}"></div>
        <div class="tt-field"><label class="tt-label" for="ia-hasta">Hasta <span class="tt-req" aria-hidden="true">*</span></label>
          <input class="tt-input" id="ia-hasta" name="hasta" type="date" required value="${fin}"></div>
        <div class="tt-field"><label class="tt-label" for="ia-enf">Enfoque</label>
          <select class="tt-select" id="ia-enf" name="enfoque"><option value="general">General</option><option value="combustible">Combustible</option><option value="flota">Flota</option></select></div>
        <div class="tt-field" aria-hidden="true"></div>
        <button type="submit" class="tt-btn tt-btn--primary">Generar resumen</button>
      </div>
    </form>
    <div id="ia-res" aria-live="polite" style="margin-top:1.5rem"></div>`;
  montar(raiz);

  const f = raiz.querySelector('form');
  const zona = raiz.querySelector('#ia-res');
  const valido = validarFormulario(f, (form) => {
    const d = form.desde.value, h = form.hasta.value;
    if (d && h && h < d) return { hasta: 'La fecha final debe ser igual o posterior a la inicial.' };
    if (d && h && (new Date(h) - new Date(d)) / 864e5 > 365) return { hasta: 'El período no puede superar 366 días.' };
    return {};
  });
  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!valido()) return;
    zona.innerHTML = String(html`<div class="tt-card" aria-busy="true"><p>Generando el resumen… puede tardar hasta 20 segundos.</p><div class="tt-skeleton" style="height:4rem"></div></div>`);
    try {
      const r = await ocupado(f.querySelector('[type=submit]'), post('ia/resumen', { desde: f.desde.value, hasta: f.hasta.value, enfoque: f.enfoque.value }));
      const a = r.datos.periodo_actual, b = r.datos.periodo_anterior;
      const fila = (t, x, y) => html`<tr><th scope="row">${t}</th><td class="tt-num">${x}</td><td class="tt-num">${y}</td></tr>`;
      zona.innerHTML = String(html`
        <section class="tt-card" aria-labelledby="h-res">
          <h2 id="h-res" class="tt-card__title" tabindex="-1">Resumen del ${fmt.fecha(a.periodo.desde)} al ${fmt.fecha(a.periodo.hasta)}</h2><br>
          <p class="tt-ia-texto">${r.resumen}</p>
          <h3>Recomendaciones</h3>
          <ol class="tt-ia-recs">${r.recomendaciones.map((x) => html`<li>${x}</li>`)}</ol>
          <p class="tt-nota">Generado por ${r.modelo}. Es un apoyo a la decisión: verifique las cifras en la tabla antes de actuar.</p>
        </section>
        <div class="tt-table-wrap" style="margin-top:1rem"><table class="tt-table">
          <caption>Indicadores enviados al modelo (período actual y anterior)</caption>
          <thead><tr><th scope="col">Indicador</th><th scope="col" class="tt-num">Actual</th><th scope="col" class="tt-num">Anterior</th></tr></thead>
          <tbody>
            ${fila('Viajes', fmt.n(a.viajes.total), fmt.n(b.viajes.total))}
            ${fila('Cumplimiento', `${fmt.n1(a.viajes.cumplimiento_pct)} %`, `${fmt.n1(b.viajes.cumplimiento_pct)} %`)}
            ${fila('Cancelación', `${fmt.n1(a.viajes.cancelacion_pct)} %`, `${fmt.n1(b.viajes.cancelacion_pct)} %`)}
            ${fila('Rendimiento de la flota', `${fmt.n1(a.combustible.rendimiento_flota_km_l)} km/L`, `${fmt.n1(b.combustible.rendimiento_flota_km_l)} km/L`)}
            ${fila('Costo de combustible por viaje', fmt.clp(a.combustible.costo_por_viaje_clp), fmt.clp(b.combustible.costo_por_viaje_clp))}
            ${fila('Mantenciones correctivas', fmt.n(a.mantenciones.correctivas), fmt.n(b.mantenciones.correctivas))}
          </tbody></table></div>`);
      zona.querySelector('#h-res').focus();
      anunciar('Resumen generado.');
    } catch (err) {
      zona.innerHTML = '';
      if (err.status === 422) return erroresDeApi(f, err);
      const t = { 503: 'Servicio de IA no disponible', 504: 'El servicio de IA no respondió a tiempo', 502: 'El servicio de IA falló', 429: 'Demasiadas solicitudes' }[err.status] || 'No se pudo generar el resumen';
      zona.innerHTML = String(html`<div class="tt-alert tt-alert--warning" role="alert"><div><strong>${t}.</strong> ${err.message}
        <br>El resto del sistema sigue funcionando con normalidad.</div></div>`);
    }
  });
}
