<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    die();
}

$hote_connecte = $_SESSION['id_utilisateur'];
$message = '';
$message_type = '';

if (!isset($_GET['id_tournoi']) && !isset($_POST['id_tournoi'])) {
    header("Location: dashboard.php");
    die();
}

$id_tournoi = $_GET['id_tournoi'] ?? $_POST['id_tournoi'];

try {
    require_once __DIR__ . '/config.php';

    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND hote = :hote");
    $stmt->execute([":id" => $id_tournoi, ":hote" => $hote_connecte]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournoi) {
        header("Location: dashboard.php");
        die();
    }

    // Si le tournoi est en cours ou termine, rediriger vers la page participants post-lancement
    if (isset($tournoi['statut_tournoi']) && in_array($tournoi['statut_tournoi'], ['en_cours', 'termine'])) {
        header("Location: participants_apres_lancement.php?id_tournoi=" . $id_tournoi);
        die();
    }

    // ===== ACTIONS =====

    // Ajout individuel
    if (isset($_POST['action']) && $_POST['action'] === 'ajouter_individuel') {
        $nom = trim($_POST['nom_participant'] ?? '');
        if (!empty($nom)) {
            // Recherche dans la table utilisateur (par id_utilisateur OU email)
            $stmtCheck = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = :nom OR email = :nom");
            $stmtCheck->execute([":nom" => $nom]);
            $utilisateur_trouve = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$utilisateur_trouve) {
                $message = "Aucun utilisateur trouve avec l'identifiant \"" . htmlspecialchars($nom) . "\". Le joueur doit avoir un compte sur la plateforme.";
                $message_type = 'error';
            } else {
                $nom_final = $utilisateur_trouve['id_utilisateur'];

                // Verifier doublon
                $stmtDup = $pdo->prepare("SELECT id FROM participant WHERE id_tournoi = :id_tournoi AND nom_participant = :nom");
                $stmtDup->execute([":id_tournoi" => $id_tournoi, ":nom" => $nom_final]);

                if ($stmtDup->fetch()) {
                    $message = "Ce participant est deja inscrit dans ce tournoi.";
                    $message_type = 'error';
                } else {
                    $stmtSeed = $pdo->prepare("SELECT COALESCE(MAX(seed), 0) + 1 FROM participant WHERE id_tournoi = :id_tournoi AND statut = 'actif'");
                    $stmtSeed->execute([":id_tournoi" => $id_tournoi]);
                    $prochain_seed = (int) $stmtSeed->fetchColumn();

                    if ($tournoi['max_participants'] && $prochain_seed > $tournoi['max_participants']) {
                        $message = "Limite de " . $tournoi['max_participants'] . " participants atteinte.";
                        $message_type = 'error';
                    } else {
                        $stmtInsert = $pdo->prepare("INSERT INTO participant (id_tournoi, nom_participant, nom_affichage, seed, statut) VALUES (:id_tournoi, :nom, :nom_affichage, :seed, 'actif')");
                        $stmtInsert->execute([":id_tournoi" => $id_tournoi, ":nom" => $nom_final, ":nom_affichage" => $nom_final, ":seed" => $prochain_seed]);

                        $message = "Participant \"" . htmlspecialchars($nom_final) . "\" ajoute (compte verifie ✓)";
                        $message_type = 'success';
                    }
                }
            }
        }
    }

    // Ajout en masse
    if (isset($_POST['action']) && $_POST['action'] === 'ajouter_masse') {
        $liste_brute = trim($_POST['liste_participants'] ?? '');
        if (!empty($liste_brute)) {
            $lignes = array_filter(array_map('trim', explode("\n", $liste_brute)));
            $ajoutes = 0;
            $doublons = 0;

            $stmtSeed = $pdo->prepare("SELECT COALESCE(MAX(seed), 0) FROM participant WHERE id_tournoi = :id_tournoi AND statut = 'actif'");
            $stmtSeed->execute([":id_tournoi" => $id_tournoi]);
            $seed_actuel = (int) $stmtSeed->fetchColumn();

            foreach ($lignes as $ligne) {
                if (empty($ligne)) continue;

                $stmtDup = $pdo->prepare("SELECT id FROM participant WHERE id_tournoi = :id_tournoi AND nom_participant = :nom");
                $stmtDup->execute([":id_tournoi" => $id_tournoi, ":nom" => $ligne]);

                if ($stmtDup->fetch()) { $doublons++; continue; }

                $seed_actuel++;
                if ($tournoi['max_participants'] && $seed_actuel > $tournoi['max_participants']) {
                    $message = "$ajoutes ajoute(s). Limite de " . $tournoi['max_participants'] . " atteinte ($doublons doublon(s)).";
                    $message_type = 'error';
                    break;
                }

                $stmtInsert = $pdo->prepare("INSERT INTO participant (id_tournoi, nom_participant, nom_affichage, seed, statut) VALUES (:id_tournoi, :nom, :nom_affichage, :seed, 'actif')");
                $stmtInsert->execute([":id_tournoi" => $id_tournoi, ":nom" => $ligne, ":nom_affichage" => $ligne, ":seed" => $seed_actuel]);
                $ajoutes++;
            }

            if (empty($message)) {
                $message = "$ajoutes participant(s) ajoute(s).";
                if ($doublons > 0) $message .= " ($doublons doublon(s) ignore(s))";
                $message_type = 'success';
            }
        }
    }

    // Forfait (desactiver)
    if (isset($_POST['action']) && $_POST['action'] === 'forfait') {
        $id_participant = $_POST['id_participant'] ?? 0;
        $pdo->prepare("UPDATE participant SET statut = 'forfait', seed = NULL WHERE id = :id AND id_tournoi = :id_tournoi")
            ->execute([":id" => $id_participant, ":id_tournoi" => $id_tournoi]);

        // Recalculer les seeds des actifs
        $stmtAll = $pdo->prepare("SELECT id FROM participant WHERE id_tournoi = :id_tournoi AND statut = 'actif' ORDER BY seed ASC");
        $stmtAll->execute([":id_tournoi" => $id_tournoi]);
        $s = 1;
        foreach ($stmtAll->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $pdo->prepare("UPDATE participant SET seed = :seed WHERE id = :id")->execute([":seed" => $s, ":id" => $p['id']]);
            $s++;
        }
        $message = "Participant mis en forfait.";
        $message_type = 'success';
    }

    // Supprimer definitivement
    if (isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        $id_participant = $_POST['id_participant'] ?? 0;
        $pdo->prepare("DELETE FROM participant WHERE id = :id AND id_tournoi = :id_tournoi")
            ->execute([":id" => $id_participant, ":id_tournoi" => $id_tournoi]);

        // Recalculer les seeds des actifs
        $stmtAll = $pdo->prepare("SELECT id FROM participant WHERE id_tournoi = :id_tournoi AND statut = 'actif' ORDER BY seed ASC");
        $stmtAll->execute([":id_tournoi" => $id_tournoi]);
        $s = 1;
        foreach ($stmtAll->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $pdo->prepare("UPDATE participant SET seed = :seed WHERE id = :id")->execute([":seed" => $s, ":id" => $p['id']]);
            $s++;
        }
        $message = "Participant supprime definitivement.";
        $message_type = 'success';
    }

    // Reactiver
    if (isset($_POST['action']) && $_POST['action'] === 'reactiver') {
        $id_participant = $_POST['id_participant'] ?? 0;

        // Prochain seed
        $stmtSeed = $pdo->prepare("SELECT COALESCE(MAX(seed), 0) + 1 FROM participant WHERE id_tournoi = :id_tournoi AND statut = 'actif'");
        $stmtSeed->execute([":id_tournoi" => $id_tournoi]);
        $prochain_seed = (int) $stmtSeed->fetchColumn();

        $pdo->prepare("UPDATE participant SET statut = 'actif', seed = :seed WHERE id = :id AND id_tournoi = :id_tournoi")
            ->execute([":seed" => $prochain_seed, ":id" => $id_participant, ":id_tournoi" => $id_tournoi]);

        $message = "Participant reactive.";
        $message_type = 'success';
    }

    // Modifier nom d'affichage
    if (isset($_POST['action']) && $_POST['action'] === 'modifier_affichage') {
        $id_participant = $_POST['id_participant'] ?? 0;
        $nouveau_nom = trim($_POST['nom_affichage'] ?? '');
        if (!empty($nouveau_nom)) {
            $pdo->prepare("UPDATE participant SET nom_affichage = :nom WHERE id = :id AND id_tournoi = :id_tournoi")
                ->execute([":nom" => $nouveau_nom, ":id" => $id_participant, ":id_tournoi" => $id_tournoi]);
            $message = "Nom d'affichage modifie.";
            $message_type = 'success';
        }
    }

    // Melanger les seeds
    if (isset($_POST['action']) && $_POST['action'] === 'melanger') {
        $stmtAll = $pdo->prepare("SELECT id FROM participant WHERE id_tournoi = :id_tournoi AND statut = 'actif'");
        $stmtAll->execute([":id_tournoi" => $id_tournoi]);
        $tous = $stmtAll->fetchAll(PDO::FETCH_COLUMN);
        shuffle($tous);
        $s = 1;
        foreach ($tous as $pid) {
            $pdo->prepare("UPDATE participant SET seed = :seed WHERE id = :id")->execute([":seed" => $s, ":id" => $pid]);
            $s++;
        }
        $message = "Seeds melanges aleatoirement.";
        $message_type = 'success';
    }

    // Recuperer les participants
    $stmtActifs = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id_tournoi AND statut = 'actif' ORDER BY seed ASC");
    $stmtActifs->execute([":id_tournoi" => $id_tournoi]);
    $participants_actifs = $stmtActifs->fetchAll(PDO::FETCH_ASSOC);

    $stmtForfaits = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id_tournoi AND statut = 'forfait' ORDER BY nom_participant ASC");
    $stmtForfaits->execute([":id_tournoi" => $id_tournoi]);
    $participants_forfaits = $stmtForfaits->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = $e->getMessage();
    $message_type = 'error';
    $tournoi = $tournoi ?? null;
    $participants_actifs = [];
    $participants_forfaits = [];
}

