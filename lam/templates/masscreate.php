<?php
/*
$Id$

  This code is part of LDAP Account Manager (http://www.sourceforge.net/projects/lam)
  Copyright (C) 2003  Tilo Lutz

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA


  LDAP Account Manager displays table for creating or modifying accounts in LDAP
*/

include_once('../lib/account.inc'); // File with custom functions
include_once('../lib/config.inc'); // File with configure-functions
include_once('../lib/ldap.inc'); // LDAP-functions
include_once('../lib/profiles.inc'); // functions to load and save profiles
include_once('../lib/status.inc'); // Return error-message
include_once('../lib/pdf.inc'); // Return a pdf-file

session_save_path('../sess');
@session_start();

$time=time();
if ($_POST['tolist'] && ($_FILES['userfile']['size']>0)) $select = 'list';
if ($_POST['back']) $select = 'main';
if ($_POST['cancel']) $select = 'cancel';
if ($_POST['create']) $select = 'create';
if ($_POST['pdf']) {
	createpdf($_SESSION['accounts']);
	$select='pdf';
	}
if (!$select && !$_SESSION['pointer']) $select='main';
if (!$select && $_SESSION['pointer']) $select='create';


if ($select!='pdf') {
	// Write HTML-Header and part of Table
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
		"http://www.w3.org/TR/html4/loose.dtd">';
	echo '<html><head><title>';
	echo _('Create new Accounts');
	echo '</title>
		<link rel="stylesheet" type="text/css" href="../style/layout.css">
		<meta http-equiv="pragma" content="no-cache">
		<meta http-equiv="cache-control" content="no-cache">
		<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">';
	}

switch ($select) {
	case 'cancel':
		if ( session_is_registered("accounts")) session_unregister("accounts");
		if ( session_is_registered("pointer")) session_unregister("pointer");
		echo '<meta http-equiv="refresh" content="0; URL=lists/listusers.php">';
		break;
	case 'create':
		if ($_SESSION['pointer'] < sizeof($_SESSION['accounts'])) {
			$refresh = get_cfg_var('max_execution_time')-5;
			echo '<meta http-equiv="refresh" content="'.$refresh.'; URL=masscreate.php">';
			}
		break;
	}

if ($select!='pdf') {
	echo	'</head><body>
		<form enctype="multipart/form-data" action="masscreate.php" method="post">';
	echo '<table class="masscreate" width="100%">
		<tr><td></td></tr>';
	}

