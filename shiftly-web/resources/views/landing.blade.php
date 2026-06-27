<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Shiftly Optimasi Penjadwalan Medis Berbasis AI</title>
    <meta name="description"
        content="Shiftly menggunakan Hybrid K-Means, Genetic Algorithm, dan Random Forest untuk mengoptimalkan penjadwalan staf medis secara presisi, mengurangi burnout dan meningkatkan efisiensi operasional rumah sakit." />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap"
        rel="stylesheet" />

    <style>
        :root {
            --primary: #4f7cff;
            --primary-dark: #2952e3;
            --primary-glow: rgba(79, 124, 255, 0.25);
            --secondary: #00adc7;
            --secondary-glow: rgba(0, 173, 199, 0.2);
            --accent: #7c5cfa;
            --accent-glow: rgba(124, 92, 250, 0.2);
            --bg-base: #f8faff;
            --bg-surface: #f0f4ff;
            --bg-card: #ffffff;
            --bg-card-hover: #f4f7ff;
            --border: rgba(79, 124, 255, 0.12);
            --border-bright: rgba(79, 124, 255, 0.35);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --success: #059669;
            --warning: #d97706;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(79,124,255,0.07) 0%, transparent 70%),
                        radial-gradient(ellipse 60% 50% at 90% 80%, rgba(0,201,224,0.05) 0%, transparent 60%);
        }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #e8eeff; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }

        /* ── PARTICLE CANVAS ── */
        #particle-canvas {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            opacity: 0.55;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 0 2rem;
            height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(248, 250, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(79, 124, 255, 0.1);
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(248, 250, 255, 0.96);
            box-shadow: 0 4px 30px rgba(79, 124, 255, 0.1);
        }

        .nav-logo {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .nav-links a:hover { color: var(--text-primary); }

        .nav-cta {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.4rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 30px var(--primary-glow);
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.4rem;
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid rgba(79, 124, 255, 0.2);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-ghost:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(79, 124, 255, 0.06);
        }

        .nav-mobile-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.5rem;
        }

        /* ── HERO ── */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8rem 2rem 6rem;
            overflow: hidden;
        }

        .hero-bg-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
        }

        .hero-glow-1 {
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(79, 124, 255, 0.15) 0%, transparent 70%);
            top: -100px; left: -150px;
            animation: float-glow 8s ease-in-out infinite alternate;
        }

        .hero-glow-2 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(0, 201, 224, 0.1) 0%, transparent 70%);
            bottom: -50px; right: -100px;
            animation: float-glow 10s ease-in-out infinite alternate-reverse;
        }

        .hero-glow-3 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(167, 139, 250, 0.09) 0%, transparent 70%);
            top: 40%; left: 50%;
            transform: translate(-50%, -50%);
            animation: float-glow 12s ease-in-out infinite alternate;
        }

        @keyframes float-glow {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, -20px) scale(1.1); }
        }

        .hero-glow-3 {
            animation: float-glow3 12s ease-in-out infinite alternate;
        }
        @keyframes float-glow3 {
            0% { transform: translate(-50%, -50%) scale(1); }
            100% { transform: translate(calc(-50% + 20px), calc(-50% - 20px)) scale(1.08); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            text-align: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: rgba(79, 124, 255, 0.1);
            border: 1px solid rgba(79, 124, 255, 0.3);
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 1.75rem;
            animation: fade-up 0.6s ease both;
        }

        .hero-badge .dot {
            width: 6px; height: 6px;
            background: var(--secondary);
            border-radius: 50%;
            animation: pulse-dot 2s ease infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.7); }
        }

        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(2.8rem, 7vw, 5.5rem);
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: -0.03em;
            margin-bottom: 1.5rem;
            animation: fade-up 0.6s 0.1s ease both;
        }

        .hero-title .line-solid {
            color: var(--text-primary);
        }

        .hero-title .line-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            background-size: 200% auto;
            animation: gradient-shift 4s linear infinite;
        }

        @keyframes gradient-shift {
            0% { background-position: 0% center; }
            100% { background-position: 200% center; }
        }

        .hero-desc {
            font-size: 1.125rem;
            color: var(--text-secondary);
            max-width: 680px;
            margin: 0 auto 2.5rem;
            line-height: 1.75;
            animation: fade-up 0.6s 0.2s ease both;
        }

        .hero-desc strong {
            color: var(--secondary);
            font-weight: 600;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            animation: fade-up 0.6s 0.3s ease both;
        }

        .btn-hero-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 0 30px var(--primary-glow), 0 4px 15px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-hero-primary::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-hero-primary:hover::before { left: 100%; }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 50px var(--primary-glow), 0 8px 25px rgba(0,0,0,0.5);
        }

        .btn-hero-ghost {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 2rem;
            background: rgba(255,255,255,0.7);
            color: var(--text-secondary);
            border: 1px solid rgba(79, 124, 255, 0.2);
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-hero-ghost:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(79, 124, 255, 0.07);
            transform: translateY(-2px);
        }

        /* ── HERO STATS BAR ── */
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 0;
            margin-top: 4rem;
            border: 1px solid rgba(79, 124, 255, 0.15);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            overflow: hidden;
            animation: fade-up 0.6s 0.4s ease both;
            box-shadow: 0 8px 32px rgba(79, 124, 255, 0.08);
        }

        .hero-stat-item {
            flex: 1;
            padding: 1.5rem 1rem;
            text-align: center;
            border-right: 1px solid rgba(79, 124, 255, 0.1);
            transition: background 0.2s ease;
        }

        .hero-stat-item:last-child { border-right: none; }
        .hero-stat-item:hover { background: rgba(79, 124, 255, 0.04); }

        .hero-stat-value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-stat-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 0.25rem;
            letter-spacing: 0.02em;
        }

        /* ── SECTIONS ── */
        section {
            position: relative;
            z-index: 2;
        }

        .section-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .section-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(1.75rem, 4vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.15;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.05rem;
            color: var(--text-secondary);
            line-height: 1.7;
            max-width: 620px;
        }

        /* ── METHODOLOGY SECTION ── */
        .methodology {
            padding: 7rem 0;
        }

        .methodology-header {
            margin-bottom: 4rem;
        }

        .algo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .algo-card {
            position: relative;
            background: var(--bg-card);
            border: 1px solid rgba(79, 124, 255, 0.12);
            border-radius: 16px;
            padding: 2rem;
            overflow: hidden;
            transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
            box-shadow: 0 2px 16px rgba(79, 124, 255, 0.05);
        }

        .algo-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 3px;
            background: var(--card-gradient, linear-gradient(90deg, var(--primary), var(--secondary)));
            border-radius: 16px 16px 0 0;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .algo-card:hover::before { transform: scaleX(1); }

        .algo-card:hover {
            transform: translateY(-6px);
            border-color: var(--border-bright);
            box-shadow: 0 20px 50px rgba(79, 124, 255, 0.12), 0 0 30px var(--primary-glow);
            background: var(--bg-card-hover);
        }

        .algo-card-1 { --card-gradient: linear-gradient(90deg, #4f7cff, #7c9fff); }
        .algo-card-2 { --card-gradient: linear-gradient(90deg, #00c9e0, #00e8a0); }
        .algo-card-3 { --card-gradient: linear-gradient(90deg, #a78bfa, #f472b6); }

        .algo-icon-wrap {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 1.5rem;
        }

        .algo-card-1 .algo-icon-wrap { background: rgba(79, 124, 255, 0.15); color: var(--primary); }
        .algo-card-2 .algo-icon-wrap { background: rgba(0, 201, 224, 0.15); color: var(--secondary); }
        .algo-card-3 .algo-icon-wrap { background: rgba(167, 139, 250, 0.15); color: var(--accent); }

        .algo-card h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.6rem;
            letter-spacing: -0.01em;
        }

        .algo-card p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 1.25rem;
        }

        .algo-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .algo-tag {
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .algo-card-1 .algo-tag { background: rgba(79,124,255,0.12); color: #7c9fff; border: 1px solid rgba(79,124,255,0.2); }
        .algo-card-2 .algo-tag { background: rgba(0,201,224,0.12); color: #5ee0ee; border: 1px solid rgba(0,201,224,0.2); }
        .algo-card-3 .algo-tag { background: rgba(167,139,250,0.12); color: #c4b5fd; border: 1px solid rgba(167,139,250,0.2); }

        /* ── PIPELINE SECTION ── */
        .pipeline {
            padding: 7rem 0;
            background: linear-gradient(180deg, transparent 0%, rgba(79, 124, 255, 0.03) 50%, transparent 100%);
        }

        .pipeline-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .pipeline-header .section-subtitle {
            margin: 0 auto;
        }

        .pipeline-flow {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 0;
            position: relative;
        }

        .pipeline-step {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
        }

        .pipeline-step::after {
            content: '';
            position: absolute;
            top: 28px;
            left: calc(50% + 36px);
            right: calc(-50% + 36px);
            height: 2px;
            background: linear-gradient(90deg, var(--primary-dark), var(--secondary));
            opacity: 0.3;
        }

        .pipeline-step:last-child::after { display: none; }

        .pipeline-num {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid rgba(79, 124, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1.25rem;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
            box-shadow: 0 2px 12px rgba(79, 124, 255, 0.08);
        }

        .pipeline-step:hover .pipeline-num {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 25px var(--primary-glow);
            transform: scale(1.1);
        }

        .pipeline-step-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .pipeline-step-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.6;
            padding: 0 0.5rem;
        }

        /* ── METRICS SECTION ── */
        .metrics {
            padding: 7rem 0;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .metrics-content .section-subtitle {
            margin-bottom: 2rem;
        }

        .impact-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .impact-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            background: #ffffff;
            border: 1px solid rgba(79, 124, 255, 0.1);
            border-radius: 12px;
            transition: all 0.25s ease;
            box-shadow: 0 2px 10px rgba(79, 124, 255, 0.04);
        }

        .impact-item:hover {
            border-color: var(--border-bright);
            background: #f6f9ff;
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(79, 124, 255, 0.1);
        }

        .impact-icon {
            width: 36px; height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.1rem;
        }

        .impact-icon.blue { background: rgba(79,124,255,0.12); color: var(--primary); }
        .impact-icon.teal { background: rgba(0,201,224,0.12); color: var(--secondary); }
        .impact-icon.green { background: rgba(16,185,129,0.12); color: var(--success); }

        .impact-text h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
        }

        .impact-text p {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.55;
        }

        /* ── COUNTER CARDS ── */
        .counter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .counter-card {
            padding: 2rem 1.5rem;
            border-radius: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .counter-card:hover { transform: translateY(-4px); }

        .counter-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 16px;
            padding: 1px;
            background: var(--card-border, linear-gradient(135deg, var(--primary), var(--secondary)));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }

        .counter-card-1 {
            background: linear-gradient(135deg, rgba(79,124,255,0.08) 0%, rgba(41,82,227,0.04) 100%);
            --card-border: linear-gradient(135deg, #4f7cff, #2952e3);
        }

        .counter-card-2 {
            background: linear-gradient(135deg, rgba(0,201,224,0.07) 0%, rgba(16,185,129,0.05) 100%);
            --card-border: linear-gradient(135deg, #00c9e0, #10b981);
        }

        .counter-card-3 {
            grid-column: span 2;
            background: linear-gradient(135deg, rgba(167,139,250,0.07) 0%, rgba(79,124,255,0.05) 100%);
            --card-border: linear-gradient(135deg, #a78bfa, #4f7cff);
        }

        .counter-value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 3rem;
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .counter-card-1 .counter-value { color: var(--primary); }
        .counter-card-2 .counter-value { color: var(--secondary); }
        .counter-card-3 .counter-value { color: var(--accent); }

        .counter-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* ── CTA SECTION ── */
        .cta-section {
            padding: 7rem 0;
        }

        .cta-card {
            position: relative;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
            border: 1px solid rgba(79, 124, 255, 0.15);
            border-radius: 24px;
            padding: 5rem 3rem;
            text-align: center;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(79, 124, 255, 0.1);
        }

        .cta-card::before {
            content: '';
            position: absolute;
            top: -150px; left: 50%;
            transform: translateX(-50%);
            width: 500px; height: 400px;
            background: radial-gradient(ellipse at center, rgba(79, 124, 255, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .cta-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(79,124,255,0.3), rgba(0,201,224,0.15), rgba(167,139,250,0.2));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        .cta-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(1.8rem, 4vw, 3rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .cta-subtitle {
            font-size: 1.05rem;
            color: var(--text-secondary);
            max-width: 560px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
            position: relative;
            z-index: 1;
        }

        .cta-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        /* ── FOOTER ── */
        footer {
            background: #eef2ff;
            border-top: 1px solid rgba(79, 124, 255, 0.12);
            padding: 2rem;
        }

        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .footer-logo {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-copy {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-links a {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-links a:hover { color: var(--primary); }

        /* ── SCROLL REVEAL ── */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ── ANIMATIONS ── */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(25px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── GRID LINES BG ── */
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

        /* ── TYPEWRITER CURSOR ── */
        .typewriter-cursor {
            display: inline-block;
            width: 3px;
            height: 0.85em;
            background: var(--secondary);
            margin-left: 4px;
            vertical-align: middle;
            animation: blink 0.75s step-end infinite;
            border-radius: 2px;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        /* ── FLOATING BADGE ── */
        .floating-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.85rem;
            background: rgba(5, 150, 105, 0.08);
            border: 1px solid rgba(5, 150, 105, 0.2);
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--success);
            margin-bottom: 1.75rem;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .algo-grid { grid-template-columns: 1fr; }
            .metrics-grid { grid-template-columns: 1fr; gap: 3rem; }
            .pipeline-flow { flex-direction: column; align-items: flex-start; gap: 2rem; }
            .pipeline-step::after { display: none; }
            .pipeline-step { flex-direction: row; text-align: left; gap: 1.25rem; align-items: flex-start; }
            .pipeline-num { flex-shrink: 0; }
            .hero-stats { flex-direction: column; }
            .hero-stat-item { border-right: none; border-bottom: 1px solid rgba(79, 124, 255, 0.1); }
            .hero-stat-item:last-child { border-bottom: none; }
            .nav-links, .nav-cta { display: none; }
            .nav-mobile-btn { display: flex; }
            .counter-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 640px) {
            .navbar { padding: 0 1.25rem; }
            .hero { padding: 7rem 1.25rem 5rem; }
            .section-container { padding: 0 1.25rem; }
            .hero-title { font-size: 2.4rem; }
            .counter-card-3 { grid-column: span 2; }
            .cta-card { padding: 3rem 1.5rem; }
        }
    </style>
</head>

<body>

    <!-- Background Grid -->
    <div class="grid-bg"></div>

    <!-- Particle Canvas -->
    <canvas id="particle-canvas"></canvas>

    <!-- ── NAVBAR ── -->
    <header class="navbar" id="navbar">
        <div class="nav-logo">Shiftly</div>
        <nav>
            <ul class="nav-links">
                <li><a href="#fitur">Fitur</a></li>
                <li><a href="#cara-kerja">Cara Kerja</a></li>
                <li><a href="#manfaat">Manfaat</a></li>
            </ul>
        </nav>
        <div class="nav-cta">
            @auth
                <a href="{{ url('/app') }}" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:16px;">dashboard</span>
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-ghost">Masuk</a>
            @endauth
        </div>
        <button class="nav-mobile-btn" onclick="toggleMobileMenu()" aria-label="Menu">
            <span class="material-symbols-outlined">menu</span>
        </button>
    </header>

    <!-- ── HERO ── -->
    <main>
        <section class="hero" id="hero">
            <div class="hero-bg-glow hero-glow-1"></div>
            <div class="hero-bg-glow hero-glow-2"></div>
            <div class="hero-bg-glow hero-glow-3"></div>

            <div class="hero-content">
                <h1 class="hero-title">
                    <span class="line-solid">Jadwal Staf Medis</span><br />
                    <span class="line-gradient" id="typewriter-target"></span>
                    <span class="typewriter-cursor" id="typewriter-cursor"></span>
                </h1>

                <div class="hero-actions" style="margin-top: 2.5rem;">
                    @auth
                        <a href="{{ url('/app') }}" class="btn-hero-primary">
                            Buka Dashboard
                            <span class="material-symbols-outlined" style="font-size:18px;">dashboard</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn-hero-primary">
                            Mulai Sekarang
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                        </a>
                    @endauth
                    <a href="#fitur" class="btn-hero-ghost">
                        <span class="material-symbols-outlined" style="font-size:18px;">explore</span>
                        Lihat Fitur
                    </a>
                </div>
            </div>
        </section>

        <!-- ── FEATURES SECTION ── -->
        <section class="methodology" id="fitur">
            <div class="section-container">
                <div class="methodology-header reveal" style="text-align:center; max-width: 640px; margin: 0 auto 4rem;">
                    <div class="section-label">Fitur</div>
                    <h2 class="section-title">Dua Pengguna</h2>
                    <p class="section-subtitle">
                        Shiftly dirancang untuk dua kelompok pengguna dalam satu rumah sakit.
                        Manajer yang menyusun jadwal, dan staf medis yang menjalankannya.
                    </p>
                </div>

                <div class="algo-grid" style="display: grid; grid-template-columns: repeat(2, minmax(0, 420px)); justify-content: center; gap: 2rem;">
                    <!-- Manager Card -->
                    <div class="algo-card algo-card-1 reveal">
                        <div class="algo-icon-wrap">
                            <span class="material-symbols-outlined">manage_accounts</span>
                        </div>
                        <h3>Manajer / Admin Rumah Sakit</h3>
                        <p>
                            Cukup masukkan data staf dan kebutuhan shift. AI Shiftly akan
                            secara otomatis menyusun jadwal yang optimal, adil, dan bebas konflik.
                            Tidak perlu spreadsheet, tidak perlu hitung manual.
                        </p>
                        <div class="algo-tags">
                            <span class="algo-tag">Generate Jadwal Otomatis</span>
                            <span class="algo-tag">Kelola Data Staf</span>
                            <span class="algo-tag">Pantau Distribusi Shift</span>
                        </div>
                    </div>

                    <!-- Employee Card -->
                    <div class="algo-card algo-card-2 reveal" style="transition-delay: 0.1s;">
                        <div class="algo-icon-wrap">
                            <span class="material-symbols-outlined">badge</span>
                        </div>
                        <h3>Staf Medis & Perawat</h3>
                        <p>
                            Staf dapat login ke platform yang sama untuk melihat jadwal shift
                            mereka kapan saja dan di mana saja.
                        </p>
                        <div class="algo-tags">
                            <span class="algo-tag">Lihat Jadwal Pribadi</span>
                            <span class="algo-tag">Notifikasi Shift</span>
                            <span class="algo-tag">Akses Real-time</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── HOW IT WORKS SECTION ── -->
        <section class="pipeline" id="cara-kerja">
            <div class="section-container">
                <div class="pipeline-header reveal">
                    <div class="section-label">Cara Kerja</div>
                    <h2 class="section-title">Mudah, Cepat, Otomatis</h2>
                    <p class="section-subtitle">
                        Tiga langkah sederhana dari input data hingga jadwal siap digunakan oleh seluruh staf.
                    </p>
                </div>

                <div class="pipeline-flow">
                    <div class="pipeline-step reveal">
                        <div class="pipeline-num">01</div>
                        <div>
                            <div class="pipeline-step-title">Input Data Staf</div>
                            <div class="pipeline-step-desc">Manajer memasukkan daftar staf, ketersediaan, dan kebutuhan shift harian</div>
                        </div>
                    </div>
                    <div class="pipeline-step reveal" style="transition-delay: 0.15s;">
                        <div class="pipeline-num">02</div>
                        <div>
                            <div class="pipeline-step-title">AI Menyusun Jadwal</div>
                            <div class="pipeline-step-desc">Sistem AI memproses data dan menghasilkan jadwal yang optimal secara otomatis</div>
                        </div>
                    </div>
                    <div class="pipeline-step reveal" style="transition-delay: 0.3s;">
                        <div class="pipeline-num">03</div>
                        <div>
                            <div class="pipeline-step-title">Staf Lihat Jadwal</div>
                            <div class="pipeline-step-desc">Setiap staf dapat langsung melihat jadwal shift mereka melalui akun masing-masing</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="metrics" id="manfaat">
            <div class="section-container">
                <div style="max-width: 720px; margin: 0 auto;">
                    <div class="metrics-content reveal">
                        <div class="section-label">Manfaat</div>
                        <h2 class="section-title">Lebih Adil dan Lebih Efisien</h2>
                        <p class="section-subtitle" style="margin-bottom: 2rem;">
                            Shiftly hadir untuk mengurangi beban administratif manajer
                            sekaligus memberikan kejelasan jadwal bagi seluruh staf medis.
                        </p>
                        <div class="impact-list">
                            <div class="impact-item">
                                <div class="impact-icon blue">
                                    <span class="material-symbols-outlined" style="font-size:18px;">auto_awesome</span>
                                </div>
                                <div class="impact-text">
                                    <h4>Penjadwalan Otomatis</h4>
                                    <p>Tidak perlu menyusun jadwal manual. AI mengurus semuanya dari awal hingga akhir secara otomatis.</p>
                                </div>
                            </div>
                            <div class="impact-item">
                                <div class="impact-icon teal">
                                    <span class="material-symbols-outlined" style="font-size:18px;">balance</span>
                                </div>
                                <div class="impact-text">
                                    <h4>Distribusi Shift yang Adil</h4>
                                    <p>Beban kerja terdistribusi secara merata sehingga tidak ada staf yang kelelahan karena shift berlebih.</p>
                                </div>
                            </div>
                            <div class="impact-item">
                                <div class="impact-icon green">
                                    <span class="material-symbols-outlined" style="font-size:18px;">calendar_month</span>
                                </div>
                                <div class="impact-text">
                                    <h4>Akses Jadwal Kapan Saja</h4>
                                    <p>Staf medis dapat melihat jadwal shift mereka kapan saja secara real-time melalui platform yang sama.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CTA SECTION ── -->
        <section class="cta-section">
            <div class="section-container">
                <div class="cta-card reveal">
                    <h2 class="cta-title">
                        Siap Beralih ke<br />
                        <span style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Penjadwalan yang Lebih Cerdas?</span>
                    </h2>
                    <p class="cta-subtitle">
                        Manajer bisa langsung generate jadwal. Staf bisa langsung lihat jadwal mereka.
                        Semuanya dalam satu platform, ditenagai AI.
                    </p>
                    <div class="cta-actions">
                        @auth
                            <a href="{{ url('/app') }}" class="btn-hero-primary">
                                Buka Dashboard
                                <span class="material-symbols-outlined" style="font-size:18px;">dashboard</span>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-hero-primary">
                                Mulai Sekarang
                                <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ── FOOTER ── -->
    <footer>
        <div class="footer-inner">
            <div class="footer-logo">Shiftly</div>
            <div class="footer-copy">© 2026 Shiftly. Optimasi penjadwalan medis berbasis AI.</div>
            <div class="footer-links">
                <a href="#">Kebijakan Privasi</a>
                <a href="#">Dukungan</a>
            </div>
        </div>
    </footer>

    <script>
        /* ── PARTICLE SYSTEM ── */
        (function () {
            const canvas = document.getElementById('particle-canvas');
            const ctx = canvas.getContext('2d');
            let W, H, particles = [], animId;

            const COLORS = ['rgba(79,124,255,', 'rgba(0,201,224,', 'rgba(167,139,250,'];
            const COUNT = window.innerWidth < 768 ? 40 : 80;

            function resize() {
                W = canvas.width = window.innerWidth;
                H = canvas.height = window.innerHeight;
            }

            function randBetween(a, b) { return a + Math.random() * (b - a); }

            function createParticle() {
                return {
                    x: Math.random() * W,
                    y: Math.random() * H,
                    r: randBetween(0.6, 2.2),
                    vx: randBetween(-0.15, 0.15),
                    vy: randBetween(-0.25, -0.05),
                    alpha: randBetween(0.2, 0.7),
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
                    p.alpha += randBetween(-0.003, 0.003);
                    p.alpha = Math.max(0.1, Math.min(0.8, p.alpha));

                    if (p.y < -5) { p.y = H + 5; p.x = Math.random() * W; }
                    if (p.x < -5) p.x = W + 5;
                    if (p.x > W + 5) p.x = -5;
                }
                animId = requestAnimationFrame(animate);
            }

            window.addEventListener('resize', () => { resize(); });
            init();
        })();

        /* ── NAVBAR SCROLL ── */
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 30);
        }, { passive: true });

        /* ── SCROLL REVEAL ── */
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    revealObserver.unobserve(e.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

        /* ── COUNTER ANIMATION ── */
        function animateCounter(el) {
            const target = parseFloat(el.dataset.count);
            const prefix = el.dataset.prefix || '';
            const suffix = el.dataset.suffix || '';
            const duration = 1800;
            const start = performance.now();

            function step(now) {
                const progress = Math.min((now - start) / duration, 1);
                const ease = 1 - Math.pow(1 - progress, 3);
                const val = target * ease;
                const display = Number.isInteger(target) ? Math.round(val) : val.toFixed(1);
                el.textContent = prefix + display + suffix;
                if (progress < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }

        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    animateCounter(e.target);
                    counterObserver.unobserve(e.target);
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('[data-count]').forEach(el => counterObserver.observe(el));

        /* ── TYPEWRITER EFFECT ── */
        (function () {
            const el = document.getElementById('typewriter-target');
            const cursor = document.getElementById('typewriter-cursor');
            const phrases = ['Otomatis', 'Bebas Konflik', 'Ditenagai AI', 'Lebih Efisien'];
            let phraseIndex = 0, charIndex = 0, isDeleting = false;

            function type() {
                const current = phrases[phraseIndex];
                if (isDeleting) {
                    charIndex--;
                } else {
                    charIndex++;
                }

                el.textContent = current.substring(0, charIndex);

                let delay = isDeleting ? 55 : 90;

                if (!isDeleting && charIndex === current.length) {
                    delay = 2200;
                    isDeleting = true;
                } else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    phraseIndex = (phraseIndex + 1) % phrases.length;
                    delay = 400;
                }

                setTimeout(type, delay);
            }

            setTimeout(type, 800);
        })();

        /* ── MOBILE MENU ── */
        function toggleMobileMenu() {
            // Simple scroll to top or toggle — extend as needed
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>

</html>
