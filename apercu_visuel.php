<?php
session_start();
$hote_connecte = $_SESSION['id_utilisateur'] ?? 'Invité';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: generateur_formulaire.php");
    die();
}

$nom_tournoi = $_POST['nom_tournoi'] ?? 'Tournoi';
$format = $_POST['format'] ?? 'single';
$troisieme_place = isset($_POST['third_place']) ? true : false;
$equipes_brutes = $_POST['equipes'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aperçu — <?= htmlspecialchars($nom_tournoi) ?> | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-bracket/0.11.1/jquery.bracket.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-bracket/0.11.1/jquery.bracket.min.js"></script>

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #020617; color: #f8fafc; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); border: 1px solid rgba(51, 65, 85, 0.8); }

        /* jQuery Bracket theme override */
        .jQBracket { font-family: 'Outfit', sans-serif !important; background: transparent !important; }

        .jQBracket .team {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-radius: 4px !important;
            border: 1px solid #334155 !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            transition: border-color 0.2s !important;
        }
        .jQBracket .team:hover { border-color: #6366f1 !important; }
        .jQBracket .team.win {
            background-color: rgba(99, 102, 241, 0.15) !important;
            border-color: #6366f1 !important;
            color: #c7d2fe !important;
        }
        .jQBracket .team.lose {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
            color: #475569 !important;
        }

        .jQBracket .score {
            background-color: #334155 !important;
            color: #f8fafc !important;
            border-radius: 3px !important;
            font-weight: 700 !important;
        }

        .jQBracket .connector { border-color: #334155 !important; }
        .jQBracket .connector.highlight { border-color: #6366f1 !important; }

        .jQBracket .bubble { background-color: #6366f1 !important; color: white !important; border-radius: 4px !important; }
        .jQBracket .label { color: #94a3b8 !important; font-weight: bold !important; font-size: 11px !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; }
        .jQBracket .match { background: transparent !important; }

        /* Hide BYE and TBD text — replace with blank */
        .jQBracket .team .label:empty,
        .jQBracket .team[data-teamid="null"] { color: transparent !important; }

        /* Zoom controls */
        .zoom-btn { transition: all 0.15s; }
        .zoom-btn:hover { background-color: rgba(99,102,241,0.2); border-color: #6366f1; }

        /* Fullscreen */
        .bracket-wrapper:fullscreen { background: #020617; padding: 2rem; }
        .bracket-wrapper:-webkit-full-screen { background: #020617; padding: 2rem; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-[95%] mx-auto px-6 h-16 flex items-center justify-between">
            <a href="<?= isset($_SESSION['id_utilisateur']) ? 'dashboard.php' : 'index.php' ?>" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="generateur_formulaire.php" class="text-sm font-bold text-indigo-400 hover:text-indigo-300 transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Modifier les paramètres
                </a>
            </div>
        </div>
    </nav>

    <main class="flex-grow w-full max-w-[95%] mx-auto py-8">
        <div id="bracket-wrapper" class="bracket-wrapper glass-card rounded-2xl p-8 overflow-hidden relative">

            <!-- Header -->
            <div class="mb-8 border-b border-slate-800 pb-5 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-[10px] font-bold uppercase tracking-widest mb-3">
                        <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full"></span>
                        Aperçu du bracket
                    </div>
                    <h1 class="text-3xl font-bold italic"><?= htmlspecialchars($nom_tournoi) ?></h1>
                    <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mt-1">
                        Format : <?= $format === 'double' ? 'Double Elimination' : 'Single Elimination' ?>
                        <?php if ($troisieme_place): ?> · Match 3ème place<?php endif; ?>
                    </p>
                </div>

                <!-- Controls -->
                <div class="flex items-center gap-2">
                    <button id="zoom-out" class="zoom-btn w-9 h-9 rounded border border-slate-700 bg-slate-900/60 flex items-center justify-center text-slate-300 hover:text-white" title="Zoom -">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                    </button>
                    <span id="zoom-level" class="text-xs font-bold text-slate-400 w-12 text-center">100%</span>
                    <button id="zoom-in" class="zoom-btn w-9 h-9 rounded border border-slate-700 bg-slate-900/60 flex items-center justify-center text-slate-300 hover:text-white" title="Zoom +">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <button id="zoom-fit" class="zoom-btn w-9 h-9 rounded border border-slate-700 bg-slate-900/60 flex items-center justify-center text-slate-300 hover:text-white ml-1" title="Ajuster à l'écran">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                    <button id="fullscreen-btn" class="zoom-btn w-9 h-9 rounded border border-slate-700 bg-slate-900/60 flex items-center justify-center text-slate-300 hover:text-white ml-1" title="Plein écran">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4m8 0h4v4m0 8v4h-4m-8 0H4v-4"/></svg>
                    </button>
                </div>
            </div>

            <!-- Bracket area -->
            <div id="bracket-scroll" class="overflow-x-auto overflow-y-auto pb-8" style="max-height: 75vh;">
                <div id="bracket-container" class="min-w-fit transition-transform origin-top-left" style="transform: scale(1);"></div>
            </div>

        </div>
    </main>

    <script>
        $(document).ready(function() {
            let donneesBrutes = <?= json_encode($equipes_brutes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            let estDoubleElimination = <?= json_encode($format === 'double') ?>;
            let veutTroisiemePlace = <?= json_encode($troisieme_place) ?>;

            // Parse teams
            let equipesBrutes = donneesBrutes.split('\n').map(t => t.trim()).filter(t => t !== "");

            if (equipesBrutes.length === 0) {
                for (let i = 1; i <= 8; i++) equipesBrutes.push("Équipe " + i);
            }

            let nbEquipes = equipesBrutes.length;
            let prochainePuissanceDeDeux = Math.pow(2, Math.ceil(Math.log2(Math.max(2, nbEquipes))));
            let nbByes = prochainePuissanceDeDeux - nbEquipes;

            
            function genererOrdreSeeding(n) {
               
                if (n === 1) return [0];
                if (n === 2) return [0, 1];

                let result = [0, 1];
                while (result.length < n) {
                    let temp = [];
                    let size = result.length;
                    for (let i = 0; i < size; i++) {
                        temp.push(result[i]);
                        temp.push(2 * size - 1 - result[i]);
                    }
                    result = temp;
                }
                return result;
            }

            let ordreSeeding = genererOrdreSeeding(prochainePuissanceDeDeux);

            let tableauSeed = new Array(prochainePuissanceDeDeux).fill("");
            for (let i = 0; i < prochainePuissanceDeDeux; i++) {
                let seedPosition = ordreSeeding[i]; // which slot in the bracket
                if (i < nbEquipes) {
                    tableauSeed[seedPosition] = equipesBrutes[i];
                } else {
                    tableauSeed[seedPosition] = ""; // bye = blank
                }
            }

            let equipesFormatees = [];
            for (let i = 0; i < tableauSeed.length; i += 2) {
                equipesFormatees.push([tableauSeed[i], tableauSeed[i + 1]]);
            }

           
            let resultatsRound1 = equipesFormatees.map(function(match) {
                let a = match[0], b = match[1];
                if (a === "" && b === "") return [null, null]; 
                if (a !== "" && b === "") return [1, 0];       
                if (a === "" && b !== "") return [0, 1];     
                return [null, null]; 
            });

            let resultatsArbre = [];
            if (estDoubleElimination) {
                resultatsArbre = [
                    [resultatsRound1],
                    [],
                    []
                ];
            } else {
                resultatsArbre = [
                    [resultatsRound1]
                ];
            }

            var donneesArbre = {
                teams: equipesFormatees,
                results: resultatsArbre
            };

            function customRender(container, data, score, state) {
                var name = data || "";
                if (!name || name === "BYE" || name === "TBD" || name === "null") {
                    container.append('<span></span>');
                } else {
                    container.append('<span>' + $('<span>').text(name).html() + '</span>');
                }
            }

            $('#bracket-container').bracket({
                init: donneesArbre,
                skipConsolationRound: !veutTroisiemePlace,
                teamWidth: 170,
                scoreWidth: 28,
                matchMargin: 50,
                roundMargin: 70,
                decorator: {
                    edit: function() {},
                    render: function(container, data, score, state) {
                        customRender(container, data, score, state);
                    }
                }
            });

            setTimeout(function() {
                $('.jQBracket .team .label, .jQBracket .team span').each(function() {
                    var txt = $(this).text().trim();
                    if (txt === 'BYE' || txt === 'TBD' || txt === 'null') {
                        $(this).text('');
                    }
                });
            }, 100);

            let currentZoom = 1;
            const bracketEl = document.getElementById('bracket-container');
            const zoomLabel = document.getElementById('zoom-level');

            function setZoom(z) {
                currentZoom = Math.max(0.3, Math.min(2, z));
                bracketEl.style.transform = 'scale(' + currentZoom + ')';
                zoomLabel.textContent = Math.round(currentZoom * 100) + '%';
            }

            document.getElementById('zoom-in').addEventListener('click', () => setZoom(currentZoom + 0.1));
            document.getElementById('zoom-out').addEventListener('click', () => setZoom(currentZoom - 0.1));
            document.getElementById('zoom-fit').addEventListener('click', () => {
                const scrollEl = document.getElementById('bracket-scroll');
                const containerWidth = scrollEl.clientWidth;
                const bracketWidth = bracketEl.scrollWidth / currentZoom;
                const fitZoom = Math.min(1, containerWidth / bracketWidth);
                setZoom(fitZoom);
            });

            document.getElementById('fullscreen-btn').addEventListener('click', () => {
                const wrapper = document.getElementById('bracket-wrapper');
                if (!document.fullscreenElement) {
                    wrapper.requestFullscreen().catch(() => {});
                } else {
                    document.exitFullscreen();
                }
            });
        });
    </script>
<?php include '_theme.php'; ?>
</body>
</html>
