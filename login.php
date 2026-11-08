<?php
require_once __DIR__ . '/bootstrap.php';

if (Auth::check()) redirect('/modules/dashboard/');

$error = '';
$msg   = get('msg');

if (isPost()) {
    CSRF::require();
    $email    = post('email');
    $password = post('password');

    if (!$email || !$password) {
        $error = 'Ingrese su email y contraseña.';
    } else {
        $result = Auth::login($email, $password);
        if ($result === true) {
            redirect('/modules/dashboard/');
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.0/css/all.min.css" integrity="sha384-/o6I2CkkWC//PSjvWC/eYN7l3xM3tJm8ZzVkCOfp//W05QcE3mlGskpoHB6XqI+B" crossorigin="anonymous">
    <style>
        :root{
            --navy:#0f172a; --navy2:#1e293b; --navy3:#334155;
            --amber:#f59e0b; --amber2:#d97706; --amber-soft:#fbbf24;
            --ink:#0f172a; --muted:#64748b; --line:#e7edf4;
        }
        *{box-sizing:border-box;}
        html,body{height:100%;}
        body{
            margin:0; font-family:"Segoe UI",system-ui,-apple-system,sans-serif;
            background:#eef2f7; color:var(--ink); overflow-x:hidden;
        }

        .tx-split{ display:flex; min-height:100vh; }

        /* ===================== PANEL IZQUIERDO ===================== */
        .tx-left{
            position:relative; flex:1.1; overflow:hidden;
            display:grid; grid-template-columns:1.05fr 0.95fr; align-items:center; gap:26px;
            padding:48px 52px;
            background:
                radial-gradient(140% 120% at 88% -10%, rgba(245,158,11,.20), transparent 55%),
                radial-gradient(120% 120% at 5% 110%, rgba(59,130,246,.12), transparent 55%),
                linear-gradient(150deg, #0b1322 0%, var(--navy) 45%, var(--navy2) 100%);
            color:#fff;
        }
        .tx-left::before{
            content:""; position:absolute; inset:0; pointer-events:none; opacity:.45;
            background-image:
                linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size:34px 34px;
            -webkit-mask-image:radial-gradient(120% 100% at 25% 25%, #000, transparent 80%);
                    mask-image:radial-gradient(120% 100% at 25% 25%, #000, transparent 80%);
        }
        .tx-copy{ position:relative; z-index:2; max-width:460px; }

        .tx-eyebrow{
            display:inline-flex; align-items:center; gap:9px;
            font-size:.72rem; font-weight:700; letter-spacing:.22em; text-transform:uppercase;
            color:var(--amber-soft); margin-bottom:20px;
        }
        .tx-eyebrow span{ width:30px; height:2px; background:var(--amber); border-radius:2px; }

        .tx-headline{
            font-size:2.2rem; font-weight:800; line-height:1.14; letter-spacing:-.01em; margin:0 0 16px;
        }
        .tx-headline em{ color:var(--amber); font-style:normal; }
        .tx-desc{
            font-size:1rem; line-height:1.65; color:rgba(255,255,255,.78); margin:0 0 28px;
        }

        .tx-feats{ display:flex; flex-wrap:wrap; gap:10px; }
        .tx-pill{
            display:inline-flex; align-items:center; gap:8px;
            font-size:.82rem; font-weight:600; color:#e7eefc;
            padding:9px 14px; border-radius:999px;
            background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12);
            box-shadow:inset 0 1px 0 rgba(255,255,255,.08);
            transition:transform .25s ease, border-color .25s ease, background .25s ease;
        }
        .tx-pill i{ color:var(--amber-soft); }
        .tx-pill:hover{ transform:translateY(-2px); border-color:rgba(245,158,11,.4); background:rgba(245,158,11,.10); }

        /* ---- Escena 3D (composición separada, sin solapamientos) ---- */
        .tx-stage{
            position:relative; z-index:2; height:480px; perspective:1300px;
        }
        .tx-deck{
            position:absolute; inset:0; transform-style:preserve-3d;
            transform:rotateY(-17deg) rotateX(7deg);
        }
        .tx-card3d{
            position:absolute; border-radius:18px;
            background:linear-gradient(150deg, rgba(255,255,255,.16), rgba(255,255,255,.045));
            border:1px solid rgba(255,255,255,.22);
            box-shadow:0 34px 64px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.25);
            -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px);
        }
        /* mini-dashboard: abajo, bien separado de los chips */
        .tx-dash{
            left:24px; bottom:8px; width:300px; height:200px; padding:18px;
            transform:translateZ(0); animation:txDash 7s ease-in-out infinite;
        }
        .tx-dash-head{ display:flex; align-items:center; gap:8px; margin-bottom:14px; }
        .tx-dot{ width:9px; height:9px; border-radius:50%; }
        .tx-dot.a{ background:#ef4444; } .tx-dot.b{ background:#f59e0b; } .tx-dot.c{ background:#22c55e; }
        .tx-dash-title{ margin-left:6px; font-size:.7rem; font-weight:700; letter-spacing:.12em; color:rgba(255,255,255,.7); text-transform:uppercase; }
        .tx-kpis{ display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px; }
        .tx-kpi{ background:rgba(255,255,255,.10); border-radius:10px; padding:9px 11px; border:1px solid rgba(255,255,255,.10); }
        .tx-kpi b{ display:block; font-size:1.05rem; font-weight:800; color:#fff; line-height:1; margin-bottom:3px; }
        .tx-kpi span{ font-size:.58rem; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.6); }
        .tx-bars{ display:flex; align-items:flex-end; gap:7px; height:46px; }
        .tx-bar{ flex:1; border-radius:5px 5px 0 0; background:linear-gradient(180deg, var(--amber-soft), var(--amber2)); box-shadow:0 0 14px rgba(245,158,11,.4); }

        /* chips de módulos: escalera arriba, separados entre sí y del dashboard */
        .tx-chip{
            position:absolute; display:flex; align-items:center; gap:9px;
            padding:11px 15px; width:max-content; max-width:200px;
            font-size:.82rem; font-weight:700; color:#fff; border-radius:14px;
            background:linear-gradient(150deg, rgba(255,255,255,.20), rgba(255,255,255,.07));
            border:1px solid rgba(255,255,255,.26);
            box-shadow:0 18px 34px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.22);
            animation:txChips 6.5s ease-in-out infinite;
        }
        .tx-chip i{
            width:30px; height:30px; border-radius:9px; display:grid; place-items:center;
            color:var(--navy); background:linear-gradient(160deg, var(--amber-soft), var(--amber2));
            box-shadow:0 4px 10px rgba(245,158,11,.45);
        }
        .tx-c1{ left:6px;  top:8px;   transform:translateZ(72px); }
        .tx-c2{ left:34px; top:70px;  transform:translateZ(72px); }
        .tx-c3{ left:6px;  top:132px; transform:translateZ(72px); }
        .tx-c4{ left:34px; top:194px; transform:translateZ(72px); }

        @keyframes txChips{ 0%,100%{ transform:translateZ(72px) translateY(0); } 50%{ transform:translateZ(72px) translateY(-9px); } }
        @keyframes txDash { 0%,100%{ transform:translateZ(0) translateY(0); }    50%{ transform:translateZ(0) translateY(8px); } }

        /* ===================== PANEL DERECHO (FORM) ===================== */
        .tx-right{
            flex:1; display:flex; align-items:center; justify-content:center;
            padding:48px 40px;
            background-image:radial-gradient(100% 100% at 50% 0%, #ffffff, #eef2f7 80%);
        }
        .tx-form-wrap{ width:100%; max-width:412px; }

        .tx-brand{ text-align:center; margin-bottom:6px; line-height:1; user-select:none; }
        .tx-brand-main, .tx-brand-accent{ font-weight:900; font-size:2.55rem; letter-spacing:.02em; }
        .tx-brand-main{
            color:#1e293b;
            text-shadow:1px 1px 0 #cbd5e1, 2px 2px 0 #b8c2d0, 3px 3px 0 #aab5c4, 4px 4px 0 #97a3b4, 5px 5px 10px rgba(15,23,42,.3);
        }
        .tx-brand-accent{
            color:var(--amber); margin-left:10px;
            text-shadow:1px 1px 0 #c8920a, 2px 2px 0 #b8850a, 3px 3px 0 #a8780a, 4px 4px 0 #986b09, 5px 5px 12px rgba(180,110,5,.45);
        }
        .tx-tagline{
            text-align:center; font-size:.7rem; font-weight:700; letter-spacing:.26em;
            text-transform:uppercase; color:var(--muted); margin:14px 0 30px;
        }

        .tx-card{
            background:#fff; border-radius:20px; padding:34px 32px; border:1px solid var(--line);
            box-shadow:0 30px 60px rgba(15,23,42,.12), 0 8px 20px rgba(15,23,42,.06), inset 0 1px 0 #fff;
        }
        .tx-welcome{ font-size:1.2rem; font-weight:800; color:var(--ink); margin:0 0 4px; }
        .tx-welcome-sub{ font-size:.88rem; color:var(--muted); margin:0 0 22px; }

        .tx-label{ font-size:.8rem; font-weight:700; color:#334155; margin-bottom:7px; display:block; }
        .tx-field{ position:relative; margin-bottom:18px; }
        .tx-field .tx-ic{ position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.95rem; pointer-events:none; }
        .tx-input{
            width:100%; height:50px; padding:0 46px 0 42px;
            border:1.5px solid var(--line); border-radius:12px; background:#f8fafc;
            font-size:.96rem; color:var(--ink); transition:all .18s ease; outline:none;
        }
        .tx-input::placeholder{ color:#aab4c2; }
        .tx-input:focus{ border-color:var(--amber); background:#fff; box-shadow:0 0 0 4px rgba(245,158,11,.16); }
        .tx-eye{
            position:absolute; right:8px; top:50%; transform:translateY(-50%);
            width:36px; height:36px; border:none; background:transparent; cursor:pointer;
            color:#94a3b8; border-radius:9px; transition:all .15s ease;
        }
        .tx-eye:hover{ color:var(--amber2); background:#fff4e2; }

        .tx-btn{
            width:100%; height:52px; border:none; border-radius:12px; cursor:pointer;
            font-size:1rem; font-weight:800; letter-spacing:.02em; color:#fff;
            background:linear-gradient(135deg, var(--amber-soft) 0%, var(--amber) 45%, var(--amber2) 100%);
            box-shadow:0 14px 26px rgba(245,158,11,.40), inset 0 1px 0 rgba(255,255,255,.4);
            transition:transform .15s ease, box-shadow .15s ease, filter .15s ease;
            display:inline-flex; align-items:center; justify-content:center; gap:10px;
        }
        .tx-btn:hover{ transform:translateY(-2px); box-shadow:0 20px 34px rgba(245,158,11,.48); filter:saturate(1.05); }
        .tx-btn:active{ transform:translateY(0); }

        .tx-alert{
            display:flex; align-items:center; gap:9px; font-size:.86rem; font-weight:600;
            padding:11px 14px; border-radius:11px; margin-bottom:18px; border:1px solid transparent;
        }
        .tx-alert.err{ background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
        .tx-alert.warn{ background:#fffbeb; color:#b45309; border-color:#fde68a; }

        .tx-foot{ text-align:center; font-size:.76rem; color:#94a3b8; margin-top:22px; }

        /* ===================== RESPONSIVO ===================== */
        /* Escritorio mediano: una sola columna a la izquierda (oculta la escena para no encimar el texto) */
        @media (max-width:1300px){
            .tx-left{ grid-template-columns:1fr; justify-items:start; }
            .tx-stage{ display:none; }
            .tx-copy{ max-width:560px; }
        }
        /* Tablet/móvil: se oculta el panel izquierdo, el formulario ocupa todo sobre fondo navy */
        @media (max-width:992px){
            .tx-left{ display:none; }
            .tx-right{ background:linear-gradient(160deg,#0b1322,#1e293b); }
            .tx-brand-main{ color:#fff; text-shadow:1px 1px 0 #334155,2px 2px 0 #2b3850,3px 3px 0 #243046,4px 4px 10px rgba(0,0,0,.5); }
            .tx-tagline{ color:rgba(255,255,255,.65); }
        }
        @media (max-width:480px){
            .tx-right{ padding:28px 18px; }
            .tx-card{ padding:26px 22px; }
            .tx-brand-main,.tx-brand-accent{ font-size:2.05rem; }
        }
        /* Móvil: 16px evita que iOS haga zoom al enfocar el campo, y
           se agrandan los objetivos táctiles. */
        @media (max-width:992px){
            .tx-input{ font-size:16px; height:52px; }
            .tx-btn{ height:54px; }
            .tx-eye{ width:44px; height:44px; }
            .tx-label{ font-size:.85rem; }
        }
        @media (max-width:360px){
            .tx-card{ padding:22px 16px; }
            .tx-brand-main,.tx-brand-accent{ font-size:1.75rem; }
        }
        @media (prefers-reduced-motion:reduce){
            .tx-chip,.tx-dash{ animation:none !important; }
        }
    </style>
</head>
<body>

<div class="tx-split">

    <!-- IZQUIERDA · DESCRIPCIÓN + ESCENA 3D -->
    <section class="tx-left">
        <div class="tx-copy">
            <div class="tx-eyebrow"><span></span>Gestión de flota y transporte</div>
            <h1 class="tx-headline">Toda tu flota,<br><em>bajo control</em>.</h1>
            <p class="tx-desc">
                TriTransportes centraliza viajes, guías de despacho, combustible,
                mantenciones y conductores de tu empresa de transporte.
                Información en tiempo real, trazable y en un solo lugar.
            </p>
            <div class="tx-feats">
                <span class="tx-pill"><i class="fas fa-chart-line"></i> KPIs en vivo</span>
                <span class="tx-pill"><i class="fas fa-shield-halved"></i> Trazabilidad total</span>
                <span class="tx-pill"><i class="fas fa-layer-group"></i> Multi-empresa</span>
                <span class="tx-pill"><i class="fas fa-bolt"></i> Operación ágil</span>
            </div>
        </div>

        <div class="tx-stage" aria-hidden="true">
            <div class="tx-deck">
                <div class="tx-card3d tx-dash">
                    <div class="tx-dash-head">
                        <span class="tx-dot a"></span><span class="tx-dot b"></span><span class="tx-dot c"></span>
                        <span class="tx-dash-title">Panel operacional</span>
                    </div>
                    <div class="tx-kpis">
                        <div class="tx-kpi"><b>128</b><span>Partes hoy</span></div>
                        <div class="tx-kpi"><b>3.480 L</b><span>Combustible</span></div>
                    </div>
                    <div class="tx-bars">
                        <div class="tx-bar" style="height:40%"></div>
                        <div class="tx-bar" style="height:70%"></div>
                        <div class="tx-bar" style="height:55%"></div>
                        <div class="tx-bar" style="height:90%"></div>
                        <div class="tx-bar" style="height:65%"></div>
                        <div class="tx-bar" style="height:80%"></div>
                    </div>
                </div>
                <div class="tx-chip tx-c1"><i class="fas fa-helmet-safety"></i> Faena</div>
                <div class="tx-chip tx-c2"><i class="fas fa-gas-pump"></i> Combustible</div>
                <div class="tx-chip tx-c3"><i class="fas fa-cogs"></i> Maquinaria</div>
                <div class="tx-chip tx-c4"><i class="fas fa-truck"></i> Transporte</div>
            </div>
        </div>
    </section>

    <!-- DERECHA · FORMULARIO -->
    <section class="tx-right">
        <div class="tx-form-wrap">

            <div class="tx-brand">
                <span class="tx-brand-main">TRI</span><span class="tx-brand-accent">TRANSPORTES</span>
            </div>
            <div class="tx-tagline">Gestión de flota y transporte</div>

            <div class="tx-card">
                <h2 class="tx-welcome">Bienvenido</h2>
                <p class="tx-welcome-sub">Inicia sesión para acceder a tu plataforma.</p>

                <?php if ($msg === 'timeout'): ?>
                    <div class="tx-alert warn">
                        <i class="fas fa-clock"></i> Sesión expirada por inactividad.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="tx-alert err">
                        <i class="fas fa-exclamation-circle"></i><?= h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <?= CSRF::field() ?>

                    <label for="email" class="tx-label">Email</label>
                    <div class="tx-field">
                        <i class="fas fa-envelope tx-ic"></i>
                        <input type="email" class="tx-input" id="email" name="email"
                               value="<?= h(post('email')) ?>"
                               placeholder="usuario@empresa.cl" required autocomplete="email">
                    </div>

                    <label for="password" class="tx-label">Contraseña</label>
                    <div class="tx-field">
                        <i class="fas fa-lock tx-ic"></i>
                        <input type="password" class="tx-input" id="password" name="password"
                               placeholder="••••••••" required autocomplete="current-password">
                        <button class="tx-eye" type="button" id="togglePwd" aria-label="Mostrar u ocultar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <button type="submit" class="tx-btn">
                        <i class="fas fa-arrow-right-to-bracket"></i> Ingresar
                    </button>
                </form>

                <div class="tx-foot"><?= APP_NAME ?> &middot; v<?= APP_VERSION ?></div>
            </div>
        </div>
    </section>
</div>

<script>
document.getElementById('togglePwd').addEventListener('click', function () {
    var pwd = document.getElementById('password');
    var icon = this.querySelector('i');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>
</body>
</html>
