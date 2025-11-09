<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تأكيد البريد الإلكتروني</title>
  <style>
    body {
      font-family: "Cairo", sans-serif;
      text-align: center;
      background: #f9fafb;
      margin-top: 10%;
    }
    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      display: inline-block;
      padding: 40px;
    }
    h1 {
      color: #16a34a;
    }
    p {
      color: #555;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="card">
    @if ($status === 'success')
      <h1> تم تأكيد بريدك الإلكتروني بنجاح</h1>
      <p>يمكنك الآن تسجيل الدخول إلى حسابك.</p>
    @else
      <h1 style="color:#dc2626;"> رابط التحقق غير صالح</h1>
      <p>ربما انتهت صلاحية الرابط أو تم استخدامه مسبقًا.</p>
    @endif
  </div>
</body>
</html>