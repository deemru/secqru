<?php

function get_cryptex( $pass, $ivsz, $macsz, $hash )
{
    require_once '../include/secqru_cryptex.php';
    return new secqru_cryptex( $pass, $ivsz, $macsz, $hash );
}

function test_secqru_cryptex()
{
    $hashes = array( 'md5', 'sha1', 'sha256', 'gost' );
    $pass_sizes = array( 0, 1, 16, 128 );
    $sizes = array( 1, 3, 7, 31, 32, 33, 337, 1337, 4095, 4096, 4097 );
    $ivszs = array( 0, 1, 2, 3, 4, 8, 16, 32 );
    $macszs = array( 0, 1, 2, 3, 4, 8, 16, 32 );
    foreach( $hashes as $hash )
    foreach( $pass_sizes as $pass_size )
    foreach( $ivszs as $ivsz )
    foreach( $macszs as $macsz )
    {
        $pass = '';
        for( $i = 0; $i < $pass_size; $i++ )
            $pass .= chr( mt_rand() );

        $cryptex = get_cryptex( $pass, $ivsz, $macsz, $hash );
        $decryptex = get_cryptex( $pass, $ivsz, $macsz, $hash );

        foreach( $sizes as $size )
        {
            $rnd = '';
            for( $i = 0; $i < $size; $i++ )
                $rnd .= chr( mt_rand() );

            $encoded = $cryptex->cryptex( $rnd );
            $decoded = $decryptex->decryptex( $encoded );

            if( $decoded !== $rnd )
            {
                var_dump( $pass );
                var_dump( $ivsz );
                var_dump( $macsz );
                var_dump( $hash );
                var_dump( bin2hex( $rnd ) );
                var_dump( bin2hex( $encoded ) );
                var_dump( bin2hex( $decoded ) );
                var_dump( $cryptex );
                return 0;
            }
        }
    }

    return 1;
}
$t = microtime( true );
test_secqru_cryptex();
echo sprintf( "%.08f\r\n", microtime( true ) - $t );
