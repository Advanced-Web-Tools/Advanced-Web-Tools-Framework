<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ WEB_NAME }} | Server error</title>

    <style>

        body {
            background: #2b2b2b;
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
            max-width: 500px;
            padding: 40px;

            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }

        .error-code {
            font-size: 70px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
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

    </style>
</head>

<body>

<div class="container">

    <div class="error-code">500</div>

    <h1>Server Error</h1>

    <p>
        Something unexpected happened on our side.<br>
        The issue has likely been logged and we’re looking into it.
    </p>

    <div class="buttons">
        <button onclick="history.back()">Go Back</button>
        <button onclick="location.reload()" class="secondary">Retry</button>
    </div>

    <div class="footer">
        {{ WEB_NAME }} • If this keeps happening please contact support
    </div>

</div>

</body>
</html>