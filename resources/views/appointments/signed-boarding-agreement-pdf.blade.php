<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Signed Boarding Agreement</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      color: #111827;
      background: #ffffff;
      font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
      font-size: 12px;
      line-height: 1.45;
    }

    .page {
      padding: 22px;
    }

    .header {
      border-bottom: 1px solid #d1d5db;
      padding-bottom: 10px;
      margin-bottom: 14px;
    }

    .header h1 {
      margin: 0;
      font-size: 22px;
      font-weight: 700;
    }

    .header p {
      margin: 4px 0 0;
      color: #4b5563;
      font-size: 11px;
    }

    .section {
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 12px;
      margin-bottom: 12px;
      page-break-inside: avoid;
    }

    .section h2 {
      margin: 0 0 8px;
      font-size: 15px;
      font-weight: 700;
      color: #111827;
    }

    .row {
      width: 100%;
      margin: 0 -6px;
      font-size: 0;
    }

    .col {
      display: inline-block;
      vertical-align: top;
      width: 50%;
      padding: 0 6px;
      font-size: 12px;
      margin-bottom: 8px;
    }

    .label {
      display: block;
      color: #6b7280;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      margin-bottom: 2px;
    }

    .value {
      color: #111827;
      word-wrap: break-word;
    }

    .agreement p {
      margin: 0 0 7px;
    }

    .check-item {
      margin: 0 0 6px;
    }

    .signature-box {
      border: 1px solid #d1d5db;
      border-radius: 6px;
      min-height: 130px;
      text-align: center;
      padding: 8px;
    }

    .signature-box img {
      max-width: 100%;
      max-height: 150px;
      display: block;
      margin: 0 auto;
    }


    .footer {
      margin-top: 8px;
      color: #6b7280;
      font-size: 10px;
      text-align: right;
    }
  </style>
</head>
<body>
  <main class="page">
    <header class="header">
      <h1>Signed Boarding Agreement</h1>
      <p>Generated on {{ now()->format('M j, Y g:i A') }}</p>
    </header>

    <section class="section">
      <h2>Appointment Information</h2>
      <div class="row">
        <div class="col">
          <span class="label">Pet Name</span>
          <div class="value">{{ $petNames->isNotEmpty() ? $petNames->join(', ') : 'N/A' }}</div>
        </div>
        <div class="col">
          <span class="label">Owner Name</span>
          <div class="value">{{ $ownerProfileName !== '' ? $ownerProfileName : $ownerDisplayName }}</div>
        </div>
        <div class="col">
          <span class="label">Check-in Date</span>
          <div class="value">{{ $checkinDateLabel }}</div>
        </div>
        <div class="col">
          <span class="label">Pickup Date</span>
          <div class="value">{{ $pickupDateLabel }}</div>
        </div>
      </div>
    </section>

    <section class="section agreement">
      <h2>Boarding Agreement</h2>
      <p><strong>Release and waiver:</strong> I understand boarding activities carry inherent risks and that The Sunshine Spot is not liable for injury, illness, or loss during my pet's stay, except where prohibited by law.</p>
      <p><strong>Treatment authorization:</strong> I authorize the facility to arrange reasonable care and treatment for my pet when needed during boarding.</p>
      <p><strong>Emergency care consent:</strong> If I cannot be reached promptly, I consent to emergency veterinary care deemed necessary for my pet's welfare.</p>
      <p><strong>Parasite and flea/tick acknowledgement:</strong> I understand that if fleas, ticks, or parasites are detected during boarding, necessary treatment may be administered and related fees may apply.</p>
      <p><strong>Facility policy acknowledgement:</strong> I acknowledge and agree to follow the facility's boarding policies, pickup requirements, cancellation policy, and payment terms.</p>
    </section>

    <section class="section">
      <h2>Owner Acknowledgement</h2>
      <p class="check-item">[{{ $agreementAccepted ? 'X' : ' ' }}] I have read and agree to the boarding agreement.</p>
      <p class="check-item">[{{ $vetAuthorized ? 'X' : ' ' }}] I authorize the facility to seek veterinary treatment if needed.</p>

      <div class="row" style="margin-top: 8px;">
        <div class="col">
          <span class="label">Owner Full Name</span>
          <div class="value">{{ $ownerDisplayName }}</div>
        </div>
        <div class="col">
          <span class="label">Signed Date and Time</span>
          <div class="value">{{ $signedAtLabel }}</div>
        </div>
      </div>
    </section>

    <section class="section">
      <h2>Signature</h2>
      <div class="signature-box">
        @if ($signatureData !== '')
          <img src="{{ $signatureData }}" alt="Owner Signature" />
        @elseif (!empty($signatureRenderFallbackMessage ?? null))
          <div class="value">{{ $signatureRenderFallbackMessage }}</div>
        @else
          <div class="value">Signature not available.</div>
        @endif
      </div>
    </section>

    <p class="footer">Appointment ID: {{ $appointment->id }} | Status: {{ ucfirst((string) $appointment->status) }}</p>
  </main>
</body>
</html>
