<?php

class secqru_app_ddns
{
    private $w;
    private $db;

    private const FORMSIZE = 33;

    private const STATUS_NULL = 0;
    private const STATUS_PDDTOKEN = 1;
    private const STATUS_DOMAINS = 2;
    private const STATUS_DOMAIN = 3;
    private const STATUS_RECORDS = 4;
    private const STATUS_RECORD = 5;
    private const STATUS_DNSEDIT = 6;
    private const STATUS_DDNSLINK = 7;

    private $last_selected;

    public function __construct( secqru_worker $w )
    {
        $this->w = $w;
        $this->db = $this->w->get_db();

        if( empty( $this->db['status'] ) )
            $this->db['status'] = self::STATUS_NULL;
    }

    public function put_buttons( secqru_html $html )
    {
        $html->add( ' — ' );
        $html->put_submit( 'help', 'help' );
        $html->put_submit( 'reset', 'reset' );
    }

    private function yandexapi( $function )
    {
        $url = 'https://pddimp.yandex.ru/api2/admin/';

        switch( $function )
        {
            case 'domains':
                $url .= 'domain/domains';
                break;

            case 'records':
                $url .= "dns/list?domain={$this->db['domain']}";
                break;

            case 'dnsedit':
                $url .= 'dns/edit';
                $fields = array( 'domain' => $this->db['domain'],
                                 'record_id' => $this->db['id'],
                                 'content' => $this->db['ip'] );
                break;

            default:
                exit( $this->w->log( 'bad yandexapi', 3 ) );
        }

        if( false == ( $ch = curl_init() ) )
            exit( $this->w->log( 'curl_init failed', 3 ) );

        if( false == curl_setopt_array( $ch, array (
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 10,
            CURLOPT_HTTPHEADER      => array( "PddToken: {$this->db['token']}" ),
            CURLOPT_CAINFO          => 'var/ca/yandex.ru',
            CURLOPT_URL             => $url
        ) ) )
            exit( $this->w->log( 'curl_setopt_array failed', 3 ) );

        if( isset( $fields ) && false == curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields ) )
            exit( $this->w->log( 'curl_setopt failed', 3 ) );

        $json = curl_exec( $ch );
        $curl_time = ( int )( curl_getinfo( $ch, CURLINFO_TOTAL_TIME ) * 1000 );

        if( 0 != ( $errorCode = curl_errno( $ch ) ) )
            $errorMsg = curl_error( $ch );

        curl_close( $ch );

        if( $errorCode )
        {
            $this->w->log( "curl_errno = $errorCode ($errorMsg)", 2 );
            return false;
        }

        $this->w->log( "curl_exec ($curl_time ms)", 7 );

        if( !( $json = json_decode( $json ) ) )
        {
            $this->w->log( 'json_decode failed', 2 );
            return false;
        }

        if( empty( $json->{'success'} ) )
        {
            $this->w->log( 'json bad format', 2 );
            return false;
        }

        if( $json->{'success'} == 'error' )
        {
            $this->w->log( empty( $json->{'error'} ) ? 'json bad format' : $json->{'error'}, 2 );
            return false;
        }

