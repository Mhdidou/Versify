<div align="center">

# 🏆 VERSIFY

### Plateforme Web de Gestion de Tournois Esports

*Créez, organisez et suivez vos compétitions de jeux vidéo, de l'inscription au podium final.*

</div>

---

## 1. Présentation du projet

**Versify** est une application web full-stack conçue pour résoudre un problème concret : l'organisation manuelle d'un tournoi esport est fastidieuse et source d'erreurs. Gérer les inscriptions, dessiner un arbre de compétition équilibré, suivre les scores en direct et faire progresser les vainqueurs de ronde en ronde demande un effort considérable lorsqu'on s'appuie sur des tableurs ou du papier.

Versify automatise l'intégralité de ce cycle de vie. La plateforme permet à n'importe quel utilisateur inscrit de :

- **Créer** des tournois dans plus de 90 jeux compétitifs, avec un choix de format (Single Elimination, Round Robin) et de configuration de match (Best of 1, 3 ou 5).
- **Gérer les participants** : ajout manuel vérifié en base, ajout en masse, gestion des forfaits, modification des noms d'affichage et mélange des têtes de série.
- **Recevoir des candidatures** : les joueurs postulent via un formulaire, l'organisateur valide ou refuse.
- **Générer automatiquement** un bracket grâce à un algorithme de seeding professionnel qui répartit équitablement les *byes*.
- **Piloter les matchs en direct** : saisie des scores en AJAX, détermination automatique du vainqueur et propagation vers la ronde suivante.
- **Clôturer** la compétition avec un classement final et un podium visuel.

L'interface adopte un design moderne (thème sombre par défaut, effets *glassmorphism*, bascule clair/sombre persistante) pensé pour une expérience fluide et responsive.

---

## 2. Stack technique

| Domaine | Technologie | Rôle |
|---------|-------------|------|
| **Backend** | PHP 8.3 | Logique métier, traitement des requêtes, génération des vues |
| **Accès données** | PDO (PHP Data Objects) | Couche d'abstraction avec **requêtes préparées** systématiques |
| **Base de données** | MySQL 8.4 | Persistance relationnelle (moteur MyISAM) |
| **Frontend** | HTML5 + Tailwind CSS 3 (CDN) | Structure et style *utility-first* |
| **Interactivité** | JavaScript (ES6) + Fetch API | Modales, filtres dynamiques, appels AJAX sans rechargement |
| **Visualisation** | jQuery 3.6 + jquery-bracket 0.11.1 | Rendu graphique des arbres de tournoi |
| **Sécurité** | `password_hash()` / `password_verify()` (bcrypt) | Hachage des mots de passe |
| **Sessions** | Sessions PHP natives + cookie « Se souvenir de moi » | Gestion de l'authentification |
| **Typographie** | Outfit (Google Fonts) | Identité visuelle |

---

## 3. Architecture de la base de données

La base `projet_database` repose sur **10 tables**. Le modèle relie systématiquement chaque entité au tournoi qui lui donne du sens, l'`id_utilisateur` servant de clé fonctionnelle pour relier les comptes aux tournois et participations.

### 3.1 Tables fondamentales

| Table | Description | Relations clés |
|-------|-------------|----------------|
| `utilisateur` | Comptes des utilisateurs : identifiant unique, email, pays, date de naissance, mot de passe haché, photo de profil. | Référencée par `tournoi.hote` et `participant.nom_participant` |
| `tournoi` | Cœur du système. Stocke la configuration : jeu, format, `best_of`, date, limite de participants, **statut** (`brouillon` → `en_cours` → `termine`), visibilité, lien de partage public. | `hote` → `utilisateur.id_utilisateur` |
| `participant` | Inscription d'un joueur dans un tournoi précis. Porte le **seed** (position dans l'arbre), le `statut` (`actif`/`forfait`), le `nom_affichage` éditable et l'état de `checked_in`. | `id_tournoi` → `tournoi.id` |
| `match_tournoi` | Une rencontre. Identifiée par `ronde` + `position`, elle relie deux participants, conserve les scores, le `gagnant_id` et le `statut_match`. | `id_tournoi` → `tournoi.id` ; `id_participant1/2` → `participant.id` |

### 3.2 Tables fonctionnelles (modules avancés)

