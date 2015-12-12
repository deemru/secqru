<?php

class secqru_html
{
    private $strs = array();
    private $tabs = array();
    private $tags = array();
    private $lvl = 0;

    public function render()
    {
        while( self::close() ){}

        $render = '';

        for( $i = 0, $n = sizeof( $this->strs ); $i < $n; $i++ )
        {
            if( strlen( $this->strs[$i] ) )
                $render .= str_repeat( '    ', $this->tabs[$i] ).$this->strs[$i];

            $render .= PHP_EOL;
        }

        return $render;
    }

    public function rows( $maxline )
    {
        $rows = 0;

        for( $i = 0, $n = sizeof( $this->strs ); $i < $n; $i++ )
            $rows += floor( strlen( $this->strs[$i] ) / $maxline ) + 1;

        return $rows;
    }

    public function open( $tag, $options = '' )
    {
        $this->strs[] = "<$tag$options>";
        $this->tabs[] = $this->lvl;
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

    public function input_full( $type, $name, $size, $max, $value, $style, $is_put = true, $is_br = false )
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

        $name = $name !== false ? " name=\"$name\"" : '';
        $size = $size !== false ? " size=\"$size\"" : '';
        $max = $max !== false ? " maxlength=\"$max\"" : '';
        $value = $value !== false ? " value=\"$value\"" : '';

        $value = "<input type=\"$type\"$name$size$max$value$style>";

        if( $is_put )
            self::put( $value, $is_br );
        else
            self::add( $value, $is_br );
    }

    public function put_input_hidden( $name, $value )
    {
        $value = "<input type=\"hidden\" name=\"$name\" value=\"$value\">";
        self::put( $value );
    }

    public function put_submit( $name, $value, $is_put = true, $is_br = false )
    {
        $value = "<input type=\"submit\" name=\"$name\" value=\"$value\">";

        if( $is_put )
            self::put( $value, $is_br );
        else
            self::add( $value, $is_br );
    }

    public function put_input( $name, $size, $max, $value, $is_put = true )
    {
        self::input_full( 'text', $name, $size, $max, $value, '', $is_put );
    }

    public function put_checkbox( $name, $value, $is_put = true )
    {
        self::input_full( 'checkbox', $name, false, false, $value, ' class="checkbox"' . ( $value ? ' checked' : '' ), $is_put );
    }

    public function put_file( $name, $is_put = true, $is_br = false )
    {
        self::input_full( 'file', $name, false, false, false, ' class="file"', $is_put, $is_br );
    }

    public function put_input_ro( $size, $value, $is_put = true )
    {
        self::input_full( 'text', false, $size, false, $value, 'r', $is_put );
    }

    public function put( $value, $br = false )
    {
        $br = $br ? '<br>' : '';

        if( is_a( $value, get_class() ) )
        {
            while( $value->close() ){}
            for( $i = 0, $n = sizeof( $value->strs ); $i < $n; $i++ )
            {
                $this->strs[] = $value->strs[$i];
                $this->tabs[] = $value->tabs[$i] + $this->lvl;
            }
        }
        else if( is_array( $value ) )
            foreach( $value as $data )
            {
                $this->strs[] = $data.$br;
                $this->tabs[] = $this->lvl;
            }
        else
        {
            $this->strs[] = $value.$br;
            $this->tabs[] = $this->lvl;
        }
    }

    public function add( $value, $br = false )
    {
        $br = $br ? '<br>' : '';

        $this->strs[ sizeof( $this->strs ) - 1 ] .= $value.$br;
    }

    public function close()
    {
        if( $this->lvl == 0 )
            return false;

        $this->lvl--;
        $this->strs[] = "</{$this->tags[$this->lvl]}>";
        $this->tabs[] = $this->lvl;
        return true;
    }
}

?>
