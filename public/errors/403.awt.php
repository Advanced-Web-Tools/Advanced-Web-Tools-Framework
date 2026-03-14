<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ WEB_NAME }} | Access Forbidden</title>

    <style>

        body {
            background-color: #222;
            color: #fff;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            text-align: center;
            max-width: 520px;
            padding: 40px;
            background: #2b2b2b;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }

        .error-code {
            font-size: 90px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
            letter-spacing: 3px;
        }

        .icon {
            font-size: 42px;
            margin-bottom: 10px;
            opacity: 0.8;
        }

        h1 {
            margin: 0;
            font-size: 26px;
        }

        p {
            color: #bbb;
            margin-top: 10px;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.25s;
            font-size: 14px;
        }

        button:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        .secondary {
            background: transparent;
            border: 1px solid #555;
        }

        .secondary:hover {
            background: #333;
        }

        .footer {
            margin-top: 25px;
            font-size: 12px;
            color: #777;
        }

        a {
            text-decoration: none;
        }

    </style>
</head>

<body>

<div class="container">

    <div class="error-code">403</div>
    <div class="icon">🔒</div>

    <h1>Access Forbidden</h1>

    <p>
        You don’t have permission to access this resource.<br>
        If you believe this is a mistake, please contact the administrator.
    </p>

    <div class="buttons">
        <button onclick="history.back()">Go Back</button>
        <a href="{{ HOSTNAME }}"><button class="secondary">Home Page</button></a>
    </div>

    <div class="footer">
        {{ WEB_NAME }} • Permission required
    </div>

</div>

</body>
</html>