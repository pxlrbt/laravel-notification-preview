<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Render failed</title>
</head>
<body style="margin:0;padding:32px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;line-height:1.6;color:#991b1b;background:#fef2f2;">
    <p style="margin:0 0 4px;font-weight:700;font-size:15px;">{{ class_basename($exception) }}</p>
    <p style="margin:0 0 20px;font-size:14px;">{{ $exception->getMessage() }}</p>
    <p style="margin:0 0 20px;color:#7f1d1d;">{{ $exception->getFile() }}:{{ $exception->getLine() }}</p>
    <p style="margin:0 0 8px;color:#7f1d1d;">Register a variation or a resolver for this notification to give it usable preview data.</p>
    <pre style="margin:0;padding:16px;overflow:auto;background:#fff;border:1px solid #fecaca;border-radius:8px;color:#7f1d1d;">{{ $exception->getTraceAsString() }}</pre>
</body>
</html>
