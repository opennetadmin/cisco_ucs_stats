<?php
global $base, $conf, $baseURL, $images;

$title_right_html = '';
$title_left_html  = '';
$modbodyhtml = '';
$modjs = '';

// Get info about this file name
$onainstalldir = dirname($base);
$file = str_replace($onainstalldir.'/www', '', __FILE__);
$thispath = dirname($file);

// future config options
$boxheight = '300px';
$divid = 'desktopucsstats';


// Display only on the desktop
if ($extravars['window_name'] == 'html_desktop' ) { # or $extravars['window_name'] == 'display_host') {


    $title_left_html .= <<<EOL
        &nbsp;<img src="{$baseURL}{$thispath}/cisco.ico" border="0" width="16"> UCS Status
EOL;

    $title_right_html .= <<<EOL
        <a title="Reload UCS status info" onclick="el('{$divid}').innerHTML = '<center>Reloading...</center>';xajax_window_submit('{$file}', xajax.getFormValues('ucsinfo_form'), 'ucs_display_stats');"><img src="{$images}/silk/arrow_refresh.png" border="0"></a>
EOL;


    $modbodyhtml .= <<<EOL
<form id="ucsinfo_form" onSubmit="return false;">
<input type="hidden" name="divname" value="{$divid}">
</form>
<div id="{$divid}" style="height: {$boxheight};overflow-y: auto;overflow-x:hidden;font-size:small">
{$conf['loading_icon']}
</div>
EOL;


    // run the function that will update the content of the plugin. update it every 5 mins
    $modjs = "xajax_window_submit('{$file}', xajax.getFormValues('ucsinfo_form'), 'ucs_display_stats');";

$divid='';

}







