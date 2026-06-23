<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) { header("Location: login.php"); die(); }
$hote_connecte = $_SESSION['id_utilisateur'];

if (!isset($_GET['id_tournoi'])) { header("Location: dashboard.php"); die(); }
$id_tournoi = (int) $_GET['id_tournoi'];

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/_helpers.php';

    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND hote = :hote");
    $stmt->execute([":id" => $id_tournoi, ":hote" => $hote_connecte]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tournoi) { header("Location: dashboard.php"); die(); }

    $fuseau = $tournoi['fuseau_horaire'] ?? 'Africa/Casablanca';

    // ===== Action AJAX : planifier l'heure d'un match =====
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'planifier') {
        header('Content-Type: application/json');
        $id_match = (int) ($_POST['id_match'] ?? 0);
        $valeur   = trim($_POST['heure'] ?? '');

        // datetime-local => 'Y-m-d\TH:i' ; on stocke 'Y-m-d H:i:s' ou NULL si vide
        $heure_db = null;
        if ($valeur !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $valeur);
            if ($dt) $heure_db = $dt->format('Y-m-d H:i:s');
        }

        $pdo->prepare("UPDATE match_tournoi SET heure_prevue = :h WHERE id = :id AND id_tournoi = :tid")
            ->execute([":h" => $heure_db, ":id" => $id_match, ":tid" => $id_tournoi]);

        $affichage = $heure_db ? (new DateTime($heure_db))->format('d M Y · H:i') : '';
        echo json_encode(["ok" => true, "affichage" => $affichage]);
        die();
    }

    // ===== Donnees d'affichage =====
    $stmtNbP = $pdo->prepare("SELECT COUNT(*) FROM participant WHERE id_tournoi = :id AND statut = 'actif'");
    $stmtNbP->execute([":id" => $id_tournoi]);
    $nb_participants = (int) $stmtNbP->fetchColumn();

    $map_participants = [];
    $stmtAllP = $pdo->prepare("SELECT id, nom_participant, nom_affichage FROM participant WHERE id_tournoi = :id");
    $stmtAllP->execute([":id" => $id_tournoi]);
    foreach ($stmtAllP->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $map_participants[$p['id']] = $p['nom_affichage'] ?: $p['nom_participant'];
    }

    $stmtM = $pdo->prepare("SELECT * FROM match_tournoi WHERE id_tournoi = :id ORDER BY manche ASC, position ASC");
    $stmtM->execute([":id" => $id_tournoi]);
    $matchs = $stmtM->fetchAll(PDO::FETCH_ASSOC);

    // Parametres Double Elimination (calcules a partir du nb de matchs de la manche 1)
    $format = $tournoi['format'] ?? 'Single Elimination';
    $is_de = ($format === 'Double Elimination');
    $nb_manches_wb = 0; $nb_manches_lb = 0;
    if ($is_de) {
        $r1c = 0;
        foreach ($matchs as $m) { if ((int) $m['manche'] === 1) $r1c++; }
        if ($r1c > 0) {
            $nb_manches_wb = (int) log($r1c * 2, 2);
            $nb_manches_lb = 2 * max(0, $nb_manches_wb - 1);
        }
    }
    $nb_manches_total = 0;
    foreach ($matchs as $m) { if ((int) $m['manche'] > $nb_manches_total) $nb_manches_total = (int) $m['manche']; }

} catch (PDOException $e) { error_log("DB error: " . $e->getMessage()); die("Une erreur est survenue. Veuillez reessayer plus tard."); }

// Label de section (bracket) + label de manche
function infoBracket($manche, $is_de, $nwb, $nlb, $total) {
    if ($is_de) {
        $gf = $nwb + $nlb + 1;
        if ($manche >= $gf)      return ['section' => 'GF', 'label' => 'Grande Finale'];
        if ($manche > $nwb)      return ['section' => 'LB', 'label' => 'Perdants R' . ($manche - $nwb)];
        if ($manche === $nwb)    return ['section' => 'WB', 'label' => 'Finale Gagnants'];
        return ['section' => 'WB', 'label' => 'Gagnants R' . $manche];
    }
    if ($manche === $total)     return ['section' => 'SE', 'label' => 'Finale'];
    if ($manche === $total - 1) return ['section' => 'SE', 'label' => 'Demi-finales'];
    if ($manche === $total - 2) return ['section' => 'SE', 'label' => 'Quarts de finale'];
    return ['section' => 'SE', 'label' => 'Manche ' . $manche];
}

// Decalage GMT du fuseau pour l'affichage
try {
    $tz = new DateTimeZone($fuseau);
    $offset = $tz->getOffset(new DateTime('now', $tz)) / 3600;
    $gmt_label = 'GMT' . ($offset >= 0 ? '+' : '') . (int) $offset;
} catch (Exception) {
    $gmt_label = '';
}

