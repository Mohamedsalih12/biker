<?php

namespace App\Console\Commands;

use App\Services\SettingsUrlSanitizer;
use Illuminate\Console\Command;

class NeutralizeSettingUrlsCommand extends Command
{
    protected $signature = 'settings:neutralize-urls
                            {--dry-run : Report unsafe values without changing the database}';

    protected $description = 'Clear settings values that render as links but are not http(s) URLs';

    public function handle(SettingsUrlSanitizer $sanitizer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $unsafe = $dryRun ? $sanitizer->inspect() : $sanitizer->neutralize();

        if ($unsafe === []) {
            $this->info('Settings: no unsafe linked URLs found.');

            return self::SUCCESS;
        }

        $this->warn(sprintf(
            'Settings: %d unsafe linked URL value(s) %s.',
            count($unsafe),
            $dryRun ? 'would be cleared' : 'cleared'
        ));

        foreach ($unsafe as $entry) {
            $this->line(sprintf('  - settings.id=%d  %s  %s', $entry['id'], $entry['field'], $entry['value']));
        }

        $this->info('Re-enter those values as http(s) URLs in Admin > Settings.');

        return self::SUCCESS;
    }
}
