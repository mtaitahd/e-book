@extends('emails.layout')

@section('document-title', 'Payment successful — Order '.$orderNumber)
@section('headline', 'Payment successful')
@section('preheader', 'Receipt for order '.$orderNumber.' — '.$amountPaid.' paid.')
@section('footer-note', 'This receipt confirms the purchase you just made at '.config('app.name').'.')

@section('content')
    <p style="margin:0 0 8px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:700; line-height:1.5; color:#111827;">
        Hi {{ $customerName }},
    </p>

    <p style="margin:0 0 20px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.7; color:#4B5563;">
        Thank you for your purchase. Your payment of <strong style="color:#111827;">{{ $amountPaid }}</strong> has been
        received and your books are ready to read in <strong style="color:#111827;">My Books</strong>.
    </p>

    {{-- Status pill --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px 0;">
        <tr>
            <td bgcolor="#ECFDF5" style="background-color:#ECFDF5; border:1px solid #A7F3D0; border-radius:999px; padding:7px 18px;">
                <span style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; font-weight:800; letter-spacing:0.8px; text-transform:uppercase; color:#047857;">
                    {{ $statusLabel }}
                </span>
            </td>
        </tr>
    </table>

    {{-- Order summary --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0; background-color:#F9FAFB; border:1px solid #E5E7EB; border-radius:10px; border-collapse:separate;">
        <tr>
            <td colspan="2" style="padding:16px 20px 6px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:800; color:#111827;">
                Order details
            </td>
        </tr>
        <tr>
            <td style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#6B7280;">
                Order number
            </td>
            <td align="right" style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; color:#111827;">
                {{ $orderNumber }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#6B7280;">
                Payment reference
            </td>
            <td align="right" style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; color:#111827;">
                {{ $paymentReference ?? '—' }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#6B7280;">
                Purchase date
            </td>
            <td align="right" style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; color:#111827;">
                {{ $purchasedOn }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#6B7280;">
                Currency
            </td>
            <td align="right" style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; color:#111827;">
                {{ $currency }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px 20px 16px 20px; border-top:1px solid #E5E7EB; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:800; color:#111827;">
                Amount paid
            </td>
            <td align="right" style="padding:12px 20px 16px 20px; border-top:1px solid #E5E7EB; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:17px; font-weight:800; color:#047857;">
                {{ $amountPaid }}
            </td>
        </tr>
    </table>

    {{-- Purchased books --}}
    <p style="margin:0 0 12px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:800; color:#111827;">
        What you bought
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0; border:1px solid #E5E7EB; border-radius:10px; border-collapse:separate; overflow:hidden;">
        <tr style="background-color:#F3F4F6;">
            <th align="left" style="padding:10px 14px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:11px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; color:#6B7280;">
                Book
            </th>
            <th align="center" width="40" style="padding:10px 4px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:11px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; color:#6B7280;">
                Qty
            </th>
            <th align="right" style="padding:10px 8px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:11px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; color:#6B7280;">
                Unit price
            </th>
            <th align="right" style="padding:10px 14px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:11px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; color:#6B7280;">
                Subtotal
            </th>
        </tr>

        @foreach ($lines as $line)
            <tr>
                <td style="padding:14px; border-top:1px solid #F3F4F6; vertical-align:top;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                        <tr>
                            @if ($line['cover_url'])
                                {{-- Progressive enhancement only: the title and price below carry the meaning if images are blocked. --}}
                                <td width="44" style="padding-right:12px; vertical-align:top;">
                                    <img src="{{ $line['cover_url'] }}" alt="" width="44" height="62"
                                         style="display:block; width:44px; height:62px; border-radius:4px; border:1px solid #E5E7EB;">
                                </td>
                            @endif
                            <td style="vertical-align:top;">
                                <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; line-height:1.35; color:#111827;">
                                    {{ $line['title'] }}
                                </p>
                                <p style="margin:2px 0 0 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.4; color:#6B7280;">
                                    {{ $line['author'] }}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td align="center" style="padding:14px 4px; border-top:1px solid #F3F4F6; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#4B5563;">
                    {{ $line['quantity'] }}
                </td>
                <td align="right" style="padding:14px 8px; border-top:1px solid #F3F4F6; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#4B5563; white-space:nowrap;">
                    {{ $line['unit_price'] }}
                </td>
                <td align="right" style="padding:14px; border-top:1px solid #F3F4F6; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; color:#111827; white-space:nowrap;">
                    {{ $line['subtotal'] }}
                </td>
            </tr>
        @endforeach
    </table>

    {{-- Totals --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0; border-collapse:collapse;">
        <tr>
            <td align="right" style="padding:6px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#6B7280;">
                Subtotal
            </td>
            <td align="right" width="140" style="padding:6px 0 6px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#4B5563; white-space:nowrap;">
                {{ $subtotal }}
            </td>
        </tr>
        <tr>
            <td align="right" style="padding:12px 0; border-top:2px solid #111827; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:800; color:#111827;">
                Total
            </td>
            <td align="right" style="padding:12px 0 12px 20px; border-top:2px solid #111827; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:17px; font-weight:800; color:#111827; white-space:nowrap;">
                {{ $total }}
            </td>
        </tr>
    </table>

    <x-mail.button :url="$myBooksUrl" label="View my books" />

    <p style="margin:14px 0 0 0; text-align:center; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.6; color:#6B7280;">
        <a href="{{ $orderUrl }}" target="_blank" style="color:#0E7490; text-decoration:underline;">View order details</a>
    </p>

    <p style="margin:24px 0 0 0; padding-top:18px; border-top:1px solid #E5E7EB; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.7; color:#6B7280;">
        No files are attached to this email. To read online or download your books, sign in and open
        <a href="{{ $myBooksUrl }}" target="_blank" style="color:#0E7490; text-decoration:underline;">My Books</a>.
    </p>
@endsection