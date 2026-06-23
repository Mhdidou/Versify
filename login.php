<?php
session_start();

// Verifier le cookie "se souvenir de moi" au chargement (jeton securise)
if (!isset($_SESSION['id_utilisateur']) && isset($_COOKIE['versify_remember'])) {
    try {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/_auth.php';
        $id_souvenir = auth_remember_verifier($pdo);
        if ($id_souvenir !== null) {
            session_regenerate_id(true);
            $_SESSION['id_utilisateur'] = $id_souvenir;
            header("Location: dashboard.php");
            die();
        }
    } catch (Exception $e) {}
}

$erreur = null;
if (isset($_POST['login']) && isset($_POST['pass'])) {
    try {
        require_once __DIR__ . '/config.php';
        $pdostat = $pdo->prepare("SELECT id_utilisateur, mdp FROM utilisateur WHERE (id_utilisateur=:login OR email=:login)");
        $pdostat->execute([":login" => $_POST['login']]);
        $row = $pdostat->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($_POST['pass'], $row['mdp'])) {
            // Empeche la fixation de session : on regenere l'identifiant
            session_regenerate_id(true);
            $_SESSION['id_utilisateur'] = $row['id_utilisateur'];

            // Cookie "se souvenir de moi" — jeton securise, 7 jours
            if (isset($_POST['remember_me'])) {
                require_once __DIR__ . '/_auth.php';
                auth_remember_creer($pdo, $row['id_utilisateur']);
            }

            header("Location: dashboard.php");
            die();
        } else {
            $erreur = "Identifiants incorrects";
        }
    } catch (PDOException $e) {
        // Ne pas divulguer les details techniques a l'utilisateur
        error_log('Login error: ' . $e->getMessage());
        $erreur = "Une erreur est survenue. Veuillez reessayer.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
        .float-slow { animation: float 5s ease-in-out infinite; }
        .grid-bg {
            background-image:
                linear-gradient(rgba(99,102,241,0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99,102,241,0.07) 1px, transparent 1px);
            background-size: 36px 36px;
            mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <a href="signup.php" class="text-sm font-medium text-slate-400 hover:text-white transition">Créer un compte →</a>
        </div>
    </nav>

    <section class="relative min-h-[calc(100vh-4rem)] overflow-hidden">
        <div class="absolute inset-0 grid-bg pointer-events-none"></div>
        <div class="absolute top-1/2 left-1/4 -translate-y-1/2 w-[400px] h-[400px] bg-indigo-600/15 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute top-1/2 right-1/4 -translate-y-1/2 w-[300px] h-[300px] bg-fuchsia-600/10 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="relative z-10 max-w-6xl mx-auto px-6 py-12 grid grid-cols-1 lg:grid-cols-2 gap-10 items-center min-h-[calc(100vh-4rem)]">

            <!-- LEFT: illustration & welcome -->
            <div class="hidden lg:flex flex-col justify-center">
                <div class="inline-flex self-start items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs font-bold uppercase tracking-widest mb-6">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full animate-pulse"></span>
                    Dashboard d'organisateur
                </div>
                <h1 class="text-4xl xl:text-5xl font-bold tracking-tight leading-tight mb-4">
                    Reprenez le <br>
                    <span class="bg-gradient-to-r from-indigo-400 to-fuchsia-500 bg-clip-text text-transparent">contrôle</span> de vos tournois
                </h1>
                <p class="text-slate-400 leading-relaxed mb-10 max-w-md">
                    Connectez-vous pour accéder à votre dashboard, gérer vos brackets et suivre vos participants.
                </p>

                <!-- Decorative mini-bracket -->
                <div class="relative float-slow">
                    <svg viewBox="0 0 400 280" class="w-full max-w-md">
                        <defs>
                            <linearGradient id="winline" x1="0" x2="1">
                                <stop offset="0" stop-color="#6366f1"/>
                                <stop offset="1" stop-color="#a855f7"/>
                            </linearGradient>
                        </defs>
                        <!-- Column 1 -->
                        <g>
                            <rect x="10" y="20" width="110" height="28" rx="4" fill="#1e293b" stroke="#334155"/>
                            <rect x="10" y="20" width="4" height="28" fill="#6366f1"/>
                            <text x="22" y="38" fill="#cbd5e1" font-family="Outfit" font-size="10">Team Alpha</text>
                            <text x="100" y="38" fill="#6366f1" font-family="Outfit" font-size="10" font-weight="700" text-anchor="end">3</text>

                            <rect x="10" y="52" width="110" height="28" rx="4" fill="#0f172a" stroke="#1e293b"/>
                            <rect x="10" y="52" width="4" height="28" fill="#475569"/>
                            <text x="22" y="70" fill="#64748b" font-family="Outfit" font-size="10">Team Bravo</text>
                            <text x="100" y="70" fill="#64748b" font-family="Outfit" font-size="10" font-weight="700" text-anchor="end">1</text>

                            <rect x="10" y="200" width="110" height="28" rx="4" fill="#1e293b" stroke="#334155"/>
                            <rect x="10" y="200" width="4" height="28" fill="#6366f1"/>
                            <text x="22" y="218" fill="#cbd5e1" font-family="Outfit" font-size="10">Team Charlie</text>
                            <text x="100" y="218" fill="#6366f1" font-family="Outfit" font-size="10" font-weight="700" text-anchor="end">2</text>

                            <rect x="10" y="232" width="110" height="28" rx="4" fill="#0f172a" stroke="#1e293b"/>
                            <rect x="10" y="232" width="4" height="28" fill="#475569"/>
                            <text x="22" y="250" fill="#64748b" font-family="Outfit" font-size="10">Team Delta</text>
                            <text x="100" y="250" fill="#64748b" font-family="Outfit" font-size="10" font-weight="700" text-anchor="end">0</text>
                        </g>
                        <!-- Connectors to semi-final -->
                        <path d="M 120 34 H 150 V 80 H 180" stroke="#475569" stroke-width="1.5" fill="none"/>
                        <path d="M 120 66 H 150" stroke="#475569" stroke-width="1.5" fill="none"/>
                        <path d="M 120 214 H 150 V 168 H 180" stroke="#475569" stroke-width="1.5" fill="none"/>
                        <path d="M 120 246 H 150" stroke="#475569" stroke-width="1.5" fill="none"/>
                        <!-- Column 2 -->
                        <g>
                            <rect x="180" y="66" width="110" height="28" rx="4" fill="#1e293b" stroke="#334155"/>
                            <rect x="180" y="66" width="4" height="28" fill="#6366f1"/>
                            <text x="192" y="84" fill="#cbd5e1" font-family="Outfit" font-size="10">Team Alpha</text>
                            <text x="270" y="84" fill="#6366f1" font-family="Outfit" font-size="10" font-weight="700" text-anchor="end">2</text>

                            <rect x="180" y="154" width="110" height="28" rx="4" fill="#0f172a" stroke="#1e293b"/>
                            <rect x="180" y="154" width="4" height="28" fill="#475569"/>
                            <text x="192" y="172" fill="#64748b" font-family="Outfit" font-size="10">Team Charlie</text>
                            <text x="270" y="172" fill="#64748b" font-family="Outfit" font-size="10" font-weight="700" text-anchor="end">1</text>
                        </g>
                        <!-- Connector to final -->
                        <path d="M 290 80 H 310 V 124 H 330" stroke="url(#winline)" stroke-width="2" fill="none"/>
                        <path d="M 290 168 H 310" stroke="#475569" stroke-width="1.5" fill="none"/>
                        <!-- Final -->
                        <g>
                            <rect x="330" y="110" width="60" height="28" rx="4" fill="#6366f1" stroke="#818cf8"/>
                            <text x="360" y="128" fill="#fff" font-family="Outfit" font-size="10" font-weight="700" text-anchor="middle">CHAMPION</text>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- RIGHT: form -->
            <div class="flex items-center justify-center">
                <div class="glass-card p-8 md:p-10 border border-slate-800 rounded-2xl w-full max-w-md shadow-2xl hover:border-indigo-500/40 transition-all duration-300">
                    <div class="mb-8 text-center">
                        <h2 class="text-3xl font-bold tracking-tight mb-2">Welcome Back</h2>
                        <p class="text-sm text-slate-400">Accédez à votre dashboard de tournois.</p>
                    </div>

                    <?php if ($erreur): ?>
                    <div class="mb-5 flex items-start gap-3 bg-red-500/10 border border-red-500/40 text-red-400 p-3.5 rounded text-sm">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span class="font-medium"><?= htmlspecialchars($erreur) ?></span>
                    </div>
                    <?php endif; ?>

                    <form action="login.php" method="post" class="flex flex-col gap-5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-tighter text-indigo-500 mb-2">Username or Email</label>
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <input type="text" name="login" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded pl-10 pr-4 py-3 text-sm outline-none transition" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-tighter text-indigo-500 mb-2">Password</label>
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <input id="pass" type="password" name="pass" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded pl-10 pr-10 py-3 text-sm outline-none transition" required>
                                <button type="button" id="togglePass" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-indigo-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Se souvenir de moi -->
                        <div class="flex items-center gap-2 mt-2">
                            <input type="checkbox" id="remember_me" name="remember_me" value="1" class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500 w-4 h-4">
                            <label for="remember_me" class="text-xs text-slate-400 cursor-pointer">Se souvenir de moi</label>
                        </div>

                        <button type="submit" class="mt-3 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded text-sm font-bold transition shadow-lg shadow-indigo-500/20 w-full uppercase tracking-widest">
                            Log In
                        </button>

                        <p class="text-center text-xs text-slate-500 mt-2">
                            Pas encore de compte ? <a href="signup.php" class="text-indigo-400 hover:text-indigo-300 font-bold">Créer un compte</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <script>
        const toggleBtn = document.getElementById('togglePass');
        const passInput = document.getElementById('pass');
        toggleBtn.addEventListener('click', () => {
            passInput.type = passInput.type === 'password' ? 'text' : 'password';
        });
    </script>
<?php include '_theme.php'; ?>
</body>
</html>
