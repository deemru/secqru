<?php

class secqru_app_tiklan
{
    private $w;

    const formsize = 33;

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
        $subnum = $this->w->get_int( 'subnum:router count', 3, 2, 64, $temp );

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
        $g_horizon = abs( $g_rng % 99 ) + 1;

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
        $g_eoip = $g_rng >> 8 & 0xFFFF;
        $g_eoip = $this->w->get_int( 'g_eoip:tunnel id seed', $g_eoip, 0, 62000 );

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
            $temp += $subnum - $i - 2;
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

        if( $this->w->get_set( 'save' ) )
            $this->w->log( 'save', 7 );

        // DISPLAY HELP / RESET/ LOG
        $help = array();
        if( $this->w->get_set( 'help' ) )
        {
            $help = '# HELP:
* This web application helps you to setup, maintain and expand a single broadcast domain between your routers.
* It needs at least 2 routers with at least 1 public ip address on one of them.
* Setup appropriate parameters on the left side, then use corresponding generated scripts.
* The final result is "'.$g_lan.'" bridge, which represents your single broadcast domain.
* Test this bridge with ping and stuff, if everything is fine add ports to it and enjoy.
* Do not forget to save your configuration with "link" button so you can easily add more routers later.

* Have a question? Found a bug? Have an improvement idea? Contact — deem@deem.ru';
            $help = explode( '
', $help );
        }

        if( !$this->w->log && !$help )
            $this->w->log( 'no action', 7 );

        $html_select = new secqru_html();
        $html_select->open_select( 'g_sel' );

        // SELECTED STATION
        for( $i = 0; $i <= $subnum; $i++ )
        {
            $temp = $subnets[$i]['name'];
            if( $i && strlen( $temp ) > self::formsize )
                $temp = substr( $temp, 0, self::formsize - 3 ).'...' ;

            $html_select->put_option( $i, $temp, $g_sel == $i );
        }

        $html_select->close();

        $html_vpn = new secqru_html();
        $html_vpn->open_select( 'g_vpn' );

        foreach( $vpn_protocols as $key => $val )
            $html_vpn->put_option( $key, $key, $g_vpn == $key );

        $html_vpn->close();

        $html_setup = new secqru_html();

        $html_setup->put( '<hr>' );
        $html_setup->open( 'table' );
        $html_setup->open( 'tr' );
        {
            $html_setup->open( 'td', ' valign="top" align="left"' );
            $html_setup->open( 'div');

            $html_setup->put_input( 'g_lan', self::formsize, 50, $g_lan, 'bridge name' );
            $html_setup->add( ' — bridge name', 1 );

            $html_setup->put_input_hidden( 'g_rw', long2ip( $g_rng ) );
            $html_setup->put_input( 'g_rng', 15, 15, long2ip( $g_rng ), false );
            $html_setup->add( ' / ' );
            $html_setup->put_input( 'g_cidr', 2, 2, $g_cidr, false );
            $html_setup->add( ' — broadcast domain', 1 );

            $html_setup->put_input( 'subnum', 2, 2, $subnum );
            $html_setup->put_submit( 'netadd', '+' );
            $html_setup->put_submit( 'netsub', '-' );
            $html_setup->add( ' — router count', 1 );

            $html_setup->put( $html_vpn );
            $html_setup->put_submit( 'save', 'v' );
            $html_setup->add( ' — VPN protocol', 1 );

            $html_setup->put_input( 'g_eoip', 5, 5, $g_eoip );
            $html_setup->add( ' — tunnel id seed', 1 );

            $html_setup->put_input_ro( self::formsize, long2ip( $g_rng + 1 ).' — '.long2ip( $g_rng_end ) );
            $html_setup->add( ' — address pool', 1 );

            $html_setup->put_input_ro( self::formsize, long2ip( $subnets[0]['subnet'] + 1 ).' — '.long2ip( $subnets[0]['subnet_end'] ) );
            $html_setup->add( ' — VPN pool', 1 );

            $html_setup->put_input( 'g_psw', self::formsize, 50, $g_psw );
            $html_setup->add( ' — password seed', 1 );

            $html_setup->put( $html_select );
            $html_setup->put_submit( 'save', 'v' );
            $html_setup->add( ' — new router' );
            $html_setup->close();
            $html_setup->close();
        }
        {
            $html_setup->open( 'td', ' valign="top" align="right"' );
            $html_setup->open( 'div', ' class="textarea"' );
            $html_setup->put( $this->w->log, 1 );
            $html_setup->put( $help, 1 );
            $html_setup->close();
            $html_setup->close();
        }
        $html_setup->close();

        // SUBNET PARAMETERS
        for( $i = 1; $i <= $subnum; $i++ )
        {
            $html_setup->open( 'tr' );
            $html_setup->open( 'td', ' colspan="2"' );
            $html_setup->put( '<hr>' );
            $html_setup->close();
            $html_setup->close();

            $html_setup->open( 'tr');
            $html_setup->open( 'td', ' valign="top"' );
            $html_setup->open( 'div' );
            {
                $html_setup->input_full( 'text', "name$i", self::formsize, 50, $subnets[$i]['name'], $subnets[$i]['name_ok'] ? '' : 'e' );
                $html_setup->add( ' — router name', 1 );

                $html_setup->put_input_hidden( "is_pub$i", $subnets[$i]['is_pub'] ? '1' : '0' );
                $html_setup->input_full( 'text', "pub$i", self::formsize, 50, $subnets[$i]['pub'], $subnets[$i]['is_pub'] ? ( $subnets[$i]['pub_ok'] ? '' : 'e' ) : ( $nopublic ? 'e' : 'r' ) );
                $html_setup->put_submit( "sw_pub$i", $subnets[$i]['is_pub'] ? 'x' : 'v' );
                $html_setup->add( ' — public address', 1 );

                $html_setup->put_input( "dist$i", 3, 3, $subnets[$i]['dist'] );
                $html_setup->add( ' — router distance', 1 );

                $html_setup->input_full( 'text', "subnet$i", 15, 15, long2ip( $subnets[$i]['subnet'] ), $subnets[$i]['subnet_ok'] ? '' : 'e' );
                $html_setup->add( ' / ' );
                $html_setup->input_full( 'text', 0, 2, 0, $subnets[$i]['subnet_cidr'], 'r', 0 );
                $html_setup->add( ' — local range', 1 );

                $html_setup->input_full( 'text', 0, 15, 0, $subnets[$i]['addr_gw'], 'r' );
                $html_setup->add( ' — LAN address', 1 );

                $html_setup->input_full( 'text', 0, 15, 0, $subnets[$i]['addr_vpn'], 'r' );
                $html_setup->add( ' — VPN address', 1 );

                $html_setup->input_full( 'text', 0, self::formsize, 0, "{$subnets[$i]['addr_dhcp_first']} - {$subnets[$i]['addr_dhcp_last']}", 'r' );
                $html_setup->add( ' — DHCP pool', 1 );
            }
            $html_setup->close();
            $html_setup->close();

            $html_config = new secqru_html();

            if( !$raw )
            {
                $raw_link = $this->w->get_raw_link( $i );
                if( $raw_link )
                    $html_config->put( "# RAW: <a href=\"$raw_link\">{$subnets[$i]['name']}</a>", 1 );
            }

            // GATEWAY OR NEWBIE
            if( !$g_sel || ( $g_sel && $g_sel == $i ) )
            {
                // GW
                $html_config->put( "# GW ({$subnets[$i]['name']})", 1 );
                $html_config->put( "interface bridge add name=\"$g_lan\"", 1 );
                $html_config->put( "ip address add address={$subnets[$i]['addr_gw']}/$g_cidr interface=\"$g_lan\"", 1 );
                $html_config->put( "ip route add dst-address={$subnets[0]['addr_subnet']}/24 type=unreachable distance=250", 1 );
                $html_config->put ( '', 1 );

                // DHCP
                $html_config->put( "# DHCP ({$subnets[$i]['name']})", 1 );
                $html_config->put( "ip pool add ranges={$subnets[$i]['addr_dhcp_first']}-{$subnets[$i]['addr_dhcp_last']} name=\"{$subnets[$i]['dhcp_pool_name']}\"", 1 );
                $html_config->put( "ip dhcp-server network add address={$subnets[$i]['addr_subnet']}/{$subnets[$i]['subnet_cidr']} gateway={$subnets[$i]['addr_gw']} dns-server={$subnets[$i]['addr_gw']}", 1 );
                $html_config->put( "ip dhcp-server add name=\"{$subnets[$i]['dhcp_server_name']}\" interface=\"$g_lan\" address-pool=\"{$subnets[$i]['dhcp_pool_name']}\"", 1 );
                $html_config->put( "ip dhcp-server enable \"{$subnets[$i]['dhcp_server_name']}\"", 1 );
                $html_config->put ( '', 1 );
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
                        $ppp_clients[] = "interface {$vpn_protocols[$g_vpn]}-client add connect-to=\"{$subnets[$s]['pub']}\" name=\"$ppp_client_name\" user=\"$g_lan-{$subnets[$i]['name']}\" password=\"{$subnets[$i]['psw']}\" profile=\"$ppp_profile_name\" keepalive-timeout=$ppp_timeout";
                    }
                    else if( $is_server )
                    {
                        $ppp_server_name = "$g_lan-$g_vpn-Server-{$subnets[$s]['name']}";
                        $ppp_users[] = "ppp secret add name=\"$g_lan-{$subnets[$s]['name']}\" password=\"{$subnets[$s]['psw']}\" profile=\"$ppp_profile_name\" local-address={$subnets[$i]['addr_vpn']} remote-address={$subnets[$s]['addr_vpn']} routes=\"{$subnets[0]['addr_subnet']}/24 {$subnets[$s]['addr_vpn']} {$subnets[$s]['dist']}\"";
                        $ppp_servers[] = "interface {$vpn_protocols[$g_vpn]}-server add name=\"$ppp_server_name\" user=\"$g_lan-{$subnets[$s]['name']}\"";
                    }
                }
            }

