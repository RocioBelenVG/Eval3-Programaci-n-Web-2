/* TriTransportes — app.js v2.0 */

// ── Sidebar toggle (mobile) ─────────────────────────────────
(function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle  = document.getElementById('sidebarToggle');

    function openSidebar()  { sidebar?.classList.add('sidebar-open');    overlay?.classList.add('active'); }
    function closeSidebar() { sidebar?.classList.remove('sidebar-open'); overlay?.classList.remove('active'); }

    toggle?.addEventListener('click', openSidebar);
    overlay?.addEventListener('click', closeSidebar);
})();

// ── Auto-dismiss alerts 5s ──────────────────────────────────
setTimeout(function () {
    document.querySelectorAll('.alert.alert-success').forEach(function (el) {
        bootstrap.Alert.getOrCreateInstance(el).close();
    });
}, 5000);

// ── Confirm handler ─────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        if (!confirm(this.dataset.confirm || '¿Confirma esta acción?')) e.preventDefault();
    });
});

// ── KPI Card 3D Tilt ────────────────────────────────────────
(function () {
    const STRENGTH = 12;

    function enableTilt(card) {
        card.addEventListener('mousemove', function (e) {
            const rect   = card.getBoundingClientRect();
            const cx     = rect.left + rect.width  / 2;
            const cy     = rect.top  + rect.height / 2;
            const dx     = (e.clientX - cx) / (rect.width  / 2);
            const dy     = (e.clientY - cy) / (rect.height / 2);
            const rotateX = -dy * STRENGTH;
            const rotateY =  dx * STRENGTH;

            card.classList.add('tilt-active');
            card.style.transform = `perspective(900px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-5px) scale(1.02)`;
        });

        card.addEventListener('mouseleave', function () {
            card.classList.remove('tilt-active');
            card.style.transform = '';
        });
    }

    document.querySelectorAll('.kpi-card, .module-card').forEach(enableTilt);
})();

// ── Animated Counter ─────────────────────────────────────────
(function () {
    function animateCount(el) {
        const target = parseFloat(el.dataset.count);
        if (isNaN(target)) return;

        const isDecimal = String(target).includes('.');
        const decimals  = isDecimal ? (String(target).split('.')[1] || '').length : 0;
        const prefix    = el.dataset.prefix || '';
        const suffix    = el.dataset.suffix || '';
        const duration  = 1200;
        const start     = performance.now();

        function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

        function frame(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const current  = target * easeOut(progress);
            el.textContent = prefix + current.toFixed(decimals) + suffix;
            if (progress < 1) requestAnimationFrame(frame);
        }

        requestAnimationFrame(frame);
    }

    // Usar IntersectionObserver para animar al entrar en viewport
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                animateCount(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    document.querySelectorAll('[data-count]').forEach(function (el) {
        observer.observe(el);
    });
})();

// ── Entrance stagger for any .stagger-in children ───────────
(function () {
    const groups = document.querySelectorAll('.stagger-group');
    groups.forEach(function (group) {
        const children = group.querySelectorAll('.stagger-item');
        children.forEach(function (el, i) {
            el.style.animationDelay = (i * 0.07) + 's';
        });
    });
})();

// ============================================================
// CHATBOT IA — TriTransportes
// ============================================================
(function () {
    var toggle   = document.getElementById('chatbot-toggle');
    var panel    = document.getElementById('chatbot-panel');
    var closeBtn = document.getElementById('chatbot-close');
    var msgs     = document.getElementById('chatbot-messages');
    var input    = document.getElementById('chatbot-input');
    var sendBtn  = document.getElementById('chatbot-send');

    if (!toggle) return;

    var history   = [];
    var isOpen    = false;
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function togglePanel() {
        isOpen = !isOpen;
        panel.classList.toggle('open', isOpen);
        toggle.innerHTML = isOpen
            ? '<i class="fas fa-times"></i>'
            : '<i class="fas fa-robot"></i>';
        if (isOpen && msgs.children.length === 0) {
            appendMsg('¡Hola! Soy el asistente de TriTransportes. Puedo ayudarte con la navegación del sistema, el uso de módulos y cualquier duda operacional. ¿En qué puedo ayudarte?', 'bot');
        }
        if (isOpen) input.focus();
    }

    toggle.addEventListener('click', togglePanel);
    if (closeBtn) closeBtn.addEventListener('click', togglePanel);

    function appendMsg(text, type) {
        var div = document.createElement('div');
        div.className = 'chat-msg ' + type;
        div.textContent = text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
        return div;
    }

    function sendMessage() {
        var text = input.value.trim();
        if (!text || sendBtn.disabled) return;

        input.value = '';
        appendMsg(text, 'user');
        sendBtn.disabled = true;
        var thinking = appendMsg('Escribiendo...', 'thinking');

        fetch((window.TT_BASE || '') + '/api/v1/chatbot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ message: text, history: history }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            msgs.removeChild(thinking);
            if (data.error) {
                appendMsg('⚠ ' + data.error, 'bot');
            } else {
                history.push({ role: 'user',      content: text });
                history.push({ role: 'assistant', content: data.reply });
                if (history.length > 20) history = history.slice(-20);
                appendMsg(data.reply, 'bot');
            }
        })
        .catch(function () {
            msgs.removeChild(thinking);
            appendMsg('Error de conexión. Verifique su red e intente nuevamente.', 'bot');
        })
        .finally(function () {
            sendBtn.disabled = false;
            input.focus();
        });
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
})();

// ── Select dependiente genérico ─────────────────────────────
document.querySelectorAll('select[data-parent]').forEach(function (child) {
    const parentSel = document.querySelector(child.dataset.parent);
    if (!parentSel) return;

    parentSel.addEventListener('change', function () {
        const val = this.value;
        const url = child.dataset.url + '&parent_id=' + encodeURIComponent(val);
        child.innerHTML = '<option value="">Cargando...</option>';
        fetch(url)
            .then(r => r.json())
            .then(function (data) {
                child.innerHTML = '<option value="">— Seleccione —</option>';
                data.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.nombre;
                    child.appendChild(opt);
                });
            })
            .catch(function () {
                child.innerHTML = '<option value="">Error al cargar</option>';
            });
    });
});

