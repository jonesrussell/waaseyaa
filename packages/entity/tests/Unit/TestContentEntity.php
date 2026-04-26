<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Concrete content entity subclass for testing ContentEntityBase.
 */
#[ContentEntityType(id: 'test_content')]
class TestContentEntity extends ContentEntityBase {}
