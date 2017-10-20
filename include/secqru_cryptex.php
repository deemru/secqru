<?php

class secqru_cryptex
{
    private $psw;
    private $ivsz;
    private $macsz;
    private $cbcsz;
    private $hash;
    private $rndfn;

    public function __construct( $psw, $ivsz = 4, $macsz = 4,
                                 $hash = 'gost', $cbcsz = 32 )
    {
        $this->psw = $psw;
        $this->ivsz = $ivsz;
        $this->macsz = $macsz;
        $this->cbcsz = $cbcsz;
        $this->hash = $hash;
        if( function_exists( 'random_bytes' ) )
            $this->rndfn = 2;
        else if( function_exists( 'mcrypt_create_iv' ) )
            $this->rndfn = 1;
        else
            $this->rndfn = 0;
    }

    public function rnd( $size = 8 )
    {
        switch( $this->rndfn )
        {
            case 2:
                return random_bytes( $size );
            case 1:
                return mcrypt_create_iv( $size, MCRYPT_DEV_URANDOM );
            case 0:
                $rnd = '';
                while( $size-- ) $rnd .= chr( mt_rand() );
                return $rnd;
        }
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
        $iv = $this->ivsz ? self::rnd( $this->ivsz ) : ''; // iiv
        $data = $iv . $data;
        $iv = $this->ivsz ? self::rnd( $this->ivsz ) : ''; // oiv
        $key = self::key( $iv );
        $mac = substr( self::hash( $key . $data ), -$this->macsz );
        $data = self::cbc( $iv . $mac, $key, $data, true );
        return $iv . $mac . $data;
    }

    public function decryptex( $data )
    {
        if( strlen( $data ) < 2 * $this->ivsz + $this->macsz )
            return false;

        $iv = substr( $data, 0, $this->ivsz );
        $mac = substr( $data, $this->ivsz, $this->macsz );
        $data = substr( $data, $this->ivsz + $this->macsz );
        $key = self::key( $iv );
        $data = self::cbc( $iv . $mac, $key, $data, false );

        if( $mac != substr( self::hash( $key . $data ), -$this->macsz ) )
            return false;

        return substr( $data, $this->ivsz ); // skip inner iv
    }
}
