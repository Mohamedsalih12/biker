<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Runtime guard for values that end up inside an href attribute.
 *
 * Blade's {{ }} escapes < > & " ' but leaves a bare URI scheme untouched, so a
 * stored value such as "data://text/html;base64,..." survives into the markup as
 * a live link. This mirrors the "url:http,https" validation rule, which
 * delegates to the very same Str::isUrl() call, so validation and rendering
 * cannot drift apart in intent.
 */
final class SafeUrl
{
    public static function isSafe(mixed $value): bool
    {
        return is_string($value) && $value !== '' && Str::isUrl($value, ['http', 'https']);
    }
}
