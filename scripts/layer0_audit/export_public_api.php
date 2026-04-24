#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Layer 0 audit: extract public classes, interfaces, traits, and file-level functions.
 * Usage: php scripts/layer0_audit/export_public_api.php [output.json]
 */

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$output = $argv[1] ?? $root . '/artifacts/layer0-audit/public_api.json';

$layer0Rel = [
    'packages/foundation/src',
    'packages/cache/src',
    'packages/plugin/src',
    'packages/typed-data/src',
    'packages/database-legacy/src',
    'packages/i18n/src',
    'packages/queue/src',
    'packages/scheduler/src',
    'packages/state/src',
    'packages/validation/src',
    'packages/mail/src',
    'packages/http-client/src',
    'packages/ingestion/src',
    'packages/error-handler/src',
    'packages/geo/src',
    'packages/mercure/src',
    'packages/analytics/src',
    'packages/oauth-provider/src',
];

final class Layer0ApiCollector extends PhpParser\NodeVisitorAbstract
{
    /** @var list<array{kind:string,name:string,file:string,public_methods:list<string>}> */
    public array $symbols = [];

    public function __construct(private readonly string $file) {}

    public function enterNode(PhpParser\Node $node): int|PhpParser\Node|null
    {
        if ($node instanceof PhpParser\Node\Stmt\Class_
            || $node instanceof PhpParser\Node\Stmt\Interface_
            || $node instanceof PhpParser\Node\Stmt\Trait_) {
            if ($node->name === null) {
                return null;
            }
            if (!$node->namespacedName instanceof PhpParser\Node\Name) {
                return null;
            }
            $name = $node->namespacedName->toString();
            if ($this->internalNode($node)) {
                return null;
            }
            $kind = $node instanceof PhpParser\Node\Stmt\Interface_ ? 'interface'
                : ($node instanceof PhpParser\Node\Stmt\Trait_ ? 'trait' : 'class');
            $methods = [];
            if ($node instanceof PhpParser\Node\Stmt\Class_ || $node instanceof PhpParser\Node\Stmt\Interface_) {
                foreach ($node->getMethods() as $m) {
                    if ($m->isPrivate()) {
                        continue;
                    }
                    if ($this->internalNode($m)) {
                        continue;
                    }
                    $methods[] = $m->name->toString();
                }
            }
            $this->symbols[] = [
                'kind' => $kind,
                'name' => $name,
                'file' => $this->file,
                'public_methods' => $methods,
            ];
        }
        if ($node instanceof PhpParser\Node\Stmt\Function_) {
            if ($node->namespacedName === null) {
                return null;
            }
            if ($this->internalNode($node)) {
                return null;
            }
            $this->symbols[] = [
                'kind' => 'function',
                'name' => $node->namespacedName->toString(),
                'file' => $this->file,
                'public_methods' => [],
            ];
        }

        return null;
    }

    private function internalNode(PhpParser\Node $node): bool
    {
        $doc = $node->getDocComment();
        if ($doc !== null && str_contains($doc->getText(), '@internal')) {
            return true;
        }
        if ($node instanceof PhpParser\Node\Stmt\ClassLike && $node->namespacedName !== null
            && str_contains($node->namespacedName->toString(), '\\Internal\\')) {
            return true;
        }

        return false;
    }
}

$parser = (new PhpParser\ParserFactory())->createForHostVersion();
$all = [];

foreach ($layer0Rel as $rel) {
    $dir = $root . '/' . $rel;
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $code = file_get_contents($path);
        if ($code === false) {
            continue;
        }
        try {
            $ast = $parser->parse($code);
        } catch (Throwable) {
            continue;
        }
        if ($ast === null) {
            continue;
        }
        $relFile = str_replace($root . '/', '', $path);
        $collector = new Layer0ApiCollector($relFile);
        $tr = new PhpParser\NodeTraverser();
        $tr->addVisitor(new PhpParser\NodeVisitor\NameResolver());
        $tr->addVisitor($collector);
        $tr->traverse($ast);
        foreach ($collector->symbols as $sym) {
            $all[] = $sym;
        }
    }
}

$dirOut = dirname($output);
if (!is_dir($dirOut)) {
    mkdir($dirOut, 0755, true);
}
file_put_contents($output, json_encode(['symbols' => $all, 'count' => count($all)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, 'Wrote ' . count($all) . " symbols to {$output}\n");
