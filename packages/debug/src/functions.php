<?php

declare(strict_types=1);

use Symfony\Component\VarDumper\VarDumper;

if (!\function_exists('dump')) {
    /**
     * @param mixed ...$vars
     */
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $v) {
            VarDumper::dump($v);
        }
    }
}

if (!\function_exists('dd')) {
    /**
     * @param mixed ...$vars
     *
     * @return never
     */
    function dd(mixed ...$vars): never
    {
        dump(...$vars);
        exit(1);
    }
}
