<?php
// ===================================================================
// Migration V3 - Scheduling & metadata
// Ajoute : tournoi.fuseau_horaire, tournoi.reglement,
//          match_tournoi.heure_prevue, match_tournoi.type_bracket
// Idempotent : verifie information_schema avant chaque ALTER.
// A executer une seule fois depuis le navigateur : /migrate_v3.php
// ===================================================================

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

/**
 * Ajouter une colonne uniquement si elle n'existe pas deja.
 */
function ajouterColonne(PDO $pdo, string $table, string $colonne, string $definition): void {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([":t" => $table, ":c" => $colonne]);

    if ((int) $stmt->fetchColumn() > 0) {
        echo "[=] $table.$colonne existe deja, ignore.\n";
        return;
    }

    // Identifiants non parametrables : injectes directement (valeurs fixes du code).
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$colonne` $definition");
    echo "[+] $table.$colonne ajoute.\n";
}

try {
    ajouterColonne($pdo, 'tournoi', 'fuseau_horaire',
        "VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Africa/Casablanca'");

    ajouterColonne($pdo, 'tournoi', 'reglement',
        "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");

    ajouterColonne($pdo, 'match_tournoi', 'heure_prevue',
        "DATETIME DEFAULT NULL");

    ajouterColonne($pdo, 'match_tournoi', 'type_bracket',
        "VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");

    echo "\nMigration V3 terminee avec succes.\n";
} catch (PDOException $e) {
    http_response_code(500);
    echo "ERREUR : " . $e->getMessage() . "\n";
}
