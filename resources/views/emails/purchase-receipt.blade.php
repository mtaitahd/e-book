@extends('emails.layout')

@section('document-title', 'Payment successful — Order '.$orderNumber)
@section('headline', 'Payment successful')
@section('footer-note', 'This receipt confirms the purchase you just made at '.config('app.name').'.')

@section('content')
    <p style="margin:0 0 16px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:16px; line-height:1.6; color:#131921;">
        Hi {{ $customerName }},
    </p>

    <p style="margin:0 0 16px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:15px; line-height:1.7; color:#3D4A5C;">
        Thank you for your purchase. We have received your payment and your books
        are ready to read in <strong>My Books</strong>.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
        <tr>
            <td bgcolor="#007185" style="background-color:#007185; border-radius:4px; padding:6px 14px;">
                <span style="font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.8px; color:#FFFFFF;">
                    {{ $statusLabel }}
                </span>
            </td>
        </tr>
    </table>

    {{-- Order summary --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 28px 0; border-collapse:collapse;">
        <tr>
            <td colspan="2" style="padding:0 0 8px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:15px; font-weight:700; color:#131921;">
                Order details
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#6B7683;">
                Order number
            </td>
            <td align="right" style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; font-weight:700; color:#131921;">
                {{ $orderNumber }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#6B7683;">
                Payment reference
            </td>
            <td align="right" style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; font-weight:700; color:#131921;">
                {{ $paymentReference ?? '—' }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#6B7683;">
                Purchase date
            </td>
            <td align="right" style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; font-weight:700; color:#131921;">
                {{ $purchasedOn }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#6B7683;">
                Currency
            </td>
            <td align="right" style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; font-weight:700; color:#131921;">
                {{ $currency }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px 0; border-top:2px solid #131921; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:15px; font-weight:700; color:#131921;">
                Amount paid
            </td>
            <td align="right" style="padding:12px 0; border-top:2px solid #131921; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:18px; font-weight:700; color:#131921;">
                {{ $amountPaid }}
            </td>
        </tr>
    </table>

    {{-- Purchased books --}}
    <p style="margin:0 0 12px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:15px; font-weight:700; color:#131921;">
        What you bought
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; margin:0 0 16px 0;">
        <tr style="background-color:#F4F6F8;">
            <th align="left" style="padding:10px 8px; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.4px; text-transform:uppercase; color:#6B7683;">
                Book
            </th>
            <th align="center" width="46" style="padding:10px 4px; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.4px; text-transform:uppercase; color:#6B7683;">
                Qty
            </th>
            <th align="right" style="padding:10px 8px; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.4px; text-transform:uppercase; color:#6B7683;">
                Unit price
            </th>
            <th align="right" style="padding:10px 8px; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.4px; text-transform:uppercase; color:#6B7683;">
                Subtotal
            </th>
        </tr>

        @foreach ($lines as $line)
            <tr>
                <td style="padding:14px 8px; border-bottom:1px solid #EAEDED; vertical-align:top;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                        <tr>
                            @if ($line['cover_url'])
                                {{-- Progressive enhancement only: the title and price below carry the meaning if images are blocked. --}}
                                <td width="44" style="padding-right:12px; vertical-align:top;">
                                    <img src="{{ $line['cover_url'] }}" alt="" width="44" height="62"
                                         style="display:block; width:44px; height:62px; border-radius:3px; border:1px solid #EAEDED;">
                                </td>
                            @endif
                            <td style="vertical-align:top;">
                                <p style="margin:0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; font-weight:700; line-height:1.4; color:#131921;">
                                    {{ $line['title'] }}
                                </p>
                                <p style="margin:2px 0 0 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:13px; line-height:1.4; color:#6B7683;">
                                    {{ $line['author'] }}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td align="center" style="padding:14px 4px; border-bottom:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#3D4A5C;">
                    {{ $line['quantity'] }}
                </td>
                <td align="right" style="padding:14px 8px; border-bottom:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#3D4A5C; white-space:nowrap;">
                    {{ $line['unit_price'] }}
                </td>
                <td align="right" style="padding:14px 8px; border-bottom:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; font-weight:700; color:#131921; white-space:nowrap;">
                    {{ $line['subtotal'] }}
                </td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; margin:0 0 28px 0;">
        <tr>
            <td align="right" style="padding:6px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#6B7683;">
                Subtotal
            </td>
            <td align="right" width="120" style="padding:6px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#3D4A5C; white-space:nowrap;">
                {{ $subtotal }}
            </td>
        </tr>
        <tr>
            <td align="right" style="padding:10px 0; border-top:2px solid #131921; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:16px; font-weight:700; color:#131921;">
                Total
            </td>
            <td align="right" style="padding:10px 0; border-top:2px solid #131921; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:16px; font-weight:700; color:#131921; white-space:nowrap;">
                {{ $total }}
            </td>
        </tr>
    </table>

    <x-mail.button :url="$myBooksUrl" label="View my books" />

    <p style="margin:16px 0 0 0; text-align:center; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:13px; line-height:1.6; color:#6B7683;">
        <a href="{{ $orderUrl }}" target="_blank" style="color:#007185; text-decoration:underline;">View order details</a>
    </p>

    <p style="margin:24px 0 0 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:13px; line-height:1.7; color:#6B7683;">
        No files are attached to this email. To read online or download your books,
        sign in and open <a href="{{ $myBooksUrl }}" target="_blank" style="color:#007185; text-decoration:underline;">My Books</a>.
    </p>
@endsection
