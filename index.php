<?php

define('SAVE_FILE', 'save.json');

//db functions
function write_data($data)
{
    file_put_contents(SAVE_FILE, json_encode($data));
}

function read_data()
{
    $data = file_get_contents(SAVE_FILE);
    return json_decode($data, true);
}

//template functions
define('TEMPLATE_DIR', 'tmpl');
define('TEMPLATE_EXT', 'tmpl');
function display_template($template, $data = array(), $display = true)
{
    $tmpl = file_get_contents(TEMPLATE_DIR.'/'.$template.'.'.TEMPLATE_EXT);
    foreach ($data as $placeholder => $value) {
        $tmpl = str_replace($placeholder, $value, $tmpl);
    }
    if ($display) {
        echo $tmpl;
    }
    return $tmpl;
}

//display functions
function display_system($id, $data)
{
    display_template(
        'system_display',
        array(
        '{{SYSTEM_NAME}}' => ucwords($id),
        '{{SYSTEM_SIGS}}' => $data
        )
    );
}

function display_add_form()
{
    display_template('system_add');
}

function display_alert($alert)
{
    display_template(
        'alert',
        array (
            '{{ALERT}}' => $alert,
        )
    );
}

//data formatting
function parse_data($input)
{
    $result = display_template('system_before', array(), false);

    ksort($input);
    foreach ($input as $id => $data) {
        $group = $data[2];
        $time = $data[5];
        
        $start_time = new DateTime();
        $start_time -> setTimestamp($time);
        $end_time = new DateTime();
        $end_time -> setTimestamp(time());
        $diff = $end_time->diff($start_time);

        $group = str_replace(
            array('Combat Site', 'Ore Site', 'Wormhole', 'Data Site', 'Gas Site', 'Relic Site'),
            array('cmb', 'ore', 'wrh', 'dat', 'gas', 'rel'),
            $group
        );
        if (empty($group)) {
            $group = '&nbsp;-&nbsp;';
        }

        if ($data[3] == 'Besieged Covert Research Facility') {
            $group = 'bes';
        }

        switch ($group) {
            case 'bes':
                $css = 'group_besieged';
                $img = 'combatSite_16.png';
                break;
            case 'cmb':
                $css = 'group_combat';
                $img = 'combatSite_16.png';
                break;
            case 'gas':
                $css = 'group_gas';
                $img = 'harvestableCloud.png';
                break;
            case 'wrh':
                $css = 'group_wormhole';
                $img = 'wormhole.png';
                break;
            case 'ore':
                $css = '';
                $img = 'ore_Site_16.png';
                break;
            case 'dat':
                $css = '';
                $img = 'data_Site_16.png';
                break;
            case 'rel':
                $css = '';
                $img = 'relic_Site_16.png';
                break;
            default:
                $css = '';
                $img = '38_16_111.png';
                break;
        }
        $image_colour = 'green';
        if ($data[4] != '100.0%') {
            $image_colour = 'red';
        }

        $details =  'Type:&nbsp;&nbsp;&nbsp;'.$data[1]."\n";
        $details .= 'Group:&nbsp;&nbsp;'.$data[2]."\n";
        $details .= 'Name:&nbsp;&nbsp;&nbsp;'.$data[3]."\n";
        $details .= 'Signal:&nbsp;'.$data[4]."\n";
        $details .= 'Date:&nbsp;&nbsp;&nbsp;'.date("Y-m-d H:i:s", $data[5])."\n";

        $result .= display_template(
            'system_row',
            array(
            '{{SIG_ID}}' => $id,
            '{{SIG_GROUP}}' => $group,
            '{{SIG_SCAN_AGE}}' => ($diff->format('%H:%I:%S')),
            '{{SIG_GROUP_CLASS}}' => $css,
            '{{SIG_DETAILS}}' => $details,
            '{{SIG_IMG}}' => $img,
            '{{SIG_IMG_COLOUR}}' => $image_colour,
            ),
            false
        );
    }
    $result .= display_template('system_after', array(), false);
    return $result;
}

function parse_sig_form_data($raw_sig_data)
{
    $new_data = array();
    
    $__tmp = explode("\n", $raw_sig_data);
    foreach ($__tmp as $line) {
        $_tmp = explode("\t", $line);

        $id = trim($_tmp[0]);
        if (empty($id)) {
            continue;
        }

        if (sizeof($_tmp) != 6) {
            continue;
        }

        $type = trim($_tmp[1]);
        $group = trim($_tmp[2]);
        $name = trim($_tmp[3]);
        $signal = trim($_tmp[4]);
        $time = time();
            
        $new_data[$id] = array($id, $type, $group, $name, $signal, $time);
    }

        return $new_data;
}

//read data from db :D
$data = read_data();
if (!$data) {
    $data = array();
}

//parse requests
$post_data = array();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST['action'] === 'add') {
        $system_name = strtolower(htmlspecialchars($_POST['system_name']));
        $system_sigs = htmlspecialchars($_POST['system_sigs']);

        $post_data = array(
            $system_name,
            array(
                'display' => true,
                'raw_data' => $system_sigs,
            )
        );
    }
    if ($_POST['action'] === 'remove') {
        $system_name = strtolower(htmlspecialchars($_POST['system_name']));
        $data[$system_name]['display'] = false;
    }
}

if (!empty($post_data)) {
    $parsed_sig_data = parse_sig_form_data($post_data[1]['raw_data']);
    if (empty($post_data[0])) {//lets try auto locate system based on saved sigs
        foreach ($parsed_sig_data as $sig_data) {
            foreach ($data as $system_id => $system_data) {
                foreach ($system_data['raw_data'] as $sig_id => $saved_sig_data) {
                    if (empty($post_data[0]) && $sig_id == $sig_data[0]) {
                        $post_data[0] = $system_id;
                    }
                }
            }
        }
    }

    if (!empty($post_data[0])) {
        $new_data = array();
        
        if (!empty($data[$post_data[0]])) {
            $old_data = $data[$post_data[0]]['raw_data'];
        } else {
            $old_data = array();
        }

        foreach ($parsed_sig_data as $sig_data) {
            $id = trim($sig_data[0]);
            $type = trim($sig_data[1]);
            $group = trim($sig_data[2]);
            $name = trim($sig_data[3]);
            $signal = trim($sig_data[4]);
            $time = $sig_data[5];
            
            if (empty($old_data[$id])) { //no data, recording what we have now
                $new_data[$id] = array($id, $type, $group, $name, $signal, $time);
            } else {
                if (empty($old_data[$id][2])) { //no type aquired
                    $new_data[$id] = array($id, $type, $group, $name, $signal, $old_data[$id][5]);
                } else { //we already know the type, new data might nto conain it, we keep old data
                    $new_data[$id] = $old_data[$id];
                }
            }
        }

        $data[$post_data[0]]['raw_data'] = $new_data;
        $data[$post_data[0]]['display'] = true;
    } else {
        display_alert('No system automatically matched!');
    }
}

write_data($data);

display_template('header');
display_template('system_list_before');
ksort($data);
foreach ($data as $id => $system_data) {
    if ($system_data['display']) {
        display_system($id, parse_data($system_data['raw_data']));
    }
}
display_template('system_list_after');
display_template('separator');
display_add_form();
display_template('footer');
