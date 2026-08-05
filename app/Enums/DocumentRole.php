<?php

namespace App\Enums;

enum DocumentRole: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';

    /**
     * Determine whether this role may change the document's content.
     */
    public function canEdit(): bool
    {
        return $this === self::Editor;
    }
}
