<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Item;

/**
 * Backed enums implementing this interface provide custom labels for
 * admin form widgets. Without it, EnumItem falls back to the case name.
 *
 * One-method opt-in (T006). Implementing this interface is a deliberate
 * decision by the enum author to expose a human-readable label for each
 * case; EnumItem's case-label resolution checks for the interface and
 * uses `$case->name` as the fallback when absent.
 */
interface LabeledCase
{
    public function getLabel(): string;
}
