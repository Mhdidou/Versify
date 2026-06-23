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

    // Verifier propriete
    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND hote = :hote");
    $stmt->execute([":id" => $id_tournoi, ":hote" => $hote_connecte]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tournoi) { header("Location: dashboard.php"); die(); }

    // Accepter une inscription
    if (isset($_POST['action']) && $_POST['action'] === 'accepter') {
        $id_inscription = (int) $_POST['id_inscription'];
        $stmtI = $pdo->prepare("SELECT * FROM inscription_tournoi WHERE id = :id AND id_tournoi = :idt");
        $stmtI->execute([":id" => $id_inscription, ":idt" => $id_tournoi]);
        $inscription = $stmtI->fetch(PDO::FETCH_ASSOC);

        if ($inscription) {
            // Ajouter comme participant
            $stmtSeed = $pdo->prepare("SELECT COALESCE(MAX(seed), 0) + 1 FROM participant WHERE id_tournoi = :id AND statut = 'actif'");
            $stmtSeed->execute([":id" => $id_tournoi]);
            $seed = (int) $stmtSeed->fetchColumn();

            $pdo->prepare("INSERT INTO participant (id_tournoi, nom_participant, nom_affichage, seed, statut) VALUES (:idt, :nom, :nom, :seed, 'actif')")
                ->execute([":idt" => $id_tournoi, ":nom" => $inscription['id_utilisateur'], ":seed" => $seed]);

            $pdo->prepare("UPDATE inscription_tournoi SET statut_inscription = 'accepte' WHERE id = :id")
                ->execute([":id" => $id_inscription]);

            // Notifier le joueur
            try {
                $pdo->prepare("INSERT INTO notification (id_destinataire, type_notif, titre, message) VALUES (:dest, 'tournoi', :titre, :msg)")
                    ->execute([":dest" => $inscription['id_utilisateur'], ":titre" => "Inscription acceptee", ":msg" => "Votre inscription au tournoi \"" . $tournoi['nom'] . "\" a ete acceptee."]);
            } catch (Exception $e) {}
            $message = "Inscription acceptee.";
            $message_type = 'success';
        }
    }

    // Refuser
    if (isset($_POST['action']) && $_POST['action'] === 'refuser') {
        $id_inscription = (int) $_POST['id_inscription'];
        $pdo->prepare("UPDATE inscription_tournoi SET statut_inscription = 'refuse' WHERE id = :id AND id_tournoi = :idt")
            ->execute([":id" => $id_inscription, ":idt" => $id_tournoi]);
        $message = "Inscription refusee.";
        $message_type = 'success';
    }

    // Recuperer les inscriptions en attente
    $stmtList = $pdo->prepare("SELECT * FROM inscription_tournoi WHERE id_tournoi = :id ORDER BY FIELD(statut_inscription, 'en_attente', 'accepte', 'refuse'), date_demande DESC");
    $stmtList->execute([":id" => $id_tournoi]);
    $inscriptions = $stmtList->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("DB error: " . $e->getMessage()); die("Une erreur est survenue. Veuillez reessayer plus tard.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscriptions — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; } .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }</style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen">
    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3"><div class="w-2 h-8 bg-indigo-500 rounded-full"></div><span class="text-xl font-bold uppercase tracking-widest">Versify</span></a>
            <a href="participants.php?id_tournoi=<?= $id_tournoi ?>" class="text-sm text-slate-400 hover:text-white transition">← Participants</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-6 py-10">
        <h1 class="text-3xl font-bold italic mb-2">Demandes d'inscription</h1>
        <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mb-8"><?= htmlspecialchars($tournoi['nom']) ?></p>

        <?php if ($message): ?>
        <div class="mb-6 flex items-center gap-3 <?= $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-red-500/10 border-red-500/40 text-red-400' ?> border p-3.5 rounded text-sm font-medium">
            <?= $message ?>
        </div>
        <?php endif; ?>

        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
            <?php if (empty($inscriptions)): ?>
                <div class="p-12 text-center text-slate-500">Aucune demande d'inscription.</div>
            <?php else: ?>
                <div class="divide-y divide-slate-800/60">
                    <?php foreach ($inscriptions as $i):
                        $badge_class = match($i['statut_inscription']) {
                            'en_attente' => 'bg-amber-500/15 text-amber-400 border-amber-500/40',
                            'accepte' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40',
                            'refuse' => 'bg-red-500/15 text-red-400 border-red-500/40',
                        };
                    ?>
                    <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <span class="text-sm font-bold"><?= htmlspecialchars($i['id_utilisateur']) ?></span>
                            <span class="ml-2 text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full border <?= $badge_class ?>"><?= $i['statut_inscription'] ?></span>
                            <?php if ($i['message']): ?>
                                <p class="text-xs text-slate-400 mt-1">"<?= htmlspecialchars($i['message']) ?>"</p>
                            <?php endif; ?>
                            <p class="text-xs text-slate-500 mt-1"><?= $i['date_demande'] ?></p>
                        </div>
                        <?php if ($i['statut_inscription'] === 'en_attente'): ?>
                        <div class="flex gap-2">
                            <form action="gerer_inscriptions.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="inline">
                                <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                                <input type="hidden" name="action" value="accepter">
                                <input type="hidden" name="id_inscription" value="<?= $i['id'] ?>">
                                <button class="text-xs font-bold bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 border border-emerald-500/40 px-3 py-1.5 rounded transition">Accepter</button>
                            </form>
                            <form action="gerer_inscriptions.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="inline">
                                <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                                <input type="hidden" name="action" value="refuser">
                                <input type="hidden" name="id_inscription" value="<?= $i['id'] ?>">
                                <button class="text-xs font-bold bg-red-500/20 hover:bg-red-500/30 text-red-400 border border-red-500/40 px-3 py-1.5 rounded transition">Refuser</button>
                            </form>
                        </div>
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
