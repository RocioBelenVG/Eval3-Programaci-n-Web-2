/* ============================================================
   TriTransportes — VISUALIZADOR DE REGISTROS (standalone)
   Archivo independiente: si app.js falla, el visualizador
   sigue funcionando. Calcula su propia ruta base a partir
   del <script src> con el que fue cargado, por lo que opera
   tanto en la raíz del dominio como en un subdirectorio,
   sin depender de la configuración de APP_URL.
   ============================================================ */
(function () {
    'use strict';

    // ── Ruta base: derivada del propio <script src> ─────────────
    // El script se carga como  {BASE}/assets/js/viewer.js
    function resolveBase() {
        var src = '';
        if (document.currentScript && document.currentScript.src) {
            src = document.currentScript.src;
        } else {
            var scripts = document.getElementsByTagName('script');
            for (var i = scripts.length - 1; i >= 0; i--) {
                if (scripts[i].src && scripts[i].src.indexOf('viewer.js') !== -1) {
                    src = scripts[i].src; break;
                }
            }
        }
        if (src) {
            // quitar "/assets/js/viewer.js(.?v=...)" para obtener la base de la app
            var path = src.replace(/[?#].*$/, '');
            var idx = path.indexOf('/assets/js/viewer.js');
            if (idx !== -1) {
                // devolver sólo el path (sin origen) para usar rutas absolutas same-origin
                var base = path.substring(0, idx);
                try { base = new URL(base).pathname; } catch (e) { /* ya es path */ }
                return base;
            }
        }
        // fallback: variable global inyectada por el footer
        return (typeof window.TT_BASE === 'string') ? window.TT_BASE : '';
    }

    var BASE = resolveBase();

    function init() {
        var overlay = document.getElementById('rv-overlay');
        if (!overlay) return; // el modal no está en esta página

        var elTitle = document.getElementById('rv-title');
        var elSub   = document.getElementById('rv-sub');
        var elIcon  = document.getElementById('rv-icon');
        var elBadge = document.getElementById('rv-badge');
        var elBody  = document.getElementById('rv-body');
        var elEdit  = document.getElementById('rv-edit');
        var elClose = document.getElementById('rv-close');
        var elPrint = document.getElementById('rv-print');
        var elPdf   = document.getElementById('rv-pdf');

        var loaderHtml = '<div class="rv-loader"><span class="rv-spinner"></span>' +
                         '<span>Cargando registro…</span></div>';
        var current = { vista: null, id: null };

        function openModal() {
            overlay.classList.add('open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('rv-no-scroll');
        }
        function closeModal() {
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('rv-no-scroll');
        }

        function loadRecord(vista, id) {
            current.vista = vista; current.id = id;
            elTitle.textContent = 'Cargando…';
            elSub.textContent = '';
            elBadge.hidden = true;
            elBody.innerHTML = loaderHtml;
            if (elEdit) elEdit.style.display = 'none';
            if (elPdf)  elPdf.style.display = 'none';
            openModal();

            var url = BASE + '/api/v1/record.php?vista=' +
                      encodeURIComponent(vista) + '&id=' + encodeURIComponent(id);

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        elTitle.textContent = 'No disponible';
                        elBody.innerHTML = '<div class="rv-error"><i class="fas fa-circle-exclamation me-2"></i>' +
                            ((data && data.error) ? data.error : 'No se pudo cargar el registro.') + '</div>';
                        return;
                    }
                    elTitle.textContent = data.titulo || 'Registro';
                    elSub.textContent   = data.subtitle || '';
                    if (data.icono) elIcon.className = 'fas fa-' + data.icono;
                    if (data.estado) {
                        elBadge.textContent = data.estado.charAt(0).toUpperCase() +
                            data.estado.slice(1).replace(/_/g, ' ');
                        elBadge.className = 'rv-hero-badge ' + (data.estado_clase || 'rv-slate');
                        elBadge.hidden = false;
                    } else {
                        elBadge.hidden = true;
                    }
                    elBody.innerHTML = data.html || '';
                    if (elEdit) {
                        if (data.edit_url) { elEdit.href = data.edit_url; elEdit.style.display = ''; }
                        else { elEdit.style.display = 'none'; }
                    }
                    if (elPdf) {
                        elPdf.href = BASE + '/api/v1/record_report.php?vista=' +
                                     encodeURIComponent(vista) + '&id=' + encodeURIComponent(id);
                        elPdf.style.display = '';
                    }
                })
                .catch(function () {
                    elTitle.textContent = 'Error de conexión';
                    elBody.innerHTML = '<div class="rv-error"><i class="fas fa-circle-exclamation me-2"></i>' +
                        'No se pudo conectar con el servidor.</div>';
                });
        }

        // Delegación global: cualquier .js-view abre el visualizador
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.js-view') : null;
            if (!btn) return;
            e.preventDefault();
            var vista = btn.getAttribute('data-vista');
            var id    = btn.getAttribute('data-id');
            if (vista && id) loadRecord(vista, id);
        });

        if (elClose) elClose.addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
        });
        if (elPrint) elPrint.addEventListener('click', function () { window.print(); });

        // expuesto por si se quiere invocar manualmente
        window.TTViewer = { open: loadRecord, close: closeModal };
    }

    var booted = false;
    function boot() {
        if (booted) return;
        if (!document.getElementById('rv-overlay')) return; // modal aún no presente
        booted = true;
        init();
    }

    // Intentar de inmediato (script al final del body) y además en los eventos
    // de carga, por si se incluyera en otra posición. Protegido contra doble init.
    boot();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    }
    window.addEventListener('load', boot);
})();
