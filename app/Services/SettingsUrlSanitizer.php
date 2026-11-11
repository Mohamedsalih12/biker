<?php

namespace App\Services;

use App\Support\SafeUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsUrlSanitizer
{
    /**
     * Settings columns that the xpress footer renders inside an href attribute.
     *
     * @var array<int, string>
     */
    public const LINKED_FIELDS = [
        'website_url',
        'facebook',
        'twitter',
        'instagram',
        'linkedin',
        'youtube',
    ];

    /**
     * Clear every linked field that does not hold an http(s) URL.
     *
     * @return array<int, array{id: int, field: string, value: string}> the values that were cleared
     */
    public function neutralize(): array
    {
        return $this->collect(apply: true);
    }

    /**
     * Report unsafe values without writing anything.
     *
     * @return array<int, array{id: int, field: string, value: string}>
     */
    public function inspect(): array
    {
        return $this->collect(apply: false);
    }

    /**
     * @return array<int, array{id: int, field: string, value: string}>
     */
    private function collect(bool $apply): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $unsafe = [];

        foreach (DB::table('settings')->orderBy('id')->get() as $setting) {
            $updates = [];

            foreach (self::LINKED_FIELDS as $field) {
                $value = $setting->{$field} ?? null;

                if ($value === null || $value === '' || SafeUrl::isSafe($value)) {
                    continue;
                }

                $updates[$field] = null;
                $unsafe[] = [
                    'id' => (int) $setting->id,
                    'field' => $field,
                    'value' => (string) $value,
                ];
            }

            if ($apply && $updates !== []) {
                DB::table('settings')->where('id', $setting->id)->update($updates);
            }
        }

        return $unsafe;
    }
}
