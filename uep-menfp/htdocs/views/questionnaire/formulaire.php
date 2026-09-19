<?php
/**
 * Formulaire dynamique d'un questionnaire (UPD ou DDE), affiché section par
 * section. Les questions viennent du catalogue : aucune n'est codée en dur.
 *
 * @var string $base
 * @var array<string,mixed> $dossier
 * @var array<string,array<string,mixed>> $sections
 * @var array<int,string> $reponses
 * @var array<int,string> $erreurs   Message d'erreur par identifiant de question
 */
$statut        = Workflow::statutValide($dossier['statut_validation'] ?? null);
$peutModifier  = Workflow::peutModifier($statut, Auth::role());
$actions       = Workflow::actionsDisponibles($statut, Auth::role());
$nombreSections = count($sections);
$idDossier     = (int)$dossier['id'];

/** Numéro de la première section contenant une erreur, pour y renvoyer l'utilisateur. */
$sectionEnErreur = 0;
if ($erreurs !== []) {
    $index = 0;
    foreach ($sections as $section) {
        $index++;
        foreach ($section['groupes'] as $groupe) {
            foreach ($groupe['lignes'] as $question) {
                if (isset($erreurs[(int)$question['id']]) && $sectionEnErreur === 0) {
                    $sectionEnErreur = $index;
                }
            }
        }
    }
}
?>
<div class="dossier-entete">
    <div>
        <p class="dossier-kicker">Dossier institutionnel · <?= e($sigle) ?></p>
        <h2><?= e($nomDossier) ?></h2>
        <p class="dossier-meta">
            <span><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e(Departements::affichage($dossier['departement'] ?? null)) ?></span>
            <?php if ($base === 'upd' && !empty($dossier['sigle_upd'])): ?>
                <span><i class="bi bi-tag" aria-hidden="true"></i> <?= e($dossier['sigle_upd']) ?></span>
            <?php endif; ?>
            <span><i class="bi bi-clock-history" aria-hidden="true"></i> Modifié le <?= Format::dateHeure($dossier['updated_at'] ?? null) ?></span>
        </p>
    </div>
    <a class="btn btn-outline" href="<?= URL_BASE ?>/<?= e($base) ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la liste
    </a>
</div>

