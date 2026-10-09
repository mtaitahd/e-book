{{--
    Premium storefront footer.

    Presentational only. This partial deliberately contains no backend calls,
    no newsletter submission and no data access of its own beyond the
    `$navCategories` list the storefront layout already shares for the header.

    Two rules govern the links below:
      1. Only routes that actually exist in routes/web.php are rendered. There is
         no public Authors index, no New Releases page, no Best Sellers page and
         no Categories index, so none of those are linked. `books.trending` is a
         JSON endpoint, not a page, so it is not a valid navigation target
         either. Anything without a real page is omitted rather than faked.
      2. Contact details are not configured anywhere in this application (there
         is no settings table, config key or seed for them), so the values below
         are neutral, obviously-fake placeholders. Replace them when real
         contact information is available.
--}}

@php
    // ---------------------------------------------------------------------------
    // PLACEHOLDER CONTACT INFORMATION -- replace with real, verified details.
    // `.example` is an IANA-reserved TLD and `000 000 000` is not an assignable
    // number, so neither can be mistaken for a live contact by accident.
    // ---------------------------------------------------------------------------
    $footerContact = [
        'location' => 'Tanzania',
        'email'    => 'support@ebook.example',
        'phone'    => '+255 000 000 000',
        'hours'    => 'Mon – Fri: 8:00 AM – 6:00 PM',
    ];

    $footerCategories = collect($navCategories ?? [])->take(6);
@endphp

<footer
    class="ebs-footer"
    style="--ebs-footer-bg-image: url('{{ asset('assets/footer.png') }}');"
