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

    // Soumettre une reclamation
    if (isset($_POST['action']) && $_POST['action'] === 'soumettre') {
        $id_match = (int) $_POST['id_match'];
        $motif = trim($_POST['motif'] ?? '');

        if (!empty($motif)) {
            $pdo->prepare("INSERT INTO reclamation (id_match, id_tournoi, id_reclamant, motif) VALUES (:m, :t, :u, :motif)")
                ->execute([":m" => $id_match, ":t" => $id_tournoi, ":u" => $utilisateur, ":motif" => $motif]);

            try {
                $pdo->prepare("INSERT INTO notification (id_destinataire, type_notif, titre, message, lien) VALUES (:dest, 'match', :titre, :msg, :lien)")
                    ->execute([":dest" => $tournoi['hote'], ":titre" => "Reclamation", ":msg" => "$utilisateur conteste le match #$id_match.", ":lien" => "reclamation.php?id_tournoi=$id_tournoi"]);
            } catch (Exception $e) {}

            $message = "Reclamation soumise. L'organisateur sera notifie.";
            $message_type = 'success';
        }
    }

    // Organisateur: accepter/rejeter
    if (isset($_POST['action']) && $_POST['action'] === 'traiter' && $est_hote) {
        $id_recl = (int) $_POST['id_reclamation'];
        $decision = $_POST['decision']; // 'acceptee' ou 'rejetee'
        $reponse = trim($_POST['reponse'] ?? '');

        $pdo->prepare("UPDATE reclamation SET statut_reclamation = :st, reponse_organisateur = :rep, date_resolution = NOW() WHERE id = :id AND id_tournoi = :idt")
            ->execute([":st" => $decision, ":rep" => $reponse ?: null, ":id" => $id_recl, ":idt" => $id_tournoi]);

        // Notifier le reclamant
        $stmtR = $pdo->prepare("SELECT id_reclamant FROM reclamation WHERE id = :id");
        $stmtR->execute([":id" => $id_recl]);
        $reclamant = $stmtR->fetchColumn();

        try {
            $pdo->prepare("INSERT INTO notification (id_destinataire, type_notif, titre, message) VALUES (:dest, 'match', :titre, :msg)")
                ->execute([":dest" => $reclamant, ":titre" => "Reclamation " . $decision, ":msg" => "Votre reclamation a ete " . $decision . "." . ($reponse ? " Reponse: $reponse" : "")]);
        } catch (Exception $e) {}
        $message = "Reclamation traitee.";
        $message_type = 'success';
    }

    // Liste des reclamations
    $stmtList = $pdo->prepare("SELECT * FROM reclamation WHERE id_tournoi = :id ORDER BY FIELD(statut_reclamation, 'ouverte', 'acceptee', 'rejetee'), date_creation DESC");
    $stmtList->execute([":id" => $id_tournoi]);
    $reclamations = $stmtList->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { error_log("DB error: " . $e->getMessage()); die("Une erreur est survenue. Veuillez reessayer plus tard."); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamations — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
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

    <main class="max-w-4xl mx-auto px-6 py-10">
        <h1 class="text-3xl font-bold italic mb-2">Reclamations</h1>
        <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mb-8"><?= htmlspecialchars($tournoi['nom']) ?></p>

        <?php if ($message): ?>
        <div class="mb-6 p-3.5 rounded text-sm font-medium border <?= $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-red-500/10 border-red-500/40 text-red-400' ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- Formulaire de soumission (pour les joueurs) -->
        <?php if (!$est_hote): ?>
        <div class="glass-card border border-slate-800 rounded-xl p-6 mb-8">
            <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300 mb-4">Soumettre une reclamation</h2>
            <form action="reclamation.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="space-y-4">
                <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                <input type="hidden" name="action" value="soumettre">
                <div>
                    <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Numero du match</label>
                    <input type="number" name="id_match" min="1" required class="w-full bg-slate-900 border border-slate-700 rounded px-4 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Motif de la reclamation</label>
                    <textarea name="motif" rows="4" required placeholder="Decrivez le probleme..." class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-sm outline-none focus:border-indigo-500 transition"></textarea>
                </div>
                <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white py-2.5 px-6 rounded text-xs font-bold uppercase tracking-widest transition">Soumettre</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Liste des reclamations -->
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
            <?php if (empty($reclamations)): ?>
                <div class="p-12 text-center text-slate-500">Aucune reclamation.</div>
            <?php else: ?>
                <div class="divide-y divide-slate-800/60">
                    <?php foreach ($reclamations as $r):
                        $badge = match($r['statut_reclamation']) {
                            'ouverte' => 'bg-amber-500/15 text-amber-400 border-amber-500/40',
                            'acceptee' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40',
                            'rejetee' => 'bg-red-500/15 text-red-400 border-red-500/40',
                        };
                    ?>
                    <div class="px-6 py-4">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-sm font-bold"><?= htmlspecialchars($r['id_reclamant']) ?></span>
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full border <?= $badge ?>"><?= $r['statut_reclamation'] ?></span>
                            <span class="text-xs text-slate-500">Match #<?= $r['id_match'] ?></span>
                        </div>
                        <p class="text-sm text-slate-300 mb-2"><?= htmlspecialchars($r['motif']) ?></p>
                        <?php if ($r['reponse_organisateur']): ?>
                            <p class="text-xs text-slate-400 italic">Reponse: <?= htmlspecialchars($r['reponse_organisateur']) ?></p>
                        <?php endif; ?>

                        <?php if ($est_hote && $r['statut_reclamation'] === 'ouverte'): ?>
                        <form action="reclamation.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="mt-3 flex flex-wrap gap-2 items-end">
                            <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                            <input type="hidden" name="action" value="traiter">
                            <input type="hidden" name="id_reclamation" value="<?= $r['id'] ?>">
                            <input type="text" name="reponse" placeholder="Reponse (optionnel)" class="bg-slate-900 border border-slate-700 rounded px-3 py-1.5 text-xs outline-none flex-grow">
                            <button type="submit" name="decision" value="acceptee" class="text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 px-3 py-1.5 rounded">Accepter</button>
                            <button type="submit" name="decision" value="rejetee" class="text-xs font-bold bg-red-500/20 text-red-400 border border-red-500/40 px-3 py-1.5 rounded">Rejeter</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
<?php include '_theme.php'; ?>
</body>
</html>
