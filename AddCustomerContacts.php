<?php
/* $Revision: 1.6 $ */
/* $Id$*/

include('includes/session.inc');
$title = _('Customer Contacts');
include('includes/header.inc');
include('includes/SQL_CommonFunctions.inc');

if (isset($_GET['Id'])){
	$Id = (int)$_GET['Id'];
} else if (isset($_POST['Id'])){
	$Id = (int)$_POST['Id'];
}
if (isset($_POST['DebtorNo'])){
	$DebtorNo = $_POST['DebtorNo'];
} elseif (isset($_GET['DebtorNo'])){
	$DebtorNo = $_GET['DebtorNo'];
}
echo '<a href="' . $rootpath . '/Customers.php?DebtorNo='.$DebtorNo.'">' . _('Back to Customers') . '</a><br />';
$SQLname="SELECT name FROM debtorsmaster WHERE debtorno='".$DebtorNo."'";
$Result = DB_query($SQLname,$db);
$row = DB_fetch_array($Result);
if (!isset($_GET['Id'])) {
	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/maintenance.png" title="' . _('Search') . '" alt="" />' .
			' ' . _('Contacts for Customer: <b>') .$row['name'].'</p><br />';
} else {
	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/maintenance.png" title="' . _('Search') . '" alt="" />' .
			' ' . _('Edit contact for <b>') .$row['name'].'</p><br />';
}
if ( isset($_POST['submit']) ) {

	//initialise no input errors assumed initially before we test
	$InputError = 0;
	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	//first off validate inputs sensible
	if (isset($_POST['Con_ID']) and !is_long((integer)$_POST['Con_ID'])) {
		$InputError = 1;
		prnMsg( _('The Contact ID must be an integer.'), 'error');
	} elseif (mb_strlen($_POST['conName']) >40) {
		$InputError = 1;
		prnMsg( _('The contact name must be forty characters or less long'), 'error');
	} elseif( trim($_POST['conName']) == '' ) {
		$InputError = 1;
		prnMsg( _('The contact name may not be empty'), 'error');
	}

	if (isset($Id) and ($Id and $InputError !=1)) {

		$sql = "UPDATE custcontacts SET
				contactname='" . $_POST['conName'] . "',
				role='" . $_POST['conRole'] . "',
				phoneno='" . $_POST['conPhone'] . "',
				notes='" . $_POST['conNotes'] . "'
			WHERE debtorno ='".$DebtorNo."'
			AND contid='".$Id."'";
		$msg = _('Customer Contacts') . ' ' . $DebtorNo  . ' ' . _('has been updated');
	} elseif ($InputError !=1) {

		$sql = "INSERT INTO custcontacts (debtorno,contactname,role,phoneno,notes)
				VALUES (
					'" . $DebtorNo. "',
					'" . $_POST['conName'] . "',
					'" . $_POST['conRole'] . "',
					'" . $_POST['conPhone'] . "',
					'" . $_POST['conNotes'] . "'
					)";
		$msg = _('The contact record has been added');
	}

	if ($InputError !=1) {
		$result = DB_query($sql,$db);
				//echo '<br />'.$sql;

		echo '<br />';
		prnMsg($msg, 'success');
		echo '<br />';
		unset($Id);
		unset($_POST['conName']);
		unset($_POST['conRole']);
		unset($_POST['conPhone']);
		unset($_POST['conNotes']);
		unset($_POST['Con_ID']);
	}
	} elseif (isset($_GET['delete']) and $_GET['delete']) {
//the link to delete a selected record was clicked instead of the submit button

// PREVENT DELETES IF DEPENDENT RECORDS IN 'SalesOrders'

	$sql="DELETE FROM custcontacts WHERE contid=".$Id."
			and debtorno='".$DebtorNo."'";
				$result = DB_query($sql,$db);
						//echo '<br />'.$sql;

				echo '<br />';
				prnMsg( _('The contact record has been deleted'), 'success');
				unset($Id);
				unset($_GET['delete']);

	}

