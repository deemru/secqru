<?php

    require( 'secqru.config.php' );

    if( defined( 'SECQRU_LOCKIP' ) )
    {
        require_once( 'include/secqru_flock.php' );

        $ip_lock = new secqru_flock( SECQRU_LOCKIP.$_SERVER['REMOTE_ADDR'] );
        if( !$ip_lock->open() )
        {
            header( 'Status: 503 Service Temporarily Unavailable' );
            header( 'Retry-After: 10' );
            exit( '503' );
        }
    }

    if( defined( 'SECQRU_ERRORLOG' ) )
    {
        error_reporting( -1 );
        ini_set( 'display_errors', TRUE );
        ini_set( 'display_startup_errors', TRUE );
        ini_set( 'log_errors', 1 );
        ini_set( 'error_log', SECQRU_ERRORLOG );
    }

    if( defined( 'SECQRU_ACCESSLOG' ) )
    {
        require_once( 'include/secqru_flock.php' );

        ( new secqru_flock( SECQRU_ACCESSLOG ) )->append(
        date( 'Y.m.d H:i:s | ' ) . str_pad( $_SERVER['REMOTE_ADDR'], 15 )
        . ' | ' . $_SERVER['REQUEST_URI'].PHP_EOL );
    }

    require_once( 'include/secqru_worker.php' );
    $w = new secqru_worker();

    $w->init_url( SECQRU_ROOT );

    $apps = array( 
        '' => '',
        'tiklan' => 'include/secqru_app_tiklan.php'
    );

    $app = $w->get_app( $apps );

    $switches = array(
        SECQRU_SITE => '',
        'tiklan' => 'tiklan'
        );

    $w->switch_app( $app, $switches );

    if( $app )
    {
        $w->link_produce( SECQRU_PASS );
        $w->link_load( SECQRU_PASS );
    }

    if( defined( 'SECQRU_APPLOG' ) && $app )
    {
        require_once( 'include/secqru_flock.php' );

        ( new secqru_flock( sprintf( SECQRU_APPLOG, $app ) ) )->append(
        date( 'Y.m.d H:i:s | ' ) . str_pad( $_SERVER['REMOTE_ADDR'], 15 )
        . ' | ' . $_SERVER['REQUEST_URI'].PHP_EOL );
    }

    if( $w->get_set( 'sw_light' ) )
    {
        $is_lite = 1;
        setcookie( 'gamma', 1, 0x7FFFFFFF );
        $w->log( 'switch to light', 7 );
    }
    else if( $w->get_set( 'sw_night' ) )
    {
        $is_lite = 0;
        setcookie( 'gamma', 0, 0x7FFFFFFF );
        $w->log( 'switch to night', 7 );
    }
    else
    {
        if( isset( $_COOKIE['gamma'] ) )
        {
            $is_lite = intval( $_COOKIE['gamma'] );
            $is_lite = $is_lite ? 1 : 0;
        }
        else
            $is_lite = $w->get_int( 'gamma', 1, 0, 1 );
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

    require_once( 'include/secqru_html.php' );
    $html = new secqru_html();

    // HEAD
    $html->open( 'html', ' style="overflow-y: scroll;"' );
    $html->open( 'head' );
    $html->put( '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' );
    $html->put( '<meta name="format-detection" content="telephone=no">' );
    if( $app )
        $html->put( '<title>' . SECQRU_SITE . " — $app</title>" );
    else
        $html->put( '<title>' . SECQRU_SITE . '</title>' );
    $html->put( '<link rel="shortcut icon" href="' . SECQRU_ADDR . 'favicon.ico" type="image/x-icon">' );

    // STYLE
    $html->open( 'style', ' type="text/css"' );
    $html->put( explode( PHP_EOL, $style ) );
    $html->close();
    $html->close();

    // BODY
    $html->open( 'body' );
    $html->open( 'div', ' style="width: 80em; margin:0 auto; padding: 1em;"' );

    // FORM
    $html->open( 'form', ' action="' . SECQRU_ADDR . $app . '" method="post"' );
    $html->input_full( 'submit', 'save', 0, 0, 'save', ' style="position: absolute; left: -100em;"' );

    $html->open( 'table' );
    $html->open( 'tr' );
    $html->open( 'td' );
    $html->input_full( 'submit', 'sw_app', 0, 0, SECQRU_SITE, $app ? '' : 'r' );
    $html->add( ' — ' );
    if( $app == 'tiklan' )
    {
        $html->input_full( 'submit', 'sw_app', 0, 0, $app, 'r' );
        $html->add( ' — ' );
        $html->put_submit( 'help', 'help' );
        $html->put_submit( 'reset', 'reset' );
    }
    else
    {
        $html->put_submit( 'sw_app', 'tiklan' );
    }
    $html->close();

    $html->open( 'td', ' align="right"' );
    $html->input_full( 'submit', 'link', 0, 0, 'link', $app ? '' : 'r' );
    $html->put_input_hidden( 'gamma', $is_lite ? '1' : '0' );
    $html->put_submit( $is_lite ? 'sw_night' : 'sw_light', $is_lite ? 'night' : 'light' );
    $html->close();
    $html->close();
    $html->close();

    if( $app == 'tiklan' )
    {
        require_once 'include/secqru_app_tiklan.php';
        $a = new secqru_app_tiklan( $w );
        $html->put( $a->html() );
    }
    else if( $w->log )
    {
        $html->put( '<hr>' );
        $html->open( 'div', ' align="right"' );
        $html->open( 'div', ' class="textarea"' );
        $html->put( $w->log );
        $html->close();
        $html->close();
    }

    $html->close();

    $html->put( '<hr>' );
    $html->open( 'div', ' style="text-align: right;"' );

    $html->put( '<a href="https://github.com/deemru/secqru">github/deemru/secqru</a>' );

    if( defined( 'SECQRU_GITHEAD' ) )
    {
        $temp = file_get_contents( '.git/FETCH_HEAD', NULL, NULL, 0, 40 );
        $html->add( "/<a href=\"https://github.com/deemru/secqru/commit/$temp\">".substr( $temp, 0, 7 ).'</a>' );
    }

    if( defined( 'SECQRU_INFORMER' ) )
    {
        $html->add( '', 1 );
        $html->put( '', 1 );
        $html->put( explode( PHP_EOL, sprintf( SECQRU_INFORMER, $color_back, $color_back, $is_lite ? '0' : '1' ) ) );
    }

    echo $html->render();

    if( defined( 'SECQRU_DEBUG' ) )
        echo '<center><small>'.sprintf( 'Memory: %.02f KB', memory_get_peak_usage()/1024 ).'<br>'.sprintf( 'Speed: %.01f ms', 1000 * ( microtime( TRUE ) - $_SERVER['REQUEST_TIME_FLOAT'] ) ).'</small></center>';

?>