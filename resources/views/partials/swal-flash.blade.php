{{--
    SweetAlert feedback for validation errors and flashed status messages.

    Renders the plain server-side list first (so it still works with JavaScript
    disabled) and tags the wrapper with the payload the shared script needs to
    swap it for a SweetAlert popup. Nothing is shown twice: the script hides the
    fallback list as soon as it takes over.

    Overridable sections:
        alert-title  popup heading
        alert-ok     popup confirm button label
--}}
@php
    $swalBag = ($errors instanceof \Illuminate\Support\ViewErrorBag) ? $errors : new \Illuminate\Support\ViewErrorBag;

    $swalMessages = array_values(array_filter(array_map('trim', $swalBag->all()), 'strlen'));
    $swalTone = 'error';
    $swalOk = trim((string) session('alert_ok', ''));
    if ($swalOk === '') {
        $swalOk = trim((string) \Illuminate\Support\Facades\View::getSection('alert-ok', 'Got it'));
    }

    // A flashed title (set by a controller that bounced the user) beats the
    // view's own section, which in turn beats the generic default.
    $swalTitle = trim((string) session('alert_title', ''));
    if ($swalTitle === '') {
        $swalTitle = trim((string) \Illuminate\Support\Facades\View::getSection('alert-title', ''));
    }

    $swalStatus = session('status') ?: (session('success') ?: null);
    $swalDanger = session('error');
    $swalNotice = session('info');

    if ($swalStatus) {
        $swalMessages = [(string) $swalStatus];
        $swalTone = 'success';
        $swalTitle = $swalTitle !== '' ? $swalTitle : 'Success';
    } elseif ($swalDanger) {
        $swalMessages = [(string) $swalDanger];
        $swalTone = 'error';
        $swalTitle = $swalTitle !== '' ? $swalTitle : 'Something went wrong';
    } elseif ($swalNotice) {
        $swalMessages = [(string) $swalNotice];
        $swalTone = 'info';
        $swalTitle = $swalTitle !== '' ? $swalTitle : 'Just so you know';
    }

    if ($swalTitle === '') {
        $swalTitle = 'Please check your details';
    }

    $swalFallbackClass = match ($swalTone) {
        'success' => 'flash success',
        'info' => 'flash note',
        default => 'flash error',
    };
@endphp

@if(! empty($swalMessages))
    <div
        data-swal-flash
        data-swal-type="{{ $swalTone }}"
        data-swal-title="{{ $swalTitle }}"
        data-swal-ok="{{ $swalOk }}"
        data-swal-messages="{{ json_encode($swalMessages) }}"
    >
        <div class="{{ $swalFallbackClass }}" data-swal-fallback>
            <ul>
                @foreach($swalMessages as $swalMessage)
                    <li>{{ $swalMessage }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
