<?php

declare(strict_types=1);

namespace Waaseyaa\Cli;

enum OptionMode
{
    case None;        // boolean flag, no value
    case Required;    // value mandatory if option present
    case Optional;    // value optional; bare presence yields null
    case Array_;      // accumulates list, repeatable
    case Negatable;   // boolean toggleable via --no-foo
}
