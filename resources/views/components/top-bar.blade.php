@props([
    'content',
    'sticky' => config('tubapay.ui.top_bar.sticky', true),
    'fontSize' => config('tubapay.ui.top_bar.font_size', 16),
    'fontColor' => config('tubapay.ui.top_bar.font_color', '#ffffff'),
    'backgroundColor' => config('tubapay.ui.top_bar.background_color', '#111827'),
])

<div
    {{ $attributes->merge(['class' => 'tubapay-top-bar']) }}
    style="
        {{ $sticky ? 'position: sticky; top: 0; z-index: 40;' : '' }}
        font-size: {{ (int) $fontSize }}px;
        color: {{ $fontColor }};
        background-color: {{ $backgroundColor }};
        padding: 10px 16px;
        text-align: center;
    "
>
    <span>{{ $content->mainText }}</span>

    @if ($content->buttonText !== '')
        <button type="button" class="tubapay-top-bar__button">
            {{ $content->buttonText }}
        </button>
    @endif
</div>
