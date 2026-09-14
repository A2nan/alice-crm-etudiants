<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    /**
     * Runs BEFORE the password is checked : anything thrown here is visible to
     * someone who does not know the password, so it must not reveal account state.
     */
    public function checkPreAuth(UserInterface $user): void
    {
    }

    /**
     * Runs AFTER the password has been validated. Only the legitimate owner of
     * the account reaches this point, so the "not verified" message can be told
     * here without leaking which addresses are registered.
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->getIsVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n\'est pas vérifié. Veuillez lire le mail qui vous a été envoyé lors de votre inscription et suivre les indications. (Pensez à vérifier vos spams.)'
            );
        }
    }
}
