<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy;

/**
 * Display names used by local demo seed flows so SSR landing pages can link
 * to the same fixture without duplicating string literals across apps.
 */
final class GenealogyLocalDemoMarkers
{
    public const string CHILD_PERSON_DISPLAY = '[Genealogy demo] Child';

    public const string PARENT_PERSON_DISPLAY = '[Genealogy demo] Parent';

    public const string FAMILY_DISPLAY = '[Genealogy demo] Family';

    public const string TREE_DISPLAY = '[Genealogy demo] Tree';
}
