<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    die();
}

$utilisateur = $_SESSION['id_utilisateur'];
$message = '';
$message_type = '';

try {
    require_once __DIR__ . '/config.php';

    // Action: rejoindre un tournoi (soumettre une demande)
    if (isset($_POST['action']) && $_POST['action'] === 'rejoindre') {
        $id_tournoi = (int) $_POST['id_tournoi'];
        $pseudo_ingame = trim($_POST['pseudo_ingame'] ?? '');
        $rang = trim($_POST['rang'] ?? '');
        $motivation = trim($_POST['motivation'] ?? '');

        // Verifier que le tournoi existe et pas le mien
        $stmtT = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id");
        $stmtT->execute([":id" => $id_tournoi]);
        $t = $stmtT->fetch(PDO::FETCH_ASSOC);

        if (!$t) {
            $message = "Tournoi introuvable.";
            $message_type = 'error';
        } elseif ($t['hote'] === $utilisateur) {
            $message = "Vous ne pouvez pas rejoindre votre propre tournoi.";
            $message_type = 'error';
        } elseif (isset($t['statut_tournoi']) && $t['statut_tournoi'] === 'termine') {
            $message = "Ce tournoi est deja termine.";
            $message_type = 'error';
        } elseif (empty($pseudo_ingame)) {
            $message = "Le pseudo in-game est obligatoire.";
            $message_type = 'error';
        } else {
            // Verifier si deja une demande en cours ou deja participant
            $stmtDup = $pdo->prepare("SELECT id FROM inscription_tournoi WHERE id_tournoi = :idt AND id_utilisateur = :u");
            $stmtDup->execute([":idt" => $id_tournoi, ":u" => $utilisateur]);

            $stmtDupP = $pdo->prepare("SELECT id FROM participant WHERE id_tournoi = :idt AND nom_participant = :u");
            $stmtDupP->execute([":idt" => $id_tournoi, ":u" => $utilisateur]);

            if ($stmtDup->fetch()) {
                $message = "Vous avez deja soumis une demande pour ce tournoi.";
                $message_type = 'error';
            } elseif ($stmtDupP->fetch()) {
                $message = "Vous etes deja inscrit a ce tournoi.";
                $message_type = 'error';
            } else {
                // Verifier la limite
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM participant WHERE id_tournoi = :idt AND statut = 'actif'");
                $stmtCount->execute([":idt" => $id_tournoi]);
                $nb_actuel = (int) $stmtCount->fetchColumn();

                if ($t['max_participants'] && $nb_actuel >= $t['max_participants']) {
                    $message = "Ce tournoi est complet (" . $t['max_participants'] . " participants max).";
                    $message_type = 'error';
                } else {
                    // Inserer la demande d'inscription
                    $msg_complet = "Pseudo: $pseudo_ingame";
                    if ($rang) $msg_complet .= " | Rang: $rang";
                    if ($motivation) $msg_complet .= " | Motivation: $motivation";

                    $pdo->prepare("INSERT INTO inscription_tournoi (id_tournoi, id_utilisateur, message, statut_inscription) VALUES (:idt, :u, :msg, 'en_attente')")
                        ->execute([":idt" => $id_tournoi, ":u" => $utilisateur, ":msg" => $msg_complet]);

                    // Notifier l'organisateur
                    try {
                        $pdo->prepare("INSERT INTO notification (id_destinataire, type_notif, titre, message, lien) VALUES (:dest, 'tournoi', :titre, :msg, :lien)")
                            ->execute([
                                ":dest" => $t['hote'],
                                ":titre" => "Demande d'inscription",
                                ":msg" => "$utilisateur souhaite rejoindre \"" . $t['nom'] . "\". Pseudo: $pseudo_ingame",
                                ":lien" => "gerer_inscriptions.php?id_tournoi=$id_tournoi"
                            ]);
                    } catch (Exception $e) { /* table notification pas encore creee */ }

                    $message = "Votre demande pour \"" . htmlspecialchars($t['nom']) . "\" a ete envoyee. L'organisateur doit la valider.";
                    $message_type = 'success';
                }
            }
        }
    }

    // Recuperer les tournois disponibles (pas les miens, statut brouillon = pas encore lance)
    $recherche = trim($_GET['q'] ?? '');
    $filtre_jeu = $_GET['jeu'] ?? '';

    $sql = "SELECT * FROM tournoi WHERE hote != :u";
    $params = [":u" => $utilisateur];

    // Filtrer les tournois termines si la colonne existe
    try {
        $pdo->query("SELECT statut_tournoi FROM tournoi LIMIT 1");
        $sql .= " AND (statut_tournoi IS NULL OR statut_tournoi != 'termine')";
    } catch (Exception $e) {
        // colonne statut_tournoi n'existe pas encore, on ignore
    }

    if ($recherche) {
        $sql .= " AND (nom LIKE :q OR jeu LIKE :q)";
        $params[":q"] = "%$recherche%";
    }
    if ($filtre_jeu) {
        $sql .= " AND jeu = :jeu";
        $params[":jeu"] = $filtre_jeu;
    }

    $sql .= " ORDER BY date_depart ASC";

    $stmtList = $pdo->prepare($sql);
    $stmtList->execute($params);
    $tournois = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    // Jeux distincts pour le filtre
    // Jeux distincts pour le filtre
    try {
        $jeux_disponibles = $pdo->query("SELECT DISTINCT jeu FROM tournoi WHERE hote != '$utilisateur' ORDER BY jeu")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $jeux_disponibles = [];
    }

    // Mes inscriptions actuelles (pour afficher le badge "Deja inscrit")
    $stmtMes = $pdo->prepare("SELECT id_tournoi FROM participant WHERE nom_participant = :u AND statut = 'actif'");
    $stmtMes->execute([":u" => $utilisateur]);
    $mes_inscriptions = $stmtMes->fetchAll(PDO::FETCH_COLUMN);

    // Mes demandes en attente
    $mes_demandes = [];
    try {
        $stmtDem = $pdo->prepare("SELECT id_tournoi, statut_inscription FROM inscription_tournoi WHERE id_utilisateur = :u");
        $stmtDem->execute([":u" => $utilisateur]);
        foreach ($stmtDem->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $mes_demandes[$d['id_tournoi']] = $d['statut_inscription'];
        }
    } catch (Exception $e) { /* table pas encore creee */ }

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
    <title>Explorer les tournois | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; } .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }</style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-sm text-slate-400 hover:text-white transition">← Mon dashboard</a>
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500 flex items-center justify-center font-bold text-white text-sm">
                    <?= strtoupper(substr($utilisateur, 0, 1)) ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto w-full px-6 py-10 relative">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-[120px] pointer-events-none"></div>

        <!-- Header -->
        <div class="mb-8 relative">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-[10px] font-bold uppercase tracking-widest mb-4">
                <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span>
                Tournois ouverts
            </div>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight italic">Explorer les tournois</h1>
            <p class="text-slate-400 text-sm mt-2">Rejoignez un tournoi organise par d'autres joueurs sur la plateforme.</p>
            <div class="w-16 h-1 bg-indigo-500 mt-3 rounded-full"></div>
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

        <!-- Filtres -->
        <div class="glass-card border border-slate-800 rounded-xl p-4 mb-8">
            <form action="explorer_tournois.php" method="get" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-grow">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="q" value="<?= htmlspecialchars($recherche) ?>" placeholder="Rechercher un tournoi ou un jeu..." class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded pl-10 pr-4 py-2.5 text-sm outline-none transition">
                </div>
                <select name="jeu" class="bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded px-4 py-2.5 text-sm outline-none transition">
                    <option value="">Tous les jeux</option>
                    <?php foreach ($jeux_disponibles as $j): ?>
                        <option value="<?= htmlspecialchars($j) ?>" <?= $filtre_jeu === $j ? 'selected' : '' ?>><?= htmlspecialchars($j) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded text-sm font-bold transition">Rechercher</button>
            </form>
        </div>

        <!-- Liste des tournois -->
        <?php if (empty($tournois)): ?>
            <div class="glass-card border border-slate-800 rounded-xl p-12 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <p class="text-slate-500 text-sm">Aucun tournoi disponible pour le moment.</p>
                <p class="text-slate-600 text-xs mt-1">Revenez plus tard ou modifiez vos filtres.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($tournois as $t):
                    $deja_inscrit = in_array($t['id'], $mes_inscriptions);
                    // Compter les participants actuels
                    $stmtNb = $pdo->prepare("SELECT COUNT(*) FROM participant WHERE id_tournoi = :id AND statut = 'actif'");
                    $stmtNb->execute([":id" => $t['id']]);
                    $nb_participants = (int) $stmtNb->fetchColumn();
                    $max_p = $t['max_participants'] ?: null;
                    $complet = $max_p && $nb_participants >= $max_p;
                ?>
                <div class="glass-card border border-slate-800 rounded-xl p-6 hover:border-indigo-500/50 transition group flex flex-col">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-10 h-10 rounded bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <?php if ($deja_inscrit): ?>
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/40">Inscrit</span>
                        <?php elseif ($complet): ?>
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-red-500/15 text-red-400 border border-red-500/40">Complet</span>
                        <?php else: ?>
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-amber-500/15 text-amber-400 border border-amber-500/40">Ouvert</span>
                        <?php endif; ?>
                    </div>

                    <!-- Infos -->
                    <h3 class="text-lg font-bold tracking-tight group-hover:text-indigo-400 transition truncate"><?= htmlspecialchars($t['nom']) ?></h3>
                    <p class="text-xs text-indigo-500 font-bold uppercase tracking-tighter mb-1 truncate"><?= htmlspecialchars($t['jeu']) ?></p>
                    <p class="text-xs text-slate-400 mb-4">Organise par <span class="text-slate-200 font-bold"><?= htmlspecialchars($t['hote']) ?></span></p>

                    <!-- Details -->
                    <div class="flex flex-wrap gap-2 mb-5 flex-grow">
                        <span class="text-[10px] font-bold px-2 py-0.5 bg-slate-800 rounded text-slate-300"><?= htmlspecialchars($t['format']) ?></span>
                        <span class="text-[10px] font-bold px-2 py-0.5 bg-slate-800 rounded text-slate-300">BO<?= $t['best_of'] ?></span>
                        <span class="text-[10px] font-bold px-2 py-0.5 bg-slate-800 rounded text-slate-300"><?= htmlspecialchars($t['date_depart']) ?></span>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-800">
                        <div class="text-xs text-slate-400 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span class="font-bold text-slate-200"><?= $nb_participants ?></span><?php if ($max_p): ?> / <?= $max_p ?><?php endif; ?>
                        </div>

                        <?php
                        $statut_demande = $mes_demandes[$t['id']] ?? null;
                        if ($deja_inscrit): ?>
                            <span class="text-xs font-bold text-emerald-400">&#10003; Vous participez</span>
                        <?php elseif ($statut_demande === 'en_attente'): ?>
                            <span class="text-xs font-bold text-amber-400">&#9203; En attente de validation</span>
                        <?php elseif ($statut_demande === 'refuse'): ?>
                            <span class="text-xs font-bold text-red-400">&#10007; Demande refusee</span>
                        <?php elseif ($complet): ?>
                            <span class="text-xs font-bold text-red-400">Places epuisees</span>
                        <?php else: ?>
                            <button type="button" onclick="ouvrirFormulaire(<?= $t['id'] ?>, '<?= addslashes(htmlspecialchars($t['nom'])) ?>', '<?= addslashes(htmlspecialchars($t['jeu'])) ?>')" class="text-xs font-bold text-indigo-500 hover:text-indigo-400 uppercase tracking-widest flex items-center gap-1 transition">
                                Postuler
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="py-8 border-t border-slate-900 text-center">
        <p class="text-xs font-bold tracking-[0.3em] text-slate-600 uppercase">Versify</p>
    </footer>

    <!-- MODAL : Formulaire de candidature -->
    <div id="modal-candidature" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/80 backdrop-blur-sm">
        <div class="glass-card border border-slate-700 rounded-2xl p-8 w-full max-w-md shadow-2xl mx-4">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold">Postuler au tournoi</h3>
                    <p id="modal-tournoi-nom" class="text-xs text-indigo-400 font-bold uppercase tracking-widest mt-1"></p>
                </div>
                <button onclick="fermerModal()" class="text-slate-400 hover:text-white text-2xl leading-none">&times;</button>
            </div>

            <form action="explorer_tournois.php" method="post" class="space-y-4">
                <input type="hidden" name="action" value="rejoindre">
                <input type="hidden" name="id_tournoi" id="modal-id-tournoi" value="">

                <div>
                    <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Pseudo in-game <span class="text-red-500">*</span></label>
                    <input type="text" name="pseudo_ingame" required placeholder="Votre pseudo sur le jeu" class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition">
                    <p class="text-[10px] text-slate-500 mt-1">Le nom que vous utilisez dans le jeu concerne.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Rang / Niveau</label>
                    <input type="text" name="rang" placeholder="Ex: Diamond 2, Global Elite, Immortal..." class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-sm outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Motivation</label>
                    <textarea name="motivation" rows="3" placeholder="Pourquoi souhaitez-vous participer ?" class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded p-3 text-sm outline-none transition"></textarea>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-3 rounded text-sm font-bold uppercase tracking-widest transition shadow-lg shadow-indigo-500/20">
                    Envoyer ma candidature
                </button>
                <p class="text-[10px] text-slate-500 text-center">L'organisateur devra valider votre demande avant que vous soyez ajoute au tournoi.</p>
            </form>
        </div>
    </div>

    <script>
    function ouvrirFormulaire(id, nom, jeu) {
        document.getElementById('modal-id-tournoi').value = id;
        document.getElementById('modal-tournoi-nom').textContent = nom + ' — ' + jeu;
        document.getElementById('modal-candidature').classList.remove('hidden');
        document.getElementById('modal-candidature').classList.add('flex');
    }

    function fermerModal() {
        document.getElementById('modal-candidature').classList.add('hidden');
        document.getElementById('modal-candidature').classList.remove('flex');
    }

    // Fermer en cliquant en dehors
    document.getElementById('modal-candidature').addEventListener('click', function(e) {
        if (e.target === this) fermerModal();
    });
    </script>

<?php include '_theme.php'; ?>
</body>
</html>