            // PPP Server
            if( sizeof( $ppp_users ) || sizeof( $ppp_servers ) )
            {
                $html_config->put( "# PPP Server ({$subnets[$i]['name']})", 1 );
                $html_config->put( $ppp_users, 1 );
                $html_config->put( $ppp_servers, 1 );
                $html_config->put ( '', 1 );
            }

            // PPP Client
            if( sizeof( $ppp_clients ) || sizeof( $ppp_routes ) )
            {
                $html_config->put( "# PPP Client ({$subnets[$i]['name']})", 1 );
                $html_config->put( $ppp_clients, 1 );
                $html_config->put( $ppp_routes, 1 );
                $html_config->put ( '', 1 );
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

                    $eoip_interface[] = "interface eoip add name=\"$eoip_name\" remote-address={$subnets[$s]['addr_vpn']} tunnel-id=$eoip_tunnel_id";
                    $eoip_to_bridge[] = "interface bridge port add bridge=\"$g_lan\" interface=\"$eoip_name\" horizon=$g_horizon";
                    $eoip_bridge_filter[] = "interface bridge nat add chain=dstnat in-interface=\"$eoip_name\" mac-protocol=ip ip-protocol=udp src-port=67-68 action=drop";
                }
            }

            $html_config->put( "# EoIP ({$subnets[$i]['name']})", 1 );
            $html_config->put( $eoip_interface, 1 );
            $html_config->put( '', 1 );
            
            $html_config->put( "# EoIP to Bridge ({$subnets[$i]['name']})", 1 );
            $html_config->put( $eoip_to_bridge, 1 );
            $html_config->put ( '', 1 );
            
            $html_config->put( "# EoIP filter DHCP ({$subnets[$i]['name']})", 1 );
            $html_config->put( $eoip_bridge_filter, 1 );

            $html_setup->open( 'td', ' valign="top" align="right"' );
            $html_setup->open( 'div', ' class="textarea"' );
            if( $raw && $raw == $i )
            {
                exit( $html_config->render() );
            }
            {
                $html_setup->put( $html_config );
            }
            $html_setup->close();
            $html_setup->close();
            $html_setup->close();
        }

        return $html_setup;
    }
}

?>