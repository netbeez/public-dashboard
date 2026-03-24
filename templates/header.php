<?php
$pageTitle = get_page_title($name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Network Status Dashboard - <?php echo htmlspecialchars($pageTitle); ?></title>

    <meta http-equiv="refresh" content="<?php echo get_refresh_interval(false, true); ?>">

    <link type="text/css" href="styles/font-awesome.min.css" rel="stylesheet" />
    <link type="text/css" href="styles/fonts.css" rel="stylesheet" />
    <link type="text/css" href="styles/base.css" rel="stylesheet" />
    <link type="text/css" href="styles/graph.css" rel="stylesheet" />
    <link type="text/css" href="styles/styles.css" rel="stylesheet" />

    <script type="text/javascript" src="js/lib/jquery-3.1.1.js"></script>
    <script type="text/javascript" src="js/lib/d3.v3.js"></script>
    <script type="text/javascript" src="js/lib/d3.tip.v0.6.3.js"></script>
</head>
<body class="<?php echo htmlspecialchars(get_body_class()); ?>">

<header class="main-header">
    <?php $isDebugView = isset($_GET['view']) && $_GET['view'] === 'debug'; ?>
    <div class="header-wrap">
        <div class="header-topline">
            <a class="brand-link" href="<?php echo htmlspecialchars(SITE_URL); ?>">
                <span class="brand-mark">
                    <span class="fa-stack fa-lg">
                        <i class="fa fa-circle fa-stack-2x"></i>
                        <i class="fa fa-home fa-stack-1x fa-inverse"></i>
                    </span>
                </span>
                <span class="brand-copy">
                    <span class="brand-eyebrow"><?php echo htmlspecialchars(COMPANY_NAME); ?></span>
                    <span class="brand-title">Public network dashboard</span>
                </span>
            </a>
            <div class="header-status">
                <span class="header-status-label">Auto refresh</span>
                <strong><?php echo (int) get_refresh_interval(false, false); ?> min</strong>
                <a class="debug-toggle" href="<?php echo $isDebugView ? htmlspecialchars(SITE_URL) : '?view=debug'; ?>">
                    <?php echo $isDebugView ? 'Back to dashboard' : 'Debug timings'; ?>
                </a>
            </div>
        </div>

        <div class="header-content">
            <div class="title-container">
                <p class="eyebrow">Operational telemetry</p>
                <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
                <div class="description"><?php echo htmlspecialchars($description); ?></div>
            </div>

            <div class="header-controls">
                <h6>Time period <span id="loading"></span></h6>
                <form action="" method="post">
                    <div class="button-group">
                        <button type="submit" name="submit" value="<?php echo day_time_window; ?>" class="button" id="interval-one-day">1 Day</button>
                        <button type="submit" name="submit" value="<?php echo week_time_window; ?>" class="button" id="interval-one-week">1 Week</button>
                        <button type="submit" name="submit" value="<?php echo month_time_window; ?>" class="button" id="interval-one-month">1 Month</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>
