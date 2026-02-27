<?php

declare(strict_types=1);

namespace Aurora\Routing\Tests\Unit;

use Aurora\Entity\EntityInterface;
use Aurora\Entity\EntityTypeManagerInterface;
use Aurora\Entity\Storage\EntityStorageInterface;
use Aurora\Routing\ParamConverter\EntityParamConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

#[CoversClass(EntityParamConverter::class)]
final class EntityParamConverterTest extends TestCase
{
    #[Test]
    public function convertLoadsEntityAndReplacesParameter(): void
    {
        $entity = $this->createMock(EntityInterface::class);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->expects($this->once())
            ->method('load')
            ->with('42')
            ->willReturn($entity);

        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $entityTypeManager->expects($this->once())
            ->method('getStorage')
            ->with('node')
            ->willReturn($storage);

        $converter = new EntityParamConverter($entityTypeManager);

        $route = new Route('/node/{node}');
        $route->setOption('parameters', [
            'node' => ['type' => 'entity:node'],
        ]);

        $parameters = ['node' => '42', '_route' => 'node.view'];
        $result = $converter->convert($parameters, $route);

        $this->assertSame($entity, $result['node']);
        $this->assertSame('node.view', $result['_route']);
    }

    #[Test]
    public function convertKeepsOriginalValueWhenEntityNotFound(): void
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->expects($this->once())
            ->method('load')
            ->with('999')
            ->willReturn(null);

        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $entityTypeManager->expects($this->once())
            ->method('getStorage')
            ->with('node')
            ->willReturn($storage);

        $converter = new EntityParamConverter($entityTypeManager);

        $route = new Route('/node/{node}');
        $route->setOption('parameters', [
            'node' => ['type' => 'entity:node'],
        ]);

        $parameters = ['node' => '999'];
        $result = $converter->convert($parameters, $route);

        // Original value kept since entity was not found.
        $this->assertSame('999', $result['node']);
    }

    #[Test]
    public function convertIgnoresNonEntityParameters(): void
    {
        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $entityTypeManager->expects($this->never())->method('getStorage');

        $converter = new EntityParamConverter($entityTypeManager);

        $route = new Route('/search/{query}');
        $route->setOption('parameters', [
            'query' => ['type' => 'string'],
        ]);

        $parameters = ['query' => 'test'];
        $result = $converter->convert($parameters, $route);

        $this->assertSame('test', $result['query']);
    }

    #[Test]
    public function convertIgnoresParametersWithoutType(): void
    {
        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $entityTypeManager->expects($this->never())->method('getStorage');

        $converter = new EntityParamConverter($entityTypeManager);

        $route = new Route('/node/{node}');
        $route->setOption('parameters', [
            'node' => ['converter' => 'some_converter'],
        ]);

        $parameters = ['node' => '42'];
        $result = $converter->convert($parameters, $route);

        $this->assertSame('42', $result['node']);
    }

    #[Test]
    public function convertSkipsMissingParameterValues(): void
    {
        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $entityTypeManager->expects($this->never())->method('getStorage');

        $converter = new EntityParamConverter($entityTypeManager);

        $route = new Route('/node/{node}');
        $route->setOption('parameters', [
            'node' => ['type' => 'entity:node'],
        ]);

        // Parameter 'node' is declared but not in the matched parameters.
        $parameters = ['_route' => 'node.view'];
        $result = $converter->convert($parameters, $route);

        $this->assertSame(['_route' => 'node.view'], $result);
    }

    #[Test]
    public function convertHandlesRouteWithNoParameterOption(): void
    {
        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $entityTypeManager->expects($this->never())->method('getStorage');

        $converter = new EntityParamConverter($entityTypeManager);

        $route = new Route('/about');
        // No 'parameters' option set at all.

        $parameters = ['_route' => 'about'];
        $result = $converter->convert($parameters, $route);

        $this->assertSame(['_route' => 'about'], $result);
    }

    #[Test]
    public function convertHandlesMultipleEntityParameters(): void
    {
        $nodeEntity = $this->createMock(EntityInterface::class);
        $userEntity = $this->createMock(EntityInterface::class);

        $nodeStorage = $this->createMock(EntityStorageInterface::class);
        $nodeStorage->expects($this->once())
            ->method('load')
            ->with('5')
            ->willReturn($nodeEntity);

        $userStorage = $this->createMock(EntityStorageInterface::class);
        $userStorage->expects($this->once())
            ->method('load')
            ->with('10')
            ->willReturn($userEntity);

        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $entityTypeManager->expects($this->exactly(2))
            ->method('getStorage')
            ->willReturnCallback(function (string $entityTypeId) use ($nodeStorage, $userStorage) {
                return match ($entityTypeId) {
                    'node' => $nodeStorage,
                    'user' => $userStorage,
                };
            });

        $converter = new EntityParamConverter($entityTypeManager);

        $route = new Route('/node/{node}/author/{user}');
        $route->setOption('parameters', [
            'node' => ['type' => 'entity:node'],
            'user' => ['type' => 'entity:user'],
        ]);

        $parameters = ['node' => '5', 'user' => '10'];
        $result = $converter->convert($parameters, $route);

        $this->assertSame($nodeEntity, $result['node']);
        $this->assertSame($userEntity, $result['user']);
    }

    #[Test]
    public function convertHandlesMixedEntityAndNonEntityParameters(): void
    {
        $entity = $this->createMock(EntityInterface::class);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->expects($this->once())
            ->method('load')
            ->with('42')
            ->willReturn($entity);

        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $entityTypeManager->expects($this->once())
            ->method('getStorage')
            ->with('node')
            ->willReturn($storage);

        $converter = new EntityParamConverter($entityTypeManager);

        $route = new Route('/node/{node}/tab/{tab}');
        $route->setOption('parameters', [
            'node' => ['type' => 'entity:node'],
            'tab' => ['type' => 'string'],
        ]);

        $parameters = ['node' => '42', 'tab' => 'edit'];
        $result = $converter->convert($parameters, $route);

        $this->assertSame($entity, $result['node']);
        $this->assertSame('edit', $result['tab']);
    }
}
