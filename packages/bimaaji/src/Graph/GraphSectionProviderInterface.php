<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Graph;

interface GraphSectionProviderInterface
{
    public function getKey(): string;

    public function provide(): GraphSection;
}
