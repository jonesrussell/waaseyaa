<?php

declare(strict_types=1);

namespace Waaseyaa\Mail;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\Transport\ArrayTransport;
use Waaseyaa\Mail\Transport\LocalTransport;
use Waaseyaa\Mail\Transport\SendGridTransport;
use Waaseyaa\Mail\Transport\TransportInterface;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $mailConfig = $this->config['mail'] ?? [];
        $transportType = $mailConfig['transport'] ?? 'local';
        $fromAddress = trim((string) ($mailConfig['from_address'] ?? ''));
        $fromName = (string) ($mailConfig['from_name'] ?? '');
        $sendgridKey = trim((string) ($mailConfig['sendgrid_api_key'] ?? ''));

        $this->singleton(TransportInterface::class, fn(): TransportInterface => match (true) {
            $sendgridKey !== '' && $fromAddress !== '' => new SendGridTransport(
                apiKey: $sendgridKey,
                fromAddress: $fromAddress,
                fromName: $fromName,
            ),
            $transportType === 'array' => new ArrayTransport(),
            default => new LocalTransport(
                $mailConfig['log_path'] ?? $this->projectRoot . '/var/mail.log',
            ),
        });

        $this->singleton(MailerInterface::class, fn(): Mailer => new Mailer(
            transport: $this->resolve(TransportInterface::class),
            defaultFrom: $fromAddress,
        ));
    }
}
