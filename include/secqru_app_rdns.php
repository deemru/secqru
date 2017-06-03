<?php

class secqru_app_rdns
{
    private $w;
    private $ns;
    private $ip;

    const FORMSIZE = 33;

    public function __construct( secqru_worker $w )
    {
        $this->w = $w;
    }

    public function init()
    {
        $this->ns = $this->w->get_ip( 'ns', '77.88.8.8' );

        if( ( $this->ip = $this->w->get_addend( ) ) )
            $this->ip = $this->ip[ 0 ];
        else
            $this->ip = $this->w->get_ip( 'ip', $_SERVER['REMOTE_ADDR'] );
    }

    public function link()
    {
        
    }

    public function html( secqru_html $html )
    {
        $html->put( '<hr>' );
        $html->open( 'table' );
        $html->open( 'tr' );
        $html->open( 'td', ' valign="top" align="left"' );
        $html->open( 'div');

        $html->put_input( 'ns', 15, 15, $this->ns );
        $html->add( ' — name server', 1 );
        $html->put_input( 'ip', 15, 15, $this->ip );
        $html->put_submit( 'save', 'rdns' );        

        $html->close( 2 );
        $html->open( 'td', ' valign="top" align="right"' );
        $html->open( 'div', ' class="textarea"' );

        if( $this->w->log )
            $html->put( $this->w->log, 1 );

        $html->put( self::rdns() );

        $html->close( 3 );
    }

    private function rdns()
    {
        require_once 'secqru_rdns.php';
        return ( new secqru_rdns() )->rdns( $this->ns, $this->ip );
    }
}
