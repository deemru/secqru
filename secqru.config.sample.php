<?php

    define( 'SECQRU_ROOT', '/secqru/' );
    define( 'SECQRU_SITE', 'secq.ru' );
    define( 'SECQRU_PASS', 'password' );

    define( 'SECQRU_ADDR', 'http' .
    ( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) ? 's' : '' ) .
    '://' . $_SERVER['HTTP_HOST'] . SECQRU_ROOT );

    date_default_timezone_set( 'Europe/Moscow' );
    mb_internal_encoding( 'UTF-8' );

    define( 'SECQRU_DEBUG', 1 );
    define( 'SECQRU_ERRORLOG', './var/log/_error.log' );
    define( 'SECQRU_ACCESSLOG', './var/log/access.log' );
    define( 'SECQRU_APPLOG', './var/log/%s.log' );
    define( 'SECQRU_LOCKIP', './var/lock/' );

?>