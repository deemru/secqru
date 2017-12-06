<?php

class secqru_cryptex
{
    private $psw;
    private $ivsz;
    private $macsz;
    private $cbcsz;
    private $hash;
    private $rndfn;

    public function __construct( $psw, $ivsz = 4, $macsz = 4, $hash = 'sha256' )
    {
        $this->psw = $psw;
        $this->hash = $hash;
        $this->ivsz = max( 0, $ivsz );
        $this->cbcsz = strlen( self::hash( '' ) );
        $this->macsz = min( $macsz, $this->cbcsz );

        if( function_exists( 'random_bytes' ) )
            $this->rndfn = 2;
        else if( function_exists( 'mcrypt_create_iv' ) )
            $this->rndfn = 1;
        else
            $this->rndfn = 0;
    }

    public function rnd( $size = 8 )
    {
        if( $this->rndfn === 2 )
            return random_bytes( $size );
        if( $this->rndfn === 1 )
            return mcrypt_create_iv( $size );

        $rnd = '';
        while( $size-- ) $rnd .= chr( mt_rand() );
        return $rnd;
    }

    private function hash( $data )
    {
        return hash( $this->hash, $data, true );
    }

    private function cbc( $v, $k, $d, $e = false )
    {
        $s = $this->cbcsz;
        $n = strlen( $d );
        $k = self::hash( $v . $k );
        $o = $d;

        for( $i = 0, $j = 0; $i < $n; $i++, $j++ )
        {
            if( $j == $s )
            {
                $k = self::hash( substr( $e ? $o : $d, $i - $j, $j ) . $k );
                $j = 0;
            }

            $o[$i] = $d[$i] ^ $k[$j];
        }

        return $o;
    }

    public function cryptex( $data )
    {
        if( $this->ivsz )
        {
            $iv = self::rnd( $this->ivsz );
            $data = self::rnd( $this->ivsz ) . $data;
        }
        else
            $iv = '';

        $key = self::hash( $iv . $this->psw );

        if( $this->macsz )
            $iv .= substr( self::hash( $data . $key ), 0, $this->macsz );

        return $iv . self::cbc( $iv, $key, $data, true );
    }

    public function decryptex( $data )
    {
        if( strlen( $data ) < 2 * $this->ivsz + $this->macsz )
            return false;

        $key = self::hash( substr( $data, 0, $this->ivsz ) . $this->psw );
        $mac = substr( $data, $this->ivsz, $this->macsz );
        $data = self::cbc( substr( $data, 0, $this->ivsz + $this->macsz ),
                           $key, substr( $data, $this->macsz + $this->ivsz ) );

        if( $mac !== substr( self::hash( $data . $key ), 0, $this->macsz ) )
            return false;

        return substr( $data, $this->ivsz );
    }
}
