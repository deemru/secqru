<?php

class secqru_cryptex
{
    private $psw;
    private $ivsz;
    private $macsz;
    private $cbcsz;
    private $hash;

    public function __construct( $psw, $ivsz = 4, $macsz = 4,
                                 $hash = 'gost', $cbcsz = 32 )
    {
        $this->psw = $psw;
        $this->ivsz = $ivsz;
        $this->macsz = $macsz;
        $this->cbcsz = $cbcsz;
        $this->hash = $hash;
    }

    public function rnd( $size = 8 )
    {
        if( function_exists( 'random_bytes' ) )
            return random_bytes( $size );
        if( function_exists( 'mcrypt_create_iv' ) )
            return mcrypt_create_iv( $size, MCRYPT_DEV_URANDOM );

        $rnd = '';
        while( $size-- )
            $rnd .= chr( mt_rand() );

        return $rnd;
    }

    private function hash( $data )
    {
        return hash( $this->hash, $data, true );
    }

    private function cbc( $iv, $key, $data, $encrypt )
    {
        for( $i = 0, $n = strlen( $data ); $i < $n; $i++ )
        {
            if( ( $i % $this->cbcsz ) == 0 )
            {
                if( $encrypt && $i )
                    $iv = substr( $data, $i - $this->cbcsz, $this->cbcsz );

                $crypta = self::hash( $iv . $key );

                if( !$encrypt )
                    $iv = substr( $data, $i, $this->cbcsz );
            }

            $data[$i] = $data[$i] ^ $crypta[ $i % $this->cbcsz ];
        }

        return $data;
    }

    private function key( $iv )
    {
        return self::hash( $this->psw . $iv );
    }

    public function cryptex( $data )
    {
        $data = gzdeflate( $data, 9 );
        $iv = $this->ivsz ? self::rnd( $this->ivsz ) : ''; // iiv
        $data = $iv . $data;
        $iv = $this->ivsz ? self::rnd( $this->ivsz ) : ''; // oiv
        $key = self::key( $iv );
        $mac = substr( self::hash( $key . $data ), -$this->macsz );
        $data = self::cbc( $iv . $mac, $key, $data, true );
        return bin2hex( $iv . $mac . $data );
    }

    public function decryptex( $data )
    {
        if( strlen( $data ) < ( $this->ivsz * 2 + $this->macsz ) * 2 // bin2hex
            || ctype_xdigit( $data ) !== true )
            return false;

        $data = pack( 'H*', $data );
        $iv = substr( $data, 0, $this->ivsz );
        $mac = substr( $data, $this->ivsz, $this->macsz );
        $data = substr( $data, $this->ivsz + $this->macsz );
        $key = self::key( $iv );
        $data = self::cbc( $iv . $mac, $key, $data, false );

        if( $mac != substr( self::hash( $key . $data ), -$this->macsz ) )
            return false;

        return gzinflate( substr( $data, $this->ivsz ) ); // skip inner iv
    }
}

?>