| Table | Description | Relations clés |
|-------|-------------|----------------|
| `inscription_tournoi` | Demandes de participation externes (candidatures) avec message et `statut_inscription` (`en_attente`/`accepte`/`refuse`). | `id_tournoi` → `tournoi.id` |
| `reclamation` | Contestations de résultats déposées par les joueurs et traitées par l'organisateur. | `id_match` → `match_tournoi.id` |
| `statistique_joueur` | Statistiques agrégées par joueur : matchs joués, victoires, défaites, podiums, scores cumulés. | `id_utilisateur` → `utilisateur.id_utilisateur` |
| `classement_tournoi` | Classement final figé d'un tournoi terminé (`position_finale`). | `id_participant` → `participant.id` |
| `historique_match` | Journal chronologique des résultats validés. | `id_tournoi` → `tournoi.id` |
| `jeu_catalogue` | Bibliothèque de jeux avec catégorie et couleur d'accent pour l'affichage. | Indépendante |

### 3.3 Schéma des relations

```
utilisateur ──1:N──> tournoi ──1:N──> participant ──0:2──> match_tournoi
     │                  │                                        ▲
     │                  ├──1:N──> inscription_tournoi            │
     │                  ├──1:N──> classement_tournoi ────────────┘
     │                  └──1:N──> reclamation
     └──1:1──> statistique_joueur
```

