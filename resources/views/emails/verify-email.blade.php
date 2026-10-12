<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify your email address</title></head>
<body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937">
  <main style="max-width:600px;margin:32px auto;padding:32px;background:#fff;border-radius:12px">
    <p style="font-size:12px;font-weight:bold;letter-spacing:.12em;color:#2563eb">SHOPLINE</p>
    <h1 style="font-size:24px">Verify your email address</h1>
    <p>Hello {{ $data['name'] ?? 'there' }},</p><p>Verify your email address.</p>
    @if(!empty($data['order_number']))<p>Order: <strong>{{ $data['order_number'] }}</strong></p>@endif
    @if(!empty($data['tracking_number']))<p>Tracking number: <strong>{{ $data['tracking_number'] }}</strong></p>@endif
    @if(!empty($data['tracking_url']))<p><a href="{{ $data['tracking_url'] }}">Track your order</a></p>@endif
    @if(!empty($data['url']))<p><a href="{{ $data['url'] }}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px">Continue</a></p>@endif
    @if(!empty($data['message']))<p>{{ $data['message'] }}</p>@endif
    <p style="margin-top:32px;color:#6b7280;font-size:12px">This is an automated message from ShopLine.</p>
  </main>
</body>
</html>
