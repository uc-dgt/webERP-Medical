<?php

/* $Id: SalesCategoryPeriodInquiry.php 4261 2010-12-22 15:56:50Z  $*/

include('includes/session.inc');
$title = _('Sales Category Report');
include('includes/header.inc');
include('includes/DefineCartClass.php');

echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/transactions.png" title="' . _('Sales Report') . '" alt="" />' . ' ' . _('Sales Category Report') . '</p>';
echo '<div class="page_help_text">' . _('Select the parameters for the report') . '</div><br />';

if (!isset($_POST['DateRange'])){
	/* then assume report is for This Month - maybe wrong to do this but hey better than reporting an error?*/
	$_POST['DateRange']='ThisMonth';
}

echo '<form name="form1" action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" method="post">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

echo '<table cellpadding="2" class="selection">';

echo '<tr><th colspan="2" class="centre">' . _('Date Selection') . '</th>
		</tr>
		<tr>
		<td>' . _('Custom Range') . ':</td>';
if ($_POST['DateRange']=='Custom') {
	echo '<td><input type="radio" name="DateRange" value="Custom" checked="True" onChange="ReloadForm(form1.ShowSales)" />';
} else {
	echo '<td><input type="radio" name="DateRange" value="Custom" onChange="ReloadForm(form1.ShowSales)" />';
}
echo	'</td>
		</tr>
		<tr>
		<td>' . _('This Week') . ':</td>
		<td>';
if ($_POST['DateRange']=='ThisWeek') {
	echo '<input type="radio" name="DateRange" value="ThisWeek" checked="True" onChange="ReloadForm(form1.ShowSales)" />';
} else {
	echo '<input type="radio" name="DateRange" value="ThisWeek" onChange="ReloadForm(form1.ShowSales)" />';
}
echo	'</td>
		</tr>
		<tr>
		<td>' . _('This Month') . ':</td>
		<td>';
if ($_POST['DateRange']=='ThisMonth') {
	echo '<input type="radio" name="DateRange" value="ThisMonth" checked="True" onChange="ReloadForm(form1.ShowSales)" />';
} else {
	echo '<input type="radio" name="DateRange" value="ThisMonth" onChange="ReloadForm(form1.ShowSales)" />';
}
echo	'</td>
		</tr>
		<tr>
		<td>' . _('This Quarter') . ':</td>
		<td>';
if ($_POST['DateRange']=='ThisQuarter') {
	echo '<input type="radio" name="DateRange" value="ThisQuarter" checked="True" onChange="ReloadForm(form1.ShowSales)" />';
} else {
	echo '<input type="radio" name="DateRange" value="ThisQuarter" onChange="ReloadForm(form1.ShowSales)" />';
}
echo	'</td>
		</tr>';
if ($_POST['DateRange']=='Custom'){
	if (!isset($_POST['FromDate'])){
		unset($_POST['ShowSales']);
		$_POST['FromDate'] = Date($_SESSION['DefaultDateFormat'],mktime(1,1,1,Date('m')-12,Date('d')+1,Date('Y')));
		$_POST['ToDate'] = Date($_SESSION['DefaultDateFormat']);
	}
	echo '<tr>
			<td>' . _('Date From') . ':</td>
			<td><input type="text" class="date" alt="' . $_SESSION['DefaultDateFormat'] . '" name="FromDate" maxlength="10" size="11" value="' . $_POST['FromDate'] . '" /></td>
			</tr>';
	echo '<tr>
			<td>' . _('Date To') . ':</td>
			<td><input type="text" class="date" alt="' . $_SESSION['DefaultDateFormat'] . '" name="ToDate" maxlength="10" size="11" value="' . $_POST['ToDate'] . '" /></td>
			</tr>';
}
echo '</table>';


echo '<br /><div class="centre"><button tabindex="4" type="submit" name="ShowSales">' . _('Show Sales') . '</button>';
echo '</div></form>';
echo '<br />';


