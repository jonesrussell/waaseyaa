<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Event;

use Waaseyaa\Config\Event\ConfigEvent;
use Waaseyaa\Config\Event\ConfigEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

#[CoversClass(ConfigEvent::class)]
#[CoversClass(ConfigEvents::class)]
final class ConfigEventTest extends TestCase
{
    public function testConstructorSetsNameAndData(): void
    {
        $event = new ConfigEvent('system.site', ['name' => 'My Site']);

        $this->assertSame('system.site', $event->getConfigName());
        $this->assertSame(['name' => 'My Site'], $event->getData());
    }

    public function testDefaultDataIsEmpty(): void
    {
        $event = new ConfigEvent('system.site');

        $this->assertSame([], $event->getData());
    }

    public function testSetDataOverwritesData(): void
    {
        $event = new ConfigEvent('system.site', ['old' => 'value']);
        $event->setData(['new' => 'value']);

        $this->assertSame(['new' => 'value'], $event->getData());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $event = new ConfigEvent('test');

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testConfigEventsEnumValues(): void
    {
        $this->assertSame('waaseyaa.config.pre_save', ConfigEvents::PRE_SAVE->value);
        $this->assertSame('waaseyaa.config.post_save', ConfigEvents::POST_SAVE->value);
        $this->assertSame('waaseyaa.config.pre_delete', ConfigEvents::PRE_DELETE->value);
        $this->assertSame('waaseyaa.config.post_delete', ConfigEvents::POST_DELETE->value);
        $this->assertSame('waaseyaa.config.import', ConfigEvents::IMPORT->value);
    }

    public function testConfigEventsHasFiveCases(): void
    {
        $cases = ConfigEvents::cases();

        $this->assertCount(5, $cases);
    }
}
