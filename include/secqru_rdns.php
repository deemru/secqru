<?php

class secqru_rdns
{

    public function rdns( $ns, $ip )
    {
        if( !( $q = self::rdns_get_query( $ip ) ) ) return $ip;
        if( !( $r = self::rdns_get_response( $ns, $q ) ) ) return $ip;
        if( !( $rdns = self::rdns_decode_response( $q, $r ) ) ) return $ip;
        if( empty( $rdns[0] ) ) return $ip;
        return $rdns[0];
    }

    private function rdns_get_query( $ip )
    {
        $q = chr( mt_rand() ) . chr( mt_rand() );
        $q .= "\1\0\0\1\0\0\0\0\0\0";

        $w = explode( '.', $ip );

        if( sizeof( $w ) != 4 )
            return false;

        for( $i = 3; $i >= 0; $i-- )
        {
            $s = $w[$i];
            $n = intval( $s );

            if( strval( $n ) !== $s )
                return false;

            if( $n < 0 || $n > 255 )
                return false;

            switch( strlen( $s ) )
            {
                case 1: $q .= "\1" . $s; break;
                case 2: $q .= "\2" . $s; break;
                case 3: $q .= "\3" . $s; break;
                default: return false;
            }
        }

        $q .= "\7in-addr\4arpa\0\0\x0C\0\1";
        return $q;
    }

    private function rdns_get_response( $dns, $query, $timeout = 1 )
    {
        $fp = fsockopen( "udp://$dns", 53 );

        if( !$fp )
            return false;

        stream_set_timeout( $fp, $timeout );
        fwrite( $fp, $query );
        $r = fread( $fp, 65536 );
        fclose( $fp );

        if( !$r )
            return false;

         return $r;
    }

    private function rdns_name_construct( $raw )
    {
        $name = '';
        $max = strlen( $raw );

        for( $i = 0; $i < $max; $i += $l )
        {
            $l = ord( $raw[$i] );

            if( $l == 0 )
                break;

            if( ++$i + $l >= $max )
                return false;

            $name .= ( $i == 1 ? '' : '.' ) . substr( $raw, $i, $l );
        }

        return $name;
    }

    private function rdns_decode_response( $query, $response )
    {
        if( strlen( $response ) <= strlen( $query ) ||
            // Transaction ID
            $response[0] != $query[0] ||
            $response[1] != $query[1] )
            return false;

        $a = substr( $response, 6, 2 );
        $a = ( ord( $a[0] ) << 8 ) + ord( $a[1] );

        if( !$a )
            return false;

        $shift = strlen( $query );

        $names = array();

        for( ;; )
        {
            if( strlen( $response ) - $shift < 12 )
                return false;

            $t = substr( $response, $shift + 2, 2 );
            $t = ( ord( $t[0] ) << 8 ) + ord( $t[1] );

            $l = substr( $response, $shift + 10, 2 );
            $l = ( ord( $l[0] ) << 8 ) + ord( $l[1] );
            
            if( strlen( $response ) - $shift - 12 < $l )
                return false;

            if( $t == 12 )
                $names[] = self::rdns_name_construct( substr( $response, $shift + 12, $l ) );

            if( --$a == 0 )
                return $names;

            $shift += 12 + $l;
        }
    }

}
