<?php
/**
 * Tests fonctionnels de bout en bout — UEP / MENFP.
 *
 * Pilote l'application par HTTP comme le ferait un navigateur : routes, CRUD,
 * rôles, circuit de validation, CSRF, XSS, injection SQL, anti-bruteforce et
 * pagination.
 *
 * PRÉREQUIS
 *   1. L'application tourne :   cd htdocs && php -S 127.0.0.1:8090 router-dev.php
 *   2. Une base de TEST est installée et contient le compte administrateur
 *      utilisé ci-dessous. N'exécutez JAMAIS ces tests sur une base de
 *      production : ils créent et suppriment des données.
 *
 * USAGE
 *   php tests/tests-fonctionnels.php
 *   UEP_BASE=http://localhost:8000 UEP_ADMIN_PASS=... php tests/tests-fonctionnels.php
 *
 * Le code de sortie vaut 0 si tout passe, 1 sinon.
 */
declare(strict_types=1);

// Connexion directe à la base de test, pour vérifier ce que l'application a
// réellement écrit. À adapter à votre environnement.
const BDD_DSN  = 'mysql:host=127.0.0.1;dbname=uep_final;charset=utf8mb4';
const BDD_USER = 'uep';
const BDD_PASS = 'ueptest';

$BASE = getenv('UEP_BASE') ?: 'http://127.0.0.1:8090';
$JAR  = sys_get_temp_dir() . '/uep_cookies_' . getmypid() . '.txt';
@unlink($JAR);
$ok = 0; $ko = 0; $fails = [];

function req(string $method, string $path, array $data = [], bool $follow = false): array {
    global $BASE, $JAR;
    $ch = curl_init($BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $JAR,
        CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HEADER => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $loc = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => substr((string)$raw, $hsize), 'headers' => substr((string)$raw, 0, $hsize), 'location' => (string)$loc];
}

function csrf(string $path): string {
    $r = req('GET', $path);
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $m)) return $m[1];
    return '';
}

function check(string $label, bool $cond, string $extra = ''): void {
    global $ok, $ko, $fails;
    if ($cond) { $ok++; echo "  OK   $label\n"; }
    else { $ko++; $fails[] = $label . ($extra ? " — $extra" : ''); echo "  FAIL $label" . ($extra ? " — $extra" : '') . "\n"; }
}

function section(string $t): void { echo "\n== $t ==\n"; }

// ---------------------------------------------------------------- Public
section('Pages publiques');
$r = req('GET', '/');            check('GET /', $r['code'] === 200, (string)$r['code']);
check('/ contient le nom UEP', str_contains($r['body'], 'UEP'));
$r = req('GET', '/login');       check('GET /login', $r['code'] === 200, (string)$r['code']);
$r = req('GET', '/page-absente'); check('404 route inconnue', $r['code'] === 404, (string)$r['code']);

section('Robustesse du routeur');
foreach (['/upd/abc', '/requisitions/xyz', '/utilisateurs/9%20/modifier', '/upd/99999999999999999999'] as $u) {
    $r = req('GET', $u);
    check("param non numerique $u => 404 ou 303", in_array($r['code'], [303, 404], true), (string)$r['code']);
}

section('Securite : CSRF');
$r = req('POST', '/login', ['email' => 'admin@menfp.gouv.ht', 'mot_de_passe' => 'x', 'csrf_token' => 'faux']);
check('POST /login sans CSRF valide rejete', in_array($r['code'], [403, 419], true), (string)$r['code']);

section('Connexion');
$tok = csrf('/login'); check('jeton CSRF present sur /login', $tok !== '');
$r = req('POST', '/login', ['csrf_token' => $tok, 'email' => 'admin@menfp.gouv.ht', 'mot_de_passe' => 'MauvaisMotDePasse1']);
check('mauvais mot de passe => page login', in_array($r['code'], [200, 401], true) && str_contains($r['body'], 'incorrect'), (string)$r['code']);
$tok = csrf('/login');
$pass = getenv('UEP_ADMIN_PASS') ?: 'AdminUep2026Test';
$r = req('POST', '/login', ['csrf_token' => $tok, 'email' => 'admin@menfp.gouv.ht', 'mot_de_passe' => $pass]);
check('connexion admin => redirection', in_array($r['code'], [302, 303], true), (string)$r['code'] . ' ' . $r['location']);

