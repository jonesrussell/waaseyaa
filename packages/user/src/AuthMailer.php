<?php

declare(strict_types=1);

namespace Waaseyaa\User;

use Twig\Environment;
use Waaseyaa\Entity\FieldableInterface;
use Waaseyaa\Mail\Envelope;
use Waaseyaa\Mail\MailerInterface;

class AuthMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly bool $authEmailConfigured,
        private readonly Environment $twig,
        private readonly string $baseUrl,
        private readonly string $appName,
    ) {}

    public function isConfigured(): bool
    {
        return $this->authEmailConfigured;
    }

    public function sendPasswordReset(FieldableInterface $user, string $token): void
    {
        if (!$this->authEmailConfigured) {
            return;
        }

        $vars = [
            'user_name' => $user->get('name'),
            'reset_url' => $this->baseUrl . '/reset-password?token=' . $token,
        ];

        $html = $this->twig->render('email/password-reset.html.twig', $vars);
        $text = $this->twig->render('email/password-reset.txt.twig', $vars);

        $this->mailer->send(new Envelope(
            to: $this->recipientList($user->get('mail')),
            from: '',
            subject: "Reset your {$this->appName} password",
            textBody: $text,
            htmlBody: $html,
        ));
    }

    public function sendEmailVerification(FieldableInterface $user, string $token): void
    {
        if (!$this->authEmailConfigured) {
            return;
        }

        $vars = [
            'user_name' => $user->get('name'),
            'verify_url' => $this->baseUrl . '/verify-email?token=' . $token,
        ];

        $html = $this->twig->render('email/email-verification.html.twig', $vars);
        $text = $this->twig->render('email/email-verification.txt.twig', $vars);

        $this->mailer->send(new Envelope(
            to: $this->recipientList($user->get('mail')),
            from: '',
            subject: "Verify your email for {$this->appName}",
            textBody: $text,
            htmlBody: $html,
        ));
    }

    public function sendWelcome(FieldableInterface $user): void
    {
        if (!$this->authEmailConfigured) {
            return;
        }

        $vars = [
            'user_name' => $user->get('name'),
            'home_url' => $this->baseUrl,
        ];

        $html = $this->twig->render('email/welcome.html.twig', $vars);
        $text = $this->twig->render('email/welcome.txt.twig', $vars);

        $this->mailer->send(new Envelope(
            to: $this->recipientList($user->get('mail')),
            from: '',
            subject: "Welcome to {$this->appName}",
            textBody: $text,
            htmlBody: $html,
        ));
    }

    /**
     * @return list<string>
     */
    private function recipientList(mixed $mail): array
    {
        if (!is_string($mail) || $mail === '') {
            return [];
        }

        return [$mail];
    }
}
