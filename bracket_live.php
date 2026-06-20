<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    die();
}

$hote_connecte = $_SESSION['id_utilisateur'];

if (!isset($_GET['id_tournoi'])) {
    header("Location: dashboard.php");
    die();
}

$id_tournoi = (int) $_GET['id_tournoi'];

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/_helpers.php';

    // Verifier le tournoi
    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND hote = :hote");
    $stmt->execute([":id" => $id_tournoi, ":hote" => $hote_connecte]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournoi) {
        header("Location: dashboard.php");
        die();
    }

    $best_of = (int) ($tournoi['best_of'] ?? 1);
    $score_pour_gagner = ceil($best_of / 2);

    // Recuperer participants actifs
    $stmtP = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id AND statut = 'actif' ORDER BY seed ASC");
    $stmtP->execute([":id" => $id_tournoi]);
    $participants = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    if (count($participants) < 2) {
        header("Location: participants.php?id_tournoi=" . $id_tournoi);
        die();
    }

    function genererSeeding($n) {
        if ($n === 1) return [0];
        if ($n === 2) return [0, 1];
        $result = [0, 1];
        while (count($result) < $n) {
            $temp = [];
            $size = count($result);
            for ($i = 0; $i < $size; $i++) {
                $temp[] = $result[$i];
                $temp[] = 2 * $size - 1 - $result[$i];
            }
            $result = $temp;
        }
        return $result;
    }

    // ===== GENERER LES MATCHS SI PAS ENCORE FAIT =====
    $stmtMatchCount = $pdo->prepare("SELECT COUNT(*) FROM match_tournoi WHERE id_tournoi = :id");
    $stmtMatchCount->execute([":id" => $id_tournoi]);
    $matchs_existent = (int) $stmtMatchCount->fetchColumn() > 0;

    if (!$matchs_existent) {
        // Generer les matchs SANS demarrer le tournoi (statut reste brouillon)
        $nb = count($participants);
        $format_tournoi = $tournoi['format'] ?? 'Single Elimination';

        if ($format_tournoi === 'Double Elimination') {
            // ===== DOUBLE ELIMINATION =====
            // WB rounds: 1..NWB | LB rounds: NWB+1..NWB+NLB | GF: NWB+NLB+1
            $puissance = (int) pow(2, ceil(log(max(2, $nb), 2)));
            $nb_rondes_wb_gen = (int) log($puissance, 2);
            $nb_rondes_lb_gen = 2 * max(0, $nb_rondes_wb_gen - 1);

            $ordre = genererSeeding($puissance);
            $slots = array_fill(0, $puissance, null);
            for ($i = 0; $i < $puissance; $i++) {
                if ($i < $nb) $slots[$ordre[$i]] = $participants[$i]['id'];
            }

            // WB Round 1 (with bye handling)
            $position = 0;
            for ($i = 0; $i < $puissance; $i += 2) {
                $p1 = $slots[$i];
                $p2 = $slots[$i + 1];
                $position++;
                $stmtIns = $pdo->prepare("INSERT INTO match_tournoi (id_tournoi, ronde, position, id_participant1, id_participant2, statut_match, type_bracket) VALUES (:id, 1, :pos, :p1, :p2, :st, 'WB')");
                if ($p1 === null && $p2 === null) {
                    $stmtIns->execute([":id" => $id_tournoi, ":pos" => $position, ":p1" => null, ":p2" => null, ":st" => 'termine']);
                } elseif ($p1 === null) {
                    $stmtIns->execute([":id" => $id_tournoi, ":pos" => $position, ":p1" => null, ":p2" => $p2, ":st" => 'termine']);
                    $pdo->prepare("UPDATE match_tournoi SET gagnant_id = :g, score2 = :s WHERE id_tournoi = :id AND ronde = 1 AND position = :pos")
                        ->execute([":g" => $p2, ":s" => $score_pour_gagner, ":id" => $id_tournoi, ":pos" => $position]);
                } elseif ($p2 === null) {
                    $stmtIns->execute([":id" => $id_tournoi, ":pos" => $position, ":p1" => $p1, ":p2" => null, ":st" => 'termine']);
                    $pdo->prepare("UPDATE match_tournoi SET gagnant_id = :g, score1 = :s WHERE id_tournoi = :id AND ronde = 1 AND position = :pos")
                        ->execute([":g" => $p1, ":s" => $score_pour_gagner, ":id" => $id_tournoi, ":pos" => $position]);
                } else {
                    $stmtIns->execute([":id" => $id_tournoi, ":pos" => $position, ":p1" => $p1, ":p2" => $p2, ":st" => 'en_attente']);
                }
            }

            // WB Rounds 2..NWB (empty shells)
            for ($r = 2; $r <= $nb_rondes_wb_gen; $r++) {
                $nb_m = (int) ($puissance / pow(2, $r));
                for ($pos = 1; $pos <= $nb_m; $pos++) {
                    $pdo->prepare("INSERT INTO match_tournoi (id_tournoi, ronde, position, statut_match, type_bracket) VALUES (:id, :r, :pos, 'en_attente', 'WB')")
                        ->execute([":id" => $id_tournoi, ":r" => $r, ":pos" => $pos]);
                }
            }

            // LB Rounds (offset: NWB+1..NWB+NLB)
            // Match count: LBRlr = puissance / 2^(ceil(lr/2)+1), min 1
            for ($lr = 1; $lr <= $nb_rondes_lb_gen; $lr++) {
                $ar = $nb_rondes_wb_gen + $lr;
                $nb_m = max(1, (int) ($puissance / pow(2, (int) ceil($lr / 2) + 1)));
                for ($pos = 1; $pos <= $nb_m; $pos++) {
                    $pdo->prepare("INSERT INTO match_tournoi (id_tournoi, ronde, position, statut_match, type_bracket) VALUES (:id, :r, :pos, 'en_attente', 'LB')")
                        ->execute([":id" => $id_tournoi, ":r" => $ar, ":pos" => $pos]);
                }
            }

            // Grand Final
            $pdo->prepare("INSERT INTO match_tournoi (id_tournoi, ronde, position, statut_match, type_bracket) VALUES (:id, :r, 1, 'en_attente', 'GF')")
                ->execute([":id" => $id_tournoi, ":r" => $nb_rondes_wb_gen + $nb_rondes_lb_gen + 1]);

            // Propagate WBR1 byes into WBR2
            $stmtR1 = $pdo->prepare("SELECT * FROM match_tournoi WHERE id_tournoi = :id AND ronde = 1 ORDER BY position ASC");
            $stmtR1->execute([":id" => $id_tournoi]);
            $matchs_r1 = $stmtR1->fetchAll(PDO::FETCH_ASSOC);
            for ($i = 0; $i < count($matchs_r1); $i += 2) {
                $m1 = $matchs_r1[$i];
                $m2 = $matchs_r1[$i + 1] ?? null;
                $pos_r2 = (int) floor($i / 2) + 1;
                if ($m1['gagnant_id']) {
                    $pdo->prepare("UPDATE match_tournoi SET id_participant1 = :p WHERE id_tournoi = :id AND ronde = 2 AND position = :pos")
                        ->execute([":p" => $m1['gagnant_id'], ":id" => $id_tournoi, ":pos" => $pos_r2]);
                }
                if ($m2 && $m2['gagnant_id']) {
                    $pdo->prepare("UPDATE match_tournoi SET id_participant2 = :p WHERE id_tournoi = :id AND ronde = 2 AND position = :pos")
                        ->execute([":p" => $m2['gagnant_id'], ":id" => $id_tournoi, ":pos" => $pos_r2]);
                }
            }

        } else {
            // ===== SINGLE ELIMINATION =====
            $puissance = (int) pow(2, ceil(log($nb, 2)));
            $nb_rondes = (int) log($puissance, 2);

            $ordre = genererSeeding($puissance);
            $slots = array_fill(0, $puissance, null);
            for ($i = 0; $i < $puissance; $i++) {
                $pos = $ordre[$i];
                if ($i < $nb) {
                    $slots[$pos] = $participants[$i]['id'];
                }
            }

            // Creer les matchs de la ronde 1
            $position = 0;
            for ($i = 0; $i < $puissance; $i += 2) {
                $p1 = $slots[$i];
                $p2 = $slots[$i + 1];
                $position++;

                $stmtInsert = $pdo->prepare("INSERT INTO match_tournoi (id_tournoi, ronde, position, id_participant1, id_participant2, statut_match, type_bracket) VALUES (:id_tournoi, 1, :pos, :p1, :p2, :statut, 'SE')");

                if ($p1 === null && $p2 === null) {
                    $stmtInsert->execute([":id_tournoi" => $id_tournoi, ":pos" => $position, ":p1" => null, ":p2" => null, ":statut" => 'termine']);
                } elseif ($p1 === null) {
                    $stmtInsert->execute([":id_tournoi" => $id_tournoi, ":pos" => $position, ":p1" => null, ":p2" => $p2, ":statut" => 'termine']);
                    $pdo->prepare("UPDATE match_tournoi SET gagnant_id = :g, score2 = :s WHERE id_tournoi = :id AND ronde = 1 AND position = :pos")
                        ->execute([":g" => $p2, ":s" => $score_pour_gagner, ":id" => $id_tournoi, ":pos" => $position]);
                } elseif ($p2 === null) {
                    $stmtInsert->execute([":id_tournoi" => $id_tournoi, ":pos" => $position, ":p1" => $p1, ":p2" => null, ":statut" => 'termine']);
                    $pdo->prepare("UPDATE match_tournoi SET gagnant_id = :g, score1 = :s WHERE id_tournoi = :id AND ronde = 1 AND position = :pos")
                        ->execute([":g" => $p1, ":s" => $score_pour_gagner, ":id" => $id_tournoi, ":pos" => $position]);
                } else {
                    $stmtInsert->execute([":id_tournoi" => $id_tournoi, ":pos" => $position, ":p1" => $p1, ":p2" => $p2, ":statut" => 'en_attente']);
                }
            }

            // Creer les matchs vides pour les rondes suivantes
            for ($r = 2; $r <= $nb_rondes; $r++) {
                $nb_matchs_ronde = (int) ($puissance / pow(2, $r));
                for ($pos = 1; $pos <= $nb_matchs_ronde; $pos++) {
                    $pdo->prepare("INSERT INTO match_tournoi (id_tournoi, ronde, position, statut_match, type_bracket) VALUES (:id, :r, :pos, 'en_attente', 'SE')")
                        ->execute([":id" => $id_tournoi, ":r" => $r, ":pos" => $pos]);
                }
            }

            // Propager les byes vers la ronde 2
            $stmtR1 = $pdo->prepare("SELECT * FROM match_tournoi WHERE id_tournoi = :id AND ronde = 1 ORDER BY position ASC");
            $stmtR1->execute([":id" => $id_tournoi]);
            $matchs_r1 = $stmtR1->fetchAll(PDO::FETCH_ASSOC);

            for ($i = 0; $i < count($matchs_r1); $i += 2) {
                $m1 = $matchs_r1[$i];
                $m2 = $matchs_r1[$i + 1] ?? null;
                $pos_r2 = (int) floor($i / 2) + 1;

                $g1 = $m1['gagnant_id'];
                $g2 = $m2 ? $m2['gagnant_id'] : null;

                if ($g1) {
                    $pdo->prepare("UPDATE match_tournoi SET id_participant1 = :p WHERE id_tournoi = :id AND ronde = 2 AND position = :pos")
                        ->execute([":p" => $g1, ":id" => $id_tournoi, ":pos" => $pos_r2]);
                }
                if ($g2) {
                    $pdo->prepare("UPDATE match_tournoi SET id_participant2 = :p WHERE id_tournoi = :id AND ronde = 2 AND position = :pos")
                        ->execute([":p" => $g2, ":id" => $id_tournoi, ":pos" => $pos_r2]);
                }
            }
        } // fin if format
    }

    // Compute DE bracket parameters from WBR1 match count (stable after generation)
    $nb_rondes_wb = 0;
    $nb_rondes_lb = 0;
    if (($tournoi['format'] ?? '') === 'Double Elimination') {
        $stmtR1C = $pdo->prepare("SELECT COUNT(*) FROM match_tournoi WHERE id_tournoi = :id AND ronde = 1");
        $stmtR1C->execute([":id" => $id_tournoi]);
        $r1c = (int) $stmtR1C->fetchColumn();
        if ($r1c > 0) {
            $nb_rondes_wb = (int) log($r1c * 2, 2);
            $nb_rondes_lb = 2 * max(0, $nb_rondes_wb - 1);
        }
    }

    // ===== ACTIONS AJAX =====
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
        header('Content-Type: application/json');

        // Lancer le tournoi (passer de brouillon a en_cours)
        if ($_POST['ajax_action'] === 'lancer_tournoi') {
            $pdo->prepare("UPDATE tournoi SET statut_tournoi = 'en_cours' WHERE id = :id AND statut_tournoi = 'brouillon'")
                ->execute([":id" => $id_tournoi]);
            // Recharger le tournoi
            $tournoi['statut_tournoi'] = 'en_cours';
            echo json_encode(["ok" => true]);
            die();
        }

        if ($_POST['ajax_action'] === 'modifier_score') {
            $id_match = (int) $_POST['id_match'];
            $score1 = (int) $_POST['score1'];
            $score2 = (int) $_POST['score2'];

            // Determiner le gagnant
            $stmtM = $pdo->prepare("SELECT id_participant1, id_participant2 FROM match_tournoi WHERE id = :id");
            $stmtM->execute([":id" => $id_match]);
            $row = $stmtM->fetch(PDO::FETCH_ASSOC);
            $gagnant = null;
            $statut  = 'en_cours';
            if ($score1 >= $score_pour_gagner) {
                $gagnant = $row['id_participant1'];
                $statut  = 'termine';
            } elseif ($score2 >= $score_pour_gagner) {
                $gagnant = $row['id_participant2'];
                $statut  = 'termine';
            }

            $pdo->prepare("UPDATE match_tournoi SET score1 = :s1, score2 = :s2, gagnant_id = :g, statut_match = :st WHERE id = :id")
                ->execute([":s1" => $score1, ":s2" => $score2, ":g" => $gagnant, ":st" => $statut, ":id" => $id_match]);

            // Propager selon le format
            if ($gagnant) {
                $stmtInfo = $pdo->prepare("SELECT ronde, position FROM match_tournoi WHERE id = :id");
                $stmtInfo->execute([":id" => $id_match]);
                $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                $r_match = $info['ronde'];
                $p_match = $info['position'];

                if (($tournoi['format'] ?? '') === 'Double Elimination') {
                    $perdant = ($gagnant == $row['id_participant1']) ? $row['id_participant2'] : $row['id_participant1'];
                    $ronde_gf = $nb_rondes_wb + $nb_rondes_lb + 1;

                    if ($r_match <= $nb_rondes_wb) {
                        // WB match: winner advances in WB (or to GF), loser drops to LB
                        if ($r_match == $nb_rondes_wb) {
                            // WB Final winner → GF p1
                            $pdo->prepare("UPDATE match_tournoi SET id_participant1 = :p WHERE id_tournoi = :id AND ronde = :r AND position = 1")
                                ->execute([":p" => $gagnant, ":id" => $id_tournoi, ":r" => $ronde_gf]);
                        } else {
                            $next_r = $r_match + 1;
                            $next_p = (int) ceil($p_match / 2);
                            $slot = ($p_match % 2 === 1) ? 'id_participant1' : 'id_participant2';
                            $pdo->prepare("UPDATE match_tournoi SET $slot = :p WHERE id_tournoi = :id AND ronde = :r AND position = :pos")
                                ->execute([":p" => $gagnant, ":id" => $id_tournoi, ":r" => $next_r, ":pos" => $next_p]);
                        }
                        // Loser drops to LB
                        if ($perdant) {
                            if ($r_match == 1) {
                                $lb_r = $nb_rondes_wb + 1;
                                $lb_p = (int) ceil($p_match / 2);
                                $lb_slot = ($p_match % 2 === 1) ? 'id_participant1' : 'id_participant2';
                            } else {
                                $lb_r = $nb_rondes_wb + 2 * ($r_match - 1);
                                $lb_p = $p_match;
                                $lb_slot = 'id_participant2';
                            }
                            $pdo->prepare("UPDATE match_tournoi SET $lb_slot = :p WHERE id_tournoi = :id AND ronde = :r AND position = :pos")
                                ->execute([":p" => $perdant, ":id" => $id_tournoi, ":r" => $lb_r, ":pos" => $lb_p]);
                        }
                    } elseif ($r_match < $ronde_gf) {
                        // LB match: winner advances in LB (or to GF), loser eliminated
                        $lr = $r_match - $nb_rondes_wb;
                        if ($lr == $nb_rondes_lb) {
                            // Last LB round winner → GF p2
                            $pdo->prepare("UPDATE match_tournoi SET id_participant2 = :p WHERE id_tournoi = :id AND ronde = :r AND position = 1")
                                ->execute([":p" => $gagnant, ":id" => $id_tournoi, ":r" => $ronde_gf]);
                        } elseif ($lr % 2 === 1) {
                            // Odd LB round (consolidation) → next LB round, same pos, p1
                            $pdo->prepare("UPDATE match_tournoi SET id_participant1 = :p WHERE id_tournoi = :id AND ronde = :r AND position = :pos")
                                ->execute([":p" => $gagnant, ":id" => $id_tournoi, ":r" => $r_match + 1, ":pos" => $p_match]);
                        } else {
                            // Even LB round (feed) → next LB round, halved pos
                            $next_p = (int) ceil($p_match / 2);
                            $lb_slot = ($p_match % 2 === 1) ? 'id_participant1' : 'id_participant2';
                            $pdo->prepare("UPDATE match_tournoi SET $lb_slot = :p WHERE id_tournoi = :id AND ronde = :r AND position = :pos")
                                ->execute([":p" => $gagnant, ":id" => $id_tournoi, ":r" => $r_match + 1, ":pos" => $next_p]);
                        }
                    }
                    // GF: no further propagation needed
                } else {
                    // Single Elimination
                    $ronde_suivante = $r_match + 1;
                    $pos_suivante = (int) ceil($p_match / 2);
                    $stmtNext = $pdo->prepare("SELECT id FROM match_tournoi WHERE id_tournoi = :id AND ronde = :r AND position = :pos");
                    $stmtNext->execute([":id" => $id_tournoi, ":r" => $ronde_suivante, ":pos" => $pos_suivante]);
                    if ($stmtNext->fetch()) {
                        $slot = ($p_match % 2 === 1) ? 'id_participant1' : 'id_participant2';
                        $pdo->prepare("UPDATE match_tournoi SET $slot = :p WHERE id_tournoi = :id AND ronde = :r AND position = :pos")
                            ->execute([":p" => $gagnant, ":id" => $id_tournoi, ":r" => $ronde_suivante, ":pos" => $pos_suivante]);
                    }
                }
            }

            echo json_encode(["ok" => true]);
            die();
        }

        if ($_POST['ajax_action'] === 'rouvrir_match') {
            $id_match = (int) $_POST['id_match'];

            // Lire l'ancien gagnant et la ronde avant de reinitialiser
            $stmtOld = $pdo->prepare("SELECT gagnant_id, ronde FROM match_tournoi WHERE id = :id");
            $stmtOld->execute([":id" => $id_match]);
            $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
            $ancien_gagnant = $old['gagnant_id'];

            // Reinitialiser le match
            $pdo->prepare("UPDATE match_tournoi SET score1 = 0, score2 = 0, gagnant_id = NULL, statut_match = 'en_attente' WHERE id = :id")
                ->execute([":id" => $id_match]);

            // Cascade : supprimer l'ancien gagnant de toutes les rondes suivantes
            if ($ancien_gagnant) {
                $pdo->prepare(
                    "UPDATE match_tournoi
                     SET id_participant1 = CASE WHEN id_participant1 = :p THEN NULL ELSE id_participant1 END,
                         id_participant2 = CASE WHEN id_participant2 = :p THEN NULL ELSE id_participant2 END,
                         gagnant_id      = CASE WHEN gagnant_id      = :p THEN NULL ELSE gagnant_id END,
                         score1          = CASE WHEN id_participant1 = :p THEN 0    ELSE score1 END,
                         score2          = CASE WHEN id_participant2 = :p THEN 0    ELSE score2 END,
                         statut_match    = CASE WHEN (id_participant1 = :p OR id_participant2 = :p)
                                               THEN 'en_attente' ELSE statut_match END
                     WHERE id_tournoi = :tid AND ronde > :ronde"
                )->execute([":p" => $ancien_gagnant, ":tid" => $id_tournoi, ":ronde" => $old['ronde']]);
            }

            echo json_encode(["ok" => true]);
            die();
        }
    }

    // Recuperer tous les matchs
    $stmtMatchs = $pdo->prepare("SELECT * FROM match_tournoi WHERE id_tournoi = :id ORDER BY ronde ASC, position ASC");
    $stmtMatchs->execute([":id" => $id_tournoi]);
    $matchs = $stmtMatchs->fetchAll(PDO::FETCH_ASSOC);

    // Map participants par id
    $map_participants = [];
    $stmtAllP = $pdo->prepare("SELECT * FROM participant WHERE id_tournoi = :id");
    $stmtAllP->execute([":id" => $id_tournoi]);
    foreach ($stmtAllP->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $map_participants[$p['id']] = $p['nom_affichage'] ?: $p['nom_participant'];
    }

    // Organiser par ronde
    $rondes = [];
    foreach ($matchs as $m) {
        $rondes[$m['ronde']][] = $m;
    }
    $nb_rondes = count($rondes);

} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

