<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Services\SettingsUrlSanitizer;
use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A base64 payload that the plain "url" rule accepts. Decodes to
     * <script>alert(1)</script>.
     */
    private const DATA_URL_PAYLOAD = 'data://text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==';

    public function test_settings_page_loads_for_an_authenticated_user(): void
    {
        Setting::create(['website_name' => 'Field Visit', 'website_url' => 'https://example.com']);

        $this->actingAsUser()->get(route('settings'))->assertOk();
    }

    public function test_authenticated_user_can_save_https_urls(): void
    {
        Setting::create(['website_name' => 'Existing']);

        $response = $this->actingAsUser()->post(route('settings.store'), [
            'website_name' => 'Field Visit',
            'website_url' => 'https://example.com',
            'facebook' => 'https://facebook.com/fieldvisit',
            'twitter' => 'https://twitter.com/fieldvisit',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('message', 'Settings updated successfully.');

        $setting = Setting::firstOrFail();

        $this->assertSame('https://example.com', $setting->website_url);
        $this->assertSame('https://facebook.com/fieldvisit', $setting->facebook);
    }

    public function test_authenticated_user_can_create_settings_when_none_exist(): void
    {
        $this->assertDatabaseCount('settings', 0);

        $response = $this->actingAsUser()->post(route('settings.store'), [
            'website_name' => 'Field Visit',
            'website_url' => 'http://example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('message', 'Settings saved successfully.');

        $this->assertDatabaseHas('settings', ['website_url' => 'http://example.com']);
    }

    #[DataProvider('unsafeLinkProvider')]
    public function test_authenticated_user_cannot_save_a_non_http_url_in_a_linked_field(string $payload): void
    {
        Setting::create(['website_name' => 'Field Visit']);

        $response = $this->actingAsUser()->post(route('settings.store'), [
            'website_url' => $payload,
            'facebook' => $payload,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['website_url', 'facebook']);

        $setting = Setting::firstOrFail();

        $this->assertNull($setting->website_url);
        $this->assertNull($setting->facebook);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeLinkProvider(): array
    {
        return [
            'data url' => [self::DATA_URL_PAYLOAD],
            'javascript scheme' => ['javascript:alert(1)'],
            'javascript with comment' => ['javascript://%0aalert(1)'],
            'ftp scheme' => ['ftp://files.example.com'],
            'plain text' => ['not a url'],
        ];
    }

    public function test_authenticated_user_cannot_save_a_non_email_value_in_an_email_field(): void
    {
        $response = $this->actingAsUser()->post(route('settings.store'), [
            'email_1' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email_1');
    }

    public function test_linked_fields_may_be_left_empty(): void
    {
        $response = $this->actingAsUser()->post(route('settings.store'), [
            'website_name' => 'Field Visit',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', ['website_name' => 'Field Visit']);
    }

    public function test_footer_renders_a_stored_payload_as_an_inert_link(): void
    {
        $this->shareSettings(['website_name' => 'Field Visit', 'website_url' => self::DATA_URL_PAYLOAD]);

        $response = $this->actingAsUser()->get(route('settings'));

        $response->assertOk();

        // The link is still rendered for layout stability, but carries no href.
        $this->assertMatchesRegularExpression(
            '/<a(?![^>]*\shref=)[^>]*>\s*<i class="fas fa-link"><\/i>\s*Visit Website/',
            $response->getContent()
        );
    }

    public function test_footer_still_renders_a_safe_stored_url(): void
    {
        $this->shareSettings(['website_name' => 'Field Visit', 'website_url' => 'https://example.com']);

        $this->actingAsUser()
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('href="https://example.com"', false);
    }

    /**
     * @param  array<string, string|null>  $attributes
     */
    private function shareSettings(array $attributes): Setting
    {
        $setting = Setting::create($attributes);

        // AppServiceProvider::boot() shares $appSetting once per application
        // boot, which happens before a test seeds data. Mirror that here so the
        // footer sees the same row a real request would.
        View::share('appSetting', $setting);

        return $setting;
    }

    public function test_safe_url_rejects_non_http_schemes(): void
    {
        $this->assertTrue(SafeUrl::isSafe('https://example.com'));
        $this->assertTrue(SafeUrl::isSafe('http://example.com/a?b=1'));

        $this->assertFalse(SafeUrl::isSafe(self::DATA_URL_PAYLOAD));
        $this->assertFalse(SafeUrl::isSafe('javascript:alert(1)'));
        $this->assertFalse(SafeUrl::isSafe('ftp://files.example.com'));
        $this->assertFalse(SafeUrl::isSafe('//example.com'));
        $this->assertFalse(SafeUrl::isSafe(''));
        $this->assertFalse(SafeUrl::isSafe(null));
    }

    public function test_sanitizer_clears_unsafe_values_and_leaves_safe_ones(): void
    {
        $setting = Setting::create([
            'website_name' => 'Field Visit',
            'website_url' => self::DATA_URL_PAYLOAD,
            'facebook' => 'https://facebook.com/fieldvisit',
            'twitter' => 'javascript:alert(1)',
            'instagram' => null,
            'linkedin' => '',
            'youtube' => 'https://youtube.com/@fieldvisit',
        ]);

        $cleared = app(SettingsUrlSanitizer::class)->neutralize();

        $setting->refresh();

        $this->assertNull($setting->website_url);
        $this->assertNull($setting->twitter);
        $this->assertSame('https://facebook.com/fieldvisit', $setting->facebook);
        $this->assertSame('https://youtube.com/@fieldvisit', $setting->youtube);

        $this->assertCount(2, $cleared);
        $this->assertSame('website_url', $cleared[0]['field']);
        $this->assertSame(self::DATA_URL_PAYLOAD, $cleared[0]['value']);
        $this->assertSame('twitter', $cleared[1]['field']);
    }

    public function test_sanitizer_dry_run_reports_without_writing(): void
    {
        Setting::create(['website_url' => self::DATA_URL_PAYLOAD]);

        $found = app(SettingsUrlSanitizer::class)->inspect();

        $this->assertCount(1, $found);
        $this->assertDatabaseHas('settings', ['website_url' => self::DATA_URL_PAYLOAD]);
    }

    public function test_sanitizer_is_idempotent(): void
    {
        Setting::create(['website_url' => self::DATA_URL_PAYLOAD]);

        $sanitizer = app(SettingsUrlSanitizer::class);

        $this->assertCount(1, $sanitizer->neutralize());
        $this->assertCount(0, $sanitizer->neutralize());
    }

    public function test_sanitizer_command_dry_run_reports_unsafe_values(): void
    {
        Setting::create(['website_url' => self::DATA_URL_PAYLOAD]);

        $this->artisan('settings:neutralize-urls', ['--dry-run' => true])
            ->expectsOutputToContain('1 unsafe linked URL value(s) would be cleared.')
            ->assertSuccessful();

        $this->assertDatabaseHas('settings', ['website_url' => self::DATA_URL_PAYLOAD]);
    }

    public function test_sanitizer_command_clears_unsafe_values(): void
    {
        Setting::create(['website_url' => self::DATA_URL_PAYLOAD]);

        $this->artisan('settings:neutralize-urls')
            ->expectsOutputToContain('1 unsafe linked URL value(s) cleared.')
            ->assertSuccessful();

        $this->assertDatabaseHas('settings', ['website_url' => null]);
    }

    public function test_sanitizer_command_reports_a_clean_table(): void
    {
        Setting::create(['website_url' => 'https://example.com']);

        $this->artisan('settings:neutralize-urls')
            ->expectsOutput('Settings: no unsafe linked URLs found.')
            ->assertSuccessful();
    }
}
