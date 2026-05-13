<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Plugin\Process;

use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;

/**
 * The trivial processor: read a named source field and return it untouched.
 *
 * `PassThrough` underpins the string-shorthand syntax in the manifest
 * (`'title' => 'post_title'` resolves to `PassThrough('post_title')`). It is
 * always the head of a chain — the `$value` argument supplied by the runner
 * is ignored.
 *
 * @api
 *
 * @spec FR-010 — framework-reserved process plugin (`pass_through`)
 */
final readonly class PassThroughProcessor implements ProcessPluginInterface
{
    /**
     * @param string $sourceField Source-record field to read. Non-empty.
     *
     * @throws \InvalidArgumentException If $sourceField is empty.
     */
    public function __construct(public string $sourceField)
    {
        if ($sourceField === '') {
            throw new \InvalidArgumentException('PassThroughProcessor::$sourceField must be a non-empty string.');
        }
    }

    public function id(): string
    {
        return ReservedPluginIds::PASS_THROUGH;
    }

    public function stability(): string
    {
        return 'stable';
    }

    /**
     * @param mixed $value Ignored — `PassThrough` is always the chain head.
     */
    public function transform(mixed $value, ProcessContext $context): mixed
    {
        return $context->sourceRecord->field($this->sourceField, null);
    }
}
