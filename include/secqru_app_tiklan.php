<?php

class secqru_app_tiklan
{
    private $w;

    const FORMSIZE = 33;
    const MAX_ROUTERS = 64;

    public function __construct( secqru_worker $w )
    {
        $this->w = $w;
    }

    public function buttons( secqru_html $html )
    {
        $html->add( ' — ' );
        $html->put_submit( 'help', 'help' );
        $html->put_submit( 'reset', 'reset' );
    }

    private function ipv4_pos( $cidr )
    {
        return  ( 0xFFFFFFFF << 32 - $cidr ) & 0xFFFFFFFF;
    }

    private function ipv4_neg( $cidr )
    {
        return ~( 0xFFFFFFFF << 32 - $cidr ) & 0xFFFFFFFF;
    }

    public function link()
    {
        return true;
    }

    public function html( secqru_html $html )
    {
        // RESET
        if( $this->w->get_set( 'reset' ) )
        {
            $this->w->reset();
            $this->w->log( 'reset to defaults', 7 );
        }

        // GLOBAL NETWORK ADDRESS
        $g_lan = $this->w->get_dns( 'g_lan:bridge name', 'TikLAN' );

        // GLOBAL PASSWORD SEED
        $g_psw = $this->w->get_dns( 'g_psw:password seed', function(){ return $this->w->rndhex( 8 ); } );
        $g_psw_prefix = substr( sha1( $g_psw ), -8 ) . '_';

        // GLOBAL IP RANGE
        $g_rng = $this->w->get_ip2long( 'g_rng:broadcast domain', ip2long( '172.17.0.0' ) );

        // GLOBAL IP CIDR (MASK)
        $g_cidr = $this->w->get_int( 'g_cidr:broadcast cidr', 20, 18, 22 );

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
        $subnum = $this->w->get_int( 'subnum:router count', 3, 2, self::MAX_ROUTERS, $temp );

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
        if( $this->w->get_set( 'g_rw' ) &&
            $g_rng != $this->w->get_ip2long( 'g_rw:global range', false ) )
        {
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
        $vpn_protocols = array( 'L2TP' => 'l2tp', 'PPTP' => 'pptp', 'SSTP' => 'sstp' );
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

            // SUBNET PATH COST
            $subnets[$i]['cost'] = $this->w->get_int( "cost$i:router path cost $i", 10, 1, 999 );

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
            $subnets[$i]['addr_id'] = ( $subnets[$i]['subnet'] >> 8 ) - ( $subnets[0]['subnet'] >> 8 );
            $subnets[$i]['addr_vpn'] = long2ip( $subnets[0]['subnet'] + $subnets[$i]['addr_id'] );
            $subnets[$i]['addr_dhcp_first'] = long2ip( $subnets[$i]['subnet'] + 10 );
            $subnets[$i]['addr_dhcp_last'] = long2ip( $subnets[$i]['subnet_end'] );
            $subnets[$i]['dhcp_pool_name'] = "$g_lan ({$subnets[$i]['name']}) Pool";
            $subnets[$i]['dhcp_server_name'] = "$g_lan ({$subnets[$i]['name']}) DHCP";
            $subnets[$i]['psw'] = $g_psw_prefix . substr( sha1( $g_psw . $subnets[$i]['name'] ), -8 );

            // SUBNET ROUTEROS VERSION
            $subnets[$i]['ros'] = $this->w->get_dns( "ros$i:routeros version $i", 'v6' );
            $ros_versions = array( 'v6', 'v7' );
            if( !in_array( $subnets[$i]['ros'], $ros_versions ) )
            {
                $subnets[$i]['ros'] = 'v6';
                $this->w->ezlog( 'default', $subnets[$i]['name'], $subnets[$i]['ros'], 1 );
            }
        }

        // TUNNEL ID CALC
        $temp = $g_eoip;
        for( $i = 1; $i <= $subnum; $i++ )
        {
            $subnets[$i]['eoip_direct'] = $temp + $i;
            $temp += self::MAX_ROUTERS - $i - 2;
        }

        // NEW STATION SELECTED?
        $g_sel = $this->w->get_int( 'g_sel:selected station', 0 );
        $subnets[0]['name'] = '...' . str_repeat( '&nbsp;', self::FORMSIZE - 3 );

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
        for( $i = 0; $i <= $subnum; $i++ )
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

            header( 'Content-Type: text/plain' );

            foreach( $router_config as $line )
                echo $line . PHP_EOL;
            echo ' ';

            exit;
        }

