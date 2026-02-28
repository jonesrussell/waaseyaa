<?php

declare(strict_types=1);

namespace Aurora\Config\Tests\Unit;

use Aurora\Config\ConfigFactory;
use Aurora\Config\ConfigFactoryInterface;
use Aurora\Config\Storage\MemoryStorage;
use Aurora\Config\TranslatableConfigFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(TranslatableConfigFactory::class)]
final class TranslatableConfigFactoryTest extends TestCase
{
    private MemoryStorage $storage;
    private ConfigFactoryInterface $innerFactory;
    private TranslatableConfigFactory $factory;

    protected function setUp(): void
    {
        $this->storage = new MemoryStorage();
        $this->innerFactory = new ConfigFactory(
            storage: $this->storage,
            eventDispatcher: new EventDispatcher(),
        );
        $this->factory = new TranslatableConfigFactory(
            inner: $this->innerFactory,
            storage: $this->storage,
            defaultLangcode: 'en',
        );
    }

    #[Test]
    public function get_returns_config_from_inner_factory(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Aurora']);

        $config = $this->factory->get('system.site');

        $this->assertSame('Aurora', $config->get('site_name'));
    }

    #[Test]
    public function get_original_returns_untranslated_config(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Aurora']);

        // Write a French translation collection
        $frCollection = $this->storage->createCollection('i18n.fr');
        $frCollection->write('system.site', ['site_name' => 'Aurore']);

        $original = $this->factory->getOriginal('system.site');

        $this->assertSame('Aurora', $original->get('site_name'));
    }

    #[Test]
    public function get_translated_returns_translated_config(): void
    {
        $this->storage->write('system.site', [
            'site_name' => 'Aurora',
            'slogan' => 'The CMS',
            'default_langcode' => 'en',
        ]);

        // Write a French translation
        $frCollection = $this->storage->createCollection('i18n.fr');
        $frCollection->write('system.site', [
            'site_name' => 'Aurore',
            'slogan' => 'Le CMS',
        ]);

        $translated = $this->factory->getTranslated('system.site', 'fr');

        // Translated keys should be overridden
        $this->assertSame('Aurore', $translated->get('site_name'));
        $this->assertSame('Le CMS', $translated->get('slogan'));
        // Non-translated keys should fall back to original
        $this->assertSame('en', $translated->get('default_langcode'));
    }

    #[Test]
    public function get_translated_falls_back_to_original_when_no_translation(): void
    {
        $this->storage->write('system.site', [
            'site_name' => 'Aurora',
            'slogan' => 'The CMS',
        ]);

        $translated = $this->factory->getTranslated('system.site', 'de');

        $this->assertSame('Aurora', $translated->get('site_name'));
        $this->assertSame('The CMS', $translated->get('slogan'));
    }

    #[Test]
    public function get_translated_with_default_langcode_returns_original(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Aurora']);

        $translated = $this->factory->getTranslated('system.site', 'en');

        $this->assertSame('Aurora', $translated->get('site_name'));
    }

    #[Test]
    public function get_translated_merges_partial_translation(): void
    {
        $this->storage->write('system.site', [
            'site_name' => 'Aurora',
            'slogan' => 'The CMS',
            'footer' => 'Copyright 2026',
        ]);

        // French translation only has site_name
        $frCollection = $this->storage->createCollection('i18n.fr');
        $frCollection->write('system.site', [
            'site_name' => 'Aurore',
        ]);

        $translated = $this->factory->getTranslated('system.site', 'fr');

        $this->assertSame('Aurore', $translated->get('site_name'));
        $this->assertSame('The CMS', $translated->get('slogan'));
        $this->assertSame('Copyright 2026', $translated->get('footer'));
    }

    #[Test]
    public function get_editable_delegates_to_inner_factory(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Aurora']);

        $editable = $this->factory->getEditable('system.site');
        $editable->set('site_name', 'New Name')->save();

        $reloaded = $this->factory->getOriginal('system.site');
        $this->assertSame('New Name', $reloaded->get('site_name'));
    }

    #[Test]
    public function load_multiple_returns_multiple_configs(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Aurora']);
        $this->storage->write('system.mail', ['transport' => 'smtp']);

        $configs = $this->factory->loadMultiple(['system.site', 'system.mail']);

        $this->assertCount(2, $configs);
        $this->assertSame('Aurora', $configs['system.site']->get('site_name'));
        $this->assertSame('smtp', $configs['system.mail']->get('transport'));
    }

    #[Test]
    public function rename_delegates_to_inner_factory(): void
    {
        $this->storage->write('system.old', ['key' => 'value']);

        $this->factory->rename('system.old', 'system.new');

        $this->assertFalse($this->storage->exists('system.old'));
        $this->assertTrue($this->storage->exists('system.new'));
    }

    #[Test]
    public function list_all_delegates_to_inner_factory(): void
    {
        $this->storage->write('system.site', ['a' => 1]);
        $this->storage->write('system.mail', ['b' => 2]);
        $this->storage->write('user.settings', ['c' => 3]);

        $all = $this->factory->listAll('system.');

        $this->assertSame(['system.mail', 'system.site'], $all);
    }

    #[Test]
    public function get_available_languages_returns_collection_langcodes(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Aurora']);
        $this->storage->createCollection('i18n.fr')->write('system.site', ['site_name' => 'Aurore']);
        $this->storage->createCollection('i18n.de')->write('system.site', ['site_name' => 'Aurora DE']);

        $languages = $this->factory->getAvailableLanguages('system.site');

        sort($languages);
        $this->assertSame(['de', 'fr'], $languages);
    }
}
