@if ($links !== [])
    <ul class="flex min-w-0 flex-wrap gap-x-4 gap-y-2 text-sm" aria-label="{{ $label }}">
        @foreach ($links as $field => $link)
            <li class="min-w-0 break-words">
                <a href="{{ $link['href'] }}" @if ($field === 'website') target="_blank" rel="ugc noopener noreferrer" @endif
                   class="font-semibold text-teal-800 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-teal-300">{{ $link['text'] }}</a>
            </li>
        @endforeach
    </ul>
@endif
