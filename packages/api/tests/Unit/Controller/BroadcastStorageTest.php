<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Database\PdoDatabase;

#[CoversClass(BroadcastStorage::class)]
final class BroadcastStorageTest extends TestCase
{
    private PdoDatabase $database;
    private BroadcastStorage $storage;

    protected function setUp(): void
    {
        $this->database = PdoDatabase::createSqlite();
        $this->storage = new BroadcastStorage($this->database);
    }

    #[Test]
    public function pushAndPollReturnsMessages(): void
    {
        $this->storage->push('admin', 'entity.saved', ['type' => 'node', 'id' => '1']);
        $this->storage->push('admin', 'entity.deleted', ['type' => 'node', 'id' => '2']);

        $messages = $this->storage->poll(0);

        $this->assertCount(2, $messages);
        $this->assertSame('entity.saved', $messages[0]['event']);
        $this->assertSame('entity.deleted', $messages[1]['event']);
    }

    #[Test]
    public function pollWithCursorSkipsOlderMessages(): void
    {
        $this->storage->push('admin', 'first', []);
        $messages = $this->storage->poll(0);
        $cursor = $messages[0]['id'];

        $this->storage->push('admin', 'second', []);
        $messages = $this->storage->poll($cursor);

        $this->assertCount(1, $messages);
        $this->assertSame('second', $messages[0]['event']);
    }

    #[Test]
    public function pruneRemovesOldMessages(): void
    {
        $this->storage->push('admin', 'old', []);
        usleep(10_000); // Ensure the message timestamp is strictly in the past.
        $this->storage->prune(0); // prune everything older than 0 seconds

        $messages = $this->storage->poll(0);
        $this->assertCount(0, $messages);
    }
}
