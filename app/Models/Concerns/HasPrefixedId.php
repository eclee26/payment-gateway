<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;

/**
 * Prefixed ULID primary keys (e.g. "pay_01j…") so public IDs are
 * non-sequential and self-describing.
 */
trait HasPrefixedId
{
    use HasUniqueStringIds;

    /**
     * The prefix prepended to generated IDs, including the trailing underscore.
     */
    abstract public static function idPrefix(): string;

    /**
     * Generate a new unique key for the model.
     */
    public function newUniqueId(): string
    {
        return static::idPrefix().strtolower((string) Str::ulid());
    }

    /**
     * Determine if given key is valid.
     *
     * @param  mixed  $value
     */
    protected function isValidUniqueId($value): bool
    {
        $prefix = static::idPrefix();

        return is_string($value)
            && str_starts_with($value, $prefix)
            && Str::isUlid(substr($value, strlen($prefix)));
    }
}
