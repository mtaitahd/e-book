(function () {
    'use strict';

    var toggles = document.querySelectorAll('[data-mega]');
    var openTrigger = null;

    function closeAll() {
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].setAttribute('aria-expanded', 'false');
        }
        var panels = document.querySelectorAll('.ebs-mega.is-open');
        for (var j = 0; j < panels.length; j++) {
            panels[j].classList.remove('is-open');
        }
        openTrigger = null;
    }

    function openPanel(trigger) {
        var id = trigger.getAttribute('data-mega');
        closeAll();
        if (typeof closeDrawer === 'function' && catDrawerOpen()) {
            closeDrawer(false);
        }
        var panel = document.getElementById(id);
        if (!panel) return;
        panel.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        openTrigger = trigger;
    }

    for (var i = 0; i < toggles.length; i++) {
        (function (trigger) {
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                if (openTrigger === trigger) {
                    closeAll();
                } else {
                    openPanel(trigger);
                }
            });
        })(toggles[i]);
    }

    document.addEventListener('click', function (e) {
        var el = e.target;
        while (el && el !== document.body) {
            if (el.classList && el.classList.contains('ebs-mega')) return;
            if (el.getAttribute && el.getAttribute('data-mega')) return;
            el = el.parentNode;
        }
        if (openTrigger) closeAll();
    });

    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.keyCode === 27) && openTrigger) {
            closeAll();
            openTrigger.focus();
        }
    });

