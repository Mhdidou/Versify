<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    die();
}
$hote_connecte = $_SESSION['id_utilisateur'];
require_once __DIR__ . '/_games.php';

$message = '';
if (isset($_POST['nom_tournoi'], $_POST['jeu'], $_POST['format'], $_POST['date_depart'])) {
    if (!empty($_POST['nom_tournoi']) && !empty($_POST['jeu']) && !empty($_POST['format']) && !empty($_POST['date_depart'])) {

        $max_participants = (isset($_POST['max_participants_check']) && !empty($_POST['max_participants']))
            ? $_POST['max_participants'] : null;

        try {
            require_once __DIR__ . '/config.php';
            require_once __DIR__ . '/_helpers.php';

            $pdostat = $pdo->prepare("INSERT INTO tournoi (hote, nom, description, jeu, format, best_of, date_depart, max_participants) VALUES (:hote, :nom, :description, :jeu, :format, :best_of, :date_depart, :max_participants)");
            $pdostat->execute([
                ":hote"             => $hote_connecte,
                ":nom"              => $_POST['nom_tournoi'],
                ":description"      => $_POST['description'] ?? '',
                ":jeu"              => $_POST['jeu'],
                ":format"           => $_POST['format'],
                ":best_of"          => $_POST['best_of'] ?? 1,
                ":date_depart"      => $_POST['date_depart'],
                ":max_participants" => $max_participants
            ]);

            header("Location: dashboard.php");
            die();
        } catch (PDOException $e) {
            $message = "<div class='mb-5 flex items-start gap-3 bg-red-500/10 border border-red-500/40 text-red-400 p-3.5 rounded text-sm'>
                <svg class='w-5 h-5 flex-shrink-0 mt-0.5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg>
                <span class='font-medium'>Une erreur est survenue lors de la création du tournoi.</span></div>";
            error_log("DB error: " . $e->getMessage());
        }
    } else {
        $message = "<div class='mb-5 bg-red-500/10 border border-red-500/40 text-red-400 p-3.5 rounded text-sm font-medium'>Veuillez remplir tous les champs obligatoires (*).</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau Tournoi | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <div class="flex items-center gap-6">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500 flex items-center justify-center font-bold text-white text-sm">
                    <?= strtoupper(substr($hote_connecte, 0, 1)) ?>
                </div>
                <span class="text-sm font-medium text-slate-400 hidden sm:inline"><?= htmlspecialchars($hote_connecte) ?></span>
                <a href="logout.php" class="text-sm font-bold text-red-500 hover:text-red-400 transition">Déconnexion</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-4xl mx-auto w-full px-6 py-10 relative">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-[120px] pointer-events-none"></div>

        <!-- Back link -->
        <a href="dashboard.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-indigo-400 uppercase tracking-widest transition mb-6">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Retour au dashboard
        </a>

        <div class="mb-8 relative">
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight italic">Nouveau tournoi</h1>
            <p class="text-slate-400 text-sm mt-2">Configurez votre évènement en remplissant les informations ci-dessous.</p>
            <div class="w-16 h-1 bg-indigo-500 mt-3 rounded-full"></div>
        </div>

        <!-- Step indicator -->
        <div class="flex items-center gap-3 mb-8 relative">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">1</div>
                <span class="text-xs font-bold uppercase tracking-widest text-indigo-400">Infos de base</span>
            </div>
            <div class="flex-1 h-0.5 bg-gradient-to-r from-indigo-500 to-slate-800"></div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">2</div>
                <span class="text-xs font-bold uppercase tracking-widest text-indigo-400 hidden sm:inline">Configuration jeu</span>
            </div>
            <div class="flex-1 h-0.5 bg-slate-800"></div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 text-slate-500 text-xs font-bold flex items-center justify-center">3</div>
                <span class="text-xs font-bold uppercase tracking-widest text-slate-500 hidden sm:inline">Gestion</span>
            </div>
        </div>

        <?= $message ?>

        <form action="tournoi.php" method="post" class="glass-card border border-slate-800 rounded-xl overflow-hidden">

            <!-- SECTION: Base info -->
            <div class="bg-slate-900/80 px-8 py-4 border-b border-slate-800 flex items-center gap-3">
                <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300 italic">Informations de base</h2>
            </div>
            <div class="p-8 grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Hôte</label></div>
                <div class="md:col-span-9">
                    <input type="text" name="hote" value="<?= htmlspecialchars($hote_connecte) ?>" class="w-full bg-slate-800/80 border border-slate-700 text-slate-400 rounded px-4 py-2.5 text-sm outline-none cursor-not-allowed" readonly>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Nom du tournoi <span class="text-red-500">*</span></label></div>
                <div class="md:col-span-9">
                    <input type="text" name="nom_tournoi" placeholder="Ex: Championnat d'Hiver 2026" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition" required>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Description</label></div>
                <div class="md:col-span-9">
                    <textarea name="description" rows="4" placeholder="Règles, prix, format détaillé…" class="w-full bg-slate-950 border border-slate-700 rounded px-4 py-3 text-sm outline-none focus:border-indigo-500 transition"></textarea>
                </div>
            </div>

            <!-- SECTION: Game config -->
            <div class="bg-slate-900/80 px-8 py-4 border-y border-slate-800 flex items-center gap-3">
                <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300 italic">Configuration du jeu</h2>
            </div>
            <div class="p-8 grid grid-cols-1 md:grid-cols-12 gap-6 items-start">

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Jeu <span class="text-red-500">*</span></label></div>
                <div class="md:col-span-9">
                    <select name="jeu" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition" required>
                        <option value="" disabled selected>Sélectionnez un jeu</option>
                        <?php foreach ($liste_jeux as $j): ?>
                            <option value="<?= htmlspecialchars($j) ?>"><?= htmlspecialchars($j) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Format <span class="text-red-500">*</span></label></div>
                <div class="md:col-span-9">
                    <!-- Format cards selector -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="format-option cursor-pointer">
                            <input type="radio" name="format" value="Single Elimination" class="sr-only peer" required checked>
                            <div class="border border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 rounded-lg p-4 hover:border-indigo-500/60 transition">
                                <svg class="w-full h-16 mb-2" viewBox="0 0 110 60">
                                    <rect x="0" y="5" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                                    <rect x="0" y="17" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                                    <rect x="0" y="35" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                                    <rect x="0" y="47" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                                    <path d="M28 9 H36 V21 H44" stroke="#475569" fill="none"/>
                                    <path d="M28 21 H36" stroke="#475569" fill="none"/>
                                    <path d="M28 39 H36 V51 H44" stroke="#475569" fill="none"/>
                                    <path d="M28 51 H36" stroke="#475569" fill="none"/>
                                    <rect x="44" y="15" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                                    <rect x="44" y="45" width="28" height="8" rx="1.5" fill="#2a3a5c"/>
                                    <path d="M72 19 H80 V49 H88" stroke="#6366f1" fill="none"/>
                                    <path d="M72 49 H80" stroke="#475569" fill="none"/>
                                    <rect x="88" y="29" width="22" height="8" rx="1.5" fill="#6366f1"/>
                                </svg>
                                <div class="text-xs font-bold text-center text-slate-200">Single Elim</div>
                            </div>
                        </label>
                        <label class="format-option cursor-pointer">
                            <input type="radio" name="format" value="Double Elimination" class="sr-only peer">
                            <div class="border border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 rounded-lg p-4 hover:border-indigo-500/60 transition">
                                <svg class="w-full h-16 mb-2" viewBox="0 0 110 60">
                                    <!-- WB top row -->
                                    <rect x="0" y="4" width="20" height="7" rx="1.5" fill="#2a3a5c"/>
                                    <rect x="0" y="13" width="20" height="7" rx="1.5" fill="#2a3a5c"/>
                                    <rect x="0" y="24" width="20" height="7" rx="1.5" fill="#2a3a5c"/>
                                    <rect x="0" y="33" width="20" height="7" rx="1.5" fill="#2a3a5c"/>
                                    <path d="M20 7.5 H26 V17 H32" stroke="#475569" fill="none" stroke-width="1"/>
                                    <path d="M20 16.5 H26" stroke="#475569" fill="none" stroke-width="1"/>
                                    <path d="M20 27.5 H26 V37 H32" stroke="#475569" fill="none" stroke-width="1"/>
                                    <path d="M20 36.5 H26" stroke="#475569" fill="none" stroke-width="1"/>
                                    <rect x="32" y="13" width="20" height="7" rx="1.5" fill="#2a3a5c"/>
                                    <rect x="32" y="33" width="20" height="7" rx="1.5" fill="#2a3a5c"/>
                                    <path d="M52 16.5 H58 V36.5 H64" stroke="#6366f1" fill="none" stroke-width="1"/>
                                    <path d="M52 36.5 H58" stroke="#475569" fill="none" stroke-width="1"/>
                                    <rect x="64" y="23" width="18" height="7" rx="1.5" fill="#6366f1"/>
                                    <!-- LB bottom row (losers bracket) -->
                                    <rect x="0" y="47" width="20" height="7" rx="1.5" fill="#334155"/>
                                    <rect x="0" y="55" width="20" height="0" rx="1.5" fill="#334155"/>
                                    <rect x="28" y="50" width="20" height="7" rx="1.5" fill="#334155" opacity="0.7"/>
                                    <path d="M20 50.5 H28" stroke="#94a3b8" fill="none" stroke-width="1" stroke-dasharray="2,2"/>
                                    <path d="M48 53.5 H56 H56" stroke="#94a3b8" fill="none" stroke-width="1" stroke-dasharray="2,2"/>
                                    <rect x="56" y="50" width="14" height="7" rx="1.5" fill="#4f46e5" opacity="0.6"/>
                                </svg>
                                <div class="text-xs font-bold text-center text-slate-200">Double Elim</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Best of</label></div>
                <div class="md:col-span-9">
                    <div class="flex gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="best_of" value="1" class="sr-only peer" checked>
                            <div class="border border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 rounded-lg px-5 py-3 hover:border-indigo-500/60 transition text-center">
                                <div class="text-sm font-bold text-slate-200">BO1</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="best_of" value="3" class="sr-only peer">
                            <div class="border border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 rounded-lg px-5 py-3 hover:border-indigo-500/60 transition text-center">
                                <div class="text-sm font-bold text-slate-200">BO3</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="best_of" value="5" class="sr-only peer">
                            <div class="border border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 rounded-lg px-5 py-3 hover:border-indigo-500/60 transition text-center">
                                <div class="text-sm font-bold text-slate-200">BO5</div>
                            </div>
                        </label>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Nombre de manches necessaires pour gagner un match.</p>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Participants</label></div>
                <div class="md:col-span-9">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-300 cursor-pointer">
                        <input type="checkbox" id="max_participants_check" name="max_participants_check" value="1" class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500 w-4 h-4">
                        Spécifier un nombre maximum de participants
                    </label>
                    <div id="max_participants_container" class="mt-3 hidden pl-6">
                        <input type="number" name="max_participants" min="2" placeholder="Ex: 16" class="w-full max-w-xs bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition">
                        <p class="text-xs text-slate-500 mt-2">Laissez vide pour un nombre illimité.</p>
                    </div>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Date de départ <span class="text-red-500">*</span></label></div>
                <div class="md:col-span-9">
                    <input type="date" name="date_depart" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition dark:[color-scheme:dark]" required>
                </div>
            </div>

            <div class="p-8 border-t border-slate-800 bg-slate-900/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-xs text-slate-500 order-2 sm:order-1">Vous pourrez modifier ces informations depuis votre dashboard.</p>
                <div class="flex gap-3 order-1 sm:order-2">
                    <a href="dashboard.php" class="px-5 py-3 rounded text-sm font-bold text-slate-300 border border-slate-700 hover:border-slate-500 transition uppercase tracking-widest">Annuler</a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded text-sm font-bold transition shadow-lg shadow-indigo-500/20 uppercase tracking-widest flex items-center gap-2">
                        Enregistrer
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('max_participants_check');
            const container = document.getElementById('max_participants_container');
            const inputField = container.querySelector('input');
            checkbox.addEventListener('change', function() {
                container.classList.toggle('hidden', !this.checked);
                inputField.required = this.checked;
                if (!this.checked) inputField.value = '';
            });
        });
    </script>
<?php include '_theme.php'; ?>
</body>
</html>
