{{ config('app.name') }} - Payment unsuccessful

Hi {{ $customerName }},

{{ $reason }} Your order is still saved, so you can retry the payment whenever you are ready.

You have not been charged.

ORDER DETAILS
Order number: {{ $orderNumber }}
Order total: {{ $orderTotal }}
{{ $isExpired ? 'Expired on' : ($isVoided ? 'Voided on' : 'Attempted on') }}: {{ $failedOn }}

Try payment again: {{ $retryUrl }}
View order details: {{ $orderUrl }}

No payment was taken for this order. You can try again at any time.

(c) {{ now()->year }} {{ config('app.name') }}. All rights reserved.
