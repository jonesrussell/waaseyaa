<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

/**
 * Sort direction enum used by {@see SortDefinition}.
 *
 * @api
 */
enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';
}
