<?php
// ===================================================================
// Fichier d'authentification - gestion securisee du "se souvenir de moi"
// ===================================================================
//
// Schema selector/validator :
//   - Le cookie contient "selector:validator" (deux valeurs aleatoires).
//   - En base on ne stocke QUE le hash SHA-256 du validator.
//   - La verification se fait par selector (indexe) puis comparaison
//     a temps constant (hash_equals) du hash du validator.
//
// Avantages vs l'ancien systeme (cookie = id_utilisateur en clair) :
//   - Impossible de se faire passer pour un autre utilisateur en
//     devinant/forgeant la valeur du cookie.
//   - Le secret en clair n'est jamais stocke en base : une fuite de la
//     table ne permet pas de reconstruire un cookie valide.
//   - Expiration verifiee cote serveur + rotation a chaque usage.
// ===================================================================

const REMEMBER_COOKIE   = 'versify_remember';
const REMEMBER_DUREE    = 7 * 24 * 60 * 60; // 7 jours

/**
 * Creer la table des jetons si elle n'existe pas (idempotent).
 */
function auth_init_table(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `auth_remember_token` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_utilisateur` VARCHAR(255) NOT NULL,
            `selector` CHAR(32) NOT NULL,
            `hashed_validator` CHAR(64) NOT NULL,
            `expires` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_selector` (`selector`),
            KEY `idx_utilisateur` (`id_utilisateur`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Determiner si le cookie doit etre marque "Secure" (HTTPS uniquement).
 * Reste compatible avec un dev en HTTP local.
 */
function auth_cookie_secure(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
}

/**
 * Poser un cookie "se souvenir de moi" securise pour un utilisateur.
 * Genere un nouveau jeton, le stocke (hash) et envoie le cookie.
 */
function auth_remember_creer(PDO $pdo, string $id_utilisateur): void {
    auth_init_table($pdo);

    $selector  = bin2hex(random_bytes(16)); // 32 caracteres
    $validator = bin2hex(random_bytes(32)); // 64 caracteres
    $expires   = time() + REMEMBER_DUREE;

    $pdo->prepare(
        "INSERT INTO auth_remember_token (id_utilisateur, selector, hashed_validator, expires)
         VALUES (:u, :s, :h, :e)"
    )->execute([
        ":u" => $id_utilisateur,
        ":s" => $selector,
        ":h" => hash('sha256', $validator),
        ":e" => date('Y-m-d H:i:s', $expires),
    ]);

    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => auth_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Verifier le cookie "se souvenir de moi".
 * Retourne l'id_utilisateur si le jeton est valide, sinon null.
 * En cas de succes, le jeton est tourne (rotation) pour limiter le rejeu.
 */
function auth_remember_verifier(PDO $pdo): ?string {
    if (empty($_COOKIE[REMEMBER_COOKIE]) || strpos($_COOKIE[REMEMBER_COOKIE], ':') === false) {
        return null;
    }

    [$selector, $validator] = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);

    try {
        auth_init_table($pdo);
        $stmt = $pdo->prepare(
            "SELECT id, id_utilisateur, hashed_validator, expires
             FROM auth_remember_token WHERE selector = :s"
        );
        $stmt->execute([":s" => $selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }

    if (!$row) {
        return null;
    }

    // Jeton expire -> on le supprime
    if (strtotime($row['expires']) < time()) {
        $pdo->prepare("DELETE FROM auth_remember_token WHERE id = :id")->execute([":id" => $row['id']]);
        auth_remember_oublier($pdo);
        return null;
    }

    // Comparaison a temps constant
    $attendu = hash('sha256', $validator);
    if (!hash_equals($row['hashed_validator'], $attendu)) {
        return null;
    }

    // Rotation : on supprime l'ancien jeton et on en cree un neuf
    $pdo->prepare("DELETE FROM auth_remember_token WHERE id = :id")->execute([":id" => $row['id']]);
    auth_remember_creer($pdo, $row['id_utilisateur']);

    return $row['id_utilisateur'];
}

/**
 * Oublier (supprimer) le jeton courant cote base et cote navigateur.
 */
function auth_remember_oublier(PDO $pdo): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE]) && strpos($_COOKIE[REMEMBER_COOKIE], ':') !== false) {
        [$selector] = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        try {
            auth_init_table($pdo);
            $pdo->prepare("DELETE FROM auth_remember_token WHERE selector = :s")->execute([":s" => $selector]);
        } catch (Exception $e) { /* table absente : rien a faire */ }
    }

    setcookie(REMEMBER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => auth_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE]);
}
