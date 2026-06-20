<?php
session_start();
$hote_connecte = $_SESSION['id_utilisateur'] ?? 'Invité';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration du Bracket | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
        .grid-bg {
            background-image:
                linear-gradient(rgba(99,102,241,0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99,102,241,0.06) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="<?= isset($_SESSION['id_utilisateur']) ? 'dashboard.php' : 'index.php' ?>" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <div class="flex items-center gap-6">
                <span class="text-sm font-medium text-slate-400 hidden sm:inline"><?= htmlspecialchars($hote_connecte) ?></span>
                <a href="<?= isset($_SESSION['id_utilisateur']) ? 'dashboard.php' : 'index.php' ?>" class="text-sm text-slate-400 hover:text-white transition">← Retour</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow flex items-center justify-center p-6 relative overflow-hidden">
        <div class="absolute inset-0 grid-bg pointer-events-none"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="w-full max-w-2xl glass-card border border-slate-800 rounded-2xl p-8 shadow-2xl relative">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-[10px] font-bold uppercase tracking-widest mb-4">
                <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span>
                Aperçu visuel
            </div>
            <h1 class="text-3xl font-bold italic mb-2">Paramètres du Bracket</h1>
            <p class="text-slate-400 text-sm mb-8">Entrez vos données pour générer l'aperçu de l'arbre de tournoi.</p>

            <form action="apercu_visuel.php" method="POST" class="space-y-6">
                <div>
                    <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Nom du tournoi</label>
                    <input type="text" name="nom_tournoi" required placeholder="Ex: Championnat d'Hiver" class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-sm outline-none focus:border-indigo-500 transition">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Format</label>
                        <select name="format" class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-sm outline-none focus:border-indigo-500">
                            <option value="single">Single Elimination</option>
                            <option value="double">Double Elimination</option>
                        </select>
                    </div>

                    <div class="flex items-end pb-3">
                        <label class="flex items-center gap-2 text-sm cursor-pointer text-slate-300">
                            <input type="checkbox" name="third_place" class="rounded border-slate-700 bg-slate-900 text-indigo-500 focus:ring-indigo-500 w-4 h-4">
                            Match pour la 3ème place
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-indigo-500 mb-2">Liste des équipes (une par ligne)</label>
                    <p class="text-[11px] text-slate-500 mb-2">Laissez vide pour générer 8 joueurs par défaut.</p>
                    <textarea name="equipes" rows="6" class="w-full bg-slate-900 border border-slate-700 rounded p-3 text-sm outline-none focus:border-indigo-500 transition font-mono" placeholder="Karmine Corp&#10;Team Vitality&#10;Fnatic&#10;G2 Esports"></textarea>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-4 rounded font-bold transition shadow-lg shadow-indigo-500/20 uppercase tracking-widest mt-4 flex items-center justify-center gap-2">
                    Générer l'Aperçu
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>
        </div>
    </main>

<?php include '_theme.php'; ?>
</body>
</html>
