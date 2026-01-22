<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForcePasswordChangeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $router
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        $excluded = [
            $this->router->generate('app_login'),
            $this->router->generate('app_logout'),
            $this->router->generate('app_change_password'),
        ];

        if (in_array($path, $excluded, true)) {
            return;
        }

        $user = $this->security->getUser();

        if ($user instanceof User && $user->isMustChangePassword())
        {
            $event->setResponse(
                new RedirectResponse($this->router->generate('app_change_password'))
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
        ];
    }
}
