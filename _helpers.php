<?php
// ===================================================================
// Fichier utilitaire - fonctions partagees entre les pages
// ===================================================================

/**
 * Retourner le label et les classes CSS du statut d'un tournoi
 */
function statut_tournoi(string $date_depart, string $statut_db = ''): array {
    if ($statut_db === 'termine') {
        return ['label' => 'Terminé', 'class' => 'bg-slate-700/60 text-slate-300 border-slate-600', 'dot' => 'bg-slate-400'];
    }
    if ($statut_db === 'en_cours') {
        return ['label' => 'En Direct', 'class' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40', 'dot' => 'bg-emerald-400 animate-pulse'];
    }
    $today  = new DateTime('today');
    $depart = new DateTime($date_depart);
    if ($depart < $today)  return ['label' => 'Terminé',   'class' => 'bg-slate-700/60 text-slate-300 border-slate-600',     'dot' => 'bg-slate-400'];
    if ($depart == $today) return ['label' => 'En Direct', 'class' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/40', 'dot' => 'bg-emerald-400 animate-pulse'];
    return ['label' => 'À venir', 'class' => 'bg-amber-500/15 text-amber-400 border-amber-500/40', 'dot' => 'bg-amber-400'];
}

/**
 * Generer un lien de partage unique pour un tournoi
 */
function genererLienPartage(PDO $pdo, int $id_tournoi): string {
    $caracteres = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $longueur = 12;

    do {
        $code = '';
        for ($i = 0; $i < $longueur; $i++) {
            $code .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }
        $stmtVerif = $pdo->prepare("SELECT id FROM tournoi WHERE lien_partage = :code");
        $stmtVerif->execute([":code" => $code]);
    } while ($stmtVerif->fetch());

    $pdo->prepare("UPDATE tournoi SET lien_partage = :code WHERE id = :id")
        ->execute([":code" => $code, ":id" => $id_tournoi]);

    return $code;
}

/**
 * Mettre a jour les statistiques d'un joueur apres un match
 */
function mettreAJourStats(PDO $pdo, string $id_utilisateur, bool $victoire, int $score_marque, int $score_encaisse): void {
    $pdo->prepare(
        "INSERT INTO statistique_joueur (id_utilisateur, matchs_joues, victoires, defaites, score_total_marque, score_total_encaisse)
         VALUES (:u, 1, :v, :d, :sm, :se)
         ON DUPLICATE KEY UPDATE
             matchs_joues         = matchs_joues + 1,
             victoires            = victoires + :v,
             defaites             = defaites + :d,
             score_total_marque   = score_total_marque + :sm,
             score_total_encaisse = score_total_encaisse + :se"
    )->execute([
        ":u"  => $id_utilisateur,
        ":v"  => $victoire ? 1 : 0,
        ":d"  => $victoire ? 0 : 1,
        ":sm" => $score_marque,
        ":se" => $score_encaisse,
    ]);
}

/**
 * Calculer et enregistrer le classement final d'un tournoi Single Elimination
 */
function calculerClassement(PDO $pdo, int $id_tournoi): void {
    // Supprimer l'ancien classement
    $pdo->prepare("DELETE FROM classement_tournoi WHERE id_tournoi = :id")->execute([":id" => $id_tournoi]);

    // Recuperer le nombre total de rondes
    $stmtR = $pdo->prepare("SELECT MAX(ronde) FROM match_tournoi WHERE id_tournoi = :id");
    $stmtR->execute([":id" => $id_tournoi]);
    $max_ronde = (int) $stmtR->fetchColumn();

    if ($max_ronde === 0) return;

    $position = 1;

    // 1er = gagnant de la finale
    $stmtFinale = $pdo->prepare("SELECT gagnant_id FROM match_tournoi WHERE id_tournoi = :id AND ronde = :r AND gagnant_id IS NOT NULL");
    $stmtFinale->execute([":id" => $id_tournoi, ":r" => $max_ronde]);
    $champion = $stmtFinale->fetchColumn();

    if ($champion) {
        $pdo->prepare("INSERT INTO classement_tournoi (id_tournoi, id_participant, position_finale) VALUES (:t, :p, :pos)")
            ->execute([":t" => $id_tournoi, ":p" => $champion, ":pos" => $position++]);
    }

    // 2eme = perdant de la finale
    $stmtFinaleMatch = $pdo->prepare("SELECT id_participant1, id_participant2, gagnant_id FROM match_tournoi WHERE id_tournoi = :id AND ronde = :r");
    $stmtFinaleMatch->execute([":id" => $id_tournoi, ":r" => $max_ronde]);
    $finale = $stmtFinaleMatch->fetch(PDO::FETCH_ASSOC);

    if ($finale && $finale['gagnant_id']) {
        $perdant_finale = ($finale['gagnant_id'] == $finale['id_participant1']) ? $finale['id_participant2'] : $finale['id_participant1'];
        if ($perdant_finale) {
            $pdo->prepare("INSERT INTO classement_tournoi (id_tournoi, id_participant, position_finale) VALUES (:t, :p, :pos)")
                ->execute([":t" => $id_tournoi, ":p" => $perdant_finale, ":pos" => $position++]);
        }
    }

    // 3eme-4eme = perdants des demi-finales
    if ($max_ronde >= 2) {
        $stmtDemi = $pdo->prepare("SELECT id_participant1, id_participant2, gagnant_id FROM match_tournoi WHERE id_tournoi = :id AND ronde = :r AND gagnant_id IS NOT NULL");
        $stmtDemi->execute([":id" => $id_tournoi, ":r" => $max_ronde - 1]);
        foreach ($stmtDemi->fetchAll(PDO::FETCH_ASSOC) as $demi) {
            $perdant = ($demi['gagnant_id'] == $demi['id_participant1']) ? $demi['id_participant2'] : $demi['id_participant1'];
            if ($perdant) {
                $pdo->prepare("INSERT IGNORE INTO classement_tournoi (id_tournoi, id_participant, position_finale) VALUES (:t, :p, :pos)")
                    ->execute([":t" => $id_tournoi, ":p" => $perdant, ":pos" => $position]);
            }
        }
        $position += 2; // 3eme ex-aequo

        // Continuer pour les rondes precedentes
        for ($r = $max_ronde - 2; $r >= 1; $r--) {
            $stmtRonde = $pdo->prepare("SELECT id_participant1, id_participant2, gagnant_id FROM match_tournoi WHERE id_tournoi = :id AND ronde = :r AND gagnant_id IS NOT NULL");
            $stmtRonde->execute([":id" => $id_tournoi, ":r" => $r]);
            $perdants_ronde = [];
            foreach ($stmtRonde->fetchAll(PDO::FETCH_ASSOC) as $match) {
                $perdant = ($match['gagnant_id'] == $match['id_participant1']) ? $match['id_participant2'] : $match['id_participant1'];
                if ($perdant) $perdants_ronde[] = $perdant;
            }
            foreach ($perdants_ronde as $pid) {
                $pdo->prepare("INSERT IGNORE INTO classement_tournoi (id_tournoi, id_participant, position_finale) VALUES (:t, :p, :pos)")
                    ->execute([":t" => $id_tournoi, ":p" => $pid, ":pos" => $position]);
            }
            $position += count($perdants_ronde);
        }
    }

    // Mettre a jour le statut du tournoi
    $pdo->prepare("UPDATE tournoi SET statut_tournoi = 'termine' WHERE id = :id")->execute([":id" => $id_tournoi]);

}

/**
 * Obtenir les infos d'un jeu depuis le catalogue (couleur, categorie)
 * Retourne null si le jeu n'est pas trouve ou si la table n'existe pas
 */
function getJeuInfo(PDO $pdo, string $nom_jeu): ?array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM jeu_catalogue WHERE nom = :nom");
        $stmt->execute([":nom" => $nom_jeu]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception) {
        return null;
    }
}
?>
