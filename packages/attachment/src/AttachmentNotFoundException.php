<?php

declare(strict_types=1);

namespace Waaseyaa\Attachment;

final class AttachmentNotFoundException extends \DomainException
{
    public function __construct(string $attachmentId)
    {
        parent::__construct("Attachment '{$attachmentId}' not found.");
    }
}
