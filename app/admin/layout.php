<?php

declare(strict_types=1);

use Scannem\Http;
use Scannem\Session;

/**
 * Gabarit de l'interface organisateur.
 *
 * @var string $title
 * @var string $content
 * @var string $active
 */

$nav = [
    'lots' => 'Lots',
    'scans' => 'Journal',
    'disputes' => 'Litiges',
    'devices' => 'Appareils',
];

Http::securityHeaders();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Http::escape($title) ?> &mdash; Scannem</title>
<link rel="icon" href="/scan/icon.svg" type="image/svg+xml">
<style>
  :root {
    --bg: #f6f7f9;
    --panel: #ffffff;
    --ink: #16181d;
    --muted: #6b7280;
    --line: #e3e6ea;
    --accent: #2b5cff;
    --green: #0f7b45;
    --green-bg: #e6f5ec;
    --red: #b4231c;
    --red-bg: #fdeceb;
    --amber: #92600a;
    --amber-bg: #fdf3e0;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #101216; --panel: #181b21; --ink: #e9ebef; --muted: #9aa3b0;
      --line: #272b33; --accent: #6b8cff;
      --green: #4ade80; --green-bg: #12301f;
      --red: #f87171; --red-bg: #331715;
      --amber: #fbbf24; --amber-bg: #332508;
    }
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; background: var(--bg); color: var(--ink);
    font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  }
  header.top {
    background: var(--panel); border-bottom: 1px solid var(--line);
    padding: 0 20px; display: flex; align-items: center; gap: 22px;
    flex-wrap: wrap; position: sticky; top: 0; z-index: 10;
  }
  .brand { font-weight: 700; letter-spacing: -0.2px; padding: 14px 0; }
  .brand span { color: var(--accent); }
  nav { display: flex; gap: 2px; flex: 1; flex-wrap: wrap; }
  nav a {
    padding: 14px 12px; color: var(--muted); text-decoration: none;
    border-bottom: 2px solid transparent; font-size: 14px;
  }
  nav a:hover { color: var(--ink); }
  nav a.on { color: var(--accent); border-bottom-color: var(--accent); }
  .who { font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 10px; }
  .who form { margin: 0; }
  main { max-width: 1100px; margin: 24px auto 60px; padding: 0 20px; }
  h1 { font-size: 21px; margin: 0 0 4px; letter-spacing: -0.3px; }
  h2 { font-size: 16px; margin: 28px 0 10px; }
  .sub { color: var(--muted); font-size: 14px; margin: 0 0 20px; }
  .panel {
    background: var(--panel); border: 1px solid var(--line);
    border-radius: 10px; padding: 18px; margin-bottom: 18px;
  }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th {
    text-align: left; font-size: 11px; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--muted); font-weight: 600;
    padding: 0 10px 8px; border-bottom: 1px solid var(--line);
  }
  td { padding: 10px; border-bottom: 1px solid var(--line); vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  code, .mono { font-family: "SFMono-Regular", Consolas, monospace; font-size: 13px; }
  .tag {
    display: inline-block; padding: 2px 9px; border-radius: 20px;
    font-size: 12px; font-weight: 600; white-space: nowrap;
  }
  .tag.green { background: var(--green-bg); color: var(--green); }
  .tag.red { background: var(--red-bg); color: var(--red); }
  .tag.amber { background: var(--amber-bg); color: var(--amber); }
  .tag.grey { background: var(--line); color: var(--muted); }
  input[type=text], input[type=password], input[type=number], input[type=date], select {
    padding: 9px 11px; border: 1px solid var(--line); border-radius: 7px;
    background: var(--bg); color: var(--ink); font-size: 14px; width: 100%;
  }
  label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 5px; }
  .field { margin-bottom: 14px; }
  .row { display: flex; gap: 14px; flex-wrap: wrap; }
  .row > * { flex: 1; min-width: 170px; }
  button, .btn {
    padding: 9px 16px; border: 1px solid var(--line); border-radius: 7px;
    background: var(--panel); color: var(--ink); font-size: 14px;
    cursor: pointer; text-decoration: none; display: inline-block;
  }
  button.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
  button.danger { color: var(--red); border-color: var(--red); }
  button:hover, .btn:hover { filter: brightness(0.97); }
  .flash { padding: 12px 15px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
  .flash.ok { background: var(--green-bg); color: var(--green); }
  .flash.err { background: var(--red-bg); color: var(--red); }
  .flash.warn { background: var(--amber-bg); color: var(--amber); }
  .stats { display: flex; gap: 26px; flex-wrap: wrap; }
  .stat .n { font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
  .stat .k { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
  .empty { color: var(--muted); text-align: center; padding: 30px; font-size: 14px; }
  .note {
    font-size: 13px; color: var(--muted); border-left: 3px solid var(--line);
    padding: 2px 0 2px 12px; margin: 14px 0;
  }
  .code-box {
    font-family: "SFMono-Regular", Consolas, monospace; font-size: 22px;
    letter-spacing: 2px; padding: 14px; background: var(--bg);
    border: 1px dashed var(--accent); border-radius: 8px; text-align: center;
  }
</style>
</head>
<body>
<header class="top">
  <div class="brand">Scan<span>nem</span></div>
  <?php if (Session::isLogged()): ?>
  <nav>
    <?php foreach ($nav as $key => $label): ?>
      <a href="/admin/?p=<?= $key ?>" class="<?= $active === $key ? 'on' : '' ?>"><?= Http::escape($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="who">
    <span><?= Http::escape(Session::username()) ?></span>
    <form method="post" action="/admin/?p=logout">
      <?= Session::csrfField() ?>
      <button type="submit">Deconnexion</button>
    </form>
  </div>
  <?php endif; ?>
</header>
<main><?= $content ?></main>
</body>
</html>
