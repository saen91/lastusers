<?php
/**
 * MyBB Plugin: Letzte registrierte Benutzer einer Gruppe anzeigen
 */


//error_reporting ( -1 );
//ini_set ( 'display_errors', true );

//Direkten Zugriff auf diese Datei aus Sicherheitsgründen nicht zulassen
if (!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


//Add Hooks
$plugins->add_hook("index_start", "lastuser_index");


// Plugin-Informationen
function lastuser_info()
{
    return array(
        'name' => 'Letzte fünf registrierte Benutzer anzeigen',
        'description' => 'Zeigt die letzten registrierten Benutzer einer bestimmten Gruppe an.',
        'author' => 'saen',
		'authorsite' => 'https://github.com/saen91',
        'version' => '1.0',
        'compatibility' => '18*',
    );
}

// Plugin-Installation
function lastuser_install()
{
	global $db, $cache, $mybb;
	
	
		// EINSTELLUNGEN anlegen - Gruppe anlegen
	$setting_group = array(
		'name'          => 'lastuser',
		'title'         => 'letzten Registrierten Benutzer anzeigen',
		'description'   => 'Einstellung für die Anzeige der letzten registrierten Benutzer',
		'disporder'     => 1,
		'isdefault'     => 0
	);
	
	$gid = $db->insert_query("settinggroups", $setting_group); 
	
	
	//Die dazugehörigen einstellungen
	// Einstellen der Gruppe
	$setting_array = array(
		'lastuser_groups' => array(
		'title' => 'Auswahl der Usergruppe',
		'description' => 'Welche Usergruppe soll angezeigt werden?',
		'optionscode' => 'groupselect',
		'value' => '2,4',
		'disporder' => 1
	) , 		
	// Einstellung, ob Gäste die Anzeige sehen sollen oder nicht
		'lastuser_guest' => array(
		'title' => 'Gästen anzeigen',
		'description' => 'Sollen auch Gäste die Anzeige sehen?',
		'optionscode' => 'yesno',
	    'value' => '1', // Default
		'disporder' => 2		
	),
	// Einstellung, ob Avatar mit angezeigt werden soll
		'lastuser_avatar' => array(
		'title' => 'Avatar anzeigen',
		'description' => 'Soll ein Avatar angezeigt werden?',
		'optionscode' => 'yesno',
	    'value' => '1', // Default
		'disporder' => 3
	),
	// Einstellung, wie hoch das ava angezeigt werden soll
		'lastuser_avatar_height' => array(
		'title' => 'Höhe vom Avatar',
		'description' => 'Bitte nur eine Zahl, ohne px o.ä. eintragen. Wenn Originalgröße: dann nichts eintragen',
		'optionscode' => 'numeric',
	    'value' => '100', // Default
		'disporder' => 4
	),	
	// Einstellung, ob Gäste ein Ava sehen dürfen oder nicht
		'lastuser_avatar_question' => array(
		'title' => 'Sichtbarkeit Avatare für Gäste',
		'description' => 'Dürfen Gäste ein Avatar sehen?',
		'optionscode' => 'yesno',
	    'value' => '1', // Default
		'disporder' => 5
	),		
	// Einstellung, zum Anzeigen vom No Ava, wenn vorheriges mit ja beantwortet wurde
		'lastuser_avatar_question_noava' => array(
		'title' => 'No Avatar für Gäste?',
		'description' => 'Wähle ja, wenn die Gäste ein No Ava anstelle des richtigen sehen sollen',
		'optionscode' => 'yesno',
	    'value' => '1', // Default
		'disporder' => 6
	),
	// Einstellung, für No Avatar
		'lastuser_noavatar' => array(
		'title' => 'Angabe des No Avatar',
		'description' => 'Gebe hier den Dateinamen des No Avatar an, der bei Usern ohne Avatar oder ggf. bei Gästen angezeigt wird',
		'optionscode' => 'text',
	    'value' => 'gastava.jpg', // Default
		'disporder' => 7
	),
	// Einstellung, wie viele angezeigt werden sollen
		'lastuser_number' => array(
		'title' => 'Anzahl der Angezeigten',
		'description' => 'Wie viele User sollen angezeigt werden?',
		'optionscode' => 'numeric',
	    'value' => '5', // Default
		'disporder' => 8
		),	
	
	);

	foreach ($setting_array as $name => $setting)
	{
		$setting['name'] = $name;
		$setting['gid'] = $gid;

		$db->insert_query('settings', $setting);
	}

	rebuild_settings();
	

	//TEMPLATE Haupt einfügen
	$insert_array = array(
		'title'        => 'lastuser_all',
		'template'    => $db->escape_string('<h2>Zuletzt registrierte User</h2>
	<div class="lastuserall">
		{$lastusers_bit}
	</div>'),
		'sid'        => '-1',
		'version'    => '',
		'dateline'    => TIME_NOW
	);
	$db->insert_query("templates", $insert_array);
	
	//TEMPLATE einzelausgabe einfügen
	$insert_array = array(
		'title'        => 'lastuser_bit',
		'template'    => $db->escape_string('<div class="lastuser_bit">
    <div class="lastuser_avatar">{$avatar}</div>
    <div class="lastuser_username">{$lastuser}</div>
</div>'),
		'sid'        => '-1',
		'version'    => '',
		'dateline'    => TIME_NOW
	);
	$db->insert_query("templates", $insert_array);
	
	//CSS hinzufügen 
	  $css = array(
    'name' => 'lastmember.css',
    'tid' => 1,
    'attachedto' => '',
    "stylesheet" =>    '
		/*Lastuser auf dem Index*/
		.lastuserall {
			display: flex;
			text-align: justify;
			justify-content: space-between;
		}

		.lastuser_bit {
			width: 33%;
			text-align: center;
		}

		.lastuser_username {
			text-align: center;
			margin-top: 10px;
		}
		',
    'cachefile' => $db->escape_string(str_replace('/', '', 'lastmember.css')),
    'lastmodified' => time()
  );
	
	 require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  $sid = $db->insert_query("themestylesheets", $css);
  $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);

  $tids = $db->simple_select("themes", "tid");
  while ($theme = $db->fetch_array($tids)) {
    update_theme_stylesheet_list($theme['tid']);
  }

}

// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function lastuser_is_installed(){

    global $db;
    
    if ($db->fetch_array($db->simple_select('settinggroups', '*', "name='lastuser'"))) {
		
        return true;
    }
    return false;
}
	


// Plugin-Deinstallation
function lastuser_uninstall()
{
    global $db, $cache;
	
	//CSS LÖSCHEN
	  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
	  $db->delete_query("themestylesheets", "name = 'lastmember.css'");
	  $query = $db->simple_select("themes", "tid");
	  while ($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	  }

    // Lösche die Plugin-Einstellungen
    $db->delete_query('settings', "name LIKE 'lastuser%'");
    $db->delete_query('settinggroups', "name = 'lastuser'");
	
    // Lösche die Vorlagengruppe und das Template
	$db->delete_query("templates", "title LIKE '%lastuser%'");
	
	rebuild_settings();
}

// Plugin-Aktivierung
function lastuser_activate()
{
	global $db, $cache;
 
	//hier steht noch nichts
		 

}

// Plugin-Deaktivierung
function lastuser_deactivate()
{
    global $db, $mybb;

	//hier passiert grad noch nichts 

}


//Hier für DIE AUSKLAPPBAREN EINSTELLUNGEN
$plugins->add_hook('admin_config_settings_change', 'lastuser_settings_change');
$plugins->add_hook('admin_settings_print_peekers', 'lastuser_settings_peek');
function lastuser_settings_change(){
    global $db, $mybb, $lastuser_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='lastuser'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $lastuser_settings_peeker = ($mybb->input['gid'] == $group['gid']) && ($mybb->request_method != 'post');
}

//in setting_lastuser_area kommt der Name der Einstellung, wo etwas ausgewählt werden muss damit etwas anderes erscheint
//in row setting kommt der Name der Einstellung, die erscheinen soll
//,/1/,true kommt das rein, was vorher ausgewählt werden muss 1= ja, 0=nein
function lastuser_settings_peek(&$peekers){
    global $mybb, $lastuser_settings_peeker;
if ($lastuser_settings_peeker) {
       	$peekers[] = 'new Peeker($(".setting_lastuser_area"), $("#row_setting_lastuser_area_pick"),/1/,true)';
		$peekers[] = 'new Peeker($(".setting_lastuser_avatar"), $("#row_setting_lastuser_avatar_height"),/1/,true)';
		$peekers[] = 'new Peeker($(".setting_lastuser_guest"), $("#row_setting_lastuser_avatar_question"),/1/,true)';
		$peekers[] = 'new Peeker($(".setting_lastuser_avatar"), $("#row_setting_lastuser_noavatar"),/1/,true)';
		$peekers[] = 'new Peeker($(".setting_lastuser_avatar_question"), $("#row_setting_lastuser_avatar_question_noava"),/1/,true)';
    }
	
}


function lastuser_index()
{
    global $db, $mybb, $templates, $lastusers, $theme, $user;
	
		if (isset($mybb->settings['lastuser_avatar'])) {
			
		//Einstellung ziehen
		$settingava = $mybb->settings['lastuser_avatar'];
		$settingguestquestion = $mybb->settings['lastuser_avatar_question'];
		$settingguestnoava = $mybb->settings['lastuser_avatar_question_noava'];
		
	
		//Abfrage bei User für die Gruppe und für wie viele Angezeigt werden sollen
		$groupId = $mybb->settings['lastuser_groups'];
		$number = $mybb->settings['lastuser_number'];
		$users = array();
	
		$query = $db->simple_select("users", "*", "usergroup in (".$groupId.")", array("order_by" => 'regdate', "order_dir" => 'DESC', "limit" =>$number));
		$lastusers_bit ="";			
		while ($user = $db->fetch_array($query)) {
		
			//leer laufen lassen, sonst stehen Inhalte von vorher drinnen. PHP 8!!!
			$avatar = "";
			$lastuser = "";
			
			//Mit Infos füllen
			//Abfrage vom Usernamen und bilden vom Profillink
			$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
			$lastuser .= build_profile_link($user['username'], $user['uid']);
			
			//Abfrage, ob in den Einstellungen hinterlegt ist, dass Avatare angezeigt werden sollen.
			if ($settingava =='1') {	
			
				//Abfrage, ob Gäste Avatare sehen dürfen. Wenn Gast auf der Seite ist
				if ($settingguestquestion == '1'  &&  $mybb->user['uid'] == '0') {

					
					//Abfrage, ob Gäste ein Noava sehen sollen, ansonsten gib normal aus.
					if ($settingguestnoava == '1') { 
						
						$avatar = "<img src='{$theme['imgdir']}/{$mybb->settings['lastuser_noavatar']}' height='{$mybb->settings['lastuser_avatar_height']}'>";
					}
					else {
						
						//Abfrage, ob ein user kein Ava hinterlegt hat, dann gib no ava aus. Ansonsten das vom Profil. 
						if (empty($user['avatar'])) {
							$avatar = "<img src='{$theme['imgdir']}/{$mybb->settings['lastuser_noavatar']}' height='{$mybb->settings['lastuser_avatar_height']}'>";
						}
						else {
							$avatar = "<img src=".$user['avatar']." height='{$mybb->settings['lastuser_avatar_height']}'>";
						}
					
					}
				}
					
				//Abfrage, ob Gäste Avatare sehen dürfen. Wenn User auf der Seite ist
				elseif ($settingguestquestion == '1' || $settingguestquestion == '0'  &&  $mybb->user['uid'] != '0') {
					if (empty($user['avatar'])) {
						$avatar = "<img src='{$theme['imgdir']}/{$mybb->settings['lastuser_noavatar']}' height='{$mybb->settings['lastuser_avatar_height']}'>";
					}
					else {
						$avatar = "<img src=".$user['avatar']." height='{$mybb->settings['lastuser_avatar_height']}'>";
					} 
				}
				
				else {
					$avatar ="";  
				}
			}
			else {
				$avatar ="";    
			}
			
		eval("\$lastusers_bit .= \"".$templates->get("lastuser_bit")."\";"); //Template für die Einzelausgabe
		}
    }

    eval("\$lastusers = \"".$templates->get("lastuser_all")."\";");
    output_page($page);
}
