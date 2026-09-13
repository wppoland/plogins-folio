<?php

declare(strict_types=1);

namespace Folio\Service;

defined('ABSPATH') || exit;

/**
 * Resolves plugin settings: stored options merged over packaged defaults, with
 * the customer-facing text keys filled in from Texts.
 */
final class Settings
{
    public const OPTION = 'folio_settings';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require FOLIO_DIR . 'config/defaults.php';
        $stored   = get_option(self::OPTION, []);
        $stored   = is_array($stored) ? $stored : [];

        $resolved = array_merge($defaults, $stored);

        // Empty text keys become the translated default here, once, so nothing
        // downstream has to remember to do it.
        $resolved['texts'] = Texts::apply((array) ($resolved['texts'] ?? []));

        return $this->cache = $resolved;
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->all()[$key] ?? $fallback;
    }

    public function bool(string $key, bool $fallback = false): bool
    {
        return (bool) ($this->all()[$key] ?? $fallback);
    }

    public function int(string $key, int $fallback = 0): int
    {
        return (int) ($this->all()[$key] ?? $fallback);
    }

    public function text(string $key): string
    {
        return (string) ($this->all()['texts'][$key] ?? '');
    }

    /**
     * The enabled field keys for one layout.
     *
     * @return list<string>
     */
    public function fields(string $layout): array
    {
        // Each key is read by name rather than through a variable built from
        // $layout. Indirect reads are invisible both to the estate's
        // settings-readers gate and to anyone grepping for where a setting is
        // used, and a setting nothing appears to read is indistinguishable
        // from one nothing actually reads.
        return match ($layout) {
            'catalog'   => $this->stringList($this->all()['catalog_fields'] ?? []),
            'pricelist' => $this->stringList($this->all()['pricelist_fields'] ?? []),
            default     => $this->stringList($this->all()['sheet_fields'] ?? []),
        };
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function stringList($value): array
    {
        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    public function flush(): void
    {
        $this->cache = null;
    }
}
