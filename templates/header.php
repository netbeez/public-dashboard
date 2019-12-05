<?php
/**
 * The header template
 */


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Network Status Dashboard - <?php echo get_page_title($name); ?></title>

    <meta http-equiv="refresh" content="<?php echo get_refresh_interval(false, true); ?>">

    <link type="text/css" href="styles/font-awesome.min.css" rel="stylesheet" />
    <link type="text/css" href="styles/fonts.css" rel="stylesheet" />
    <link type="text/css" href="styles/base.css" rel="stylesheet" />
    <link type="text/css" href="styles/graph.css" rel="stylesheet" />
    <link type="text/css" href="styles/styles.css" rel="stylesheet" />

    <script type="text/javascript" src="js/lib/jquery-3.1.1.js"></script>
    <script type="text/javascript" src="js/lib/d3.v3.js"></script>
    <script type="text/javascript" src="js/lib/d3.tip.v0.6.3.js"></script>

    <!--<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400|Roboto:400,400i,900" rel="stylesheet">-->

</head>
<body>

<header class="main-header">

    <div class="header-wrap">
        <div class="header-content three-col-x2">
            <div class="home-icon-container">
                <a href="<?php echo SITE_URL; ?>">
                    <span class="fa-stack fa-lg">
                      <i class="fa fa-circle fa-stack-2x"></i>
                      <i class="fa fa-home fa-stack-1x fa-inverse"></i>
                    </span>
                </a>
            </div>
            <div class="title-container">
                <h2><?php echo get_page_title($name); ?></h2>
                <div class="description"><?php echo $description; ?></div>
            </div>
        </div>

        <div class="header-content">
            <h6>Choose your time period: <span id="loading"></span></h6>
            <form action="" method="post">
                <div class="button-group">
                    <button type="submit" name="submit" value="<?php echo day_time_window; ?>" class="button" id="interval-one-day">1 Day</button>
                    <button type="submit" name="submit" value="<?php echo week_time_window; ?>" class="button" id="interval-one-week">1 Week</button>
                    <button type="submit" name="submit" value="<?php echo month_time_window; ?>" class="button" id="interval-one-month">1 Month</button>
                </div>
            </form>
        </div>

    </div>

</header>

