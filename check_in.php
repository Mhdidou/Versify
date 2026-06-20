<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) { header("Location: login.php"); die(); }

$utilisateur = $_SESSION['id_utilisateur'];
$id_tournoi = $_GET['id_tournoi'] ?? $_POST['id_tournoi'] ?? null;
if (!$id_tournoi) { header("Location: dashboard.php"); die(); }

$message = '';
$message_type = '';

try {
    require_once __DIR__ . '/config.php';

    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id");
    $stmt->execute([":id" => $id_tournoi]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tournoi) { header("Location: dashboard.php"); die(); }

    $est_hote = ($tournoi['hote'] === $utilisateur);

    // Action: joueur fait son check-in
    if (isset($_POST['action']) && $_POST['action'] === 'check_in') {
        $pdo->prepare("UPDATE participant SET checked_in = 1, date_check_in = NOW() WHERE id_tournoi = :idt AND nom_participant = :u AND statut = 'actif'")
            ->execute([":idt" => $id_tournoi, ":u" => $utilisateur]);
        $message = "Check-in effectue avec succes.";
        $message_type = 'success';
    }

    // Action: organisateur forfait les non-checked-in
    if (isset($_POST['action']) && $_POST['action'] === 'forfait_non_checkin' && $est_hote) {
        $pdo->prepare("UPDATE participant SET statut = 'forfait', seed = NULL WHERE id_tournoi = :idt AND checked_in = 0 AND statut = 'actif'")
            ->execute([":idt" => $id_tournoi]);

        // Recalculer seeds
        $stmtAll = $pdo->prepare("SELECT id FROM participant WHERE id_tournoi = :idt AND statut = 'actif' ORDER BY seed ASC");
        $stmtAll->execute([":idt" => $id_tournoi]);
        $s = 1;
        foreach ($stmtAll->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $pdo->prepare("UPDATE participant SET seed = :s WHERE id = :id")->execute([":s" => $s, ":id" => $p['id']]);
            $s++;
        }
        $message = "Participants non-confirmes mis en forfait.";
        $message_type = 'success';
    }

    // Liste des participants avec statut check-in
    $stmtP = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id AND statut = 'actif' ORDER BY seed ASC");
    $stmtP->execute([":id" => $id_tournoi]);
    $participants = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // Mon statut
    $stmtMoi = $pdo->prepare("SELECT checked_in FROM participant WHERE id_tournoi = :id AND nom_participant = :u AND statut = 'actif'");
    $stmtMoi->execute([":id" => $id_tournoi, ":u" => $utilisateur]);
    $mon_checkin = $stmtMoi->fetch(PDO::FETCH_ASSOC);

    $nb_checked = 0;
    foreach ($participants as $p) { if ($p['checked_in']) $nb_checked++; }

} catch (PDOException $e) { die("Erreur: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; } .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }</style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen">
    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3"><div class="w-2 h-8 bg-indigo-500 rounded-full"></div><span class="text-xl font-bold uppercase tracking-widest">Versify</span></a>
            <a href="participants.php?id_tournoi=<?= $id_tournoi ?>" class="text-sm text-slate-400 hover:text-white transition">← Retour</a>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-6 py-10">
        <h1 class="text-3xl font-bold italic mb-2">Check-in</h1>
        <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mb-2"><?= htmlspecialchars($tournoi['nom']) ?></p>
        <p class="text-slate-400 text-sm mb-8"><?= $nb_checked ?> / <?= count($participants) ?> participants confirmes</p>

        <?php if ($message): ?>
        <div class="mb-6 p-3.5 rounded text-sm font-medium border <?= $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-red-500/10 border-red-500/40 text-red-400' ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- Bouton check-in pour le joueur -->
        <?php if ($mon_checkin !== false && !$mon_checkin['checked_in']): ?>
        <form action="check_in.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="mb-8">
            <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
            <input type="hidden" name="action" value="check_in">
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-4 rounded-xl text-lg font-bold uppercase tracking-widest transition shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Confirmer ma presence
            </button>
        </form>
        <?php elseif ($mon_checkin !== false && $mon_checkin['checked_in']): ?>
        <div class="mb-8 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-400 text-center font-bold">
            ✓ Vous etes confirme pour ce tournoi
        </div>
        <?php endif; ?>

        <!-- Bouton organisateur: forfait les absents -->
        <?php if ($est_hote): ?>
        <form action="check_in.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="mb-8">
            <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
            <input type="hidden" name="action" value="forfait_non_checkin">
            <button type="submit" onclick="return confirm('Mettre en forfait tous les participants non confirmes ?');" class="w-full bg-red-500/20 hover:bg-red-500/30 border border-red-500/40 text-red-400 py-3 rounded-xl text-sm font-bold uppercase tracking-widest transition">
                Forfait les non-confirmes
            </button>
        </form>
        <?php endif; ?>

        <!-- Liste -->
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
            <div class="divide-y divide-slate-800/60">
                <?php foreach ($participants as $p): ?>
                <div class="flex items-center justify-between px-6 py-3">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold"><?= $p['seed'] ?></span>
                        <span class="text-sm font-bold"><?= htmlspecialchars($p['nom_affichage'] ?: $p['nom_participant']) ?></span>
                    </div>
                    <?php if ($p['checked_in']): ?>
                        <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/40">Confirme</span>
                    <?php else: ?>
                        <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-slate-700/60 text-slate-400 border border-slate-600">En attente</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
<?php include '_theme.php'; ?>
</body>
</html>
