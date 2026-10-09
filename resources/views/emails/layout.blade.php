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
    external stylesheet, and Nunito is requested first but always falls back
    to a system stack, so the design degrades instead of breaking.
--}}
<body style="margin:0; padding:0; width:100%; background-color:#EAEDED; -webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#EAEDED"
       style="background-color:#EAEDED; margin:0; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:600px; width:100%; background-color:#FFFFFF; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(19,25,33,0.12);">

                {{-- Brand header --}}
                <tr>
                    <td bgcolor="#131921" style="background-color:#131921; padding:28px 32px;">
                        <p style="margin:0 0 4px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:22px; font-weight:700; line-height:1.2; color:#FFFFFF; letter-spacing:0.3px;">
                            {{ config('app.name') }}
                        </p>
                        <p style="margin:0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; line-height:1.5; color:#FF9900; font-weight:700;">
                            @yield('headline')
                        </p>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td bgcolor="#FFFFFF" style="background-color:#FFFFFF; padding:32px;">
                        @yield('content')
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td bgcolor="#232F3E" style="background-color:#232F3E; padding:24px 32px;">
                        <p style="margin:0 0 8px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:13px; line-height:1.6; color:#FFFFFF;">
                            @yield('footer-note', 'This message was sent because you have an account with us.')
                        </p>
                        <p style="margin:0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:12px; line-height:1.6; color:#9BA7B4;">
                            &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
