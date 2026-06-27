<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Shiftly</title>
    <meta name="description" content="Masuk ke Shiftly untuk mengakses platform penjadwalan staf medis berbasis AI.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4f7cff;
            --primary-dark: #2952e3;
            --primary-glow: rgba(79, 124, 255, 0.25);
            --secondary: #00adc7;
            --bg-base: #f8faff;
            --bg-card: #ffffff;
            --border: rgba(79, 124, 255, 0.12);
            --border-bright: rgba(79, 124, 255, 0.35);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            line-height: 1.6;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(79,124,255,0.09) 0%, transparent 70%),
                radial-gradient(ellipse 60% 50% at 90% 90%, rgba(0,201,224,0.06) 0%, transparent 60%);
        }

        /* ── GRID BG ── */
        .grid-bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background-image:
                linear-gradient(rgba(79,124,255,0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(79,124,255,0.06) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black, transparent);
        }

        /* ── PARTICLE CANVAS ── */
        #particle-canvas {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
        }

        /* ── GLOW BLOBS ── */
        .bg-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            pointer-events: none;
            z-index: 0;
        }
        .glow-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(79,124,255,0.13) 0%, transparent 70%);
            top: -120px; left: -150px;
            animation: float-glow 9s ease-in-out infinite alternate;
        }
        .glow-2 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(0,201,224,0.09) 0%, transparent 70%);
            bottom: -80px; right: -100px;
            animation: float-glow 11s ease-in-out infinite alternate-reverse;
        }

        @keyframes float-glow {
            0%   { transform: translate(0, 0) scale(1); }
            100% { transform: translate(25px, -20px) scale(1.08); }
        }

        /* ── LOGIN CARD ── */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            padding: 1.5rem;
            animation: fade-up 0.55s ease both;
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(79, 124, 255, 0.15);
            border-radius: 20px;
            padding: 2.75rem 2.5rem;
            box-shadow:
                0 8px 40px rgba(79, 124, 255, 0.1),
                0 2px 8px rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 20px 20px 0 0;
        }

        /* ── BRAND ── */
        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px; height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            margin-bottom: 1rem;
            box-shadow: 0 4px 20px var(--primary-glow);
        }

        .brand-logo .material-symbols-outlined {
            color: #fff;
            font-size: 28px;
        }

        .brand-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.25rem;
        }

        .brand-sub {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        /* ── ERROR ALERT ── */
        .alert-error {
            background: rgba(239, 68, 68, 0.07);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: #dc2626;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ── FORM ── */
        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%; left: 0.9rem;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 18px;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            background: #f8faff;
            border: 1px solid rgba(79, 124, 255, 0.15);
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 124, 255, 0.1);
        }

        input[type="email"]:focus + .input-icon,
        input[type="password"]:focus + .input-icon {
            color: var(--primary);
        }

        .input-wrap input:focus ~ .input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--primary);
        }

        /* ── SUBMIT BUTTON ── */
        .btn-submit {
            width: 100%;
            margin-top: 0.75rem;
            padding: 0.85rem 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 20px var(--primary-glow);
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-submit:hover::before { left: 100%; }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px var(--primary-glow);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* ── FOOTER LINK ── */
        .card-footer {
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(79, 124, 255, 0.08);
        }

        .card-footer a {
            font-size: 0.82rem;
            color: var(--text-muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: color 0.2s ease;
        }

        .card-footer a:hover {
            color: var(--primary);
        }

        .card-footer .material-symbols-outlined {
            font-size: 16px;
        }
    </style>
</head>
<body>

    <div class="grid-bg"></div>
    <canvas id="particle-canvas"></canvas>
    <div class="bg-glow glow-1"></div>
    <div class="bg-glow glow-2"></div>

    <div class="login-wrapper">
        <div class="login-card">

            <!-- Brand -->
            <div class="brand">
                <div class="brand-name">Shiftly</div>
                <div class="brand-sub">AI Scheduling Platform</div>
            </div>

            <!-- Error -->
            @if($errors->any())
                <div class="alert-error">
                    <span class="material-symbols-outlined" style="font-size:16px; flex-shrink:0;">error</span>
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <div class="input-wrap">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="nama@rumahsakit.com"
                        >
                        <span class="material-symbols-outlined input-icon">mail</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        >
                        <span class="material-symbols-outlined input-icon">lock</span>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span class="material-symbols-outlined" style="font-size:18px;">login</span>
                    Masuk ke Shiftly
                </button>
            </form>

            <!-- Footer -->
            <div class="card-footer">
                <a href="{{ url('/') }}">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Kembali ke Beranda
                </a>
            </div>

        </div>
    </div>

    <script>
        /* ── PARTICLE SYSTEM ── */
        (function () {
            const canvas = document.getElementById('particle-canvas');
            const ctx = canvas.getContext('2d');
            let W, H, particles = [], animId;

            const COLORS = ['rgba(79,124,255,', 'rgba(0,201,224,', 'rgba(167,139,250,'];
            const COUNT = window.innerWidth < 768 ? 30 : 60;

            function resize() {
                W = canvas.width = window.innerWidth;
                H = canvas.height = window.innerHeight;
            }

            function rand(a, b) { return a + Math.random() * (b - a); }

            function createParticle() {
                return {
                    x: Math.random() * W,
                    y: Math.random() * H,
                    r: rand(0.5, 2),
                    vx: rand(-0.12, 0.12),
                    vy: rand(-0.2, -0.04),
                    alpha: rand(0.15, 0.6),
                    color: COLORS[Math.floor(Math.random() * COLORS.length)]
                };
            }

            function init() {
                resize();
                particles = [];
                for (let i = 0; i < COUNT; i++) particles.push(createParticle());
                if (animId) cancelAnimationFrame(animId);
                animate();
            }

            function animate() {
                ctx.clearRect(0, 0, W, H);
                for (let p of particles) {
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = p.color + p.alpha + ')';
                    ctx.fill();

                    p.x += p.vx;
                    p.y += p.vy;
                    p.alpha += rand(-0.003, 0.003);
                    p.alpha = Math.max(0.08, Math.min(0.7, p.alpha));

                    if (p.y < -5) { p.y = H + 5; p.x = Math.random() * W; }
                    if (p.x < -5) p.x = W + 5;
                    if (p.x > W + 5) p.x = -5;
                }
                animId = requestAnimationFrame(animate);
            }

            window.addEventListener('resize', resize);
            init();
        })();
    </script>

</body>
</html>
