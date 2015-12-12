<?php

    require( 'secqru.config.php' );

    if( defined( 'SECQRU_LOCKIP' ) )
    {
        require_once( 'include/secqru_flock.php' );

        $ip_lock = new secqru_flock( SECQRU_LOCKIP.$_SERVER['REMOTE_ADDR'].'.lock' );
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
        ini_set( 'display_errors', true );
        ini_set( 'display_startup_errors', true );
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

    $apps = array( 'ip', 'tiklan', 'zakrug' );
    list( $a, $app ) = $w->init( $apps );

    if( defined( 'SECQRU_APPLOG' ) && $a )
    {
        require_once( 'include/secqru_flock.php' );

        ( new secqru_flock( sprintf( SECQRU_APPLOG, $app ) ) )->append(
        date( 'Y.m.d H:i:s | ' ) . str_pad( $_SERVER['REMOTE_ADDR'], 15 )
        . ' | ' . $_SERVER['REQUEST_URI'].PHP_EOL );
    }

    require_once( 'include/secqru_html.php' );
    $html = new secqru_html();

    // HEAD
    $html->open( 'html' );
    $html->open( 'head' );
    $html->put( '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' );
    $html->put( '<meta name="format-detection" content="telephone=no">' );
    if( $a && method_exists( $a, 'get_title' ) )
        $html->put( '<title>' . $a->get_title() . '</title>' );
    else
        $html->put( '<title>' . SECQRU_SITE . ( $a ? " — $app</title>" : '</title>' ) );
    $html->put( '<link rel="shortcut icon" href="' . SECQRU_ADDR . 'favicon.ico" type="image/x-icon">' );

    // STYLE
    $html->open( 'style', ' type="text/css"' );
    $is_lite = 0; // TODO
    $color_back = 0; // TODO
    $html->put( $w->get_style( $is_lite, $color_back ) );
    $html->close();
    $html->close();

    // BODY
    $html->open( 'body', ' style="overflow-y: scroll;"' );
    $html->open( 'div', ' style="width: 80em; margin:0 auto; padding: 1em;"' );

    // FORM
    $html->open( 'form', ' enctype="multipart/form-data" method="POST" action="' . SECQRU_ADDR . $app . '"' );
    $html->input_full( 'submit', 'save', 0, 0, 'save', ' style="position: absolute; left: -100em;"' );

    // BUTTONS
    $html->open( 'table' );
    $html->open( 'tr' );
    $html->open( 'td' );
    $html->input_full( 'submit', 'sw_app', 0, 0, SECQRU_SITE, $a ? '' : 'r' );
    $html->add( ' — ' );

    if( $a )
    {
        // APP SELECTED
        $html->input_full( 'submit', 'sw_app', 0, 0, $app, 'r' );
        if( method_exists( $a, 'put_buttons' ) )
            $a->put_buttons( $html );
    }
    else foreach( $apps as $app_name )
    {
        // ALL APPS
        $html->input_full( 'submit', 'sw_app', 0, 0, $app_name, '' );
    }

    $html->close();

    $html->open( 'td', ' align="right"' );
    $html->input_full( 'submit', 'link', 0, 0, 'link', $a ? '' : 'r' );
    $html->put_input_hidden( 'gamma', $is_lite ? '1' : '0' );
    $html->put_submit( $is_lite ? 'sw_night' : 'sw_light', $is_lite ? 'night' : 'light' );
    $html->close();
    $html->close();
    $html->close();

    if( $a )
    {
        $html->put( '<hr>' );
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
        $temp = file_get_contents( '.git/FETCH_HEAD', null, null, 0, 40 );
        $html->add( "/<a href=\"https://github.com/deemru/secqru/commit/$temp\">".substr( $temp, 0, 7 ).'</a>' );
    }

    if( defined( 'SECQRU_INFORMER' ) )
    {
        $html->add( '', 1 );
        $html->put( '', 1 );
        $html->put( explode( SECQRU_EOL, sprintf( SECQRU_INFORMER, $color_back, $color_back, $is_lite ? '0' : '1' ) ) );
    }

    echo $html->render();

    if( defined( 'SECQRU_DEBUG' ) )
        echo '<center><small>'.sprintf( 'Memory: %.02f KB', memory_get_peak_usage()/1024 ).'<br>'.sprintf( 'Speed: %.01f ms', 1000 * ( microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'] ) ).'</small></center>';

?>
