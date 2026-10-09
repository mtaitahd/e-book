{{ config('app.name') }} - Payment successful

Hi {{ $customerName }},

Thank you for your purchase. We have received your payment and your books are ready to read in My Books.

ORDER DETAILS
Order number: {{ $orderNumber }}
Status: {{ $statusLabel }}
Payment reference: {{ $paymentReference ?? '-' }}
Purchase date: {{ $purchasedOn }}
Currency: {{ $currency }}
Amount paid: {{ $amountPaid }}

WHAT YOU BOUGHT
@foreach ($lines as $line)
- {{ $line['title'] }} by {{ $line['author'] }}
  {{ $line['quantity'] }} x {{ $line['unit_price'] }} = {{ $line['subtotal'] }}
@endforeach

Subtotal: {{ $subtotal }}
Total: {{ $total }}

View my books: {{ $myBooksUrl }}
View order details: {{ $orderUrl }}

No files are attached to this email. To read online or download your books, sign in and open My Books.

This receipt confirms the purchase you just made at {{ config('app.name') }}.

(c) {{ now()->year }} {{ config('app.name') }}. All rights reserved.
