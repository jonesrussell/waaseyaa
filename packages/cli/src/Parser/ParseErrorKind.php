<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Parser;

enum ParseErrorKind
{
    case UnknownCommand;
    case UnknownOption;
    case MissingRequiredArgument;
    case MissingRequiredOptionValue;
    case TypeCoercion;
    case TooManyArguments;
}
