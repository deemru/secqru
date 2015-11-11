<?php

class secqru_flock
{
    private $fp;
    private $filename;
    private $delay;
    private $timeout;

    public function __construct( $filename,
                          $delay = 100000 /* 0.1 sec */,
                          $timeout = 1000000 /* 1 sec */ )
    {
        $this->fp = 0;
        $this->filename = $filename;
        $this->delay = $delay;
        $this->timeout = $timeout;
    }

    public function __destruct()
    {
        self::close();
    }

    public function open( $access = 'a+' )
    {
        self::close();

        $this->fp = fopen( $this->filename, $access );

        if( !$this->fp )
            return 0;

        $timer = 0;
        do
        {
            if( flock( $this->fp, LOCK_EX | LOCK_NB ) )
                return 1;

            usleep( $this->delay );
            $timer += $this->delay;
        }
        while( $timer < $this->timeout );

        return 0;
    }

    public function close()
    {
        if( $this->fp )
        {
            flock( $this->fp, LOCK_UN );
            fclose( $this->fp );
            $this->fp = 0;
        }
    }

    public function append( $data )
    {
        self::close();

        if( !self::open( 'a+' ) )
            return 0;

        fwrite( $this->fp, $data );
        self::close();
        return 1;
    }
}

?>