switch ($select) {
	case 'main':
		if ( session_is_registered("accounts")) session_unregister("accounts");
		if ( session_is_registered("pointer")) session_unregister("pointer");
		session_register("accounts");
		session_register("pointer");
		$_SESSION['pointer']=0;
		$profilelist = getUserProfiles();
		echo '<tr><td><input name="select" type="hidden" value="main">';
		echo _('Mass Creation');
		echo '</td></tr><tr><td>';
		echo _('Please provide a csv-file with the following syntax. Values with * are required:');
		echo '</td></tr><tr><td>';
		echo _('Surname*,Givenname*,Username*,PrimaryGroup,Title,Mail,telephonenumber,');
		echo '</td></tr><tr><td>';
		echo _('mobileTelephoneNumber,facsimileNumber,street,postalCode,postalAddress,');
		echo '</td></tr><tr><td>';
		echo _('employeeType. If PrimaryGroup is not given it\'ll used from profile.');
		echo '</td></tr><tr><td>';
		echo _('If PrimaryGroup doesn\'t exist it will be created.');
		echo '</td></tr><tr><td>';
		echo _('Select Profile:');
		echo '</td><td><select name="f_selectprofile">';
			foreach ($profilelist as $profile) echo '<option>' . $profile;
			echo '</select>';
		echo '</td></tr><tr><td>
			<input type="hidden" name="MAX_FILE_SIZE" value="100000">';
		echo _('Select file:');
		echo '</td><td><input name="userfile" type="file"></td></tr>
			<tr><td><input name="tolist" type="submit" value="'; echo _('Commit'); echo '">';
		echo '</td></tr>';
		break;
	case 'list':
		if (!is_array($accounts)) $accounts = array();
	 	$handle = fopen($_FILES['userfile']['tmp_name'], 'r');
		$error=false;
		$groups = array();
		echo '<tr><td>';
		echo _('Confirm List');
		echo '</td></tr>';
		for ($row=0; $line_array=fgetcsv($handle,2048); $row++) { // loops for every row
			$_SESSION['accounts'][$row] = loadUserProfile($_POST['f_selectprofile']) ;
			if ($line_array[0]) $_SESSION['accounts'][$row]->general_surname = $line_array[0];
			if ($line_array[1]) $_SESSION['accounts'][$row]->general_givenname = $line_array[1];
			if ($line_array[2]) $_SESSION['accounts'][$row]->general_username = $line_array[2];
			if ($line_array[3]) $_SESSION['accounts'][$row]->general_group = $line_array[3];
			if ($line_array[4]) $_SESSION['accounts'][$row]->personal_title = $line_array[4];
			if ($line_array[5]) $_SESSION['accounts'][$row]->personal_mail = $line_array[5];
			if ($line_array[6]) $_SESSION['accounts'][$row]->personal_telephoneNumber = $line_array[6];
			if ($line_array[7]) $_SESSION['accounts'][$row]->personal_mobileTelephoneNumber = $line_array[7];
			if ($line_array[8]) $_SESSION['accounts'][$row]->personal_facsimileTelephoneNumber = $line_array[8];
			if ($line_array[9]) $_SESSION['accounts'][$row]->personal_street = $line_array[9];
			if ($line_array[10]) $_SESSION['accounts'][$row]->personal_postalCode = $line_array[10];
			if ($line_array[11]) $_SESSION['accounts'][$row]->personal_postalAddress = $line_array[11];
			if ($line_array[12]) $_SESSION['accounts'][$row]->personal_employeeType = $line_array[12];
			$_SESSION['accounts'][$row]->unix_password=genpasswd();
			$_SESSION['accounts'][$row]->smb_password=genpasswd();
			$values = checkglobal($_SESSION['accounts'][$row], 'user'); // account.inc
			if (is_object($values)) {
				while (list($key, $val) = each($values)) // Set only defined values
					if ($val) $_SESSION['accounts'][$row]->$key = $val;
				$_SESSION['accounts'][$row]->general_uidNumber="";
				}
				else $error = $values;
			if (!$error) {
				$values = checkpersonal($_SESSION['accounts'][$row], 'user'); // account.inc
				if (is_object($values)) {
					while (list($key, $val) = each($values)) // Set only defined values
						if ($val) $_SESSION['accounts'][$row]->$key = $val;
					}
					else $error = $values;
				}
			if (!$error) {
				$values = checksamba($_SESSION['accounts'][$row], 'user'); // account.inc
				if (is_object($values)) {
					while (list($key, $val) = each($values)) // Set only defined values
						if ($val) $_SESSION['accounts'][$row]->$key = $val;
					}
					else $error = $values;
				$values = checkquota($_SESSION['accounts'][$row], 'user'); // account.inc
				}
			if (!$error) {
				if (is_object($values)) {
					while (list($key, $val) = each($values)) // Set only defined values
						if ($val) $_SESSION['accounts'][$row]->$key = $val;
					}
					else $error = $values;
				}
			if ($error) StatusMessage('ERROR', _('Invalid Value in row ').$row.'!', $error);
			if ((getgid($_SESSION['accounts'][$row]->general_group)==-1) && (!in_array($_SESSION['accounts'][$row]->general_group, $groups))) $groups[] = $_SESSION['accounts'][$row]->general_group;
			}
		for ($i=0; $i<sizeof($groups); $i++)
			StatusMessage('INFO', _('Group ').
				$_SESSION['accounts'][$i]->general_group._(' not found!'), _('It will be created.'));
		fclose($handle);
		unlink($_FILES['userfile']['tmp_name']);
		echo '<tr><td>'._('row').'</td><td>'. _('Surname'). '</td><td>'. _('Givenname'). '</td><td>'. _('Username'). '</td><td>'. _('Primary Group'). '</td><td>'.
			_('Title'). '</td><td>'. _('Mail Address'). '</td><td>'. _('Telephonenumber'). '</td><td>'. _('Mobiletelephonenumber')
			. '</td><td>'. _('Facsimiletelephonenumber'). '</td><td>'. _('Street'). '</td><td>'. _('Postal Code')
			. '</td><td>'. _('Postal Address'). '</td><td>'. _('Employee Type') .'</td></tr>';
		for ($row=0; $row<sizeof($_SESSION['accounts']); $row++) { // loops for every row
			echo '<tr><td>'.$row.'</td><td>'.
				$_SESSION['accounts'][$row]->general_surname.'</td><td>'.
				$_SESSION['accounts'][$row]->general_givenname.'</td><td>'.
				$_SESSION['accounts'][$row]->general_username.'</td><td>'.
				$_SESSION['accounts'][$row]->general_group.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_title.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_mail.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_telephoneNumber.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_mobileTelephoneNumber.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_facsimileTelephoneNumber.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_street.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_postalCode.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_postalAddress.'</td><td>'.
				$_SESSION['accounts'][$row]->personal_employeeType.'</td></tr>';
			}
		echo '<tr><td><input name="back" type="submit" value="'; echo _('Back'); echo '">';
		echo '</td><td><input name="cancel" type="submit" value="'; echo _('Cancel'); echo '">';
		echo '</td><td><input name="create" type="submit" value="'; echo _('Create'); echo '">';
		break;
	case 'create':
		$stay=true;
		while (($_SESSION['pointer'] < sizeof($_SESSION['accounts'])) && $stay) {
			if (getgid($_SESSION['accounts'][$_SESSION['pointer']]->general_group)==-1) {
				$group = new account();
				$group->general_username=$_SESSION['accounts'][$_SESSION['pointer']]->general_group;
				$group->general_uidNumber=checkid($_SESSION['accounts'][$_SESSION['pointer']], 'group');
				$group->general_gecos=$_SESSION['accounts'][$_SESSION['pointer']]->general_group;
				creategroup($group);
				}
			$_SESSION['accounts'][$_SESSION['pointer']]->general_uidNumber = checkid($_SESSION['accounts'][$_SESSION['pointer']], 'user');
			if ( time()-$time<(get_cfg_var('max_execution_time')-10)) {
				$error = createuser($_SESSION['accounts'][$_SESSION['pointer']]);
				if ($error==1) $_SESSION['pointer']++;
					else {
					$stay = false;
					StatusMessage('ERROR', _('Could not create user'), _('Was unable to create ').$_SESSION['accounts'][$row]->general_username);
					}
				}
				else $stay=false;
			}
		if (!$stay) { echo '<tr><td><input name="cancel" type="submit" value="'; echo _('Cancel'); echo '">
			<td>'._('Please wait until all users are created if no error is shown.').'</td></tr>'; }
			else {
			echo '<tr><td>';
			echo _('All Users have been created');
			echo '</td></tr><tr><td>';
			echo '<tr><td><input name="cancel" type="submit" value="'; echo _('Mainmenu'); echo '">';
			echo '</td><td></td><td><input name="pdf" type="submit" value="'; echo _('Create PDF-File'); echo '">';
			echo '</td></tr>';
			}
		break;
	}


if ($select!='pdf') echo '</table></form></body></html>';
?>
