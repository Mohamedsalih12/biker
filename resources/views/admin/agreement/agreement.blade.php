<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agreement Draft — {{ $booking->booking_code }}</title>
    <style>
        :root {
            --primary: #1a365d;
            --accent: #2b6cb0;
            --border: #cbd5e0;
            --bg-light: #f7fafc;
            --text: #1a202c;
            --muted: #4a5568;
        }

        * { box-sizing: border-box; }
        body, body * { margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: var(--text);
            background: #edf2f7;
            padding: 2rem 1rem;
        }
        .document {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 2.5rem 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
            overflow-wrap: anywhere;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 1.25rem;
            margin-bottom: 1.75rem;
        }
        .header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .intro { margin-bottom: 1.75rem; font-size: 0.95rem; }
        .intro strong { color: var(--primary); }
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .party-box {
            min-width: 0;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 1.1rem 1.25rem;
            background: var(--bg-light);
        }
        .party-box h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--accent);
            margin-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.35rem;
        }
        .party-box p { font-size: 0.92rem; margin-bottom: 0.35rem; }
        .party-box .label { color: var(--muted); font-size: 0.8rem; }
        .section { margin-bottom: 1.75rem; }
        .section h2 {
            font-size: 1.05rem;
            color: var(--primary);
            border-left: 4px solid var(--accent);
            padding-left: 0.75rem;
            margin-bottom: 0.85rem;
        }
        .vehicle-details, .rental-terms { list-style: none; }
        .vehicle-details li, .rental-terms li {
            padding: 0.4rem 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 0.93rem;
        }
        .vehicle-details li:last-child, .rental-terms li:last-child { border-bottom: none; }
        .vehicle-details strong, .rental-terms strong {
            display: inline-block;
            min-width: 160px;
            color: var(--muted);
        }
        .terms-list { list-style: none; counter-reset: term; }
        .terms-list li {
            position: relative;
            padding: 0.65rem 0 0.65rem 2.2rem;
            border-bottom: 1px solid #edf2f7;
            font-size: 0.92rem;
        }
        .terms-list li:last-child { border-bottom: none; }
        .terms-list li::before {
            counter-increment: term;
            content: counter(term);
            position: absolute;
            left: 0;
            top: 0.65rem;
            width: 1.5rem;
            height: 1.5rem;
            background: var(--primary);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .terms-list li strong { color: var(--primary); }
        .signature-section {
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--border);
        }
        .signature-section p { margin-bottom: 1.5rem; font-size: 0.93rem; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .sig-box { border: 1px dashed var(--border); border-radius: 6px; padding: 1.25rem; min-height: 110px; }
        .sig-box h4 { font-size: 0.85rem; color: var(--muted); margin-bottom: 2.5rem; }
        .witnesses { margin-top: 1.5rem; }
        .witnesses h4 { font-size: 0.9rem; color: var(--primary); margin-bottom: 0.75rem; }
        .witness-line {
            border-bottom: 1px solid #a0aec0;
            min-height: 2rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .footer-note { margin-top: 2rem; font-size: 0.8rem; color: var(--muted); text-align: center; }
        .payment-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; margin-bottom: 1rem; }
        .save-button { background: var(--primary); color: #fff; border: 0; border-radius: 4px; padding: 0.65rem 1.5rem; font: inherit; cursor: pointer; }
        .action-button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; text-decoration: none; }
        .back-button { margin-right: auto; background: var(--bg-light); color: var(--primary); border: 1px solid var(--border); }
        .back-button:hover { color: #fff; }
        .save-button:hover { background: var(--accent); }
        .payment-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; margin-top: 0.75rem; }
        .payment-field { display: block; min-width: 0; font-size: 0.93rem; }
        .payment-field label { display: block; color: var(--muted); font-weight: 600; margin-bottom: 0.25rem; }
        .payment-field input, .payment-field select { width: 100%; max-width: 320px; min-height: 44px; padding: 0.5rem 0.65rem; border: 1px solid var(--border); border-radius: 4px; background: #fff; color: var(--text); font: inherit; }
        .save-button:focus-visible, .payment-field input:focus-visible, .payment-field select:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
        .payment-feedback { padding: 0.75rem; margin-bottom: 1rem; background: var(--bg-light); border: 1px solid var(--border); border-radius: 4px; }
        .payment-error { display: block; color: #9b1c1c; font-size: 0.85rem; }
        @media (max-width: 600px) {
            .parties, .signatures { grid-template-columns: 1fr; }
            .payment-form { grid-template-columns: 1fr; }
            .document { padding: 1.5rem 1rem; }
        }
        @media print {
            .payment-toolbar, .payment-feedback, .payment-form { display: none; }
            body { background: #fff; padding: 0; }
            .document { box-shadow: none; padding: 1.5rem; }
            .party-box, .sig-box, .terms-list li { break-inside: avoid; }
            .section h2 { break-after: avoid; }
        }
    </style>
    @if ($isPdf ?? false)
        <style>
            @page { margin: 30px; }
            html { font-size: 12px; }
            body { font-family: "DejaVu Sans", sans-serif; color: #1a202c; background: #fff; padding: 0; }
            .document { max-width: none; margin: 0; padding: 0; box-shadow: none; }
            .header { border-color: #1a365d; }
            .header h1, .intro strong, .section h2, .terms-list li strong, .witnesses h4 { color: #1a365d; }
            .parties, .signatures { display: table; width: 100%; table-layout: fixed; border-spacing: 8px 0; }
            .party-box, .sig-box { display: table-cell; width: 50%; vertical-align: top; border-color: #cbd5e0; page-break-inside: avoid; overflow-wrap: break-word; }
            .party-box { background: #f7fafc; }
            .party-box h3 { color: #2b6cb0; border-color: #cbd5e0; }
            .party-box .label, .vehicle-details strong, .rental-terms strong, .sig-box h4, .witness-line, .footer-note { color: #4a5568; }
            .section h2 { border-color: #2b6cb0; page-break-after: avoid; }
            .terms-list { list-style: decimal; padding-left: 1.5rem; }
            .terms-list li { padding-left: 0.4rem; page-break-inside: avoid; }
            .terms-list li::before { content: none; display: none; }
            .signature-section { border-color: #cbd5e0; }
        </style>
    @endif
</head>
<body>
    <main class="document">
        @if (! ($isPdf ?? false))
            <div class="payment-toolbar">
                <a class="save-button action-button back-button" href="{{ route('xpress.bookings.index') }}">Back</a>
                <button class="save-button action-button" type="button" onclick="window.print()">Print</button>
                <a class="save-button action-button" href="{{ route('xpress.bookings.agreement.pdf', $booking) }}">Download as PDF</a>
                @can('manage-payments')
                    <button class="save-button action-button" type="submit" form="agreement-payment">Pay</button>
                @endcan
            </div>
        @if (session('message'))
            <p class="payment-feedback" role="status">{{ session('message') }}</p>
        @endif
        @if ($errors->any())
            <div class="payment-feedback" role="alert">
                <p>The payment was not saved. Please correct the following:</p>
                @foreach ($errors->all() as $error)
                    <p class="payment-error">{{ $error }}</p>
                @endforeach
            </div>
        @endif
        @endif
        <div class="header">
            <h1>Electric Vehicle Rental Agreement</h1>
            <p>Draft · Booking {{ $booking->booking_code }}</p>
        </div>

        <p class="intro">
            This Electric Scooter Rental Agreement ("Agreement") is executed on this
            <strong>{{ now()->format('d-M-Y') }}</strong> at
            <strong>Adirampattinam, Thanjavur District, Tamil Nadu (Pincode: 614701)</strong>.
        </p>

        <p class="intro" style="margin-bottom: 0.5rem;"><strong>BY AND BETWEEN:</strong></p>

        <div class="parties">
            <div class="party-box">
                <h3>Lessor (Vehicle Owner)</h3>
                <p><span class="label">Name:</span><br><strong>Mohamed Salih</strong></p>
                <p><span class="label">Aadhaar No / Govt ID:</span><br>323326041254</p>
                <p><span class="label">Address:</span><br>Adirampattinam, Tamil Nadu – 614701</p>
                <p><span class="label">Phone / WhatsApp:</span><br>+91 9994518970</p>
            </div>

            <div class="party-box">
                <h3>Lessee (NRI Renter)</h3>
                <p><span class="label">Full Name:</span><br>{{ $booking->customer->name }}</p>
                <p><span class="label">Aadhaar No.:</span><br>{{ $booking->customer->aadhaar ?? '—' }}</p>
                <p><span class="label">Local Address in Adirampattinam:</span><br>{{ $booking->customer->address ?? '—' }}</p>
                <p><span class="label">Contact No (WhatsApp / Local SIM):</span><br>{{ $booking->customer->phone ?? '—' }}</p>
            </div>
        </div>

        <section class="section">
            <h2>1. Vehicle Details &amp; Handover Condition</h2>
            <ul class="vehicle-details">
                <li><strong>Make &amp; Model:</strong> {{ $booking->vehicle->brand }} - {{ $booking->vehicle->model }}</li>
                <li><strong>Registration Number:</strong> {{ $booking->vehicle->registration_number }}</li>
                <li><strong>Odometer:</strong> {{ $booking->start_odometer }} km</li>
                <li><strong>Accessories Included:</strong> 1 Portable Charger, 2 Keys, 1 Helmet, Vehicle RC Copy, Commercial Insurance Copy</li>
            </ul>
        </section>

        <section class="section">
            <h2>2. Rental Period, Deposit &amp; Payment Terms</h2>
            <ul class="rental-terms">
                <li><strong>Rental Term:</strong> From {{ $booking->start_date->format('d-M-Y') }} to {{ $booking->end_date->format('d-M-Y') }}</li>
                <li><strong>{{ ucfirst($booking->rental_type) }} Rental Fee:</strong> ₹{{ number_format((float) $booking->rate_per_unit, 2) }} / {{ ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month'][$booking->rental_type] }} (payable in advance upon vehicle handover)</li>
            </ul>
            @if (! ($isPdf ?? false))
            @can('manage-payments')
                <form id="agreement-payment" class="payment-form" method="POST" action="{{ route('xpress.bookings.agreement.payments.store', $booking) }}">
                    @csrf
                    <div class="payment-field">
                        <label for="amount_received">Amount Received (₹)</label>
                        <input type="number" id="amount_received" name="amount_received" value="{{ old('amount_received') }}" min="0.01" max="9999999999.99" step="0.01" required @error('amount_received') aria-invalid="true" aria-describedby="amount-received-error" @enderror>
                        @error('amount_received')
                            <span id="amount-received-error" class="payment-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="payment-field">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" required @error('payment_method') aria-invalid="true" aria-describedby="payment-method-error" @enderror>
                            <option value="">Select payment method</option>
                            @foreach (\App\Models\Payment::METHODS as $method)
                                <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method === 'upi' ? 'UPI' : ucfirst($method) }}</option>
                            @endforeach
                        </select>
                        @error('payment_method')
                            <span id="payment-method-error" class="payment-error">{{ $message }}</span>
                        @enderror
                    </div>
                </form>
            @endcan
            @endif
        </section>

        <section class="section">
            <h2>3. Terms &amp; Conditions</h2>
            <ol class="terms-list">
                <li>
                    <strong>Prohibited Inter-District Travel:</strong>
                    The vehicle shall NOT be driven beyond a 30 km radius of Adirampattinam
                    (e.g., Thanjavur city, Tiruchirappalli, Erode) without prior written consent from the Lessor.
                </li>
                <li>
                    <strong>Sub-leasing Ban:</strong>
                    The Lessee shall not sub-let, assign, or allow third parties or unlicensed persons to drive the vehicle.
                </li>
                <li>
                    <strong>Safe Parking:</strong>
                    The vehicle must be parked in a secure, covered area overnight to protect it from weather and coastal saltwater exposure.
                </li>
                <li>
                    <strong>Traffic Penalties &amp; Fines:</strong>
                    The Lessee is 100% responsible for all traffic fines, e-challans (e.g., helmet violation, overspeeding), or toll charges incurred during the rental tenure.
                </li>
                <li>
                    <strong>Minor damage</strong> (scratches, broken mirrors, lever snaps):
                    Repair costs will be deducted from the security deposit or paid directly by the Lessee.
                </li>
                <li>
                    <strong>Major accidents / Third-Party Damage:</strong>
                    Covered under Commercial Insurance. However, the Lessee shall pay the insurance claim processing fee
                    (compulsory deductible ~₹1,500–₹2,500) plus rental income loss for the repair duration.
                </li>
                <li>
                    <strong>Theft or Total Loss:</strong>
                    In the event of theft or total loss due to negligence (e.g., leaving keys in the ignition),
                    the Lessee shall compensate the Lessor for the unrecovered market value of the vehicle.
                </li>
                <li>
                    This Agreement is governed by the laws of India and the state of Tamil Nadu.
                    Any disputes arising out of this Agreement shall fall under the jurisdiction of the
                    Civil Courts in <strong>Pattukkottai / Thanjavur District</strong>.
                </li>
            </ol>
        </section>

        <div class="signature-section">
            <p>
                <strong>IN WITNESS WHEREOF</strong>, the Parties have signed this Agreement on the date first mentioned above.
            </p>
            <div class="signatures">
                <div class="sig-box"><h4>LESSOR (Owner Signature)</h4></div>
                <div class="sig-box"><h4>LESSEE (Renter Signature)</h4></div>
            </div>
            <div class="witnesses">
                <h4>Witnesses</h4>
                <div class="witness-line">Witness 1 (Local Contact Name &amp; Phone):</div>
                <div class="witness-line">Witness 2 (Name &amp; Phone):</div>
            </div>
        </div>

        <p class="footer-note">Electric Vehicle Rental Agreement · Adirampattinam, Thanjavur District, Tamil Nadu</p>
    </main>
</body>
</html>
