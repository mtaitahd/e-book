@extends('emails.layout')

@section('document-title', 'Payment failed — Order '.$orderNumber)
@section('headline', 'Payment unsuccessful')
@section('preheader', 'Your payment could not be completed. No payment was taken for order '.$orderNumber.'.')
@section('footer-note', 'No payment was taken for this order. You can try again at any time.')

@section('content')
    <p style="margin:0 0 8px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:700; line-height:1.5; color:#111827;">
        Hi {{ $customerName }},
    </p>

    <p style="margin:0 0 18px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.7; color:#4B5563;">
        {{ $reason }} Your order is still saved, so you can retry the payment whenever you are ready.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px 0;">
        <tr>
            <td bgcolor="#FEF2F2" style="background-color:#FEF2F2; border:1px solid #FECACA; border-radius:8px; padding:12px 16px;">
                <span style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; color:#B91C1C;">
                    <strong>You have not been charged.</strong>
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
                Order total
            </td>
            <td align="right" style="padding:8px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; color:#111827;">
                {{ $orderTotal }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px 20px 16px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#6B7280;">
                {{ $isExpired ? 'Expired on' : ($isVoided ? 'Voided on' : 'Attempted on') }}
            </td>
            <td align="right" style="padding:8px 20px 16px 20px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:700; color:#111827;">
                {{ $failedOn }}
            </td>
        </tr>
    </table>

    <x-mail.button :url="$retryUrl" label="Try payment again" />

    <p style="margin:14px 0 0 0; text-align:center; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.6; color:#6B7280;">
        <a href="{{ $orderUrl }}" target="_blank" style="color:#0E7490; text-decoration:underline;">View order details</a>
    </p>
@endsection