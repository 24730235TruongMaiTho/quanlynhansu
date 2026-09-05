@props([
    'title',
    'titleId' => 'page-title',
    'icon' => null,
    'description' => null,
    'descriptionId' => null,
    'breadcrumbs' => [],
])

<header {{ $attributes->class(['page-header']) }}>
    <div class="left">
        <div class="page-header__content">
            @if ($breadcrumbs !== [])
                <nav aria-label="Đường dẫn trang">
                    <ol class="breadcrumb mb-1">
                        @foreach ($breadcrumbs as $breadcrumb)
                            <li class="breadcrumb-item {{ empty($breadcrumb['url']) ? 'active' : '' }}"
                                @if (empty($breadcrumb['url'])) aria-current="page" @endif>
                                @if (! empty($breadcrumb['url']))
                                    <a class="breadcrumb-item__link" href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                                @else
                                    {{ $breadcrumb['label'] }}
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            <div class="page-header__title-row d-flex align-items-center flex-wrap gap-2">
                @isset($titlePrefix)
                    {{ $titlePrefix }}
                @endisset
                @if (filled($icon))
                    <i class="bi {{ $icon }} page-header__icon" aria-hidden="true"></i>
                @endif
                <h1 class="h3 fw-semibold mb-1" id="{{ $titleId }}">{{ $title }}</h1>
                @isset($titleSuffix)
                    {{ $titleSuffix }}
                @endisset
            </div>
            @if ($description)
                <p class="text-secondary mb-0" @if ($descriptionId) id="{{ $descriptionId }}" @endif>{{ $description }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="page-header__actions">{{ $actions }}</div>
    @endisset
</header>
