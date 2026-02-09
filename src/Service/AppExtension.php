<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private Security $security) {}

    public function getGlobals(): array
    {
        return [
            'user' => $this->security->getUser(),
        ];
    }
}
