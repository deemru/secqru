<?php

    require( "secqru.config.php" );

    ignore_user_abort( true );
    set_time_limit( 0 );
    date_default_timezone_set( "Europe/Moscow" );
    mb_internal_encoding( "UTF-8" );

    if( defined( "DEBUG" ) )
    {
        ini_set( 'display_errors', TRUE );
        ini_set( 'display_startup_errors', TRUE );
        ini_set( "log_errors", 1 );
        ini_set( "error_log", "./log/php_error.log" );
        error_reporting( E_ALL );
    }

    // BASIC SERIALIZATION BY IP
    require( "include\secqru_flock.php" );
    $ipblock = new secqru_flock( "./log/ip/{$_SERVER["REMOTE_ADDR"]}.lock" );
    $ipblock->open();

    if( defined( "DEBUG" ) )
    {
        $debug_log = new secqru_flock( "./log/{$_SERVER["REMOTE_ADDR"]}.log" );
        $debug_log->append( date("Y.m.d H:i:s | ").$_SERVER['REQUEST_URI'].PHP_EOL );
    }

    if( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == "on" )
        define( "SECQRU_ADDR", "https://".$_SERVER["HTTP_HOST"].SECQRU_ROOT );
    else
        define( "SECQRU_ADDR", "http://".$_SERVER["HTTP_HOST"].SECQRU_ROOT );

    require( "include\secqru_worker.php" );
    $w = new secqru_worker();

    $app = $w->get_app( SECQRU_ROOT );
    // FILTER APPS
    switch( $app )
    {
        case "tiklan":
            break;
        default:
            $app = "";
    }

    $sw_app = $w->get_dns( "sw_app", "" );
    // FILTER APPS
    switch( $sw_app )
    {
        case "tiklan":
            break;
        case SECQRU_SITE:
            $sw_app = "";
            break;
        default:
            unset( $sw_app );
    }

    if( isset( $sw_app ) && $sw_app != $app )
    {
        header( "Location: ".SECQRU_ADDR.$sw_app );
        exit;
    }
    else
        unset( $sw_app );

    if( $w->link_exists() )
    {
        if( $w->link_load( SECQRU_PASS ) )
        {
            $w->log( "link loaded", 7 );
        }
        else
        {
            $w->log( "bad link", 2 );
            $_POST["reset"] = 1;
        }
    }

    if( $w->get_set( "link" ) )
    {
        unset( $_POST["link"] );
        if( isset( $_POST["gamma"] ) )
            unset( $_POST["gamma"] );
        // filter $_POST content
        $app = $app ? "$app/link/" : "link/";
        $link = SECQRU_ADDR.$app.$w->link_get( SECQRU_PASS );
        header( "Location: $link" );
        exit;
    }

    if( $w->get_set( "sw_light" ) )
    {
        $is_lite = 1;
        setcookie( "gamma", 1 );
        $w->log( "switch to light", 7 );
    }
    else if( $w->get_set( "sw_night" ) )
    {
        $is_lite = 0;
        setcookie( "gamma", 0 );
        $w->log( "switch to night", 7 );
    }
    else
    {
        if( isset( $_COOKIE["gamma"] ) )
        {
            $is_lite = intval( $_COOKIE["gamma"] );
            $is_lite = $is_lite ? 1 : 0;
        }
        else
            $is_lite = $w->get_int( "gamma", 1, 0, 1 );
    }

  $color_back = $is_lite ? 'E0E0D0' : '404840';
  $color_lite = $is_lite ? 'FFFFFF' : 'A0A8B0';
  $color_txt1 = $is_lite ? '202010' : 'A0A8B0';
  $color_txt2 = $is_lite ? '202010' : '101820';  
  $color_bord = $is_lite ? 'B0B0A0' : '606870';
  $color_link = $is_lite ? '606050' : '808890';
  $style_font_fixed = 'font-size: 12pt; font-family: "Courier New", Courier, monospace;';

  $style =
"body, table, input, select
{
    #white-space: nowrap;
    $style_font_fixed
}

