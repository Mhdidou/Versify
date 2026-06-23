<?php
session_start();

$id_tournoi = $_GET['id_tournoi'] ?? null;
if (!$id_tournoi) { header("Location: dashboard.php"); die(); }

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/_helpers.php';

    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id");
    $stmt->execute([":id" => $id_tournoi]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tournoi) { header("Location: dashboard.php"); die(); }

    $est_hote = (isset($_SESSION['id_utilisateur']) && $_SESSION['id_utilisateur'] === $tournoi['hote']);

    // Generer le classement si demande par l'organisateur
    if (isset($_POST['action']) && $_POST['action'] === 'calculer' && $est_hote) {
        calculerClassement($pdo, (int) $id_tournoi);
    }

    // Recuperer le classement
    $stmtC = $pdo->prepare("SELECT c.position_finale, p.nom_affichage, p.nom_participant FROM classement_tournoi c JOIN participant p ON p.id = c.id_participant WHERE c.id_tournoi = :id ORDER BY c.position_finale ASC");
    $stmtC->execute([":id" => $id_tournoi]);
    $classement = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    $jeu_info = getJeuInfo($pdo, $tournoi['jeu']);

} catch (PDOException $e) { error_log("DB error: " . $e->getMessage()); die("Une erreur est survenue. Veuillez reessayer plus tard."); }

$accent = $jeu_info['couleur_accent'] ?? '#6366f1';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; } .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }</style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen">
    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3"><div class="w-2 h-8 bg-indigo-500 rounded-full"></div><span class="text-xl font-bold uppercase tracking-widest">Versify</span></a>
            <a href="dashboard.php" class="text-sm text-slate-400 hover:text-white transition">Dashboard</a>
        </div>
    </nav>

    <?php $tab_actif = 'standings'; include '_tabs.php'; ?>

    <main class="max-w-3xl mx-auto px-6 py-10">
        <div class="text-center mb-10">
            <h1 class="text-3xl md:text-4xl font-bold italic">Classement Final</h1>
            <p class="text-sm uppercase tracking-widest mt-2" style="color: <?= $accent ?>"><?= htmlspecialchars($tournoi['nom']) ?> &middot; <?= htmlspecialchars($tournoi['jeu']) ?></p>
        </div>

        <?php if ($est_hote && empty($classement)): ?>
        <form action="classement.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="mb-8 text-center">
            <input type="hidden" name="action" value="calculer">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3 rounded text-sm font-bold uppercase tracking-widest transition shadow-lg shadow-indigo-500/20">
                Calculer le classement final
            </button>
            <p class="text-xs text-slate-500 mt-2">Cela terminera officiellement le tournoi.</p>
        </form>
        <?php endif; ?>

        <?php if (!empty($classement)): ?>
        <!-- Podium visuel -->
        <div class="glass-card border border-slate-800 rounded-2xl p-8 mb-8">
            <div class="flex items-end justify-center gap-6 mb-6" style="min-height: 200px;">
                <!-- 2eme -->
                <?php if (isset($classement[1])): ?>
                <div class="text-center flex flex-col items-center">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-slate-400 to-slate-600 flex items-center justify-center text-xl font-bold text-white mb-2 shadow-lg">
                        <?= strtoupper(substr($classement[1]['nom_affichage'] ?: $classement[1]['nom_participant'], 0, 1)) ?>
                    </div>
                    <div class="w-20 h-28 bg-gradient-to-t from-slate-700 to-slate-600 rounded-t-lg flex items-center justify-center">
                        <span class="text-3xl">🥈</span>
                    </div>
                    <p class="text-xs font-bold mt-2 truncate w-24"><?= htmlspecialchars($classement[1]['nom_affichage'] ?: $classement[1]['nom_participant']) ?></p>
                    <p class="text-[10px] text-slate-500">2ème</p>
                </div>
                <?php endif; ?>

                <!-- 1er (Champion) -->
                <?php if (isset($classement[0])): ?>
                <div class="text-center flex flex-col items-center">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-2xl font-bold text-white mb-2 shadow-lg shadow-amber-500/30 ring-2 ring-amber-400/50">
                        <?= strtoupper(substr($classement[0]['nom_affichage'] ?: $classement[0]['nom_participant'], 0, 1)) ?>
                    </div>
                    <div class="w-24 h-40 bg-gradient-to-t from-amber-600/30 to-amber-500/20 border border-amber-500/40 rounded-t-lg flex items-center justify-center">
                        <span class="text-4xl">🏆</span>
                    </div>
                    <p class="text-sm font-bold mt-2 text-amber-400 truncate w-28"><?= htmlspecialchars($classement[0]['nom_affichage'] ?: $classement[0]['nom_participant']) ?></p>
                    <p class="text-[10px] text-amber-500 font-bold uppercase tracking-widest">Champion</p>
                </div>
                <?php endif; ?>

                <!-- 3eme -->
                <?php if (isset($classement[2])): ?>
                <div class="text-center flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-700 to-orange-900 flex items-center justify-center text-lg font-bold text-white mb-2 shadow-lg">
                        <?= strtoupper(substr($classement[2]['nom_affichage'] ?: $classement[2]['nom_participant'], 0, 1)) ?>
                    </div>
                    <div class="w-20 h-20 bg-gradient-to-t from-orange-900/40 to-orange-800/20 rounded-t-lg flex items-center justify-center">
                        <span class="text-2xl">🥉</span>
                    </div>
                    <p class="text-xs font-bold mt-2 truncate w-24"><?= htmlspecialchars($classement[2]['nom_affichage'] ?: $classement[2]['nom_participant']) ?></p>
                    <p class="text-[10px] text-slate-500">3ème</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Liste complete -->
        <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
            <div class="bg-slate-900/80 px-6 py-3 border-b border-slate-800">
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Classement complet</h2>
            </div>
            <div class="divide-y divide-slate-800/60">
                <?php foreach ($classement as $c):
                    $pos = $c['position_finale'];
                    $nom = $c['nom_affichage'] ?: $c['nom_participant'];
                    $pos_color = match(true) {
                        $pos === 1 => 'text-amber-400',
                        $pos === 2 => 'text-slate-300',
                        $pos <= 4 => 'text-orange-600',
                        default => 'text-slate-500'
                    };
                ?>
                <div class="flex items-center gap-4 px-6 py-3 hover:bg-slate-800/30 transition">
                    <span class="w-8 text-center text-sm font-bold <?= $pos_color ?>">#<?= $pos ?></span>
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500/30 to-fuchsia-500/30 border border-slate-700 flex items-center justify-center text-[10px] font-bold">
                        <?= strtoupper(substr($nom, 0, 1)) ?>
                    </div>
                    <span class="text-sm font-bold"><?= htmlspecialchars($nom) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif (!$est_hote): ?>
        <div class="glass-card border border-slate-800 rounded-xl p-12 text-center text-slate-500">
            Le classement n'a pas encore ete calcule.
        </div>
        <?php endif; ?>
    </main>
<?php include '_theme.php'; ?>
</body>
</html>
