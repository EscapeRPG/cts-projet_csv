<?php

namespace App\Controller;

use App\Entity\Salarie;
use App\Entity\User;
use App\Form\CreateSalarieType;
use App\Form\CreateUserType;
use App\Repository\SalarieRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted("ROLE_ADMIN")]
final class AdminController extends AbstractController
{
    /*
     * Affiche la liste des utilisateurs du site
     */
    #[Route("/admin/users/list", name: 'app_users_list')]
    public function list(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('users/list.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     *
     * Permet d'ajouter un nouvel utilisateur du site
     */
    #[Route("/admin/users/add", name: 'app_users_add')]
    public function addUser(
        Request                $request,
        EntityManagerInterface $em,
        MailerInterface        $mailer
    ): Response
    {
        $form = $this->createForm(CreateUserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();

            $token = Uuid::v4()->toRfc4122();
            $user->setActivationToken($token);
            $user->setIsActive(false);
            $user->setPassword('!');

            $role = $form->get('roles')->getData();
            $user->setRoles([$role]);

            $em->persist($user);
            $em->flush();

            $activationUrl = $this->generateUrl(
                'app_activate_account',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = new TemplatedEmail()
                ->from(new Address('emilienfrancois.ct@gmail.com', 'Ker-Milo'))
                ->to($user->getEmail())
                ->subject('Votre compte a été créé')
                ->htmlTemplate('emails/user_created.html.twig')
                ->context([
                    'activationUrl' => $activationUrl,
                    'username' => $user->getUsername(),
                ]);

            $mailer->send($email);

            $this->addFlash('success', 'Utilisateur créé et mail envoyé.');

            return $this->redirectToRoute('app_users_list');
        }

        return $this->render('users/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /*
     * Permet de transformer un utilisateur en administrateur et inversement
     */
    #[Route('/admin/users/promote/{id}', name: 'app_users_promote', requirements: ['id' => '\d+'])]
    public function promoteUser(User $user, EntityManagerInterface $em): Response
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            $roles = array_diff($roles, ['ROLE_ADMIN']);
            $roles[] = 'ROLE_IMPORT';
        } else {
            $roles = array_diff($roles, ['ROLE_IMPORT']);
            $roles[] = 'ROLE_ADMIN';
        }

        $user->setRoles(array_values($roles));
        $em->flush();

        return $this->redirectToRoute('app_users_list');
    }

    /*
     * Permet de supprimer un utilisateur du site
     */
    #[Route("/admin/users/delete/{id}", name: 'app_users_delete', requirements: ['id' => '\d+'])]
    public function deleteUser(User $user, EntityManagerInterface $em, Request $request): Response
    {
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete-user-' . $user->getId(), $token)) {
            $this->addFlash('error', 'Token invalide, suppression annulée.');
            return $this->redirectToRoute('app_users_list');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès.');

        return $this->redirectToRoute('app_users_list');
    }

    /*
     * Affiche la liste des salariés sous forme de formulaire pour les modifier directement
     */
    #[Route("/admin/salaries/list", name: 'app_salaries_list')]
    public function listSalaries(
        SalarieRepository    $salarieRepository,
        FormFactoryInterface $formFactory
    ): Response
    {
        $salaries = $salarieRepository->findBy([], ['nom' => 'ASC']);
        $forms = [];

        foreach ($salaries as $salarie) {
            $forms[$salarie->getId()] = $formFactory
                ->createNamed(
                    'salarie_' . $salarie->getId(),
                    CreateSalarieType::class,
                    $salarie,
                    [
                        'csrf_field_name' => '_token',
                        'csrf_token_id' => 'salarie_' . $salarie->getId(),
                    ]
                )
                ->createView();
        }

        return $this->render('salaries/list.html.twig', [
            'salaries' => $salaries,
            'forms' => $forms,
        ]);
    }

    /*
     * Permet d'ajouter un nouveau salarié à la liste
     */
    #[Route("/admin/salaries/add", name: 'app_salaries_add')]
    public function addSalarie(
        Request                $request,
        EntityManagerInterface $em
    ): Response
    {
        $form = $this->createForm(CreateSalarieType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $salarie = $form->getData();

            $em->persist($salarie);
            $em->flush();

            $this->addFlash('success', 'Salarié créé.');

            return $this->redirectToRoute('app_salaries_list');
        }

        return $this->render('salaries/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /*
     * Met à jour le salarié modifié
     */
    #[Route('/admin/salaries/update/{id}', name: 'app_salaries_update', methods: ['POST'])]
    public function updateSalarie(
        Salarie                $salarie,
        Request                $request,
        EntityManagerInterface $em,
        FormFactoryInterface   $formFactory
    ): Response
    {
        $form = $formFactory->createNamed(
            'salarie_' . $salarie->getId(),
            CreateSalarieType::class,
            $salarie,
            [
                'csrf_field_name' => '_token',
                'csrf_token_id' => 'salarie_' . $salarie->getId(),
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($salarie);
            $em->flush();

            $this->addFlash('success', "Salarié {$salarie->getNom()} mis à jour.");

            return $this->redirectToRoute('app_salaries_list');
        }

        $salaries = $em->getRepository(Salarie::class)->findAll();
        $forms = [];

        foreach ($salaries as $s) {
            if ($s->getId() === $salarie->getId()) {
                $forms[$s->getId()] = $form->createView();
            } else {
                $forms[$s->getId()] = $formFactory
                    ->createNamed('salarie_' . $s->getId(), CreateSalarieType::class, $s)
                    ->createView();
            }
        }

        $this->addFlash('error', 'Erreur dans le formulaire, vérifiez les champs.');

        return $this->render('salaries/list.html.twig', [
            'salaries' => $salaries,
            'forms' => $forms,
        ]);
    }
}
