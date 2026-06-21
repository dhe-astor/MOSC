<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Official Receipt - {{ $receipt_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .receipt-container {
            border: 2px solid #5d0f19;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            background: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #5d0f19;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .diocese-title {
            font-size: 18px;
            font-weight: bold;
            color: #5d0f19;
            text-transform: uppercase;
            margin: 0 0 5px 0;
        }
        .church-title {
            font-size: 16px;
            font-weight: 500;
            margin: 0 0 5px 0;
        }
        .receipt-title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 20px 0 10px 0;
            text-align: center;
            color: #5d0f19;
            text-transform: uppercase;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 6px 0;
        }
        .meta-label {
            font-weight: bold;
            color: #555;
            width: 150px;
        }
        .meta-value {
            border-bottom: 1px dotted #ccc;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .details-table th {
            background-color: #5d0f19;
            color: #fff;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 13px;
        }
        .details-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }
        .amount-big {
            font-size: 20px;
            font-weight: bold;
            color: #5d0f19;
        }
        .footer-signatures {
            margin-top: 60px;
            width: 100%;
        }
        .signature-box {
            text-align: center;
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .seal-box {
            border: 2px dashed #999;
            width: 100px;
            height: 100px;
            margin: 0 auto;
            line-height: 100px;
            color: #999;
            font-size: 11px;
            font-weight: bold;
            border-radius: 50%;
            text-transform: uppercase;
        }
        .notice {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #777;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="header">
        <div class="diocese-title">{{ $diocese_name }}</div>
        <div class="church-title">{{ $church_name }}</div>
        <div style="font-size: 12px; color: #666;">{{ $church_address }}</div>
    </div>

    <div class="receipt-title">Official Receipt</div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Receipt Number:</td>
            <td class="meta-value" style="font-weight: bold; color: #5d0f19;">{{ $receipt_number }}</td>
            <td class="meta-label" style="text-align: right; padding-right: 15px;">Date:</td>
            <td class="meta-value">{{ $receipt_date }}</td>
        </tr>
        <tr>
            <td class="meta-label">Received From:</td>
            <td class="meta-value" colspan="3">{{ $payer_name }}</td>
        </tr>
        @if($payer_email || $payer_phone)
        <tr>
            <td class="meta-label">Contact:</td>
            <td class="meta-value" colspan="3">
                {{ $payer_email ?? 'N/A' }} {{ $payer_phone ? ' / ' . $payer_phone : '' }}
            </td>
        </tr>
        @endif
    </table>

    <table class="details-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right; width: 150px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($lines) && is_array($lines) && count($lines) > 0)
                @foreach($lines as $line)
                <tr>
                    <td>
                        {{ $line['description'] }}
                    </td>
                    <td style="text-align: right;">
                        {{ $currency }} {{ number_format($line['amount'], 2) }}
                    </td>
                </tr>
                @endforeach
                <tr style="font-weight: bold; background-color: #fafafa;">
                    <td>Grand Total</td>
                    <td style="text-align: right;" class="amount-big">
                        {{ $currency }} {{ number_format($amount, 2) }}
                    </td>
                </tr>
            @else
                <tr>
                    <td>
                        <strong>{{ $description }}</strong>
                        @if($payment_reference)
                            <br><span style="font-size: 12px; color: #666;">Ref: {{ $payment_reference }}</span>
                        @endif
                    </td>
                    <td style="text-align: right;" class="amount-big">
                        {{ $currency }} {{ number_format($amount, 2) }}
                    </td>
                </tr>
            @endif
            <tr>
                <td colspan="2" style="background-color: #fafafa; font-size: 12px;">
                    <strong>Payment Method:</strong> {{ strtoupper($payment_method) }}
                </td>
            </tr>
        </tbody>
    </table>

    <table class="footer-signatures" style="width: 100%;">
        <tr>
            <td class="signature-box" style="width: 45%;">
                <div class="seal-box">Church Seal</div>
            </td>
            <td style="width: 10%;"></td>
            <td class="signature-box" style="width: 45%; vertical-align: bottom;">
                <div class="signature-line">
                    Authorized Signature<br>
                    <span style="font-size: 11px; font-weight: normal; color: #555;">Issued By: {{ $issued_by }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="notice">
        Thank you for your contribution. This is a secure official receipt issued by MSOC Europe.
    </div>
</div>

</body>
</html>
