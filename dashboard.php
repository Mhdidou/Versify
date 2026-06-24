<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    die();
}

$hote_connecte = $_SESSION['id_utilisateur'];
$tournois = [];
$error_msg = null;

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/_helpers.php';

    if (isset($_GET['supprimer_id'])) {
        $pdostat = $pdo->prepare("DELETE FROM tournoi WHERE id = :id AND hote = :hote");
        $pdostat->execute([
            ":id"   => $_GET['supprimer_id'],
            ":hote" => $hote_connecte
        ]);
        header("Location: dashboard.php");
        die();
    }

    $pdostat = $pdo->prepare("SELECT * FROM tournoi WHERE hote = :hote ORDER BY date_depart DESC");
    $pdostat->execute([":hote" => $hote_connecte]);
    $tournois = $pdostat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $erreur) {
    error_log("DB error: " . $erreur->getMessage());
    $error_msg = "Une erreur est survenue lors du chargement du dashboard.";
}

// Stats
$today = new DateTime('today');
$stats = ['total' => count($tournois), 'actifs' => 0, 'termines' => 0, 'prochain' => null];
foreach ($tournois as $t) {
    $d = new DateTime($t['date_depart']);
    if ($d >= $today) {
        $stats['actifs']++;
        if (!$stats['prochain'] || $d < new DateTime($stats['prochain']['date_depart'])) {
            $stats['prochain'] = $t;
        }
    } else {
        $stats['termines']++;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
        .sidebar-link.active { background: rgba(99,102,241,0.15); color: #a5b4fc; border-left-color: #6366f1; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen">

    <!-- TOP NAV -->
    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-[1600px] mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle" class="lg:hidden text-slate-400 hover:text-white mr-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="index.php" class="flex items-center gap-3">
                    <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                    <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <!-- Avatar + user -->
                <a href="profil.php" class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500 flex items-center justify-center font-bold text-white text-sm hover:ring-2 hover:ring-indigo-400/50 transition">
                        <?= strtoupper(substr($hote_connecte, 0, 1)) ?>
                    </div>
                    <span class="text-sm font-medium text-slate-300 hidden sm:inline"><?= htmlspecialchars($hote_connecte) ?></span>
                </a>
                <a href="logout.php" class="text-sm font-bold text-red-500 hover:text-red-400 transition">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="max-w-[1600px] mx-auto flex">

        <!-- SIDEBAR -->
        <aside id="sidebar" class="hidden lg:block w-60 min-h-[calc(100vh-4rem)] border-r border-slate-800 py-8 sticky top-16 self-start">
            <nav class="flex flex-col gap-1">
                <a href="dashboard.php" class="sidebar-link active flex items-center gap-3 px-6 py-3 text-sm font-bold text-slate-300 hover:bg-slate-800/50 border-l-2 border-transparent transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>
                <a href="tournoi.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 border-l-2 border-transparent transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Créer un tournoi
                </a>
                <a href="explorer_tournois.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 border-l-2 border-transparent transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rejoindre un tournoi
                </a>
                <a href="generateur_formulaire.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 border-l-2 border-transparent transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2M9 17H7a2 2 0 01-2-2V9a2 2 0 012-2h2m0 10V7m0 0h6m-6 0V5a2 2 0 012-2h2a2 2 0 012 2v2m0 0v10m0 0h2a2 2 0 002-2V9a2 2 0 00-2-2h-2"/></svg>
                    Aperçu Brackets
                </a>
               
                
                <div class="px-6 mt-6 mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-600">Compte</div>
                <a href="logout.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-bold text-slate-400 hover:text-red-400 hover:bg-slate-800/50 border-l-2 border-transparent transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Déconnexion
                </a>
            </nav>
        </aside>

        <main class="flex-grow w-full px-6 py-10 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-[120px] pointer-events-none"></div>

            <?php if ($error_msg): ?>
                <div class="bg-red-500/10 border border-red-500/40 text-red-400 p-4 rounded mb-6 text-sm"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <!-- HEADER -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-800 pb-6 mb-8 gap-4 relative">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold tracking-tight italic">Vos Tournois</h1>
                    <p class="text-slate-400 text-sm mt-2">Bon retour, <span class="text-indigo-400 font-bold"><?= htmlspecialchars($hote_connecte) ?></span>. Voici votre vue d'ensemble.</p>
                    <div class="w-16 h-1 bg-indigo-500 mt-3 rounded-full"></div>
                </div>
                <a href="tournoi.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded text-sm font-bold transition shadow-lg shadow-indigo-500/20 flex items-center gap-2 whitespace-nowrap uppercase tracking-widest">
                    Créer un tournoi
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </a>
            </div>

            <!-- STATS BAR -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 relative">
                <div class="glass-card border border-slate-800 rounded-xl p-5 hover:border-indigo-500/40 transition">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total</span>
                    </div>
                    <div class="text-3xl font-bold"><?= $stats['total'] ?></div>
                    <div class="text-xs text-slate-400 mt-1">Tournois organisés</div>
                </div>
                <div class="glass-card border border-slate-800 rounded-xl p-5 hover:border-emerald-500/40 transition">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Actifs</span>
                    </div>
                    <div class="text-3xl font-bold text-emerald-400"><?= $stats['actifs'] ?></div>
                    <div class="text-xs text-slate-400 mt-1">En cours / à venir</div>
                </div>
                <div class="glass-card border border-slate-800 rounded-xl p-5 hover:border-slate-600 transition">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded bg-slate-700/40 border border-slate-600 flex items-center justify-center">
                            <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Terminés</span>
                    </div>
                    <div class="text-3xl font-bold text-slate-300"><?= $stats['termines'] ?></div>
                    <div class="text-xs text-slate-400 mt-1">Historique</div>
                </div>
                <div class="glass-card border border-slate-800 rounded-xl p-5 hover:border-amber-500/40 transition">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded bg-amber-500/10 border border-amber-500/30 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Prochain</span>
                    </div>
                    <?php if ($stats['prochain']): ?>
                        <div class="text-base font-bold text-amber-400 truncate"><?= htmlspecialchars($stats['prochain']['nom']) ?></div>
                        <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($stats['prochain']['date_depart']) ?></div>
                    <?php else: ?>
                        <div class="text-base font-bold text-slate-500">—</div>
                        <div class="text-xs text-slate-500 mt-1">Aucun évènement</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($tournois)): ?>
                <!-- EMPTY STATE -->
                <div class="glass-card border border-slate-800 rounded-2xl p-8 md:p-16 flex flex-col lg:flex-row items-center justify-between gap-12 relative overflow-hidden">
                    <div class="absolute inset-0 pointer-events-none opacity-20"
                         style="background-image: linear-gradient(rgba(99,102,241,0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.15) 1px, transparent 1px); background-size: 40px 40px;"></div>
                    <div class="relative z-10 max-w-lg">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-[10px] font-bold uppercase tracking-widest mb-4">
                            <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full"></span>
                            Premier pas
                        </div>
                        <h2 class="text-3xl font-bold mb-4 italic">Créez votre premier tournoi</h2>
                        <p class="text-slate-400 text-base mb-8 leading-relaxed">
                            Vous n'avez pas encore de tournoi actif. Choisissez un jeu, un format et lancez votre première compétition en quelques clics.
                        </p>
                        <a href="tournoi.php" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3.5 rounded text-sm font-bold transition shadow-lg shadow-indigo-500/20 uppercase tracking-widest">
                            Commencer →
                        </a>
                    </div>
                    <!-- Decorative bracket SVG -->
                    <div class="relative z-10 w-full max-w-sm hidden md:block">
                        <svg viewBox="0 0 320 240" class="w-full">
                            <rect x="0" y="20" width="90" height="24" rx="3" fill="#1e293b" stroke="#334155"/>
                            <rect x="0" y="20" width="3" height="24" fill="#6366f1"/>
                            <rect x="0" y="52" width="90" height="24" rx="3" fill="#0f172a" stroke="#1e293b"/>
                            <rect x="0" y="52" width="3" height="24" fill="#475569"/>
                            <rect x="0" y="164" width="90" height="24" rx="3" fill="#1e293b" stroke="#334155"/>
                            <rect x="0" y="164" width="3" height="24" fill="#6366f1"/>
                            <rect x="0" y="196" width="90" height="24" rx="3" fill="#0f172a" stroke="#1e293b"/>
                            <rect x="0" y="196" width="3" height="24" fill="#475569"/>
                            <path d="M 90 32 H 120 V 64 H 150" stroke="#475569" fill="none" stroke-width="1.5"/>
                            <path d="M 90 64 H 120" stroke="#475569" fill="none" stroke-width="1.5"/>
                            <path d="M 90 176 H 120 V 144 H 150" stroke="#475569" fill="none" stroke-width="1.5"/>
                            <path d="M 90 208 H 120" stroke="#475569" fill="none" stroke-width="1.5"/>
                            <rect x="150" y="52" width="90" height="24" rx="3" fill="#1e293b" stroke="#334155"/>
                            <rect x="150" y="52" width="3" height="24" fill="#6366f1"/>
                            <rect x="150" y="132" width="90" height="24" rx="3" fill="#0f172a" stroke="#1e293b"/>
                            <rect x="150" y="132" width="3" height="24" fill="#475569"/>
                            <path d="M 240 64 H 265 V 104 H 285" stroke="#6366f1" fill="none" stroke-width="2"/>
                            <path d="M 240 144 H 265" stroke="#475569" fill="none" stroke-width="1.5"/>
                            <rect x="285" y="92" width="35" height="24" rx="3" fill="#6366f1" stroke="#818cf8"/>
                            <text x="302" y="108" fill="#fff" font-family="Outfit" font-size="8" font-weight="700" text-anchor="middle">WIN</text>
                        </svg>
                    </div>
                </div>

            <?php else: ?>
                <!-- FILTER BAR -->
                <div class="glass-card border border-slate-800 rounded-xl p-4 mb-6 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <div class="relative flex-grow">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input id="search-input" type="text" placeholder="Rechercher un tournoi ou un jeu…" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded pl-10 pr-4 py-2.5 text-sm outline-none transition">
                    </div>
                    <select id="format-filter" class="bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition">
                        <option value="">Tous les formats</option>
                        <option value="Single Elimination">Single Elimination</option>
                        <option value="Double Elimination">Double Elimination</option>
                    </select>
                    <select id="status-filter" class="bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition">
                        <option value="">Tous les statuts</option>
                        <option value="À venir">À venir</option>
                        <option value="En Direct">En Direct</option>
                        <option value="Terminé">Terminé</option>
                    </select>
                </div>

                <!-- TOURNAMENT GRID -->
                <div id="tournament-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($tournois as $t):
                        $st = statut_tournoi($t['date_depart'], $t['statut_tournoi'] ?? '');
                    ?>
                        <div class="tournament-card glass-card border border-slate-800 rounded-xl p-6 hover:border-indigo-500/50 transition-all duration-300 group flex flex-col h-full cursor-pointer"
                             data-name="<?= strtolower(htmlspecialchars($t['nom'])) ?>"
                             data-game="<?= strtolower(htmlspecialchars($t['jeu'])) ?>"
                             data-format="<?= htmlspecialchars($t['format']) ?>"
                             data-status="<?= $st['label'] ?>"
                             onclick="window.location.href='participants.php?id_tournoi=<?= $t['id'] ?>'"
                             role="link" tabindex="0">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-10 h-10 border border-indigo-500/30 flex items-center justify-center rounded bg-indigo-500/10 group-hover:bg-indigo-500/20 transition">
                                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full border <?= $st['class'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $st['dot'] ?>"></span>
                                    <?= $st['label'] ?>
                                </span>
                            </div>

                            <h3 class="text-xl font-bold tracking-tight mb-1 group-hover:text-indigo-400 transition-colors truncate"><?= htmlspecialchars($t['nom']) ?></h3>
                            <p class="text-sm text-indigo-500 font-bold mb-2 uppercase tracking-tighter truncate"><?= htmlspecialchars($t['jeu']) ?></p>
                            <p class="text-xs text-slate-400 mb-5 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Début : <?= htmlspecialchars($t['date_depart']) ?>
                            </p>

                            <div class="inline-block self-start text-[10px] font-bold uppercase tracking-widest px-2 py-1 bg-slate-800/80 rounded text-slate-300 mb-5 flex-grow-0">
                                <?= htmlspecialchars($t['format']) ?>
                            </div>

                            <div class="flex items-center justify-between pt-4 border-t border-slate-800 mt-auto">
                                <div class="text-xs text-slate-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                    <span class="font-bold text-slate-200"><?= $t['max_participants'] ?: '∞' ?></span> max
                                </div>
                                <div class="flex items-center gap-3">
                                    <a href="gerer_tournoi.php?id=<?= $t['id'] ?>" onclick="event.stopPropagation();" class="text-xs font-bold text-indigo-500 hover:text-indigo-400 uppercase tracking-widest flex items-center gap-1">
                                        Gérer
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                    <a href="dashboard.php?supprimer_id=<?= $t['id'] ?>"
                                       onclick="event.stopPropagation(); return confirm('Attention ! Voulez-vous vraiment supprimer ce tournoi ?');"
                                       class="text-slate-500 hover:text-red-400 transition" title="Supprimer">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="empty-filter" class="hidden text-center py-16 text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <p class="text-sm">Aucun tournoi ne correspond à votre recherche.</p>
                </div>
            <?php endif; ?>

            <!-- MES PARTICIPATIONS (tournois rejoints en tant que joueur) -->
            <?php
            $tournois_rejoints = [];
            try {
                $stmtRejoints = $pdo->prepare("SELECT t.*, p.seed, p.checked_in FROM tournoi t INNER JOIN participant p ON p.id_tournoi = t.id WHERE p.nom_participant = :u AND p.statut = 'actif' AND t.hote != :u ORDER BY t.date_depart DESC");
                $stmtRejoints->execute([":u" => $hote_connecte]);
                $tournois_rejoints = $stmtRejoints->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            ?>
            <?php if (!empty($tournois_rejoints)): ?>
            <div class="mt-12 border-t border-slate-800 pt-8">
                <div class="flex items-center gap-2 mb-6">
                    <div class="w-1 h-5 bg-emerald-500 rounded"></div>
                    <h2 class="text-xl font-bold italic">Mes participations</h2>
                    <span class="text-xs font-bold text-slate-500 ml-2"><?= count($tournois_rejoints) ?> tournoi(s)</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($tournois_rejoints as $tr): ?>
                    <div class="glass-card border border-slate-800 rounded-xl p-5 hover:border-emerald-500/50 transition">
                        <div class="flex items-start justify-between mb-3">
                            <div class="w-9 h-9 rounded bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <?php if (!empty($tr['checked_in']) && $tr['checked_in']): ?>
                                <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/40">Confirme</span>
                            <?php else: ?>
                                <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-400 border border-amber-500/40">Non confirme</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-base font-bold truncate"><?= htmlspecialchars($tr['nom']) ?></h3>
                        <p class="text-xs text-indigo-500 font-bold uppercase tracking-tighter truncate"><?= htmlspecialchars($tr['jeu']) ?></p>
                        <p class="text-xs text-slate-400 mt-1">Par <?= htmlspecialchars($tr['hote']) ?> &middot; Seed #<?= $tr['seed'] ?></p>
                        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-slate-800">
                            <a href="check_in.php?id_tournoi=<?= $tr['id'] ?>" class="text-[10px] font-bold text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 px-2.5 py-1.5 rounded transition">Check-in</a>
                            <a href="bracket_live.php?id_tournoi=<?= $tr['id'] ?>" class="text-[10px] font-bold text-indigo-400 hover:text-indigo-300 bg-indigo-500/10 border border-indigo-500/30 px-2.5 py-1.5 rounded transition">Bracket</a>
                            <a href="reclamation.php?id_tournoi=<?= $tr['id'] ?>" class="text-[10px] font-bold text-slate-400 hover:text-slate-300 bg-slate-800 border border-slate-700 px-2.5 py-1.5 rounded transition">Reclamation</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <footer class="py-8 border-t border-slate-900 text-center">
        <p class="text-xs font-bold tracking-[0.3em] text-slate-600 uppercase">Versify</p>
    </footer>

    <script>
        // Sidebar toggle (mobile)
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebar-toggle');
        if (toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('hidden'));

        // Client-side search + filter
        const search = document.getElementById('search-input');
        const formatFilter = document.getElementById('format-filter');
        const statusFilter = document.getElementById('status-filter');
        const cards = document.querySelectorAll('.tournament-card');
        const emptyFilter = document.getElementById('empty-filter');

        function applyFilters() {
            const q = (search?.value || '').toLowerCase().trim();
            const f = formatFilter?.value || '';
            const s = statusFilter?.value || '';
            let visible = 0;
            cards.forEach(c => {
                const matchQ = !q || c.dataset.name.includes(q) || c.dataset.game.includes(q);
                const matchF = !f || c.dataset.format === f;
                const matchS = !s || c.dataset.status === s;
                const show = matchQ && matchF && matchS;
                c.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (emptyFilter) emptyFilter.classList.toggle('hidden', visible > 0);
        }

        [search, formatFilter, statusFilter].forEach(el => el?.addEventListener('input', applyFilters));
    </script>

<?php include '_theme.php'; ?>
</body>
</html>
