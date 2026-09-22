<?php

// SERVER / PHP TIMEZONE
$serverTimezone = date_default_timezone_get();
$serverTime = new DateTime('now');


// UTC TIME
$utcTime = new DateTime('now', new DateTimeZone('UTC'));


// BROWSER TIMEZONE WILL BE FILLED BY JAVASCRIPT
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Timezone Test</title>
</head>
<body>

<h1>Timezone Diagnostic</h1>

<hr>

<h2>Server / PHP</h2>

<p>
    <strong>PHP Timezone:</strong>
    <?= htmlspecialchars($serverTimezone) ?>
</p>

<p>
    <strong>Server Date/Time:</strong>
    <?= $serverTime->format('Y-m-d H:i:s P T') ?>
</p>

<p>
    <strong>UTC Date/Time:</strong>
    <?= $utcTime->format('Y-m-d H:i:s P T') ?>
</p>

<p>
    <strong>Server UTC Offset:</strong>
    <?= $serverTime->format('P') ?>
</p>

<hr>

<h2>Browser / User</h2>

<p>
    <strong>Browser Timezone:</strong>
    <span id="browser-timezone">Detecting...</span>
</p>

<p>
    <strong>Browser Date/Time:</strong>
    <span id="browser-time">Detecting...</span>
</p>

<script>

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const currentTime = new Date();

document.getElementById('browser-timezone').textContent = timezone;

document.getElementById('browser-time').textContent =
    currentTime.toLocaleString();

</script>

</body>
</html>