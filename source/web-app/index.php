<?php
// Retrieve IMDSv2 Token
$token_ch = curl_init('http://169.254.169.254/latest/api/token');
curl_setopt($token_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($token_ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($token_ch, CURLOPT_HTTPHEADER, array('X-aws-ec2-metadata-token-ttl-seconds: 21600'));
curl_setopt($token_ch, CURLOPT_TIMEOUT, 2);
$token = curl_exec($token_ch);
curl_close($token_ch);

// Function to fetch metadata using IMDSv2 token
function get_metadata($path, $token) {
    if (!$token) {
        return 'N/A';
    }
    $ch = curl_init('http://169.254.169.254/latest/meta-data/' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-aws-ec2-metadata-token: ' . $token));
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: 'Unavailable';
}

$instance_id = get_metadata('instance-id', $token);
$az = get_metadata('placement/availability-zone', $token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Web App</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background-color: #0b1320;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #172236;
            border-radius: 12px;
            padding: 48px 64px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
            max-width: 520px;
            width: 90%;
        }
        h1 {
            color: #38bdf8;
            font-size: 2.2rem;
            margin-bottom: 24px;
            font-weight: 700;
        }
        .label {
            color: #94a3b8;
            font-size: 1.05rem;
            margin-top: 18px;
            margin-bottom: 6px;
        }
        .value {
            color: #a855f7;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>App Live &amp; Balanced</h1>
        <div class="label">Served from Instance ID:</div>
        <div class="value"><?php echo htmlspecialchars($instance_id); ?></div>
        <div class="label">Availability Zone:</div>
        <div class="value"><?php echo htmlspecialchars($az); ?></div>
    </div>
</body>
</html>
