<?php
declare(strict_types=1);

/**
 * Classe de base des contrôleurs : rendu des vues, redirections, réponses JSON
 * et génération des champs des questionnaires dynamiques.
 */
abstract class Controller
{
    /** Affiche une vue dans un layout. */
    protected function render(string $vue, array $donnees = [], string $layout = 'app'): void
    {
        $fichier = RACINE_VIEWS . '/' . $vue . '.php';
        if (!is_file($fichier)) {
            throw new RuntimeException("Vue introuvable : {$vue}");
        }

        extract($donnees, EXTR_SKIP);

        ob_start();
        require $fichier;
        $contenu = (string)ob_get_clean();

        require RACINE_VIEWS . '/layouts/' . $layout . '.php';
    }

    /** Redirige vers un chemin de l'application, puis arrête l'exécution. */
    protected function redirect(string $chemin, int $code = 303): never
    {
        header('Location: ' . $this->url($chemin), true, $code);
        exit;
    }

    protected function url(string $chemin): string
    {
        return URL_BASE . '/' . ltrim($chemin, '/');
    }

    protected function json(array $donnees, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Vérifie le jeton CSRF d'une requête POST. En cas d'échec, la requête
     * s'arrête sur une page d'erreur explicite plutôt que sur un texte brut.
     */
    protected function exigerCsrf(): void
    {
        if (!Csrf::verifier($_POST['csrf_token'] ?? null)) {
            ErreurHttp::afficher(
                419,
                'Votre session a expiré ou les cookies sont bloqués par le navigateur. '
                . 'Revenez en arrière, rechargez la page et recommencez.'
            );
        }
    }

    /** Valeur POST scalaire, nettoyée et tronquée, ou null si vide. */
    protected function champNullable(string $cle, int $max): ?string
    {
        $valeur = $_POST[$cle] ?? null;
        if (!is_scalar($valeur)) {
            return null;
        }

        $valeur = trim((string)$valeur);

        return $valeur === '' ? null : mb_substr($valeur, 0, $max);
    }

    protected function champTexte(string $cle, int $max = 255): string
    {
        $valeur = $_POST[$cle] ?? '';

        return is_scalar($valeur) ? mb_substr(trim((string)$valeur), 0, $max) : '';
    }

    /** Paramètre de filtre lu dans la query string. */
    protected function filtre(string $cle, int $max = 100): string
    {
        $valeur = $_GET[$cle] ?? '';

        return is_scalar($valeur) ? mb_substr(trim((string)$valeur), 0, $max) : '';
    }

    /**
     * Champ HTML d'une question du catalogue, selon son type de réponse.
     */
    public function genererChamp(array $question, string $name, ?string $valeur = '', bool $enErreur = false): string
    {
        $valeur = (string)($valeur ?? '');
        $obligatoire = (int)($question['obligatoire'] ?? 0) === 1;
        $classe = 'form-control' . ($enErreur ? ' is-invalid' : '');

        $attributs = sprintf(
            'name="%s" id="%s" class="%s"%s',
            e($name),
            e($name),
            $classe,
            $obligatoire ? ' data-obligatoire="1" aria-required="true"' : ''
        );

        return match ($question['type_reponse'] ?? 'texte') {
            'texte_long' => sprintf('<textarea %s rows="3">%s</textarea>', $attributs, e($valeur)),
            'nombre'     => sprintf('<input type="number" step="any" min="0" inputmode="decimal" %s value="%s">', $attributs, e($valeur)),
            'date'       => sprintf('<input type="date" %s value="%s">', $attributs, e($valeur)),
            'oui_non'    => sprintf(
                '<select %s><option value="">— Choisir —</option><option value="Oui"%s>Oui</option><option value="Non"%s>Non</option></select>',
                $attributs,
                $valeur === 'Oui' ? ' selected' : '',
                $valeur === 'Non' ? ' selected' : ''
            ),
            default      => sprintf('<input type="text" %s value="%s" maxlength="5000">', $attributs, e($valeur)),
        };
    }

    /**
     * Valide les réponses postées d'un questionnaire dynamique.
     *
     * @return array{0: array<int, string>, 1: array<int, string|null>}
     *         [erreurs par question, valeurs normalisées]. Une valeur null
     *         signifie « réponse effacée ».
     */
    protected function validerReponsesCatalogue(array $questions, array $post, string $prefixe = 'q_'): array
    {
        $erreurs = [];
        $valeurs = [];

        foreach ($questions as $question) {
            $qid = (int)$question['id'];
            $brut = $post[$prefixe . $qid] ?? '';

            if (!is_scalar($brut)) {
                $erreurs[$qid] = 'Valeur invalide.';
                continue;
            }

            $valeur = trim((string)$brut);

            if ($valeur === '') {
                if ((int)$question['obligatoire'] === 1) {
                    $erreurs[$qid] = 'Ce champ est obligatoire.';
                    continue;
                }
                $valeurs[$qid] = null;
                continue;
            }

            if (mb_strlen($valeur, 'UTF-8') > 5000) {
                $erreurs[$qid] = 'La réponse ne peut pas dépasser 5 000 caractères.';
                continue;
            }

            switch ($question['type_reponse']) {
                case 'nombre':
                    if (!is_numeric($valeur) || !is_finite((float)$valeur) || (float)$valeur < 0) {
                        $erreurs[$qid] = 'Indiquez un nombre positif.';
                        continue 2;
                    }
                    break;

                case 'date':
                    if (!Validator::dateValide($valeur)) {
                        $erreurs[$qid] = 'Date invalide (format attendu : jj/mm/aaaa).';
                        continue 2;
                    }
                    break;

                case 'oui_non':
                    if (!in_array($valeur, ['Oui', 'Non'], true)) {
                        $erreurs[$qid] = 'Répondez par « Oui » ou « Non ».';
                        continue 2;
                    }
                    break;
            }

            $valeurs[$qid] = $valeur;
        }

        return [$erreurs, $valeurs];
    }
}