> **Note d'implémentation :** le moteur MyISAM ne gère pas les contraintes de clés étrangères physiques. L'intégrité référentielle est donc assurée **applicativement** au niveau du code PHP, via des vérifications systématiques avant chaque écriture (existence du tournoi, propriété par l'hôte, absence de doublon).

### 3.4 Comment le modèle soutient les fonctionnalités

- Le champ `statut_tournoi` agit comme une **machine à états** : tant qu'il vaut `brouillon`, l'organisateur peut modifier les participants ; dès qu'il passe à `en_cours`, l'accès à la gestion est verrouillé et seul le suivi des matchs reste possible.
- Le couple `(ronde, position)` dans `match_tournoi` encode la **topologie de l'arbre** sans nécessiter de table d'arêtes : le vainqueur d'un match en position *p* à la ronde *r* est propagé vers la position `ceil(p/2)` à la ronde `r+1`.
- Le `seed` du participant pilote le placement initial, garantissant que les têtes de série ne se rencontrent qu'aux phases finales.

---

## 4. Sous le capot (fonctionnement)

### 4.1 Connexion à la base de données

Chaque script ouvre une connexion PDO configurée en mode exception, ce qui permet une gestion d'erreurs propre via `try/catch` :

```php
$pdo = new PDO("mysql:host=localhost;dbname=projet_database;port=3306", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

**Toutes** les interactions SQL passent par des requêtes préparées avec paramètres nommés, ce qui neutralise les injections SQL :

```php
$stmt = $pdo->prepare("SELECT * FROM tournoi WHERE id = :id AND hote = :hote");
$stmt->execute([":id" => $id_tournoi, ":hote" => $hote_connecte]);
```

### 4.2 Authentification et routage

L'application suit un modèle **multi-page** : chaque fichier PHP est à la fois un contrôleur et une vue. Le « routage » repose sur le serveur web (Apache) qui sert directement les fichiers.

Le flux d'authentification est sécurisé de bout en bout :

1. **Inscription** (`signup.php`) — le mot de passe est haché avec bcrypt avant insertion :
   ```php
   ":mdp" => password_hash($_POST['mdp'], PASSWORD_DEFAULT)
   ```
2. **Connexion** (`login.php`) — le hash est récupéré puis vérifié, sans jamais exposer le mot de passe en clair :
   ```php
   if ($row && password_verify($_POST['pass'], $row['mdp'])) {
       $_SESSION['id_utilisateur'] = $row['id_utilisateur'];
   }
   ```
   Une case **« Se souvenir de moi »** dépose un cookie valable 7 jours qui restaure automatiquement la session.
3. **Garde de session** — chaque page protégée débute par un contrôle qui redirige les visiteurs non authentifiés :
   ```php
   if (!isset($_SESSION['id_utilisateur'])) { header("Location: login.php"); die(); }
   ```

### 4.3 Le moteur de tournoi

C'est le cœur algorithmique du projet, concentré dans `bracket_live.php` et `_helpers.php`.

**Génération du seeding (Single Elimination)** — un algorithme récursif construit l'ordre de placement standard des tournois professionnels (1 vs N, 2 vs N-1...), garantissant un arbre équilibré même lorsque le nombre de joueurs n'est pas une puissance de 2 (les *byes* sont attribués aux meilleures têtes de série) :

```php
function genererSeeding($n) {
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
```

**Round Robin** — une méthode de rotation circulaire génère toutes les confrontations possibles, en gérant un adversaire « fantôme » si le nombre de joueurs est impair.

**Propagation des vainqueurs** — lorsqu'un score atteint le seuil `ceil(best_of / 2)`, le vainqueur est déterminé et automatiquement placé dans le match suivant (sauf en Round Robin où les matchs sont indépendants).

### 4.4 Interaction frontend ↔ backend (AJAX)

Pour le suivi en direct, le frontend dialogue avec le backend sans rechargement de page. Le fichier `bracket_live.php` joue un double rôle : page HTML **et** point d'entrée API. Il détecte les requêtes asynchrones via le champ `ajax_action` et répond en JSON :

```javascript
fetch('bracket_live.php?id_tournoi=' + tournoiId, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if (data.ok) location.reload(); });
```

Côté serveur :

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    // ... lancer_tournoi | modifier_score | rouvrir_match
    echo json_encode(["ok" => true]);
    die();
}
```

Trois actions sont exposées : **lancer le tournoi**, **modifier un score** et **rouvrir un match** (remise à zéro qui annule aussi la propagation du vainqueur).

### 4.5 Mutualisation du code

- `_helpers.php` centralise les fonctions transverses : génération de lien de partage unique, mise à jour des statistiques, calcul du classement final, lecture du catalogue de jeux.
- `_games.php` expose la liste partagée des jeux.
- `_theme.php` est inclus en pied de chaque page pour injecter le bouton de bascule clair/sombre et persister le choix dans `localStorage`.

---

## 5. Installation et configuration

### 5.1 Prérequis

- Un environnement **XAMPP** (ou tout stack Apache + PHP 8.x + MySQL 8.x).
- Un navigateur moderne.

### 5.2 Étapes

**1. Déployer les fichiers**

Placez le dossier du projet dans le répertoire racine du serveur web (par défaut `htdocs/` sous XAMPP).

**2. Créer et importer la base de données**

Depuis phpMyAdmin, créez une base nommée `projet_database`, puis importez les fichiers SQL **dans cet ordre** :

```
sql/utilisateur.sql
sql/tournoi.sql
sql/participants.sql
sql/matchs.sql
sql/v2_schema.sql      ← contient les ALTER TABLE et les tables des modules avancés
```

> `v2_schema.sql` agrandit notamment la colonne `mdp` en `VARCHAR(255)` pour accueillir les hachages bcrypt. Cette étape est **indispensable** au bon fonctionnement de l'authentification.

**3. Configurer la connexion**

Les identifiants de connexion sont définis dans chaque script PHP. Pour la configuration XAMPP par défaut, aucune modification n'est nécessaire :

```php
new PDO("mysql:host=localhost;dbname=projet_database;port=3306", "root", "");
```

En production, adaptez l'hôte, l'utilisateur et le mot de passe.

**4. Lancer l'application**

Démarrez Apache et MySQL via le panneau XAMPP, puis accédez à :

```
http://localhost/projet/index.php
```

**5. Première utilisation**

Créez un compte via `signup.php`, connectez-vous, puis créez votre premier tournoi depuis le tableau de bord.

---

## 6. Structure du projet

```
projet/
├── sql/                            → Schémas SQL (5 fichiers)
├── docs/                           → Documentation et diagrammes
├── uploads/avatars/                → Photos de profil
│
├── _helpers.php                    → Fonctions transverses (seeding, stats, classement)
├── _games.php                      → Catalogue de jeux partagé
├── _theme.php                      → Bascule clair/sombre (incluse partout)
│
├── index.php                       → Page d'accueil publique
├── login.php / signup.php / logout.php   → Authentification
├── dashboard.php                   → Tableau de bord
├── profil.php                      → Profil + upload photo
│
├── tournoi.php / gerer_tournoi.php → Création et édition de tournoi
├── explorer_tournois.php           → Découverte et candidature
├── gerer_inscriptions.php          → Validation des candidatures
├── participants.php                → Gestion (avant lancement)
├── participants_apres_lancement.php → Gestion (après lancement)
├── check_in.php                    → Confirmation de présence
│
├── bracket_live.php                → Moteur de tournoi + suivi AJAX
├── apercu_visuel.php               → Aperçu visuel (jquery-bracket)
├── classement.php                  → Podium et classement final
├── historique.php                  → Journal des matchs
├── reclamation.php                 → Gestion des litiges
├── tournoi_public.php              → Vue publique partageable
└── jeux_catalogue.php              → Bibliothèque de jeux
```

---

## 7. Points forts techniques

- **Sécurité applicative** : requêtes préparées partout, mots de passe hachés bcrypt, vérification de propriété sur chaque action sensible.
- **Algorithme de seeding** conforme aux standards des tournois professionnels, avec gestion automatique des *byes*.
- **Architecture pilotée par états** : le statut du tournoi verrouille ou déverrouille les fonctionnalités au bon moment.
- **Expérience temps réel** via AJAX sans dépendance à un framework JavaScript lourd.
- **Thème clair/sombre** persistant, intégré sans surcharge.

---

<div align="center">

*Versify — Conçu et développé comme un projet logiciel personnel et indépendant.*

</div>
