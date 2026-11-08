/**
 * TriTransportes — Trabajo sin conexión
 * ----------------------------------------------------------------------------
 * En faena la señal se corta. Este módulo hace tres cosas:
 *   1. Registra el service worker (que mantiene la app abierta sin red).
 *   2. Guarda en el teléfono los formularios enviados sin conexión.
 *   3. Los reenvía solo cuando la señal vuelve, avisando del resultado.
 *
 * Los formularios que participan se marcan con data-offline="1".
 */
(function () {
    'use strict';

    const BASE = window.TT_BASE || '';

    // ── 1. Service worker ───────────────────────────────────────────────────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' })
                .catch(function () { /* sin service worker la app funciona igual */ });
        });
    }

    // ── 2. Almacén local de envíos pendientes (IndexedDB) ───────────────────
    const BD_NOMBRE = 'tritransportes_offline';
    const ALMACEN   = 'pendientes';

    function abrirBD() {
        return new Promise(function (resolve, reject) {
            const solicitud = indexedDB.open(BD_NOMBRE, 1);
            solicitud.onupgradeneeded = function () {
                const bd = solicitud.result;
                if (!bd.objectStoreNames.contains(ALMACEN)) {
                    bd.createObjectStore(ALMACEN, { keyPath: 'id', autoIncrement: true });
                }
            };
            solicitud.onsuccess = () => resolve(solicitud.result);
            solicitud.onerror   = () => reject(solicitud.error);
        });
    }

    function conAlmacen(modo, operacion) {
        return abrirBD().then(function (bd) {
            return new Promise(function (resolve, reject) {
                const tx = bd.transaction(ALMACEN, modo);
                const resultado = operacion(tx.objectStore(ALMACEN));
                tx.oncomplete = () => resolve(resultado && resultado.result !== undefined ? resultado.result : resultado);
                tx.onerror    = () => reject(tx.error);
            });
        });
    }

    const guardarPendiente = (registro) => conAlmacen('readwrite', (a) => a.add(registro));
    const listarPendientes = ()          => conAlmacen('readonly',  (a) => a.getAll());
    const borrarPendiente  = (id)        => conAlmacen('readwrite', (a) => a.delete(id));

    // ── 3. Aviso visual del estado ──────────────────────────────────────────
    let barra = null;

    function mostrarAviso(texto, tipo) {
        if (!barra) {
            barra = document.createElement('div');
            barra.className = 'offline-barra';
            document.body.appendChild(barra);
        }
        barra.className = 'offline-barra visible ' + (tipo || 'info');
        barra.innerHTML = texto;
    }

    function ocultarAviso(retardo) {
        if (!barra) return;
        setTimeout(function () { barra.classList.remove('visible'); }, retardo || 4000);
    }

    async function actualizarContador() {
        try {
            const pendientes = await listarPendientes();
            document.querySelectorAll('.offline-pendientes').forEach(function (el) {
                el.textContent = pendientes.length;
                el.closest('.offline-chip')?.classList.toggle('d-none', pendientes.length === 0);
            });
            return pendientes.length;
        } catch (e) { return 0; }
    }

    // ── 4. Interceptar el envío de formularios de terreno ───────────────────
    document.querySelectorAll('form[data-offline="1"]').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            if (navigator.onLine) return;   // con señal, comportamiento normal

            evento.preventDefault();

            const datos = {};
            new FormData(form).forEach(function (valor, clave) {
                // Los archivos no se pueden guardar de forma fiable: se avisa aparte
                if (valor instanceof File) return;
                if (datos[clave] !== undefined) {
                    if (!Array.isArray(datos[clave])) datos[clave] = [datos[clave]];
                    datos[clave].push(valor);
                } else {
                    datos[clave] = valor;
                }
            });

            const tieneArchivo = Array.from(form.querySelectorAll('input[type="file"]'))
                                      .some((i) => i.files && i.files.length);

            guardarPendiente({
                url:      form.action || window.location.href,
                datos:    datos,
                titulo:   document.title,
                creado:   new Date().toISOString(),
            }).then(function () {
                actualizarContador();
                mostrarAviso(
                    '<i class="fas fa-cloud-arrow-up me-2"></i>' +
                    'Guardado en el teléfono. Se enviará solo cuando vuelva la señal.' +
                    (tieneArchivo ? ' <small>(la foto deberá adjuntarse luego)</small>' : ''),
                    'exito'
                );
                form.reset();
                ocultarAviso(6000);
            }).catch(function () {
                mostrarAviso('<i class="fas fa-triangle-exclamation me-2"></i>' +
                             'No se pudo guardar el registro en el teléfono.', 'error');
            });
        });
    });

    // ── 5. Sincronización al recuperar la señal ─────────────────────────────
    let sincronizando = false;

    async function sincronizar() {
        if (sincronizando || !navigator.onLine) return;

        const pendientes = await listarPendientes();
        if (!pendientes.length) return;

        sincronizando = true;
        mostrarAviso('<i class="fas fa-rotate fa-spin me-2"></i>Enviando ' +
                     pendientes.length + ' registro(s) pendiente(s)…', 'info');

        let enviados = 0, fallidos = 0;

        for (const registro of pendientes) {
            try {
                const cuerpo = new URLSearchParams();
                Object.keys(registro.datos).forEach(function (clave) {
                    const v = registro.datos[clave];
                    if (Array.isArray(v)) v.forEach((x) => cuerpo.append(clave, x));
                    else cuerpo.append(clave, v);
                });

                const respuesta = await fetch(registro.url, {
                    method:      'POST',
                    body:        cuerpo,
                    headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    redirect:    'follow',
                });

                if (respuesta.ok || respuesta.redirected) {
                    await borrarPendiente(registro.id);
                    enviados++;
                } else {
                    fallidos++;
                }
            } catch (e) {
                fallidos++;
                break;  // se perdió la señal otra vez: se reintenta más tarde
            }
        }

        sincronizando = false;
        await actualizarContador();

        if (enviados && !fallidos) {
            mostrarAviso('<i class="fas fa-circle-check me-2"></i>' +
                         enviados + ' registro(s) enviado(s) correctamente.', 'exito');
        } else if (fallidos) {
            mostrarAviso('<i class="fas fa-triangle-exclamation me-2"></i>' +
                         fallidos + ' registro(s) quedaron pendientes. Se reintentará.', 'error');
        }
        ocultarAviso(6000);
    }

    window.addEventListener('online', function () {
        mostrarAviso('<i class="fas fa-wifi me-2"></i>Conexión recuperada.', 'exito');
        ocultarAviso(2500);
        setTimeout(sincronizar, 1200);
    });

    window.addEventListener('offline', function () {
        mostrarAviso('<i class="fas fa-plane-up me-2"></i>Sin conexión. ' +
                     'Puedes seguir registrando: se guardará en el teléfono.', 'aviso');
    });

    // Estado inicial
    document.addEventListener('DOMContentLoaded', function () {
        actualizarContador().then(function (n) {
            if (!navigator.onLine) {
                mostrarAviso('<i class="fas fa-plane-up me-2"></i>Trabajando sin conexión.', 'aviso');
            } else if (n > 0) {
                sincronizar();
            }
        });
    });

    // Se expone por si se quiere forzar el envío desde la interfaz
    window.TT_sincronizar = sincronizar;
})();