/*
Using curl, gather all of the data from the ucs systems and update the status
*/
function ws_ucs_display_stats($window_name, $form='') {
    global $conf, $self, $onadb, $base, $images, $baseURL;

    // Get info about this file name
    $onainstalldir = dirname($base);
    $file = str_replace($onainstalldir.'/www', '', __FILE__);
    $thispath = dirname($file);
    $ucs_srv_list = array();
    //$warncount = 0;
    //$critcount = 0;
    $SUMMARY = array();
    $SUMMARY[crit_count] = 0;
    $SUMMARY[major_count] = 0;
    $SUMMARY[minor_count] = 0;
    $SUMMARY[warn_count] = 0;
    $SUMMARY[info_count] = 0;
    $SUMMARY[ack_count] = 0;


    // If an array in a string was provided, build the array and store it in $form
    $form = parse_options_string($form);

    // Get the hosts having ucs_manager CA
//    list($status, $rows, $ucshosts) = db_get_records($onadb, 'custom_attributes', "custom_attribute_type_id in (select id from custom_attribute_types where name='ucs_manager') and table_name_ref = 'hosts' and value = 'Y'");
//    if ($rows) {
//        foreach($ucshosts as $ucshost) {
//            list($status,$rows,$host) = ona_get_host_record(array('id' => $ucshost['table_id_ref']));
//            array_push($srv_list, $host['fqdn']);
//        }
//    }
    if (!is_readable(dirname(__FILE__) . '/ucs_servers.inc.php')) {
            $htmllines .= <<<EOL
                <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';"><td class="list-row" colspan="5">Unable to open config file.</td></tr>
EOL;
    } else {
    @require_once(dirname(__FILE__) . '/ucs_servers.inc.php');
    if (!isset($ucs_srv_list['1'])) {
            $htmllines .= <<<EOL
                <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';"><td class="list-row" colspan="5">No UCS servers defined via config file.</td></tr>
EOL;
    }

    // Lets loop through each of the provided UCS servers and get their data
    foreach ($ucs_srv_list as $srv) {
        $blade_data = array();
        $bladeinfo = array();
        $htmlinfo = array();
        $UCS_SERVER=$srv['srv'].':443';
        $bladeCount=0;

        # Log in to the UCS system and get a cookie. YUM
        $LOGIN['inName']=$srv['user'];
        $LOGIN['inPassword']=$srv['pass'];
        list($ucsstat,$COOKIE) = UCS_XML_request($UCS_SERVER, "aaaLogin", $LOGIN);

        if ($ucsstat) {
            $htmllines .= <<<EOL
                <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';"><td class="list-row">{$srv['srv']}&nbsp;&nbsp;</td><td class="list-row" colspan="5">UCS server may not be available.</td></tr>
EOL;
            continue;
        }

	// gather fault information
	$configscope['inClass']='faultInst';
	$configscope['inHierarchical']='false';
	$configscope['cookie']=$COOKIE['outCookie'];

	list($ucsstat,$XMLOUT) = UCS_XML_request($UCS_SERVER, "configScope", $configscope);
	foreach($XMLOUT->xpath("//faultInst") as $ITEM) {
        	if ($ITEM[severity] == "cleared") continue;
        	if ($ITEM[severity] == "critical")       $SUMMARY[crit_count] += 1;
        	if ($ITEM[severity] == "major")          $SUMMARY[major_count] += 1;
        	if ($ITEM[severity] == "minor")          $SUMMARY[minor_count] += 1;
        	if ($ITEM[severity] == "warning")        $SUMMARY[warn_count] += 1;
        	if ($ITEM[severity] == "info")           $SUMMARY[info_count] += 1;
        	if ($ITEM[ack] == "yes") $SUMMARY[ack_count] += 1;
	}

        // Gather data about service profiles
        $confresolve['classId']='lsServer';
        $confresolve['inHierarchical']='false';
        $confresolve['cookie']=$COOKIE['outCookie'];

        list($ucsstat,$serviceprofile_data) = UCS_XML_request($UCS_SERVER, "configResolveClass", $confresolve);

        // Gather data about physical blades
        $confresolve['classId']='computeBlade';
        $confresolve['inHierarchical']='false';
        $confresolve['cookie']=$COOKIE['outCookie'];

        list($ucsstat,$blade_data) = UCS_XML_request($UCS_SERVER, "configResolveClass", $confresolve);

        # Log out of the session
        list($ucsstat,$LOGOUT) = UCS_XML_request($UCS_SERVER, "aaaLogout", array('inCookie'=>$COOKIE['outCookie']));


        # Sort the list
        ksort($blade_data->xpath("//computeBlade"));

        # Loop through blade information
        foreach ($blade_data->xpath("//computeBlade") as $blade) {
            $y++;
            $bladeCount++;
            $color = '';
            $ucs_srv_name = '';
            $ucsnamestyle = 'style="border-top: none;border-bottom: none;"';

            switch ($blade[operState]) {
                case 'ok':
                    $color = ''; break;
                case 'degraded':
                    $color = "#F7FF5E"; break;
                case 'critical':
                    $color = "#FF7375"; break;
            }

            // For this blade, gather service profile info
            $sp_info = '';
            $userlabelp = '';
            $sp_info = $serviceprofile_data->xpath("//outConfigs/lsServer[@dn='{$blade[assignedToDn]}']");
            if ($sp_info[0]['usrLbl']) $userlabelp = "({$sp_info[0]['usrLbl']})";

            // Clean up the name a bit
            $blade[assignedToDn] = substr($blade[assignedToDn],12);

            if ($blade[association] == 'none') {$blade[assignedToDn] = 'Unassociated';$color = "#DADADA";}

            $bladeinfo["{$blade[chassisId]}{$blade[slotId]}"]['htmlline'] = <<<EOL
            <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
EOL;
            if ($bladeCount == 1) {
            	$ucs_srv_name = $srv['srv'];
                $ucsnamestyle = 'style="border-top: 1px solid;border-bottom: none;"';
            }


            $bladeinfo["{$blade[chassisId]}{$blade[slotId]}"]['htmlline'] .= <<<EOL
                <td class="list-row" {$ucsnamestyle} ><a href="https://{$srv['srv']}/ucsm/ucsm.jnlp" title="Click to open UCS Manager">{$ucs_srv_name}</a></td>
                <td class="list-row" style="border-left: 1px solid;">{$blade[chassisId]}/{$blade[slotId]}</td>
                <td class="list-row" style='background-color: {$color};' onMouseOver="wwTT(this, event,
                                            'id', 'tt_ucsinfo_{$y}',
                                            'type', 'velcro',
                                            'styleClass', 'wwTT_ca_info',
                                            'direction', 'south',
                                            'javascript', 'xajax_window_submit(\'{$file}\', xajax.getFormValues(\'ucsinfo_form_{$y}\'), \'ucs_popup_info\');'
                                           );">
                    <form id="ucsinfo_form_{$y}" onSubmit="return false;">
                    <input type="hidden" name="id" value="tt_ucsinfo_{$y}"/>
                    <input type="hidden" name="serial" value="{$blade[serial]}"/>
                    <input type="hidden" name="cpunum" value="{$blade[numOfCpus]}"/>
                    <input type="hidden" name="corenum" value="{$blade[numOfCores]}"/>
                    <input type="hidden" name="model" value="{$blade[model]}"/>
                    <input type="hidden" name="opstate" value="{$blade[operState]}"/>
                    <input type="hidden" name="profile" value="{$blade[assignedToDn]}"/>
                    <input type="hidden" name="totalmem" value="{$blade[totalMemory]}"/>
                    <input type="hidden" name="powerstate" value="{$blade[operPower]}"/>
                    <input type="hidden" name="dn" value="{$blade[dn]}"/>
                    <input type="hidden" name="usrlbl" value="{$sp_info[0]['usrLbl']}"/>
                    <input type="hidden" name="ucssrv" value="{$srv['srv']}"/>
                    </form>
                    {$blade[assignedToDn]} {$userlabelp}
                </td>
                <td class="list-row">{$blade[operPower]}</td>
                <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
            </tr>
EOL;
        }

        // If we actually have information.. print the table
        if (!$blade) {
            $htmllines .= <<<EOL
                <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';"><td class="list-row">{$srv['srv']}&nbsp;&nbsp;</td><td class="list-row" colspan="5">Unable to gather blade info.</td></tr>
EOL;
        }

        foreach ($bladeinfo as $htmlinfo) {
            $htmllines .= $htmlinfo['htmlline'];
        }

    }

    $html .= <<<EOL
              <table class="list-box" cellspacing="0" border="0" cellpadding="0">
                <tr><td class="list-row" colspan=4 align="center">
                    <b>Fault Summary</b>&nbsp;&nbsp;&nbsp;&nbsp;
                    <span title="Critical faults"><img src="{$images}/silk/cancel.png" border="0"> {$SUMMARY[crit_count]}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    <span title="Major faults"><img src="{$images}/silk/error.png" border="0"> {$SUMMARY[major_count]}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                  </td>
                </tr>
EOL;
    } // end config file check
    $html .= $htmllines;
    $html .= "</table>";



    // Insert the new table into the window
    $response = new xajaxResponse();
    $response->addAssign($form['divname'], "innerHTML", $html);
    $response->addScript($js);
    return($response->getXML());
}





