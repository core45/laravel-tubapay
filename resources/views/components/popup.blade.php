@props([
    'content',
    'id' => 'tubapay-popup',
])

<section {{ $attributes->merge(['id' => $id, 'class' => 'tubapay-popup']) }} aria-label="TubaPay">
    @if (count($content->topList) > 0)
        <ol class="tubapay-popup__steps">
            @foreach ($content->topList as $step)
                <li>
                    <strong>{{ $step->title }}</strong>
                    <span>{{ $step->description }}</span>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($content->mainText !== '')
        <p class="tubapay-popup__main-text">{{ $content->mainText }}</p>
    @endif
</section>
