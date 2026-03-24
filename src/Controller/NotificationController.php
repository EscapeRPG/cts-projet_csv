<?php

namespace App\Controller;

use App\Entity\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/notifications/{id}/read', name: 'app_notifications_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markAsRead(
        UserNotification $userNotification,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $token = $request->request->get('_token', $request->headers->get('X-CSRF-Token'));
        if (!is_string($token) || !$this->isCsrfTokenValid('read-notification-' . $userNotification->getId(), $token)) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        if ($userNotification->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Cette notification ne t’appartient pas.'], Response::HTTP_FORBIDDEN);
        }

        if ($userNotification->getReadAt() === null) {
            $userNotification->setReadAt(new \DateTimeImmutable());
            $entityManager->flush();
        }

        return new JsonResponse([
            'status' => 'ok',
            'id' => $userNotification->getId(),
        ]);
    }
}
