@props([
    'url',
    'label',
    'background' => '#FF9900',
    'color' => '#131921',
])

{{--
    Bulletproof call-to-action button. Compiled Blade, so it needs no
    JavaScript and no client-side rendering inside the mail client.
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
    <tr>
        <td bgcolor="{{ $background }}" style="background-color:{{ $background }}; border-radius:6px;">
            <a href="{{ $url }}" target="_blank"
               style="display:inline-block; padding:13px 28px; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:15px; font-weight:700; line-height:1; color:{{ $color }}; text-decoration:none;">
                {{ $label }}
            </a>
        </td>
    </tr>
</table>
