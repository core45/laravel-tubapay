<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

final class UiRoutesDisabledTest extends TestCase
{
    #[Test]
    public function ui_routes_are_disabled_by_default(): void
    {
        $this->assertFalse(Route::has('tubapay.ui.installments'));
        $this->assertFalse(Route::has('tubapay.ui.content.top-bar'));
        $this->assertFalse(Route::has('tubapay.ui.content.popup'));
        $this->assertFalse(Route::has('tubapay.ui.texts'));
    }
}
