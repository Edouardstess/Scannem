<?php
declare(strict_types=1);

/** Questionnaire des Universités Publiques Départementales (sections A à Q). */
final class UpdController extends QuestionnaireController
{
    protected function base(): string
    {
        return 'upd';
    }

    protected function sigle(): string
    {
        return 'UPD';
    }

    protected function intitule(): string
    {
        return 'Universités Publiques Départementales';
    }

    protected function colonnesDenormalisees(): array
    {
        return [
            'nom_upd'     => 'A.1',
            'sigle_upd'   => 'A.2',
            'departement' => 'A.3',
        ];
    }
}