// ============================================================
// ADAPTACIÓN MÓVIL — se aplica a todos los módulos sin tocarlos
// ============================================================

// ── Tablas → tarjetas: copia el encabezado de cada columna a la celda ──
(function () {
    function etiquetarTabla(tabla) {
        const encabezados = Array.from(tabla.querySelectorAll('thead th'))
            .map(th => th.textContent.trim());
        if (!encabezados.length) return;

        tabla.classList.add('tabla-movil');

        tabla.querySelectorAll('tbody tr').forEach(function (fila) {
            const celdas = fila.children;
            for (let i = 0; i < celdas.length; i++) {
                // Las filas de "sin registros" usan colspan: se dejan centradas
                if (celdas[i].hasAttribute('colspan')) continue;
                if (celdas[i].hasAttribute('data-label')) continue;
                celdas[i].setAttribute('data-label', encabezados[i] || '');
            }
        });

        // El pie de tabla se resume en una sola línea legible
        const pie = tabla.querySelector('tfoot tr');
        if (pie) {
            const conTexto = Array.from(pie.children)
                .filter(td => td.textContent.trim() !== '' && !td.hasAttribute('data-label'));
            conTexto.forEach(td => td.classList.add('tfoot-movil'));
        }
    }

    document.querySelectorAll('table.table').forEach(etiquetarTabla);
})();