<div class="workflow-barre">
    <div class="workflow-etat">
        <span class="workflow-label">Statut</span>
        <span class="<?= e(Workflow::classeBadge($statut)) ?>"><?= e(Workflow::libelle($statut)) ?></span>
        <?php if (!$peutModifier): ?>
            <span class="workflow-verrou"><i class="bi bi-lock-fill" aria-hidden="true"></i> Saisie verrouillée</span>
        <?php endif; ?>
    </div>

    <?php if ($actions !== []): ?>
        <div class="workflow-actions">
            <?php foreach ($actions as $nomAction => $regle): ?>
                <!-- Les formulaires d'action sont hors du formulaire de saisie :
                     un formulaire HTML ne peut pas en contenir un autre. -->
                <form method="POST" action="<?= URL_BASE ?>/<?= e($base) ?>/<?= $idDossier ?>/statut"
                      data-confirmer="<?= e($regle['confirm']) ?>">
                    <?= Csrf::champ() ?>
                    <input type="hidden" name="action" value="<?= e($nomAction) ?>">
                    <button type="submit" class="btn <?= e($regle['classe']) ?> btn-petit">
                        <i class="bi <?= e($regle['icone']) ?>" aria-hidden="true"></i> <?= e($regle['libelle']) ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!$peutModifier): ?>
    <div class="alerte alerte-info">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <span>
            <?php if ($statut === 'soumis'): ?>
                Ce dossier est en cours d'examen. Il redeviendra modifiable s'il est rejeté ou rouvert par un administrateur.
            <?php elseif ($statut === 'valide'): ?>
                Ce dossier est validé. Seul un administrateur peut le rouvrir pour le modifier.
            <?php else: ?>
                Votre rôle permet la consultation de ce dossier, pas sa modification.
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<form method="POST" action="<?= URL_BASE ?>/<?= e($base) ?>/<?= $idDossier ?>/sauvegarder"
      id="formulaireQuestionnaire" data-etape-initiale="<?= max(1, $sectionEnErreur) ?>">
    <?= Csrf::champ() ?>

    <fieldset class="fieldset-nu"<?= $peutModifier ? '' : ' disabled' ?>>
        <div class="dossier-grille">
            <aside class="etapes" aria-label="Sections du questionnaire">
                <p class="etapes-titre">Sections</p>
                <p class="etapes-compte"><strong data-etape-courante>1</strong> / <?= $nombreSections ?></p>
                <div class="progress-bar-container"><div class="progress-bar-fill" data-etape-progression></div></div>
                <nav class="etapes-liste">
                    <?php $numero = 0; foreach ($sections as $section): $numero++; ?>
                        <button type="button" class="etape-lien<?= $numero === 1 ? ' is-active' : '' ?>" data-aller-etape="<?= $numero ?>">
                            <span class="etape-numero"><?= str_pad((string)$numero, 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="etape-libelle"><?= e($section['libelle']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <div class="etapes-contenu">
                <?php $numero = 0; foreach ($sections as $section): $numero++; ?>
                    <section class="panel etape<?= $numero === 1 ? ' is-active' : '' ?>" data-etape="<?= $numero ?>">
                        <header class="panel-header">
                            <div>
                                <p class="panel-kicker">Section <?= e($section['code']) ?></p>
                                <h3 class="panel-title"><?= e($section['libelle']) ?></h3>
                            </div>
                            <span class="panel-compteur"><?= $numero ?> / <?= $nombreSections ?></span>
                        </header>

                        <div class="panel-body">
                            <?php foreach ($section['groupes'] as $groupe): ?>
                                <?php
                                $lignes = $groupe['lignes'];
                                $codes = array_column($lignes, 'ligne_code');
                                // Plusieurs colonnes pour un même code de ligne = tableau à saisir.
                                $estTableau = count($codes) > count(array_unique($codes));
                                ?>

                                <?php if ($groupe['code'] !== null && $groupe['libelle'] !== ''): ?>
                                    <h4 class="groupe-titre">
                                        <span class="groupe-code"><?= e($groupe['code']) ?></span>
                                        <?= e($groupe['libelle']) ?>
                                    </h4>
                                <?php endif; ?>

                                <?php if ($estTableau): ?>
                                    <?php
                                    $colonnes = [];
                                    foreach ($lignes as $ligne) {
                                        if (!in_array($ligne['colonne_libelle'], $colonnes, true)) {
                                            $colonnes[] = $ligne['colonne_libelle'];
                                        }
                                    }
                                    $parLigne = [];
                                    foreach ($lignes as $ligne) {
                                        $parLigne[$ligne['ligne_code']][$ligne['colonne_libelle']] = $ligne;
                                    }
                                    ?>
                                    <div class="table-defilante">
                                        <table class="data-table table-saisie">
                                            <thead>
                                                <tr>
                                                    <th scope="col" class="colonne-intitule">Intitulé</th>
                                                    <?php foreach ($colonnes as $colonne): ?>
                                                        <th scope="col"><?= e($colonne) ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($parLigne as $codeLigne => $colonnesLigne): ?>
                                                    <tr>
                                                        <th scope="row" class="colonne-intitule">
                                                            <span class="ligne-code"><?= e($codeLigne) ?></span>
                                                            <?= e($colonnesLigne[array_key_first($colonnesLigne)]['ligne_libelle'] ?? '') ?>
                                                        </th>
                                                        <?php foreach ($colonnes as $colonne): ?>
                                                            <td>
                                                                <?php $question = $colonnesLigne[$colonne] ?? null; ?>
                                                                <?php if ($question !== null): ?>
                                                                    <?php $qid = (int)$question['id']; ?>
                                                                    <?= $this->genererChamp($question, 'q_' . $qid, $reponses[$qid] ?? '', isset($erreurs[$qid])) ?>
                                                                    <?php if (isset($erreurs[$qid])): ?>
                                                                        <p class="champ-erreur"><?= e($erreurs[$qid]) ?></p>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($lignes as $question): ?>
                                        <?php $qid = (int)$question['id']; ?>
                                        <div class="question<?= isset($erreurs[$qid]) ? ' question-erreur' : '' ?>">
                                            <label class="question-label" for="q_<?= $qid ?>">
                                                <span class="ligne-code"><?= e($question['ligne_code']) ?></span>
                                                <?= e($question['ligne_libelle'] ?? $question['colonne_libelle']) ?>
                                                <?php if ((int)$question['obligatoire'] === 1): ?>
                                                    <span class="obligatoire" title="Champ obligatoire">*</span>
                                                <?php endif; ?>
                                            </label>
                                            <div class="question-champ">
                                                <?= $this->genererChamp($question, 'q_' . $qid, $reponses[$qid] ?? '', isset($erreurs[$qid])) ?>
                                                <?php if (isset($erreurs[$qid])): ?>
                                                    <p class="champ-erreur"><?= e($erreurs[$qid]) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <footer class="etape-actions">
                            <?php if ($numero > 1): ?>
                                <button type="button" class="btn btn-outline" data-etape-precedente>
                                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Section précédente
                                </button>
                            <?php endif; ?>
                            <?php if ($numero < $nombreSections): ?>
                                <button type="button" class="btn btn-primary" data-etape-suivante>
                                    Section suivante <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($peutModifier): ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Enregistrer le dossier
                                </button>
                            <?php endif; ?>
                        </footer>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </fieldset>
</form>
