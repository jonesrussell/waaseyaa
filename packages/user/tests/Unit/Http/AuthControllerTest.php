<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\AnonymousUser;
use Waaseyaa\User\Http\AuthController;
use Waaseyaa\User\User;

#[CoversClass(AuthController::class)]
final class AuthControllerTest extends TestCase
{
    #[Test]
    public function meReturnsUserDataForAuthenticatedAccount(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice', 'mail' => 'alice@example.com', 'roles' => ['editor']]);
        $controller = new AuthController();

        $result = $controller->me($user);

        $this->assertSame(200, $result['statusCode']);
        $this->assertSame(5, $result['data']['id']);
        $this->assertSame('alice', $result['data']['name']);
        $this->assertSame('alice@example.com', $result['data']['email']);
        $this->assertSame(['editor'], $result['data']['roles']);
    }

    #[Test]
    public function meReturns401ForAnonymousAccount(): void
    {
        $controller = new AuthController();

        $result = $controller->me(new AnonymousUser());

        $this->assertSame(401, $result['statusCode']);
    }

    #[Test]
    public function findUserByNameReturnsUserWhenFound(): void
    {
        $user = new User(['uid' => 3, 'name' => 'bob']);
        $storage = $this->makeStorageThatReturnsIds([(string) $user->id()]);
        $controller = new AuthController();

        $found = $controller->findUserByName($storage, 'bob');

        $this->assertInstanceOf(User::class, $found);
        $this->assertSame(3, $found->id());
    }

    #[Test]
    public function findUserByNameReturnsNullWhenNotFound(): void
    {
        $storage = $this->makeStorageThatReturnsIds([]);
        $controller = new AuthController();

        $found = $controller->findUserByName($storage, 'nobody');

        $this->assertNull($found);
    }

    /**
     * @param array<int|string> $ids
     */
    private function makeStorageThatReturnsIds(array $ids): EntityStorageInterface
    {
        $user = $ids !== [] ? new User(['uid' => (int) reset($ids), 'name' => 'bob']) : null;

        $query = new class($ids) implements EntityQueryInterface {
            public function __construct(private array $ids) {}
            public function condition(string $field, mixed $value, string $operator = '='): static { return $this; }
            public function exists(string $field): static { return $this; }
            public function notExists(string $field): static { return $this; }
            public function sort(string $field, string $direction = 'ASC'): static { return $this; }
            public function range(int $offset, int $limit): static { return $this; }
            public function count(): static { return $this; }
            public function accessCheck(bool $check = true): static { return $this; }
            public function execute(): array { return $this->ids; }
        };

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        if ($user !== null) {
            $storage->method('load')->with((int) reset($ids))->willReturn($user);
        } else {
            $storage->method('load')->willReturn(null);
        }

        return $storage;
    }
}
