<?php

declare(strict_types=1);

namespace Folio\Document;

defined('ABSPATH') || exit;

/**
 * One piece of a document.
 *
 * Deliberately one class with a string type rather than a class per kind. A
 * renderer is a `match ($block->type)`, and there are two renderers: the print
 * HTML in this plugin and, in the paid edition, a PDF one. Two implementations
 * earn the seam; a hierarchy of eight block classes would not, and the point of
 * this object is that both renderers read the same list.
 *
 * A renderer that meets a type it does not know skips it. That is how the paid
 * edition can add a block the print path has no way to draw without either side
 * knowing about the other.
 */
final class Block
{
    public const PAGE_BREAK = 'page-break';
    public const HEADER     = 'header';
    public const FOOTER     = 'footer';
    public const HEADING    = 'heading';
    public const IMAGE      = 'image';
    public const TEXT       = 'text';
    public const FIELDS     = 'fields';
    public const ROW        = 'row';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function of(string $type, array $data = []): self
    {
        return new self($type, $data);
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->data[$key] ?? $fallback;
    }

    public function string(string $key, string $fallback = ''): string
    {
        $value = $this->data[$key] ?? $fallback;

        return is_scalar($value) ? (string) $value : $fallback;
    }
}
