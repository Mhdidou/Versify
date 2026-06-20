<?php
session_start();
// Index page — fetches a few featured tournaments from DB to showcase activity
$tournois_featured = [];
$stats = ['tournois' => 0, 'joueurs' => 0, 'actifs' => 0];
$est_connecte = isset($_SESSION['id_utilisateur']);
$utilisateur_connecte = $_SESSION['id_utilisateur'] ?? null;

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/_helpers.php';

    // Featured tournaments (latest 6)
    $stmt = $pdo->query("SELECT id, hote, nom, jeu, format, date_depart, max_participants FROM tournoi ORDER BY date_depart DESC LIMIT 6");
    $tournois_featured = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $stats['tournois'] = (int) $pdo->query("SELECT COUNT(*) FROM tournoi")->fetchColumn();
    $stats['joueurs']  = (int) $pdo->query("SELECT COUNT(*) FROM utilisateur")->fetchColumn();
    $stats['actifs']   = (int) $pdo->query("SELECT COUNT(*) FROM tournoi WHERE date_depart >= CURDATE()")->fetchColumn();
} catch (PDOException $e) {
    // Silent — DB may be offline while developing, landing page still renders
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Versify — La Plateforme de Tournois Esports</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Outfit', sans-serif; }
    .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
    .float-slow { animation: float 4s ease-in-out infinite; }
    .grid-bg {
        background-image:
            linear-gradient(rgba(99,102,241,0.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(99,102,241,0.06) 1px, transparent 1px);
        background-size: 48px 48px;
        mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
    }
</style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col">

<!-- NAV -->
<nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="index.php" class="flex items-center gap-3">
            <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
            <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
        </a>
        <div class="hidden md:flex items-center gap-8 text-sm text-slate-400">
            <a href="#formats" class="hover:text-white transition">Formats</a>
            <a href="#tournois" class="hover:text-white transition">Tournois</a>
            <a href="#how-it-works" class="hover:text-white transition">Comment ça marche</a>
            <a href="jeux_catalogue.php" class="hover:text-white transition">Jeux</a>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($est_connecte): ?>
                <a href="explorer_tournois.php" class="text-sm font-medium text-slate-400 hover:text-white transition hidden sm:inline">Rejoindre</a>
                <a href="dashboard.php" class="text-sm font-medium text-slate-400 hover:text-white transition hidden sm:inline">Dashboard</a>
                <a href="profil.php" class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500 flex items-center justify-center font-bold text-white text-sm hover:ring-2 hover:ring-indigo-400/50 transition">
                    <?= strtoupper(substr($utilisateur_connecte, 0, 1)) ?>
                </a>
            <?php else: ?>
                <a href="signup.php" class="text-sm font-medium text-slate-400 hover:text-white transition hidden sm:inline">Sign Up</a>
                <a href="login.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2 rounded text-sm font-bold transition shadow-lg shadow-indigo-500/20">Log In</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- HERO -->
<header class="relative overflow-hidden">
    <div class="absolute inset-0 grid-bg pointer-events-none"></div>
    <div class="absolute -top-20 -left-20 w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute top-40 right-0 w-[400px] h-[400px] bg-fuchsia-600/5 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="relative max-w-7xl mx-auto px-6 pt-24 pb-20 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs font-bold uppercase tracking-widest mb-8">
            <span class="w-2 h-2 bg-indigo-400 rounded-full animate-pulse"></span>
            Nouvelle génération de tournois esports
        </div>

        <h1 class="text-4xl md:text-6xl lg:text-7xl font-bold tracking-tight leading-tight mb-6">
            The Standard for<br>
            <span class="bg-gradient-to-r from-indigo-400 via-indigo-500 to-fuchsia-500 bg-clip-text text-transparent">Competitive Integrity.</span>
        </h1>
        <p class="max-w-2xl mx-auto text-base md:text-lg text-slate-400 leading-relaxed mb-10">
            La plateforme où la skill rencontre la stratégie. Créez des brackets, gérez vos participants et suivez vos phases de jeu en temps réel. Que vous soyez joueur, organisateur ou spectateur, la compétition commence ici.
        </p>
        <div class="flex flex-wrap gap-3 justify-center mb-16">
            <?php if (!$est_connecte): ?>
            <a href="signup.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-7 py-3 rounded text-sm font-bold transition shadow-lg shadow-indigo-500/20 uppercase tracking-widest">Créer un compte</a>
            <?php endif; ?>
            <a href="explorer_tournois.php" class="bg-slate-800/50 border border-slate-700 hover:border-indigo-500 hover:text-indigo-400 text-white px-7 py-3 rounded text-sm font-bold transition uppercase tracking-widest">Rejoindre un tournoi</a>
            <a href="generateur_formulaire.php" class="bg-slate-800/50 border border-slate-700 hover:border-indigo-500 hover:text-indigo-400 text-white px-7 py-3 rounded text-sm font-bold transition uppercase tracking-widest">Tester les brackets</a>
        </div>

        <!-- STATS -->
        <div class="grid grid-cols-3 gap-4 md:gap-8 max-w-3xl mx-auto">
            <div class="glass-card border border-slate-800 rounded-xl p-5 md:p-6 hover:border-indigo-500/40 transition">
                <div class="text-2xl md:text-4xl font-bold text-indigo-400"><?= number_format($stats['tournois']) ?></div>
                <div class="text-xs md:text-sm text-slate-400 uppercase tracking-widest mt-1">Tournois créés</div>
            </div>
            <div class="glass-card border border-slate-800 rounded-xl p-5 md:p-6 hover:border-indigo-500/40 transition">
                <div class="text-2xl md:text-4xl font-bold text-emerald-400"><?= number_format($stats['joueurs']) ?></div>
                <div class="text-xs md:text-sm text-slate-400 uppercase tracking-widest mt-1">Joueurs inscrits</div>
            </div>
            <div class="glass-card border border-slate-800 rounded-xl p-5 md:p-6 hover:border-indigo-500/40 transition">
                <div class="text-2xl md:text-4xl font-bold text-amber-400"><?= number_format($stats['actifs']) ?></div>
                <div class="text-xs md:text-sm text-slate-400 uppercase tracking-widest mt-1">Actifs / à venir</div>
            </div>
        </div>
    </div>
</header>

<!-- FEATURED TOURNAMENTS -->
<?php if (!empty($tournois_featured)): ?>
<section id="tournois" class="max-w-7xl mx-auto w-full px-6 py-16">
    <div class="flex items-end justify-between border-b border-slate-800 pb-4 mb-8">
        <div>
            <h2 class="text-2xl md:text-3xl font-bold italic tracking-tight">Tournois en vedette</h2>
            <p class="text-slate-400 text-sm mt-2">Découvrez les derniers évènements créés sur la plateforme</p>
        </div>
        <a href="login.php" class="text-xs font-bold text-indigo-400 hover:text-indigo-300 uppercase tracking-widest hidden sm:flex items-center gap-1">
            Tout voir
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($tournois_featured as $t):
            $st = statut_tournoi($t['date_depart'], $t['statut_tournoi'] ?? '');
        ?>
        <div class="glass-card border border-slate-800 rounded-xl p-6 hover:border-indigo-500/50 transition group flex flex-col">
            <div class="flex items-start justify-between mb-4">
                <div class="w-10 h-10 rounded bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full border <?= $st['class'] ?>"><?= $st['label'] ?></span>
            </div>
            <h3 class="text-lg font-bold tracking-tight group-hover:text-indigo-400 transition truncate"><?= htmlspecialchars($t['nom']) ?></h3>
            <p class="text-xs text-indigo-500 font-bold uppercase tracking-tighter mb-3 truncate"><?= htmlspecialchars($t['jeu']) ?></p>
            <p class="text-xs text-slate-400 mb-5 flex-grow">Organisé par <span class="text-slate-200 font-bold"><?= htmlspecialchars($t['hote']) ?></span></p>
            <div class="flex items-center justify-between text-xs pt-4 border-t border-slate-800">
                <div class="text-slate-400 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <?= htmlspecialchars($t['date_depart']) ?>
                </div>
                <div class="text-slate-400 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <?= $t['max_participants'] ?: '∞' ?>
                </div>
                <span class="text-indigo-400 font-bold uppercase tracking-widest"><?= htmlspecialchars($t['format']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- HOW IT WORKS -->
<section id="how-it-works" class="max-w-7xl mx-auto w-full px-6 py-16">
    <div class="text-center mb-12">
        <h2 class="text-2xl md:text-3xl font-bold tracking-tight italic">Comment ça marche</h2>
        <p class="text-slate-400 text-sm mt-3">Trois étapes pour lancer votre compétition</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="glass-card border border-slate-800 rounded-xl p-8 relative overflow-hidden hover:border-indigo-500/40 transition">
            <div class="text-6xl font-bold text-indigo-500/10 absolute -top-2 -right-2">01</div>
            <div class="w-12 h-12 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center mb-5">
                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <h3 class="text-lg font-bold mb-2">Créez votre tournoi</h3>
            <p class="text-sm text-slate-400 leading-relaxed">Choisissez un jeu, un format (Single Elimination ou Double Elimination) et une date. Quelques secondes suffisent.</p>
        </div>
        <div class="glass-card border border-slate-800 rounded-xl p-8 relative overflow-hidden hover:border-indigo-500/40 transition">
            <div class="text-6xl font-bold text-indigo-500/10 absolute -top-2 -right-2">02</div>
            <div class="w-12 h-12 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center mb-5">
                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <h3 class="text-lg font-bold mb-2">Invitez les participants</h3>
            <p class="text-sm text-slate-400 leading-relaxed">Ajoutez vos équipes ou joueurs individuels. Le bracket est généré automatiquement à partir de votre liste.</p>
        </div>
        <div class="glass-card border border-slate-800 rounded-xl p-8 relative overflow-hidden hover:border-indigo-500/40 transition">
            <div class="text-6xl font-bold text-indigo-500/10 absolute -top-2 -right-2">03</div>
            <div class="w-12 h-12 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center mb-5">
                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <h3 class="text-lg font-bold mb-2">Lancez la compétition</h3>
            <p class="text-sm text-slate-400 leading-relaxed">Suivez les matchs en direct, reportez les scores et laissez le bracket avancer automatiquement vers la finale.</p>
        </div>
    </div>
</section>

<!-- FORMATS -->
<section id="formats" class="max-w-7xl mx-auto w-full px-6 py-16">
    <div class="text-center mb-10">
        <h2 class="text-2xl md:text-3xl font-bold tracking-tight italic">Formats de tournoi</h2>
        <p class="text-slate-400 text-sm mt-3">Le format parfait pour votre évènement</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="glass-card border border-slate-800 rounded-xl flex items-stretch overflow-hidden hover:border-indigo-500/50 hover:-translate-y-0.5 transition group">
            <div class="w-40 min-w-40 bg-slate-900/60 border-r border-slate-800 flex items-center justify-center p-5">
                <svg width="110" height="100" viewBox="0 0 110 100">
                    <rect x="0" y="10" width="40" height="12" rx="2" fill="#2a3a5c"/><rect x="0" y="10" width="4" height="12" rx="1" fill="#6366f1"/>
                    <rect x="0" y="28" width="40" height="12" rx="2" fill="#2a3a5c"/><rect x="0" y="28" width="4" height="12" rx="1" fill="#6366f1"/>
                    <rect x="0" y="50" width="40" height="12" rx="2" fill="#2a3a5c"/><rect x="0" y="50" width="4" height="12" rx="1" fill="#6366f1"/>
                    <rect x="0" y="68" width="40" height="12" rx="2" fill="#2a3a5c"/><rect x="0" y="68" width="4" height="12" rx="1" fill="#6366f1"/>
                    <line x1="40" y1="16" x2="52" y2="16" stroke="#4a5a7a" stroke-width="1.5"/>
                    <line x1="40" y1="34" x2="52" y2="34" stroke="#4a5a7a" stroke-width="1.5"/>
                    <line x1="52" y1="16" x2="52" y2="34" stroke="#4a5a7a" stroke-width="1.5"/>
                    <line x1="52" y1="25" x2="64" y2="25" stroke="#4a5a7a" stroke-width="1.5"/>
                    <line x1="40" y1="56" x2="52" y2="56" stroke="#4a5a7a" stroke-width="1.5"/>
                    <line x1="40" y1="74" x2="52" y2="74" stroke="#4a5a7a" stroke-width="1.5"/>
                    <line x1="52" y1="56" x2="52" y2="74" stroke="#4a5a7a" stroke-width="1.5"/>
                    <line x1="52" y1="65" x2="64" y2="65" stroke="#4a5a7a" stroke-width="1.5"/>
                    <rect x="64" y="19" width="40" height="12" rx="2" fill="#2a3a5c"/><rect x="64" y="19" width="4" height="12" rx="1" fill="#6366f1"/>
                    <rect x="64" y="59" width="40" height="12" rx="2" fill="#2a3a5c"/><rect x="64" y="59" width="4" height="12" rx="1" fill="#6366f1"/>
                    <line x1="104" y1="25" x2="110" y2="25" stroke="#6366f1" stroke-width="1.5"/>
                    <line x1="104" y1="65" x2="110" y2="65" stroke="#6366f1" stroke-width="1.5"/>
                </svg>
            </div>
            <div class="p-6 flex flex-col justify-center">
                <div class="text-base font-bold italic mb-2 group-hover:text-indigo-400 transition">Single Elimination</div>
                <div class="text-sm text-slate-400 leading-relaxed">Une seule défaite et le joueur est éliminé du tournoi.</div>
            </div>
        </div>

        <div class="glass-card border border-slate-800 rounded-xl flex items-stretch overflow-hidden hover:border-indigo-500/50 hover:-translate-y-0.5 transition group">
            <div class="w-40 min-w-40 bg-slate-900/60 border-r border-slate-800 flex items-center justify-center p-5">
                <svg width="110" height="100" viewBox="0 0 110 100">
                    <!-- WB -->
                    <rect x="0" y="8" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                    <rect x="0" y="18" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                    <rect x="0" y="32" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                    <rect x="0" y="42" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                    <path d="M28 12 H35 V22 H42" stroke="#475569" fill="none" stroke-width="1.5"/>
                    <path d="M28 22 H35" stroke="#475569" fill="none" stroke-width="1.5"/>
                    <path d="M28 36 H35 V46 H42" stroke="#475569" fill="none" stroke-width="1.5"/>
                    <path d="M28 46 H35" stroke="#475569" fill="none" stroke-width="1.5"/>
                    <rect x="42" y="17" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                    <rect x="42" y="41" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                    <path d="M70 21 H77 V45 H84" stroke="#6366f1" fill="none" stroke-width="1.5"/>
                    <path d="M70 45 H77" stroke="#475569" fill="none" stroke-width="1.5"/>
                    <rect x="84" y="29" width="26" height="8" rx="1.5" fill="#6366f1"/>
                    <!-- LB (below) -->
                    <text x="0" y="75" fill="#64748b" font-size="7" font-family="Outfit,sans-serif">LOSERS</text>
                    <rect x="0" y="78" width="20" height="7" rx="1" fill="#334155"/>
                    <rect x="22" y="78" width="20" height="7" rx="1" fill="#334155"/>
                    <path d="M42 81.5 H50" stroke="#64748b" fill="none" stroke-width="1" stroke-dasharray="2,2"/>
                    <rect x="52" y="78" width="20" height="7" rx="1" fill="#334155" opacity="0.7"/>
                    <path d="M72 81.5 H80" stroke="#64748b" fill="none" stroke-width="1" stroke-dasharray="2,2"/>
                    <rect x="82" y="78" width="18" height="7" rx="1" fill="#4f46e5" opacity="0.7"/>
                    <!-- GF label -->
                    <text x="55" y="97" fill="#6366f1" font-size="7" text-anchor="middle" font-family="Outfit,sans-serif" font-weight="bold">GRANDE FINALE</text>
                </svg>
            </div>
            <div class="p-6 flex flex-col justify-center">
                <div class="text-base font-bold italic mb-2 group-hover:text-indigo-400 transition">Double Elimination</div>
                <div class="text-sm text-slate-400 leading-relaxed">Il faut perdre deux fois pour être éliminé. Les perdants continuent dans le bracket secondaire.</div>
            </div>
        </div>
    </div>
</section>

<!-- CTA BAND -->
<section class="max-w-7xl mx-auto w-full px-6 py-16">
    <div class="glass-card border border-slate-800 rounded-2xl p-10 md:p-14 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-indigo-600/10 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="max-w-xl text-center md:text-left">
                <h2 class="text-2xl md:text-3xl font-bold tracking-tight italic mb-3">Prêt à organiser votre premier tournoi ?</h2>
                <p class="text-slate-400">Créez un compte gratuit et lancez votre évènement en quelques minutes.</p>
            </div>
            <?php if ($est_connecte): ?>
            <a href="tournoi.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-4 rounded font-bold uppercase tracking-widest shadow-lg shadow-indigo-500/20 whitespace-nowrap">Créer un tournoi →</a>
            <?php else: ?>
            <a href="signup.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-4 rounded font-bold uppercase tracking-widest shadow-lg shadow-indigo-500/20 whitespace-nowrap">Commencer →</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<footer class="border-t border-slate-900 py-10 text-center">
    <div class="flex items-center justify-center gap-3 mb-3">
        <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
        <span class="text-xs font-bold tracking-[0.3em] text-slate-500 uppercase">Versify</span>
    </div>
    <p class="text-xs text-slate-600">© <?= date('Y') ?> Versify — La plateforme de tournois esports.</p>
</footer>

<?php include '_theme.php'; ?>
</body>
</html>
