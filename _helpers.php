<?php

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


function calculerClassement(PDO $pdo, int $id_tournoi): void {
    $pdo->prepare("DELETE FROM classement_tournoi WHERE id_tournoi = :id")->execute([":id" => $id_tournoi]);
    $stmtR = $pdo->prepare("SELECT MAX(manche) FROM match_tournoi WHERE id_tournoi = :id");
    $stmtR->execute([":id" => $id_tournoi]);
    $max_manche = (int) $stmtR->fetchColumn();

    if ($max_manche === 0) return;

    $position = 1;

    $stmtFinale = $pdo->prepare("SELECT gagnant_id FROM match_tournoi WHERE id_tournoi = :id AND manche = :r AND gagnant_id IS NOT NULL");
    $stmtFinale->execute([":id" => $id_tournoi, ":r" => $max_manche]);
    $champion = $stmtFinale->fetchColumn();

    if ($champion) {
        $pdo->prepare("INSERT INTO classement_tournoi (id_tournoi, id_participant, position_finale) VALUES (:t, :p, :pos)")
            ->execute([":t" => $id_tournoi, ":p" => $champion, ":pos" => $position++]);
    }
    $stmtFinaleMatch = $pdo->prepare("SELECT id_participant1, id_participant2, gagnant_id FROM match_tournoi WHERE id_tournoi = :id AND manche = :r");
    $stmtFinaleMatch->execute([":id" => $id_tournoi, ":r" => $max_manche]);
    $finale = $stmtFinaleMatch->fetch(PDO::FETCH_ASSOC);

    if ($finale && $finale['gagnant_id']) {
        $perdant_finale = ($finale['gagnant_id'] == $finale['id_participant1']) ? $finale['id_participant2'] : $finale['id_participant1'];
        if ($perdant_finale) {
            $pdo->prepare("INSERT INTO classement_tournoi (id_tournoi, id_participant, position_finale) VALUES (:t, :p, :pos)")
                ->execute([":t" => $id_tournoi, ":p" => $perdant_finale, ":pos" => $position++]);
        }
    }

    if ($max_manche >= 2) {
        $stmtDemi = $pdo->prepare("SELECT id_participant1, id_participant2, gagnant_id FROM match_tournoi WHERE id_tournoi = :id AND manche = :r AND gagnant_id IS NOT NULL");
        $stmtDemi->execute([":id" => $id_tournoi, ":r" => $max_manche - 1]);
        foreach ($stmtDemi->fetchAll(PDO::FETCH_ASSOC) as $demi) {
            $perdant = ($demi['gagnant_id'] == $demi['id_participant1']) ? $demi['id_participant2'] : $demi['id_participant1'];
            if ($perdant) {
                $pdo->prepare("INSERT IGNORE INTO classement_tournoi (id_tournoi, id_participant, position_finale) VALUES (:t, :p, :pos)")
                    ->execute([":t" => $id_tournoi, ":p" => $perdant, ":pos" => $position]);
            }
        }
        $position += 2; 

        for ($r = $max_manche - 2; $r >= 1; $r--) {
            $stmtManche = $pdo->prepare("SELECT id_participant1, id_participant2, gagnant_id FROM match_tournoi WHERE id_tournoi = :id AND manche = :r AND gagnant_id IS NOT NULL");
            $stmtManche->execute([":id" => $id_tournoi, ":r" => $r]);
            $perdants_manche = [];
            foreach ($stmtManche->fetchAll(PDO::FETCH_ASSOC) as $match) {
                $perdant = ($match['gagnant_id'] == $match['id_participant1']) ? $match['id_participant2'] : $match['id_participant1'];
                if ($perdant) $perdants_manche[] = $perdant;
            }
            foreach ($perdants_manche as $pid) {
                $pdo->prepare("INSERT IGNORE INTO classement_tournoi (id_tournoi, id_participant, position_finale) VALUES (:t, :p, :pos)")
                    ->execute([":t" => $id_tournoi, ":p" => $pid, ":pos" => $position]);
            }
            $position += count($perdants_manche);
        }
    }

    $pdo->prepare("UPDATE tournoi SET statut_tournoi = 'termine' WHERE id = :id")->execute([":id" => $id_tournoi]);

}


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
