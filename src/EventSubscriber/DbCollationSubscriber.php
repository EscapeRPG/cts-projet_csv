<?php

namespace App\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures MySQL session collation stays consistent with application tables.
 *
 * Some hosting/client setups keep a connection/session with a different collation
 * (e.g. utf8mb4_general_ci), which can trigger "Illegal mix of collations" errors
 * when comparing parameters to utf8mb4_0900_ai_ci columns.
 *
 * This is a pragmatic safety net. It is idempotent and cheap.
 */
final readonly class DbCollationSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_db_collation_set') === 1) {
            return;
        }
        $request->attributes->set('_db_collation_set', 1);

        $conn = $this->em->getConnection();
        $params = $conn->getParams();
        $driver = (string) ($params['driver'] ?? '');
        if ($driver !== 'pdo_mysql' && $driver !== 'mysqli') {
            return;
        }

        try {
            // Align the session variables used for string literals/parameters.
            $conn->executeStatement("SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci");
            $conn->executeStatement("SET collation_connection = 'utf8mb4_0900_ai_ci'");
        } catch (\Throwable) {
            // Ignore: better to serve a request than crash on a best-effort mitigation.
        }
    }
}

