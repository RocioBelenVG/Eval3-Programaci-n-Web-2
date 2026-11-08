    </div><!-- /page-content -->
</div><!-- /main-content -->

<!-- ============================================================
     CHATBOT IA — TriTransportes
     ============================================================ -->
<button id="chatbot-toggle" title="Asistente IA TriTransportes">
    <i class="fas fa-robot"></i>
</button>

<div id="chatbot-panel" role="dialog" aria-label="Asistente IA">
    <div class="chatbot-header">
        <i class="fas fa-robot text-warning"></i>
        <span class="chatbot-title">Asistente TriTransportes</span>
        <span class="chatbot-subtitle">IA · Siempre disponible</span>
        <button class="chatbot-close-btn" id="chatbot-close" title="Cerrar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="chatbot-messages" id="chatbot-messages" aria-live="polite"></div>
    <div class="chatbot-input-area">
        <input type="text" id="chatbot-input" placeholder="Escribe tu pregunta..." maxlength="1000" autocomplete="off" aria-label="Mensaje al asistente">
        <button class="chatbot-send-btn" id="chatbot-send" title="Enviar">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- ============================================================
     VISUALIZADOR DE REGISTROS — TriTransportes
     ============================================================ -->
<div id="rv-overlay" class="rv-overlay" aria-hidden="true">
    <div class="rv-modal" role="dialog" aria-modal="true" aria-labelledby="rv-title">
        <div class="rv-hero">
            <div class="rv-hero-icon"><i id="rv-icon" class="fas fa-file-alt"></i></div>
            <div class="rv-hero-text">
                <div class="rv-hero-title" id="rv-title">Cargando…</div>
                <div class="rv-hero-sub" id="rv-sub"></div>
            </div>
            <span class="rv-hero-badge" id="rv-badge" hidden></span>
            <button class="rv-close" id="rv-close" title="Cerrar" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </div>
        <div class="rv-body" id="rv-body">
            <div class="rv-loader"><span class="rv-spinner"></span><span>Cargando registro…</span></div>
        </div>
        <div class="rv-footer">
            <button type="button" class="rv-btn rv-btn-ghost" id="rv-print"><i class="fas fa-print me-1"></i>Imprimir</button>
            <a class="rv-btn rv-btn-pdf" id="rv-pdf" href="#" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-1"></i>Descargar PDF</a>
            <a class="rv-btn rv-btn-edit" id="rv-edit" href="#"><i class="fas fa-pen me-1"></i>Editar registro</a>
        </div>
    </div>
</div>

<script>window.TT_BASE = <?= json_encode(APP_URL) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js" integrity="sha384-dug+JxfBvklEQdJ4AYuBBAIScUz0bVN73xpy273gcAwHjb3qI0fXmuYNaNfdyYJG" crossorigin="anonymous"></script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=5"></script>
<script src="<?= APP_URL ?>/assets/js/viewer.js?v=5"></script>
<script src="<?= APP_URL ?>/assets/js/offline.js?v=1"></script>
</body>
</html>