>
    <div class="container ebs-footer__inner">

        {{-- ============================ SECTION A: columns ============================ --}}
        <div class="ebs-footer-cols">

            {{-- Column 1: brand --}}
            <div class="ebs-footer-col ebs-footer-col--brand">
                <a class="ebs-footer__logo" href="{{ route('home') }}">
                    <img
                        src="{{ asset('assets/e_book-removebg-preview.png') }}"
                        alt=""
                        width="34"
                        height="34"
                        loading="lazy"
                    >
                    <span>E-Book</span>
                </a>

                <p class="ebs-footer__tagline">Read &bull; Learn &bull; Grow</p>

                <p class="ebs-footer__about">Your trusted digital library. Buy and read e-books online with secure payments, instant access, and a seamless reading experience.</p>

            </div>

            {{-- Column 2: explore. Real routes only. --}}
            <div class="ebs-footer-col ebs-footer-col--links">
            <nav aria-labelledby="ebsFooterExploreTitle">
                <h2 class="ebs-footer-col__title" id="ebsFooterExploreTitle">Explore</h2>
                <ul class="ebs-footer-links">
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('books.index') }}">All Books</a></li>
                </ul>

                @if($footerCategories->isNotEmpty())
                    <p class="ebs-footer-col__label" id="ebsFooterCatsLabel">Categories</p>
                    <ul class="ebs-footer-links" aria-labelledby="ebsFooterCatsLabel">
                        @foreach($footerCategories as $footerCategory)
                            <li><a href="{{ route('categories.show', $footerCategory) }}">{{ $footerCategory->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </nav>

            {{--
                Column 3: help & support. Only routes that exist are rendered.
                Help Centre, How It Works, Payment Methods, Terms of Service and
                Privacy Policy have no pages in this application and are
                therefore omitted rather than linked to a dead URL.
            --}}
            <nav class="ebs-footer-nav" aria-labelledby="ebsFooterHelpTitle">
                <h2 class="ebs-footer-col__title" id="ebsFooterHelpTitle">Help &amp; Support</h2>
                <ul class="ebs-footer-links">
                    @auth
                        <li><a href="{{ route('account.show') }}">My Account</a></li>
                        <li><a href="{{ route('account.orders.index') }}">Order History</a></li>
                        <li><a href="{{ route('account.purchases.index') }}">Your Books</a></li>
                    @else
                        {{-- A guest has no account page, so "My Account" opens the sign-in modal, exactly as it does in the header. --}}
                        <li><a href="{{ route('login') }}" data-ebs-auth-open="login">My Account</a></li>
                    @endauth
                    <li><a href="{{ route('cart.show') }}">Your Cart</a></li>
                </ul>
            </nav>
            </div>

            {{-- Column 4: contact. Placeholder values, see the note at the top. --}}
            <div class="ebs-footer-col" aria-labelledby="ebsFooterContactTitle">
                <h2 class="ebs-footer-col__title" id="ebsFooterContactTitle">Contact Us</h2>

                <ul class="ebs-footer-contact">
                    <li class="ebs-footer-contact__row">
                        <span class="ebs-footer-contact__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="16" height="16" focusable="false">
                                <path d="M12 21.2s7.2-5.7 7.2-11.2a7.2 7.2 0 1 0-14.4 0c0 5.5 7.2 11.2 7.2 11.2z" fill="currentColor"/>
                                <circle cx="12" cy="9.8" r="2.7" fill="#162437"/>
                            </svg>
                        </span>
                        <span class="ebs-footer-contact__body">
                            <span class="ebs-footer-contact__label">Location</span>
                            <span class="ebs-footer-contact__value">{{ $footerContact['location'] }}</span>
                        </span>
                    </li>

                    <li class="ebs-footer-contact__row">
                        <span class="ebs-footer-contact__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="16" height="16" focusable="false">
                                <rect x="3" y="5.5" width="18" height="13" rx="2.6" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M3.9 7.2l8.1 5.8 8.1-5.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="ebs-footer-contact__body">
                            <span class="ebs-footer-contact__label">Email</span>
                            <a class="ebs-footer-contact__value" href="mailto:{{ $footerContact['email'] }}">{{ $footerContact['email'] }}</a>
                        </span>
                    </li>

                    <li class="ebs-footer-contact__row">
                        <span class="ebs-footer-contact__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="16" height="16" focusable="false">
                                <path d="M6.4 3.6h3.1l1.5 4-2 1.4a12.2 12.2 0 0 0 6 6l1.4-2 4 1.5v3.1a2 2 0 0 1-2.2 2A17.2 17.2 0 0 1 4.4 5.8a2 2 0 0 1 2-2.2z" fill="currentColor"/>
                            </svg>
                        </span>
                        <span class="ebs-footer-contact__body">
                            <span class="ebs-footer-contact__label">Phone</span>
                            <a class="ebs-footer-contact__value" href="tel:{{ preg_replace('/[^0-9+]/', '', $footerContact['phone']) }}">{{ $footerContact['phone'] }}</a>
                        </span>
                    </li>

                    <li class="ebs-footer-contact__row">
                        <span class="ebs-footer-contact__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="8.4" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 7.4V12l3.1 1.9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="ebs-footer-contact__body">
                            <span class="ebs-footer-contact__label">Business hours</span>
                            <span class="ebs-footer-contact__value">{{ $footerContact['hours'] }}</span>
                        </span>
                    </li>
                </ul>
            </div>

            {{-- Social profiles remain placeholders until profile URLs are configured. --}}
            <div class="ebs-footer-col ebs-footer-col--social" aria-labelledby="ebsFooterSocialTitle">
                <h2 class="ebs-footer-col__title" id="ebsFooterSocialTitle">Follow Us</h2>
                <ul class="ebs-footer__social">
                    <li><a class="ebs-footer__social-link" href="#" aria-label="E-Book on Facebook"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M13.5 21v-8h2.7l.4-3.2h-3.1V7.8c0-.9.3-1.5 1.6-1.5h1.6V3.4c-.3 0-1.2-.1-2.3-.1-2.4 0-4 1.4-4 4v2.5H7.5V13h2.9v8h3.1z"/></svg></a></li>
                    <li><a class="ebs-footer__social-link" href="#" aria-label="E-Book on X"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l5.164 6.931zm-1.161 17.52h1.833L7.084 4.126H5.117l11.966 15.644z"/></svg></a></li>
                    <li><a class="ebs-footer__social-link" href="#" aria-label="E-Book on Instagram"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5.2" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="4.1" stroke="currentColor" stroke-width="1.9"/><circle cx="17.2" cy="6.8" r="1.3" fill="currentColor"/></svg></a></li>
                    <li><a class="ebs-footer__social-link" href="#" aria-label="E-Book on YouTube"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true" focusable="false"><rect x="2.5" y="5.5" width="19" height="13" rx="4" stroke="currentColor" stroke-width="1.9"/><path d="M10.4 9.2v5.6l5-2.8-5-2.8z" fill="currentColor"/></svg></a></li>
                    <li><a class="ebs-footer__social-link" href="#" aria-label="E-Book on LinkedIn"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="3.4" stroke="currentColor" stroke-width="1.9"/><circle cx="7.4" cy="7.9" r="1.35" fill="currentColor"/><path d="M6.2 10.1h2.4v7.2H6.2zM10.2 10.1h2.3v1a2.6 2.6 0 0 1 2.2-1.1c2 0 3 1.3 3 3.5v3.8h-2.4v-3.4c0-1-.4-1.6-1.3-1.6s-1.4.6-1.4 1.7v3.3h-2.4z" fill="currentColor"/></svg></a></li>
                </ul>
            </div>
        </div>

        {{-- ============================ SECTION B: bottom bar ============================ --}}
        <div class="ebs-footer-bottom">
            <p class="ebs-footer-copy">&copy; {{ now()->year }} E-Book. All rights reserved.</p>
        </div>
    </div>
</footer>
