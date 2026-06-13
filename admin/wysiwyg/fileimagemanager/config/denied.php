<?php
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title>Přístup odepřen</title>
<style>
    html, body { height: 100%; margin: 0; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        background: #f5f5f5;
        color: #333;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
    }
    .box {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        padding: 32px 36px;
        max-width: 480px;
        text-align: center;
    }
    .box h1 {
        margin: 0 0 12px;
        font-size: 20px;
        color: #c0392b;
    }
    .box p {
        margin: 0 0 20px;
        line-height: 1.5;
    }
    .box button {
        background: #2c7be5;
        color: #fff;
        border: 0;
        padding: 10px 20px;
        font-size: 14px;
        border-radius: 4px;
        cursor: pointer;
    }
    .box button:hover { background: #1a68d1; }
</style>
</head>
<body>
    <div class="box">
        <h1>Přístup odepřen</h1>
        <p>Nejste přihlášen(a) nebo vaše přihlášení vypršelo. Pro správu souborů se prosím znovu přihlaste do administrace.</p>
        <button type="button" id="rfmCloseBtn">Zavřít</button>
    </div>
    <script src="denied.js"></script>
</body>
</html>
