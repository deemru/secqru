<?php

class secqru_app_zakrug
{
    private $w;

    public function __construct( $w )
    {
        $this->w = $w;

        if( defined( 'SECQRU_CACHE' ) )
        {
            if( !file_exists( SECQRU_CACHE . 'zakrug' ) )
                mkdir( SECQRU_CACHE . 'zakrug' );
            if( !file_exists( SECQRU_CACHE . 'zakrug/ready' ) )
                mkdir( SECQRU_CACHE . 'zakrug/ready' );
            if( !file_exists( SECQRU_CACHE . 'zakrug/upload' ) )
                mkdir( SECQRU_CACHE . 'zakrug/upload' );
            if( !file_exists( SECQRU_CACHE . 'zakrug/zdb' ) )
                mkdir( SECQRU_CACHE . 'zakrug/zdb' );
        }
    }

    private $u_w;
    private $u_h;
    private $u_w_fix;
    private $u_h_fix;
    private $u_radius;
    private $u_noline;
    private $u_zbegin;
    private $u_zend;
    private $u_ztail;
    private $u_zclarity;

    private $uploaded_file;
    private $u_ff;
    private $u_foutput;
    private $img;

    private function show_img()
    {
        $imgsrc = $this->w->get_dns( 'imgsrc', false );

        if( !$imgsrc )
            return;

        $imgsrc = $this->w->decryptex( $imgsrc );

        if( $imgsrc && file_exists( $imgsrc ) )
        {
            header( 'Content-Type: image/png' );
            echo file_get_contents( $imgsrc );
        }

        exit;
    }

    private function get_img_user()
    {
        if( !empty( $_FILES['userfile']['tmp_name'] ) )
        {
            $img_type = $_FILES['userfile']['type'];
            $img_file = $_FILES['userfile']['tmp_name'];

            $img = self::get_img_uploaded( $img_file, $img_type );

            if( !defined( 'SECQRU_CACHE' ) )
                return $img;

            if( $img )
            {
                do
                {
                    $fileid = date('Ymd_His_') . $this->w->rndhex( 3 );
                    $this->uploaded_file = SECQRU_CACHE . "zakrug/upload/$fileid.$img_type";
                }
                while( file_exists( $this->uploaded_file ) );

                if( !move_uploaded_file( $_FILES['userfile']['tmp_name'], $this->uploaded_file ) )
                {
                    $this->w->log( 'move_uploaded_file', 3 );
                }
                else
                {
                    $this->u_ff = $this->w->cryptex( $this->uploaded_file );
                }
            }
        }

        if( !defined( 'SECQRU_CACHE' ) )
            return false;

        if( $this->w->get_set( 'fc' ) )
            $this->w->clear( 'ff' );

        if( !empty( $_POST['ff'] ) && !isset( $this->uploaded_file ) )
        {
            $this->u_ff = $this->w->get_dns( 'ff', false );
            $this->uploaded_file = $this->w->decryptex( $this->u_ff );

            if( $this->uploaded_file && !file_exists( $this->uploaded_file ) )
            {
                $this->uploaded_file = false;
                $this->u_ff = false;
                $this->w->log( 'file not found', 2 );
                return false;
            }

            $fileid = substr( $this->uploaded_file, -26, -4 );
        }

        if( defined( 'SECQRU_CACHE' ) && isset( $fileid ) )
        {
            $params = "{$this->u_w}_{$this->u_h}_{$this->u_w_fix}_{$this->u_h_fix}_{$this->u_noline}_{$this->u_radius}_{$this->u_zbegin}_{$this->u_zend}_{$this->u_ztail}_{$this->u_zclarity}";
            $this->u_foutput = SECQRU_CACHE . "zakrug/ready/{$fileid}__($params).png";
        }

        if( isset( $img ) )
        {
            return $img;
        }
        else if( !empty( $this->uploaded_file ) )
        {
            if( defined( 'SECQRU_CACHE' ) )
            {
                if( file_exists( $this->u_foutput ) )
                    return true;
            }

            $img_type = substr( $this->uploaded_file, -3 );
            return self::get_img_uploaded( $this->uploaded_file, $img_type );
        }
    }

