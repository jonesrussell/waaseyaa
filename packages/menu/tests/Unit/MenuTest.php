<?php

declare(strict_types=1);

namespace Waaseyaa\Menu\Tests\Unit;

use Waaseyaa\Menu\Menu;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Waaseyaa\Menu\Menu
 */
final class MenuTest extends TestCase
{
    public function testEntityTypeId(): void
    {
        $menu = new Menu(['id' => 'main', 'label' => 'Main navigation']);

        $this->assertSame('menu', $menu->getEntityTypeId());
    }

    public function testIdAndLabel(): void
    {
        $menu = new Menu(['id' => 'footer', 'label' => 'Footer menu']);

        $this->assertSame('footer', $menu->id());
        $this->assertSame('Footer menu', $menu->label());
    }

    public function testDescriptionDefaultsToEmpty(): void
    {
        $menu = new Menu(['id' => 'main']);

        $this->assertSame('', $menu->getDescription());
    }

    public function testGetSetDescription(): void
    {
        $menu = new Menu(['id' => 'main', 'description' => 'Primary navigation']);

        $this->assertSame('Primary navigation', $menu->getDescription());

        $menu->setDescription('Updated description');
        $this->assertSame('Updated description', $menu->getDescription());
    }

    public function testLockedDefaultsToFalse(): void
    {
        $menu = new Menu(['id' => 'custom']);

        $this->assertFalse($menu->isLocked());
    }

    public function testGetSetLocked(): void
    {
        $menu = new Menu(['id' => 'main', 'locked' => true]);

        $this->assertTrue($menu->isLocked());

        $menu->setLocked(false);
        $this->assertFalse($menu->isLocked());
    }

    public function testLockedFromValues(): void
    {
        $menu = new Menu(['id' => 'main', 'locked' => true]);

        $this->assertTrue($menu->isLocked());
    }

    public function testStatusDefaults(): void
    {
        $menu = new Menu(['id' => 'main']);

        $this->assertTrue($menu->status());
    }

    public function testEnableDisable(): void
    {
        $menu = new Menu(['id' => 'main']);

        $menu->disable();
        $this->assertFalse($menu->status());

        $menu->enable();
        $this->assertTrue($menu->status());
    }

    public function testToConfig(): void
    {
        $menu = new Menu([
            'id' => 'main',
            'label' => 'Main navigation',
            'description' => 'The primary site navigation',
            'locked' => true,
        ]);

        $config = $menu->toConfig();

        $this->assertSame('main', $config['id']);
        $this->assertSame('Main navigation', $config['label']);
        $this->assertSame('The primary site navigation', $config['description']);
        $this->assertTrue($config['locked']);
        $this->assertTrue($config['status']);
    }

    public function testToConfigWithDisabledStatus(): void
    {
        $menu = new Menu(['id' => 'footer', 'status' => false]);

        $config = $menu->toConfig();

        $this->assertFalse($config['status']);
    }

    public function testToArray(): void
    {
        $menu = new Menu(['id' => 'admin', 'label' => 'Administration']);

        $array = $menu->toArray();

        $this->assertSame('admin', $array['id']);
        $this->assertSame('Administration', $array['label']);
    }

    public function testConfigEntityHasNoUuid(): void
    {
        $menu = new Menu(['id' => 'test']);

        // Config entities do not have UUIDs.
        $this->assertSame('', $menu->uuid());
    }

    public function testFluentInterface(): void
    {
        $menu = new Menu(['id' => 'main']);

        $result = $menu->setDescription('desc')->setLocked(true);

        $this->assertSame($menu, $result);
    }
}
