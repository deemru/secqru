<?php

class secqru_worker
{
    public $log = array();
    public $url = array();

    public function ezlog( $message, $print, $value, $level = 0 ) {
        return self::log( "$message \"$print\" = \"$value\"", $level );
    }

    public function log( $message, $level = 0 )
    {
        $log_string = '';

        if( defined( 'SECQRU_DEBUG' ) )
        {
            $dbg = debug_backtrace();
            $log_string = ' (';
            for( $i = 0, $n = sizeof( $dbg ); $i < $n; $i++ )
            {
                if( $i > 0 )
                    $log_string .= ' > ';
                $file = substr( $dbg[$i]['file'], strrpos( $dbg[$i]['file'], '\\' ) + 1 );
                $log_string .= $file.':'.$dbg[$i]['line'];
            }
            $log_string .= ')';
        }

        switch( $level )
        {
            case 0: $level = '#   DEBUG: '; break;
            case 1: $level = '# WARNING: '; break;
            case 2: $level = '#   ERROR: '; break;
            case 3: $level = '# CRITICAL: '; break;
            case 7: $level = '# SUCCESS: '; break;
            default:
                exit( "# CRITICAL: unknown level == $level".$log_string );
        }

        $log_string = $level.$message.$log_string;
        $this->log[] = $log_string;
        return $log_string;
    }

    public function init( &$apps )
    {
        // url
        $this->url = substr( $_SERVER['REQUEST_URI'], strlen( SECQRU_ROOT ) );
        $this->url = explode( '/', $this->url );

        if( !isset( $this->url[0] ) )
            exit( self::log( 'bad url', 3 ) );

        // appname
        $app = $this->url[0];

        if( $app && !in_array( $app, $apps, true ) )
        {
            header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );
            exit( self::log( 'unknown app', 3 ) );
        }

        // sw_app
        $sw_app = self::get_dns( 'sw_app', -1 );

        if( $sw_app != -1 )
        {
            if( $sw_app == SECQRU_SITE )
                $sw_app = '';

            if( $sw_app != $app )
            {
                header( 'Location: ' . SECQRU_ADDR . $sw_app );
                exit;
            }
        }

        // cookie apps
        if( $app )
        {
            self::set_cookie_apps( $app, $apps );
        }
        else
        {
            $apps = self::get_cookie_apps( $apps );
            return array( false, '' );
        }

        // link produce
        if( self::get_set( 'link' ) )
        {
            unset( $_POST['link'] );

            if( isset( $_POST['gamma'] ) )
                unset( $_POST['gamma'] );

            $cryptex = self::get_cryptex( SECQRU_PASS );
            $link = $cryptex->cryptex( serialize( $_POST ) );

            $link = SECQRU_ADDR . $app . '/link/' . $link;
            header( "Location: $link" );
            exit;
        }

        // link load
        if( isset( $this->url[1] ) && $this->url[1] == 'link' &&
            isset( $this->url[2] ) )
        {
            $cryptex = self::get_cryptex( SECQRU_PASS );
            $data = $cryptex->decryptex( $this->url[2] );

            if( !$data )
                exit( self::log( 'bad link', 3 ) );

            $_POST = unserialize( $data );

            if( $_POST === false )
                exit( self::log( 'bad link', 3 ) );

            self::log( 'link', 7 );
        }

        require_once "include/secqru_app_$app.php";
        $classname = "secqru_app_$app";
        return array( new $classname( $this ), $app );
    }

    public function cryptex( $string )
    {
        $cryptex = self::get_cryptex( SECQRU_PASS );
        return $cryptex->cryptex( $string );
    }

    public function decryptex( $string )
    {
        $cryptex = self::get_cryptex( SECQRU_PASS );
        return $cryptex->decryptex( $string );
    }

    public function get_cookie_apps( $apps )
    {
        if( isset( $_COOKIE['apps'] ) )
        {
            $apps_user = $_COOKIE['apps'];
            $apps_user = explode( ':', $apps_user );
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
            setcookie( 'apps', implode( ':', $apps_new ), 0x7FFFFFFF );
    }

    public function get_style( &$is_lite, &$color_back )
    {
        if( $this->get_set( 'sw_light' ) )
        {
            $is_lite = 1;
            setcookie( 'gamma', 1, 0x7FFFFFFF );
            $this->log( 'switch to light', 7 );
        }
        else if( $this->get_set( 'sw_night' ) )
        {
            $is_lite = 0;
            setcookie( 'gamma', 0, 0x7FFFFFFF );
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
        $style_font_fixed = 'font-size: 12pt; font-family: "Courier New", Courier, monospace;';

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
        return explode( SECQRU_EOL, $style );
    }

    private function get_cryptex( $password )
    {
        require_once( 'secqru_cryptex.php' );
        return new secqru_cryptex( $password );
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
        for( $i = 0; $i < $size; $i++ )
        {
            if( ( $i % 3 ) == 0 )
                $rseed = pack( 'I', mt_rand() );

            if( $i == 0 )
                $rnd = $rseed[ $i % 3 ];
            else
                $rnd.= $rseed[ $i % 3 ];
        }

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

                if( $val && $val == $raw ) {
                    return $val;
                }

                if( !$val ) {
                    $default = self::get_default( $default );
                    self::ezlog( 'default', $print, $default, 1 );
                    return $default;
                }

                self::ezlog( 'filtered', $print, $val, 1 );
                return $val;

            case 2: // ip address
                $val = filter_var( $raw, FILTER_VALIDATE_IP );

                if( $val && $val == $raw ) {
                    return ip2long( $val );
                }

                $default = self::get_default( $default );
                self::ezlog( 'default', $print, $default, 1 );
                return $default;

            default:
                exit( self::log( "\"$print\" unknown type", 3 ) );
        }
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
    public function get_set( $name ) { return isset( $_POST[$name] ); }
    public function clear( $name ) { if( isset( $_POST[$name] ) ) unset( $_POST[$name] ); }
    public function reset(){ unset( $_POST ); }
}

?>
