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
        </tfoot>
      </table>

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

