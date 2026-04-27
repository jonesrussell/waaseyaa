<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command\Make;

use Waaseyaa\CLI\Command\Make\MakeProviderCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(MakeProviderCommand::class)]
final class MakeProviderCommandTest extends TestCase
{
    #[Test]
    public function it_generates_a_service_provider(): void
    {
        $tester = $this->createTester();
        $tester->execute(['name' => 'SitemapServiceProvider']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('class SitemapServiceProvider extends ServiceProvider', $output);
        $this->assertStringContainsString('public function register(): void', $output);
        $this->assertStringContainsString('public function boot(): void', $output);
        $this->assertStringContainsString('declare(strict_types=1);', $output);
    }

    #[Test]
    public function it_converts_snake_case_name(): void
    {
        $tester = $this->createTester();
        $tester->execute(['name' => 'sitemap_service_provider']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('class SitemapServiceProvider extends ServiceProvider', $output);
    }

    #[Test]
    public function it_appends_service_provider_suffix(): void
    {
        $tester = $this->createTester();
        $tester->execute(['name' => 'Blog']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('class BlogServiceProvider extends ServiceProvider', $output);
    }

    #[Test]
    public function it_generates_domain_provider_with_entity_boilerplate(): void
    {
        $tester = $this->createTester();
        $tester->execute(['name' => 'Blog', '--domain' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('class BlogServiceProvider extends ServiceProvider', $output);
        $this->assertStringContainsString('EntityType::fromClass(', $output);
        $this->assertStringContainsString('Blog::class', $output);
        $this->assertStringContainsString("group: 'blog'", $output);
        $this->assertStringNotContainsString('fieldDefinitions:', $output);
        $this->assertStringNotContainsString('new EntityType(', $output);
    }

    #[Test]
    public function domain_flag_converts_multi_word_to_snake_case(): void
    {
        $tester = $this->createTester();
        $tester->execute(['name' => 'UserProfile', '--domain' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('class UserProfileServiceProvider extends ServiceProvider', $output);
        $this->assertStringContainsString("group: 'user_profile'", $output);
    }

    private function createTester(): CommandTester
    {
        $app = new Application();
        $app->add(new MakeProviderCommand());
        $command = $app->find('make:provider');

        return new CommandTester($command);
    }
}
