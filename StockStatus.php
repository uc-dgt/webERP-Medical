<?php

/* $Id$*/

include('includes/session.inc');

$title = _('Stock Status');

include('includes/header.inc');

if (isset($_GET['StockID'])){
	$StockID = trim(strtoupper($_GET['StockID']));
} elseif (isset($_POST['StockID'])){
	$StockID = trim(strtoupper($_POST['StockID']));
} else {
	$StockID = '';
}

// This is already linked from this page
//echo '<a href='" . $rootpath . '/SelectProduct.php'>" . _('Back to Items') . '</a><br />';

$result = DB_query("SELECT description,
							units,
							mbflag,
							decimalplaces,
							serialised,
							controlled
					FROM stockmaster
					WHERE stockid='".$StockID."'",
							$db,
							_('Could not retrieve the requested item'),
							_('The SQL used to retrieve the items was'));

$myrow = DB_fetch_array($result);

$DecimalPlaces = $myrow['decimalplaces'];
$Serialised = $myrow['serialised'];
$Controlled = $myrow['controlled'];

echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/inventory.png" title="' . _('Inventory') . '" alt="" />
		<b>' . ' ' . $StockID . ' - ' . $myrow['description'] . ' : ' . _('in units of') . ' : ' . $myrow['units'] . '</b></p>';

$Its_A_KitSet_Assembly_Or_Dummy =False;
if ($myrow['mbflag']=='K'){
	$Its_A_KitSet_Assembly_Or_Dummy =True;
	prnMsg( _('This is a kitset part and cannot have a stock holding') . ', ' . _('only the total quantity on outstanding sales orders is shown'),'info');
} elseif ($myrow['mbflag']=='A'){
	$Its_A_KitSet_Assembly_Or_Dummy =True;
	prnMsg(_('This is an assembly part and cannot have a stock holding') . ', ' . _('only the total quantity on outstanding sales orders is shown'),'info');
} elseif ($myrow['mbflag']=='D'){
	$Its_A_KitSet_Assembly_Or_Dummy =True;
	prnMsg( _('This is an dummy part and cannot have a stock holding') . ', ' . _('only the total quantity on outstanding sales orders is shown'),'info');
}

echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
echo '<table class="selection"><input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
echo '<tr><td>' . _('Stock Code') . ':</td><td><input type="text" name="StockID" size="21" value="' . $StockID . '" maxlength="20" /></td>';

echo '<td><button type="submit" name="ShowStatus">' . _('Show Stock Status') . '</button></td></tr></table>';

$sql = "SELECT locstock.loccode,
				locations.locationname,
				locstock.quantity,
				locstock.reorderlevel,
			locations.managed
				FROM locstock,
					locations
				WHERE locstock.loccode=locations.loccode AND
					 locstock.stockid = '" . $StockID . "'
				ORDER BY locstock.loccode";

$ErrMsg = _('The stock held at each location cannot be retrieved because');
$DbgMsg = _('The SQL that was used to update the stock item and failed was');
$LocStockResult = DB_query($sql, $db, $ErrMsg, $DbgMsg);

echo '<br /><table cellpadding="2" class="selection">';

if ($Its_A_KitSet_Assembly_Or_Dummy == True){
	$tableheader = '<tr>
			<th>' . _('Location') . '</th>
			<th>' . _('Demand') . '</th>
			</tr>';
} else {
	$tableheader = '<tr>
			<th>' . _('Location') . '</th>
			<th>' . _('Quantity On Hand') . '</th>
			<th>' . _('Re-Order Level') . '</th>
			<th>' . _('Demand') . '</th>
			<th>' . _('Available') . '</th>
			<th>' . _('On Order') . '</th>
			</tr>';
}
echo $tableheader;
$j = 1;
$k=0; //row colour counter