// Labels des rondes
function labelRonde($ronde, $total) {
    global $tournoi, $nb_rondes_wb, $nb_rondes_lb;
    $format = $tournoi['format'] ?? 'Single Elimination';
    if ($format === 'Double Elimination') {
        $ronde_gf = $nb_rondes_wb + $nb_rondes_lb + 1;
        if ($ronde >= $ronde_gf) return "Grande Finale";
        if ($ronde > $nb_rondes_wb) {
            $lr = $ronde - $nb_rondes_wb;
            return "Perdants R" . $lr;
        }
        if ($ronde == $nb_rondes_wb) return "Finale Gagnants";
        if ($ronde == $nb_rondes_wb - 1 && $nb_rondes_wb >= 2) return "Demi-finales Gagnants";
        return "Gagnants R" . $ronde;
    }
    if ($ronde === $total) return "Finale";
    if ($ronde === $total - 1) return "Demi-finales";
    if ($ronde === $total - 2) return "Quarts de finale";
    return "Ronde " . $ronde;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<script>if(localStorage.getItem('versify_theme')==='light')document.documentElement.classList.add('light-mode');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bracket — <?= htmlspecialchars($tournoi['nom']) ?> | Versify</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='8' height='60' x='46' y='20' fill='%236366f1' rx='4'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.55); backdrop-filter: blur(12px); }
        .match-card { transition: all 0.2s; }
        .match-card:hover { border-color: rgba(99,102,241,0.5); transform: translateY(-1px); }
        .match-card:hover .match-actions { opacity: 1; pointer-events: auto; }
        .match-actions { opacity: 0; pointer-events: none; transition: opacity 0.2s; }
        /* --- Bracket pyramide + connecteurs --- */
        .bracket-section { position: relative; }
        .bracket-svg { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: visible; }
        .bracket-rounds { position: relative; z-index: 1; }
        .bracket-svg path { transition: stroke 0.3s ease; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl">
        <div class="max-w-[95%] mx-auto px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                <span class="text-xl font-bold uppercase tracking-widest">Versify</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-xs font-bold text-slate-400 hover:text-white transition">Dashboard</a>
                <a href="historique.php?id_tournoi=<?= $id_tournoi ?>" class="text-xs font-bold text-slate-400 hover:text-white transition">Historique</a>
                <a href="classement.php?id_tournoi=<?= $id_tournoi ?>" class="text-xs font-bold text-slate-400 hover:text-white transition">Classement</a>
                <a href="reclamation.php?id_tournoi=<?= $id_tournoi ?>" class="text-xs font-bold text-slate-400 hover:text-white transition">Reclamations</a>
                <a href="participants_apres_lancement.php?id_tournoi=<?= $id_tournoi ?>" class="text-sm font-bold text-indigo-400 hover:text-indigo-300 transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Participants
                </a>
                <span class="text-sm text-slate-400 hidden sm:inline"><?= htmlspecialchars($hote_connecte) ?></span>
            </div>
        </div>
    </nav>

    <?php $tab_actif = 'bracket'; include '_tabs.php'; ?>

    <main class="flex-grow w-full max-w-[95%] mx-auto py-8">
        <div class="glass-card border border-slate-800 rounded-2xl p-8 overflow-hidden">

            <!-- Header -->
            <div class="mb-8 border-b border-slate-800 pb-5 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
                <div>
                    <?php $est_en_cours = (isset($tournoi['statut_tournoi']) && $tournoi['statut_tournoi'] === 'en_cours'); ?>
                    <?php $est_brouillon = (!isset($tournoi['statut_tournoi']) || $tournoi['statut_tournoi'] === 'brouillon'); ?>
                    <?php if ($est_en_cours): ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-[10px] font-bold uppercase tracking-widest mb-3">
                        <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                        Tournoi en cours
                    </div>
                    <?php else: ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-[10px] font-bold uppercase tracking-widest mb-3">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full"></span>
                        Apercu du bracket — Tournoi non lance
                    </div>
                    <?php endif; ?>
                    <h1 class="text-3xl font-bold italic"><?= htmlspecialchars($tournoi['nom']) ?></h1>
                    <p class="text-indigo-400 font-bold text-sm uppercase tracking-widest mt-1">
                        <?= htmlspecialchars($tournoi['jeu']) ?> &middot; <?= htmlspecialchars($tournoi['format']) ?> &middot; BO<?= $best_of ?>
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-xs text-slate-400">
                        Score pour gagner : <span class="text-indigo-400 font-bold"><?= $score_pour_gagner ?></span> manche(s)
                    </div>
                    <?php if ($est_brouillon): ?>
                    <button id="btn-lancer" onclick="lancerTournoi()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2.5 rounded text-xs font-bold uppercase tracking-widest transition shadow-lg shadow-emerald-500/20 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Lancer le tournoi
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bracket -->
            <?php
            $format_tournoi_display = $tournoi['format'] ?? 'Single Elimination';
            $is_de = ($format_tournoi_display === 'Double Elimination');
            $ronde_gf_display = $nb_rondes_wb + $nb_rondes_lb + 1;

            // Build section groups for DE
            $sections = [];
            if ($is_de) {
                $wb_rounds = []; $lb_rounds = []; $gf_rounds = [];
                foreach ($rondes as $nr => $mr) {
                    if ($nr <= $nb_rondes_wb)                              $wb_rounds[$nr] = $mr;
                    elseif ($nr <= $nb_rondes_wb + $nb_rondes_lb)          $lb_rounds[$nr] = $mr;
                    else                                                    $gf_rounds[$nr] = $mr;
                }
                if (!empty($wb_rounds)) $sections[] = ['label' => 'Winners Bracket', 'accent' => 'indigo', 'rounds' => $wb_rounds];
                if (!empty($lb_rounds)) $sections[] = ['label' => 'Losers Bracket',  'accent' => 'amber',  'rounds' => $lb_rounds];
                if (!empty($gf_rounds)) $sections[] = ['label' => 'Grande Finale',   'accent' => 'emerald','rounds' => $gf_rounds];
            } else {
                $sections[] = ['label' => null, 'accent' => 'indigo', 'rounds' => $rondes];
            }
            ?>
            <?php
            // Separer la Grande Finale (apex, a droite) des sous-arbres WB/LB (empiles a gauche)
            $gf_section = null;
            $main_sections = [];
            foreach ($sections as $s) {
                if (($s['label'] ?? '') === 'Grande Finale') $gf_section = $s;
                else $main_sections[] = $s;
            }

            // Closure : rendu d'une section (titre + colonnes de rounds en pyramide)
            $renderSection = function ($section) use ($map_participants, $is_de, $nb_rondes_wb, $nb_rondes_lb, $ronde_gf_display, $nb_rondes, $est_en_cours) {
                // Hauteur commune des colonnes = nb max de matchs * unite -> espacement qui double a chaque round
                $section_max = max(array_map('count', $section['rounds']));
                $unit        = 104;
                $col_height  = $section_max * $unit;
                $accent      = $section['accent'];
                $txt_accent  = $accent === 'amber' ? 'text-amber-400' : ($accent === 'emerald' ? 'text-emerald-400' : 'text-indigo-400');
                $bg_accent   = $accent === 'amber' ? 'bg-amber-500/20' : ($accent === 'emerald' ? 'bg-emerald-500/20' : 'bg-indigo-500/20');
                ?>
                <div class="min-w-fit">
                    <?php if ($section['label']): ?>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-[10px] font-bold uppercase tracking-widest <?= $txt_accent ?>"><?= $section['label'] ?></span>
                        <div class="flex-1 h-px <?= $bg_accent ?>"></div>
                    </div>
                    <?php endif; ?>
                    <div class="flex gap-20 min-w-fit">
                        <?php foreach ($section['rounds'] as $num_ronde => $matchs_ronde): ?>
                        <div class="flex flex-col">
                            <div class="text-center text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">
                                <?= labelRonde($num_ronde, $nb_rondes) ?>
                            </div>
                            <div class="flex flex-col justify-around" style="min-height: <?= $col_height ?>px;">
                                <?php foreach ($matchs_ronde as $match):
                                    $nom1 = isset($match['id_participant1']) ? ($map_participants[$match['id_participant1']] ?? '') : '';
                                    $nom2 = isset($match['id_participant2']) ? ($map_participants[$match['id_participant2']] ?? '') : '';
                                    $est_termine = $match['statut_match'] === 'termine';
                                    $est_gagnant1 = $est_termine && $match['gagnant_id'] == $match['id_participant1'];
                                    $est_gagnant2 = $est_termine && $match['gagnant_id'] == $match['id_participant2'];
                                    $a_gagnant    = $est_termine && $match['gagnant_id'];

                                    // ----- Match cible (flux du gagnant) pour tracer le connecteur -----
                                    $tr = null; $tp = null;
                                    if (!$is_de) {
                                        $tr = $num_ronde + 1; $tp = (int) ceil($match['position'] / 2);
                                    } elseif ($num_ronde < $nb_rondes_wb) {
                                        $tr = $num_ronde + 1; $tp = (int) ceil($match['position'] / 2);
                                    } elseif ($num_ronde == $nb_rondes_wb) {
                                        $tr = $ronde_gf_display; $tp = 1;
                                    } elseif ($num_ronde < $ronde_gf_display) {
                                        $lr = $num_ronde - $nb_rondes_wb;
                                        if ($lr == $nb_rondes_lb)      { $tr = $ronde_gf_display; $tp = 1; }
                                        elseif ($lr % 2 === 1)         { $tr = $num_ronde + 1; $tp = $match['position']; }
                                        else                           { $tr = $num_ronde + 1; $tp = (int) ceil($match['position'] / 2); }
                                    }
                                    // Overlay SVG global -> on relie partout (y compris WB/LB -> Grande Finale)
                                    $data_to = ($tr !== null) ? "m-$tr-$tp" : '';
                                    $node_id = "m-{$num_ronde}-{$match['position']}";
                                ?>
                                <div class="match-card relative border border-slate-700 rounded-lg overflow-hidden w-60 bg-slate-900/40 <?= ($est_gagnant1 || $est_gagnant2) ? 'border-l-[3px] border-l-emerald-500' : '' ?>"
                                     data-match-id="<?= $match['id'] ?>"
                                     data-node="<?= $node_id ?>"
                                     data-to="<?= $data_to ?>"
                                     data-won="<?= $a_gagnant ? '1' : '0' ?>">
                                    <?php if (!empty($match['heure_prevue'])): ?>
                                    <div class="px-3 py-0.5 bg-slate-900/70 border-b border-slate-800 text-[9px] font-bold uppercase tracking-widest text-slate-500 text-right">
                                        <?= (new DateTime($match['heure_prevue']))->format('d M · H:i') ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex items-center gap-2 px-2.5 py-1.5 border-b border-slate-800 <?= $est_gagnant1 ? 'bg-emerald-500/10' : ($est_termine && !$est_gagnant1 && $nom1 ? 'opacity-45' : '') ?>">
                                        <svg class="w-3.5 h-3.5 shrink-0 <?= $est_gagnant1 ? 'text-emerald-400' : 'text-slate-600' ?>" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5z"/></svg>
                                        <span class="flex-1 text-[13px] font-semibold truncate <?= $est_gagnant1 ? 'text-emerald-300' : 'text-slate-200' ?>"><?= htmlspecialchars($nom1) ?: '&mdash;' ?></span>
                                        <span class="text-[13px] font-bold tabular-nums min-w-[18px] text-center <?= $est_gagnant1 ? 'text-emerald-400' : 'text-slate-400' ?>"><?= ($nom1 || $nom2) ? $match['score1'] : '' ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 px-2.5 py-1.5 <?= $est_gagnant2 ? 'bg-emerald-500/10' : ($est_termine && !$est_gagnant2 && $nom2 ? 'opacity-45' : '') ?>">
                                        <svg class="w-3.5 h-3.5 shrink-0 <?= $est_gagnant2 ? 'text-emerald-400' : 'text-slate-600' ?>" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5z"/></svg>
                                        <span class="flex-1 text-[13px] font-semibold truncate <?= $est_gagnant2 ? 'text-emerald-300' : 'text-slate-200' ?>"><?= htmlspecialchars($nom2) ?: '&mdash;' ?></span>
                                        <span class="text-[13px] font-bold tabular-nums min-w-[18px] text-center <?= $est_gagnant2 ? 'text-emerald-400' : 'text-slate-400' ?>"><?= ($nom1 || $nom2) ? $match['score2'] : '' ?></span>
                                    </div>
                                    <?php if ($nom1 || $nom2): ?>
                                    <div class="match-actions absolute inset-0 bg-slate-950/90 flex items-center justify-center gap-2 rounded-lg">
                                        <button onclick="ouvrirDetails(<?= $match['id'] ?>, '<?= addslashes($nom1) ?>', '<?= addslashes($nom2) ?>', <?= $match['score1'] ?>, <?= $match['score2'] ?>, '<?= $match['statut_match'] ?>')" class="text-[10px] font-bold text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 border border-slate-600 px-2 py-1.5 rounded transition">
                                            Details
                                        </button>
                                        <?php if ($est_en_cours): ?>
                                        <button onclick="ouvrirScore(<?= $match['id'] ?>, '<?= addslashes($nom1) ?>', '<?= addslashes($nom2) ?>', <?= $match['score1'] ?>, <?= $match['score2'] ?>)" class="text-[10px] font-bold text-indigo-300 hover:text-indigo-200 bg-indigo-500/20 hover:bg-indigo-500/30 border border-indigo-500/40 px-2 py-1.5 rounded transition">
                                            Score
                                        </button>
                                        <?php if ($est_termine): ?>
                                        <button onclick="rouvrirMatch(<?= $match['id'] ?>)" class="text-[10px] font-bold text-amber-300 hover:text-amber-200 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 px-2 py-1.5 rounded transition">
                                            Rouvrir
                                        </button>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            };
            ?>
            <div class="overflow-x-auto pb-8">
                <div class="bracket-section min-w-fit" data-bracket-section>
                    <svg class="bracket-svg"></svg>
                    <div class="bracket-rounds flex items-stretch gap-24 min-w-fit">
                        <!-- Gauche : Winners (haut) + Losers (bas) empiles -->
                        <div class="flex flex-col gap-16 min-w-fit justify-center">
                            <?php foreach ($main_sections as $section) $renderSection($section); ?>
                        </div>
                        <!-- Droite : Grande Finale, apex centre verticalement -->
                        <?php if ($gf_section): ?>
                        <div class="flex flex-col justify-center min-w-fit">
                            <?php $renderSection($gf_section); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL : Details du match -->
    <div id="modal-details" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/80 backdrop-blur-sm">
        <div class="glass-card border border-slate-700 rounded-2xl p-8 w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold">Details du match</h3>
                <button onclick="fermerModal('modal-details')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between bg-slate-900 rounded-lg p-4">
                    <span id="detail-nom1" class="text-sm font-bold"></span>
                    <span id="detail-score1" class="text-2xl font-bold text-indigo-400"></span>
                </div>
                <div class="text-center text-xs text-slate-500 font-bold uppercase tracking-widest">VS</div>
                <div class="flex items-center justify-between bg-slate-900 rounded-lg p-4">
                    <span id="detail-nom2" class="text-sm font-bold"></span>
                    <span id="detail-score2" class="text-2xl font-bold text-indigo-400"></span>
                </div>
                <div class="text-center">
                    <span id="detail-statut" class="text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL : Modifier le score -->
    <div id="modal-score" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/80 backdrop-blur-sm">
        <div class="glass-card border border-slate-700 rounded-2xl p-8 w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold">Modifier le score</h3>
                <button onclick="fermerModal('modal-score')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form id="form-score" class="space-y-4">
                <input type="hidden" id="score-match-id">
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <label id="score-label1" class="block text-xs font-bold text-slate-400 mb-2 truncate"></label>
                        <input type="number" id="score-input1" min="0" max="<?= $score_pour_gagner ?>" class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-center text-2xl font-bold outline-none transition">
                    </div>
                    <span class="text-slate-500 font-bold text-lg mt-5">-</span>
                    <div class="flex-1">
                        <label id="score-label2" class="block text-xs font-bold text-slate-400 mb-2 truncate"></label>
                        <input type="number" id="score-input2" min="0" max="<?= $score_pour_gagner ?>" class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded px-4 py-3 text-center text-2xl font-bold outline-none transition">
                    </div>
                </div>
                <p class="text-xs text-slate-500 text-center">BO<?= $best_of ?> — Premier a <?= $score_pour_gagner ?> manche(s) gagne.</p>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-3 rounded text-sm font-bold uppercase tracking-widest transition shadow-lg shadow-indigo-500/20">
                    Enregistrer le score
                </button>
            </form>
        </div>
    </div>

    <script>
        // ===== Connecteurs du bracket (overlay SVG mesure sur le DOM) =====
        const SVG_NS = 'http://www.w3.org/2000/svg';
        function drawConnectors() {
            document.querySelectorAll('[data-bracket-section]').forEach(section => {
                const svg = section.querySelector('.bracket-svg');
                if (!svg) return;
                const sRect = section.getBoundingClientRect();
                svg.setAttribute('width', section.offsetWidth);
                svg.setAttribute('height', section.offsetHeight);
                svg.setAttribute('viewBox', `0 0 ${section.offsetWidth} ${section.offsetHeight}`);
                while (svg.firstChild) svg.removeChild(svg.firstChild);

                section.querySelectorAll('[data-to]').forEach(src => {
                    const target = src.getAttribute('data-to');
                    if (!target) return;
                    const tgt = section.querySelector('[data-node="' + target + '"]');
                    if (!tgt) return;

                    const a = src.getBoundingClientRect();
                    const b = tgt.getBoundingClientRect();
                    const x1 = a.right - sRect.left;
                    const y1 = a.top - sRect.top + a.height / 2;
                    const x2 = b.left - sRect.left;
                    const y2 = b.top - sRect.top + b.height / 2;
                    const midX = x1 + (x2 - x1) / 2;
                    const won = src.getAttribute('data-won') === '1';

                    const path = document.createElementNS(SVG_NS, 'path');
                    // Sortie horizontale -> jonction verticale -> entree horizontale (look "arbre")
                    path.setAttribute('d', `M ${x1} ${y1} H ${midX} V ${y2} H ${x2}`);
                    path.setAttribute('fill', 'none');
                    path.setAttribute('stroke', won ? 'rgba(16,185,129,0.6)' : 'rgba(100,116,139,0.32)');
                    path.setAttribute('stroke-width', won ? '2' : '1.5');
                    path.setAttribute('stroke-linejoin', 'round');
                    svg.appendChild(path);
                });
            });
        }
        window.addEventListener('load', drawConnectors);
        window.addEventListener('resize', drawConnectors);

        // Lancer le tournoi
        function lancerTournoi() {
            if (!confirm('Lancer le tournoi ? Les participants ne pourront plus etre modifies.')) return;
            const formData = new FormData();
            formData.append('ajax_action', 'lancer_tournoi');
            fetch('bracket_live.php?id_tournoi=<?= $id_tournoi ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => { if (data.ok) location.reload(); });
        }

        function ouvrirDetails(id, nom1, nom2, s1, s2, statut) {
            document.getElementById('detail-nom1').textContent = nom1 || '—';
            document.getElementById('detail-nom2').textContent = nom2 || '—';
            document.getElementById('detail-score1').textContent = s1;
            document.getElementById('detail-score2').textContent = s2;
            const badge = document.getElementById('detail-statut');
            if (statut === 'termine') {
                badge.textContent = 'Termine';
                badge.className = 'text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/40';
            } else if (statut === 'en_cours') {
                badge.textContent = 'En cours';
                badge.className = 'text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full bg-amber-500/15 text-amber-400 border border-amber-500/40';
            } else {
                badge.textContent = 'En attente';
                badge.className = 'text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full bg-slate-700/60 text-slate-300 border border-slate-600';
            }
            document.getElementById('modal-details').classList.remove('hidden');
            document.getElementById('modal-details').classList.add('flex');
        }

        function ouvrirScore(id, nom1, nom2, s1, s2) {
            document.getElementById('score-match-id').value = id;
            document.getElementById('score-label1').textContent = nom1 || 'Joueur 1';
            document.getElementById('score-label2').textContent = nom2 || 'Joueur 2';
            document.getElementById('score-input1').value = s1;
            document.getElementById('score-input2').value = s2;
            document.getElementById('modal-score').classList.remove('hidden');
            document.getElementById('modal-score').classList.add('flex');
        }

        function fermerModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }

        // Fermer modals en cliquant en dehors
        ['modal-details', 'modal-score'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) fermerModal(id);
            });
        });

        // Soumettre le score
        document.getElementById('form-score').addEventListener('submit', function(e) {
            e.preventDefault();
            const id = document.getElementById('score-match-id').value;
            const s1 = document.getElementById('score-input1').value;
            const s2 = document.getElementById('score-input2').value;

            const formData = new FormData();
            formData.append('ajax_action', 'modifier_score');
            formData.append('id_match', id);
            formData.append('score1', s1);
            formData.append('score2', s2);

            fetch('bracket_live.php?id_tournoi=<?= $id_tournoi ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) location.reload();
                });
        });

        // Rouvrir un match
        function rouvrirMatch(id) {
            if (!confirm('Rouvrir ce match ? Le score sera remis a zero.')) return;
            const formData = new FormData();
            formData.append('ajax_action', 'rouvrir_match');
            formData.append('id_match', id);

            fetch('bracket_live.php?id_tournoi=<?= $id_tournoi ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) location.reload();
                });
        }
    </script>

<?php include '_theme.php'; ?>
</body>
</html>