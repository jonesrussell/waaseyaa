<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Http\AppController;

enum AppParameterKind: string
{
    case FrameworkService = 'service';
    case RouteEntity = 'entity';
    case RouteScalar = 'scalar';
    case RouteEnum = 'enum';
    case MapRoute = 'map_route';
    case MapQuery = 'map_query';
    case Custom = 'custom';
}
