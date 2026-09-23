<?php
/**
 * Shown when the application cannot serve because it is not configured.
 *
 * Standalone rather than using a layout: the layout reads settings, and the
 * settings live in the database this page exists to say is unreachable.
 *
 * @var string      $reason    'missing_env' or 'database'
 * @var string|null $installer Relative path to the installer, when it exists.
 */

$isDatabase = ($reason ?? '') === 'database';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Installation requise</title>
<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 2rem 1rem;
        background: #fbfaf8; color: #1a1a1a;
        font: 400 16px/1.65 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    .card {
        width: 100%; max-width: 560px; background: #fff;
        border: 1px solid #e3ded6; border-radius: 3px; padding: 2.5rem;
    }
    .eyebrow { margin: 0 0 .35rem; font-size: .72rem; letter-spacing: .2em; text-transform: uppercase; color: #6f6a63; }
    h1 { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 500; line-height: 1.2; }
    p { margin: 0 0 1rem; color: #4a463f; }
    ol { margin: 0 0 1.5rem; padding-left: 1.2rem; color: #4a463f; }
    li { margin-bottom: .4rem; }
    code {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .86em;
        background: #f4f1ec; padding: .1rem .35rem; border-radius: 2px;
    }
    .button {
        display: inline-flex; align-items: center; justify-content: center;
        padding: .85rem 1.75rem; background: #1a1a1a; color: #fff;
        border: 1px solid #1a1a1a; border-radius: 2px; text-decoration: none;
        font-size: .85rem; font-weight: 500; letter-spacing: .08em; text-transform: uppercase;
    }
    .button:hover { background: #000; }
    .note { margin: 1.5rem 0 0; font-size: .85rem; color: #6f6a63; }
</style>
</head>
<body>

<main class="card">
    <p class="eyebrow">Studio</p>

    <?php if ($isDatabase): ?>
        <h1>La base de données ne répond pas</h1>
        <p>
            Le site est configuré, mais il n'arrive pas à joindre sa base de données.
            Les visiteurs voient cette page à la place du site.
        </p>
        <ol>
            <li>Vérifiez <code>DB_HOST</code>, <code>DB_DATABASE</code>,
                <code>DB_USERNAME</code> et <code>DB_PASSWORD</code> dans <code>.env</code>.</li>
            <li>Vérifiez que le serveur MySQL est démarré.</li>
            <li>Lancez <code>php bin/console.php check</code> pour un diagnostic précis.</li>
        </ol>
        <p class="note">
            Le détail de l'erreur est dans <code>storage/logs/</code>. Il n'est pas affiché ici :
            ces messages contiennent souvent les identifiants de connexion.
        </p>
    <?php else: ?>
        <h1>Installation requise</h1>
        <p>
            Les fichiers sont en place, mais le site n'est pas encore configuré :
            il n'y a pas de fichier <code>.env</code>.
        </p>

        <?php if (($installer ?? null) !== null): ?>
            <p>
                <a class="button" href="<?= e((string) $installer) ?>">Lancer l'installation</a>
            </p>
        <?php else: ?>
            <ol>
                <li>Copiez <code>.env.example</code> en <code>.env</code>.</li>
                <li>Générez une clé : <code>php bin/console.php key:generate</code>.</li>
                <li>Renseignez les identifiants de base de données dans <code>.env</code>.</li>
                <li>Lancez <code>php bin/console.php install</code>.</li>
            </ol>
            <p class="note">
                L'installateur web a été supprimé, ce qui est la bonne pratique après
                une installation. Utilisez la ligne de commande, ou reposez
                temporairement <code>public/install.php</code>.
            </p>
        <?php endif; ?>
    <?php endif; ?>
</main>

</body>
</html>
