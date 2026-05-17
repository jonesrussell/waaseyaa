<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy;

use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Entity\EntityTypeManagerInterface;

/**
 * Late binding for genealogy access policies (kernel discovery instantiates policies
 * without constructor DI). {@see GenealogyServiceProvider::configureHttpKernel()} wires this.
 * @api
 */
final class GenealogyBootstrap
{
    private static ?EntityTypeManagerInterface $entityTypeManager = null;

    private static ?EntityAccessHandler $accessHandler = null;

    public static function bind(
        ?EntityTypeManagerInterface $entityTypeManager,
        ?EntityAccessHandler $accessHandler,
    ): void {
        self::$entityTypeManager = $entityTypeManager;
        self::$accessHandler = $accessHandler;
    }

    public static function reset(): void
    {
        self::$entityTypeManager = null;
        self::$accessHandler = null;
    }

    public static function entityTypeManager(): ?EntityTypeManagerInterface
    {
        return self::$entityTypeManager;
    }

    public static function accessHandler(): ?EntityAccessHandler
    {
        return self::$accessHandler;
    }
}
