<!DOCTYPE html>
<html>
  <head>
    <title>Invoice</title>
    <meta charset="UTF-8">
    <style>
      body {
        background: #f6f8fa;
        font-family: Arial, Helvetica, sans-serif;
        margin: 0;
        padding: 0;
      }
      .email-container {
        max-width: 600px;
        margin: 40px auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        padding: 32px 24px;
      }
      h2 {
        color: #2563eb;
        margin-bottom: 8px;
        font-size: 28px;
        font-weight: 700;
      }
      h4 {
        color: #374151;
        margin-bottom: 16px;
        font-size: 18px;
        font-weight: 600;
      }
      p {
        color: #374151;
        font-size: 16px;
        margin-bottom: 12px;
      }
      .info-section {
        background: #f9fafb;
        border-radius: 6px;
        padding: 16px;
        margin: 20px 0;
      }
      .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
      }
      .info-row:last-child {
        border-bottom: none;
      }
      .label {
        font-weight: 600;
        color: #4b5563;
      }
      .value {
        color: #374151;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
      }
      th {
        background: #f3f4f6;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
      }
      td {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
        color: #374151;
      }
      .total-row {
        font-weight: 700;
        font-size: 18px;
        background: #f9fafb;
      }
      .footer {
        color: #6b7280;
        font-size: 14px;
        margin-top: 32px;
        text-align: center;
        border-top: 1px solid #e5e7eb;
        padding-top: 20px;
      }
      .notes {
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        padding: 12px 16px;
        margin: 20px 0;
        border-radius: 4px;
      }
    </style>
  </head>
  <body>
    <div class="email-container">
      <h2>Invoice #{{ $invoiceData['invoice_number'] ?? 'N/A' }}</h2>
      <p>Dear {{ $invoiceData['first_name'] ?? '' }} {{ $invoiceData['last_name'] ?? '' }},</p>
      <p>Thank you for choosing our services. Please find your invoice details below:</p>
      
      <div class="info-section">
        <div class="info-row">
          <span class="label">Invoice Number:</span>
          <span class="value">{{ $invoiceData['invoice_number'] ?? 'N/A' }}</span>
        </div>
        @if(isset($invoiceData['issued_at']) && $invoiceData['issued_at'])
        <div class="info-row">
          <span class="label">Issued Date:</span>
          <span class="value">{{ \Carbon\Carbon::parse($invoiceData['issued_at'])->format('F j, Y h:i A') }}</span>
        </div>
        @endif
        @if(isset($invoiceData['due_date']) && $invoiceData['due_date'])
        <div class="info-row">
          <span class="label">Due Date:</span>
          <span class="value">{{ \Carbon\Carbon::parse($invoiceData['due_date'])->format('F j, Y') }}</span>
        </div>
        @endif
        @if(isset($invoiceData['status']) && $invoiceData['status'])
        <div class="info-row">
          <span class="label">Status:</span>
          <span class="value" style="text-transform: uppercase; font-weight: 600;">{{ $invoiceData['status'] }}</span>
        </div>
        @endif
      </div>

      <h4>Items & Pricing</h4>

      @if(isset($invoiceData['additional_services_grouped_by_pet']) && is_array($invoiceData['additional_services_grouped_by_pet']) && count($invoiceData['additional_services_grouped_by_pet']) > 0)
      <div style="margin-bottom: 12px;">
        <strong>Additional Services by Pet</strong>
        <ul style="margin: 6px 0 0 18px; padding: 0;">
          @foreach($invoiceData['additional_services_grouped_by_pet'] as $petGroup)
            <li>{{ $petGroup['pet_name'] ?? 'Pet' }}: {{ isset($petGroup['services']) && is_array($petGroup['services']) ? implode(', ', $petGroup['services']) : '' }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Item</th>
            <th style="text-align: right;">Price</th>
          </tr>
        </thead>
        <tbody>
          @php
            $rowNumber = 1;
          @endphp
          
          @if(isset($invoiceData['main_service_items']) && is_array($invoiceData['main_service_items']) && count($invoiceData['main_service_items']) > 0)
            @foreach($invoiceData['main_service_items'] as $item)
            <tr>
              <td>{{ $rowNumber++ }}</td>
              <td>{{ $item['description'] ?? 'N/A' }}</td>
              <td style="text-align: right;">${{ number_format($item['price'] ?? 0, 2) }}</td>
            </tr>
            @endforeach
          @endif

          @if(isset($invoiceData['additional_service_items']) && is_array($invoiceData['additional_service_items']) && count($invoiceData['additional_service_items']) > 0)
            @foreach($invoiceData['additional_service_items'] as $item)
            <tr>
              <td>{{ $rowNumber++ }}</td>
              <td>{{ $item['description'] ?? 'N/A' }}</td>
              <td style="text-align: right;">${{ number_format($item['price'] ?? 0, 2) }}</td>
            </tr>
            @endforeach
          @endif

          @if(isset($invoiceData['inventory_items']) && is_array($invoiceData['inventory_items']) && count($invoiceData['inventory_items']) > 0)
            @foreach($invoiceData['inventory_items'] as $item)
            <tr>
              <td>{{ $rowNumber++ }}</td>
              <td>{{ $item['description'] ?? 'N/A' }}</td>
              <td style="text-align: right;">${{ number_format($item['price'] ?? 0, 2) }}</td>
            </tr>
            @endforeach
          @endif
          
          @if($rowNumber === 1)
            <tr>
              <td colspan="3" style="text-align: center; color: #6b7280;">No items available</td>
            </tr>
          @endif
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 600;">Total Price of Services:</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($invoiceData['total_service_price'] ?? 0, 2) }}</td>
          </tr>
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 600;">Estimated Price of Services:</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($invoiceData['estimated_price'] ?? 0, 2) }}</td>
          </tr>
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 600;">Discount{{ !empty($invoiceData['discount_title']) ? ' (' . $invoiceData['discount_title'] . ')' : '' }}:</td>
            <td style="text-align: right; font-weight: 600;">-${{ number_format($invoiceData['discount_amount'] ?? 0, 2) }}</td>
          </tr>
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 600;">Total Inventory Amount:</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($invoiceData['total_inventory_amount'] ?? 0, 2) }}</td>
          </tr>
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 600;">Subtotal:</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($invoiceData['subtotal_amount'] ?? 0, 2) }}</td>
          </tr>
          @if(!empty($invoiceData['state_tax_rate']) && ($invoiceData['state_tax_rate'] > 0))
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 600;">State Tax ({{ number_format($invoiceData['state_tax_rate'], 2) }}%):</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($invoiceData['state_tax_amount'] ?? 0, 2) }}</td>
          </tr>
          @endif
          <tr class="total-row">
            <td colspan="2" style="text-align: right;">Total Amount:</td>
            <td style="text-align: right;">${{ number_format($invoiceData['total_amount'] ?? 0, 2) }}</td>
          </tr>
          @if(!empty($invoiceData['online_payment']) && $invoiceData['online_payment'] > 0)
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 600;">Online Payment:</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($invoiceData['online_payment'] ?? 0, 2) }}</td>
          </tr>
          @endif
          @if(!empty($invoiceData['in_person_payment']) && $invoiceData['in_person_payment'] > 0)
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 600;">In-Person Payment:</td>
            <td style="text-align: right; font-weight: 600;">${{ number_format($invoiceData['in_person_payment'] ?? 0, 2) }}</td>
          </tr>
          @endif
          @if(isset($invoiceData['balance_due']))
          <tr>
            <td colspan="2" style="text-align: right; font-weight: 700;">Balance Due:</td>
            <td style="text-align: right; font-weight: 700;">${{ number_format($invoiceData['balance_due'] ?? 0, 2) }}</td>
          </tr>
          @endif
        </tfoot>
      </table>

      @if(isset($invoiceData['payment_link_url']) && $invoiceData['payment_link_url'])
      <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 16px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0 0 12px 0; font-weight: 600; color: #1565c0;">Pay Invoice Online</p>
        <p style="margin: 0 0 12px 0; color: #333;">Click the button below to pay your remaining balance securely online using Stripe:</p>
        <a href="{{ $invoiceData['payment_link_url'] }}" style="display: inline-block; background: #2196f3; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: 600;">
          Pay Now - ${{ number_format($invoiceData['balance_due'] ?? $invoiceData['total_amount'] ?? 0, 2) }}
        </a>
        <p style="margin: 12px 0 0 0; font-size: 13px; color: #666;">Your payment is secure. Your card details are encrypted and processed by Stripe.</p>
      </div>
      @endif

      @if(isset($invoiceData['notes']) && $invoiceData['notes'])
      <div class="notes">
        <strong>Notes:</strong><br>
        {{ $invoiceData['notes'] }}
      </div>
      @endif

      <p>If you have any questions about this invoice, please don't hesitate to contact us.</p>

      <div class="footer">
        Best regards,<br>
        <strong>Sunshine Spot Team</strong>
      </div>
    </div>
  </body>
</html>

