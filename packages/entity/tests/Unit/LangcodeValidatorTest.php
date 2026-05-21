<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\LangcodeValidator;

#[CoversClass(LangcodeValidator::class)]
final class LangcodeValidatorTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function validLangcodes(): array
    {
        return [
            'simple language'            => ['en'],
            'language + region'          => ['en-US'],
            'language + region CA'       => ['fr-CA'],
            'language + script'          => ['zh-Hant'],
            'language + script + region' => ['zh-Hant-TW'],
            'three-letter language'      => ['mas'],
            'lowercase region'           => ['en-us'],
            'uppercase language'         => ['EN'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidLangcodes(): array
    {
        return [
            'empty string'              => [''],
            'injection semicolon'       => ["en-US'); DROP TABLE users;--"],
            'injection single quote'    => ["en'"],
            'injection newline'         => ["en\n"],
            'injection null byte'       => ["en\x00"],
            'leading whitespace'        => [' en'],
            'trailing whitespace'       => ['en '],
            'too short'                 => ['e'],
            'too long language subtag'  => ['toolonglang'],
            'digit language'            => ['12'],
            'variant subtag'            => ['en-US-x-twain'],
            'slash'                     => ['en/US'],
            'injection tab'             => ["en\t"],
        ];
    }

    #[Test]
    #[DataProvider('validLangcodes')]
    public function validLangcodeDoesNotThrow(string $langcode): void
    {
        $this->expectNotToPerformAssertions();
        LangcodeValidator::validate($langcode);
    }

    #[Test]
    #[DataProvider('invalidLangcodes')]
    public function invalidLangcodeThrowsInvalidArgumentException(string $langcode): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LangcodeValidator::validate($langcode);
    }
}
