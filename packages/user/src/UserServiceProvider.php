<?php

declare(strict_types=1);

namespace Waaseyaa\User;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\MailerInterface;

final class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(EntityType::fromClass(
            User::class,
            group: 'people',
        ));

        $this->entityType(EntityType::fromClass(
            UserBlock::class,
            group: 'user',
        ));

        $this->singleton(UserBlockService::class, fn() => new UserBlockService(
            $this->resolve(EntityTypeManager::class),
        ));

        $config = $this->config ?? [];
        $mailConfig = $config['mail'] ?? [];
        $authEmailConfigured = trim((string) ($mailConfig['sendgrid_api_key'] ?? '')) !== ''
            && trim((string) ($mailConfig['from_address'] ?? '')) !== '';

        $this->singleton(AuthMailer::class, fn() => new AuthMailer(
            mailer: $this->resolve(MailerInterface::class),
            authEmailConfigured: $authEmailConfigured,
            twig: \Waaseyaa\SSR\SsrServiceProvider::getTwigEnvironment(),
            baseUrl: $config['app']['url']
                ?? (getenv('APP_URL') !== false && getenv('APP_URL') !== '' ? (string) getenv('APP_URL') : 'http://localhost:8000'),
            appName: $config['app']['name']
                ?? (getenv('APP_NAME') !== false && getenv('APP_NAME') !== '' ? (string) getenv('APP_NAME') : 'Waaseyaa'),
        ));
    }

}