    public function pre()
    {
        $this->show_img();

        // common parameters
        $this->u_w = $this->w->get_int( 'w:max width', 1600, 1, 9999 );
        $this->u_h = $this->w->get_int( 'h:max height', 1200, 1, 9999 );
        $this->u_w_fix = $this->w->get_set( 'wfix' );
        $this->u_h_fix = $this->w->get_set( 'hfix' );

        $this->u_radius = $this->w->get_int( 'zr:radius', round( min( $this->u_w, $this->u_h )/10 ), 0, 9999 );
        $this->u_noline = $this->w->get_set( 'nl' );
        $this->u_zbegin = $this->w->get_int( 'zb:zbegin', 28, 10, 998 );
        $this->u_zend = $this->w->get_int( 'ze:zend', 30, $this->u_zbegin + 1, min( $this->u_zbegin + 40, 999 ) );
        $this->u_ztail = $this->w->get_int( 'zt:ztail', 7, 1, 99 );
        $this->u_zclarity = $this->w->get_int( 'zc:zclarity', 10, 1, 99 );

        if( !defined( 'GD_VERSION' ) )
        {
            $this->w->log( 'PHP GD is required', 1 );
            return;
        }

        $this->img = $this->get_img_user();

        // zakrug
        if( $this->img )
        {
            if( !isset( $this->u_foutput ) || !file_exists( $this->u_foutput ) )
            {
                $this->img = self::resize_img( $this->img, $this->u_w, $this->u_w_fix, $this->u_h, $this->u_h_fix );
                if( $this->u_radius )
                {
                    $this->img = self::zakrug( $this->img, $this->u_radius, $this->u_zbegin / 10, $this->u_zend / 10, $this->u_ztail, $this->u_zclarity / 10, !$this->u_noline );
                }

                if( !defined( 'SECQRU_CACHE' ) )
                {
                    header( 'Content-Type: image/png' );
                    exit( imagepng( $this->img ) );
                }

                imagepng( $this->img, $this->u_foutput );
            }
        }
    }

    public function put_buttons( secqru_html $html )
    {
        $html->add( ' — ' );
        $html->put_submit( 'help', 'help' );
        $html->put_submit( 'reset', 'reset' );
    }