if (isset($_POST['ShowSales'])){
	$InputError=0; //assume no input errors now test for errors
	if ($_POST['DateRange']=='Custom'){
		if (!Is_Date($_POST['FromDate'])){
			$InputError = 1;
			prnMsg(_('The date entered for the from date is not in the appropriate format. Dates must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'], 'error');
		}
		if (!Is_Date($_POST['ToDate'])){
			$InputError = 1;
			prnMsg(_('The date entered for the to date is not in the appropriate format. Dates must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'], 'error');
		}
		if (Date1GreaterThanDate2($_POST['FromDate'],$_POST['ToDate'])){
			$InputError = 1;
			prnMsg(_('The from date is expected to be a date prior to the to date. Please review the selected date range'),'error');
		}
	}
	switch ($_POST['DateRange']) {
		case 'ThisWeek':
			$FromDate = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-date('w')+1,date('Y')));
			$ToDate = date('Y-m-d');
			break;
		case 'ThisMonth':
			$FromDate = date('Y-m-d',mktime(0,0,0,date('m'),1,date('Y')));
			$ToDate = date('Y-m-d');
			break;
		case 'ThisQuarter':
			switch (date('m')) {
				case 1:
				case 2:
				case 3:
					$QuarterStartMonth=1;
					break;
				case 4:
				case 5:
				case 6:
					$QuarterStartMonth=4;
					break;
				case 7:
				case 8:
				case 9:
					$QuarterStartMonth=7;
					break;
				default:
					$QuarterStartMonth=10;
			}
			$FromDate = date('Y-m-d',mktime(0,0,0,$QuarterStartMonth,1,date('Y')));
			$ToDate = date('Y-m-d');
			break;
		case 'Custom':
			$FromDate = FormatDateForSQL($_POST['FromDate']);
			$ToDate = FormatDateForSQL($_POST['ToDate']);
	}
	$sql = "SELECT stockmaster.categoryid,
					stockcategory.categorydescription,
					SUM(CASE WHEN stockmoves.type=10 THEN
							price*(1-discountpercent)* -qty
							ELSE 0 END) as salesvalue,
					SUM(CASE WHEN stockmoves.type=11 THEN
							price*(1-discountpercent)* (-qty)
							ELSE 0 END) as returnvalue,
					SUM(CASE WHEN stockmoves.type=11
								OR stockmoves.type=10 THEN
							price*(1-discountpercent)* (-qty)
							ELSE 0 END) as netsalesvalue,
					SUM((standardcost * -qty)) as cost
			FROM stockmoves INNER JOIN stockmaster
			ON stockmoves.stockid=stockmaster.stockid
			INNER JOIN stockcategory
			ON stockmaster.categoryid=stockcategory.categoryid
			WHERE (stockmoves.type=10 or stockmoves.type=11)
			AND show_on_inv_crds =1
			AND trandate>='" . $FromDate . "'
			AND trandate<='" . $ToDate . "'
			GROUP BY stockmaster.categoryid
			ORDER BY netsalesvalue DESC";

	$ErrMsg = _('The sales data could not be retrieved because') . ' - ' . DB_error_msg($db);
	$SalesResult = DB_query($sql,$db,$ErrMsg);

	echo '<table cellpadding="2" class="selection">';

	echo'<tr>
		<th>' . _('Category') . '</th>
		<th>' . _('Total Sales') . '</th>
		<th>' . _('Refunds') . '</th>
		<th>' . _('Net Sales') . '</th>
		<th>' . _('Cost of Sales') . '</th>
		<th>' . _('Gross Profit') . '</th>
		</tr>';

	$CumulativeTotalSales = 0;
	$CumulativeTotalRefunds = 0;
	$CumulativeTotalNetSales = 0;
	$CumulativeTotalCost = 0;
	$CumulativeTotalGP = 0;

	$k=0;
	while ($SalesRow=DB_fetch_array($SalesResult)) {
		if ($k==1){
			echo '<tr class="EvenTableRows">';
			$k=0;
		} else {
			echo '<tr class="OddTableRows">';
			$k=1;
		}

		echo '<td>' . $SalesRow['categoryid'] . ' - ' . $SalesRow['categorydescription'] . '</td>
				<td class="number">' . locale_money_format($SalesRow['salesvalue'],$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number">' . locale_money_format($SalesRow['returnvalue'],$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number">' . locale_money_format($SalesRow['salesvalue']+$SalesRow['returnvalue'],$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number">' . locale_money_format($SalesRow['cost'],$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number">' . locale_money_format(($SalesRow['salesvalue']+$SalesRow['returnvalue']-$SalesRow['cost']),$_SESSION['CompanyRecord']['currencydefault']) . '</td>
			</tr>';

		$CumulativeTotalSales += $SalesRow['salesvalue'];
		$CumulativeTotalRefunds += $SalesRow['returnvalue'];
		$CumulativeTotalNetSales += ($SalesRow['salesvalue']+$SalesRow['returnvalue']);
		$CumulativeTotalCost += $SalesRow['cost'];
		$CumulativeTotalGP += ($SalesRow['salesvalue']+$SalesRow['returnvalue']-$SalesRow['cost']);
	} //loop around category sales for the period

	if ($k==1){
		echo '<tr class="EvenTableRows"><td colspan="8" style="background: transparent;"><hr /></td></tr>';
		echo '<tr class="OddTableRows">';
	} else {
		echo '<tr class="OddTableRows"><td colspan="8" style="background: transparent;"><hr /></td></tr>';
		echo '<tr class="EvenTableRows">';
	}
	echo '<td class="number">' . _('GRAND Total') . '</td>
		<td class="number">' . locale_money_format($CumulativeTotalSales,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
		<td class="number">' . locale_money_format($CumulativeTotalRefunds,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
		<td class="number">' . locale_money_format($CumulativeTotalNetSales,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
		<td class="number">' . locale_money_format($CumulativeTotalCost,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
		<td class="number">' . locale_money_format($CumulativeTotalGP,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
		</tr>';

	echo '</table>';

} //end of if user hit show sales
include('includes/footer.inc');
?>