<?php
declare(strict_types=1);

/** Contrôle d'accès par rôle (RBAC). */
final class RoleMiddleware
{
    /** @param list<string> $rolesAutorises */
    public static function exigerRole(array $rolesAutorises): void
    {
        AuthMiddleware::exigerConnexion();

        if (!in_array(Auth::role(), $rolesAutorises, true)) {
            Auth::journaliser(Auth::id(), 'acces_refuse', null, null, (string)($_SERVER['REQUEST_URI'] ?? ''));
            ErreurHttp::afficher(403);
        }
    }
}
