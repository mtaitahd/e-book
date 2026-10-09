{{--
    Premium CSS 3D book cover box.

    Params:
      $book    - App\Models\Book
      $variant - 'card' | 'detail'

    Used by: partials/book-card.blade.php, books/show.blade.php
--}}
@php
    $variant = $variant ?? 'card';
    $coverUrl = $book->cover_image ? asset('storage/' . $book->cover_image) : null;
@endphp
<div class="bk3d bk3d--{{ $variant }}" @if($coverUrl) style='--bk3d-cover: url("{{ $coverUrl }}")' @endif>
    <span class="bk3d__shadow" aria-hidden="true"></span>
    <div class="bk3d__book">
        <div class="bk3d__face bk3d__back" aria-hidden="true"></div>

        <div class="bk3d__face bk3d__front">
            @if($coverUrl)
                <img src="{{ $coverUrl }}"
                     alt="{{ $book->title }}"
                     @if($variant === 'card') loading="lazy" @endif>
            @else
                <div class="bk3d__ph">
                    <span class="bk3d__ph-title">{{ $book->title }}</span>
                    <span class="bk3d__ph-sub">E-Book</span>
                </div>
            @endif
            <span class="bk3d__gloss" aria-hidden="true"></span>
        </div>

        <div class="bk3d__face bk3d__spine" aria-hidden="true">
            @if($variant === 'detail')
                <span class="bk3d__spine-label">{{ $book->title }}</span>
            @endif
        </div>
        <div class="bk3d__face bk3d__fore" aria-hidden="true"></div>
        <div class="bk3d__face bk3d__top" aria-hidden="true"></div>
    </div>
</div>
