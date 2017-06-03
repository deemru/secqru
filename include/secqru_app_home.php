<?php

class secqru_app_home
{
    private $w;

    public function __construct( secqru_worker $w )
    {
        $this->w = $w;
    }

    public function html( secqru_html $html )
    {
        if( $this->w->log )
        {
            $html->put( '<hr>' );
            $html->open( 'div', ' align="right"' );
            $html->open( 'div', ' class="textarea"' );
            $html->put( $this->w->log );
            $html->close( 2 );
        }
    }
}
