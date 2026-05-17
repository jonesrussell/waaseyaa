<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

use Waaseyaa\TypedData\ListInterface;

/**
 * @api
 */
interface FieldItemListInterface extends ListInterface
{
    public function getFieldDefinition(): FieldDefinitionInterface;

    public function __get(string $name): mixed;
}
