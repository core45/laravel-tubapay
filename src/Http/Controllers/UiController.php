<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Http\Controllers;

use Core45\LaravelTubaPay\Services\TubaPayCheckoutOptions;
use Core45\LaravelTubaPay\ViewModels\CheckoutOptions;
use Core45\TubaPay\TubaPay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class UiController extends Controller
{
    public function __construct(
        private readonly TubaPay $tubaPay,
        private readonly TubaPayCheckoutOptions $checkoutOptions,
    ) {}

    public function installments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $options = $this->checkoutOptions->forAmount((float) $validated['amount']);

        return response()->json($this->checkoutOptionsToArray($options));
    }

    public function topBar(): JsonResponse
    {
        try {
            return response()->json(Cache::remember(
                'tubapay:content:top-bar',
                $this->cacheTtl(),
                fn () => $this->tubaPay->content()->topBar(),
            ));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function popup(): JsonResponse
    {
        try {
            return response()->json(Cache::remember(
                'tubapay:content:popup',
                $this->cacheTtl(),
                fn () => $this->tubaPay->content()->popup(),
            ));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function texts(): JsonResponse
    {
        try {
            return response()->json(Cache::remember(
                'tubapay:ui:texts',
                $this->cacheTtl(),
                fn () => $this->tubaPay->uiTexts()->getTexts()->toArray(),
            ));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutOptionsToArray(CheckoutOptions $options): array
    {
        return [
            'amount' => $options->amount,
            'available' => $options->available,
            'recommendedInstallments' => $options->recommendedInstallments,
            'installments' => array_map(static fn ($option): array => [
                'installments' => $option->installments,
                'monthlyAmount' => $option->monthlyAmount,
                'label' => $option->label,
                'selected' => $option->selected,
            ], $options->installments),
            'consents' => array_map(static fn ($consent): array => [
                'type' => $consent->type,
                'label' => $consent->label,
                'required' => $consent->required,
            ], $options->consents),
            'texts' => $options->uiTexts->toArray(),
        ];
    }

    private function cacheTtl(): int
    {
        return max(0, (int) config('tubapay.ui.cache_ttl', 3600));
    }
}