// ============================================================
// FIRMA DIGITAL EN CANVAS + CAPTURA GPS
// Componente de terreno: funciona con dedo (touch) y con mouse.
// ============================================================
(function () {

    // ── Firma ───────────────────────────────────────────────
    document.querySelectorAll('[data-firma]').forEach(function (caja) {
        const canvas      = caja.querySelector('.firma-canvas');
        const campo       = caja.querySelector('.firma-input');
        const botonBorrar = caja.querySelector('.firma-limpiar');
        const marcaAgua   = caja.querySelector('.firma-placeholder');
        if (!canvas || !campo) return;

        const ctx = canvas.getContext('2d');
        let trazando = false;
        let hayTrazo = false;

        function ajustarTamano() {
            // Se conserva lo dibujado al cambiar el tamaño (giro del teléfono)
            const previo = hayTrazo ? canvas.toDataURL() : null;
            const ancho  = caja.clientWidth || 320;
            const escala = window.devicePixelRatio || 1;

            canvas.width  = ancho * escala;
            canvas.height = 170 * escala;
            canvas.style.width  = ancho + 'px';
            canvas.style.height = '170px';

            ctx.scale(escala, escala);
            ctx.lineWidth   = 2.2;
            ctx.lineCap     = 'round';
            ctx.lineJoin    = 'round';
            ctx.strokeStyle = '#0f172a';

            if (previo) {
                const img = new Image();
                img.onload = () => ctx.drawImage(img, 0, 0, ancho, 170);
                img.src = previo;
            }
        }

        function posicion(e) {
            const r = canvas.getBoundingClientRect();
            const p = e.touches ? e.touches[0] : e;
            return { x: p.clientX - r.left, y: p.clientY - r.top };
        }

        function iniciar(e) {
            e.preventDefault();
            trazando = true;
            const { x, y } = posicion(e);
            ctx.beginPath();
            ctx.moveTo(x, y);
            if (marcaAgua) marcaAgua.classList.add('oculto');
        }

        function mover(e) {
            if (!trazando) return;
            e.preventDefault();
            const { x, y } = posicion(e);
            ctx.lineTo(x, y);
            ctx.stroke();
            hayTrazo = true;
        }

        function terminar() {
            if (!trazando) return;
            trazando = false;
            if (hayTrazo) campo.value = canvas.toDataURL('image/png');
        }

        canvas.addEventListener('mousedown', iniciar);
        canvas.addEventListener('mousemove', mover);
        window.addEventListener('mouseup', terminar);
        canvas.addEventListener('touchstart', iniciar, { passive: false });
        canvas.addEventListener('touchmove', mover, { passive: false });
        canvas.addEventListener('touchend', terminar);

        botonBorrar?.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            campo.value = '';
            hayTrazo = false;
            if (marcaAgua) marcaAgua.classList.remove('oculto');
        });

        ajustarTamano();
        window.addEventListener('resize', ajustarTamano);

        // Firma ya guardada (modo edición): se dibuja sobre el lienzo
        if (campo.value) {
            const img = new Image();
            img.onload = function () {
                ctx.drawImage(img, 0, 0, caja.clientWidth || 320, 170);
                hayTrazo = true;
                if (marcaAgua) marcaAgua.classList.add('oculto');
            };
            img.src = campo.value;
        }
    });

    // ── Ubicación GPS ───────────────────────────────────────
    document.querySelectorAll('[data-gps]').forEach(function (caja) {
        const boton  = caja.querySelector('.gps-capturar');
        const estado = caja.querySelector('.gps-estado');
        const coords = caja.querySelector('.gps-coords');
        const inLat  = caja.querySelector('.gps-input-lat');
        const inLng  = caja.querySelector('.gps-input-lng');
        if (!boton) return;

        function mostrar(icono, color, texto) {
            if (!estado) return;
            estado.innerHTML = '<i class="fas fa-' + icono + ' text-' + color + '"></i>' +
                               '<span class="gps-texto">' + texto + '</span>';
        }

        boton.addEventListener('click', function () {
            if (!navigator.geolocation) {
                mostrar('triangle-exclamation', 'warning', 'Este dispositivo no entrega ubicación');
                return;
            }

            boton.disabled = true;
            mostrar('spinner fa-spin', 'muted', 'Obteniendo ubicación…');

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude.toFixed(7);
                    const lng = pos.coords.longitude.toFixed(7);
                    inLat.value = lat;
                    inLng.value = lng;

                    mostrar('circle-check', 'success',
                            'Ubicación capturada (±' + Math.round(pos.coords.accuracy) + " m)");

                    if (coords) {
                        coords.classList.remove('d-none');
                        coords.querySelector('.gps-lat').textContent = lat;
                        coords.querySelector('.gps-lng').textContent = lng;
                        const mapa = coords.querySelector('.gps-mapa');
                        if (mapa) mapa.href = 'https://www.google.com/maps?q=' + lat + ',' + lng;
                    }
                    boton.disabled = false;
                },
                function (err) {
                    const motivo = err.code === 1 ? 'Permiso denegado'
                                 : err.code === 2 ? 'Ubicación no disponible'
                                 : 'Tiempo de espera agotado';
                    mostrar('circle-xmark', 'danger', motivo);
                    boton.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 }
            );
        });
    });
})();

