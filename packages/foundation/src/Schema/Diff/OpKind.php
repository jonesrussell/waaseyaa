<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Discriminator for {@see SchemaDiffOp} implementations.
 *
 * String-backed so canonical JSON serializes the kind as a stable
 * snake_case token across PHP versions and platforms.
 */
enum OpKind: string
{
    case AddColumn = 'add_column';
    case AlterColumn = 'alter_column';
    case DropColumn = 'drop_column';
    case AddIndex = 'add_index';
    case DropIndex = 'drop_index';
    case AddForeignKey = 'add_foreign_key';
    case DropForeignKey = 'drop_foreign_key';
    case RenameColumn = 'rename_column';
    case RenameTable = 'rename_table';
}
