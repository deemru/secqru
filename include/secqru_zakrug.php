<?php

class secqru_zakrug
{

    public function zakrug( $img, $radius, $zbegin = 2.8, $zend = 3.0, $ztail = 7, $zclarity = 1.0, $line = true )
    {
        $zdb = self::get_zdb( $radius, $zbegin, $zend, $ztail, $zclarity );
        $ldb = array();

        $w_img = imagesx( $img );
        $h_img = imagesy( $img );

        $x_max = min( $w_img, $radius );
        $y_max = min( $h_img, $radius );

        for( $y = 0; $y < $y_max; $y++ )
            for( $x = $y; $x < $x_max; $x++ )
                if( isset( $zdb[$x][$y] ) )
                {
                    $alpha = $zdb[$x][$y];

                    self::set_alpha_pixel( $img, $x, $y, $alpha );
                    self::set_alpha_pixel( $img, $w_img - 1 - $x, $y, $alpha );
                    self::set_alpha_pixel( $img, $w_img - 1 - $x, $h_img - 1 - $y, $alpha );
                    self::set_alpha_pixel( $img, $x, $h_img - 1 - $y, $alpha );

                    if( $x == $y && $alpha != 127 )
                        $ldb[] = $alpha;
                }

        // x <> y
        for( $x = 0; $x < $x_max; $x++ )
            for( $y = $x + 1; $y < $y_max; $y++ )
                if( isset( $zdb[$y][$x] ) )
                {
                    $alpha = $zdb[$y][$x];

                    self::set_alpha_pixel( $img, $x, $y, $alpha );
                    self::set_alpha_pixel( $img, $w_img - 1 - $x, $y, $alpha );
                    self::set_alpha_pixel( $img, $w_img - 1 - $x, $h_img - 1 - $y, $alpha );
                    self::set_alpha_pixel( $img, $x, $h_img - 1 - $y, $alpha );
                }

        if( $line )
        {
            $n = sizeof( $ldb );
            for( $i = 0; $i < $n; $i++ )
            {
                $alpha = $ldb[$i];

                for( $x = 0; $x < $w_img; $x++ )
                {
                    self::set_alpha_pixel( $img, $x, $i, $alpha );
                    self::set_alpha_pixel( $img, $x, $h_img - $i - 1, $alpha );
                }

                for( $y = 2; $y < $h_img - 2; $y++ )
                {
                    self::set_alpha_pixel( $img, $i, $y, $alpha );
                    self::set_alpha_pixel( $img, $w_img - $i - 1, $y, $alpha );
                }
            }
        }

        return $img;
    }

    public function resize( $img, $w, $wfixed, $h, $hfixed )
    {
        $w_img = imagesx( $img );
        $h_img = imagesy( $img );

        $w_ratio = $w_img / $w;
        $h_ratio = $h_img / $h;

        if( !$wfixed )
        {
            if( !$hfixed )
            {
                if( $w_img < $w && $h_img < $h )
                {
                    return $img;
                }
                else
                {
                    $ratio = $w_ratio > $h_ratio ? $w_ratio : $h_ratio;

                    $w = round( $w_img / $ratio );
                    $h = round( $h_img / $ratio );
                }
            }
            else
            {
                $w = min( $w, round( $w_img / $h_ratio ) );
            }
        }
        else if( !$hfixed )
        {
            $h = min( $h, round( $h_img / $w_ratio ) );
        }

        $img_new = self::create_img( $w, $h );

        $ratio = $w_img / $h_img > $w / $h ? $h_img / $h : $w_img / $w;

        if( imagecopyresampled( $img_new, $img, 0, 0,
                      round( ( $w_img - ( $w * $ratio ) ) / 2 ),
                      round( ( $h_img - ( $h * $ratio ) ) / 2 ),
                      $w, $h, round( $w * $ratio ), round( $h * $ratio ) ) )
        {
            return $img_new;
        }

        return false;
    }

    private function set_alpha_pixel( $image, $x, $y, $alpha_new )
    {
        $color = imagecolorat( $image, $x, $y );
        $alpha = ( $color & 0x7F000000 ) >> 24;

        if( $alpha == 127 )
            return;
        else if( $alpha && $alpha_new != 127 )
            $alpha = $alpha_new + round( $alpha * ( 1 - $alpha_new / 127 ) );
        else
            $alpha = $alpha_new;

        if( $alpha != 127 )
            $color = ( $alpha << 24 ) ^ ( $color & 0xFFFFFF );
        else
            $color = ( $alpha << 24 );

        imagesetpixel( $image, $x, $y, $color );
    }

    private function get_zdb( $radius, $zbegin, $zend, $ztail, $zclarity )
    {
        if( defined( 'SECQRU_CACHE' ) )
        {
            $zdb_cache = SECQRU_CACHE . "zakrug/zdb/$radius-$zbegin-$zend-$ztail-$zclarity";
            if( file_exists( $zdb_cache ) )
                return unserialize( gzinflate( file_get_contents( $zdb_cache ) ) );
        }

        $zdb = array();
        $alpha_last = 0;

        for( $q = $zbegin; $q < $zend + 0.0001; $q += 0.001 )
        {
            $alpha = round( 127 * pow( ( $q - $zbegin ) / ( $zend - $zbegin ), $zclarity ) );

            if( $alpha == $alpha_last )
                continue;
            else
                $alpha_last = $alpha;

            for( $i = 0; $i < $radius; $i += 1 )
            {
                $x = round( $radius - pow( pow( $radius + ( $q - $zbegin ) * $ztail, $q ) - pow( $radius - $i, $q ), 1 / $q ) );
                $y = $i;

                for( $j = $y; $j <= $x; $j++ )
                    $zdb[$j][$y] = $alpha;
            }
        }

        if( defined( 'SECQRU_CACHE' ) )
            file_put_contents( $zdb_cache, gzdeflate( serialize( $zdb ), 9 ) );

        return $zdb;
    }

    private function create_img( $w, $h )
    {
        if( ( $img = imagecreatetruecolor( $w, $h ) ) )
        {
            imagealphablending( $img, false );
            imagesavealpha( $img, true );
            return $img;
        }

        return false;
    }

}

?>