function ws_ucs_popup_info($window_name, $form='') {
    global $conf, $self, $onadb, $tip_style;
    global $font_family, $color, $style, $images;
    $html = $js = '';

    // If an array in a string was provided, build the array and store it in $form
    $form = parse_options_string($form);

    // clean up some of the incoming data
    $form['totalmem'] = (int)$form['totalmem'] / 1024;


    $style['content_box'] = <<<EOL
        padding: 2px 4px;
        vertical-align: top;
EOL;

    // WARNING: this one's different than most of them!
    $style['label_box'] = <<<EOL
        font-weight: bold;
        cursor: move;

EOL;

    $html .= <<<EOL

    <table style="{$style['content_box']}" cellspacing="0" border="0" cellpadding="0">

    <tr><td colspan="2" align="center" class="qf-search-line" style="{$style['label_box']}; padding-top: 0px;" onMouseDown="dragStart(event, '{$form['id']}', 'savePosition', 0);" nowrap="true">
        Service Profile: {$form['profile']}
    </td></tr>

    <tr>
        <td align="right" class="qf-search-line" style="font-weight: bold;" nowrap="true">User Label</td>
        <td align="left" class="qf-search-line" nowrap="true">{$form['usrlbl']}</td>
    </tr>

    <tr>
        <td align="right" class="qf-search-line" style="font-weight: bold;" nowrap="true">Serial #</td>
        <td align="left" class="qf-search-line" nowrap="true">{$form['serial']}</td>
    </tr>

    <tr>
        <td align="right" class="qf-search-line" style="font-weight: bold;" nowrap="true">Model</td>
        <td align="left" class="qf-search-line" nowrap="true">{$form['model']}</td>
    </tr>
    <tr>
        <td align="right" class="qf-search-line" style="font-weight: bold;" nowrap="true">CPUs (Cores)</td>
        <td align="left" class="qf-search-line" nowrap="true">{$form['cpunum']} ({$form['corenum']})</td>
    </tr>
    <tr>
        <td align="right" class="qf-search-line" style="font-weight: bold;" nowrap="true">Memory</td>
        <td align="left" class="qf-search-line" nowrap="true">{$form['totalmem']}GB</td>
    </tr>
    <tr>
        <td align="right" class="qf-search-line" style="font-weight: bold;" nowrap="true">Power state</td>
        <td align="left" class="qf-search-line" nowrap="true">{$form['powerstate']}</td>
    </tr>
    <tr>
        <td align="right" class="qf-search-line" style="font-weight: bold;" nowrap="true">Operation state</td>
        <td align="left" class="qf-search-line" nowrap="true">{$form['opstate']}</td>
    </tr>
EOL;

    if ($form['opstate'] == "degraded") {
        $UCS_SERVER=$form['ucssrv'].':443';
        # Log in to the UCS system and get a cookie. YUM
        $LOGIN['inName']=$srv['user'];
        $LOGIN['inPassword']=$srv['pass'];
        list($ucsstat,$COOKIE) = UCS_XML_request($UCS_SERVER, "aaaLogin", $LOGIN);

        if ($ucsstat) {
            $htmllines .= <<<EOL
                <tr><td>Unable to get Error messages:<br>UCS server may not be available.</td></tr>
EOL;
        }

        $configscope['inClass']='faultInst';
        $configscope['inHierarchical']='false';
        $configscope['cookie']=$COOKIE['outCookie'];
        $configscope['dn']=$form[dn];

        list($ucsstat,$fault_data) = UCS_XML_request($UCS_SERVER, "configScope", $configscope);


        foreach ($fault_data->xpath("//faultInst") as $fault) {
          if ($fault[severity] == "major" or $fault[severity] == "critical") {
            $z++;
            $html .= <<<EOL
            <tr>
                <td align="right" class="qf-search-line" style="font-weight: bold;" nowrap="true">FAULT({$z})</td>
                <td align="left" class="qf-search-line" nowrap="true">{$fault[severity]}: {$fault[descr]}</td>
            </tr>
EOL;
          }
        }
    }

        # Log out of the session
        list($ucsstat,$LOGOUT) = UCS_XML_request($UCS_SERVER, "aaaLogout", array('inCookie'=>$COOKIE['outCookie']));

    $html .= <<<EOL
    </table>

EOL;

    // Okay here's what we do:
    //   1. Hide the tool-tip
    //   2. Update it's content
    //   3. Reposition it
    //   4. Unhide it
    $response = new xajaxResponse();
    $response->addScript("el('{$form['id']}').style.visibility = 'hidden';");
    $response->addAssign($form['id'], "innerHTML", $html);
    $response->addScript("wwTT_position('{$form['id']}'); el('{$form['id']}').style.visibility = 'visible';");
    if ($js) { $response->addScript($js); }
    return($response->getXML());
}


