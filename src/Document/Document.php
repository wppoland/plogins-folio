<?php

declare(strict_types=1);

namespace Folio\Document;

defined('ABSPATH') || exit;

/**
 * A built document, ready for a renderer.
 *
 * This is the whole contract between building and drawing. Everything that
 * decides what appears and in what order happens before this object exists;
 * everything after it is medium-specific. That split is the reason a field
 * added to the sheet shows up in the paid edition's PDF with no change on the
 * paid side.
 */
final class Document
{
    /**
     * @param list<Block>          $blocks
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $title,
        public readonly string $layout,
        public readonly array $blocks,
        public readonly array $meta = [],
    ) {
    }

    /**
     * @param list<Block> $blocks
     */
    public function withBlocks(array $blocks): self
    {
        return new self($this->title, $this->layout, $blocks, $this->meta);
    }

    public function metaValue(string $key, mixed $fallback = null): mixed
    {
        return $this->meta[$key] ?? $fallback;
    }
}