// Mobile drawer
    var drawerToggle = document.getElementById('ebsMobileToggle');
    var drawer = document.getElementById('ebsMobileDrawer');
    if (drawerToggle && drawer) {
        drawerToggle.addEventListener('click', function () {
            var open = drawer.classList.toggle('is-open');
            drawerToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && drawer.classList.contains('is-open')) {
                drawer.classList.remove('is-open');
                drawerToggle.setAttribute('aria-expanded', 'false');
                drawerToggle.focus();
            }
        });
    }

    // Categories side drawer (Amazon-style)
    var catDrawer = document.getElementById('ebsDrawer');
    var catDrawerOverlay = document.getElementById('ebsDrawerOverlay');
    var catDrawerClose = document.getElementById('ebsDrawerClose');
    var catDrawerTriggers = document.querySelectorAll('[data-drawer-open="ebsDrawer"]');
    var lastDrawerTrigger = null;

    function catDrawerOpen() {
        return !!catDrawer && catDrawer.classList.contains('is-open');
    }

    function megaTriggerFor(id) {
        for (var i = 0; i < toggles.length; i++) {
            if (toggles[i].getAttribute('data-mega') === id) return toggles[i];
        }
        return null;
    }

    function closeDrawer(returnFocus) {
        if (!catDrawer) return;
        catDrawer.classList.remove('is-open');
        catDrawer.setAttribute('aria-hidden', 'true');
        catDrawerOverlay.classList.remove('is-open');
        catDrawerOverlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ebs-drawer-open');
        for (var i = 0; i < catDrawerTriggers.length; i++) {
            catDrawerTriggers[i].setAttribute('aria-expanded', 'false');
        }
        if (returnFocus && lastDrawerTrigger) lastDrawerTrigger.focus();
    }

    function openDrawer(trigger) {
        lastDrawerTrigger = trigger || null;
        closeAll();
        catDrawer.classList.add('is-open');
        catDrawer.setAttribute('aria-hidden', 'false');
        catDrawerOverlay.classList.add('is-open');
        catDrawerOverlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ebs-drawer-open');
        for (var i = 0; i < catDrawerTriggers.length; i++) {
            catDrawerTriggers[i].setAttribute('aria-expanded', 'true');
        }
        if (catDrawerClose) catDrawerClose.focus();
    }

    if (catDrawer && catDrawerOverlay) {
        for (var d = 0; d < catDrawerTriggers.length; d++) {
            (function (trigger) {
                trigger.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (catDrawerOpen()) {
                        closeDrawer(true);
                    } else {
                        openDrawer(trigger);
                    }
                });
            })(catDrawerTriggers[d]);
        }

        if (catDrawerClose) {
            catDrawerClose.addEventListener('click', function () { closeDrawer(true); });
        }
        catDrawerOverlay.addEventListener('click', function () { closeDrawer(true); });

        var drawerMegaBtns = document.querySelectorAll('[data-drawer-mega]');
        for (var m = 0; m < drawerMegaBtns.length; m++) {
            (function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-drawer-mega');
                    closeDrawer(false);
                    var trigger = megaTriggerFor(id);
                    if (trigger) {
                        openPanel(trigger);
                        trigger.focus();
                    }
                });
            })(drawerMegaBtns[m]);
        }

        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && catDrawerOpen()) {
                closeDrawer(true);
            }
        });
    }

    // Amazon-style search department selector
    var searchBox = document.getElementById('ebsSearchForm');
    var searchCatToggle = document.getElementById('ebsSearchCatToggle');
    var searchCatMenu = document.getElementById('ebsSearchCatMenu');
    var searchCatName = searchBox && searchBox.querySelector('[data-search-cat-name]');

    function searchMenuOpen() {
        return !!searchCatMenu && !searchCatMenu.hidden;
    }

    function closeSearchMenu(returnFocus) {
        if (!searchCatMenu) return;
        searchCatMenu.hidden = true;
        searchCatToggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) searchCatToggle.focus();
    }

    if (searchBox && searchCatToggle && searchCatMenu) {
        searchCatToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (searchCatMenu.hidden) {
                searchCatMenu.hidden = false;
                searchCatToggle.setAttribute('aria-expanded', 'true');
            } else {
                closeSearchMenu();
            }
        });

        var searchCatOptions = searchCatMenu.querySelectorAll('[data-search-option]');
        for (var o = 0; o < searchCatOptions.length; o++) {
            (function (option) {
                option.addEventListener('click', function (e) {
                    e.stopPropagation();
                    for (var i = 0; i < searchCatOptions.length; i++) {
                        searchCatOptions[i].setAttribute('aria-checked', 'false');
                    }
                    option.setAttribute('aria-checked', 'true');
                    if (searchCatName) searchCatName.textContent = option.getAttribute('data-name');
                    searchBox.action = option.getAttribute('data-url');
                    closeSearchMenu(true);
                });
            })(searchCatOptions[o]);
        }

        document.addEventListener('click', function (e) {
            if (searchMenuOpen() && !searchBox.contains(e.target)) {
                closeSearchMenu();
            }
        });

        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && searchMenuOpen()) {
                closeSearchMenu(true);
            }
        });
    }

    // Account dropdown: hover on desktop, tap on touch
    var accountMenu = document.getElementById('ebsAccountMenu');
    if (accountMenu) {
        var accountTriggerEl = accountMenu.querySelector('.ebs-account__trigger');
        var accountPanelEl = document.getElementById('ebsAccountPanel');
        var accountOpenTimer = null;
        var accountCloseTimer = null;

        function openAccountMenu() {
            clearTimeout(accountCloseTimer);
            if (!accountPanelEl || !accountPanelEl.hidden) return;
            accountMenu.classList.add('is-open');
            accountPanelEl.hidden = false;
            accountTriggerEl.setAttribute('aria-expanded', 'true');
        }

        function closeAccountMenu() {
            clearTimeout(accountOpenTimer);
            if (!accountPanelEl || accountPanelEl.hidden) return;
            accountMenu.classList.remove('is-open');
            accountPanelEl.hidden = true;
            accountTriggerEl.setAttribute('aria-expanded', 'false');
        }

        var accountFinePointer = true;
        try {
            accountFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        } catch (err) { /* keep default */ }

        if (accountFinePointer) {
            accountMenu.addEventListener('mouseenter', function () {
                clearTimeout(accountCloseTimer);
                accountOpenTimer = setTimeout(openAccountMenu, 160);
            });
            accountMenu.addEventListener('mouseleave', function () {
                clearTimeout(accountOpenTimer);
                accountCloseTimer = setTimeout(closeAccountMenu, 200);
            });
        }

        accountTriggerEl.addEventListener('click', function (e) {
            if (accountFinePointer) return;
            if (e.target.closest('a')) return;
            e.preventDefault();
            if (accountPanelEl.hidden) {
                openAccountMenu();
            } else {
                closeAccountMenu();
            }
        });

        document.addEventListener('click', function (e) {
            if (accountPanelEl && !accountPanelEl.hidden && !accountMenu.contains(e.target)) {
                closeAccountMenu();
            }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && accountPanelEl && !accountPanelEl.hidden) {
                closeAccountMenu();
            }
        });
    }

