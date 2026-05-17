<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Vector;

/**
 * Supported distance/similarity metrics for vector comparison.
 * @api
 */
enum DistanceMetric: string
{
    case COSINE = 'cosine';
    case EUCLIDEAN = 'euclidean';
    case DOT_PRODUCT = 'dot_product';
}
