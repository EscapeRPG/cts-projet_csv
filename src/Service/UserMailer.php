<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class UserMailer
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendWelcomeEmail(User $user, string $plainPassword): void
    {
        $email = new TemplatedEmail()
            ->from(new Address('emilienfrancois.ct@gmail.com', 'Mon App'))
            ->to($user->getEmail())
            ->subject('Votre compte a été créé')
            ->htmlTemplate('emails/user_created.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'password' => $plainPassword,
            ]);

        $this->mailer->send($email);
    }
}
