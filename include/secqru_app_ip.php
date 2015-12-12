<?php

class secqru_app_ip
{
    private $w;

    const ATTRS = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

    private function getMainIP()
    {
        foreach( self::ATTRS as $attr )
            if( !empty( $_SERVER[$attr] ) )
                return $_SERVER[$attr];
    }

    public function __construct( &$w )
    {
        $this->w = &$w;
    }

    public function get_title()
    {
        return self::getMainIP();
    }

    private function ip_render( $attr, &$out )
    {
        if( empty( $_SERVER[$attr] ) )
            return;

        $html =
'%s:<div style="font-size: 36">
    %s — %s — %s — %s — %s — %s
</div>';

        $ip = $_SERVER[$attr];
        $whois = "<a href=\"http://api.hackertarget.com/whois/?q=$ip\">whois</a>";
        $geoip = "<a href=\"http://api.hackertarget.com/geoip/?q=$ip\">geoip</a>";
        $dns = "<a href=\"http://api.hackertarget.com/reversedns/?q=$ip\">dns</a>";
        $ping = "<a href=\"http://api.hackertarget.com/nping/?q=$ip\">ping</a>";
        $mtr = "<a href=\"http://api.hackertarget.com/mtr/?q=$ip\">trace</a>";

        if( $out )
            $out .= '<br>'.SECQRU_EOL;
        $out .= sprintf( $html, $attr, $ip, $whois, $geoip, $dns, $ping, $mtr );
    }

    public function html()
    {
        $out = '';

        self::ip_render( 'SERVER_ADDR', $out );

        foreach( self::ATTRS as $attr )
            self::ip_render( $attr, $out );

        return explode( SECQRU_EOL, $out );
    }
}

?>