// ============================================================
// FILTROS AUTOMÁTICOS — se aplican solos, sin botón "Filtrar"
// ============================================================
(function () {
    const RETARDO_TEXTO = 550; // ms de espera tras dejar de escribir

    // Un formulario de filtros es todo GET dentro de la cabecera de una tarjeta
    const formularios = document.querySelectorAll(
        '.card-header form[method="GET"], .card-header form.js-filtros, form.js-filtros'
    );

    formularios.forEach(function (form) {
        if (form.dataset.autoFiltro === 'listo') return;
        form.dataset.autoFiltro = 'listo';

        let temporizador = null;
        let enviando     = false;

        function enviar(campoFoco) {
            if (enviando) return;
            enviando = true;
            form.classList.add('filtrando');

            // Recuerda qué campo se estaba usando para devolverle el foco al recargar
            if (campoFoco) {
                let marca = form.querySelector('input[name="_foco"]');
                if (!marca) {
                    marca = document.createElement('input');
                    marca.type = 'hidden';
                    marca.name = '_foco';
                    form.appendChild(marca);
                }
                marca.value = campoFoco;
            }

            // Al cambiar un filtro siempre se vuelve a la primera página
            const pagina = form.querySelector('input[name="page"]');
            if (pagina) pagina.value = 1;

            form.submit();
        }

        form.querySelectorAll('select, input, textarea').forEach(function (campo) {
            const tipo = (campo.type || '').toLowerCase();
            if (['hidden', 'submit', 'button', 'reset', 'file'].includes(tipo)) return;

            if (campo.tagName === 'SELECT' || ['date', 'month', 'checkbox', 'radio'].includes(tipo)) {
                campo.addEventListener('change', () => enviar(campo.name));
                return;
            }

            // Campos de texto: se espera a que el usuario deje de escribir
            campo.addEventListener('input', function () {
                clearTimeout(temporizador);
                temporizador = setTimeout(() => enviar(campo.name), RETARDO_TEXTO);
            });
            // Enter aplica de inmediato
            campo.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(temporizador);
                    enviar(campo.name);
                }
            });
        });

        // El botón de aplicar deja de ser necesario; se conserva en el DOM por
        // si el navegador tuviera JS deshabilitado, pero se oculta.
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (b) {
            const txt = (b.textContent || '').toLowerCase();
            if (txt.includes('filtrar') || txt.includes('aplicar') || txt.includes('buscar')) {
                b.classList.add('d-none');
            }
        });
    });

    // Devuelve el foco al campo que se estaba usando antes de recargar
    (function restaurarFoco() {
        const params = new URLSearchParams(window.location.search);
        const nombre = params.get('_foco');
        if (!nombre) return;
        const campo = document.querySelector('[name="' + CSS.escape(nombre) + '"]');
        if (!campo) return;
        campo.focus();
        if (typeof campo.setSelectionRange === 'function' && campo.value) {
            try { campo.setSelectionRange(campo.value.length, campo.value.length); } catch (e) {}
        }
    })();
})();

// ── Filtros plegables en móvil ──────────────────────────────
(function () {
    const esMovil = () => window.matchMedia('(max-width: 767.98px)').matches;

    document.querySelectorAll('.table-card > .card-header form[method="GET"], .card-header form.js-filtros')
        .forEach(function (form, idx) {
            if (form.closest('.filtros-body')) return;

            // Envuelve el formulario para poder plegarlo
            const caja = document.createElement('div');
            caja.className = 'filtros-body';
            caja.id = 'filtros-' + idx;
            form.parentNode.insertBefore(caja, form);
            caja.appendChild(form);

            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn btn-sm btn-outline-secondary filtros-toggle';
            boton.setAttribute('aria-controls', caja.id);
            boton.setAttribute('aria-expanded', 'false');
            boton.innerHTML = '<i class="fas fa-sliders-h"></i><span>Filtros y acciones</span>';
            caja.parentNode.insertBefore(boton, caja);

            boton.addEventListener('click', function () {
                const abierto = caja.classList.toggle('abierto');
                boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                boton.querySelector('span').textContent = abierto ? 'Ocultar filtros' : 'Filtros y acciones';
            });

            // Si el usuario llegó con filtros aplicados, se muestra abierto
            if (esMovil() && window.location.search.replace(/^\?/, '').length > 0) {
                caja.classList.add('abierto');
                boton.setAttribute('aria-expanded', 'true');
                boton.querySelector('span').textContent = 'Ocultar filtros';
            }
        });
})();

// ── Cerrar el menú lateral al navegar (móvil) ───────────────
(function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;

    sidebar.querySelectorAll('.nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
            sidebar.classList.remove('sidebar-open');
            overlay?.classList.remove('active');
        });
    });

    // Gesto: deslizar hacia la izquierda cierra el menú
    let xInicio = null;
    sidebar.addEventListener('touchstart', e => { xInicio = e.touches[0].clientX; }, { passive: true });
    sidebar.addEventListener('touchend', function (e) {
        if (xInicio === null) return;
        if (xInicio - e.changedTouches[0].clientX > 60) {
            sidebar.classList.remove('sidebar-open');
            overlay?.classList.remove('active');
        }
        xInicio = null;
    }, { passive: true });
})();

// El visualizador de registros se movió a assets/js/viewer.js (archivo independiente y resiliente).
