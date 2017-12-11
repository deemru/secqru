<?php // https://raw.githubusercontent.com/deemru/secqru/8cca0104eb944a5c218897a3d314caa78fb4b289/include/secqru_cryptex.php

class secqru_cryptex_old
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
