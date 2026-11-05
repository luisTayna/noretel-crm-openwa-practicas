<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NoreTel CRM — Acceso al Sistema</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg: #0e1628;
            --card-bg: rgba(17, 26, 46, 0.82);
            --card-border: rgba(255, 255, 255, 0.14);
            --input-bg: rgba(11, 18, 32, 0.7);
            --input-border: rgba(255, 255, 255, 0.16);
            --text-primary: #ffffff;
            --text-secondary: #cbd5e1;
            --primary-accent: #6366f1;
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg);
            background-image: 
                radial-gradient(circle at 85% 25%, rgba(99, 102, 241, 0.22) 0%, transparent 50%),
                radial-gradient(circle at 15% 75%, rgba(168, 85, 247, 0.18) 0%, transparent 50%),
                linear-gradient(180deg, #0d1526 0%, #121b30 100%);
            min-height: 100vh;
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Dynamic HTML5 Fiber Optic Canvas Layer */
        #fiberCanvas {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            pointer-events: none;
        }

        /* SVG Antenna Tower Layer (Fixed Right Side) */
        .antenna-tower-svg {
            position: fixed;
            right: 0;
            top: 0;
            width: 400px;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            opacity: 0.85;
        }

        @keyframes beaconBlink {
            0%, 100% { opacity: 0.35; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.4); }
        }

        .beacon-light-glow {
            transform-origin: center;
            animation: beaconBlink 2s infinite ease-in-out;
        }

        /* Chrome Autofill Fix */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px #111a2e inset !important;
            -webkit-text-fill-color: #ffffff !important;
            transition: background-color 5000s ease-in-out 0s;
            border-color: rgba(99, 102, 241, 0.5) !important;
        }

        /* Container & Grid */
        .wrapper {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
        }

        .login-grid {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1fr 410px;
            gap: 5rem;
            align-items: center;
        }

        @media (max-width: 991px) {
            .login-grid {
                grid-template-columns: 1fr;
                max-width: 420px;
                gap: 2.5rem;
            }
            .brand-section {
                text-align: center;
            }
            .logo-row {
                justify-content: center !important;
            }
        }

        /* Left Side: Brand Section */
        .brand-section {
            padding-right: 1rem;
        }

        .logo-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .logo-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.28) 0%, rgba(124, 58, 237, 0.28) 100%);
            border: 1.5px solid rgba(99, 102, 241, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.55rem;
            color: #a5b4fc;
            flex-shrink: 0;
            box-shadow: 0 0 25px rgba(99, 102, 241, 0.28);
        }

        .brand-title {
            font-size: 2.35rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 0;
            color: #ffffff;
        }

        .brand-title span {
            color: #a5b4fc;
        }

        .brand-subtitle {
            color: var(--text-secondary);
            font-size: 1.025rem;
            font-weight: 500;
            margin-bottom: 3rem;
        }

        /* Clean Feature Rows */
        .features-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .feature-icon-circle {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #a5b4fc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .feature-info h4 {
            font-size: 0.975rem;
            font-weight: 600;
            color: #ffffff;
            margin: 0 0 0.2rem 0;
        }

        .feature-info p {
            font-size: 0.865rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Right Side: Login Card */
        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            padding: 2.5rem 2.15rem;
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.5), 0 0 40px rgba(99, 102, 241, 0.12);
        }

        .card-badge-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            background: rgba(99, 102, 241, 0.18);
            border: 1px solid rgba(99, 102, 241, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem auto;
            color: #a5b4fc;
            font-size: 1.3rem;
        }

        .card-h2 {
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .card-p {
            font-size: 0.885rem;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 2rem;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            font-size: 0.84rem;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 0.45rem;
            display: block;
        }

        .input-relative {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 1rem;
            color: #94a3b8;
            font-size: 1.05rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .input-field {
            width: 100%;
            height: 48px;
            background-color: var(--input-bg) !important;
            border: 1px solid var(--input-border) !important;
            border-radius: 12px !important;
            padding: 0 2.75rem 0 2.75rem !important;
            color: #ffffff !important;
            font-size: 0.925rem !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }

        .input-field:focus {
            border-color: #818cf8 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25) !important;
            background-color: rgba(17, 26, 46, 0.9) !important;
        }

        .input-relative:focus-within .input-icon-left {
            color: #a5b4fc;
        }

        .btn-eye {
            position: absolute;
            right: 0.9rem;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.05rem;
            cursor: pointer;
            padding: 0.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .btn-eye:hover {
            color: #ffffff;
        }

        /* Checkbox & Link */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.85rem;
            margin-bottom: 1.75rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.84rem;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .checkbox-label input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            background-color: var(--input-bg);
            cursor: pointer;
            display: grid;
            place-content: center;
            transition: all 0.2s ease;
        }

        .checkbox-label input[type="checkbox"]:checked {
            background-color: #6366f1;
            border-color: #6366f1;
        }

        .checkbox-label input[type="checkbox"]:checked::before {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #ffffff;
            font-size: 0.725rem;
        }

        .forgot-link {
            color: #818cf8;
            font-size: 0.84rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #c084fc;
            text-decoration: underline;
        }

        /* Primary Button */
        .btn-primary-gradient {
            width: 100%;
            height: 48px;
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 0.975rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            box-shadow: 0 8px 22px -4px rgba(99, 102, 241, 0.45);
            transition: all 0.2s ease;
        }

        .btn-primary-gradient:hover {
            opacity: 0.95;
            box-shadow: 0 12px 28px -4px rgba(124, 58, 237, 0.65);
        }

        .btn-primary-gradient:active {
            transform: scale(0.99);
        }

        /* Divider & Support */
        .divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 1.75rem 0 1.25rem 0;
        }

        .support-text {
            text-align: center;
            font-size: 0.84rem;
            color: var(--text-secondary);
        }

        .support-text a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 600;
        }

        .support-text a:hover {
            text-decoration: underline;
        }

        /* Alert Messages */
        .alert-custom {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .alert-danger-custom {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success-custom {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #86efac;
        }

        /* Footer Bar */
        .footer {
            position: relative;
            z-index: 1;
            max-width: 980px;
            margin: 0 auto;
            width: 100%;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: rgba(203, 213, 225, 0.7);
            font-size: 0.825rem;
        }

        @media (max-width: 768px) {
            .footer {
                flex-direction: column;
                gap: 0.4rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Dynamic HTML5 Fiber Optic Canvas Background -->
    <canvas id="fiberCanvas"></canvas>

    <!-- Telecom Antenna Tower (Fixed Right Side SVG) -->
    <svg class="antenna-tower-svg" viewBox="0 0 400 900" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <g transform="translate(180, 80)">
            <line x1="120" y1="30" x2="120" y2="820" stroke="#475569" stroke-width="4" />
            <line x1="120" y1="30" x2="60" y2="820" stroke="#334155" stroke-width="3" />
            <line x1="120" y1="30" x2="180" y2="820" stroke="#334155" stroke-width="3" />
            
            <line x1="100" y1="130" x2="140" y2="130" stroke="#64748b" stroke-width="2" />
            <line x1="88" y1="230" x2="152" y2="230" stroke="#64748b" stroke-width="2" />
            <line x1="78" y1="360" x2="162" y2="360" stroke="#64748b" stroke-width="2" />
            <line x1="68" y1="510" x2="172" y2="510" stroke="#64748b" stroke-width="2" />

            <ellipse cx="90" cy="260" rx="14" ry="24" fill="#334155" stroke="#64748b" stroke-width="2" />
            <ellipse cx="150" cy="380" rx="16" ry="28" fill="#334155" stroke="#64748b" stroke-width="2" />

            <circle cx="120" cy="30" r="6" fill="#ef4444" />
            <circle cx="120" cy="30" r="16" fill="#ef4444" opacity="0.4" class="beacon-light-glow" />
        </g>
    </svg>

    <main class="wrapper">
        <div class="login-grid">
            <!-- Left Branding Side -->
            <div class="brand-section">
                <div class="logo-row">
                    <div class="logo-icon">
                        <i class="fa-solid fa-wifi"></i>
                    </div>
                    <h1 class="brand-title">NoreTel <span>CRM</span></h1>
                </div>
                <p class="brand-subtitle">Sistema Integral para Proveedores ISP</p>

                <div class="features-list">
                    <div class="feature-item">
                        <div class="feature-icon-circle">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="feature-info">
                            <h4>Gestión de Clientes</h4>
                            <p>Contratos, planes y soporte especializado</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon-circle">
                            <i class="fa-solid fa-network-wired"></i>
                        </div>
                        <div class="feature-info">
                            <h4>Monitoreo de Red</h4>
                            <p>Equipos, ONT y señal en tiempo real</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon-circle">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                        <div class="feature-info">
                            <h4>Facturación y Pagos</h4>
                            <p>Cobros, facturación electrónica y reportes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Login Card -->
            <div class="login-card">
                <div class="card-badge-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h2 class="card-h2">Bienvenido de vuelta</h2>
                <p class="card-p">Inicia sesión para continuar</p>

                @if ($errors->any())
                    <div class="alert-custom alert-danger-custom">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <div>
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert-custom alert-success-custom">
                        <i class="fa-solid fa-circle-check"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('core.auth.login.post') }}" autocomplete="off">
                    @csrf

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="input-relative">
                            <i class="fa-regular fa-envelope input-icon-left"></i>
                            <input
                                type="email"
                                class="input-field @error('email') is-invalid @enderror"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="usuario3@example.test"
                                required
                                autofocus
                                autocomplete="username"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-relative">
                            <i class="fa-solid fa-lock input-icon-left"></i>
                            <input
                                type="password"
                                class="input-field @error('password') is-invalid @enderror"
                                id="password"
                                name="password"
                                placeholder="••••••••••••"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="btn-eye" id="togglePasswordBtn" aria-label="Ver u ocultar clave">
                                <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <span>Recordar mi sesión</span>
                        </label>
                        <a href="{{ route('core.auth.forgot-password') }}" class="forgot-link">¿Olvidaste tu contraseña?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-primary-gradient">
                        <span>Iniciar sesión</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

                <div class="divider"></div>

                <div class="support-text">
                    ¿Necesitas ayuda? <a href="mailto:usuario4@example.test">Contacta a soporte</a>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div>&copy; {{ date('Y') }} NoreTel CRM. Todos los derechos reservados.</div>
        <div>v2.0.1 &bull; NoreTel CRM</div>
    </footer>

    <!-- Password Toggle & Fiber Optic Canvas Engine Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            if (toggleBtn && passwordInput && toggleIcon) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    
                    if (isPassword) {
                        toggleIcon.classList.remove('fa-eye');
                        toggleIcon.classList.add('fa-eye-slash');
                        toggleIcon.style.color = '#818cf8';
                    } else {
                        toggleIcon.classList.remove('fa-eye-slash');
                        toggleIcon.classList.add('fa-eye');
                        toggleIcon.style.color = '#94a3b8';
                    }
                });
            }

            // HTML5 Canvas Dynamic Fiber Optic Packet Engine
            const canvas = document.getElementById('fiberCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            // Dynamic Bezier curves representing fiber paths across the screen
            function getCurves() {
                const w = canvas.width;
                const h = canvas.height;
                return [
                    { p0: {x: -30, y: h * 0.72}, p1: {x: w * 0.35, y: h * 0.28}, p2: {x: w * 0.88, y: h * 0.52} },
                    { p0: {x: w * 0.05, y: h * 0.88}, p1: {x: w * 0.48, y: h * 0.22}, p2: {x: w * 0.92, y: h * 0.38} },
                    { p0: {x: w * 0.18, y: h * 0.52}, p1: {x: w * 0.42, y: h * 0.14}, p2: {x: w * 0.84, y: h * 0.24} },
                    { p0: {x: -40, y: h * 0.42}, p1: {x: w * 0.32, y: h * 0.08}, p2: {x: w * 0.78, y: h * 0.18} },
                    { p0: {x: w * 0.12, y: h * 0.94}, p1: {x: w * 0.58, y: h * 0.38}, p2: {x: w * 0.95, y: h * 0.68} },
                    { p0: {x: w * 0.28, y: h * 0.65}, p1: {x: w * 0.65, y: h * 0.18}, p2: {x: w * 0.89, y: h * 0.44} }
                ];
            }

            function getBezierPoint(p0, p1, p2, t) {
                const oneMinusT = 1 - t;
                return {
                    x: oneMinusT * oneMinusT * p0.x + 2 * oneMinusT * t * p1.x + t * t * p2.x,
                    y: oneMinusT * oneMinusT * p0.y + 2 * oneMinusT * t * p1.y + t * t * p2.y
                };
            }

            class FiberPulse {
                constructor() {
                    this.reset(true);
                }

                reset(initial = false) {
                    const curves = getCurves();
                    this.curveIndex = Math.floor(Math.random() * curves.length);
                    this.t = initial ? Math.random() : 0;
                    this.speed = 0.0018 + Math.random() * 0.0045; // Random speed per packet
                    this.length = 0.05 + Math.random() * 0.09; // Random tail length
                    
                    const colors = ['#22d3ee', '#c084fc', '#60a5fa', '#34d399', '#f43f5e', '#a855f7'];
                    this.color = colors[Math.floor(Math.random() * colors.length)];
                    this.width = 2 + Math.random() * 1.8;
                }

                update() {
                    this.t += this.speed;
                    if (this.t > 1 + this.length) {
                        this.reset(false);
                    }
                }

                draw() {
                    const curves = getCurves();
                    const curve = curves[this.curveIndex];
                    if (!curve) return;

                    const headT = Math.min(1, Math.max(0, this.t));
                    const tailT = Math.min(1, Math.max(0, this.t - this.length));

                    if (headT <= tailT) return;

                    const steps = 14;
                    ctx.save();
                    ctx.lineWidth = this.width;
                    ctx.lineCap = 'round';

                    for (let i = 0; i < steps; i++) {
                        const t1 = tailT + (headT - tailT) * (i / steps);
                        const t2 = tailT + (headT - tailT) * ((i + 1) / steps);
                        const pt1 = getBezierPoint(curve.p0, curve.p1, curve.p2, t1);
                        const pt2 = getBezierPoint(curve.p0, curve.p1, curve.p2, t2);

                        const alpha = (i / steps);
                        ctx.strokeStyle = this.color;
                        ctx.globalAlpha = alpha * 0.85;

                        ctx.beginPath();
                        ctx.moveTo(pt1.x, pt1.y);
                        ctx.lineTo(pt2.x, pt2.y);
                        ctx.stroke();
                    }

                    // Bright glowing head flare
                    if (headT < 1) {
                        const headPt = getBezierPoint(curve.p0, curve.p1, curve.p2, headT);
                        ctx.shadowColor = this.color;
                        ctx.shadowBlur = 10;
                        ctx.fillStyle = '#ffffff';
                        ctx.globalAlpha = 1;
                        ctx.beginPath();
                        ctx.arc(headPt.x, headPt.y, this.width * 1.15, 0, Math.PI * 2);
                        ctx.fill();
                    }
                    ctx.restore();
                }
            }

            // Spawn 16 independent random light pulses
            const pulses = Array.from({ length: 16 }, () => new FiberPulse());

            function renderEngine() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const curves = getCurves();

                // Draw static base fiber optic cables
                ctx.lineWidth = 1;
                ctx.strokeStyle = 'rgba(129, 140, 248, 0.22)';
                curves.forEach(curve => {
                    ctx.beginPath();
                    ctx.moveTo(curve.p0.x, curve.p0.y);
                    ctx.quadraticCurveTo(curve.p1.x, curve.p1.y, curve.p2.x, curve.p2.y);
                    ctx.stroke();
                });

                // Update & Render pulses
                pulses.forEach(pulse => {
                    pulse.update();
                    pulse.draw();
                });

                requestAnimationFrame(renderEngine);
            }

            renderEngine();
        });
    </script>
</body>
</html>
