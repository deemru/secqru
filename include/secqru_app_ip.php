<?php

class secqru_app_ip
{
    private $w;

    private static $ATTRS = array( 'REMOTE_ADDR',
                                   'HTTP_CLIENT_IP',
                                   'HTTP_X_FORWARDED_FOR',
                                   'HTTP_FORWARDED_FOR',
                                   'HTTP_X_FORWARDED',
                                   'HTTP_FORWARDED',
                                   'HTTP_FORWARDED_FOR_IP',
                                   'HTTP_VIA',
                                   'HTTP_PROXY_CONNECTION',
                                   'CLIENT_IP',
                                   'X_FORWARDED_FOR',
                                   'FORWARDED_FOR',
                                   'X_FORWARDED',
                                   'FORWARDED',
                                   'FORWARDED_FOR_IP',
                                   'VIA',
                                   'PROXY_CONNECTION' );

    private function getMainIP()
    {
        foreach( self::$ATTRS as $attr )
            if( !empty( $_SERVER[$attr] ) )
                return $_SERVER[$attr];
    }

    public function __construct( &$w )
    {
        $this->w = &$w;
    }

    // deprecated
    public function get_title()
    {
        return self::getMainIP();
    }

    // api rework
    public function prep()
    {
        $this->w->set_title( self::getMainIP() );
    }

    private function ip_render( $attr, &$out )
    {
        if( empty( $_SERVER[$attr] ) )
            return;

        $html =
'<div style="text-align: center">
    <div style="font-size: 36; margin: 1em auto;">%s:<br>
        <h1>%s</h1>%s — %s — %s — %s — %s<br>
    </div>
</div>';

        $ip = $_SERVER[$attr];
        $whois = "<a href=\"http://api.hackertarget.com/whois/?q=$ip\">whois</a>";
        $geoip = "<a href=\"http://api.hackertarget.com/geoip/?q=$ip\">geoip</a>";
        $dns = "<a href=\"http://api.hackertarget.com/reversedns/?q=$ip\">dns</a>";
        $ping = "<a href=\"http://api.hackertarget.com/nping/?q=$ip\">ping</a>";
        $mtr = "<a href=\"http://api.hackertarget.com/mtr/?q=$ip\">trace</a>";

        if( $out )
            $out .= '<br>' . SECQRU_EOL;
        $out .= sprintf( $html, $attr, $ip, $whois, $geoip, $dns, $ping, $mtr );
    }

    public function html()
    {
        $out = '';

        foreach( self::$ATTRS as $attr )
            self::ip_render( $attr, $out );

        return explode( SECQRU_EOL, $out );
    }
}