$statut = statut_tournoi($tournoi['date_depart'], $tournoi['statut_tournoi'] ?? '');
$date_fmt = (new DateTime($tournoi['date_depart']))->format('D d M Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; } .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }</style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-[95%] mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-xs font-bold text-slate-400 hover:text-white transition">Dashboard</a>
                <a href="gerer_tournoi.php?id=<?= $id_tournoi ?>" class="text-xs font-bold text-slate-400 hover:text-white transition">Paramètres</a>
                <span class="text-sm text-slate-400 hidden sm:inline"><?= htmlspecialchars($hote_connecte) ?></span>
            </div>
        </div>
    </nav>

    <?php $tab_actif = 'overview'; include '_tabs.php'; ?>

    <main class="max-w-5xl mx-auto px-6 py-10">

        <!-- En-tete -->
        <div class="mb-8">
            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full border <?= $statut['class'] ?> mb-3">
                <span class="w-1.5 h-1.5 rounded-full <?= $statut['dot'] ?>"></span>
                <?= $statut['label'] ?>
            </span>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight italic"><?= htmlspecialchars($tournoi['nom']) ?></h1>
            <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mt-2">Organisé par <?= htmlspecialchars($tournoi['hote']) ?></p>
        </div>

        <!-- Grille de metadonnees -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
            <?php
            $cards = [
                ['Jeu', htmlspecialchars($tournoi['jeu']),
                 '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                ['Participants', $nb_participants . ($tournoi['max_participants'] ? ' / ' . (int) $tournoi['max_participants'] : ''),
                 '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/>'],
                ['Format', htmlspecialchars($format) . ' · BO' . (int) ($tournoi['best_of'] ?? 1),
                 '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h4v4H4zM4 14h4v4H4zM16 9h4M14 6h6M16 15h4M14 18h6M8 8h6M8 16h6"/>'],
                ['Date de départ', $date_fmt . ($gmt_label ? ' · ' . $gmt_label : ''),
                 '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
            ];
            foreach ($cards as [$titre, $valeur, $icone]):
            ?>
            <div class="glass-card border border-slate-800 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $icone ?></svg>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500"><?= $titre ?></span>
                </div>
                <p class="text-sm font-bold text-slate-100"><?= $valeur ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Schedule -->
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
            <div class="bg-slate-900/80 px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                    <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Schedule</h2>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    <?= htmlspecialchars($fuseau) ?><?= $gmt_label ? ' (' . $gmt_label . ')' : '' ?>
                </span>
            </div>

            <?php if (empty($matchs)): ?>
            <div class="p-10 text-center text-slate-500 text-sm">
                Aucun match généré. Ouvrez l'onglet <a href="bracket_live.php?id_tournoi=<?= $id_tournoi ?>" class="text-indigo-400 hover:text-indigo-300 font-bold">Bracket</a> pour générer l'arbre.
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-800/60">
                <?php foreach ($matchs as $m):
                    $info = infoBracket((int) $m['manche'], $is_de, $nb_manches_wb, $nb_manches_lb, $nb_manches_total);
                    $nom1 = isset($m['id_participant1']) ? ($map_participants[$m['id_participant1']] ?? null) : null;
                    $nom2 = isset($m['id_participant2']) ? ($map_participants[$m['id_participant2']] ?? null) : null;
                    $sect_color = match ($info['section']) {
                        'LB' => 'text-amber-400 border-amber-500/30 bg-amber-500/10',
                        'GF' => 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10',
                        default => 'text-indigo-400 border-indigo-500/30 bg-indigo-500/10',
                    };
                    $heure_input = !empty($m['heure_prevue']) ? (new DateTime($m['heure_prevue']))->format('Y-m-d\TH:i') : '';
                    $heure_label = !empty($m['heure_prevue']) ? (new DateTime($m['heure_prevue']))->format('d M Y · H:i') : '';
                ?>
                <div class="px-6 py-3.5 flex flex-col md:flex-row md:items-center gap-3 hover:bg-slate-800/20 transition" data-match-row="<?= $m['id'] ?>">
                    <div class="flex items-center gap-3 md:w-64 flex-shrink-0">
                        <span class="text-[9px] font-bold uppercase tracking-widest px-2 py-1 rounded border <?= $sect_color ?> w-9 text-center"><?= $info['section'] ?></span>
                        <span class="text-xs font-bold text-slate-300"><?= $info['label'] ?></span>
                        <span class="text-[10px] text-slate-600">#<?= $m['position'] ?></span>
                    </div>
                    <div class="flex-1 flex items-center gap-2 text-sm min-w-0">
                        <span class="font-bold text-slate-200 truncate max-w-[40%]"><?= htmlspecialchars($nom1 ?? 'À déterminer') ?></span>
                        <span class="text-[10px] text-slate-600 font-bold uppercase">vs</span>
                        <span class="font-bold text-slate-200 truncate max-w-[40%]"><?= htmlspecialchars($nom2 ?? 'À déterminer') ?></span>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="match-heure-label text-[11px] font-bold text-indigo-300 min-w-[110px] text-right <?= $heure_label ? '' : 'text-slate-600' ?>">
                            <?= $heure_label ?: 'Non planifié' ?>
                        </span>
                        <input type="datetime-local" value="<?= $heure_input ?>"
                               onchange="planifier(<?= $m['id'] ?>, this)"
                               class="bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded px-2 py-1.5 text-xs text-slate-200 outline-none transition dark:[color-scheme:dark]">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <p class="text-xs text-slate-600 mt-3">
            Les heures sont enregistrées dans le fuseau de l'événement (<?= htmlspecialchars($fuseau) ?>). Modifiable dans les <a href="gerer_tournoi.php?id=<?= $id_tournoi ?>" class="text-indigo-400 hover:text-indigo-300 font-bold">Paramètres</a>.
        </p>
    </main>

    <script>
        function planifier(idMatch, input) {
            const row = document.querySelector('[data-match-row="' + idMatch + '"]');
            const label = row.querySelector('.match-heure-label');
            const formData = new FormData();
            formData.append('ajax_action', 'planifier');
            formData.append('id_match', idMatch);
            formData.append('heure', input.value);

            fetch('tournoi_overview.php?id_tournoi=<?= $id_tournoi ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        if (data.affichage) {
                            label.textContent = data.affichage;
                            label.classList.remove('text-slate-600');
                            label.classList.add('text-indigo-300');
                        } else {
                            label.textContent = 'Non planifié';
                            label.classList.add('text-slate-600');
                            label.classList.remove('text-indigo-300');
                        }
                    }
                });
        }
    </script>

<?php include '_theme.php'; ?>
</body>
</html>
