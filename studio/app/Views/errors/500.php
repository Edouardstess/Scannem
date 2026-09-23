<?php
/**
 * @var string          $message
 * @var \Throwable|null $exception Only populated when APP_DEBUG is on.
 */

use App\Core\View;

if (($exception ?? null) === null) {
    echo View::render('errors.error', [
        'status'  => 500,
        'message' => (string) ($message ?? ''),
    ]);

    return;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Erreur serveur</title>
<style>
    body { margin: 0; padding: 2.5rem; background: #14110f; color: #f4f1ec;
           font: 400 15px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; }
    h1 { font-size: 1.25rem; margin: 0 0 .25rem; color: #ff8c73; }
    p { margin: .25rem 0 1.5rem; color: #b9b2a8; }
    pre { background: #1d1917; padding: 1.25rem; overflow: auto; border-radius: 4px;
          font-size: 13px; line-height: 1.7; }
    .note { margin-top: 2rem; color: #7d766c; font-size: 13px; }
</style>
</head>
<body>
<h1><?= e($exception::class) ?></h1>
<p><?= e($exception->getMessage()) ?></p>
<p><?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?></p>
<pre><?= e($exception->getTraceAsString()) ?></pre>
<p class="note">
    Cette page détaillée n'apparaît que parce que APP_DEBUG=true.
    Passez APP_DEBUG=false avant toute mise en production.
</p>
</body>
</html>
