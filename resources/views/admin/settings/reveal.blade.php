<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Stored {{ $field === 'api_key' ? 'API key' : 'webhook secret' }} | E-Book Admin</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-gray-100">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header py-3">
                        <span class="font-weight-bold">
                            {{ $field === 'api_key' ? 'API key' : 'Webhook secret' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning" role="alert">
                            This is the real value. Do not paste it anywhere except the Abliner dashboard,
                            and do not send it to anyone. It is shown once and this page is not cached.
                        </div>

                        <div class="form-group">
                            <label for="revealed">Copy from here</label>
                            <textarea class="form-control" id="revealed" rows="3" readonly
                                      spellcheck="false" autocomplete="off">{{ $value }}</textarea>
                        </div>

                        <button type="button" class="btn btn-primary btn-sm" id="copyButton"
                                data-copy-target="revealed">
                            Copy to clipboard
                        </button>

                        <a href="{{ route('admin.settings.payments') }}" class="btn btn-link btn-sm">
                            Back to payment settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('copyButton').addEventListener('click', function () {
            var field = document.getElementById('revealed');
            field.select();
            field.setSelectionRange(0, field.value.length);

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(field.value);
            } else {
                document.execCommand('copy');
            }

            this.textContent = 'Copied';
        });
    </script>
</body>
</html>
