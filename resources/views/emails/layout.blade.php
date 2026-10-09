<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>@yield('document-title', config('app.name'))</title>
</head>
{{--
    Table-based layout with inline styles: the only structure that renders
    consistently across Outlook, Gmail and Apple Mail. No JavaScript, no
    external stylesheet, and a system font stack so the design degrades
    instead of breaking.
--}}
<body style="margin:0; padding:0; width:100%; background-color:#F3F4F6; -webkit-font-smoothing:antialiased;">

    {{-- Hidden preheader so Gmail's snippet shows a useful sentence. --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F3F4F6"
           style="background-color:#F3F4F6; margin:0; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:620px; width:100%; background-color:#FFFFFF; border-radius:14px; overflow:hidden; box-shadow:0 6px 18px rgba(17,24,39,0.08);">

                    {{-- Brand accent bar --}}
                    <tr>
                        <td bgcolor="#FF9900" height="6"
                            style="background-color:#FF9900; font-size:0; line-height:0; height:6px;">&nbsp;</td>
                    </tr>

                    {{-- Brand header with logo --}}
                    <tr>
                        <td style="padding:28px 32px 18px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                                <tr>
                                    <td width="50" style="vertical-align:middle;">
                                        <img src="{{ asset('assets/e_book-removebg-preview.png') }}" alt="{{ config('app.name') }}"
                                             width="50" height="50"
                                             style="display:block; width:50px; height:50px; border-radius:12px; background-color:#FFFFFF; border:1px solid #F3F4F6;">
                                    </td>
                                    <td style="padding-left:14px; vertical-align:middle;">
                                        <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:21px; font-weight:800; letter-spacing:0.2px; color:#111827; white-space:nowrap;">
                                            {{ config('app.name') }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0 0; text-align:center; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:700; letter-spacing:0.3px; color:#B45309;">
                                @yield('headline')
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td bgcolor="#FFFFFF" style="background-color:#FFFFFF; padding:6px 32px 32px 32px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td bgcolor="#F8FAFC" style="background-color:#F8FAFC; border-top:1px solid #E5E7EB; padding:24px 32px;">
                            <p style="margin:0 0 12px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.6; color:#4B5563;">
                                @yield('footer-note', 'This message was sent to a customer of '.config('app.name').'.')
                            </p>
                            <p style="margin:0 0 6px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.6; color:#6B7280;">
                                {{ config('app.name') }} &middot; {{ config('app.url') }}
                            </p>
                            <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.6; color:#9CA3AF;">
                                &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
                            </p>
                            <p style="margin:12px 0 0 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.6; color:#6B7280;">
                                Need help? <a href="mailto:info@gajokibooks.co.tz" style="color:#0E7490; text-decoration:underline;">info@gajokibooks.co.tz</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>