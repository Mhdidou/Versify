<?php
// Page publique d'un tournoi - accessible sans connexion via lien de partage
session_start();

if (!isset($_GET['code'])) {
    header("Location: index.php");
    die();
}

$code = $_GET['code'];

try {
    require_once __DIR__ . '/config.php';

    // Trouver le tournoi par lien de partage
    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE lien_partage = :code");
    $stmt->execute([":code" => $code]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournoi) {
        header("Location: index.php");
        die();
    }

    $id_tournoi = $tournoi['id'];
    $best_of = (int) ($tournoi['best_of'] ?? 1);

    // Participants actifs
    $stmtP = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id AND statut = 'actif' ORDER BY seed ASC");
    $stmtP->execute([":id" => $id_tournoi]);
    $participants = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // Matchs
    $stmtM = $pdo->prepare("SELECT * FROM match_tournoi WHERE id_tournoi = :id ORDER BY ronde ASC, position ASC");
    $stmtM->execute([":id" => $id_tournoi]);
    $matchs = $stmtM->fetchAll(PDO::FETCH_ASSOC);

    // Map participants
    $map_p = [];
    $stmtAllP = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id");
    $stmtAllP->execute([":id" => $id_tournoi]);
    foreach ($stmtAllP->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $map_p[$p['id']] = $p['nom_affichage'] ?: $p['nom_participant'];
    }

    // Classement
    $stmtC = $pdo->prepare("SELECT c.*, p.nom_affichage, p.nom_participant FROM classement_tournoi c LEFT JOIN participant p ON p.id = c.id_participant WHERE c.id_tournoi = :id ORDER BY c.position_finale ASC");
    $stmtC->execute([":id" => $id_tournoi]);
    $classement = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    // Organiser matchs par ronde
    $rondes = [];
    foreach ($matchs as $m) { $rondes[$m['ronde']][] = $m; }
    $nb_rondes = count($rondes);

    // Statut
    $today = new DateTime('today');
    $depart = new DateTime($tournoi['date_depart']);
    if ($tournoi['statut_tournoi'] === 'termine') { $st_label = 'Termine'; $st_class = 'bg-slate-700/60 text-slate-300 border-slate-600'; }
    elseif ($tournoi['statut_tournoi'] === 'en_cours') { $st_label = 'En cours'; $st_class = 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40'; }
    else { $st_label = 'A venir'; $st_class = 'bg-amber-500/15 text-amber-400 border-amber-500/40'; }

    // Inscription ouverte ?
    $inscription_ouverte = (bool) $tournoi['inscription_ouverte'];
    $utilisateur_connecte = $_SESSION['id_utilisateur'] ?? null;

    // Labels rondes
    function labelRondePublic($r, $total) {
        if ($r === $total) return "Finale";
        if ($r === $total - 1) return "Demi-finales";
        if ($r === $total - 2) return "Quarts de finale";
        return "Ronde " . $r;
    }

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
    <title><?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
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
            <div class="flex items-center gap-4">
                <?php if ($utilisateur_connecte): ?>
                    <a href="dashboard.php" class="text-sm text-slate-400 hover:text-white transition">Mon dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="text-sm text-slate-400 hover:text-white transition">Se connecter</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-10">

        <!-- Header tournoi -->
        <div class="glass-card border border-slate-800 rounded-2xl p-8 mb-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-indigo-600/10 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="relative">
                <div class="flex flex-wrap items-center gap-3 mb-3">
                    <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full border <?= $st_class ?>"><?= $st_label ?></span>
                    <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700"><?= htmlspecialchars($tournoi['format']) ?></span>
                    <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700">BO<?= $best_of ?></span>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold italic"><?= htmlspecialchars($tournoi['nom']) ?></h1>
                <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mt-2"><?= htmlspecialchars($tournoi['jeu']) ?></p>
                <p class="text-slate-400 text-sm mt-2">Organise par <span class="text-slate-200 font-bold"><?= htmlspecialchars($tournoi['hote']) ?></span> &middot; <?= htmlspecialchars($tournoi['date_depart']) ?></p>
                <?php if ($tournoi['description']): ?>
                    <p class="text-slate-400 text-sm mt-4 max-w-2xl"><?= nl2br(htmlspecialchars($tournoi['description'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Onglets -->
        <div class="flex gap-2 mb-8 border-b border-slate-800 pb-4">
            <button onclick="showTab('bracket')" id="tab-bracket" class="tab-btn text-sm font-bold px-4 py-2 rounded bg-indigo-600 text-white">Bracket</button>
            <button onclick="showTab('participants')" id="tab-participants" class="tab-btn text-sm font-bold px-4 py-2 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 transition">Participants (<?= count($participants) ?>)</button>
            <?php if (!empty($classement)): ?>
            <button onclick="showTab('classement')" id="tab-classement" class="tab-btn text-sm font-bold px-4 py-2 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 transition">Classement</button>
            <?php endif; ?>
            <?php if ($inscription_ouverte && $utilisateur_connecte): ?>
            <button onclick="showTab('inscription')" id="tab-inscription" class="tab-btn text-sm font-bold px-4 py-2 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 transition">S'inscrire</button>
            <?php endif; ?>
        </div>

        <!-- TAB: Bracket -->
        <div id="content-bracket" class="tab-content">
            <?php if (empty($matchs)): ?>
                <div class="glass-card border border-slate-800 rounded-xl p-12 text-center">
                    <p class="text-slate-500">Le bracket n'a pas encore ete genere.</p>
                </div>
            <?php else: ?>
                <div class="glass-card border border-slate-800 rounded-2xl p-6 overflow-x-auto">
                    <div class="flex gap-10 min-w-fit">
                        <?php foreach ($rondes as $num_ronde => $matchs_ronde): ?>
                        <div class="flex flex-col gap-4">
                            <div class="text-center text-xs font-bold uppercase tracking-widest text-slate-500 mb-2"><?= labelRondePublic($num_ronde, $nb_rondes) ?></div>
                            <div class="flex flex-col justify-around flex-grow gap-4">
                                <?php foreach ($matchs_ronde as $match):
                                    $nom1 = isset($match['id_participant1']) ? ($map_p[$match['id_participant1']] ?? '') : '';
                                    $nom2 = isset($match['id_participant2']) ? ($map_p[$match['id_participant2']] ?? '') : '';
                                    $est_termine = $match['statut_match'] === 'termine';
                                    $g1 = $est_termine && $match['gagnant_id'] == $match['id_participant1'];
                                    $g2 = $est_termine && $match['gagnant_id'] == $match['id_participant2'];
                                ?>
                                <div class="border border-slate-700 rounded-lg overflow-hidden w-52">
                                    <div class="flex items-center justify-between px-3 py-2 border-b border-slate-800 <?= $g1 ? 'bg-indigo-500/15' : 'bg-slate-900/40' ?>">
                                        <span class="text-xs font-bold truncate max-w-[130px] <?= $g1 ? 'text-indigo-300' : 'text-slate-200' ?>"><?= htmlspecialchars($nom1) ?: '&mdash;' ?></span>
                                        <span class="text-xs font-bold <?= $g1 ? 'text-indigo-400' : 'text-slate-400' ?>"><?= ($nom1 || $nom2) ? $match['score1'] : '' ?></span>
                                    </div>
                                    <div class="flex items-center justify-between px-3 py-2 <?= $g2 ? 'bg-indigo-500/15' : 'bg-slate-900/40' ?>">
                                        <span class="text-xs font-bold truncate max-w-[130px] <?= $g2 ? 'text-indigo-300' : 'text-slate-200' ?>"><?= htmlspecialchars($nom2) ?: '&mdash;' ?></span>
                                        <span class="text-xs font-bold <?= $g2 ? 'text-indigo-400' : 'text-slate-400' ?>"><?= ($nom1 || $nom2) ? $match['score2'] : '' ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: Participants -->
        <div id="content-participants" class="tab-content hidden">
            <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
                <div class="divide-y divide-slate-800/60">
                    <?php foreach ($participants as $p): ?>
                    <div class="flex items-center gap-3 px-6 py-3 hover:bg-slate-800/30 transition">
                        <span class="w-7 h-7 rounded bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold"><?= $p['seed'] ?></span>
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500/30 to-fuchsia-500/30 border border-slate-700 flex items-center justify-center text-[10px] font-bold"><?= strtoupper(substr($p['nom_affichage'] ?: $p['nom_participant'], 0, 1)) ?></div>
                        <span class="text-sm font-bold"><?= htmlspecialchars($p['nom_affichage'] ?: $p['nom_participant']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TAB: Classement -->
        <?php if (!empty($classement)): ?>
        <div id="content-classement" class="tab-content hidden">
            <div class="glass-card border border-slate-800 rounded-xl p-8">
                <!-- Podium -->
                <div class="flex items-end justify-center gap-4 mb-8">
                    <?php if (isset($classement[1])): ?>
                    <div class="text-center">
                        <div class="w-16 h-24 bg-slate-700 rounded-t-lg flex items-end justify-center pb-2"><span class="text-2xl">🥈</span></div>
                        <p class="text-xs font-bold mt-2 truncate w-20"><?= htmlspecialchars($classement[1]['nom_affichage'] ?: $classement[1]['nom_participant']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($classement[0])): ?>
                    <div class="text-center">
                        <div class="w-16 h-32 bg-amber-500/20 border border-amber-500/40 rounded-t-lg flex items-end justify-center pb-2"><span class="text-3xl">🥇</span></div>
                        <p class="text-xs font-bold mt-2 truncate w-20 text-amber-400"><?= htmlspecialchars($classement[0]['nom_affichage'] ?: $classement[0]['nom_participant']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($classement[2])): ?>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-orange-900/30 rounded-t-lg flex items-end justify-center pb-2"><span class="text-xl">🥉</span></div>
                        <p class="text-xs font-bold mt-2 truncate w-20"><?= htmlspecialchars($classement[2]['nom_affichage'] ?: $classement[2]['nom_participant']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Liste complete -->
                <div class="divide-y divide-slate-800/60">
                    <?php foreach ($classement as $c): ?>
                    <div class="flex items-center justify-between py-2">
                        <div class="flex items-center gap-3">
                            <span class="w-6 text-center text-sm font-bold text-slate-400">#<?= $c['position_finale'] ?></span>
                            <span class="text-sm font-bold"><?= htmlspecialchars($c['nom_affichage'] ?: $c['nom_participant']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB: Inscription -->
        <?php if ($inscription_ouverte && $utilisateur_connecte): ?>
        <div id="content-inscription" class="tab-content hidden">
            <div class="glass-card border border-slate-800 rounded-xl p-8 max-w-md">
                <h2 class="text-lg font-bold mb-4">S'inscrire a ce tournoi</h2>
                <form action="inscription_publique.php" method="post" class="space-y-4">
                    <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                    <div>
                        <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Message (optionnel)</label>
                        <textarea name="message" rows="3" placeholder="Presentez-vous..." class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-sm outline-none focus:border-indigo-500 transition"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-3 rounded text-sm font-bold uppercase tracking-widest transition">Demander l'inscription</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <script>
    function showTab(name) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.tab-btn').forEach(el => { el.classList.remove('bg-indigo-600','text-white'); el.classList.add('bg-slate-800','text-slate-300'); });
        document.getElementById('content-' + name).classList.remove('hidden');
        const btn = document.getElementById('tab-' + name);
        btn.classList.remove('bg-slate-800','text-slate-300');
        btn.classList.add('bg-indigo-600','text-white');
    }
    </script>
<?php include '_theme.php'; ?>
</body>
</html>