        if( $this->w->get_set( 'save' ) )
            $this->w->log( 'save', 7 );

        // DISPLAY HELP / RESET/ LOG
        if( $this->w->get_set( 'help' ) || !$this->w->log )
        {
            $help = explode( SECQRU_EOL, '# HELP:

* <a href="https://github.com/deemru/secqru/wiki/tiklan">Tiklan</a> is available on github

* This app makes broadcast domains between Mikrotik routers
* Minimum requirements: 2 routers + 1 public ip address
* You can use a default configuration from scratch
* Just correct public ip addresses
* In the end you get "' . $g_lan . '" bridge
* Test it, add ports, enjoy!

* Contact — deem@deem.ru' );
        }
        else
        {
            $help = 0;
        }

        $html->put( '<hr>' );
        $html->open( 'table' );
        $html->open( 'tr' );
        {
            $html->open( 'td', ' valign="top" align="left"' );
            $html->open( 'div');

            $html->put_input( 'g_lan', self::FORMSIZE, 50, $g_lan, 'bridge name' );
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
                foreach( array_keys( $vpn_protocols ) as $temp )
                    $html->put_option( $temp, $temp, $g_vpn == $temp );
            }
            $html->close();

            $html->put_submit( 'save', 'v' );
            $html->add( ' — VPN protocol', 1 );

            $html->put_input( 'g_eoip', 5, 5, $g_eoip );
            $html->add( ' — tunnel id seed', 1 );

            $html->put_input_ro( self::FORMSIZE, long2ip( $g_rng + 1 ) . ' — ' . long2ip( $g_rng_end ) );
            $html->add( ' — address pool', 1 );

            $html->put_input_ro( self::FORMSIZE, long2ip( $subnets[0]['subnet'] + 1 ) . ' — ' . long2ip( $subnets[0]['subnet_end'] ) );
            $html->add( ' — reserved pool', 1 );

            $html->put_input( 'g_psw', self::FORMSIZE, 50, $g_psw );
            $html->add( ' — password seed', 1 );

            $html->open_select( 'g_sel' );
            {
                for( $i = 0; $i <= $subnum; $i++ )
                {
                    $temp = $subnets[$i]['name'];
                    if( $i && strlen( $temp ) > self::FORMSIZE )
                        $temp = substr( $temp, 0, self::FORMSIZE - 3 ) . '...' ;

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
                $html->input_full( 'text', "name$i", self::FORMSIZE, 50, $subnets[$i]['name'], $subnets[$i]['name_ok'] ? '' : 'e' );
                $html->add( ' — router name', 1 );

                $html->put_input_hidden( "is_pub$i", $subnets[$i]['is_pub'] ? '1' : '0' );
                $html->input_full( 'text', "pub$i", self::FORMSIZE, 50, $subnets[$i]['pub'], $subnets[$i]['is_pub'] ? ( $subnets[$i]['pub_ok'] ? '' : 'e' ) : ( $nopublic ? 'e' : 'r' ) );
                $html->put_submit( "sw_pub$i", $subnets[$i]['is_pub'] ? 'x' : 'v' );
                $html->add( ' — public address', 1 );

                $html->put_input( "cost$i", 3, 3, $subnets[$i]['cost'] );
                $html->add( ' — router path cost', 1 );

                $html->input_full( 'text', "subnet$i", 15, 15, long2ip( $subnets[$i]['subnet'] ), $subnets[$i]['subnet_ok'] ? '' : 'e' );
                $html->add( ' / ' );
                $html->input_full( 'text', 0, 2, 0, $subnets[$i]['subnet_cidr'], 'r', 0 );
                $html->add( ' — local range', 1 );

                $html->input_full( 'text', 0, 15, 0, $subnets[$i]['addr_gw'], 'r' );
                $html->add( ' — LAN address', 1 );

                $html->input_full( 'text', 0, 15, 0, $subnets[$i]['addr_vpn'], 'r' );
                $html->add( ' — VPN address', 1 );

                $html->input_full( 'text', 0, self::FORMSIZE, 0, "{$subnets[$i]['addr_dhcp_first']} - {$subnets[$i]['addr_dhcp_last']}", 'r' );
                $html->add( ' — DHCP pool', 1 );

                $html->open_select( "ros$i" );
                {
                    foreach( $ros_versions as $temp )
                        $html->put_option( $temp, $temp, $subnets[$i]['ros'] == $temp );
                }
                $html->close();
                $html->put_submit( 'save', 'v' );
                $html->add( ' — RouterOS version', 1 );
            }
            $html->close();
            $html->close();

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
        $config = array();

        $filter_list = $g_lan . '-Remotes';
        $v7 = $subnets[$i]['ros'] == 'v7';

        // GATEWAY OR NEWBIE
        if( !$g_sel || ( $g_sel && $g_sel == $i ) )
        {
            // Bridge
            $config[] = "# Bridge ({$subnets[$i]['name']})";
            $config[] = "interface bridge add name=\"$g_lan\" comment=\"$g_lan\" protocol-mode=none mtu=1500";
            $config[] = "ip address add address={$subnets[$i]['addr_gw']}/$g_cidr interface=\"$g_lan\"";
            $config[] = "ip route add dst-address={$subnets[0]['addr_subnet']}/24 " . ( $v7 ? 'blackhole' : 'type=blackhole' );
            $config[] = '';

            // OSPF Routes
            $config[] = "# OSPF Routes ({$subnets[$i]['name']})";
            if( $v7 )
            {
                $config[] = "routing ospf instance add name=\"$g_lan\" version=2 router-id={$subnets[$i]['addr_vpn']}";
                $config[] = "routing ospf area add name=\"$g_lan\" instance=\"$g_lan\" area-id={$subnets[0]['addr_subnet']}";
            }
            else
            {
                $config[] = "routing ospf instance add name=\"$g_lan\" router-id={$subnets[$i]['addr_vpn']}";
                $config[] = "routing ospf area add name=\"$g_lan\" instance=\"$g_lan\" area-id={$subnets[0]['addr_subnet']}";
                $config[] = "routing ospf network add network={$subnets[0]['addr_subnet']}/24 area=\"$g_lan\"";
            }
            $config[] = '';

            // DHCP
            $config[] = "# DHCP ({$subnets[$i]['name']})";
            $config[] = "ip pool add ranges={$subnets[$i]['addr_dhcp_first']}-{$subnets[$i]['addr_dhcp_last']} name=\"{$subnets[$i]['dhcp_pool_name']}\"";
            $config[] = "ip dhcp-server network add address={$subnets[$i]['addr_subnet']}/$g_cidr gateway={$subnets[$i]['addr_gw']} dns-server={$subnets[$i]['addr_gw']}";
            $config[] = "ip dhcp-server add name=\"{$subnets[$i]['dhcp_server_name']}\" interface=\"$g_lan\" address-pool=\"{$subnets[$i]['dhcp_pool_name']}\"";
            $config[] = "ip dhcp-server enable \"{$subnets[$i]['dhcp_server_name']}\"";
            $config[] = '';

            // Bridge Filters
            $config[] = "# Bridge Filters ({$subnets[$i]['name']})";
            $config[] = "interface list add name=\"$filter_list\"";
            $config[] = "interface bridge nat add chain=srcnat out-interface-list=\"$filter_list\" mac-protocol=ip dst-address=224.0.0.0/4 action=drop";
            $config[] = "interface bridge nat add chain=srcnat out-interface-list=\"$filter_list\" mac-protocol=ipv6 action=drop";
            $config[] = "interface bridge nat add chain=srcnat out-interface-list=\"$filter_list\" mac-protocol=ip ip-protocol=udp src-port=67-68 action=drop";
            $config[] = '';
        }

        // PPP PROFILE ADD
        $ppp_timeout = '7';

        // PPP SECRET ADD
        $ppp_users = array();
        $ppp_servers = array();
        $ppp_clients = array();
        $ospf_costs = array();

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
                        if( $subnets[$i]['cost'] > $subnets[$s]['cost'] || ( $subnets[$i]['cost'] == $subnets[$s]['cost'] && $i > $s ) )
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
                    if( $v7 )
                        $ospf_costs[] = "routing ospf interface-template add interfaces=\"$ppp_client_name\" area=\"$g_lan\" type=ptp cost={$subnets[$s]['cost']}";
                    else
                        $ospf_costs[] = "routing ospf interface add interface=\"$ppp_client_name\" network-type=point-to-point cost={$subnets[$s]['cost']}";
                    $ppp_clients[] = "interface {$vpn_protocols[$g_vpn]}-client add connect-to=\"{$subnets[$s]['pub']}\" name=\"$ppp_client_name\" user=\"$g_lan-{$subnets[$i]['name']}\" password=\"{$subnets[$i]['psw']}\" profile=default-encryption keepalive-timeout=$ppp_timeout disabled=no";
                }
                else if( $is_server )
                {
                    $ppp_server_name = "$g_lan-$g_vpn-Server-{$subnets[$s]['name']}";
                    if( $v7 )
                        $ospf_costs[] = "routing ospf interface-template add interfaces=\"$ppp_server_name\" area=\"$g_lan\" type=ptp cost={$subnets[$s]['cost']}";
                    else
                        $ospf_costs[] = "routing ospf interface add interface=\"$ppp_server_name\" network-type=point-to-point cost={$subnets[$s]['cost']}";
                    $ppp_users[] = "ppp secret add name=\"$g_lan-{$subnets[$s]['name']}\" password=\"{$subnets[$s]['psw']}\" profile=default-encryption local-address={$subnets[$i]['addr_vpn']} remote-address={$subnets[$s]['addr_vpn']}";
                    $ppp_servers[] = "interface {$vpn_protocols[$g_vpn]}-server add name=\"$ppp_server_name\" user=\"$g_lan-{$subnets[$s]['name']}\"";
                }
            }
        }

        // PPP Server
        if( sizeof( $ppp_users ) || sizeof( $ppp_servers ) )
        {
            $config[] = "# PPP Server ({$subnets[$i]['name']})";
            if( !$g_sel || ( $g_sel && $g_sel == $i ) )
                $config[] = "interface {$vpn_protocols[$g_vpn]}-server server set enabled=yes keepalive-timeout=$ppp_timeout";
            $config = array_merge( $config, $ppp_users );
            $config =  array_merge( $config, $ppp_servers );
            $config[] = '';
        }

        // PPP Client
        if( sizeof( $ppp_clients ) )
        {
            $config[] = "# PPP Client ({$subnets[$i]['name']})";
            $config = array_merge( $config, $ppp_clients );
            $config[] = '';
        }

        // OSPF ROUTES WITH COSTS
        if( sizeof( $ospf_costs ) )
        {
            $config[] = "# OSPF Costs ({$subnets[$i]['name']})";
            $config = array_merge( $config, $ospf_costs );
            $config[] = '';
        }

        // EOIP ADD
        $eoip_interface = array();
        $eoip_to_bridge = array();
        $eoip_bridge_filter = array();
        for( $s = 1; $s <= $subnum; $s++ )
        {
            if( $i != $s && ( !$g_sel || ( $g_sel && $g_sel == $s ) || ( $g_sel && $g_sel == $i ) ) )
            {
                $eoip_name_direct = "$g_lan-EoIP-{$subnets[$s]['name']}";
                $eoip_id_direct = $subnets[ min( $i, $s ) ]['eoip_direct'] + max( $i, $s ) - 2;

                $eoip_interface[] = "interface eoip add name=\"$eoip_name_direct\" !keepalive remote-address={$subnets[$s]['addr_vpn']} tunnel-id=$eoip_id_direct";
                $eoip_to_bridge[] = "interface bridge port add bridge=\"$g_lan\" interface=\"$eoip_name_direct\" horizon=$g_horizon";
                $eoip_bridge_filter[] = "interface list member add interface=\"$eoip_name_direct\" list=\"$filter_list\"";
            }
        }

        $config[] = "# EoIP ({$subnets[$i]['name']})";
        $config = array_merge( $config, $eoip_interface );
        $config[] = '';

        $config[] = "# EoIP to Bridge ({$subnets[$i]['name']})";
        $config = array_merge( $config, $eoip_to_bridge );
        $config[] = '';

        $config[] = "# EoIP Filters ({$subnets[$i]['name']})";
        $config = array_merge( $config, $eoip_bridge_filter );

        return $config;
    }
}
