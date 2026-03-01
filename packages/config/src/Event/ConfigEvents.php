<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Event;

enum ConfigEvents: string
{
    case PRE_SAVE = 'waaseyaa.config.pre_save';
    case POST_SAVE = 'waaseyaa.config.post_save';
    case PRE_DELETE = 'waaseyaa.config.pre_delete';
    case POST_DELETE = 'waaseyaa.config.post_delete';
    case IMPORT = 'waaseyaa.config.import';
}
