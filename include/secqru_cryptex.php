<?php

class secqru_cryptex
{
    private $psw;
    private $ivsz;
    private $macsz;
    private $rndsz;
    private $cbcsz;
    private $hash;

    public function __construct( $psw, $ivsz = 4, $macsz = 4, $rndsz = 2,
                                 $hash = 'gost', $cbcsz = 32 )
    {
        $this->psw = $psw;
        $this->ivsz = $ivsz;
        $this->macsz = $macsz;
        $this->rndsz = $rndsz;
        $this->cbcsz = $cbcsz;
        $this->hash = $hash;
    }

    public function rnd( $size = 8, $rndsz = 1 )
    {
        // be aware of use $rndsz > 3
        for( $i = 0; $i < $size; $i++ )
        {
            if( ( $i % $rndsz ) == 0 )
                $rseed = pack( 'I', mt_rand() );

            if( $i == 0 )
                $rnd = $rseed[ $i % $rndsz ];
            else
                $rnd.= $rseed[ $i % $rndsz ];
        }

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
        $iv = $this->ivsz ? self::rnd( $this->ivsz, $this->rndsz ) : ''; // iiv
        $data = $iv . $data;
        $iv = $this->ivsz ? self::rnd( $this->ivsz, $this->rndsz ) : ''; // oiv
        $key = self::key( $iv );
        $mac = substr( self::hash( $key . $data ), -$this->macsz );
        $data = self::cbc( $iv . $mac, $key, $data, TRUE );
        return bin2hex( $iv . $mac . $data );
    }

    public function decryptex( $data )
    {
        if( strlen( $data ) < ( $this->ivsz * 2 + $this->macsz ) * 2 // bin2hex
            || ctype_xdigit( $data ) !== TRUE )
            return FALSE;

        $data = pack( 'H*', $data );
        $iv = substr( $data, 0, $this->ivsz );
        $mac = substr( $data, $this->ivsz, $this->macsz );
        $data = substr( $data, $this->ivsz + $this->macsz );
        $key = self::key( $iv );
        $data = self::cbc( $iv . $mac, $key, $data, FALSE );

        if( $mac != substr( self::hash( $key . $data ), -$this->macsz ) )
            return FALSE;

        return gzinflate( substr( $data, $this->ivsz ) ); // skip inner iv
    }
}

?>
