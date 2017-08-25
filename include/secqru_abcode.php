<?php

class secqru_abcode
{
    private $a;
    private $amap;
    private $b;
    private $bmap;
    private $gmp = false;

    public function __construct( $alphabet, $base = false /* binary */ )
    {
        if( function_exists( 'gmp_add' ) )
            $this->gmp = true;

        $this->a = $alphabet;
        $this->amap = self::map( $alphabet );

        if( $base === false ) // binary
        {
            $base = '';
            for( $i = 0; $i < 256; $i++ )
                $base[$i] = chr( $i );
        }
        $this->b = $base;
        $this->bmap = self::map( $base );
    }

    public function encode( $data )
    {
        for( $i = 0, $n = strlen( $data ); $i < $n; $i++ )
            if( !isset( $this->bmap[ $data[$i] ] ) )
                return false;

        if( $this->gmp )
            return self::convert_gmp( $data, $this->bmap, $this->a );
        else
            return self::convert_bc( $data, $this->bmap, $this->a );
    }

    public function decode( $data )
    {
        for( $i = 0, $n = strlen( $data ); $i < $n; $i++ )
            if( !isset( $this->amap[ $data[$i] ] ) )
                return false;

        if( $this->gmp )
            return self::convert_gmp( $data, $this->amap, $this->b );
        else
            return self::convert_bc( $data, $this->amap, $this->b );
    }

    private function map( $alphabet )
    {
        $map = array();
        for( $i = 0, $n = strlen( $alphabet ); $i < $n; $i++ )
            $map[ $alphabet[$i] ] = $i;
        return $map;
    }

    private function convert_gmp( $data, $from, $to )
    {
        $q = gmp_init( sizeof( $from ) );
        $b = gmp_init( $from[ $data[0] ] );

        for( $i = 1, $n = strlen( $data ); $i < $n; $i++ )
            $b = gmp_add( $from[ $data[$i] ], gmp_mul( $b, $q ) );

        $q = gmp_init( strlen( $to ) );
        $z = gmp_init( 0 );

        for( $i = 0;; $i++ )
        {
            $data[$i] = $to[ gmp_intval( gmp_mod( $b, $q ) ) ];
            $b = gmp_div( $b, $q );
            if( !gmp_cmp( $b, $z ) )
                break;
        }

        return strrev( substr( $data, 0, $i + 1 ) );
    }

    private function convert_bc( $data, $from, $to )
    {
        $q = sizeof( $from );
        $b = $from[ $data[0] ];

        for( $i = 1, $n = strlen( $data ); $i < $n; $i++ )
            $b = bcadd( $from[ $data[$i] ], bcmul( $b, $q ) );

        $q = strlen( $to );

        for( $i = 0;; $i++ )
        {
            $data[$i] = $to[ bcmod( $b, $q ) ];
            $b = bcdiv( $b, $q );
            if( !$b )
                break;
        }

        return strrev( substr( $data, 0, $i + 1 ) );
    }
}
