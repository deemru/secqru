<?php

class secqru_worker
{
    public $log = array();
    public $url = array();

    private $a;
    private $app;
    private $apps;
    private $home;

    private $gamma;

    public function ezlog( $message, $print, $value, $level = 0 ) {
        return self::log( "$message \"$print\" = \"$value\"", $level );
    }

    public function log( $message, $level = 0 )
    {
        $log_string = '';

        if( defined( 'SECQRU_DEBUG' ) && $level != 8 )
        {
            $dbg = debug_backtrace();
            $log_string = ' (';
            for( $i = 0, $n = sizeof( $dbg ); $i < $n; $i++ )
            {
                if( $i > 0 )
                    $log_string .= ' > ';
                $file = substr( $dbg[$i]['file'], strrpos( $dbg[$i]['file'], '\\' ) + 1 );
                $log_string .= $file . ':' . $dbg[$i]['line'];
            }
            $log_string .= ')';
        }

        switch( $level )
        {
            case 0: $level = '# DEBUG: '; break;
            case 1: $level = '# WARNING: '; break;
            case 2: $level = '# ERROR: '; break;
            case 3: $level = '# CRITICAL: '; break;
            case 7: $level = '# SUCCESS: '; break;
            case 8: $level = ''; break;
            default:
                exit( "# CRITICAL: unknown level == $level" . $log_string );
        }

        $log_string = $level . $message . $log_string;
        $this->log[] = $log_string;
        return $log_string;
    }

    private function logrec()
    {
        return date( 'Y.m.d H:i:s | ' ) . str_pad( $_SERVER['REMOTE_ADDR'], 15 ) . ' | ' . $_SERVER['REQUEST_URI'] . PHP_EOL;
    }

    public function access_log()
    {
        if( defined( 'SECQRU_ACCESSLOG' ) )
        {
            require_once 'secqru_flock.php';
            ( new secqru_flock( SECQRU_ACCESSLOG ) )->append( self::logrec() );
        }
    }

    public function app_log()
    {
        if( defined( 'SECQRU_APPLOG' ) )
        {
            require_once 'secqru_flock.php';
            ( new secqru_flock( sprintf( SECQRU_APPLOG, $this->app ) ) )->append( self::logrec() );
        }
    }

    public function app_init()
    {
        if( method_exists( $this->a, 'init' ) )
            $this->a->init();
    }

    public function is_link()
    {
        return method_exists( $this->a, 'link' );
    }

    public function gamma()
    {
        return $this->gamma;
    }

    public function action()
    {
        return SECQRU_ADDR . ( $this->home ? '' : $this->app );
    }

    public function body( secqru_html $html )
    {
        $this->a->html( $html );
    }

    public function githead( secqru_html $html )
    {
        if( defined( 'SECQRU_GITHEAD' ) &&  file_exists( SECQRU_GITHEAD ) )
        {
            $rev = file_get_contents( SECQRU_GITHEAD, null, null, 0, 40 );
            $html->add( "/<a href=\"https://github.com/deemru/secqru/commit/$rev\">".substr( $rev, 0, 7 ).'</a>' );
        }
    }

    public function informer( secqru_html $html )
    {
        if( defined( 'SECQRU_INFORMER' ) )
        {
            $html->add( '', 1 );
            $html->put( '', 1 );
            $html->put( explode( SECQRU_EOL, sprintf( SECQRU_INFORMER, $this->color, $this->color, $this->gamma ? '0' : '1' ) ) );
        }
    }

    public function buttons( secqru_html $html )
    {
        $html->input_full( 'submit', 'swap', 0, 0, SECQRU_SITE, $this->home ? 'r' : '' );
        $html->add( ' — ' );

        if( $this->home )
        {
            foreach( $this->apps as $app )
                $html->input_full( 'submit', 'swap', 0, 0, $app, '' );
        }
        else
        {
            $html->input_full( 'submit', 'swap', 0, 0, $this->app, 'r' );

            if( method_exists( $this->a, 'buttons' ) )
                $this->a->buttons( $html );
        }
    }

    public function init()
    {
        // URL
        $this->url = substr( $_SERVER['REQUEST_URI'], strlen( SECQRU_ROOT ) );
        $this->url = explode( '/', $this->url );

        // APP
        $app = $this->url[0];
        $apps = explode( ':', SECQRU_APPS );

        if( $app == '' )
        {
            $app = SECQRU_HOME;
        }
        else if( !in_array( $app, $apps, true ) )
        {
            header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );
            exit( self::log( 'app not found', 3 ) );
        }

        // SWITCH APP
        $swap = self::get_dns( 'swap', -1 );

        if( $swap != -1 )
        {
            if( $swap == SECQRU_SITE )
                $swap = SECQRU_HOME;

            if( $swap != $app )
            {
                if( $swap == SECQRU_HOME )
                    $swap = '';

                exit( header( 'Location: ' . SECQRU_ADDR . $swap ) );
            }
        }

