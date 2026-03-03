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
    /**
     * @param string $mailerFromAddress Sender email address used for support messages.
     * @param string $mailerFromName Sender display name used for support messages.
     * @param string $supportToAddress Recipient support email address.
     */
    public function __construct(
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
        private readonly string $supportToAddress
    )
    {
    }

    /**
     * Displays and processes the support contact form for account-related incidents.
     *
     * @param Request $request Current HTTP request containing context and form submission data.
     * @param MailerInterface $mailer Mailer service used to send the support email.
     * @param Security $security Security helper used to resolve the current user.
     *
     * @return Response Rendered support form page or redirect to home after successful send.
     */
    #[Route('/support', name: 'app_support')]
    public function support(
        Request         $request,
        MailerInterface $mailer,
        Security        $security
    ): Response
    {
        $user = $security->getUser();
        $context = strtolower(trim((string)$request->query->get('context', 'reset')));

        $subject = 'Problème réinitialisation mot de passe';
        $message = "Bonjour,\n\nJe pense qu'il y a un problème avec mon compte, je viens de recevoir une demande de réinitialisation de mot de passe non sollicitée.\n\nMerci.";

        if ($context === 'creation') {
            $subject = 'Problème création de compte';
            $message = "Bonjour,\n\nJe pense qu'il y a un problème avec mon compte, je viens de recevoir un email de création de compte non sollicité.\n\nMerci.";
        }

        $form = $this->createForm(SupportRequestType::class, [
            'subject' => $subject,
            'message' => $message
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $email = new Email()
                ->from(new \Symfony\Component\Mime\Address($this->mailerFromAddress, $this->mailerFromName))
                ->to($this->supportToAddress)
                ->subject('[SUPPORT] ' . $data['subject'])
                ->text(
                    "Utilisateur: " . ($user?->getEmail() ?? 'anonyme') . "\n" .
                    "Contexte: " . $context . "\n" .
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
