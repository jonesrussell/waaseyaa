<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\Command\Make\AbstractMakeHandler;

/**
 * @api
 */
final class MakeProviderHandler extends AbstractMakeHandler
{
    public function execute(CliIO $io): int
    {
        $name = (string) $io->argument('name');
        $isDomain = (bool) $io->option('domain');

        // Normalize: "Blog" → "BlogServiceProvider", "BlogServiceProvider" → as-is.
        $className = $this->toPascalCase($name);
        if (!str_ends_with($className, 'ServiceProvider')) {
            $className .= 'ServiceProvider';
        }

        // Extract domain name from class: "BlogServiceProvider" → "Blog".
        $domain = str_replace('ServiceProvider', '', $className);

        if ($isDomain) {
            $entityTypeId = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $domain) ?? $domain);
            $rendered = $this->renderStub('provider-domain', [
                'class' => $className,
                'domain' => $domain,
                'entity_namespace' => 'App\\Entity',
                'entity_class' => $domain,
                'entity_type_id' => $entityTypeId,
                'entity_label' => $domain,
            ]);
        } else {
            $rendered = $this->renderStub('provider', [
                'class' => $className,
            ]);
        }

        $io->write($rendered);

        return 0;
    }
}
