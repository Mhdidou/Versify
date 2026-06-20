<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    die();
}

$utilisateur_connecte = $_SESSION['id_utilisateur'];
$user_cible = $_GET['user'] ?? $utilisateur_connecte;
$est_mon_profil = ($user_cible === $utilisateur_connecte);
$message = '';
$message_type = '';

try {
    require_once __DIR__ . '/config.php';

    // Upload de photo
    if ($est_mon_profil && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $extensions_ok = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $extensions_ok)) {
                $message = "Format non supporte. Utilisez JPG, PNG, GIF ou WEBP.";
                $message_type = 'error';
            } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                $message = "Fichier trop volumineux (max 2 Mo).";
                $message_type = 'error';
            } else {
                // Creer le dossier uploads s'il n'existe pas
                $dossier = __DIR__ . '/uploads/avatars/';
                if (!is_dir($dossier)) {
                    mkdir($dossier, 0755, true);
                }

                $nom_fichier = $utilisateur_connecte . '_' . time() . '.' . $ext;
                $chemin = $dossier . $nom_fichier;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $chemin)) {
                    // Supprimer l'ancienne photo si elle existe
                    try {
                        $stmtOld = $pdo->prepare("SELECT photo_profil FROM utilisateur WHERE id_utilisateur = :u");
                        $stmtOld->execute([":u" => $utilisateur_connecte]);
                        $ancienne = $stmtOld->fetchColumn();
                        if ($ancienne && file_exists(__DIR__ . '/' . $ancienne)) {
                            unlink(__DIR__ . '/' . $ancienne);
                        }
                    } catch (Exception $e) {}

                    $chemin_relatif = 'uploads/avatars/' . $nom_fichier;
                    try {
                        $pdo->prepare("UPDATE utilisateur SET photo_profil = :photo WHERE id_utilisateur = :u")
                            ->execute([":photo" => $chemin_relatif, ":u" => $utilisateur_connecte]);
                    } catch (Exception $e) {
                        // colonne photo_profil n'existe pas encore
                    }
                    $message = "Photo de profil mise a jour.";
                    $message_type = 'success';
                }
            }
        }
    }

    // Infos utilisateur
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = :u");
    $stmt->execute([":u" => $user_cible]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: dashboard.php");
        die();
    }

    $photo = $user['photo_profil'] ?? null;

    // Tournois organises
    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM tournoi WHERE hote = :u");
    $stmtT->execute([":u" => $user_cible]);
    $nb_tournois_organises = (int) $stmtT->fetchColumn();

    // Tournois participes
    $stmtP = $pdo->prepare("SELECT COUNT(DISTINCT p.id_tournoi) FROM participant p WHERE p.nom_participant = :u");
    $stmtP->execute([":u" => $user_cible]);
    $nb_tournois_participes = (int) $stmtP->fetchColumn();

    // Stats joueur
    $stats = ['matchs_joues' => 0, 'victoires' => 0, 'defaites' => 0, 'podiums' => 0, 'score_total_marque' => 0, 'score_total_encaisse' => 0, 'tournois_joues' => 0];
    try {
        $stmtStats = $pdo->prepare("SELECT * FROM statistique_joueur WHERE id_utilisateur = :u");
        $stmtStats->execute([":u" => $user_cible]);
        $row = $stmtStats->fetch(PDO::FETCH_ASSOC);
        if ($row) $stats = $row;
    } catch (Exception $e) {}

    $winrate = $stats['matchs_joues'] > 0 ? round(($stats['victoires'] / $stats['matchs_joues']) * 100) : 0;
    $kd = $stats['defaites'] > 0 ? round($stats['victoires'] / $stats['defaites'], 2) : $stats['victoires'];

    // Derniers tournois participes
    $stmtRecent = $pdo->prepare("SELECT t.id, t.nom, t.jeu, t.date_depart, t.format, t.statut_tournoi FROM tournoi t INNER JOIN participant p ON p.id_tournoi = t.id WHERE p.nom_participant = :u ORDER BY t.date_depart DESC LIMIT 8");
    $stmtRecent->execute([":u" => $user_cible]);
    $derniers_tournois = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    // Tournois organises (liste)
    $stmtOrg = $pdo->prepare("SELECT id, nom, jeu, date_depart, format, statut_tournoi FROM tournoi WHERE hote = :u ORDER BY date_depart DESC LIMIT 5");
    $stmtOrg->execute([":u" => $user_cible]);
    $tournois_organises = $stmtOrg->fetchAll(PDO::FETCH_ASSOC);

    // Date d'inscription (approximation via le plus ancien tournoi ou la date de naissance)
    $date_inscription = $user['date_de_naissance']; // on utilise ca comme fallback

} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil — <?= htmlspecialchars($user_cible) ?> | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; } .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }</style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <a href="dashboard.php" class="text-sm text-slate-400 hover:text-white transition">← Dashboard</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-6 py-12">

        <?php if ($message): ?>
        <div class="mb-6 p-3.5 rounded text-sm font-medium border <?= $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-red-500/10 border-red-500/40 text-red-400' ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- HEADER PROFIL -->
        <div class="glass-card border border-slate-800 rounded-2xl p-8 mb-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-[300px] h-[300px] bg-indigo-600/10 rounded-full blur-[100px] pointer-events-none"></div>
            <div class="relative flex flex-col sm:flex-row items-center gap-6">
                <!-- Avatar -->
                <div class="relative group">
                    <?php if ($photo && file_exists(__DIR__ . '/' . $photo)): ?>
                        <img src="<?= htmlspecialchars($photo) ?>" alt="Avatar" class="w-24 h-24 rounded-full object-cover border-4 border-indigo-500/30 shadow-lg shadow-indigo-500/20">
                    <?php else: ?>
                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500 flex items-center justify-center text-4xl font-bold text-white shadow-lg shadow-indigo-500/30 border-4 border-indigo-500/30">
                            <?= strtoupper(substr($user_cible, 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($est_mon_profil): ?>
                    <form action="profil.php" method="post" enctype="multipart/form-data" class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                        <input type="hidden" name="action" value="upload_photo">
                        <label class="w-24 h-24 rounded-full bg-slate-950/80 flex items-center justify-center cursor-pointer">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <input type="file" name="photo" accept="image/*" class="hidden" onchange="this.form.submit()">
                        </label>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Infos -->
                <div class="text-center sm:text-left flex-grow">
                    <h1 class="text-3xl font-bold"><?= htmlspecialchars($user_cible) ?></h1>
                    <p class="text-slate-400 text-sm mt-1">
                        <?= htmlspecialchars($user['pays']) ?> &middot; <?= htmlspecialchars($user['email']) ?>
                    </p>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <?php if ($est_mon_profil): ?>
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-indigo-500/15 text-indigo-400 border border-indigo-500/30">Mon profil</span>
                        <?php endif; ?>
                        <?php if ($nb_tournois_organises > 0): ?>
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-amber-500/15 text-amber-400 border border-amber-500/30">Organisateur</span>
                        <?php endif; ?>
                        <?php if ($nb_tournois_participes > 0): ?>
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">Joueur actif</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick stats -->
                <div class="text-center sm:text-right">
                    <div class="text-3xl font-bold text-indigo-400"><?= $nb_tournois_organises + $nb_tournois_participes ?></div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Tournois total</div>
                </div>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="glass-card border border-slate-800 rounded-xl p-4 text-center hover:border-indigo-500/40 transition">
                <div class="text-2xl font-bold text-indigo-400"><?= $stats['matchs_joues'] ?></div>
                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-1">Matchs</div>
            </div>
            <div class="glass-card border border-slate-800 rounded-xl p-4 text-center hover:border-emerald-500/40 transition">
                <div class="text-2xl font-bold text-emerald-400"><?= $stats['victoires'] ?></div>
                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-1">Victoires</div>
            </div>
            <div class="glass-card border border-slate-800 rounded-xl p-4 text-center hover:border-red-500/40 transition">
                <div class="text-2xl font-bold text-red-400"><?= $stats['defaites'] ?></div>
                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-1">Defaites</div>
            </div>
            <div class="glass-card border border-slate-800 rounded-xl p-4 text-center hover:border-emerald-500/40 transition">
                <div class="text-2xl font-bold text-emerald-400"><?= $winrate ?>%</div>
                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-1">Winrate</div>
            </div>
            <div class="glass-card border border-slate-800 rounded-xl p-4 text-center hover:border-amber-500/40 transition">
                <div class="text-2xl font-bold text-amber-400"><?= $stats['podiums'] ?></div>
                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-1">Podiums</div>
            </div>
        </div>

        <!-- BARRE W/L + DETAILS -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="glass-card border border-slate-800 rounded-xl p-6">
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300 mb-4">Ratio Victoires / Defaites</h2>
                <div class="flex h-5 rounded-full overflow-hidden bg-slate-800 mb-3">
                    <?php if ($stats['matchs_joues'] > 0): ?>
                    <div class="bg-emerald-500 transition-all flex items-center justify-center" style="width: <?= $winrate ?>%">
                        <?php if ($winrate > 15): ?><span class="text-[9px] font-bold text-white"><?= $winrate ?>%</span><?php endif; ?>
                    </div>
                    <div class="bg-red-500 transition-all flex items-center justify-center" style="width: <?= 100 - $winrate ?>%">
                        <?php if ((100 - $winrate) > 15): ?><span class="text-[9px] font-bold text-white"><?= 100 - $winrate ?>%</span><?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="w-full flex items-center justify-center text-[9px] text-slate-500 font-bold">Aucun match</div>
                    <?php endif; ?>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-emerald-400 font-bold"><?= $stats['victoires'] ?> V</span>
                    <span class="text-red-400 font-bold"><?= $stats['defaites'] ?> D</span>
                </div>
            </div>

            <div class="glass-card border border-slate-800 rounded-xl p-6">
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300 mb-4">Informations</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Nom d'utilisateur</span>
                        <span class="font-bold"><?= htmlspecialchars($user_cible) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Pays</span>
                        <span class="font-bold"><?= htmlspecialchars($user['pays']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Tournois organises</span>
                        <span class="font-bold text-amber-400"><?= $nb_tournois_organises ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Tournois rejoints</span>
                        <span class="font-bold text-emerald-400"><?= $nb_tournois_participes ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Score total marque</span>
                        <span class="font-bold"><?= $stats['score_total_marque'] ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Score total encaisse</span>
                        <span class="font-bold"><?= $stats['score_total_encaisse'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOURNOIS PARTICIPES -->
        <?php if (!empty($derniers_tournois)): ?>
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden mb-8">
            <div class="bg-slate-900/80 px-6 py-4 border-b border-slate-800 flex items-center gap-2">
                <div class="w-1 h-4 bg-emerald-500 rounded"></div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Tournois rejoints</h2>
            </div>
            <div class="divide-y divide-slate-800/60">
                <?php foreach ($derniers_tournois as $t):
                    $st_class = match($t['statut_tournoi'] ?? '') {
                        'termine' => 'bg-slate-700/60 text-slate-300 border-slate-600',
                        'en_cours' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40',
                        default => 'bg-amber-500/15 text-amber-400 border-amber-500/40'
                    };
                    $st_label = match($t['statut_tournoi'] ?? '') {
                        'termine' => 'Termine',
                        'en_cours' => 'En cours',
                        default => 'A venir'
                    };
                ?>
                <div class="px-6 py-3 flex items-center justify-between hover:bg-slate-800/30 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <div>
                            <span class="text-sm font-bold"><?= htmlspecialchars($t['nom']) ?></span>
                            <span class="text-xs text-indigo-400 ml-2"><?= htmlspecialchars($t['jeu']) ?></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-slate-400"><?= $t['date_depart'] ?></span>
                        <span class="text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full border <?= $st_class ?>"><?= $st_label ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- TOURNOIS ORGANISES -->
        <?php if (!empty($tournois_organises)): ?>
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
            <div class="bg-slate-900/80 px-6 py-4 border-b border-slate-800 flex items-center gap-2">
                <div class="w-1 h-4 bg-amber-500 rounded"></div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Tournois organises</h2>
            </div>
            <div class="divide-y divide-slate-800/60">
                <?php foreach ($tournois_organises as $t):
                    $st_class = match($t['statut_tournoi'] ?? '') {
                        'termine' => 'bg-slate-700/60 text-slate-300 border-slate-600',
                        'en_cours' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40',
                        default => 'bg-amber-500/15 text-amber-400 border-amber-500/40'
                    };
                    $st_label = match($t['statut_tournoi'] ?? '') {
                        'termine' => 'Termine',
                        'en_cours' => 'En cours',
                        default => 'Brouillon'
                    };
                ?>
                <div class="px-6 py-3 flex items-center justify-between hover:bg-slate-800/30 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded bg-amber-500/10 border border-amber-500/30 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <div>
                            <span class="text-sm font-bold"><?= htmlspecialchars($t['nom']) ?></span>
                            <span class="text-xs text-slate-400 ml-2"><?= htmlspecialchars($t['jeu']) ?> &middot; <?= $t['format'] ?></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-slate-400"><?= $t['date_depart'] ?></span>
                        <span class="text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full border <?= $st_class ?>"><?= $st_label ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
<?php include '_theme.php'; ?>
</body>
</html>
