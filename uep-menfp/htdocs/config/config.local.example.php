<?php
/**
 * Modèle de configuration locale.
 *
 * Copiez ce fichier en « config.local.php » et renseignez les valeurs données
 * par votre hébergeur, ou laissez install.php le générer pour vous.
 *
 * NE JAMAIS publier config.local.php : il contient le mot de passe MySQL.
 */
return [
    'UEP_APP_NAME'   => 'UEP - MENFP',
    'UEP_APP_ENV'    => 'production',   // production | development
    'UEP_TIMEZONE'   => 'America/Port-au-Prince',

    // Laisser vide : l'application déduit son adresse de la requête en cours.
    'UEP_APP_URL'    => '',

    // Valeurs fournies par le panneau de l'hébergeur (VistaPanel sur ByetHost).
    'UEP_DB_HOST'    => 'sqlXXX.byethost.com',
    'UEP_DB_PORT'    => 3306,
    'UEP_DB_NAME'    => 'bX_00000000_uep',
    'UEP_DB_USER'    => 'bX_00000000',
    'UEP_DB_PASS'    => '',

    // Chaîne aléatoire d'au moins 32 caractères, propre à votre installation.
    'UEP_APP_SECRET' => '',
];