section('Pages protegees');
foreach (['/dashboard', '/upd', '/dde', '/requisitions', '/requisitions/nouveau', '/utilisateurs', '/utilisateurs/nouveau'] as $u) {
    $r = req('GET', $u);
    check("GET $u", $r['code'] === 200, (string)$r['code']);
    check("  $u sans erreur PHP", !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)/', $r['body']));
}

section('Module UPD');
$tok = csrf('/upd');
$r = req('POST', '/upd/nouveau', ['csrf_token' => $tok]);
check('creation UPD => 302', in_array($r['code'], [302, 303], true), (string)$r['code']);
preg_match('#/upd/(\d+)#', $r['location'], $m);
$updId = (int)($m[1] ?? 0);
check('id UPD recupere', $updId > 0);
$r = req('GET', "/upd/$updId");
check("GET /upd/$updId", $r['code'] === 200, (string)$r['code']);
check('  formulaire UPD sans erreur PHP', !preg_match('/(Fatal error|Warning:|Notice:|Uncaught)/', $r['body']));
preg_match_all('/name="(q_\d+)"/', $r['body'], $mm);
$champs = array_unique($mm[1] ?? []);
check('formulaire UPD genere des champs (' . count($champs) . ')', count($champs) > 500);
// enregistrer quelques valeurs
$tok = csrf("/upd/$updId");
$post = ['csrf_token' => $tok];
$pdo = new PDO(BDD_DSN, BDD_USER, BDD_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$qs = $pdo->query("SELECT id, ligne_code, type_reponse FROM upd_questions_catalogue WHERE ligne_code IN ('A.1','A.2','A.3')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($qs as $q) {
    $post['q_' . $q['id']] = match ($q['ligne_code']) {
        'A.1' => 'UPD du Nord',
        'A.2' => 'UPDN',
        'A.3' => 'Nord',
    };
}
$r = req('POST', "/upd/$updId/sauvegarder", $post);
check('sauvegarde UPD => 302', in_array($r['code'], [302, 303], true), (string)$r['code']);
$nom = $pdo->query("SELECT nom_upd FROM institutions_upd WHERE id = $updId")->fetchColumn();
check('denormalisation nom_upd ecrite', $nom === 'UPD du Nord', var_export($nom, true));
$r = req('GET', '/upd');
check('liste UPD affiche le nom', str_contains($r['body'], 'UPD du Nord'));

section('Workflow UPD');
foreach ([['soumettre', 'soumis'], ['rejeter', 'rejete'], ['soumettre', 'soumis'], ['valider', 'valide'], ['rouvrir', 'brouillon']] as [$action, $attendu]) {
    $tok = csrf("/upd/$updId");
    $r = req('POST', "/upd/$updId/statut", ['csrf_token' => $tok, 'action' => $action]);
    $statut = $pdo->query("SELECT statut_validation FROM institutions_upd WHERE id = $updId")->fetchColumn();
    check("workflow $action => $attendu", in_array($r['code'], [302, 303], true) && $statut === $attendu, "code={$r['code']} statut=$statut");
}
// ecriture interdite quand soumis
$tok = csrf("/upd/$updId");
req('POST', "/upd/$updId/statut", ['csrf_token' => $tok, 'action' => 'soumettre']);
$tok = csrf("/upd/$updId");
$qA1 = $pdo->query("SELECT id FROM upd_questions_catalogue WHERE ligne_code='A.1' LIMIT 1")->fetchColumn();
req('POST', "/upd/$updId/sauvegarder", ['csrf_token' => $tok, 'q_' . $qA1 => 'PIRATE']);
$val = $pdo->query("SELECT valeur FROM upd_reponses WHERE upd_id=$updId AND question_id=$qA1")->fetchColumn();
check('ecriture refusee sur dossier soumis', $val === 'UPD du Nord', var_export($val, true));
$tok = csrf("/upd/$updId");
req('POST', "/upd/$updId/statut", ['csrf_token' => $tok, 'action' => 'rouvrir']);

section('Module DDE');
$tok = csrf('/dde');
$r = req('POST', '/dde/nouveau', ['csrf_token' => $tok]);
check('creation DDE => 302', in_array($r['code'], [302, 303], true), (string)$r['code']);
preg_match('#/dde/(\d+)#', $r['location'], $m);
$ddeId = (int)($m[1] ?? 0);
$r = req('GET', "/dde/$ddeId");
check("GET /dde/$ddeId", $r['code'] === 200, (string)$r['code']);
check('  formulaire DDE sans erreur PHP', !preg_match('/(Fatal error|Warning:|Notice:|Uncaught)/', $r['body']));
$tok = csrf("/dde/$ddeId");
$qs = $pdo->query("SELECT id, ligne_code FROM dde_questions_catalogue WHERE ligne_code IN ('A.1','A.0')")->fetchAll(PDO::FETCH_ASSOC);
$post = ['csrf_token' => $tok];
foreach ($qs as $q) { $post['q_' . $q['id']] = $q['ligne_code'] === 'A.1' ? 'DDE Sud' : 'Sud'; }
$r = req('POST', "/dde/$ddeId/sauvegarder", $post);
check('sauvegarde DDE => 302', in_array($r['code'], [302, 303], true), (string)$r['code']);
$nomDde = $pdo->query("SELECT nom_dde FROM institutions_dde WHERE id = $ddeId")->fetchColumn();
check('denormalisation nom_dde', $nomDde === 'DDE Sud', var_export($nomDde, true));

section('Module Requisitions');
$cat = (int)$pdo->query('SELECT id FROM categories_articles ORDER BY id LIMIT 1')->fetchColumn();
$tok = csrf('/requisitions/nouveau');
$r = req('POST', '/requisitions/creer', [
    'csrf_token' => $tok, 'objet' => 'Achat de 3 ordinateurs', 'priorite' => 'haute',
    'service_demandeur' => 'Service informatique', 'adresse_livraison' => 'Delmas 83',
    'justification' => 'Renouvellement du parc', 'observations' => '',
    'designation' => ['Ordinateur portable', 'Onduleur'],
    'categorie_id' => [$cat, $cat],
    'quantite' => [3, 2],
    'prix_unitaire' => [85000.50, 12000],
]);
check('creation requisition => 302', in_array($r['code'], [302, 303], true), (string)$r['code'] . ' ' . $r['location']);
preg_match('#/requisitions/(\d+)#', $r['location'], $m);
$reqId = (int)($m[1] ?? 0);
check('id requisition', $reqId > 0);
$row = $pdo->query("SELECT numero_requisition FROM requisitions WHERE id=$reqId")->fetchColumn();
check('numero attribue: ' . $row, (bool)preg_match('/^REQ-\d{4}-\d{4}$/', (string)$row));
$total = (float)$pdo->query("SELECT SUM(montant_total) FROM requisition_articles WHERE requisition_id=$reqId")->fetchColumn();
check('montant calcule = 279001.50 (3x85000.50 + 2x12000)', abs($total - 279001.50) < 0.01, (string)$total);
$r = req('GET', "/requisitions/$reqId");
check('details requisition 200', $r['code'] === 200, (string)$r['code']);
check('  details sans erreur PHP', !preg_match('/(Fatal error|Warning:|Notice:|Uncaught)/', $r['body']));
$r = req('GET', "/requisitions/$reqId/modifier");
check('modifier requisition 200', $r['code'] === 200, (string)$r['code']);
check('  modifier sans erreur PHP', !preg_match('/(Fatal error|Warning:|Notice:|Uncaught)/', $r['body']));
$tok = csrf("/requisitions/$reqId/modifier");
$r = req('POST', "/requisitions/$reqId/mettre-a-jour", [
    'csrf_token' => $tok, 'objet' => 'Achat de 4 ordinateurs', 'priorite' => 'urgente',
    'service_demandeur' => 'Service informatique',
    'designation' => ['Ordinateur portable'], 'categorie_id' => [$cat],
    'quantite' => [4], 'prix_unitaire' => [85000],
]);
check('mise a jour requisition => 302', in_array($r['code'], [302, 303], true), (string)$r['code']);
$st = $pdo->query("SELECT statut, objet FROM requisitions WHERE id=$reqId")->fetch(PDO::FETCH_ASSOC);
check('objet mis a jour, statut inchange', $st['statut'] === 'en_attente' && $st['objet'] === 'Achat de 4 ordinateurs', json_encode($st));
$total = (float)$pdo->query("SELECT SUM(montant_total) FROM requisition_articles WHERE requisition_id=$reqId")->fetchColumn();
check('articles remplaces : 4 x 85000 = 340000', abs($total - 340000.0) < 0.01, (string)$total);

// Circuit de decision
$tok = csrf("/requisitions/$reqId");
$r = req('POST', "/requisitions/$reqId/decision", ['csrf_token' => $tok, 'statut' => 'livree']);
$st = $pdo->query("SELECT statut FROM requisitions WHERE id=$reqId")->fetchColumn();
check('livraison refusee avant approbation', $st === 'en_attente', (string)$st);
$tok = csrf("/requisitions/$reqId");
$r = req('POST', "/requisitions/$reqId/decision", ['csrf_token' => $tok, 'statut' => 'approuvee', 'commentaire_decision' => 'Budget disponible']);
$st = $pdo->query("SELECT statut, approuve_par, commentaire_decision FROM requisitions WHERE id=$reqId")->fetch(PDO::FETCH_ASSOC);
check('approbation enregistree', $st['statut'] === 'approuvee' && (int)$st['approuve_par'] > 0 && $st['commentaire_decision'] === 'Budget disponible', json_encode($st));
$tok = csrf("/requisitions/$reqId");
req('POST', "/requisitions/$reqId/decision", ['csrf_token' => $tok, 'statut' => 'livree']);
$st = $pdo->query("SELECT statut FROM requisitions WHERE id=$reqId")->fetchColumn();
check('livraison acceptee apres approbation', $st === 'livree', (string)$st);
$tok = csrf("/requisitions/$reqId");
req('POST', "/requisitions/$reqId/decision", ['csrf_token' => $tok, 'statut' => 'rejetee']);
$st = $pdo->query("SELECT statut FROM requisitions WHERE id=$reqId")->fetchColumn();
check('requisition livree figee', $st === 'livree', (string)$st);
$r = req('GET', "/requisitions/$reqId/imprimer");
check('bon imprimable 200', $r['code'] === 200, (string)$r['code']);
check('  bon imprimable sans erreur PHP', !preg_match('/(Fatal error|Warning:|Notice:|Uncaught)/', $r['body']));
check('  bon imprimable contient le total', str_contains($r['body'], '340 000,00'));
// validation : requisition sans article
$tok = csrf('/requisitions/nouveau');
$r = req('POST', '/requisitions/creer', ['csrf_token' => $tok, 'objet' => 'Vide', 'priorite' => 'normale']);
check('requisition sans article refusee', in_array($r['code'], [200, 422], true) && str_contains($r['body'], 'article'), (string)$r['code']);
// numero unique : deuxieme requisition
$tok = csrf('/requisitions/nouveau');
$r = req('POST', '/requisitions/creer', [
    'csrf_token' => $tok, 'objet' => 'Deuxieme', 'priorite' => 'normale',
    'designation' => ['Souris'], 'categorie_id' => [$cat], 'quantite' => [10], 'prix_unitaire' => [500],
]);
check('deuxieme requisition creee', in_array($r['code'], [302, 303], true), (string)$r['code']);
$nums = $pdo->query('SELECT COUNT(DISTINCT numero_requisition) n, COUNT(*) t FROM requisitions')->fetch(PDO::FETCH_ASSOC);
check('numeros uniques', $nums['n'] === $nums['t'], json_encode($nums));

section('Module Utilisateurs');
// Le harnais doit pouvoir être relancé : on repart d'un état connu.
$pdo->exec("DELETE FROM utilisateurs WHERE email = 'saisie@menfp.gouv.ht'");
$tok = csrf('/utilisateurs/nouveau');
$roleSaisisseur = (int)$pdo->query("SELECT id FROM roles WHERE nom_role='saisisseur'")->fetchColumn();
$r = req('POST', '/utilisateurs/creer', [
    'csrf_token' => $tok, 'nom_complet' => 'Jean Saisisseur', 'email' => 'saisie@menfp.gouv.ht',
    'mot_de_passe' => 'MotDePasse2026x', 'role_id' => $roleSaisisseur,
    'departement_rattachement' => 'nord', 'telephone' => '+509 0000 0000', 'actif' => '1',
]);
check('creation utilisateur => 302', in_array($r['code'], [302, 303], true), (string)$r['code']);
$uid = (int)$pdo->query("SELECT id FROM utilisateurs WHERE email='saisie@menfp.gouv.ht'")->fetchColumn();
check('utilisateur en base', $uid > 0);
$r = req('GET', "/utilisateurs/$uid/modifier");
check('page modification utilisateur', $r['code'] === 200, (string)$r['code']);
$tok = csrf("/utilisateurs/$uid/modifier");
$r = req('POST', "/utilisateurs/$uid/mettre-a-jour", [
    'csrf_token' => $tok, 'nom_complet' => 'Jean S. Modifie', 'email' => 'saisie@menfp.gouv.ht',
    'role_id' => $roleSaisisseur, 'departement_rattachement' => 'sud', 'actif' => '1',
]);
check('modification utilisateur => 302', in_array($r['code'], [302, 303], true), (string)$r['code']);
$nomU = $pdo->query("SELECT nom_complet FROM utilisateurs WHERE id=$uid")->fetchColumn();
check('nom modifie', $nomU === 'Jean S. Modifie', (string)$nomU);
// email en doublon refuse
$tok = csrf('/utilisateurs/nouveau');
$r = req('POST', '/utilisateurs/creer', [
    'csrf_token' => $tok, 'nom_complet' => 'Doublon', 'email' => 'saisie@menfp.gouv.ht',
    'mot_de_passe' => 'MotDePasse2026x', 'role_id' => $roleSaisisseur, 'actif' => '1',
]);
check('email en doublon refuse', in_array($r['code'], [200, 422], true) && str_contains($r['body'], 'd') && str_contains($r['body'], 'utilis'), (string)$r['code']);
// auto-retrogradation refusee
$adminId = (int)$pdo->query("SELECT id FROM utilisateurs WHERE email='admin@menfp.gouv.ht'")->fetchColumn();
$tok = csrf("/utilisateurs/$adminId/modifier");
$r = req('POST', "/utilisateurs/$adminId/mettre-a-jour", [
    'csrf_token' => $tok, 'nom_complet' => 'Admin', 'email' => 'admin@menfp.gouv.ht',
    'role_id' => $roleSaisisseur, 'actif' => '1',
]);
$roleApres = $pdo->query("SELECT r.nom_role FROM utilisateurs u JOIN roles r ON r.id=u.role_id WHERE u.id=$adminId")->fetchColumn();
check('admin ne peut pas se retrograder', $roleApres === 'administrateur', (string)$roleApres);

section('Profil et journal');
foreach (['/profil', '/journal'] as $u) {
    $r = req('GET', $u);
    check("GET $u", $r['code'] === 200, (string)$r['code']);
    check("  $u sans erreur PHP", !preg_match('/(Fatal error|Warning:|Notice:|Uncaught)/', $r['body']));
}
$r = req('GET', '/journal?q=connexion');
check('journal filtre', $r['code'] === 200 && str_contains($r['body'], 'connexion'), (string)$r['code']);

section('XSS et injection SQL');
$tok = csrf('/requisitions/nouveau');
$charge = '<script>alert(1)</script>';
$r = req('POST', '/requisitions/creer', [
    'csrf_token' => $tok, 'objet' => $charge, 'priorite' => 'normale',
    'designation' => ["Clavier' OR 1=1 --"], 'categorie_id' => [$cat], 'quantite' => [1], 'prix_unitaire' => [100],
]);
preg_match('#/requisitions/(\d+)#', $r['location'], $m);
$xssId = (int)($m[1] ?? 0);
$r = req('GET', "/requisitions/$xssId");
check('XSS echappe dans la fiche', $xssId > 0 && !str_contains($r['body'], '<script>alert(1)</script>') && str_contains($r['body'], '&lt;script&gt;'), (string)$r['code']);
$r = req('GET', '/requisitions?q=' . urlencode("' OR '1'='1"));
check('injection SQL dans le filtre sans effet', $r['code'] === 200 && !preg_match('/(SQLSTATE|Fatal error)/', $r['body']), (string)$r['code']);
$compte = (int)$pdo->query('SELECT COUNT(*) FROM requisitions')->fetchColumn();
check('base intacte apres tentative injection', $compte > 0);

section('Pagination');
$r = req('GET', '/requisitions?page=999999');
check('page hors limites geree', $r['code'] === 200, (string)$r['code']);
$r = req('GET', '/upd?page=abc');
check('page non numerique geree', $r['code'] === 200, (string)$r['code']);

section('Deconnexion / RBAC');
$tok = csrf('/dashboard');
$r = req('POST', '/logout', ['csrf_token' => $tok]);
check('logout => 302', in_array($r['code'], [302, 303], true), (string)$r['code']);
$r = req('GET', '/dashboard');
check('dashboard inaccessible apres logout', $r['code'] === 303, (string)$r['code']);
// connexion saisisseur
$tok = csrf('/login');
$r = req('POST', '/login', ['csrf_token' => $tok, 'email' => 'saisie@menfp.gouv.ht', 'mot_de_passe' => 'MotDePasse2026x']);
check('connexion saisisseur', in_array($r['code'], [302, 303], true), (string)$r['code']);
$r = req('GET', '/utilisateurs');
check('saisisseur sur /utilisateurs => 403', $r['code'] === 403, (string)$r['code']);
$tok = csrf("/upd/$updId");
$r = req('POST', "/upd/$updId/statut", ['csrf_token' => $tok, 'action' => 'valider']);
check('saisisseur ne peut pas valider => 403', $r['code'] === 403, (string)$r['code']);

section('Anti-bruteforce');
$tok = csrf('/dashboard'); req('POST', '/logout', ['csrf_token' => $tok]);
$pdo->exec('DELETE FROM tentatives_connexion');
for ($i = 0; $i < 6; $i++) {
    $tok = csrf('/login');
    $r = req('POST', '/login', ['csrf_token' => $tok, 'email' => 'saisie@menfp.gouv.ht', 'mot_de_passe' => 'faux' . $i]);
}
check('blocage apres 5 echecs', str_contains($r['body'], 'tentatives'), substr(strip_tags($r['body']), 0, 120));
$pdo->exec('DELETE FROM tentatives_connexion');
$tok = csrf('/login');
$r = req('POST', '/login', ['csrf_token' => $tok, 'email' => 'saisie@menfp.gouv.ht', 'mot_de_passe' => 'MotDePasse2026x']);
check('reconnexion apres purge', in_array($r['code'], [302, 303], true), (string)$r['code']);

echo "\n=====================================\n";
echo "REUSSIS : $ok    ECHECS : $ko\n";
if ($fails) { echo "\nDetail des echecs :\n"; foreach ($fails as $f) echo " - $f\n"; }
@unlink($JAR);
exit($ko === 0 ? 0 : 1);
