<?php

declare(strict_types=1);

namespace Waaseyaa\GitHub;

/**
 * @api
 */
final readonly class PullRequest
{
    public function __construct(
        public int $number,
        public string $url,
        public string $title,
        public string $state,
    ) {}
}
