<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Patch;

use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\DeclareDeclare;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter\Standard;

final class PhpFileBuilder
{
    private readonly Standard $printer;

    public function __construct()
    {
        $this->printer = new Standard();
    }

    /** @param array<string, mixed> $fieldConfig */
    public function buildFieldDefinitionPatch(
        string $entityType,
        string $fieldName,
        array $fieldConfig,
    ): string {
        $items = [];
        foreach ($fieldConfig as $key => $value) {
            $items[] = new ArrayItem(
                $this->valueToNode($value),
                new String_($key),
            );
        }

        $factory = new BuilderFactory();

        $stmts = [
            new Declare_([new DeclareDeclare('strict_types', new Int_(1))]),
            $factory->namespace("Waaseyaa\\FieldDefinition\\{$this->toPascalCase($entityType)}")
                ->getNode(),
            new Return_(new Array_([
                new ArrayItem(
                    new Array_($items, ['kind' => Array_::KIND_SHORT]),
                    new String_($fieldName),
                ),
            ], ['kind' => Array_::KIND_SHORT])),
        ];

        return $this->printer->prettyPrintFile($stmts) . "\n";
    }

    private function valueToNode(mixed $value): \PhpParser\Node\Expr
    {
        if (is_string($value)) {
            return new String_($value);
        }

        if (is_int($value)) {
            return new Int_($value);
        }

        if (is_bool($value)) {
            return new \PhpParser\Node\Expr\ConstFetch(
                new \PhpParser\Node\Name($value ? 'true' : 'false'),
            );
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $k => $v) {
                $items[] = is_string($k)
                    ? new ArrayItem($this->valueToNode($v), new String_($k))
                    : new ArrayItem($this->valueToNode($v));
            }

            return new Array_($items, ['kind' => Array_::KIND_SHORT]);
        }

        return new String_((string) $value);
    }

    private function toPascalCase(string $name): string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }
}
