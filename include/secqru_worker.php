<?php

class secqru_worker
{
    public $log = '';

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
                exit( "# CRITICAL: unknown level == $level".$log_string.PHP_EOL );
        }

        $log_string = $level.$message.$log_string.PHP_EOL;
        $this->log .= $log_string;
        return $log_string;
    }

    private function get_cryptex( $password )
    {
        require_once( 'secqru_cryptex.php' );
        return new secqru_cryptex( $password );
    }

    function link_load( $password )
    {
        $data = $_SERVER['REQUEST_URI'];
        $pos = strpos( $data, '?' );
        if( $pos )
            $data = substr( $data, 0, $pos );
        $pos = strrpos( $data, '/' );
        if( strlen( $data ) == $pos + 1 )
            $data = substr( $data, 0, -1 );
        $pos = strrpos( $data, '/' );
        $data = substr( $data, $pos + 1 );

        $cryptex = self::get_cryptex( $password );
        $data = $cryptex->decryptex( $data );
        if( !$data )
            return 0;

        $_POST = unserialize( $data );
        if( $_POST === FALSE )
            return 0;

        return 1;
    }

    function link_get( $password )
    {
        $cryptex = self::get_cryptex( $password );
        return $cryptex->cryptex( serialize( $_POST ) );
    }

    function link_exists()
    {
        return strpos( $_SERVER['REQUEST_URI'], '/link/' );
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