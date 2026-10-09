<div class="book-card">
    <a class="book-card__cover" href="{{ route('books.show', $book) }}" aria-label="{{ $book->title }}">
        @include('partials.book3d', ['book' => $book, 'variant' => 'card'])
    </a>
    <div class="book-card__body">
        <h3 class="book-card__title">
            <a href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
        </h3>
        @if(isset($book->authors) && $book->authors->isNotEmpty())
            <p class="book-card__authors">by {{ $book->authors->pluck('name')->join(', ') }}</p>
        @endif
        {{-- What the buyer gets: a chapter-based E-Book or a single PDF. --}}
        <p>
            <span class="pill">{{ $book->typeLabel() }}</span>
        </p>
        @if(isset($showCategories) && $showCategories && $book->categories->isNotEmpty())
            <p>
                @foreach($book->categories->take(2) as $category)
                    <a class="pill" href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>
                @endforeach
            </p>
        @endif
        <p class="book-card__price @if($book->isFree()) book-card__price--free @endif">
            @if($book->isFree())
                Free
            @else
                {{ \App\Support\Money::format($book->price) }}
            @endif
        </p>
        <a class="btn btn--secondary btn--block btn--sm book-card__btn" href="{{ route('books.show', $book) }}">View Book</a>
    </div>
</div>