<?php
declare(strict_types=1);

/** Page d'accueil publique — vitrine institutionnelle de l'UEP. */
final class HomeController extends Controller
{
    public function index(): void
    {
        $this->render('home/index', ['titrePage' => 'Accueil'], 'guest');
    }
}