if (!isset($Id)) {

	$sql = "SELECT contid,
					debtorno,
					contactname,
					role,
					phoneno,
					notes
				FROM custcontacts
				WHERE debtorno='".$DebtorNo."'
				ORDER BY contid";
	$result = DB_query($sql,$db);
			//echo '<br />'.$sql;

	echo '<table class="selection">';
	echo '<tr>
			<th>' . _('Name') . '</th>
			<th>' . _('Role') . '</th>
			<th>' . _('Phone no') . '</th>
			<th>' . _('Notes') . '</th>';

	$k=0; //row colour counter

	while ($myrow = DB_fetch_array($result)) {
		if ($k==1){
			echo '<tr class="OddTableRows">';
			$k=0;
		} else {
			echo '<tr class="EvenTableRows">';
			$k=1;
		}
		printf('<td>%s</td>
				<td>%s</td>
				<td>%s</td>
				<td>%s</td>
				<td><a href="%sId=%s&DebtorNo=%s">'. _('Edit').' </td>
				<td><a href="%sId=%s&DebtorNo=%s&delete=1">'. _('Delete'). '</td></tr>',
				$myrow['contactname'],
				$myrow['role'],
				$myrow['phoneno'],
				$myrow['notes'],
				$_SERVER['PHP_SELF'] . '?',
				$myrow['contid'],
				$myrow['debtorno'],
				$_SERVER['PHP_SELF'] . '?',
				$myrow['contid'],
				$myrow['debtorno']);

	}
	//END WHILE LIST LOOP
	echo '</table>';
}
if (isset($Id)) {  ?>
	<div class="centre"><a href="<?php echo $_SERVER['PHP_SELF'] . '?DebtorNo='.$DebtorNo;?>"><?=_('Review all contacts for this Customer')?></a></div>
<?php } ?>
<br />

<?php
if (!isset($_GET['delete'])) {

	echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . 'DebtorNo='.$DebtorNo.'">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	if (isset($Id)) {
		//editing an existing Shipper

		$sql = "SELECT contid,
						contactname,
						role,
						phoneno,
						notes,
						debtorno
					FROM custcontacts
					WHERE contid='".$Id."'
						AND debtorno='".$DebtorNo."'";

		$result = DB_query($sql, $db);
				//echo '<br />'.$sql;

		$myrow = DB_fetch_array($result);

		$_POST['Con_ID'] = $myrow['contid'];
		$_POST['conName']	= $myrow['contactname'];
		$_POST['conRole']  = $myrow['role'];
		$_POST['conPhone']  = $myrow['phoneno'];
		$_POST['conNotes']  = $myrow['notes'];
		$_POST['debtorno']  = $myrow['debtorno'];
		echo '<input type="hidden" name="Id" value="'. $Id .'" />';
		echo '<input type="hidden" name="Con_ID" value="' . $_POST['Con_ID'] . '" />';
		echo '<input type="hidden" name="DebtorNo" value="' . $_POST['debtorno'] . '" />';
		echo '<table class="selection"><tr><td>'. _('Contact Code').':</td><td>' . $_POST['Con_ID'] . '</td></tr>';
	} else {
		echo '<table class="selection">';
	}

	echo '<tr><td>'. _('Contact Name') . '</td>';
    if (isset($_POST['conName'])) {
        echo '<td><input type="text" name="conName" value="' . $_POST['conName']. '" size="35" maxlength="40" /></td></tr>';
    } else {
        echo '<td><input type="text" name="conName" size="35" maxlength="40" /></td></tr>';
    }
	echo '<tr><td>' . _('Role') . '</td>';
    if (isset($_POST['conRole'])) {
        echo '<td><input type="text" name="conRole" value="'. $_POST['conRole']. '" size="35" maxlength="40" /></td></tr>';
    } else {
        echo '<td><input type="text" name="conRole" size="35" maxlength="40" /></td></tr>';
    }
	echo '<tr><td>' . _('Phone') . '</td>';
    if (isset($_POST['conPhone'])) {
        echo '<td><input type="text" name="conPhone" value="' . $_POST['conPhone'] . '" size="35" maxlength="40" /></td></tr>';
    } else {
        echo '<td><input type="text" name="conPhone"" size="35" maxlength="40" /></td></tr>';
    }
	echo '<tr><td>' . _('Notes') . '</td>';
    if (isset($_POST['conNotes'])) {
        echo '<td><textarea name="conNotes">'. $_POST['conNotes'] . '</textarea>';
    } else {
       echo '<td><textarea name="conNotes"></textarea>';
    }
	echo '<tr><td colspan="2"><div class="centre"><input type="submit" name="submit" value="'. _('Enter Information') . '" /></div></td></tr>';
	echo '</table>';
	echo '</form>';

} //end if record deleted no point displaying form to add record

include('includes/footer.inc');
?>