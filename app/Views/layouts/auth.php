<?php /** Auth layout — for login page only. */
use Insophinia\POS\Lib\Settings;
$shopName = Settings::get('shop_name', 'INSOPHINIA');
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#000000">
    <title><?= e($title ?? 'INSOPHINIA POS v7') ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg">
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <?= $content ?>
    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/theme.js"></script>
</body>
</html>
