<?php

namespace App\Data;

/** A baseline item (scope or timeline) owned by E-3. E-5 never stores it; it only reads it. */
final class BaselineItem
{
    /** @param 'SCOPE'|'TIMELINE' $type */
    public function __construct(
        public readonly string $baselineItemId,
        public readonly string $projectId,
        public readonly string $name,
        public readonly string $type,
    ) {}
}
