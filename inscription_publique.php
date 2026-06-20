<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    die();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_tournoi'])) {
    header("Location: dashboard.php");
    die();
}

$utilisateur = $_SESSION['id_utilisateur'];
$id_tournoi = (int) $_POST['id_tournoi'];
$message_joueur = trim($_POST['message'] ?? '');
$code = $_POST['code'] ?? '';

try {
    require_once __DIR__ . '/config.php';

    // Verifier que le tournoi existe et que l'inscription est ouverte
    $stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND inscription_ouverte = 1");
    $stmt->execute([":id" => $id_tournoi]);
    $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournoi) {
        header("Location: index.php");
        die();
    }

    // Verifier doublon
    $stmtDup = $pdo->prepare("SELECT id FROM inscription_tournoi WHERE id_tournoi = :id AND id_utilisateur = :u");
    $stmtDup->execute([":id" => $id_tournoi, ":u" => $utilisateur]);

    if ($stmtDup->fetch()) {
        // Deja inscrit, rediriger
        header("Location: tournoi_public.php?code=" . urlencode($code));
        die();
    }

    // Verifier si deja participant
    $stmtP = $pdo->prepare("SELECT id FROM participant WHERE id_tournoi = :id AND nom_participant = :u");
    $stmtP->execute([":id" => $id_tournoi, ":u" => $utilisateur]);

    if ($stmtP->fetch()) {
        header("Location: tournoi_public.php?code=" . urlencode($code));
        die();
    }

    // Inserer la demande
    $stmtInsert = $pdo->prepare("INSERT INTO inscription_tournoi (id_tournoi, id_utilisateur, message, statut_inscription) VALUES (:id, :u, :msg, 'en_attente')");
    $stmtInsert->execute([":id" => $id_tournoi, ":u" => $utilisateur, ":msg" => $message_joueur ?: null]);

    // Notifier l'organisateur
    try {
        $stmtNotif = $pdo->prepare("INSERT INTO notification (id_destinataire, type_notif, titre, message, lien) VALUES (:dest, 'tournoi', :titre, :msg, :lien)");
        $stmtNotif->execute([
            ":dest" => $tournoi['hote'],
            ":titre" => "Nouvelle inscription",
            ":msg" => "$utilisateur souhaite rejoindre \"" . $tournoi['nom'] . "\".",
            ":lien" => "gerer_inscriptions.php?id_tournoi=$id_tournoi"
        ]);
    } catch (Exception $e) {}

    header("Location: tournoi_public.php?code=" . urlencode($code));
    die();

} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>
