@extends('emails.layout')

@section('document-title', 'Payment failed — Order '.$orderNumber)
@section('headline', 'Payment unsuccessful')
@section('footer-note', 'No payment was taken for this order. You can try again at any time.')

@section('content')
    <p style="margin:0 0 16px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:16px; line-height:1.6; color:#131921;">
        Hi {{ $customerName }},
    </p>

    <p style="margin:0 0 16px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:15px; line-height:1.7; color:#3D4A5C;">
        {{ $reason }} Your order is still saved, so you can retry the payment whenever
        you are ready.
    </p>

    <p style="margin:0 0 24px 0; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:15px; line-height:1.7; color:#3D4A5C;">
        <strong style="color:#131921;">You have not been charged.</strong>
    </p>

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
                Order total
            </td>
            <td align="right" style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; font-weight:700; color:#131921;">
                {{ $orderTotal }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; color:#6B7683;">
                {{ $isExpired ? 'Expired on' : ($isVoided ? 'Voided on' : 'Attempted on') }}
            </td>
            <td align="right" style="padding:8px 0; border-top:1px solid #EAEDED; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:14px; font-weight:700; color:#131921;">
                {{ $failedOn }}
            </td>
        </tr>
    </table>

    <x-mail.button :url="$retryUrl" label="Try payment again" />

    <p style="margin:16px 0 0 0; text-align:center; font-family:'Nunito','Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif; font-size:13px; line-height:1.6; color:#6B7683;">
        <a href="{{ $orderUrl }}" target="_blank" style="color:#007185; text-decoration:underline;">View order details</a>
    </p>
@endsection
