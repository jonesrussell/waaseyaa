<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Fixtures\Templates;

/**
 * A class without #[BundleTemplate] — should be ignored by the compiler.
 */
final class NoBundleTemplateClass
{
    public string $ignored = '';
}