$nb_actifs = count($participants_actifs);
$nb_forfaits = count($participants_forfaits);
$max_p = $tournoi['max_participants'] ?: '&#8734;';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participants — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <div class="flex items-center gap-4">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500 flex items-center justify-center font-bold text-white text-sm">
                    <?= strtoupper(substr($hote_connecte, 0, 1)) ?>
                </div>
                <span class="text-sm font-medium text-slate-400 hidden sm:inline"><?= htmlspecialchars($hote_connecte) ?></span>
                <a href="logout.php" class="text-sm font-bold text-red-500 hover:text-red-400 transition">Deconnexion</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-6xl mx-auto w-full px-6 py-10 relative">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-[120px] pointer-events-none"></div>

        <a href="dashboard.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-indigo-400 uppercase tracking-widest transition mb-6">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Retour au dashboard
        </a>

        <!-- En-tete tournoi -->
        <div class="mb-8 relative">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold tracking-tight italic"><?= htmlspecialchars($tournoi['nom']) ?></h1>
                    <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mt-1"><?= htmlspecialchars($tournoi['jeu']) ?> &middot; <?= htmlspecialchars($tournoi['format']) ?></p>
                    <div class="w-16 h-1 bg-indigo-500 mt-3 rounded-full"></div>
                </div>
                <div class="glass-card border border-slate-800 rounded-xl px-5 py-3 text-center">
                    <div class="text-2xl font-bold text-indigo-400"><?= $nb_actifs ?><span class="text-slate-500 text-lg"> / <?= $max_p ?></span></div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-1">Participants actifs</div>
                </div>
            </div>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
        <div class="mb-6 flex items-start gap-3 <?= $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-red-500/10 border-red-500/40 text-red-400' ?> border p-3.5 rounded text-sm">
            <?php if ($message_type === 'success'): ?>
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <?php else: ?>
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php endif; ?>
            <span class="font-medium"><?= $message ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- COLONNE GAUCHE -->
            <div class="lg:col-span-1 space-y-6">

                <!-- Ajout individuel -->
                <div class="glass-card border border-slate-800 rounded-xl p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                        <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Ajouter un participant</h2>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">Recherche par nom d'utilisateur. Si le joueur a un compte, il sera verifie.</p>
                    <form action="participants.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="space-y-3">
                        <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                        <input type="hidden" name="action" value="ajouter_individuel">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <input type="text" name="nom_participant" placeholder="Nom d'utilisateur ou pseudo" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded pl-10 pr-4 py-2.5 text-sm outline-none transition" required>
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 rounded text-xs font-bold uppercase tracking-widest transition shadow-lg shadow-indigo-500/20 flex items-center justify-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Ajouter
                        </button>
                    </form>
                </div>

                <!-- Ajout en masse -->
                <div class="glass-card border border-slate-800 rounded-xl p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                        <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Ajout en masse</h2>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">Un participant par ligne. Les doublons seront ignores.</p>
                    <form action="participants.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="space-y-3">
                        <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                        <input type="hidden" name="action" value="ajouter_masse">
                        <textarea name="liste_participants" rows="6" placeholder="Karmine Corp&#10;Team Vitality&#10;Fnatic&#10;G2 Esports" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded p-3 text-sm outline-none transition font-mono" required></textarea>
                        <button type="submit" class="w-full bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-indigo-500 text-white py-2.5 rounded text-xs font-bold uppercase tracking-widest transition flex items-center justify-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Importer la liste
                        </button>
                    </form>
                </div>

                <!-- Melanger les seeds -->
                <?php if ($nb_actifs > 1): ?>
                <form action="participants.php?id_tournoi=<?= $id_tournoi ?>" method="post">
                    <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                    <input type="hidden" name="action" value="melanger">
                    <button type="submit" class="w-full glass-card border border-slate-800 hover:border-amber-500/50 rounded-xl p-4 text-sm font-bold text-amber-400 hover:text-amber-300 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Melanger les seeds
                    </button>
                </form>
                <?php endif; ?>

                <!-- Lancer le tournoi -->
                <?php if ($nb_actifs >= 2): ?>
                <a href="bracket_live.php?id_tournoi=<?= $id_tournoi ?>" class="w-full glass-card border border-emerald-500/30 hover:border-emerald-500/60 rounded-xl p-4 text-sm font-bold text-emerald-400 hover:text-emerald-300 transition flex items-center justify-center gap-2 mt-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Lancer le tournoi / Voir le bracket
                </a>
                <?php endif; ?>

                <!-- Check-in -->
                <a href="check_in.php?id_tournoi=<?= $id_tournoi ?>" class="w-full glass-card border border-slate-800 hover:border-indigo-500/50 rounded-xl p-3 text-xs font-bold text-slate-300 hover:text-indigo-400 transition flex items-center justify-center gap-2 mt-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Check-in des joueurs
                </a>

                <!-- Lien de partage -->
                <?php
                $lien_partage = null;
                try {
                    $lien_partage = $tournoi['lien_partage'] ?? null;
                    if (!$lien_partage) {
                        require_once __DIR__ . '/_helpers.php';
                        $lien_partage = genererLienPartage($pdo, (int) $id_tournoi);
                    }
                } catch (Exception $e) {
                    $lien_partage = null; // colonne pas encore ajoutee
                }
                ?>
                <?php if ($lien_partage): ?>
                <div class="glass-card border border-slate-800 rounded-xl p-4 mt-2">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Lien public</p>
                    <div class="flex gap-1">
                        <input type="text" value="tournoi_public.php?code=<?= $lien_partage ?>" class="w-full bg-slate-900 border border-slate-700 rounded px-2 py-1.5 text-[10px] text-slate-300 outline-none" readonly id="share-link">
                        <button onclick="navigator.clipboard.writeText(document.getElementById('share-link').value); this.textContent='OK'" class="text-[10px] font-bold bg-indigo-500/20 text-indigo-400 border border-indigo-500/40 px-2 py-1 rounded">Copier</button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Inscriptions -->
                <a href="gerer_inscriptions.php?id_tournoi=<?= $id_tournoi ?>" class="w-full glass-card border border-slate-800 hover:border-indigo-500/50 rounded-xl p-3 text-xs font-bold text-slate-300 hover:text-indigo-400 transition flex items-center justify-center gap-2 mt-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Gerer les inscriptions
                </a>
            </div>

            <!-- COLONNE DROITE -->
            <div class="lg:col-span-2 space-y-6">

                <!-- LISTE ACTIFS -->
                <div class="glass-card border border-slate-800 rounded-xl overflow-hidden">
                    <div class="bg-slate-900/80 px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-1 h-4 bg-indigo-500 rounded"></div>
                            <h2 class="text-sm font-bold uppercase tracking-widest text-slate-300">Participants actifs</h2>
                        </div>
                        <span class="text-xs font-bold text-slate-500"><?= $nb_actifs ?> inscrit(s)</span>
                    </div>

                    <?php if (empty($participants_actifs)): ?>
                        <div class="p-12 text-center">
                            <svg class="w-16 h-16 mx-auto mb-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <p class="text-slate-500 text-sm mb-2">Aucun participant actif.</p>
                            <p class="text-slate-600 text-xs">Utilisez les formulaires pour ajouter des joueurs.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-12 gap-2 px-6 py-3 border-b border-slate-800 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                            <div class="col-span-1 text-center">Seed</div>
                            <div class="col-span-4">Participant</div>
                            <div class="col-span-4">Nom d'affichage</div>
                            <div class="col-span-3 text-right">Actions</div>
                        </div>

                        <div class="divide-y divide-slate-800/60 max-h-[450px] overflow-y-auto">
                            <?php foreach ($participants_actifs as $p):
                                $stmtVerif = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = :nom");
                                $stmtVerif->execute([":nom" => $p['nom_participant']]);
                                $est_verifie = $stmtVerif->fetch() ? true : false;
                                $affichage = $p['nom_affichage'] ?: $p['nom_participant'];
                            ?>
                            <div class="grid grid-cols-12 gap-2 px-6 py-3 items-center hover:bg-slate-800/30 transition group">
                                <!-- Seed -->
                                <div class="col-span-1 text-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded bg-slate-800 border border-slate-700 text-xs font-bold text-slate-200 group-hover:border-indigo-500/50 transition">
                                        <?= $p['seed'] ?>
                                    </span>
                                </div>
                                <!-- Nom participant -->
                                <div class="col-span-4 flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500/30 to-fuchsia-500/30 border border-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-200 flex-shrink-0">
                                        <?= strtoupper(substr($p['nom_participant'], 0, 1)) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-sm font-bold text-slate-100 truncate block"><?= htmlspecialchars($p['nom_participant']) ?></span>
                                        <?php if ($est_verifie): ?>
                                            <span class="text-[9px] font-bold text-emerald-400 uppercase tracking-widest">Verifie</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- Nom affichage (editable) -->
                                <div class="col-span-4">
                                    <form action="participants.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="flex gap-1">
                                        <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                                        <input type="hidden" name="action" value="modifier_affichage">
                                        <input type="hidden" name="id_participant" value="<?= $p['id'] ?>">
                                        <input type="text" name="nom_affichage" value="<?= htmlspecialchars($affichage) ?>" class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded px-2 py-1.5 text-xs outline-none transition">
                                        <button type="submit" class="text-indigo-400 hover:text-indigo-300 p-1.5 rounded hover:bg-indigo-500/10 transition flex-shrink-0" title="Enregistrer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </form>
                                </div>
                                <!-- Actions -->
                                <div class="col-span-3 flex items-center justify-end gap-1">
                                    <!-- Forfait -->
                                    <form action="participants.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="inline">
                                        <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                                        <input type="hidden" name="action" value="forfait">
                                        <input type="hidden" name="id_participant" value="<?= $p['id'] ?>">
                                        <button type="submit" onclick="return confirm('Mettre ce participant en forfait ?');" class="text-slate-500 hover:text-amber-400 transition p-1.5 rounded hover:bg-amber-500/10" title="Forfait">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        </button>
                                    </form>
                                    <!-- Supprimer -->
                                    <form action="participants.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="inline">
                                        <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                                        <input type="hidden" name="action" value="supprimer">
                                        <input type="hidden" name="id_participant" value="<?= $p['id'] ?>">
                                        <button type="submit" onclick="return confirm('Supprimer definitivement ce participant ?');" class="text-slate-500 hover:text-red-400 transition p-1.5 rounded hover:bg-red-500/10" title="Supprimer">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- LISTE FORFAITS -->
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
                                <div>
                                    <span class="text-sm font-bold text-slate-400 line-through"><?= htmlspecialchars($p['nom_affichage'] ?: $p['nom_participant']) ?></span>
                                    <span class="ml-2 text-[9px] font-bold text-red-400 bg-red-500/10 border border-red-500/30 px-1.5 py-0.5 rounded-full uppercase tracking-widest">Forfait</span>
                                </div>
                            </div>
                            <form action="participants.php?id_tournoi=<?= $id_tournoi ?>" method="post" class="inline">
                                <input type="hidden" name="id_tournoi" value="<?= $id_tournoi ?>">
                                <input type="hidden" name="action" value="reactiver">
                                <input type="hidden" name="id_participant" value="<?= $p['id'] ?>">
                                <button type="submit" class="flex items-center gap-1.5 text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 p-2 rounded transition" title="Reactiver">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/></svg>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <footer class="py-8 border-t border-slate-900 text-center">
        <p class="text-xs font-bold tracking-[0.3em] text-slate-600 uppercase">Versify</p>
    </footer>

<?php include '_theme.php'; ?>
</body>
</html>