body
{
    margin: 0;
    padding: 0;
    background-color: #$color_back;
    color: #$color_txt1;
}

table {
    border: 0;
    border-collapse: collapse;
    width: 100%;
}

td {
    padding: 0;
}

input
{
    margin: 0.2em 0 0.2em 0;
    padding: 0.2em 0.5em 0.1em 0.5em;
    background: #$color_lite;
    color: #$color_txt2;
    border: 1px solid #$color_bord;
}

select
{
    margin: 0.2em 0 0.2em 0;
    padding: 0.2em 0.5em 0.1em 0.2em;
    background: #$color_lite;
    color: #$color_txt2;
    border: 1px solid #$color_bord;
}

div.textarea
{
    padding: 1em;
    border: 1px solid #$color_bord;
    overflow: hidden;
    width: 48em;
    text-align: left;
    word-wrap: break-word;
    $style_font_fixed
    font-size: 10pt;
}

.ro
{
    background: #$color_back;
    color: #$color_txt1;
}
.red {
    background: #e08080;
    color: #000000;
}

hr
{
    margin: 1em 0 1em 0;
    height: 1px;
    border: 0;
    background-color: #$color_bord;
}

a
{
    color: #$color_link;
}";

    require( "include\secqru_html.php" );
    $html = new secqru_html();

    // HEAD
    $html->open( "html", ' style="overflow-y: scroll;"' );
    $html->open( "head" );
    $html->put( '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' );
    $html->put( '<meta name="format-detection" content="telephone=no">' );
    if( $app )
        $html->put( "<title>".SECQRU_SITE." — $app</title>" );
    else
        $html->put( "<title>".SECQRU_SITE."</title>" );
    $html->put( '<link rel="shortcut icon" href="'.SECQRU_ADDR.'favicon.ico" type="image/x-icon">' );

    // STYLE
    $html->open( "style", ' type="text/css"' );
    $html->put( explode( PHP_EOL, $style ) );
    $html->close();
    $html->close();

    // BODY
    $html->open( "body" );
    $html->open( "div", ' style="width: 80em; margin:0 auto; padding: 1em;"' );

    // FORM
    $html->open( "form", ' action="'.SECQRU_ADDR.$app.'" method="post"' );
    $html->input_full( "submit", "save", 0, 0, "save", ' style="position: absolute; left: -100em;"' );

    $html->open( "table" );
    $html->open( "tr" );
    $html->open( "td" );
    $html->input_full( "submit", "sw_app", 0, 0, SECQRU_SITE, "r" );
    $html->add(" — ");
    if( $app == "tiklan" )
    {
        $html->input_full( "submit", "sw_app", 0, 0, $app, 'r' );
        $html->add(" — ");
        $html->put_submit( "help", "help" );
        $html->put_submit( "reset", "reset" );
    }
    else
    {
        $html->put_submit( "sw_app", "tiklan" );
    }
    $html->close();

    $html->open( "td", ' align="right"' );
    $html->put_submit( "link", "link" );
    $html->put_input_hidden( "gamma", $is_lite ? "1" : "0" );
    $html->put_submit( $is_lite ? "sw_night" : "sw_light", $is_lite ? "night" : "light" );
    $html->close();
    $html->close();
    $html->close();

    if( $app == "tiklan" )
    {
        include "include\secqru_app_tiklan.php";
        $a = new secqru_app_tiklan( $w );
        $html->put( $a->html() );
    }
    else
    {
        $html->put( "<hr>" );
        $html->open( "div", ' align="right"' );
        $html->open( "div", " class=\"textarea\"" );
        $html->put( $w->log );
        $html->close();
        $html->close();
    }

    $html->put("<hr>");
    $html->open( "div", " style=\"text-align: right;\"" );
    $html->put( '<a href="https://github.com/deemru/secqru">github.com/deemru/secqru</a>' );
    echo $html->render();
    echo '<div  style="text-align: right;">'.memory_get_peak_usage()."<br>".round( ( microtime( TRUE ) - $_SERVER['REQUEST_TIME_FLOAT'] ), 4 )."</div>";

?>