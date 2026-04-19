@props([
    'options',
    'name' => 'tubapay_installments',
])

<div {{ $attributes->merge(['class' => 'tubapay-installment-selector']) }}>
    <p>{{ $options->installmentTitle() }}</p>

    @foreach ($options->installments as $option)
        <label class="tubapay-installment-selector__option" for="{{ $name }}_{{ $option->installments }}">
            <input
                type="radio"
                name="{{ $name }}"
                id="{{ $name }}_{{ $option->installments }}"
                value="{{ $option->installments }}"
                @checked($option->selected)
            >
            <span>{{ $option->label }}</span>
        </label>
    @endforeach
</div>
