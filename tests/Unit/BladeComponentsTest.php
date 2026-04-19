<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\LaravelTubaPay\ViewModels\CheckoutOptions;
use Core45\LaravelTubaPay\ViewModels\ConsentOption;
use Core45\LaravelTubaPay\ViewModels\InstallmentOption;
use Core45\TubaPay\DTO\Content\PopupContent;
use Core45\TubaPay\DTO\Content\PopupStep;
use Core45\TubaPay\DTO\Content\TopBarContent;
use Core45\TubaPay\DTO\UiTexts;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;

final class BladeComponentsTest extends TestCase
{
    #[Test]
    public function installment_selector_renders_input_names_and_values(): void
    {
        $html = Blade::render(
            '<x-tubapay::installment-selector :options="$options" name="installments" />',
            ['options' => $this->checkoutOptions()],
        );

        $this->assertStringContainsString('name="installments"', $html);
        $this->assertStringContainsString('value="12"', $html);
    }

    #[Test]
    public function consent_checkboxes_render_required_attribute(): void
    {
        $html = Blade::render(
            '<x-tubapay::consent-checkboxes :options="$options" name-prefix="consents" />',
            ['options' => $this->checkoutOptions()],
        );

        $this->assertStringContainsString('name="consents[]"', $html);
        $this->assertStringContainsString('value="RODO_BP"', $html);
        $this->assertStringContainsString('required', $html);
    }

    #[Test]
    public function top_bar_component_renders_content(): void
    {
        $html = Blade::render(
            '<x-tubapay::top-bar :content="$content" />',
            ['content' => new TopBarContent('Main text', 'Button text', 'Mobile text')],
        );

        $this->assertStringContainsString('Main text', $html);
        $this->assertStringContainsString('Button text', $html);
    }

    #[Test]
    public function popup_component_renders_steps(): void
    {
        $html = Blade::render(
            '<x-tubapay::popup :content="$content" />',
            ['content' => new PopupContent([new PopupStep('Step 1', 'Description')], 'Main text')],
        );

        $this->assertStringContainsString('Step 1', $html);
        $this->assertStringContainsString('Description', $html);
        $this->assertStringContainsString('Main text', $html);
    }

    private function checkoutOptions(): CheckoutOptions
    {
        return new CheckoutOptions(
            amount: 1200.0,
            available: true,
            installments: [
                new InstallmentOption(installments: 12, monthlyAmount: 100.0, label: '12 months', selected: true),
            ],
            consents: [
                new ConsentOption(type: 'RODO_BP', label: 'Consent text', required: true),
            ],
            uiTexts: new UiTexts([]),
            recommendedInstallments: 12,
        );
    }
}