while ($myrow=DB_fetch_array($LocStockResult)) {

	if ($k==1){
		echo '<tr class="EvenTableRows">';
		$k=0;
	} else {
		echo '<tr class="OddTableRows">';
		$k=1;
	}

	$sql = "SELECT SUM(salesorderdetails.quantity-salesorderdetails.qtyinvoiced)*salesorderdetails.conversionfactor AS dem
				 FROM salesorderdetails,
					  salesorders
				 WHERE salesorders.orderno = salesorderdetails.orderno AND
				 salesorders.fromstkloc='" . $myrow['loccode'] . "' AND
				 salesorderdetails.completed=0 AND
		 salesorders.quotation=0 AND
				 salesorderdetails.stkcode='" . $StockID . "'";

	$ErrMsg = _('The demand for this product from') . ' ' . $myrow['loccode'] . ' ' . _('cannot be retrieved because');
	$DemandResult = DB_query($sql,$db,$ErrMsg,$DbgMsg);

	if (DB_num_rows($DemandResult)==1){
	  $DemandRow = DB_fetch_array($DemandResult);
	  $DemandQty =  $DemandRow['dem'];
	} else {
	  $DemandQty =0;
	}

	//Also need to add in the demand as a component of an assembly items if this items has any assembly parents.
	$sql = "SELECT SUM((salesorderdetails.quantity-salesorderdetails.qtyinvoiced)*bom.quantity) AS dem
				 FROM salesorderdetails,
					  salesorders,
					  bom,
					  stockmaster
				 WHERE salesorderdetails.stkcode=bom.parent AND
						salesorders.orderno = salesorderdetails.orderno AND
						salesorders.fromstkloc='" . $myrow['loccode'] . "' AND
						salesorderdetails.quantity-salesorderdetails.qtyinvoiced > 0 AND
						bom.component='" . $StockID . "' AND stockmaster.stockid=bom.parent AND
						stockmaster.mbflag='A'
				AND salesorders.quotation=0";

	$ErrMsg = _('The demand for this product from') . ' ' . $myrow['loccode'] . ' ' . _('cannot be retrieved because');
	$DemandResult = DB_query($sql,$db,$ErrMsg,$DbgMsg);

	if (DB_num_rows($DemandResult)==1){
		$DemandRow = DB_fetch_array($DemandResult);
		$DemandQty += $DemandRow['dem'];
	}

	//Also the demand for the item as a component of works orders

	$sql = "SELECT SUM(qtypu*(woitems.qtyreqd - woitems.qtyrecd)) AS woqtydemo
				FROM woitems INNER JOIN worequirements
				ON woitems.stockid=worequirements.parentstockid
				INNER JOIN workorders
				ON woitems.wo=workorders.wo
				AND woitems.wo=worequirements.wo
				WHERE workorders.loccode='" . $myrow['loccode'] . "'
				AND worequirements.stockid='" . $StockID . "'
				AND workorders.closed=0";

	$ErrMsg = _('The workorder component demand for this product from') . ' ' . $myrow['loccode'] . ' ' . _('cannot be retrieved because');
	$DemandResult = DB_query($sql,$db,$ErrMsg,$DbgMsg);

	if (DB_num_rows($DemandResult)==1){
		$DemandRow = DB_fetch_array($DemandResult);
		$DemandQty += $DemandRow['woqtydemo'];
	}

	if ($Its_A_KitSet_Assembly_Or_Dummy == False){

		$QOOSQL="SELECT SUM((purchorderdetails.quantityord*purchorderdetails.conversionfactor) -
									(purchorderdetails.quantityrecd*purchorderdetails.conversionfactor)) AS totalonorder
								FROM purchorders
								LEFT JOIN purchorderdetails
									ON purchorders.orderno=purchorderdetails.orderno
								WHERE purchorderdetails.itemcode='" . $StockID . "'
									AND purchorderdetails.completed =0
									AND purchorders.intostocklocation='" . $myrow['loccode'] . "'
									AND purchorders.status<>'Cancelled'
									AND purchorders.status<>'Pending'
									AND purchorders.status<>'Rejected'";
		$QOOResult = DB_query($QOOSQL, $db);
		if (DB_num_rows($QOOResult) == 0) {
			$QOO = 0;
		} else {
			$QOORow = DB_fetch_array($QOOResult);
			$QOO = $QOORow['totalonorder'];
		}

		//Also the on work order quantities
		$sql = "SELECT SUM(woitems.qtyreqd-woitems.qtyrecd) AS qtywo
				FROM woitems INNER JOIN workorders
				ON woitems.wo=workorders.wo
				WHERE workorders.closed=0
				AND workorders.loccode='" . $myrow['loccode'] . "'
				AND woitems.stockid='" . $StockID . "'";
		$ErrMsg = _('The quantity on work orders for this product to be received into') . ' ' . $myrow['loccode'] . ' ' . _('cannot be retrieved because');
		$QOOResult = DB_query($sql,$db,$ErrMsg, $DbgMsg);

		if (DB_num_rows($QOOResult)==1){
			$QOORow = DB_fetch_array($QOOResult);
			$QOO +=  $QOORow['qtywo'];
		}

		echo '<td>' . $myrow['locationname'] . '</td>';

		printf('<td class="number">%s</td>
			<td class="number">%s</td>
			<td class="number">%s</td>
			<td class="number">%s</td>
			<td class="number">%s</td></tr>',
			locale_number_format($myrow['quantity'], $DecimalPlaces),
			locale_number_format($myrow['reorderlevel'], $DecimalPlaces),
			locale_number_format($DemandQty, $DecimalPlaces),
			locale_number_format($myrow['quantity'] - $DemandQty, $DecimalPlaces),
			locale_number_format($QOO, $DecimalPlaces)
			);

		if ($Serialised ==1){ /*The line is a serialised item*/

			echo '<td><a target="_blank" href="' . $rootpath . '/StockSerialItems.php?Serialised=Yes&Location=' . $myrow['loccode'] . '&StockID=' .$StockID . '">' . _('Serial Numbers') . '</a></td></tr>';
		} elseif ($Controlled==1){
			echo '<td><a target="_blank" href="' . $rootpath . '/StockSerialItems.php?Location=' . $myrow['loccode'] . '&StockID=' .$StockID . '">' . _('Batches') . '</a></td></tr>';
		}

	} else {
	/* It must be a dummy, assembly or kitset part */

		printf('<td>%s</td>
			<td class="number">%s</td>
			</tr>',
			$myrow['locationname'],
			locale_number_format($DemandQty, $DecimalPlaces)
			);
	}
