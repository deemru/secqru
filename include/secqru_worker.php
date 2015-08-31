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
        if( defined( 'DEBUG' ) && DEBUG )
        {
            $dbg = debug_backtrace();
            $log_string = ' (';
            for( $i = 0; $i < sizeof( $dbg ); $i++ )
            {
                if( $i > 0 )
                    $log_string .= ' > ';
                $file = substr( $dbg[$i]['file'], strrpos( $dbg[$i]['file'], '\\' ) + 1 );
                $log_string .= $file.':'.$dbg[$i]['line'];
            }
            $log_string .= ')';
        }
        else
        {
            $log_string = '';
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

    function link_exists()
    {
        return strpos( $_SERVER['REQUEST_URI'], '/link/' );
    }

    function init_url( $root )
    {
        $this->url = substr( $_SERVER['REQUEST_URI'], strlen( $root ) );
        $this->url = explode( '/', $this->url );

        if( !isset( $this->url[0] ) )
            exit( self::log( 'bad url', 3 ) );
    }

    function get_app( $apps )
    {
        if( !isset( $this->url[0] ) )
            exit( self::log( 'unknown app', 3 ) );

        return $this->url[0];
    }

    function switch_app( $app, $switches )
    {
        $sw_app = self::get_dns( 'sw_app', -1 );

        if( $sw_app == -1 )
            return;

        if( !isset( $switches[$sw_app] ) )
            exit( self::log( 'unknown app', 3 ) );

        $sw_app = $switches[$sw_app];

        if( $app != $sw_app )
        {
            header( 'Location: ' . SECQRU_ADDR . $sw_app );
            exit;
        }
    }

    private function get_cryptex( $password )
    {
        require_once( 'secqru_cryptex.php' );
        return new secqru_cryptex( $password );
    }

    function link_produce( $password )
    {
        if( self::get_set( 'link' ) )
        {
            unset( $_POST['link'] );

            if( isset( $_POST['gamma'] ) )
                unset( $_POST['gamma'] );

            $cryptex = self::get_cryptex( $password );
            $link = $cryptex->cryptex( serialize( $_POST ) );

            $link = SECQRU_ADDR.$this->url[0].'/link/'.$link;
            header( "Location: $link" );
            exit;
        }
    }

    function get_raw_link( $n )
    {
        if( isset( $this->url[1] ) && $this->url[1] == 'link' &&
            isset( $this->url[2] ) )
        {
            return SECQRU_ADDR . $this->url[0] . '/' . $this->url[1] . '/' . $this->url[2] . '/raw/' . $n;
        }

        return 0;
    }

    function link_load( $password )
    {
        if( isset( $this->url[1] ) && $this->url[1] == 'link' &&
            isset( $this->url[2] ) )
        {
            $cryptex = self::get_cryptex( $password );
            $data = $cryptex->decryptex( $this->url[2] );

            if( !$data )
                exit( self::log( 'bad link', 3 ) );

            $_POST = unserialize( $data );

            if( $_POST === FALSE )
                exit( self::log( 'bad link', 3 ) );

            self::log( 'link', 7 );
        }
    }

    function get_raw()
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
}

?>