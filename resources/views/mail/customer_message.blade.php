
<!DOCTYPE html>
<html>
  <head>
    <title>Message</title>
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
      .cta-wrapper {
        margin: 22px 0;
      }
      .cta-button {
        display: inline-block;
        background: #2563eb;
        color: #ffffff !important;
        text-decoration: none;
        padding: 12px 18px;
        border-radius: 6px;
        font-weight: 600;
      }
      .direct-link {
        word-break: break-all;
        font-size: 14px;
      }
    </style>
  </head>
  <body>
    <div class="email-container">
      <h2>Sunshine Spot</h2>
      <p>Dear {{ $messageData['customer_name'] ?? 'Customer' }},</p>
      <p>{!! nl2br(e($messageData['message'] ?? '')) !!}</p>
      @if(!empty($messageData['cta_url']))
      <div class="cta-wrapper">
        <a class="cta-button" href="{{ $messageData['cta_url'] }}" target="_blank" rel="noopener noreferrer">{{ $messageData['cta_label'] ?? 'Open Link' }}</a>
      </div>
      <p class="direct-link">
        If the button does not work, open this link directly:
        <a href="{{ $messageData['cta_url'] }}" target="_blank" rel="noopener noreferrer">{{ $messageData['cta_url'] }}</a>
      </p>
      @endif
      <div class="footer">
        Best regards,<br>
        <strong>{{ $messageData['sender_name'] ?? 'Sunshine Spot Team' }}</strong>
      </div>
    </div>
  </body>
</html>


