@props([
    'options',
    'namePrefix' => 'tubapay_consents',
])

@if (count($options->consents) > 0)
    <div {{ $attributes->merge(['class' => 'tubapay-consent-checkboxes']) }}>
        @foreach ($options->consents as $consent)
            <label class="tubapay-consent-checkboxes__option" for="{{ $namePrefix }}_{{ $consent->type }}">
                <input
                    type="checkbox"
                    name="{{ $namePrefix }}[]"
                    id="{{ $namePrefix }}_{{ $consent->type }}"
                    value="{{ $consent->type }}"
                    @required($consent->required)
                >
                <span>{{ $consent->label }}</span>
            </label>
        @endforeach
    </div>
@endif
