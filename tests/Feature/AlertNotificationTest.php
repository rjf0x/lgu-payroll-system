<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlertNotificationTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function alertStyles(): array
    {
        $cases = [];
        foreach (['success' => 'status', 'destructive' => 'alert', 'warning' => 'alert', 'info' => 'status', 'primary' => 'status', 'secondary' => 'status', 'mono' => 'status'] as $variant => $role) {
            foreach (['solid', 'outline', 'light'] as $appearance) {
                foreach (['sm', 'md', 'lg'] as $size) {
                    $cases["$variant $appearance $size"] = [$variant, $appearance, $size, $role];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('alertStyles')]
    public function test_alert_styles_keep_the_message_icon_and_accessible_role(string $variant, string $appearance, string $size, string $role): void
    {
        $html = Blade::render('<x-ui.alert :variant="$variant" :appearance="$appearance" :size="$size" message="Payroll updated." />', compact('variant', 'appearance', 'size'));

        $document = $this->document($html);
        $alerts = $document->query('//*[@data-alert and @role="'.$role.'" and @aria-atomic="true"]');
        $this->assertSame(1, $alerts->length);
        $classes = $alerts->item(0)->getAttribute('class');
        $this->assertStringContainsString('ui-alert-'.$variant, $classes);
        $this->assertStringContainsString('ui-alert-'.$appearance, $classes);
        $this->assertStringContainsString('ui-alert-'.$size, $classes);
        $this->assertSame(1, $document->query('//*[@data-alert]//p[text()="Payroll updated."]')->length);
        $this->assertSame(1, $document->query('//*[@data-alert]/svg[@aria-hidden="true" and @focusable="false"]')->length);
        $this->assertSame(1, $document->query('//button[@type="button" and @aria-label="Dismiss notification" and @data-alert-dismiss]')->length);
    }

    public function test_alerts_escape_titles_and_messages_and_allow_explicit_rich_slots(): void
    {
        $unsafe = '<script>alert("payroll")</script>';

        $html = Blade::render('<x-ui.alert :title="$unsafe" :message="$unsafe" /><x-ui.alert title="Review"><a href="/payroll-runs">{{ $unsafe }}</a></x-ui.alert>', compact('unsafe'));

        $this->assertStringNotContainsString($unsafe, $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertSame(1, $this->document($html)->query('//a[@href="/payroll-runs"]')->length);
    }

    public function test_persistent_notices_can_disable_close_controls_and_icons_and_use_the_legacy_error_alias(): void
    {
        $html = Blade::render('<x-ui.alert variant="error" :dismissible="false" :auto-dismiss="false" :icon="false" title="" message="Review payroll inputs." />');

        $document = $this->document($html);
        $this->assertSame(1, $document->query('//*[@data-alert-variant="destructive" and @role="alert"]')->length);
        $this->assertSame(0, $document->query('//button | //svg')->length);
        $this->assertStringContainsString('\\u0022autoDismiss\\u0022:false', $html);
    }

    public function test_flash_container_handles_all_keys_without_duplicating_success_aliases_or_multiple_containers(): void
    {
        session()->put([
            'success' => 'Saved payroll.',
            'status' => 'Saved payroll.',
            'error' => 'Unable to finalize.',
            'warning' => 'Review approved payable days.',
            'info' => 'Processing payroll.',
            'primary' => 'General notification.',
            'secondary' => 'Neutral notification.',
            'mono' => 'Monochrome notification.',
        ]);

        $html = Blade::render('<x-ui.alert-container /><x-toast-notifications />');

        $document = $this->document($html);
        $this->assertSame(1, $document->query('//section[@aria-label="Notifications"]')->length);
        $this->assertSame(7, $document->query('//*[@data-alert]')->length);
        $this->assertSame(1, $document->query('//p[text()="Saved payroll."]')->length);
        $this->assertSame(1, $document->query('//*[@data-alert-variant="destructive"]//p[text()="Unable to finalize."]')->length);
    }

    public function test_inline_container_ignores_empty_and_non_text_flash_data(): void
    {
        session()->put(['status' => '', 'success' => ['internal' => 'private data'], 'info' => 'Processing payroll.']);

        $html = Blade::render('<x-ui.alert-container presentation="inline" />');

        $document = $this->document($html);
        $section = $document->query('//section')->item(0);
        $this->assertStringNotContainsString('fixed', $section->getAttribute('class'));
        $this->assertSame(1, $document->query('//*[@data-alert]')->length);
        $this->assertStringNotContainsString('private data', $html);
        $this->assertStringContainsString('\\u0022toast\\u0022:false', $html);
    }

    public function test_variant_timer_defaults_and_explicit_overrides_are_passed_to_alpine(): void
    {
        $html = Blade::render('<x-ui.alert variant="success" message="Saved." /><x-ui.alert variant="info" message="Queued." /><x-ui.alert variant="warning" :auto-dismiss="true" :duration="9000" message="Review." />');

        $document = $this->document($html);
        foreach (['success' => '\\u0022duration\\u0022:5000', 'info' => '\\u0022duration\\u0022:6000', 'warning' => '\\u0022duration\\u0022:9000'] as $variant => $expected) {
            $configuration = $document->query('//*[@data-alert-variant="'.$variant.'"]')->item(0)->getAttribute('x-data');
            $this->assertStringContainsString($expected, $configuration);
            $this->assertStringContainsString('\\u0022autoDismiss\\u0022:true', $configuration);
        }
    }

    private function document(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
