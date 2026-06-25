<?php
const REMEMBER_COOKIE   = 'versify_remember';
const REMEMBER_DUREE    = 7 * 24 * 60 * 60; 

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


function auth_cookie_secure(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
}


function auth_remember_creer(PDO $pdo, string $id_utilisateur): void {
    auth_init_table($pdo);

    $selector  = bin2hex(random_bytes(16)); 
    $validator = bin2hex(random_bytes(32)); 
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

    if (strtotime($row['expires']) < time()) {
        $pdo->prepare("DELETE FROM auth_remember_token WHERE id = :id")->execute([":id" => $row['id']]);
        auth_remember_oublier($pdo);
        return null;
    }

    $attendu = hash('sha256', $validator);
    if (!hash_equals($row['hashed_validator'], $attendu)) {
        return null;
    }

    $pdo->prepare("DELETE FROM auth_remember_token WHERE id = :id")->execute([":id" => $row['id']]);
    auth_remember_creer($pdo, $row['id_utilisateur']);

    return $row['id_utilisateur'];
}

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
