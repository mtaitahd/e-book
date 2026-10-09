@props([
    'url',
    'label',
    'background' => '#0E7490',
    'color' => '#FFFFFF',
])

{{--
    Bulletproof call-to-action button. Compiled Blade, so it needs no
    JavaScript and no client-side rendering inside the mail client.
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
    <tr>
        <td bgcolor="{{ $background }}" style="background-color:{{ $background }}; border-radius:8px;">
            <a href="{{ $url }}" target="_blank"
               style="display:inline-block; padding:14px 32px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:700; line-height:1; color:{{ $color }}; text-decoration:none;">
                {{ $label }}
            </a>
        </td>
    </tr>
</table>