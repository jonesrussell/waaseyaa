<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Event;

enum EntityEvents: string
{
    case PRE_SAVE = 'waaseyaa.entity.pre_save';
    case POST_SAVE = 'waaseyaa.entity.post_save';
    case PRE_DELETE = 'waaseyaa.entity.pre_delete';
    case POST_DELETE = 'waaseyaa.entity.post_delete';
    case POST_LOAD = 'waaseyaa.entity.post_load';
    case PRE_CREATE = 'waaseyaa.entity.pre_create';
    case REVISION_CREATED = 'waaseyaa.entity.revision_created';
    case REVISION_REVERTED = 'waaseyaa.entity.revision_reverted';

    // Translation-scoped lifecycle (M-006). Dispatched alongside
    // {@see EntityEvents::PRE_SAVE} / {@see EntityEvents::POST_SAVE} for each
    // translation that is being inserted, updated or deleted within a single
    // entity save. Payload is a {@see TranslationEvent} carrying the langcode.
    case PRE_TRANSLATION_INSERT = 'waaseyaa.entity.pre_translation_insert';
    case POST_TRANSLATION_INSERT = 'waaseyaa.entity.post_translation_insert';
    case PRE_TRANSLATION_UPDATE = 'waaseyaa.entity.pre_translation_update';
    case POST_TRANSLATION_UPDATE = 'waaseyaa.entity.post_translation_update';
    case PRE_TRANSLATION_DELETE = 'waaseyaa.entity.pre_translation_delete';
    case POST_TRANSLATION_DELETE = 'waaseyaa.entity.post_translation_delete';
}