/*
Gathers information from the supplied UCS system
*/
function UCS_XML_request($site, $methodName, $params = NULL, $user_agent = 'application/OpenNetAdmin'){
        global $conf, $self, $onadb, $base;
        $site = explode(':', $site);                                                       
        if(isset($site[1]) and is_numeric($site[1])){                                      
                $port = $site[1];                                                          
                if($port == 443)                                                           
                        $sslhost = "tls://".$site[0];                                      
                        #$sslhost = $site[0];                                              
                else                                                                       
                        $sslhost = $site[0];                                               
        }else{                                                                             
                $port = 80;                                                                
                $sslhost = $site[0];                                                       
        }                                                                                  
        $site = $site[0];                                                                  

        #  Build attributes in the request
        foreach($params as $element => $value) {
                $attr .= "{$element}=\"{$value}\" ";
        }                                           

        $data = "<$methodName $attr/>";

        $conn = @fsockopen ($sslhost, $port, $errno, $errmsg, 5);
        if(!$conn){ #if the connection was not opened successfully
                return(array(1,"ERROR: ----unable to connect to $sslhost. $errmsg\n"));
        }else{
                $headers =
                        "POST /nuova HTTP/1.0\r\n" .
                        "Host: $site\r\n" .
                        "Connection: close\r\n" .
                        ($user_agent ? "User-Agent: $user_agent\r\n" : '') .
                        "Content-Type: text/xml\r\n" .
                        "Content-Length: " . strlen($data) . "\r\n\r\n";

                fputs($conn, "$headers");
                fputs($conn, $data);

                #socket_set_blocking ($conn, false);
                $resp = '';
                while(!feof($conn)){
                        $resp .= fgets($conn, 1024);
                }
                fclose($conn);

                if(preg_match("/HTTP\/1\.\d\s(\d+)/", $resp, $matches) && $matches[1] == 200) {
                        // load xml as object
                        $parts = explode("\r\n\r\n", $resp);

                        // check for errors in the XML response
                        if (strpos($parts[1],'ERROR ')) {
                                return(array(1,"ERROR: There was an error with the XML response. {$parts[1]}\n"));
                        }

                        $data = @simplexml_load_string($parts[1]);

                } else {
                        return(array(1,"ERROR: There was an error with the XML response. {$resp}\n"));
                }


                #strip headers off of response
                #$data = substr($resp, strpos($resp, "\r\n\r\n")+4);
                # turn it into a simpleXML array
                #$data = new SimpleXMLElement($data);

                # check for errors in the return
                if ($data[errorCode]) {
                        return(array(1,"ERROR: {$data[errorDescr]}\n"));
                }

                return(array(0,$data));
        }
}


?>
