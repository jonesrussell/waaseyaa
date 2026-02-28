<?php

declare(strict_types=1);

namespace Aurora\Config;

/**
 * Config factory decorator that adds translation support.
 *
 * Translations are stored as collections in the underlying storage using
 * the naming convention "i18n.{langcode}". Each translation collection
 * contains partial config overrides that are merged on top of the original.
 *
 * Resolution order:
 * 1. Original config from main storage
 * 2. Translation overrides merged on top (shallow merge per top-level key)
 */
final class TranslatableConfigFactory implements TranslatableConfigFactoryInterface
{
    public function __construct(
        private readonly ConfigFactoryInterface $inner,
        private readonly StorageInterface $storage,
        private readonly string $defaultLangcode = 'en',
    ) {}

    public function get(string $name): ConfigInterface
    {
        return $this->inner->get($name);
    }

    public function getEditable(string $name): ConfigInterface
    {
        return $this->inner->getEditable($name);
    }

    /**
     * Get the original (untranslated) config.
     */
    public function getOriginal(string $name): ConfigInterface
    {
        return $this->inner->get($name);
    }

    /**
     * Get a config with translation overrides applied for the given language.
     *
     * If no translation exists for the given langcode, the original config
     * is returned. If the langcode matches the default language, the original
     * config is returned without any override lookup.
     *
     * Translation overrides are shallow-merged on top of the original config data.
     */
    public function getTranslated(string $name, string $langcode): ConfigInterface
    {
        if ($langcode === $this->defaultLangcode) {
            return $this->getOriginal($name);
        }

        $original = $this->inner->get($name);
        $originalData = $original->getRawData();

        $collectionName = 'i18n.' . $langcode;
        $translationStorage = $this->storage->createCollection($collectionName);
        $translationData = $translationStorage->read($name);

        if ($translationData === false) {
            return $original;
        }

        // Merge translation overrides on top of original data
        $mergedData = array_replace($originalData, $translationData);

        return new Config(
            name: $name,
            storage: $this->storage,
            data: $mergedData,
            immutable: true,
            isNew: false,
        );
    }

    /**
     * Get the available translation languages for a given config name.
     *
     * @return string[] Language codes that have translations for this config.
     */
    public function getAvailableLanguages(string $name): array
    {
        $collections = $this->storage->getAllCollectionNames();
        $languages = [];

        foreach ($collections as $collection) {
            if (str_starts_with($collection, 'i18n.')) {
                $langcode = substr($collection, 5);
                $translationStorage = $this->storage->createCollection($collection);
                if ($translationStorage->exists($name)) {
                    $languages[] = $langcode;
                }
            }
        }

        return $languages;
    }

    public function loadMultiple(array $names): array
    {
        return $this->inner->loadMultiple($names);
    }

    public function rename(string $oldName, string $newName): static
    {
        $this->inner->rename($oldName, $newName);

        return $this;
    }

    public function listAll(string $prefix = ''): array
    {
        return $this->inner->listAll($prefix);
    }
}