        $this->app = $app;
        $this->home = $app == SECQRU_HOME;

        // COOKIES
        if( !$this->home )
            self::set_cookie_apps( $app, $apps );
        else
            $this->apps = self::get_cookie_apps( $apps );

        // LINK MAKE
        if( self::get_set( 'link' ) )
        {
            unset( $_POST['link'] );

            if( isset( $_POST['gamma'] ) )
                unset( $_POST['gamma'] );

            $link = self::cryptex( serialize( $_POST ) );
            $link = SECQRU_ADDR . $app . '/link/' . $link;
            exit( header( "Location: $link" ) );
        }

        // LINK LOAD
        if( !empty( $this->url[1] ) && $this->url[1] == 'link' &&
            !empty( $this->url[2] ) )
        {
            $data = self::decryptex( $this->url[2] );

            if( !$data )
                exit( self::log( 'bad link', 3 ) );

            $_POST = unserialize( $data );

            if( $_POST === false )
                exit( self::log( 'bad link', 3 ) );

            self::log( 'link', 7 );
        }

        require_once "include/secqru_app_$app.php";
        $classname = "secqru_app_$app";
        $this->a = new $classname( $this );
    }

    public function cryptex( $string )
    {
        $cryptex = self::get_cryptex();
        $abcode = self::get_abcode();

        if( !( $string = gzdeflate( $string, 9 ) ) ||
            !( $string = $cryptex->cryptex( $string ) ) ||
            !( $string = $abcode->encode( $string ) ) )
            return false;
        return $string;
    }

    public function decryptex( $string )
    {
        $cryptex = self::get_cryptex();
        $abcode = self::get_abcode();

        if( !( $string = $abcode->decode( $string ) ) ||
            !( $string = $cryptex->decryptex( $string ) ) ||
            !( $string = gzinflate( $string ) ) )
            return false;
        return $string;
    }

    public function get_cookie_apps( $apps )
    {
        if( isset( $_COOKIE['apps'] ) )
        {
            $apps_user = $_COOKIE['apps'];
            $apps_user = explode( '-', $apps_user );
            $apps_user = array_intersect( $apps_user, $apps );
            $apps = array_merge( $apps_user, array_diff( $apps, $apps_user ) );
        }

        return $apps;
    }

    public function set_cookie_apps( $app, $apps )
    {
        $apps = self::get_cookie_apps( $apps );
        $app = array( $app );
        $apps_new = array_merge( $app, array_diff( $apps, $app ) );
        if( $apps_new !== $apps )
            setcookie( 'apps', implode( '-', $apps_new ), 0x7FFFFFFF, '/' );
    }

    public function style( secqru_html $html )
    {
        if( $this->get_set( 'sw_light' ) )
        {
            $is_lite = 1;
            setcookie( 'gamma', 1, 0x7FFFFFFF, '/' );
            $this->log( 'switch to light', 7 );
        }
        else if( $this->get_set( 'sw_night' ) )
        {
            $is_lite = 0;
            setcookie( 'gamma', 0, 0x7FFFFFFF, '/' );
            $this->log( 'switch to night', 7 );
        }
        else
        {
            if( isset( $_COOKIE['gamma'] ) )
            {
                $is_lite = intval( $_COOKIE['gamma'] );
                $is_lite = $is_lite ? 1 : 0;
            }
            else
                $is_lite = $this->get_int( 'gamma', 1, 0, 1 );
        }

        $color_back = $is_lite ? 'E0E0D0' : '404840';
        $color_lite = $is_lite ? 'FFFFFF' : 'A0A8B0';
        $color_txt1 = $is_lite ? '202010' : 'A0A8B0';
        $color_txt2 = $is_lite ? '202010' : '101820';
        $color_bord = $is_lite ? 'B0B0A0' : '606870';
        $color_link = $is_lite ? '606050' : '808890';
        $style_font_fixed = 'font-size: 12pt; font-size: 1.14vw; font-family: "Courier New", Courier, monospace;';

        $style =
"body, table, input, select, div
{
    $style_font_fixed
}

body
{
    margin: 0;
    padding: 0;
    background-color: #$color_back;
    color: #$color_txt1;
}

table
{
    border: 0;
    border-collapse: collapse;
    width: 100%;
}

td
{
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

input.checkbox
{
    position: relative;
    bottom: -0.1em;
}

input.file
{
    padding: 0;
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
    font-size: 10pt;
    font-size: 0.95vw;
}

.ro
{
    background: #$color_back;
    color: #$color_txt1;
}

.red
{
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
        $this->gamma = $is_lite;
        $this->color = $color_back;
        $html->put( explode( SECQRU_EOL, $style ) );
    }

    private function get_cryptex()
    {
        require_once 'secqru_cryptex.php';
        return new secqru_cryptex( $this->app . SECQRU_PASS );
    }

    private function get_abcode()
    {
        require_once 'secqru_abcode.php';
        return new secqru_abcode( '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' );
    }

    public function get_raw_link()
    {
        if( isset( $this->url[1] ) && $this->url[1] == 'link' &&
            isset( $this->url[2] ) )
        {
            return SECQRU_ADDR . $this->url[0] . '/' . $this->url[1] . '/' . $this->url[2] . '/raw/';
        }

        return 0;
    }

    public function get_addend()
    {
        if( !empty( $this->url[ 2 ] ) && $this->url[ 1 ] == 'link' )
            $offset = 3;
        else
            $offset = 1;

        return !empty( $this->url[ $offset ] ) ? array_slice( $this->url, $offset ) : false;
    }

    public function get_raw()
    {
        if( isset( $this->url[3] ) && $this->url[3] == 'raw' &&
            isset( $this->url[4] ) )
        {
            $val = intval( $this->url[4] );
            if( strval( $val ) != $this->url[4] )
                exit( self::log( "bad raw", 3 ) );

            return $val;
        }
        else
            return 0;
    }

    static public function rndhex( $size )
    {
        $rnd = '';
        while( $size-- )
            $rnd .= chr( mt_rand() );

        return bin2hex( $rnd );
    }

    private function get_default( $default )
    {
        return is_callable( $default ) ? $default() : $default;
    }

    public function get_val( $name, $default, $type, &$print = 0 )
    {
        if( strpos( $name, ':' ) )
        {
            $print = explode( ':', $name );
            $name = $print[0];
            $print = $print[1];
        }
        else
            $print = $name;

        if( !isset( $_POST[$name] ) )
            return self::get_default( $default );

        $raw = $_POST[$name];

        switch( $type )
        {
            case 0: // decimal integer
                $val = intval( $raw );
                if( strval( $val ) == $raw )
                    return $val;

                $default = self::get_default( $default );
                self::ezlog( 'default', $print, $default, 1 );
                return $default;

            case 1: // dns like string
                $val = preg_replace( '/[^A-Za-z0-9\-.]/', '', $raw );

                if( $val && $val == $raw )
                    return $val;

                if( !$val )
                {
                    $default = self::get_default( $default );
                    if( $default != $raw )
                        self::ezlog( 'default', $print, $default, 1 );
                    return $default;
                }

                if( defined( 'SECQRU_DEBUG' ) )
                    self::ezlog( "'$raw' filtered", $print, $val, 1 );
                else
                    self::ezlog( 'filtered', $print, $val, 1 );
                return $val;

            case 2: // ip2long address
                $val = filter_var( $raw, FILTER_VALIDATE_IP );

                if( $val && $val == $raw )
                    return ip2long( $val );

                $default = self::get_default( $default );
                self::ezlog( 'default', $print, $default, 1 );
                return $default;

            case 3: // ip address
                $val = filter_var( $raw, FILTER_VALIDATE_IP );

                if( $val && $val == $raw )
                    return $val;

                $default = self::get_default( $default );
                self::ezlog( 'default', $print, $default, 1 );
                return $default;

            default:
                exit( self::log( "\"$print\" unknown type", 3 ) );
        }
    }

    public function get_db()
    {
        if( !empty( $_POST['db'] ) &&
            ( $db = self::decryptex( $_POST['db'] ) ) &&
            ( $db = unserialize( $db ) ) )
            return $db;

        return array();
    }

    public function put_db( $db )
    {
        return self::cryptex( serialize( $db ) );
    }

    public function get_special_link( $db )
    {
        return SECQRU_ADDR . $this->app . '/link/' . self::cryptex( serialize( $db ) );
    }

    public function get_title()
    {
        if( method_exists( $this->a, 'title' ) )
            return $this->a->title();

        return SECQRU_SITE . ( $this->home ? '' : " — {$this->app}" );
    }

    public function set_title( $title )
    {
        $this->title = $title;
    }

    public function get_int( $name, $default, $min = false, $max = false, $change = false )
    {
        $int = $this->get_val( $name, $default, 0, $print );

        if( $change !== false )
            $int += $change;

        if( $min !== false && $max !== false && $min > $max )
            exit( self::log( "min > max ($min > $max)", 3 ) );

        // MIN
        if( $min !== false && $int < $min )
        {
            $int = $min;
            self::ezlog( 'minimum', $print, $int, 1 );
        }
        else

        // MAX
        if( $max !== false && $int > $max )
        {
            $int = $max;
            self::ezlog( 'maximum', $print, $int, 1 );
        }

        return $int;
    }

    public function get_dns( $name, $default ) { return $this->get_val( $name, $default, 1 ); }
    public function get_ip2long( $name, $default ) { return $this->get_val( $name, $default, 2 ); }
    public function get_ip( $name, $default ) { return $this->get_val( $name, $default, 3 ); }
    public function get_set( $name ) { return isset( $_POST[$name] ); }
    public function clear( $name ) { if( isset( $_POST[$name] ) ) unset( $_POST[$name] ); }
    public function reset(){ unset( $_POST ); }
}