// "New &amp; Trending" mega menu: load newest published books live
    var trendList = document.getElementById('ebsTrendList');

    function escapeHtml(value) {
        return String(value).replace(/[<>&"']/g, function (c) {
            return { '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    if (trendList) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', trendList.getAttribute('data-trending'));
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = function () {
            if (xhr.status !== 200) {
                trendList.innerHTML = '<p class="muted">Browse all books anytime.</p>';
                return;
            }
            var data;
            try { data = JSON.parse(xhr.responseText); } catch (e) { trendList.innerHTML = ''; return; }
            var books = (data && data.books) || [];
            if (!books.length) { trendList.innerHTML = '<p class="muted">More books are on the way.</p>'; return; }

            var html = '<div class="ebs-trend-grid">';
            for (var i = 0; i < books.length; i++) {
                var b = books[i];
                var cover = b.cover
                    ? '<img src="' + escapeHtml(b.cover) + '" alt="" loading="lazy">'
                    : '<span class="ebs-trend-card__none"></span>';
                var authors = b.authors ? '<span class="ebs-trend-card__meta">by ' + escapeHtml(b.authors) + '</span>' : '';
                html += '<a class="ebs-trend-card" href="' + escapeHtml(b.url) + '">'
                    + cover
                    + '<span class="ebs-trend-card__body">'
                    + '<span class="ebs-trend-card__title">' + escapeHtml(b.title) + '</span>'
                    + authors
                    + '</span></a>';
            }
            html += '</div>';
            trendList.innerHTML = html;
        };
        xhr.onerror = function () {
            trendList.innerHTML = '<p class="muted">Browse all books anytime.</p>';
        };
        xhr.send();
    }

    // Auth modal (customer sign-in / sign-up) -- frontend presentation of the
    // login.store and register.store routes. The dedicated /login page is
    // administrator-only and is never intercepted here, so admins can still
    // reach it.
    var authOverlay = document.getElementById('ebsAuthOverlay');
    if (authOverlay) {
        var authPanel = document.getElementById('ebsAuthPanel');
        var authCloseBtn = document.getElementById('ebsAuthClose');
        var authLoginView = document.getElementById('ebsLoginView');
        var authRegisterView = document.getElementById('ebsRegisterView');
        var authTitle = document.getElementById('ebsAuthTitle');
        var authSubtitle = document.getElementById('ebsAuthSubtitle');
        var authLastTrigger = null;
        // Where to send the customer once the modal reports a successful sign-in
        // or sign-up.
        var authReturnTo = authOverlay.getAttribute('data-auth-intended') || '';

        function authField(form, name) {
            return form ? form.querySelector('[name="' + name + '"]') : null;
        }

        function clearAuthErrors(form) {
            if (!form) return;
            var errs = form.querySelectorAll('.ebs-auth-error');
            for (var i = 0; i < errs.length; i++) errs[i].textContent = '';
            var bad = form.querySelectorAll('.is-invalid');
            for (var j = 0; j < bad.length; j++) bad[j].classList.remove('is-invalid');
        }

        // Write the message next to the field it belongs to. Used for sign-up,
        // where there are enough distinct fields that a single popup would lose
        // the detail (and where the fields are rendered per field server-side
        // for the no-JS fallback).
        function renderAuthErrors(form, errors) {
            for (var key in errors) {
                if (!Object.prototype.hasOwnProperty.call(errors, key)) continue;
                var slot = form.querySelector('[data-error-for="' + key + '"]');
                if (slot) slot.textContent = (errors[key] || []).join(' ');
                var input = authField(form, key);
                if (input) input.classList.add('is-invalid');
            }
        }

        // Mark the offending fields without writing any text. Used for sign-in
        // failures, where the SweetAlert popup already carries the message --
        // writing it inline as well would show it twice.
        function markAuthInvalid(form, errors) {
            for (var key in errors) {
                if (!Object.prototype.hasOwnProperty.call(errors, key)) continue;
                var input = authField(form, key);
                if (input) input.classList.add('is-invalid');
            }
        }

        // Raise a SweetAlert for a rejected sign-in or sign-up so the user gets
        // an unmistakable popup. Sign-in failures are only reported here; sign-up
        // failures are also written inline next to their field.
        function notifyAuthFailure(view, data) {
            var flash = window.EbookSwalFlash;
            if (!flash || typeof flash.show !== 'function') return;

            var isLogin = view === 'login';
            var title = (data && data.title) || (isLogin ? 'Login Failed' : 'Registration Failed');
            var message = (data && data.message) || 'Something went wrong. Please try again.';

            flash.show({
                type: 'error',
                title: title,
                ok: 'Try Again',
                messages: [message]
            });
        }

        // Swap between the sign-in and sign-up views. The heading is shared, so
        // its text comes from the incoming view's data attributes.
        function showAuthView(name) {
            var wantRegister = name === 'register' && authRegisterView;
            authLoginView.hidden = !!wantRegister;
            if (authRegisterView) authRegisterView.hidden = !wantRegister;

            var active = wantRegister ? authRegisterView : authLoginView;
            if (authTitle) authTitle.textContent = active.getAttribute('data-title') || '';
            if (authSubtitle) authSubtitle.textContent = active.getAttribute('data-subtitle') || '';

            var first = active.querySelector('input');
            if (first) first.focus();
        }

        function openAuthModal(trigger, view) {
            authLastTrigger = trigger || document.activeElement;
            authOverlay.hidden = false;
            document.body.classList.add('ebs-auth-open');
            showAuthView(view);
            window.requestAnimationFrame(function () {
                authOverlay.classList.add('is-open');
            });
        }

        function closeAuthModal() {
            if (authOverlay.hidden) return;
            document.body.classList.remove('ebs-auth-open');
            authOverlay.classList.remove('is-open');
            window.setTimeout(function () {
                authOverlay.hidden = true;
                if (authLastTrigger && authLastTrigger.focus) {
                    try { authLastTrigger.focus(); } catch (err) {}
                }
            }, 180);
        }

        // Guest-only links carry data-ebs-auth-open; those pointing at a
        // protected page (checkout) also carry data-ebs-return so the customer
        // resumes that page after signing in. The href is kept as the no-JS
        // fallback: the server bounces the guest back here with the modal open.
        document.addEventListener('click', function (e) {
            var link = e.target.closest && e.target.closest('a[href]');
            if (!link) return;

            if (link.hasAttribute('data-ebs-checkout')) {
                e.preventDefault();
                authReturnTo = link.getAttribute('data-ebs-return') || link.getAttribute('href') || '';
                openAuthModal(link);
                return;
            }

            if (link.hasAttribute('data-ebs-auth-open')) {
                e.preventDefault();
                authReturnTo = link.getAttribute('data-ebs-return') || '';
                openAuthModal(link);
            }
        });

        // Overlay-click-close
        document.addEventListener('click', function (e) {
            if (e.target === authOverlay) closeAuthModal();
        });

        if (authCloseBtn) authCloseBtn.addEventListener('click', closeAuthModal);

        // Show/hide password toggles
        var authEyes = authPanel.querySelectorAll('[data-auth-eye]');
        for (var eyeIdx = 0; eyeIdx < authEyes.length; eyeIdx++) {
            (function (eyeBtn) {
                eyeBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var pwInput = document.getElementById(eyeBtn.getAttribute('data-auth-eye'));
                    if (!pwInput) return;
                    var show = pwInput.type === 'password';
                    pwInput.type = show ? 'text' : 'password';
                    var openIcon = eyeBtn.querySelector('.ebs-auth-eye__open');
                    var offIcon = eyeBtn.querySelector('.ebs-auth-eye__off');
                    if (openIcon) openIcon.style.display = show ? 'none' : '';
                    if (offIcon) offIcon.style.display = show ? '' : 'none';
                    eyeBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                });
            })(authEyes[eyeIdx]);
        }

        // Forgot password is a visual affordance only (no reset flow exists)
        document.addEventListener('click', function (e) {
            var forgot = e.target.closest && e.target.closest('[data-auth-forgot]');
            if (forgot) e.preventDefault();
        });

        // "Don't have an account? Create one" / "Already have an account?
        // Sign in" -- swap views in place, clearing anything either form showed.
        document.addEventListener('click', function (e) {
            var link = e.target.closest && e.target.closest('[data-auth-switch]');
            if (!link) return;
            e.preventDefault();
            clearAuthErrors(document.getElementById('ebsLoginForm'));
            clearAuthErrors(document.getElementById('ebsRegisterForm'));
            showAuthView(link.getAttribute('data-auth-switch'));
        });

        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && !authOverlay.hidden) closeAuthModal();
        });

        // Submit through the existing backend route (same validation, CSRF, sessions)
        var authForm = document.getElementById('ebsLoginForm');
        if (authForm) {
            authForm.addEventListener('submit', function (e) {
                e.preventDefault();
                clearAuthErrors(authForm);
                var btn = authForm.querySelector('.ebs-auth-submit');
                var label = btn.querySelector('.ebs-auth-submit__label');
                var original = label ? label.textContent : btn.textContent;
                if (label) label.textContent = 'Signing in...';
                btn.disabled = true;

                window.fetch(authForm.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(authForm)
                }).then(function (res) {
                    if (res.status === 422) {
                        return res.json().then(function (data) {
                            // The popup carries the message; only flag the field.
                            clearAuthErrors(authForm);
                            markAuthInvalid(authForm, (data && data.errors) || {});
                            notifyAuthFailure('login', data);
                        });
                    }
                    // Signed in. Continue to the protected page the customer was
                    // trying to reach (e.g. checkout), otherwise reload so the
                    // server-rendered authenticated state takes over.
                    if (authReturnTo) {
                        var target = authReturnTo;
                        authReturnTo = '';
                        window.location.assign(target);
                        return;
                    }
                    window.location.reload();
                }).catch(function () {
                    // Popup carries the message; keep the field text empty.
                    clearAuthErrors(authForm);
                    notifyAuthFailure('login', null);
                }).then(function () {
                    btn.disabled = false;
                    if (label) label.textContent = original;
                });
            });
        }

        // Sign-up posts to register.store, which creates the account and signs
        // it in, so success takes the same path as a successful sign-in.
        var registerForm = document.getElementById('ebsRegisterForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function (e) {
                e.preventDefault();
                clearAuthErrors(registerForm);
                var btn = registerForm.querySelector('.ebs-auth-submit');
                var label = btn.querySelector('.ebs-auth-submit__label');
                var original = label ? label.textContent : btn.textContent;
                if (label) label.textContent = 'Creating account...';
                btn.disabled = true;

                window.fetch(registerForm.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(registerForm)
                }).then(function (res) {
                    if (res.status === 422) {
                        return res.json().then(function (data) {
                            renderAuthErrors(registerForm, (data && data.errors) || {});
                            notifyAuthFailure('register', data);
                        });
                    }
                    if (authReturnTo) {
                        var target = authReturnTo;
                        authReturnTo = '';
                        window.location.assign(target);
                        return;
                    }
                    window.location.reload();
                }).catch(function () {
                    notifyAuthFailure('register', null);
                }).then(function () {
                    btn.disabled = false;
                    if (label) label.textContent = original;
                });
            });
        }

        // Page-load fallback: a guest bounced off a protected page arrives here
        // with the URL they wanted, so reopen the modal and resume it later.
        if (authOverlay.getAttribute('data-auth-auto') === '1') {
            var bouncedErrors = {};
            try { bouncedErrors = JSON.parse(authOverlay.getAttribute('data-auth-errors') || '{}'); } catch (err) {}
            var bouncedOld = {};
            try { bouncedOld = JSON.parse(authOverlay.getAttribute('data-auth-old') || '{}'); } catch (err) {}
            var bouncedView = authOverlay.getAttribute('data-auth-view') || 'login';
            openAuthModal(null, bouncedView === 'register' ? 'register' : 'login');
            if (bouncedView === 'register') {
                // Sign-up errors are already rendered next to their fields by the
                // partial, so there is nothing left to mark here.
            } else {
                var bouncedEmail = authField(authForm, 'email');
                if (bouncedEmail) bouncedEmail.value = bouncedOld.email || '';
                // The page-level SweetAlert already showed any message, so only flag
                // the field -- rendering it inline too would duplicate it.
                markAuthInvalid(authForm, bouncedErrors);
            }
        }
    }
})();