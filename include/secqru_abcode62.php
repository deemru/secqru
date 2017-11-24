<?php

class secqru_abcode62
{
    private $cc;
    private $cs;

    static private $ab62 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct( $cc = '0', $cs = 0 )
    {
        $this->cc = $cc;
        $this->cs = $cs;
    }

    public function encode( $data )
    {
        return self::base64_clean( base64_encode( $data ) );
    }

    public function decode( $data )
    {
        if( false === ( $data = self::base64_restore( $data ) ) )
            return false;

        return base64_decode( $data );
    }

    private function base64_clean( $data )
    {
        $cc = $this->cc;
        $cs = $this->cs;
        $l = strlen( $data );
        $out = '';

        for( $i = 0; $i < $l; $i++ )
        {
            $c = $data[$i];

            if( $c == $cc )
                $n = 0;
            elseif( $c == '+' )
                $n = 1;
            elseif( $c == '/' )
                $n = 2;
            elseif( $c == '=' )
                continue;
            else
            {
                $out .= $c;
                continue;
            }

            $c = self::$ab62[ ( $cs + ( mt_rand() % 20 ) * 3 + $n ) % 62 ];
            $out .= $cc . $c;
            $cc = self::$ab62[ ( $cs + ord( $cc ) + ord( $c ) ) % 62 ];
            $cs++;
        }

        return $out;
    }

    private function base64_restore( $data )
    {
        $cc = $this->cc;
        $cs = $this->cs;
        $l = strlen( $data );
        $out = '';

        for( $i = 0; $i < $l; $i++ )
        {
            $c = $data[$i];

            if( $c != $cc )
            {
                $out .= $c;
                continue;
            }

            if( ++$i == $l )
                return false;

            $c = ord( $data[$i] );
            if( $c >= 48 && $c <= 57 )
                $n = ( $c + 14 - ( $cs % 62 ) ) % 62 % 3;
            else if( $c >= 97 && $c <= 122 )
                $n = ( $c - 25 - ( $cs % 62 ) ) % 62 % 3;
            else if( $c >= 65 && $c <= 90 )
                $n = ( $c + 33 - ( $cs % 62 ) ) % 62 % 3;
            else
                return false;

            if( $n == 0 )
                $out .= $cc;
            elseif( $n == 1 )
                $out .= '+';
            else
                $out .= '/';

            $cc = self::$ab62[ ( $cs + ord( $cc ) + $c ) % 62 ];
            $cs++;
        }

        return $out;
    }
}
