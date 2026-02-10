<?php

namespace App\Controller;

use App\Form\SupportRequestType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class SupportController extends AbstractController
{
    /*
     * Affiche une page permettant d'envoyer un email à l'administrateur réseau en cas de demande de réinitialisation de mot de passe non sollicitée
     */
    #[Route('/support', name: 'app_support')]
    public function support(
        Request         $request,
        MailerInterface $mailer,
        Security        $security
    ): Response
    {
        $user = $security->getUser();

        $form = $this->createForm(SupportRequestType::class, [
            'subject' => 'Problème réinitialisation mot de passe',
            'message' => "Bonjour,\n\nJe pense qu'il y a un problème avec mon compte, je viens de recevoir une demande de réinitialisation de mot de passe non sollicitée.\n\nMerci."
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $email = new Email()
                ->from('no-reply@ker-milo.fr')
                ->to('emilienfrancois.ct@gmail.com')
                ->subject('[SUPPORT] ' . $data['subject'])
                ->text(
                    "Utilisateur: " . ($user?->getEmail() ?? 'anonyme') . "\n" .
                    "IP: " . $request->getClientIp() . "\n\n" .
                    $data['message']
                );

            $mailer->send($email);

            $this->addFlash('success', 'Votre message a été envoyé.');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('support/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

}
