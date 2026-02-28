<?php

declare(strict_types=1);

namespace Aurora\Config;

/**
 * Extended config factory interface with translation support.
 *
 * Implementations provide getTranslated() for fetching config with
 * language-specific overrides, and getOriginal() for the untranslated
 * version.
 */
interface TranslatableConfigFactoryInterface extends ConfigFactoryInterface
{
    /**
     * Get a config with translation overrides for the given language.
     *
     * If no translation exists for the given langcode, the original
     * config is returned unchanged.
     */
    public function getTranslated(string $name, string $langcode): ConfigInterface;

    /**
     * Get the original (untranslated) config.
     */
    public function getOriginal(string $name): ConfigInterface;

    /**
     * Get the list of available translation languages for a config name.
     *
     * @return string[] Language codes with existing translations.
     */
    public function getAvailableLanguages(string $name): array;
}
