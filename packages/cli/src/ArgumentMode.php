<?php

declare(strict_types=1);

namespace Waaseyaa\CLI;

enum ArgumentMode
{
    case Required;
    case Optional;
    case Array_;   // repeatable, accumulates list (maps Symfony InputArgument::IS_ARRAY)
}
