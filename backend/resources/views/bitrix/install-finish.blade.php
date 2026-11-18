<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Installing…</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
</head>
<body style="font-family: system-ui, sans-serif; padding: 2rem;">
    <p id="status">Finishing installation…</p>
    <script>
        // Bitrix24 keeps the app "not installed" (no events, install page on
        // every open) until this call is made by an admin.
        BX24.init(function () {
            BX24.installFinish();
            document.getElementById('status').textContent = 'Installation complete.';
        });
    </script>
</body>
</html>
