<?php
declare(strict_types=1);

/** Questionnaire des Directions Départementales d'Éducation (sections A à F). */
final class DdeController extends QuestionnaireController
{
    protected function base(): string
    {
        return 'dde';
    }

    protected function sigle(): string
    {
        return 'DDE';
    }

    protected function intitule(): string
    {
        return 'Directions Départementales d\'Éducation';
    }

    protected function colonnesDenormalisees(): array
    {
        // A.3 est le NUMÉRO DE TÉLÉPHONE de la direction : l'alimenter dans la
        // colonne « departement » remplissait les listes et le tableau de bord
        // de numéros de téléphone. Le département est désormais une question à
        // part entière du catalogue (A.0).
        return [
            'nom_dde'     => 'A.1',
            'departement' => 'A.0',
        ];
    }
}
