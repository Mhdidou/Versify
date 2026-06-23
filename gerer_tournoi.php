<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    die();
}
$hote_connecte = $_SESSION['id_utilisateur'];
require_once __DIR__ . '/_games.php';

try {
    require_once __DIR__ . '/config.php';

    if (isset($_POST['id_tournoi']) && !empty($_POST['nom_tournoi'])) {
        $pdostat = $pdo->prepare("UPDATE tournoi SET
            nom = :nom,
            description = :description,
            jeu = :jeu,
            format = :format,
            best_of = :best_of,
            date_depart = :date_depart,
            fuseau_horaire = :fuseau,
            max_participants = :max_p
            WHERE id = :id AND hote = :hote");

        $pdostat->execute([
            ":nom"          => $_POST['nom_tournoi'],
            ":description"  => $_POST['description'] ?? '',
            ":jeu"          => $_POST['jeu'],
            ":format"       => $_POST['format'],
            ":best_of"      => $_POST['best_of'] ?? 1,
            ":date_depart"  => $_POST['date_depart'],
            ":fuseau"       => $_POST['fuseau_horaire'] ?? 'Africa/Casablanca',
            ":max_p"        => (isset($_POST['max_participants_check']) && !empty($_POST['max_participants'])) ? $_POST['max_participants'] : null,
            ":id"           => $_POST['id_tournoi'],
            ":hote"         => $hote_connecte
        ]);

        header("Location: dashboard.php");
        die();
    }

    if (isset($_GET['id'])) {
        $pdostat = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND hote = :hote");
        $pdostat->execute([":id" => $_GET['id'], ":hote" => $hote_connecte]);
        $tournoi = $pdostat->fetch(PDO::FETCH_ASSOC);
        if (!$tournoi) { header("Location: dashboard.php"); die(); }
    } else {
        header("Location: dashboard.php"); die();
    }
} catch (PDOException $e) {
    error_log("DB error: " . $e->getMessage());
    die("Une erreur est survenue. Veuillez reessayer plus tard.");
}

// Status
$today = new DateTime('today');
$depart = new DateTime($tournoi['date_depart']);
$statut_db = $tournoi['statut_tournoi'] ?? '';

if ($statut_db === 'termine')      { $st_label = 'Terminé';   $st_class = 'bg-slate-700/60 text-slate-300 border-slate-600'; $st_dot = 'bg-slate-400'; }
elseif ($statut_db === 'en_cours') { $st_label = 'En Direct'; $st_class = 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40'; $st_dot = 'bg-emerald-400 animate-pulse'; }
elseif ($depart < $today)          { $st_label = 'Terminé';   $st_class = 'bg-slate-700/60 text-slate-300 border-slate-600'; $st_dot = 'bg-slate-400'; }
elseif ($depart == $today)         { $st_label = 'En Direct'; $st_class = 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40'; $st_dot = 'bg-emerald-400 animate-pulse'; }
else                               { $st_label = 'À venir';   $st_class = 'bg-amber-500/15 text-amber-400 border-amber-500/40'; $st_dot = 'bg-amber-400'; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen">

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

    <main class="max-w-4xl mx-auto w-full px-6 py-10 relative">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-[120px] pointer-events-none"></div>

        <a href="dashboard.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-indigo-400 uppercase tracking-widest transition mb-6">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Retour au dashboard
        </a>

        <div class="mb-8 flex flex-col sm:flex-row justify-between items-start gap-4 relative">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full border <?= $st_class ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $st_dot ?>"></span>
                        <?= $st_label ?>
                    </span>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold tracking-tight italic">Gérer le tournoi</h1>
                <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mt-2 truncate"><?= htmlspecialchars($tournoi['nom']) ?> · <?= htmlspecialchars($tournoi['jeu']) ?></p>
                <div class="w-16 h-1 bg-indigo-500 mt-3 rounded-full"></div>
            </div>
        </div>

        <form action="gerer_tournoi.php" method="post" class="glass-card border border-slate-800 rounded-xl overflow-hidden">
            <input type="hidden" name="id_tournoi" value="<?= $tournoi['id'] ?>">

            <div class="bg-slate-900/80 px-8 py-4 border-b border-slate-800 flex items-center gap-3">
                <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300 italic">Informations de base</h2>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Hôte</label></div>
                <div class="md:col-span-9">
                    <input type="text" value="<?= htmlspecialchars($hote_connecte) ?>" class="w-full bg-slate-800/80 border border-slate-700 text-slate-400 rounded px-4 py-2.5 text-sm outline-none cursor-not-allowed" readonly>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Nom <span class="text-red-500">*</span></label></div>
                <div class="md:col-span-9">
                    <input type="text" name="nom_tournoi" value="<?= htmlspecialchars($tournoi['nom']) ?>" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition" required>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Description</label></div>
                <div class="md:col-span-9">
                    <textarea name="description" rows="4" class="w-full bg-slate-950 border border-slate-700 rounded px-4 py-3 text-sm outline-none focus:border-indigo-500 transition"><?= htmlspecialchars($tournoi['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="bg-slate-900/80 px-8 py-4 border-y border-slate-800 flex items-center gap-3">
                <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300 italic">Configuration du jeu</h2>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Jeu <span class="text-red-500">*</span></label></div>
                <div class="md:col-span-9">
                    <select name="jeu" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition" required>
                        <?php foreach($liste_jeux as $j): ?>
                            <option value="<?= htmlspecialchars($j) ?>" <?= ($tournoi['jeu'] == $j) ? 'selected' : '' ?>><?= htmlspecialchars($j) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Format <span class="text-red-500">*</span></label></div>
                <div class="md:col-span-9">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php
                        $formats = [
                            'Single Elimination' => 'Single Elim',
                            'Double Elimination' => 'Double Elim',
                        ];
                        foreach ($formats as $val => $label):
                            $checked = ($tournoi['format'] == $val) ? 'checked' : '';
                        ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="format" value="<?= $val ?>" class="sr-only peer" <?= $checked ?> required>
                            <div class="border border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 rounded-lg p-4 hover:border-indigo-500/60 transition text-center">
                                <div class="text-sm font-bold text-slate-200"><?= $label ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Best of</label></div>
                <div class="md:col-span-9">
                    <div class="flex gap-3">
                        <?php foreach ([1 => 'BO1', 3 => 'BO3', 5 => 'BO5'] as $val => $label): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="best_of" value="<?= $val ?>" class="sr-only peer" <?= (($tournoi['best_of'] ?? 1) == $val) ? 'checked' : '' ?>>
                            <div class="border border-slate-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 rounded-lg px-5 py-3 hover:border-indigo-500/60 transition text-center">
                                <div class="text-sm font-bold text-slate-200"><?= $label ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Participants</label></div>
                <div class="md:col-span-9">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-300 cursor-pointer">
                        <input type="checkbox" id="max_participants_check" name="max_participants_check" value="1" class="rounded border-slate-600 bg-slate-900 text-indigo-500 w-4 h-4" <?= !empty($tournoi['max_participants']) ? 'checked' : '' ?>>
                        Specifier un nombre maximum de participants
                    </label>
                    <div id="max_participants_container" class="mt-3 hidden pl-6">
                        <input type="number" name="max_participants" min="2" value="<?= htmlspecialchars($tournoi['max_participants'] ?? '') ?>" class="w-full max-w-xs bg-slate-950 border border-slate-700 rounded px-4 py-2.5 text-sm outline-none">
                    </div>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Date de départ <span class="text-red-500">*</span></label></div>
                <div class="md:col-span-9">
                    <input type="date" name="date_depart" value="<?= htmlspecialchars($tournoi['date_depart']) ?>" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition dark:[color-scheme:dark]" required>
                </div>

                <div class="md:col-span-3"><label class="block text-sm font-bold text-slate-300">Fuseau horaire</label></div>
                <div class="md:col-span-9">
                    <?php
                    $fuseau_actuel = $tournoi['fuseau_horaire'] ?? 'Africa/Casablanca';
                    $fuseaux = [
                        'Africa/Casablanca' => 'Casablanca (Maroc)',
                        'Europe/Paris'      => 'Paris (Europe centrale)',
                        'Europe/London'     => 'Londres (GMT)',
                        'America/New_York'  => 'New York (Est US)',
                        'America/Los_Angeles' => 'Los Angeles (Pacifique US)',
                        'Asia/Dubai'        => 'Dubaï (Golfe)',
                        'Asia/Riyadh'       => 'Riyad (Arabie)',
                        'UTC'               => 'UTC',
                    ];
                    if (!isset($fuseaux[$fuseau_actuel])) $fuseaux[$fuseau_actuel] = $fuseau_actuel;
                    ?>
                    <select name="fuseau_horaire" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition" required>
                        <?php foreach ($fuseaux as $tz => $libelle): ?>
                            <option value="<?= htmlspecialchars($tz) ?>" <?= ($fuseau_actuel === $tz) ? 'selected' : '' ?>><?= htmlspecialchars($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500 mt-2">Utilisé pour planifier l'heure de chaque match dans l'onglet Overview.</p>
                </div>
            </div>

            <div class="p-8 border-t border-slate-800 bg-slate-900/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="dashboard.php?supprimer_id=<?= $tournoi['id'] ?>"
                   onclick="return confirm('Attention ! Voulez-vous vraiment supprimer ce tournoi ?');"
                   class="text-xs font-bold text-red-500 hover:text-red-400 uppercase tracking-widest flex items-center gap-1.5 transition order-2 sm:order-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Supprimer le tournoi
                </a>
                <div class="flex gap-3 order-1 sm:order-2">
                    <a href="dashboard.php" class="px-5 py-3 rounded text-sm font-bold text-slate-300 border border-slate-700 hover:border-slate-500 transition uppercase tracking-widest">Annuler</a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded text-sm font-bold transition shadow-lg shadow-indigo-500/20 uppercase tracking-widest flex items-center gap-2">
                        Enregistrer
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        const checkbox = document.getElementById('max_participants_check');
        const container = document.getElementById('max_participants_container');
        function toggle() { container.classList.toggle('hidden', !checkbox.checked); }
        checkbox.addEventListener('change', toggle);
        toggle();
    </script>
<?php include '_theme.php'; ?>
</body>
</html>