        return $json;
    }

    private function get_domains()
    {
        if( !( $json = self::yandexapi( 'domains' ) ) )
            return false;

        if( !isset( $json->{'domains'} ) ||
            !is_array( $json->{'domains'} ) )
        {
            $this->w->log( "json bad format", 2 );
            return false;
        }

        $this->db['domains'] = array();
        $domains = $json->{'domains'};
        $count = sizeof( $domains );
        for( $i = 0; $i < $count; $i++ )
        {
            if( empty( $domains[$i]->{'name'} ) )
            {
                $this->w->log( "json bad format", 2 );
                return false;
            }

            $this->db['domains'][$i] = $domains[$i]->{'name'};
        }

        if( $count )
            sort( $this->db['domains'] );

        return true;
    }

    private function get_records()
    {
        if( !( $json = self::yandexapi( 'records' ) ) )
            return false;

        if( !isset( $json->{'records'} ) ||
            !is_array( $json->{'records'} ) )
        {
            $this->w->log( "json bad format", 2 );
            return false;
        }

        $records = $json->{'records'};
        $count = sizeof( $records );
        for( $i = 0, $n = 0; $i < $count; $i++ )
        {
            if( empty( $records[$i]->{'type'} ) ||
                empty( $records[$i]->{'fqdn'} ) ||
                empty( $records[$i]->{'record_id'} ) ||
                empty( $records[$i]->{'content'} ) )
            {
                $this->db['records'] = array();
                $this->w->log( "json bad format", 2 );
                return false;
            }

            if( $records[$i]->{'type'} == 'A' || $records[$i]->{'type'} == 'CNAME' )
            {
                $this->db['records'][$n] = $records[$i]->{'fqdn'};
                $this->db['ids'][$n] = $records[$i]->{'record_id'};
                $this->db['ips'][$n] = $records[$i]->{'content'};
                $n++;
            }
        }

        return true;
    }

    public function prep()
    {
        if( $this->w->get_set( 't' ) && $this->w->get_set( 'd' ) && $this->w->get_set( 'i' ) )
        {
            $this->db['token'] = $this->w->get_dns( 't', '' );
            $this->db['domain'] = $this->w->get_dns( 'd', '' );
            $this->db['id'] = $this->w->get_dns( 'i', '' );
            $this->db['ip'] = $_SERVER['REMOTE_ADDR'];
            $this->w->log = array();

            if( self::yandexapi( 'dnsedit' ) )
                header( $_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500 );

            header( 'Content-Type: text/plain' );
            foreach( $this->w->log as $string )
                echo $string . PHP_EOL;
            exit;
        }

        if( $this->w->get_set( 'reset' ) )
        {
            $this->w->reset();
            $this->w->log( 'reset to defaults', 7 );
            $this->db = array();
            $this->db['status'] = self::STATUS_NULL;
        }
        else if( $this->w->get_set( 'tknx' ) )
        {
            $this->db = array();
            $this->db['status'] = self::STATUS_NULL;
        }
        else if( $this->w->get_set( 'dmnx' ) )
        {
            $temp = $this->db;
            $this->db = array();
            $this->db['token'] = $temp['token'];
            $this->last_selected = $temp['domain'];
            $this->db['status'] = self::STATUS_PDDTOKEN;
        }
        else if( $this->w->get_set( 'recx' ) )
        {
            $temp = $this->db;
            $this->db = array();
            $this->db['token'] = $temp['token'];
            $this->db['domain'] = $temp['domain'];
            $this->last_selected = $temp['record'];
            $this->db['status'] = self::STATUS_DOMAIN;
        }

        switch( $this->db['status'] )
        {
            case self::STATUS_NULL:

                $this->db['status'] = self::STATUS_PDDTOKEN;
                return;

            case self::STATUS_PDDTOKEN:

                if( empty( $this->db['token'] ) )
                {
                    $token = $this->w->get_dns( 'tkn:PddToken', false );
                    if( $token == false )
                    {
                        $this->w->log( 'PddToken required', 1 );
                        return;
                    }

                    $this->db['token'] = $token;
                }

                if( !self::get_domains() )
                {
                    unset( $this->db['token'] );
                    return;
                }

                $this->db['status'] = self::STATUS_DOMAINS;

            case self::STATUS_DOMAINS:

                $this->db['status'] = self::STATUS_DOMAIN;
                return;

            case self::STATUS_DOMAIN:

                if( empty( $this->db['domain'] ) )
                {
                    $domain = $this->w->get_dns( 'dmn:Domain', false );
                    if( $domain == false ||
                        in_array( $domain, $this->db['domains'] ) == false )
                    {
                        $this->w->log( 'Domain selection failed', 1 );
                        return;
                    }

                    $this->db['domain'] = $domain;
                }

                if( !self::get_records() )
                {
                    unset( $this->db['domain'] );
                    return;
                }

                unset( $this->db['domains'] );
                $this->db['status'] = self::STATUS_RECORDS;

            case self::STATUS_RECORDS:

                $this->db['status'] = self::STATUS_RECORD;
                return;

            case self::STATUS_RECORD:

                if( empty( $this->db['record'] ) )
                {
                    $record = $this->w->get_dns( 'rec:DNS record', false );
                    if( $record == false ||
                        in_array( $record, $this->db['records'] ) == false )
                    {
                        $this->w->log( 'DNS record selection failed', 1 );
                        return;
                    }

                    $count = sizeof( $this->db['records'] );
                    for( $i = 0; $i < $count; $i++ )
                        if( $record == $this->db['records'][$i] )
                            break;

                    $this->db['record'] = $record;
                    $this->db['ip'] = $this->db['ips'][$i];
                    $this->db['id'] = $this->db['ids'][$i];
                }

                unset( $this->db['records'] );
                unset( $this->db['ips'] );
                unset( $this->db['ids'] );
                $this->db['status'] = self::STATUS_DNSEDIT;
                return;

            case self::STATUS_DNSEDIT:

                if( $this->w->get_set( 'ddns' ) )
                {
                    $temp = array();
                    $temp['t'] = $this->db['token'];
                    $temp['d'] = $this->db['domain'];
                    $temp['i'] = $this->db['id'];

                    $this->w->log( 'use DDNS-LINK on an intendent device only', 1 );
                    $this->w->log( '# ВНИМАНИЕ: используйте DDNS-LINK только на целевом устройстве', 8 );
                    $this->w->log( '', 8 );
                    $this->w->log( "# DDNS-LINK: <a href=\"{$this->w->get_special_link( $temp )}\">{$this->db['record']}</a>", 8 );
                    return;
                }

                if( $this->w->get_set( 'ipv' ) == false )
                    return;

                $ip = $this->w->get_dns( 'ip:record_ip', false );

                if( $ip == false )
                {
                    $this->w->log( 'ip broken', 1 );
                    return;
                }
                
                if( $ip == $this->db['ip'] )
                {
                    $this->w->log( 'ip not changed', 1 );
                    return;
                }

                $temp = $this->db['ip'];
                $this->db['ip'] = $ip;

                if( !self::yandexapi( 'dnsedit' ) )
                {
                    $this->db['ip'] = $temp;
                    return;
                }

                $this->db['status'] = self::STATUS_DNSEDIT;
                return;

            default:
                exit( $this->w->log( "unknown status", 3 ) );
        }
    }

    public function html()
    {
        $html = new secqru_html();

        $html->open( 'table' );
        $html->open( 'tr' );
        $html->open( 'td', ' valign="top" align="left"' );
        $html->open( 'div');

        for( ;; )
        {
            $html->put_input_hidden( 'db', $this->w->put_db( $this->db ) );
            $html->put_input_ro( self::FORMSIZE, 'pdd.yandex.ru' );
            $html->add( ' — DDNS provider', 1 );

            if( $this->db['status'] == self::STATUS_PDDTOKEN )
            {
                $html->input_full( 'password', 'tkn', self::FORMSIZE, 64, false, 'PddToken' );
                $html->put_submit( 'save', 'v' );
                $html->add( ' — <a href="https://tech.yandex.ru/pdd/doc/concepts/access-docpage/">PddToken</a>', 1 );
                break;
            }

            $html->put_input_ro( self::FORMSIZE, str_repeat( '*', self::FORMSIZE ) );
            $html->put_submit( 'tknx', 'x' );
            $html->add( ' — PddToken', 1 );

            if( $this->db['status'] == self::STATUS_DOMAIN )
            {
                $html->open_select( 'dmn' );
                $count = sizeof( $this->db['domains'] );

                if( $count )
                {
                    if( !empty( $this->last_selected ) )
                        $selected = $this->last_selected;
                    else if( !empty( $this->db['domain'] ) )
                        $selected = $this->db['domain'];
                    else
                        $selected = $this->db['domains'][0];

                    for( $i = 0; $i < $count; $i++ )
                    {
                        $option = $this->db['domains'][$i];
                        $view = $option;
                        if( strlen( $view ) > self::FORMSIZE )
                            $view = substr( $view, 0, self::FORMSIZE - 3 ) . '...' ;
                        else if( $i == 0 )
                            $view = $view . str_repeat( '&nbsp;', self::FORMSIZE - strlen( $view ) );

                        $html->put_option( $option, $view, $option == $selected );
                    }
                }

                $html->close();
                $html->put_submit( 'save', 'v' );
                $html->add( ' — Domain', 1 );
                break;
            }

            $html->put_input_ro( self::FORMSIZE, $this->db['domain'] );
            $html->put_submit( 'dmnx', 'x' );
            $html->add( ' — Domain', 1 );

            if( $this->db['status'] == self::STATUS_RECORD )
            {
                $html->open_select( 'rec' );
                $count = sizeof( $this->db['records'] );

                if( $count )
                {
                    if( !empty( $this->last_selected ) )
                        $selected = $this->last_selected;
                    else if( !empty( $this->db['record'] ) )
                        $selected = $this->db['record'];
                    else
                        $selected = $this->db['records'][0];

                    for( $i = 0; $i < $count; $i++ )
                    {
                        $option = $this->db['records'][$i];
                        $view = $option;
                        if( strlen( $view ) > self::FORMSIZE )
                            $view = substr( $view, 0, self::FORMSIZE - 3 ) . '...' ;
                        else if( $i == 0 )
                            $view = $view . str_repeat( '&nbsp;', self::FORMSIZE - strlen( $view ) );

                        $html->put_option( $option, $view, $option == $selected );
                    }
                }

                $html->close();
                $html->put_submit( 'save', 'v' );
                $html->add( ' — DNS record', 1 );
                break;
            }

            $html->put_input_ro( self::FORMSIZE, $this->db['record'] );
            $html->put_submit( 'recx', 'x' );
            $html->add( ' — DNS record', 1 );

            if( $this->db['status'] == self::STATUS_DNSEDIT )
            {
                if( $this->w->get_set( 'ipx' ) )
                {
                    $html->put_input( 'ip', self::FORMSIZE, self::FORMSIZE, $this->db['ip'] );
                    $html->put_submit( 'ipv', 'v', 1 );
                    $html->add( ' — DNS value', 1 );
                }
                else
                {
                    $html->put_input_ro( self::FORMSIZE, $this->db['ip'] );
                    $html->put_submit( 'ipx', 'x', 1 );
                    $html->add( ' — DNS value', 1 );
                    $html->put_submit( 'ddns', 'DDNS-LINK', 1 );
                }
            }

            break;
        }
        $html->close();
        $html->close();
        {
            $html->open( 'td', ' valign="top" align="right"' );

            if( $this->w->log )
            {
                $html->open( 'div', ' class="textarea"' );

                if( $this->w->log )
                    $html->put( $this->w->log, 1 );

                $html->close();
            }
            $html->close();
        }
        $html->close();

        return $html;
    }
}