    public function html()
    {
        self::pre();

        $html = new secqru_html();

        $html->open( 'table' );
        $html->open( 'tr' );
        {
            $html->open( 'td', ' valign="top" align="left"' );
            $html->open( 'div');

            $html->put_input( 'w', 4, 4, $this->u_w );
            $html->add( ' ' );
            $html->put_checkbox( 'wfix', $this->u_w_fix, 0 );
            $html->add( ' — ширина (максимальная/точная)', true );

            $html->put_input( 'h', 4, 4, $this->u_h );
            $html->add( ' ' );
            $html->put_checkbox( 'hfix', $this->u_h_fix, 0 );
            $html->add( ' — высота (максимальная/точная)', true );

            $html->put_input( 'zr', 4, 4, $this->u_radius );
            $html->add( ' ' );
            $html->put_checkbox( 'nl', $this->u_noline, 0 );
            $html->add( ' — радиус закругления (только углы)', true );

            $html->put_input( 'zb', 3, 3, $this->u_zbegin );
            $html->add( ' ' );
            $html->put_input( 'ze', 3, 3, $this->u_zend, false );
            $html->add( ' ' );
            $html->put_input( 'zt', 2, 2, $this->u_ztail, false );
            $html->add( ' ' );
            $html->put_input( 'zc', 2, 2, $this->u_zclarity, false );
            $html->add( ' — параметры изгиба', true );
            $html->put( '', true );

            if( $this->img && !empty( $this->u_ff ) )
            {
                $html->put( 'Ваш файл:', true );
                $html->put_input_hidden( 'ff', $this->u_ff );
                $html->input_full( 'text', false, 32, 32, substr( $this->uploaded_file, -26 ), 'r', true );
                $html->put_submit( 'fc', 'x', true, true );
            }
            else
            {
                $html->put( 'Загрузите файл:', true );
                $html->put_file( 'userfile', true, true );
            }

            $html->put_submit( 'zakrug', 'Выполнить' );

            $html->close();
            $html->close();
        }
        {
            $html->open( 'td', ' valign="top" align="right"' );
            $html->open( 'div', ' class="textarea" style="min-height: 40em"' );

            if( !isset( $this->img ) && $this->w->get_set( 'zakrug' ) )
                $this->w->log( 'Загрузите файл', 1 );

            if( !empty( $this->w->log ) )
            {
                $html->put( $this->w->log, 1 );
            }

            if( isset( $this->img ) && $this->img )
            {
                if( !empty( $this->w->log ) )
                    $html->put( '<hr>' );

                $imgsrc = $this->w->cryptex( $this->u_foutput );
                $imgsrc = $this->w->cryptex( serialize( array( 'imgsrc' => $imgsrc ) ) );
                $imgsrc = SECQRU_ADDR . 'zakrug/link/' . $imgsrc . '/' . substr( $this->u_foutput, strrpos( $this->u_foutput, '/' ) + 1 );
                $html->put( "<img src=\"$imgsrc\" style=\"width: 100%\">" );
            }

            $html->close();
            $html->close();
        }
        $html->close();

        return $html;
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

                for( $j = $y; $j < $x; $j++ )
                    $zdb[$j][$y] = $alpha;
            }
        }

        if( defined( 'SECQRU_CACHE' ) )
            file_put_contents( $zdb_cache, gzdeflate( serialize( $zdb ), 9 ) );

        return $zdb;
    }

    private function zakrug( $img, $radius, $zbegin = 2.8, $zend = 3.0, $ztail = 7, $zclarity = 0.7, $line = true )
    {
        $zdb = $this->get_zdb( $radius, $zbegin, $zend, $ztail, $zclarity );
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
                    self::set_alpha_pixel( $this->img, $x, $i, $alpha );
                    self::set_alpha_pixel( $this->img, $x, $h_img - $i - 1, $alpha );
                }

                for( $y = 2; $y < $h_img - 2; $y++ )
                {
                    self::set_alpha_pixel( $this->img, $i, $y, $alpha );
                    self::set_alpha_pixel( $this->img, $w_img - $i - 1, $y, $alpha );
                }
            }
        }

        return $img;
    }

    private function resize_img( $img, $w, $wfixed, $h, $hfixed )
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

    private function get_img_uploaded( $file, &$type )
    {
        if( file_exists( $file ) )
        {
            $funcs = array( 'imagecreatefrompng', 'imagecreatefromjpeg', 'imagecreatefromgif' );
            $types = array( 'png', 'jpg', 'gif' );

            switch( $type )
            {
                default:
                // case 'png':
                // case 'image/png':
                    $t = 0; break;
                case 'jpg':
                case 'image/jpeg':
                    $t = 1; break;
                case 'gif';
                case 'image/gif':
                    $t = 2; break;
            }

            for( $i = 0; $i < 3; $i++ )
            {
                // skip warnings here
                $temp = error_reporting();
                error_reporting( 0 );
                {
                    $img = $funcs[ ( $i + $t ) % 3 ]( $file );
                }
                error_reporting( $temp );

                if( $img )
                {
                    // PHP 5.4 >>>>
                    if( !function_exists( 'imagepalettetotruecolor' ) )
                    {
                        if( !imageistruecolor( $img ) )
                        {
                            $img_true = imagecreatetruecolor( imagesx( $img ), imagesy( $img ) );
                            imagecopy( $img_true, $img, 0, 0, 0, 0, imagesx( $img ), imagesy( $img ) );
                            $img = $img_true;
                        }
                    }
                    else
                    // <<<< PHP 5.4
                    imagepalettetotruecolor( $img );
                    imagealphablending( $img, false );
                    imagesavealpha( $img, true );
                    $type = $types[ ( $i + $t ) % 3 ];
                    return $img;
                }
            }
        }

        $this->w->log( 'Неизвестный формат файла', 2 );
        return false;
    }
}

?>
