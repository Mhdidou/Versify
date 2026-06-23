<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) { header("Location: login.php"); die(); }

$hote_connecte = $_SESSION['id_utilisateur'];
$id_tournoi = $_GET['id_tournoi'] ?? $_POST['id_tournoi'] ?? null;
if (!$id_tournoi) { header("Location: dashboard.php"); die(); }

$message = '';
$message_type = '';

try {
    require_once __DIR__ . '/config.php';

    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND hote = :hote");
    $stmt->execute([":id" => $id_tournoi, ":hote" => $hote_connecte]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournoi) { header("Location: dashboard.php"); die(); }

    if ($tournoi['statut_tournoi'] === 'termine') {
        header("Location: bracket_live.php?id_tournoi=" . (int)$id_tournoi);
        die();
    }

    // Action: Forfait
    if (isset($_POST['action']) && $_POST['action'] === 'forfait') {
        $id_participant = $_POST['id_participant'] ?? 0;
        $pdo->prepare("UPDATE participant SET statut = 'forfait', seed = NULL WHERE id = :id AND id_tournoi = :idt")
            ->execute([":id" => $id_participant, ":idt" => $id_tournoi]);
        $message = "Participant mis en forfait.";
        $message_type = 'success';
    }

    // Action: Reactiver
    if (isset($_POST['action']) && $_POST['action'] === 'reactiver') {
        $id_participant = $_POST['id_participant'] ?? 0;
        $stmtSeed = $pdo->prepare("SELECT COALESCE(MAX(seed), 0) + 1 FROM participant WHERE id_tournoi = :idt AND statut = 'actif'");
        $stmtSeed->execute([":idt" => $id_tournoi]);
        $prochain_seed = (int) $stmtSeed->fetchColumn();

        $pdo->prepare("UPDATE participant SET statut = 'actif', seed = :seed WHERE id = :id AND id_tournoi = :idt")
            ->execute([":seed" => $prochain_seed, ":id" => $id_participant, ":idt" => $id_tournoi]);
        $message = "Participant reactive.";
        $message_type = 'success';
    }

    // Action: Modifier nom d'affichage
    if (isset($_POST['action']) && $_POST['action'] === 'modifier_affichage') {
        $id_participant = $_POST['id_participant'] ?? 0;
        $nouveau_nom = trim($_POST['nom_affichage'] ?? '');
        if (!empty($nouveau_nom)) {
            $pdo->prepare("UPDATE participant SET nom_affichage = :nom WHERE id = :id AND id_tournoi = :idt")
                ->execute([":nom" => $nouveau_nom, ":id" => $id_participant, ":idt" => $id_tournoi]);
            $message = "Nom d'affichage modifie.";
            $message_type = 'success';
        }
    }

    // Recuperer participants
    $stmtActifs = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id AND statut = 'actif' ORDER BY seed ASC");
    $stmtActifs->execute([":id" => $id_tournoi]);
    $participants_actifs = $stmtActifs->fetchAll(PDO::FETCH_ASSOC);

    $stmtForfaits = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id AND statut = 'forfait' ORDER BY nom_participant ASC");
    $stmtForfaits->execute([":id" => $id_tournoi]);
    $participants_forfaits = $stmtForfaits->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { error_log("DB error: " . $e->getMessage()); die("Une erreur est survenue. Veuillez reessayer plus tard."); }

$nb_actifs = count($participants_actifs);
$nb_forfaits = count($participants_forfaits);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participants — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
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
            <a href="bracket_live.php?id_tournoi=<?= $id_tournoi ?>" class="text-sm font-bold text-indigo-400 hover:text-indigo-300 transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Retour au bracket
            </a>
        </div>
    </nav>

    <?php $tab_actif = 'participants'; include '_tabs.php'; ?>

    <main class="max-w-4xl mx-auto px-6 py-10">

        <div class="mb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-[10px] font-bold uppercase tracking-widest mb-3">
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                Tournoi en cours
            </div>
            <h1 class="text-3xl font-bold italic"><?= htmlspecialchars($tournoi['nom']) ?></h1>
            <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mt-1"><?= htmlspecialchars($tournoi['jeu']) ?> &middot; <?= $nb_actifs ?> participants actifs</p>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 p-3.5 rounded text-sm font-medium border <?= $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-red-500/10 border-red-500/40 text-red-400' ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- PARTICIPANTS ACTIFS -->
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden mb-6">
            <div class="bg-slate-900/80 px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                    <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Participants actifs</h2>
                </div>
                <span class="text-xs font-bold text-slate-500"><?= $nb_actifs ?> joueur(s)</span>
            </div>

            <div class="grid grid-cols-12 gap-2 px-6 py-3 border-b border-slate-800 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                <div class="col-span-1 text-center">Seed</div>
                <div class="col-span-4">Participant</div>
                <div class="col-span-4">Nom d'affichage</div>
                <div class="col-span-3 text-right">Actions</div>
            </div>

            <div class="divide-y divide-slate-800/60 max-h-[500px] overflow-y-auto">
                <?php foreach ($participants_actifs as $p):
                    $affichage = $p['nom_affichage'] ?: $p['nom_participant'];
                ?>
                <div class="grid grid-cols-12 gap-2 px-6 py-3 items-center hover:bg-slate-800/30 transition group">
                    <div class="col-span-1 text-center">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded bg-slate-800 border border-slate-700 text-xs font-bold text-slate-200"><?= $p['seed'] ?></span>
                    </div>
                    <div class="col-span-4 flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500/30 to-fuchsia-500/30 border border-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-200 flex-shrink-0">
                            <?= strtoupper(substr($p['nom_participant'], 0, 1)) ?>
                        </div>
                        <span class="text-sm font-bold text-slate-100 truncate"><?= htmlspecialchars($p['nom_participant']) ?></span>
                    </div>
                    <div class="col-span-4">
                        <form action="participants_apres_lancement.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="flex gap-1">
                            <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                            <input type="hidden" name="action" value="modifier_affichage">
                            <input type="hidden" name="id_participant" value="<?= $p['id'] ?>">
                            <input type="text" name="nom_affichage" value="<?= htmlspecialchars($affichage) ?>" class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded px-2 py-1.5 text-xs outline-none transition">
                            <button type="submit" class="text-indigo-400 hover:text-indigo-300 p-1.5 rounded hover:bg-indigo-500/10 transition flex-shrink-0" title="Enregistrer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                        </form>
                    </div>
                    <div class="col-span-3 flex items-center justify-end gap-1">
                        <form action="participants_apres_lancement.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="inline">
                            <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                            <input type="hidden" name="action" value="forfait">
                            <input type="hidden" name="id_participant" value="<?= $p['id'] ?>">
                            <button type="submit" onclick="return confirm('Mettre en forfait ?');" class="text-slate-500 hover:text-amber-400 transition p-1.5 rounded hover:bg-amber-500/10" title="Forfait">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- FORFAITS -->
        <?php if (!empty($participants_forfaits)): ?>
        <div class="glass-card border border-red-500/20 rounded-xl overflow-hidden">
            <div class="bg-red-500/5 px-6 py-4 border-b border-red-500/20 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-4 bg-red-500 rounded"></div>
                    <h2 class="text-sm font-bold uppercase tracking-widest text-red-400">Forfaits</h2>
                </div>
                <span class="text-xs font-bold text-red-500/60"><?= $nb_forfaits ?> forfait(s)</span>
            </div>
            <div class="divide-y divide-slate-800/40">
                <?php foreach ($participants_forfaits as $p): ?>
                <div class="flex items-center justify-between px-6 py-3 opacity-60 hover:opacity-100 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center text-[10px] font-bold text-red-400">
                            <?= strtoupper(substr($p['nom_participant'], 0, 1)) ?>
                        </div>
                        <span class="text-sm font-bold text-slate-400 line-through"><?= htmlspecialchars($p['nom_affichage'] ?: $p['nom_participant']) ?></span>
                        <span class="text-[9px] font-bold text-red-400 bg-red-500/10 border border-red-500/30 px-1.5 py-0.5 rounded-full uppercase tracking-widest">Forfait</span>
                    </div>
                    <form action="participants_apres_lancement.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="inline">
                        <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                        <input type="hidden" name="action" value="reactiver">
                        <input type="hidden" name="id_participant" value="<?= $p['id'] ?>">
                        <button type="submit" class="text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 p-2 rounded transition" title="Reactiver">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/></svg>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
<?php include '_theme.php'; ?>
</body>
</html>
