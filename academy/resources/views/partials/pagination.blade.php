@if ($paginator->hasPages())
    <nav aria-label="{{ __('التنقل بين الصفحات') }}">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li class="is-disabled" aria-disabled="true"><span aria-hidden="true"><x-icon name="chevron-start" size="18"/></span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('السابق') }}"><x-icon name="chevron-start" size="18"/></a></li>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="is-disabled"><span>{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="is-current" aria-current="page"><span>{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}" aria-label="{{ __('الصفحة :n', ['n' => $page]) }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('التالي') }}"><x-icon name="chevron-end" size="18"/></a></li>
            @else
                <li class="is-disabled" aria-disabled="true"><span aria-hidden="true"><x-icon name="chevron-end" size="18"/></span></li>
            @endif
        </ul>
    </nav>
@endif
