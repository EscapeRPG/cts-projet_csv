<?php

namespace App\Utilisateur\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (method_exists($user, 'isActive') && !$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Compte non activé.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Rien ici
    }
}
