<?php

class secqru_html
{
    private $html = array();
    private $tags = array();
    private $lvl = 0;

    const EOL = PHP_EOL;
    const TAB = '    ';

    public function render()
    {
        while( self::close() ){}

        foreach( $this->html as $pair )
            if( isset( $render ) )
                $render .= self::EOL.str_repeat( self::TAB, $pair[0] ).$pair[1];
            else
                $render = str_repeat( self::TAB, $pair[0] ).$pair[1];

        return $render;
    }

    public function rows( $maxline )
    {
        $rows = 0;

        foreach( $this->html as $pair )
            $rows += floor( strlen( $pair[1] ) / $maxline ) + 1;

        return $rows;
    }

    public function open( $tag, $options = '' )
    {
        $this->html[] = array( $this->lvl, "<$tag$options>" );
        $this->tags[$this->lvl] = $tag;
        $this->lvl++;
    }

    public function open_select( $id )
    {
        self::open( 'select', " name=\"$id\"" );
    }

    public function put_option( $id, $text, $selected )
    {
        $selected = $selected ? ' selected' : '';
        self::put( "<option value=\"$id\"$selected>$text</option>" );
    }

    public function input_full( $type, $name, $size, $max, $value, $style, $is_put = true )
    {
        switch( $style )
        {
            case 'r':
                $style = ' class="ro" readonly'; break;
            case 'e':
                $style = ' class="red"'; break;
            default:
                $style = $style ? $style : '';
        }

        $name = $name ? " name=\"$name\"" : '';
        $size = $size ? " size=\"$size\"" : '';
        $max = $max ? " maxlength=\"$max\"" : '';

        $value = "<input type=\"$type\"$name$size$max value=\"$value\"$style>";

        if( $is_put )
            self::put( $value );
        else
            self::add( $value );
    }

    public function put_input_hidden( $name, $value )
    {
        $value = "<input type=\"hidden\" name=\"$name\" value=\"$value\">";
        self::put( $value );
    }

    public function put_submit( $name, $value, $is_put = true )
    {
        $value = "<input type=\"submit\" name=\"$name\" value=\"$value\">";

        if( $is_put )
            self::put( $value );
        else
            self::add( $value );
    }

    public function put_input( $name, $size, $max, $value, $is_put = true )
    {
        self::input_full( 'text', $name, $size, $max, $value, '', $is_put );
    }

    public function put_input_ro( $size, $value, $is_put = true )
    {
        self::input_full( 'text', false, $size, false, $value, 'r', $is_put );
    }

    public function put( $value, $br = false )
    {
        $br = $br ? '<br>' : '';

        if( isset( $value->html ) )
        {
            while( $value->close() ){}
            foreach( $value->html as $data )
                $this->html[] = array( $this->lvl + $data[0], $data[1] );
        }
        else if( is_array( $value ) )
            foreach( $value as $data )
                $this->html[] = array( $this->lvl, $data.$br );
        else
            $this->html[] = array( $this->lvl, $value.$br );
    }

    public function add( $value, $br = false )
    {
        $br = $br ? '<br>' : '';

        $this->html[ sizeof( $this->html ) - 1 ][1] .= $value.$br;
    }

    public function close( $is_put = true )
    {
        if( $this->lvl == 0 )
            return false;

        $this->html[] = array( --$this->lvl, "</{$this->tags[$this->lvl]}>" );
        return true;
    }
}

?>