//end of page full new headings if
}
//end of while loop
echo '</table>';

if (isset($_GET['DebtorNo'])){
	$DebtorNo = trim(strtoupper($_GET['DebtorNo']));
} elseif (isset($_POST['DebtorNo'])){
	$DebtorNo = trim(strtoupper($_POST['DebtorNo']));
} elseif (isset($_SESSION['CustomerID'])){
	$DebtorNo=$_SESSION['CustomerID'];
}

if ($DebtorNo) { /* display recent pricing history for this debtor and this stock item */

	$sql = "SELECT stockmoves.trandate,
				stockmoves.qty,
				stockmoves.price,
				stockmoves.discountpercent
			FROM stockmoves
			WHERE stockmoves.debtorno='" . $DebtorNo . "'
				AND stockmoves.type=10
				AND stockmoves.stockid = '" . $StockID . "'
				AND stockmoves.hidemovt=0
			ORDER BY stockmoves.trandate DESC";

	/* only show pricing history for sales invoices - type=10 */

	$ErrMsg = _('The stock movements for the selected criteria could not be retrieved because') . ' - ';
	$DbgMsg = _('The SQL that failed was') . ' ';

	$MovtsResult = DB_query($sql, $db, $ErrMsg, $DbgMsg);

	$k=1;
	$LastPrice=0;
	while ($myrow=DB_fetch_array($MovtsResult)) {
	  if (($LastPrice != $myrow['price'] or $LastDiscount != $myrow['discountpercent'])) { /* consolidate price history for records with same price/discount */
		if (isset($qty)) {
			$DateRange=ConvertSQLDate($FromDate);
			if ($FromDate != $ToDate) {
				$DateRange .= ' - ' . ConvertSQLDate($ToDate);
		 	}
			$PriceHistory[] = array($DateRange, $qty, $LastPrice, $LastDiscount);
			$k++;
			if ($k > 9) {
				break; /* 10 price records is enough to display */
			}
			if ($myrow['trandate'] < FormatDateForSQL(DateAdd(date($_SESSION['DefaultDateFormat']),'y', -1))) {
				break; /* stop displaying pirce history more than a year old once we have at least one  to display */
			}
		}
		$LastPrice = $myrow['price'];
		$LastDiscount = $myrow['discountpercent'];
		$ToDate = $myrow['trandate'];
		$qty = 0;
	  }
	  $qty += $myrow['qty'];
	  $FromDate = $myrow['trandate'];
	}
	if (isset($qty)) {
		$DateRange = ConvertSQLDate($FromDate);
		if ($FromDate != $ToDate) {
				$DateRange .= ' - '.ConvertSQLDate($ToDate);
		}
		$PriceHistory[] = array($DateRange, $qty, $LastPrice, $LastDiscount);
	}
	if (isset($PriceHistory)) {
	  echo '<br /><table cellpadding="4" class="selection">';
	  echo '<tr><th colspan="4" class="header">' . _('Pricing history for sales of') . ' ' . $StockID . ' ' . _('to') . ' ' . $DebtorNo . '</th></tr>';
	  $tableheader = '<tr>
			<th>' . _('Date Range') . '</th>
			<th>' . _('Quantity') . '</th>
			<th>' . _('Price') . '</th>
			<th>' . _('Discount') . '</th>
			</tr>';

	  $j = 0;
	  $k = 0; //row colour counter

	  foreach($PriceHistory as $ph) {
		$j--;
		if ($j < 0 ){
			$j = 11;
			echo $tableheader;
		}

		if ($k==1){
			echo '<tr class="EvenTableRows">';
			$k=0;
		} else {
			echo '<tr class="OddTableRows">';
			$k=1;
		}

			printf('<td>%s</td>
			<td class="number">%s</td>
			<td class="number">%s</td>
			<td class="number">%s%%</td>
			</tr>',
			$ph[0],
			locale_number_format($ph[1],$DecimalPlaces),
			locale_money_format($ph[2],$_SESSION['CompanyRecord']['currencydefault']),
			locale_number_format($ph[3]*100,2)
			);
	  }
	 echo '</table>';
	 }
	//end of while loop
	else {
	  echo '<p>'._('No history of sales of') . ' ' . $StockID . ' ' . _('to') . ' ' . $DebtorNo . '</p>';
	}
}
//end of displaying price history for a debtor
echo '<div class="centre">';
echo '<br /><a href="' . $rootpath . '/StockMovements.php?StockID=' . $StockID . '">' . _('Show Movements') . '</a>';
echo '<br /><a href="' . $rootpath . '/StockUsage.php?StockID=' . $StockID . '">' . _('Show Usage') . '</a>';
echo '<br /><a href="' . $rootpath . '/SelectSalesOrder.php?SelectedStockItem=' . $StockID . '">' . _('Search Outstanding Sales Orders') . '</a>';
echo '<br /><a href="' . $rootpath . '/SelectCompletedOrder.php?SelectedStockItem=' . $StockID . '">' . _('Search Completed Sales Orders') . '</a>';
if ($Its_A_KitSet_Assembly_Or_Dummy ==False){
	echo '<br /><a href="' . $rootpath . '/PO_SelectOSPurchOrder.php?SelectedStockItem=' . $StockID . '">' . _('Search Outstanding Purchase Orders') . '</a>';
}

echo '</div></form>';
include('includes/footer.inc');

?>