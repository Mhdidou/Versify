<?php
session_start();

$id_tournoi = $_GET['id_tournoi'] ?? null;
if (!$id_tournoi) { header("Location: index.php"); die(); }

try {
    require_once __DIR__ . '/config.php';

    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id");
    $stmt->execute([":id" => $id_tournoi]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tournoi) { header("Location: index.php"); die(); }

    // Map participants
    $map_p = [];
    $stmtP = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id");
    $stmtP->execute([":id" => $id_tournoi]);
    foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $map_p[$p['id']] = $p['nom_affichage'] ?: $p['nom_participant'];
    }

    // Matchs termines, ordre chronologique inverse
    $stmtH = $pdo->prepare("SELECT * FROM match_tournoi WHERE id_tournoi = :id AND statut_match = 'termine' AND gagnant_id IS NOT NULL ORDER BY manche DESC, position ASC");
    $stmtH->execute([":id" => $id_tournoi]);
    $matchs = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    $nb_manches = 0;
    if (!empty($matchs)) {
        $stmtR = $pdo->prepare("SELECT MAX(manche) FROM match_tournoi WHERE id_tournoi = :id");
        $stmtR->execute([":id" => $id_tournoi]);
        $nb_manches = (int) $stmtR->fetchColumn();
    }

    function labelMancheHist($r, $total) {
        if ($r === $total) return "Finale";
        if ($r === $total - 1) return "Demi-finales";
        if ($r === $total - 2) return "Quarts de finale";
        return "Manche " . $r;
    }

} catch (PDOException $e) { error_log("DB error: " . $e->getMessage()); die("Une erreur est survenue. Veuillez reessayer plus tard."); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; } .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }</style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen">
    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3"><div class="w-2 h-8 bg-indigo-500 rounded-full"></div><span class="text-xl font-bold uppercase tracking-widest">Versify</span></a>
            <a href="bracket_live.php?id_tournoi=<?= $id_tournoi ?>" class="text-sm text-slate-400 hover:text-white transition">← Bracket</a>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-6 py-10">
        <h1 class="text-3xl font-bold italic mb-2">Historique des matchs</h1>
        <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mb-8"><?= htmlspecialchars($tournoi['nom']) ?> &middot; <?= htmlspecialchars($tournoi['jeu']) ?></p>

        <?php if (empty($matchs)): ?>
            <div class="glass-card border border-slate-800 rounded-xl p-12 text-center text-slate-500">Aucun match termine pour le moment.</div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($matchs as $m):
                    $nom1 = $map_p[$m['id_participant1']] ?? '?';
                    $nom2 = $map_p[$m['id_participant2']] ?? '?';
                    $g1 = $m['gagnant_id'] == $m['id_participant1'];
                ?>
                <div class="glass-card border border-slate-800 rounded-xl p-4 flex items-center justify-between hover:border-indigo-500/40 transition">
                    <div class="flex items-center gap-4">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 w-24"><?= labelMancheHist($m['manche'], $nb_manches) ?></span>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold <?= $g1 ? 'text-emerald-400' : 'text-slate-400' ?>"><?= htmlspecialchars($nom1) ?></span>
                            <span class="text-xs text-slate-500 font-bold px-2 py-0.5 bg-slate-800 rounded"><?= $m['score1'] ?> - <?= $m['score2'] ?></span>
                            <span class="text-sm font-bold <?= !$g1 ? 'text-emerald-400' : 'text-slate-400' ?>"><?= htmlspecialchars($nom2) ?></span>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">Termine</span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
<?php include '_theme.php'; ?>
</body>
</html>
