<?php
/**
 * tools/creer-admin.php — Création d'un administrateur en ligne de commande.
 *
 * Réservé aux serveurs disposant d'un accès SSH (VPS, serveur dédié). Sur un
 * hébergement mutualisé sans ligne de commande, utilisez install.php ou
 * database/creer-admin.sql.
 *
 * Usage :
 *   php tools/creer-admin.php "Nom Complet" adresse@menfp.gouv.ht
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

require dirname(__DIR__) . '/config/config.php';

spl_autoload_register(static function (string $classe): void {
    $fichier = RACINE_APP . '/core/' . $classe . '.php';
    if (is_file($fichier)) {
        require_once $fichier;
    }
});

$nom = $argv[1] ?? '';
$email = mb_strtolower(trim($argv[2] ?? ''));

if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Usage : php tools/creer-admin.php \"Nom Complet\" adresse@exemple.ht\n");
}

// Le mot de passe est saisi de façon interactive : il ne figure jamais dans
// l'historique du shell ni dans la liste des processus.
echo 'Mot de passe (au moins ' . LONGUEUR_MDP_MIN . " caractères) : ";
system('stty -echo 2>/dev/null');
$mdp = trim((string)fgets(STDIN));
system('stty echo 2>/dev/null');
echo PHP_EOL;

if (strlen($mdp) < LONGUEUR_MDP_MIN
    || !preg_match('/[a-z]/', $mdp)
    || !preg_match('/[A-Z]/', $mdp)
    || !preg_match('/\d/', $mdp)
) {
    exit("Mot de passe trop faible : au moins " . LONGUEUR_MDP_MIN . " caractères, une minuscule, une majuscule et un chiffre.\n");
}

try {
    $pdo = Database::pdo();

    $roleId = $pdo->query("SELECT id FROM roles WHERE nom_role = 'administrateur' LIMIT 1")->fetchColumn();
    if ($roleId === false) {
        exit("Le rôle « administrateur » est absent : importez database/schema.sql.\n");
    }

    $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);

    if ($stmt->fetchColumn()) {
        exit("Un compte utilise déjà cette adresse.\n");
    }

    $pdo->prepare(
        'INSERT INTO utilisateurs (nom_complet, email, mot_de_passe_hash, role_id, institution_type, actif, doit_changer_mdp)
         VALUES (:nom, :email, :hash, :role, \'MENFP\', 1, 0)'
    )->execute([
        ':nom'   => mb_substr($nom, 0, 150),
        ':email' => mb_substr($email, 0, 150),
        ':hash'  => password_hash($mdp, PASSWORD_DEFAULT),
        ':role'  => (int)$roleId,
    ]);

    echo "Compte administrateur créé : {$email}\n";
} catch (Throwable $e) {
    exit('Erreur : ' . $e->getMessage() . "\n");
}
