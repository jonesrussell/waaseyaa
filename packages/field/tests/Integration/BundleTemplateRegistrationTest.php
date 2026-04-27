<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Field\BundleTemplateCompiler;
use Waaseyaa\Field\FieldDefinitionRegistry;
use Waaseyaa\Field\Tests\Fixtures\Templates\SampleArticleTemplate;

/**
 * Integration test: verifies that BundleTemplateCompiler + FieldDefinitionRegistry
 * work end-to-end against a real fixture template class.
 */
#[CoversNothing]
final class BundleTemplateRegistrationTest extends TestCase
{
    #[Test]
    public function compiling_sample_article_template_registers_three_fields(): void
    {
        $registry = new FieldDefinitionRegistry();
        $compiler = new BundleTemplateCompiler($registry);

        $compiler->compile([SampleArticleTemplate::class]);

        $fields = $registry->bundleFieldsFor('node', 'article');
        self::assertCount(3, $fields);
    }

    #[Test]
    public function registered_field_names_match_declared_keys(): void
    {
        $registry = new FieldDefinitionRegistry();
        $compiler = new BundleTemplateCompiler($registry);
        $compiler->compile([SampleArticleTemplate::class]);

        $fields = $registry->bundleFieldsFor('node', 'article');
        $names = array_keys($fields);

        self::assertSame(['title', 'body', 'tags'], $names);
    }

    #[Test]
    public function registered_fields_have_correct_labels(): void
    {
        $registry = new FieldDefinitionRegistry();
        $compiler = new BundleTemplateCompiler($registry);
        $compiler->compile([SampleArticleTemplate::class]);

        $fields = $registry->bundleFieldsFor('node', 'article');

        self::assertSame('Title', $fields['title']->getLabel());
        self::assertSame('Body', $fields['body']->getLabel());
        self::assertSame('Tags', $fields['tags']->getLabel());
    }

    #[Test]
    public function registered_fields_have_correct_groups(): void
    {
        $registry = new FieldDefinitionRegistry();
        $compiler = new BundleTemplateCompiler($registry);
        $compiler->compile([SampleArticleTemplate::class]);

        $fields = $registry->bundleFieldsFor('node', 'article');

        self::assertSame('basic', $fields['title']->getGroup());
        self::assertSame('content', $fields['body']->getGroup());
        self::assertSame('metadata', $fields['tags']->getGroup());
    }

    #[Test]
    public function registered_fields_have_correct_prompt_aliases(): void
    {
        $registry = new FieldDefinitionRegistry();
        $compiler = new BundleTemplateCompiler($registry);
        $compiler->compile([SampleArticleTemplate::class]);

        $fields = $registry->bundleFieldsFor('node', 'article');

        self::assertSame(['headline', 'subject'], $fields['title']->getPromptAliases());
        self::assertSame(['content', 'text'], $fields['body']->getPromptAliases());
        self::assertSame(['keywords', 'labels'], $fields['tags']->getPromptAliases());
    }

    #[Test]
    public function field_service_provider_boot_wires_compiler(): void
    {
        $provider = new \Waaseyaa\Field\FieldServiceProvider();
        $provider->register();
        $provider->boot();

        // Boot with empty class list is a no-op — verifies no exceptions thrown.
        $compiler = $provider->resolve(\Waaseyaa\Field\BundleTemplateCompiler::class);
        self::assertInstanceOf(BundleTemplateCompiler::class, $compiler);
    }
}
