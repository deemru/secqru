<?php

class secqru_app_tiklan
{
    private $w;

    const formsize = 33;
    const max_routers = 64;

    function __construct( &$w )
    {
        $this->w = &$w;
    }

    private function ipv4_pos( $cidr )
    {
        return  ( 0xFFFFFFFF << 32 - $cidr ) & 0xFFFFFFFF;
    }

    private function ipv4_neg( $cidr )
    {
        return ~( 0xFFFFFFFF << 32 - $cidr ) & 0xFFFFFFFF;
    }
    
    function html()
    {
        // RESET
        if( $this->w->get_set( 'reset' ) )
        {
            unset( $_POST );
            $this->w->log( 'reset to defaults', 7 );
        }

        // GLOBAL NETWORK ADDRESS
        $g_lan = $this->w->get_dns( 'g_lan:bridge name', 'TikLAN' );

        // GLOBAL PASSWORD SEED
        $g_psw = $this->w->get_dns( 'g_psw:password seed', function(){ return $this->w->rndhex( 8 ); } );
        $g_psw_prefix = substr( sha1( $g_psw ), -8 ) . '_';

        // GLOBAL IP RANGE
        $g_rng = $this->w->get_ip2long( 'g_rng:broadcast domain', ip2long( '192.168.160.0' ) );

        // GLOBAL IP CIDR (MASK)
        $g_cidr = $this->w->get_int( 'g_cidr:broadcast cidr', 20, 16, 22 );

        // SUBNET COUNTER INIT
        $temp = 0;
        if( $this->w->get_set( 'netadd' ) )
        {
            $temp++;
            $this->w->log( 'routers++', 7 );
        }
        if( $this->w->get_set( 'netsub' ) )
        {
            $temp--;
            $this->w->log( 'routers--', 7 );
        }
        $subnum = $this->w->get_int( 'subnum:router count', 3, 2, self::max_routers, $temp );

        // SUBNET COUNTER CORRECTION BY CIDR (MASK)
        $temp = floor( self::ipv4_neg( $g_cidr ) / 256 );
        if( $subnum > $temp )
        {
            $subnum = $temp;
            $this->w->log( "maximum \"router count\" for /$g_cidr = \"$subnum\"", 1 );
        }

        // GLOBAL RANGE CALCULATION
        $g_rng = $g_rng & self::ipv4_pos( $g_cidr );
        $g_rng_end = $g_rng | self::ipv4_neg( $g_cidr ) - 1;
        $g_horizon = ( ( $g_rng & 0x7FFFFFFF ) % 97 ) + 1;

        // GLOBAL RANGE CHANGED?
        if( !$this->w->get_set( 'g_rw' ) ||
            $g_rng == $this->w->get_ip2long( 'g_rw:global range', false ) )
            $g_changed = false;
        else
        {
            $g_changed = true;

            for( $i = 1; $i <= $subnum; $i++ )
                $this->w->clear( "subnet$i" );
            $this->w->clear( 'g_eoip' );

            if( $this->w->get_set( 'g_rw' ) )
                $this->w->log( 'global range changed', 1 );
        }

        // GLOBAL EOIP TUNNEL ID SEED
        $g_eoip = ( $g_rng & 0x7FFFFFFF ) % 60000;
        $g_eoip = $this->w->get_int( 'g_eoip:tunnel id seed', $g_eoip, 0, 60000 );

        // GLOBAL VPN
        $g_vpn = $this->w->get_dns( 'g_vpn:VPN protocol', 'L2TP' );
        $vpn_protocols = array( 'L2TP' => 'l2tp', 'PPTP' => 'pptp',
                                'SSTP' => 'sstp', 'OVPN' => 'ovpn' );
        if( !array_key_exists( $g_vpn, $vpn_protocols ) )
        {
            $g_vpn = 'L2TP';
            $this->w->ezlog( 'default', 'VPN protocol', $g_vpn, 1 );
        }

        // SUBNET PARAMETERS CYCLE
        $subnets = array();
        for( $i = 0; $i <= $subnum; $i++ )
        {
            // SUBNET NAME
            $subnets[$i]['name'] = $this->w->get_dns( "name$i:router name $i", sprintf( 'Tik-%02d', $i ) );

            // SUBNET PUBLIC ADDRESS or DNS
            $subnets[$i]['pub'] = $this->w->get_dns( "pub$i:public address $i", sprintf( '%s.sn.mynetname.net', $subnets[$i]['name'] ) );

            // SUBNET is PUBLIC?
            $subnets[$i]['is_pub'] = $this->w->get_int( "is_pub$i:public status $i", $i == 1 ? true : false );

            // SUBNET PUBLIC SWITCH
            switch( $this->w->get_dns( "sw_pub$i:public address switcher $i", false ) )
            {
                case 'v':
                    $subnets[$i]['is_pub'] = true;
                    $this->w->ezlog( 'switch', $subnets[$i]['name'], 'public', 7 );
                    break;
                case 'x':
                    $subnets[$i]['is_pub'] = false;
                    $this->w->ezlog( 'switch', $subnets[$i]['name'], 'private', 7 );
                    break;
            }

            // SUBNET DISTANCE (PRIORITY)
            $subnets[$i]['dist'] = $this->w->get_int( "dist$i:router distance $i", 10, 1, 240 );

            // SUBNET IP RANGE
            $subnets[$i]['subnet'] = $this->w->get_ip2long( "subnet$i", $g_rng + ( $i * 256 ) );

            // SUBNET CIDR (MASK)
            $subnets[$i]['subnet_cidr'] = $this->w->get_int( "subcidr$i", 24 );

            // SUBNET IP RANGE CALCULATION
            $subnets[$i]['subnet'] = $subnets[$i]['subnet'] & self::ipv4_pos( $subnets[$i]['subnet_cidr'] );
            $subnets[$i]['subnet_end'] = $subnets[$i]['subnet'] | self::ipv4_neg( $subnets[$i]['subnet_cidr'] ) - 1;

            // SUBNET PARAMETERS
            $subnets[$i]['addr_subnet'] = long2ip( $subnets[0]['subnet'] );
            $subnets[$i]['addr_gw'] = long2ip( $subnets[$i]['subnet'] + 1 );
            $subnets[$i]['addr_id'] = ( $subnets[$i]['subnet'] >> 8 ) & 0xFF;
            $subnets[$i]['addr_vpn'] = long2ip( $subnets[0]['subnet'] + $subnets[$i]['addr_id'] );
            $subnets[$i]['addr_dhcp_first'] = long2ip( $subnets[$i]['subnet'] + 10 );
            $subnets[$i]['addr_dhcp_last'] = long2ip( $subnets[$i]['subnet_end'] );
            $subnets[$i]['dhcp_pool_name'] = "$g_lan ({$subnets[$i]['name']}) Pool";
            $subnets[$i]['dhcp_server_name'] = "$g_lan ({$subnets[$i]['name']}) DHCP";
            $subnets[$i]['psw'] = $g_psw_prefix . substr( sha1( $g_psw.$subnets[$i]['name'] ), -8 );
        }

        // TUNNEL ID CALC
        $temp = $g_eoip;
        for( $i = 1; $i <= $subnum; $i++ )
        {
            $subnets[$i]['eoip_mark'] = $temp + $i;
            $temp += self::max_routers - $i - 2;
        }

        // NEW STATION SELECTED?
        $g_sel = $this->w->get_int( 'g_sel:selected station', 0 );
        $subnets[0]['name'] = '...'.str_repeat( '&nbsp;', self::formsize - 3 );

        if( $g_sel != 0 && $g_sel > $subnum )
        {
            $g_sel = 0;
            $this->w->log( 'new router out of range', 1 );
        }

        // CHECK PUBLICS AT LEAST 1
        $nopublic = true;
        for( $i = 1; $i <= $subnum; $i++ )
        {
            if( $subnets[$i]['is_pub'] )
            {
                $nopublic = false;
                break;
            }
        }

        if( $nopublic )
            $this->w->log( 'no public addresses', 2 );

        // CHECK NAMES NOT EQUAL
        for( $i = 1; $i <= $subnum; $i++ )
        {
            if( !isset( $subnets[$i]['name_ok'] ) )
                $subnets[$i]['name_ok'] = true;

            for( $s = $i + 1; $s <= $subnum; $s++ )
            {
                if( $subnets[$i]['name'] == $subnets[$s]['name'] )
                {
                    $subnets[$i]['name_ok'] = false;
                    $subnets[$s]['name_ok'] = false;
                    $this->w->log( "router names $i and $s are equal \"{$subnets[$i]['name']}\"", 2 );
                    break;
                }
            }
        }

        // CHECK DNS NOT EQUAL
        for( $i = 1; $i <= $subnum; $i++ )
        {
            if( !isset( $subnets[$i]['pub_ok'] ) )
                $subnets[$i]['pub_ok'] = true;

            for( $s = $i + 1; $s <= $subnum; $s++ )
            {
                if( $subnets[$i]['is_pub'] && $subnets[$s]['is_pub'] &&
                    $subnets[$i]['pub'] == $subnets[$s]['pub'] )
                {
                    $subnets[$i]['pub_ok'] = false;
                    $subnets[$s]['pub_ok'] = false;
                    $this->w->log( "public addresses $i and $s are equal \"{$subnets[$i]['pub']}\"", 2 );
                    break;
                }
            }
        }

        // CHECK IP RANGE INTERSECTIONS
        for( $i = 1; $i <= $subnum; $i++ )
        {
            if( !isset( $subnets[$i]['subnet_ok'] ) )
                $subnets[$i]['subnet_ok'] = true;

            if( $subnets[$i]['subnet'] < $g_rng || $subnets[$i]['subnet'] > $g_rng_end ||
                $subnets[$i]['subnet_end'] < $g_rng || $subnets[$i]['subnet_end'] > $g_rng_end  )
            {
                $subnets[$i]['subnet_ok'] = false;
                $this->w->log( "router range $i is not in global range", 2 );
            }
            else
            {
                for( $s = $i + 1; $s <= $subnum; $s++ )
                {
                    if( ( $subnets[$i]['subnet'] >= $subnets[$s]['subnet'] && $subnets[$i]['subnet'] < $subnets[$s]['subnet_end'] ) ||
                        ( $subnets[$i]['subnet_end'] >= $subnets[$s]['subnet'] && $subnets[$i]['subnet_end'] < $subnets[$s]['subnet_end'] ) )
                    {
                        $subnets[$i]['subnet_ok'] = false;
                        $subnets[$s]['subnet_ok'] = false;
                        $this->w->log( "router ranges $i and $s intersect", 2 );
                        break;
                    }
                }
            }
        }

        // RAW view
        $raw = $this->w->get_raw();
        if( $raw && ( $raw < 1 || $raw > $subnum ) )
            exit( $this->w->log( "raw out of range", 3 ) );

        if( $raw )
        {
            $router_config = self::get_config( $g_lan, $g_cidr, $g_vpn, $vpn_protocols, $g_horizon, $subnets, $subnum, $g_sel, $raw );

            header('Content-Type: text/plain');

            foreach( $router_config as $line )
                echo $line.PHP_EOL;
            echo ' ';

            exit;
        }

        if( $this->w->get_set( 'save' ) )
            $this->w->log( 'save', 7 );

        // DISPLAY HELP / RESET/ LOG
        if( $this->w->get_set( 'help' ) || !$this->w->log )
        {
            $help = explode( PHP_EOL, '# HELP:

* <a href="https://github.com/deemru/secqru/wiki/tiklan">Tiklan</a> is available on github

* This app makes broadcast domains between Mikrotik routers
* Minimum requirements: 2 routers + 1 public ip address
* You can use a default configuration from scratch
* Just correct public ip addresses
* In the end you get "'.$g_lan.'" bridge
* Test it, add ports, enjoy!

* Contact — deem@deem.ru' );
        }
        else
        {
            $help = 0;
        }

        $html = new secqru_html();

        $html->put( '<hr>' );
        $html->open( 'table' );
        $html->open( 'tr' );
        {
            $html->open( 'td', ' valign="top" align="left"' );
            $html->open( 'div');

            $html->put_input( 'g_lan', self::formsize, 50, $g_lan, 'bridge name' );
            $html->add( ' — bridge name', 1 );

            $html->put_input_hidden( 'g_rw', long2ip( $g_rng ) );
            $html->put_input( 'g_rng', 15, 15, long2ip( $g_rng ), false );
            $html->add( ' / ' );
            $html->put_input( 'g_cidr', 2, 2, $g_cidr, false );
            $html->add( ' — broadcast domain', 1 );

            $html->put_input( 'subnum', 2, 2, $subnum );
            $html->put_submit( 'netadd', '+' );
            $html->put_submit( 'netsub', '-' );
            $html->add( ' — router count', 1 );

            $html->open_select( 'g_vpn' );
            {
                foreach( $vpn_protocols as $key => $val )
                    $html->put_option( $key, $key, $g_vpn == $key );
            }
            $html->close();

            $html->put_submit( 'save', 'v' );
            $html->add( ' — VPN protocol', 1 );

            $html->put_input( 'g_eoip', 5, 5, $g_eoip );
            $html->add( ' — tunnel id seed', 1 );

            $html->put_input_ro( self::formsize, long2ip( $g_rng + 1 ).' — '.long2ip( $g_rng_end ) );
            $html->add( ' — address pool', 1 );

            $html->put_input_ro( self::formsize, long2ip( $subnets[0]['subnet'] + 1 ).' — '.long2ip( $subnets[0]['subnet_end'] ) );
            $html->add( ' — VPN pool', 1 );

            $html->put_input( 'g_psw', self::formsize, 50, $g_psw );
            $html->add( ' — password seed', 1 );

            $html->open_select( 'g_sel' );
            {
                for( $i = 0; $i <= $subnum; $i++ )
                {
                    $temp = $subnets[$i]['name'];
                    if( $i && strlen( $temp ) > self::formsize )
                        $temp = substr( $temp, 0, self::formsize - 3 ).'...' ;

                    $html->put_option( $i, $temp, $g_sel == $i );
                }
            }
            $html->close();

            $html->put_submit( 'save', 'v' );
            $html->add( ' — new router' );
            $html->close();
            $html->close();
        }
        {
            $html->open( 'td', ' valign="top" align="right"' );

            if( $this->w->log || $help )
            {
                $html->open( 'div', ' class="textarea"' );

                if( $this->w->log )
                    $html->put( $this->w->log, 1 );

                if( $help )
                    $html->put( $help, 1 );

                if( !$raw )
                {
                    $raw_link = $this->w->get_raw_link();
                    if( $raw_link )
                    {
                        $html->put( '', 1 );
                        for( $i = 1; $i <= $subnum; $i++ )
                            $html->put( "# RAW: <a href=\"$raw_link$i\">{$subnets[$i]['name']}</a>", 1 );
                    }
                }
                $html->close();
            }
            $html->close();
        }
        $html->close();

        // SUBNET PARAMETERS
        for( $i = 1; $i <= $subnum; $i++ )
        {
            $html->open( 'tr' );
            $html->open( 'td', ' colspan="2"' );
            $html->put( '<hr>' );
            $html->close();
            $html->close();

            $html->open( 'tr');
            $html->open( 'td', ' valign="top"' );
            $html->open( 'div' );
            {
                $html->input_full( 'text', "name$i", self::formsize, 50, $subnets[$i]['name'], $subnets[$i]['name_ok'] ? '' : 'e' );
                $html->add( ' — router name', 1 );

                $html->put_input_hidden( "is_pub$i", $subnets[$i]['is_pub'] ? '1' : '0' );
                $html->input_full( 'text', "pub$i", self::formsize, 50, $subnets[$i]['pub'], $subnets[$i]['is_pub'] ? ( $subnets[$i]['pub_ok'] ? '' : 'e' ) : ( $nopublic ? 'e' : 'r' ) );
                $html->put_submit( "sw_pub$i", $subnets[$i]['is_pub'] ? 'x' : 'v' );
                $html->add( ' — public address', 1 );

                $html->put_input( "dist$i", 3, 3, $subnets[$i]['dist'] );
                $html->add( ' — router distance', 1 );

                $html->input_full( 'text', "subnet$i", 15, 15, long2ip( $subnets[$i]['subnet'] ), $subnets[$i]['subnet_ok'] ? '' : 'e' );
                $html->add( ' / ' );
                $html->input_full( 'text', 0, 2, 0, $subnets[$i]['subnet_cidr'], 'r', 0 );
                $html->add( ' — local range', 1 );

                $html->input_full( 'text', 0, 15, 0, $subnets[$i]['addr_gw'], 'r' );
                $html->add( ' — LAN address', 1 );

                $html->input_full( 'text', 0, 15, 0, $subnets[$i]['addr_vpn'], 'r' );
                $html->add( ' — VPN address', 1 );

                $html->input_full( 'text', 0, self::formsize, 0, "{$subnets[$i]['addr_dhcp_first']} - {$subnets[$i]['addr_dhcp_last']}", 'r' );
                $html->add( ' — DHCP pool', 1 );
            }
            $html->close();
            $html->close();

            $html_config = self::get_config( $g_lan, $g_cidr, $g_vpn, $vpn_protocols, $g_horizon, $subnets, $subnum, $g_sel, $i );

            $html->open( 'td', ' valign="top" align="right"' );
            $html->open( 'div', ' class="textarea"' );
            $html->put( self::get_config( $g_lan, $g_cidr, $g_vpn, $vpn_protocols, $g_horizon, $subnets, $subnum, $g_sel, $i ), 1 );
            $html->close();
            $html->close();
            $html->close();
        }

        return $html;
    }

    private function get_config( $g_lan, $g_cidr, $g_vpn, $vpn_protocols, $g_horizon, $subnets, $subnum, $g_sel, $i )
    {
        $html_config = array();

        // GATEWAY OR NEWBIE
        if( !$g_sel || ( $g_sel && $g_sel == $i ) )
        {
            // GW
            $html_config[] = "# GW ({$subnets[$i]['name']})";
            $html_config[] = "interface bridge add name=\"$g_lan\" comment=\"$g_lan\" protocol-mode=none";
            $html_config[] = "ip address add address={$subnets[$i]['addr_gw']}/$g_cidr interface=\"$g_lan\"";
            $html_config[] = "ip route add dst-address={$subnets[0]['addr_subnet']}/24 type=unreachable distance=250";
            $html_config[] = '';

            // DHCP
            $html_config[] = "# DHCP ({$subnets[$i]['name']})";
            $html_config[] = "ip pool add ranges={$subnets[$i]['addr_dhcp_first']}-{$subnets[$i]['addr_dhcp_last']} name=\"{$subnets[$i]['dhcp_pool_name']}\"";
            $html_config[] = "ip dhcp-server network add address={$subnets[$i]['addr_subnet']}/$g_cidr gateway={$subnets[$i]['addr_gw']} dns-server={$subnets[$i]['addr_gw']}";
            $html_config[] = "ip dhcp-server add name=\"{$subnets[$i]['dhcp_server_name']}\" interface=\"$g_lan\" address-pool=\"{$subnets[$i]['dhcp_pool_name']}\"";
            $html_config[] = "ip dhcp-server enable \"{$subnets[$i]['dhcp_server_name']}\"";
            $html_config[] = '';
        }

        // PPP PROFILE ADD
        $ppp_profile_name = 'default-encryption';
        $ppp_timeout = '17';

        // PPP SECRET ADD
        $ppp_users = array();
        $ppp_servers = array();
        $ppp_clients = array();
        $ppp_routes = array();

        for( $s = 1; $s <= $subnum; $s++ )
        {
            if( $i != $s && ( !$g_sel || ( $g_sel && $g_sel == $s ) || ( $g_sel && $g_sel == $i ) ) )
            {
                $is_client = false;
                $is_server = false;

                if( $subnets[$s]['is_pub'] )
                {
                    if( !$subnets[$i]['is_pub'] )
                    {
                        $is_client = true;
                    }
                    else
                    {
                        if( $subnets[$i]['dist'] > $subnets[$s]['dist'] || ( $subnets[$i]['dist'] == $subnets[$s]['dist'] && $i > $s ) )
                        {
                            $is_client = true;
                        }
                        else
                        {
                            $is_server = true;
                        }
                    }
                }
                else if( $subnets[$i]['is_pub'] )
                {
                    $is_server = true;
                }

                if( $is_client )
                {
                    $ppp_client_name = "$g_lan-$g_vpn-Client-{$subnets[$s]['name']}";
                    $ppp_routes[] = "ip route add dst-address={$subnets[0]['addr_subnet']}/24 gateway=$ppp_client_name distance={$subnets[$s]['dist']}";
                    $ppp_clients[] = "interface {$vpn_protocols[$g_vpn]}-client add connect-to=\"{$subnets[$s]['pub']}\" name=\"$ppp_client_name\" user=\"$g_lan-{$subnets[$i]['name']}\" password=\"{$subnets[$i]['psw']}\" profile=\"$ppp_profile_name\" keepalive-timeout=$ppp_timeout disabled=no";
                    $ppp_clients[] = "ip neighbor discovery set \"$ppp_client_name\" discover=no";
                }
                else if( $is_server )
                {
                    $ppp_server_name = "$g_lan-$g_vpn-Server-{$subnets[$s]['name']}";
                    $ppp_users[] = "ppp secret add name=\"$g_lan-{$subnets[$s]['name']}\" password=\"{$subnets[$s]['psw']}\" profile=\"$ppp_profile_name\" local-address={$subnets[$i]['addr_vpn']} remote-address={$subnets[$s]['addr_vpn']} routes=\"{$subnets[0]['addr_subnet']}/24 {$subnets[$s]['addr_vpn']} {$subnets[$s]['dist']}\"";
                    $ppp_servers[] = "interface {$vpn_protocols[$g_vpn]}-server add name=\"$ppp_server_name\" user=\"$g_lan-{$subnets[$s]['name']}\"";
                    $ppp_servers[] = "ip neighbor discovery set \"$ppp_server_name\" discover=no";
                }
            }
        }

        // PPP Server
        if( sizeof( $ppp_users ) || sizeof( $ppp_servers ) )
        {
            $html_config[] = "# PPP Server ({$subnets[$i]['name']})";
            $html_config[] = "interface {$vpn_protocols[$g_vpn]}-server server set enabled=yes";
            $html_config = array_merge( $html_config, $ppp_users );
            $html_config =  array_merge( $html_config, $ppp_servers );
            $html_config[] = '';
        }

        // PPP Client
        if( sizeof( $ppp_clients ) || sizeof( $ppp_routes ) )
        {
            $html_config[] = "# PPP Client ({$subnets[$i]['name']})";
            $html_config = array_merge( $html_config, $ppp_clients );
            $html_config =  array_merge( $html_config, $ppp_routes );
            $html_config[] = '';
        }

        // EOIP ADD
        $eoip_interface = array();
        $eoip_to_bridge = '';
        $eoip_bridge_filter = '';
        for( $s = 1; $s <= $subnum; $s++ )
        {
            if( $i != $s && ( !$g_sel || ( $g_sel && $g_sel == $s ) || ( $g_sel && $g_sel == $i ) ) )
            {
                $eoip_name = "$g_lan-EoIP-{$subnets[$s]['name']}";
                $eoip_tunnel_id = $subnets[ min( $i, $s ) ]['eoip_mark'] + max( $i, $s ) - 2;

                $eoip_interface[] = "interface eoip add name=\"$eoip_name\" remote-address={$subnets[$s]['addr_vpn']} tunnel-id=$eoip_tunnel_id keepalive=7,3";
                $eoip_interface[] = "ip neighbor discovery set \"$eoip_name\" discover=no";
                $eoip_to_bridge[] = "interface bridge port add bridge=\"$g_lan\" interface=\"$eoip_name\" horizon=$g_horizon";
                $eoip_bridge_filter[] = "interface bridge nat add chain=dstnat in-interface=\"$eoip_name\" mac-protocol=ip ip-protocol=udp src-port=67-68 action=drop";
            }
        }

        $html_config[] = "# EoIP ({$subnets[$i]['name']})";
        $html_config = array_merge( $html_config, $eoip_interface );
        $html_config[] = '';

        $html_config[] = "# EoIP to Bridge ({$subnets[$i]['name']})";
        $html_config = array_merge( $html_config, $eoip_to_bridge );
        $html_config[] = '';

        $html_config[] = "# EoIP filter DHCP ({$subnets[$i]['name']})";
        $html_config = array_merge( $html_config, $eoip_bridge_filter );

        return $html_config;
    }
}

?>
