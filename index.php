<?php

    require 'secqru.config.php';

    if( defined( 'SECQRU_LOCKIP' ) )
    {
        require 'include/secqru_flock.php';
        $l = new secqru_flock( SECQRU_LOCKIP . $_SERVER['REMOTE_ADDR'] );
        if( !$l->open() )
        {
            header( 'Status: 503 Service Temporarily Unavailable' );
            header( 'Retry-After: 10' );
            exit( SECQRU_SITE . ' busy' );
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

    require 'include/secqru_worker.php';
    $w = new secqru_worker();
    $w->access_log();
    $w->init();
    $w->app_log();
    $w->app_init();

    $raw = $w->raw();
    if( $raw !== false )
        exit( $raw );

    require_once 'include/secqru_html.php';
    $h = new secqru_html();

    // HEAD
    $h->open( 'html' );
    $h->open( 'head' );
    $h->put( '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' );
    $h->put( '<meta name="format-detection" content="telephone=no">' );
    $h->put( '<title>' . $w->get_title() . '</title>' );
    $h->put( '<link rel="shortcut icon" href="' . SECQRU_ADDR . 'favicon.ico" type="image/x-icon">' );

    // STYLE
    $h->open( 'style', ' type="text/css"' );
    $w->style( $h );
    $h->close( 2 );

    // BODY
    $h->open( 'body', ' style="overflow-y: scroll;"' );
    $h->open( 'div', ' style="width: 80em; margin:0 auto; padding: 1em;"' );

    // FORM
    $h->open( 'form', ' enctype="multipart/form-data" method="POST" action="' . $w->action() . '"' );
    $h->input_full( 'submit', 'save', 0, 0, 'save', ' style="position: absolute; left: -100em;"' );

    // TOP BUTTONS
    $h->open( 'table' );
    $h->open( 'tr' );
    $h->open( 'td' );
    $w->buttons( $h );
    $h->close();

    $h->open( 'td', ' align="right"' );
    if( $w->is_link() )
        $h->input_full( 'submit', 'link', 0, 0, 'link', '' );
    $h->put_input_hidden( 'gamma', $w->gamma() ? '1' : '0' );
    $h->put_submit( $w->gamma() ? 'sw_night' : 'sw_light', $w->gamma() ? 'night' : 'light' );
    $h->close( 3 );

    $w->body( $h );
    $h->close();

    $h->put( '<hr>' );
    $h->open( 'div', ' style="text-align: right;"' );

    $h->put( '<a href="https://github.com/deemru/secqru">github/deemru/secqru</a>' );
    $w->githead( $h );
    $w->informer( $h );

    $h->render();

    if( defined( 'SECQRU_DEBUG' ) )
        echo '<center><small>'.sprintf( 'Memory: %.02f KB', memory_get_peak_usage()/1024 ).'<br>'.sprintf( 'Speed: %.01f ms', 1000 * ( microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'] ) ).'</small></center>';
