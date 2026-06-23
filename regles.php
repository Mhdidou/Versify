<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) { header("Location: login.php"); die(); }
$hote_connecte = $_SESSION['id_utilisateur'];

if (!isset($_GET['id_tournoi'])) { header("Location: dashboard.php"); die(); }
$id_tournoi = (int) $_GET['id_tournoi'];

$message = '';
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/_helpers.php';

    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND hote = :hote");
    $stmt->execute([":id" => $id_tournoi, ":hote" => $hote_connecte]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tournoi) { header("Location: dashboard.php"); die(); }

    // Enregistrer le reglement (hote)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reglement'])) {
        $pdo->prepare("UPDATE tournoi SET reglement = :r WHERE id = :id AND hote = :hote")
            ->execute([":r" => trim($_POST['reglement']), ":id" => $id_tournoi, ":hote" => $hote_connecte]);
        $tournoi['reglement'] = trim($_POST['reglement']);
        $message = "Règlement enregistré.";
    }

} catch (PDOException $e) { error_log("DB error: " . $e->getMessage()); die("Une erreur est survenue. Veuillez reessayer plus tard."); }

$format = $tournoi['format'] ?? 'Single Elimination';
$best_of = (int) ($tournoi['best_of'] ?? 1);
$reglement = $tournoi['reglement'] ?? '';
$texte_libre = $reglement !== '' ? $reglement : ($tournoi['description'] ?? '');

// Faits derives automatiquement
$faits = [
    'Format' => $format,
    'Mode de matchs' => 'Best of ' . $best_of . ' (premier à ' . (int) ceil($best_of / 2) . ' manche·s)',
    'Participants max' => $tournoi['max_participants'] ? (int) $tournoi['max_participants'] : 'Illimité',
    'Date de départ' => (new DateTime($tournoi['date_depart']))->format('d/m/Y'),
];
if (!empty($tournoi['check_in_actif'])) {
    $faits['Check-in'] = 'Obligatoire — fenêtre de ' . (int) ($tournoi['check_in_limite_minutes'] ?? 30) . ' min';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rules — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
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
                <span class="text-sm text-slate-400 hidden sm:inline"><?= htmlspecialchars($hote_connecte) ?></span>
            </div>
        </div>
    </nav>

    <?php $tab_actif = 'rules'; include '_tabs.php'; ?>

    <main class="max-w-3xl mx-auto px-6 py-10">

        <h1 class="text-3xl font-bold italic mb-2">Règlement</h1>
        <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mb-8"><?= htmlspecialchars($tournoi['nom']) ?></p>

        <?php if ($message): ?>
        <div class="mb-6 p-3.5 rounded text-sm font-medium border bg-emerald-500/10 border-emerald-500/40 text-emerald-400"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Faits derives -->
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden mb-8">
            <div class="bg-slate-900/80 px-6 py-3 border-b border-slate-800 flex items-center gap-2">
                <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Format &amp; conditions</h2>
            </div>
            <div class="divide-y divide-slate-800/60">
                <?php foreach ($faits as $cle => $valeur): ?>
                <div class="flex items-center justify-between px-6 py-3">
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-500"><?= htmlspecialchars($cle) ?></span>
                    <span class="text-sm font-bold text-slate-200"><?= htmlspecialchars((string) $valeur) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reglement libre -->
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
            <div class="bg-slate-900/80 px-6 py-3 border-b border-slate-800 flex items-center gap-2">
                <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Ruleset</h2>
            </div>
            <div class="p-6">
                <form action="regles.php?id_tournoi=<?= $id_tournoi ?>" method="post">
                    <textarea name="reglement" rows="10" placeholder="Saisissez ici les règles détaillées du tournoi (comportement, formats de cartes, sanctions, etc.)."
                        class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition leading-relaxed"><?= htmlspecialchars($reglement) ?></textarea>
                    <?php if ($reglement === '' && !empty($tournoi['description'])): ?>
                    <p class="text-xs text-slate-500 mt-2">À défaut de règlement, la description du tournoi est affichée publiquement : « <?= htmlspecialchars($tournoi['description']) ?> »</p>
                    <?php endif; ?>
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-2.5 rounded text-sm font-bold uppercase tracking-widest transition shadow-lg shadow-indigo-500/20">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

<?php include '_theme.php'; ?>
</body>
</html>
