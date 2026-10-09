{{-- The topbar sits above #adminScroll, the shell's only scroll container, so it
     stays put on its own and needs no sticky positioning. `sticky-top` is kept
     purely for its z-index: without it the account dropdown would paint behind
     the content that follows it in the document. --}}
<nav class="navbar navbar-expand navbar-light bg-navbar topbar sticky-top" style="background-color: rgb(105, 105, 141);">
    <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3">
        <i class="fa fa-bars text-white"></i>
    </button>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <div class="img-profile rounded-circle bg-secondary" style="width: 32px; height: 32px; display: inline-block;"></div>
                <span class="ml-2 d-none d-lg-inline text-white small">{{ auth()->user()->name }}</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                <span class="dropdown-item-text small text-gray-500">Signed in as {{ auth()->user()->role }}</span>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">
                        <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                    </button>
                </form>
            </div>
        </li>
    </ul>
</nav>