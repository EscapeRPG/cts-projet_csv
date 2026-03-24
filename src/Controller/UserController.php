<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Form\CreateNotificationType;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
/**
 * Exposes user-facing profile pages.
 */
final class UserController extends AbstractController
{
    /**
     * Displays the profile page only when the requested user matches the authenticated user.
     *
     * @param UserRepository $userRepository Repository used to retrieve the profile owner.
     * @param int $id User identifier from route parameter.
     *
     * @return Response Rendered profile page or redirect to home when access is not allowed.
     */
    #[Route('/profile/{id}', name: 'app_profile', requirements: ['id' => '\d+'])]
    public function profile(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        NotificationPublisher $notificationPublisher,
        int            $id,
    ): Response
    {
        $userConnected = $this->getUser();
        $user = $userRepository->findUserById($id);

        if ($userConnected !== $user) {
            return $this->redirectToRoute('app_home');
        }

        $notificationForm = null;

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $notification = new Notification();
            $notification->setExpiresAt((new \DateTimeImmutable('+7 days'))->setTime(23, 59, 59));

            $form = $this->createForm(CreateNotificationType::class, $notification, [
                'attr' => ['data-turbo' => 'false'],
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $notification
                    ->setType('manual')
                    ->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($notification);
                $notificationPublisher->publishToAllActiveUsers($notification);
                $entityManager->flush();

                $this->addFlash('success', 'Notification envoyée à tous les utilisateurs actifs.');

                return $this->redirectToRoute('app_profile', ['id' => $id]);
            }

            $notificationForm = $form->createView();
        }

        return $this->render('users/profile.html.twig', [
            'user' => $user,
            'id' => $id,
            'notification_form' => $notificationForm,
        ]);
    }
}
