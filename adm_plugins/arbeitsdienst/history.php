<?php
/**
 ***********************************************************************************************
 * Anzeigen einer Historie von Zahlungen fuer das Admidio-Plugin Arbeitsdienst
 *
 * @copyright 2018-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Hinweis: Funktion vom Plugin Mitgliederbeitrag übernommen
 *
 * Parameters:
 *
 * mode:				Output(html, print, csv-ms, csv-oo, pdf, pdfl)
 * filter_date_from: 	is set to actual date,
 *             			if no date information is delivered
 * filter_date_to: 		is set to 31.12.9999,
 *             			if no date information is delivered
 * filter_last_name:	Lastname for filter
 * filter_first_name:	Firstname for filter
 * full_screen:     	false - (Default) show sidebar, head and page bottom of html page
 *                  	true  - Only show the list without any other html unnecessary elements
 ***********************************************************************************************
 */
require_once (__DIR__ . '/../../adm_program/system/common.php');
require_once (__DIR__ . '/common_function.php');
require_once (__DIR__ . '/classes/configtable.php');

$user = new User($gDb, $gProfileFields);


// only authorized user are allowed to start this module
if (! isUserAuthorized($_SESSION['pMembershipFee']['script_name'])) 
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$pPreferences = new ConfigTablePAD();
$pPreferences->read();

// calculate default date from which the contribution history should be shown
$filterDateFrom = DateTime::createFromFormat('Y-m-d', DATE_NOW);
$filterDateFrom->modify('-'.$gSettingsManager->getString('members_days_field_history').' day');

// Initialize and check the parameters
$getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
$getDateTo = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));
$getLastName = admFuncVariableIsValid($_GET, 'filter_last_name', 'string');
$getFirstName = admFuncVariableIsValid($_GET, 'filter_first_name', 'string');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html',
                                                                 'validValues' => array(
                                                                 'csv-ms',
                                                                 'csv-oo',
                                                                 'html',
                                                                 'print',
                                                                 'pdf',
                                                                 'pdfl')));
$getExportAndFilter = admFuncVariableIsValid($_GET, 'export_and_filter', 'bool', array('defaultValue' => false));

$title = $gL10n->get('PLG_ARBEITSDIENST_CONTRIBUTION_HISTORY');
$headline = $gL10n->get('PLG_ARBEITSDIENST_CONTRIBUTION_HISTORY');
$filename = $gL10n->get('PLG_ARBEITSDIENST_CONTRIBUTION_HISTORY');


// filter_date_from and filter_date_to can have different formats
// now we try to get a default format for intern use and html output
$objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom);
if($objDateFrom === false)
{
    // check if date has system format
    $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateFrom);
    if($objDateFrom === false)
    {
        $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
    }
}

$objDateTo = DateTime::createFromFormat('Y-m-d', $getDateTo);
if($objDateTo === false)
{
    // check if date has system format
    $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateTo);
    if($objDateTo === false)
    {
        $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
    }
}

// DateTo should be greater than DateFrom
if($objDateFrom > $objDateTo)
{
    $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    // => EXIT
}

$dateFromIntern = $objDateFrom->format('Y-m-d');
$dateFromHtml   = $objDateFrom->format($gSettingsManager->getString('system_date'));
$dateToIntern   = $objDateTo->format('Y-m-d');
$dateToHtml     = $objDateTo->format($gSettingsManager->getString('system_date'));

// initialize some special mode parameters
$separator = '';
$valueQuotes = '';
$charset = '';
$classTable = '';
$orientation = '';
$csvStr = ''; // CSV file as string

switch ($getMode) 
{
    case 'csv-ms':
        $separator = ';'; // Microsoft Excel 2007 or new needs a semicolon
        $valueQuotes = '"'; // all values should be set with quotes
        $getMode = 'csv';
        $charset = 'iso-8859-1';
        break;
    case 'csv-oo':
        $separator = ','; // a CSV file should have a comma
        $valueQuotes = '"'; // all values should be set with quotes
        $getMode = 'csv';
        $charset = 'utf-8';
        break;
    case 'pdf':
        $classTable = 'table';
        $orientation = 'P';
        $getMode = 'pdf';
        break;
    case 'pdfl':
        $classTable = 'table';
        $orientation = 'L';
        $getMode = 'pdf';
        break;
    case 'html':
        $classTable = 'table table-condensed';
        break;
    case 'print':
        $classTable = 'table table-condensed table-striped';
        break;
    default:
        break;
}

$sqlConditions = 'WHERE TRUE ';
if ($getLastName !== '') {
    $sqlConditions .= 'AND last_name.usd_value = \'' . $getLastName . '\' ';
}
if ($getFirstName !== '') {
    $sqlConditions .= 'AND first_name.usd_value = \'' . $getFirstName . '\' ';
}

// create select statement with all necessary data
$sql = 'SELECT usl_id, usl_usr_id, last_name.usd_value AS last_name, first_name.usd_value AS first_name, usl_usf_id, usl_value_new, usl_timestamp_create, usr_uuid
          FROM ' . TBL_USER_LOG . '
    INNER JOIN ' . TBL_USER_DATA . ' AS last_name
            ON last_name.usd_usr_id = usl_usr_id
           AND last_name.usd_usf_id = ' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . '
    INNER JOIN ' . TBL_USER_DATA . ' AS first_name
            ON first_name.usd_usr_id = usl_usr_id
           AND first_name.usd_usf_id = ' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . '
    Inner JOIN ' . TBL_USERS . ' as usr
            ON usr.usr_id = usl_usr_id
            ' . $sqlConditions . '
    ORDER BY usl_id ASC';

$fieldHistoryStatement = $gDb->query($sql);

if ($fieldHistoryStatement->rowCount() === 0) {
    // message is shown, so delete this page from navigation stack
    $gNavigation->deleteLastUrl();

    $gMessage->show($gL10n->get('MEM_NO_CHANGES'));
    // => EXIT
}

if ($getMode !== 'csv') 
{
    $datatable = false;
    $hoverRows = false;

    if ($getMode === 'print') {
        // create html page object without the custom theme files
        $page = new HtmlPage('plg-arbeitsdienst-history-print', $headline);
        //$page->hideThemeHtml();
        //$page->hideMenu();
        $page->setPrintMode();
        $page->setTitle($title);
        $page->setHeadline($headline);
        $table = new HtmlTable('history_table', $page, $hoverRows, $datatable, $classTable);
    } elseif ($getMode === 'pdf') {
        require_once (ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/tcpdf/tcpdf.php');
        $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admidio');
        $pdf->SetTitle($headline);

        // remove default header/footer
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);

        // set header and footer fonts
        $pdf->setHeaderFont(array(
            PDF_FONT_NAME_MAIN,
            '',
            PDF_FONT_SIZE_MAIN
        ));
        $pdf->setFooterFont(array(
            PDF_FONT_NAME_DATA,
            '',
            PDF_FONT_SIZE_DATA
        ));

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->SetMargins(10, 20, 10);
        $pdf->setHeaderMargin(10);
        $pdf->setFooterMargin(0);

        // headline for PDF
        $pdf->setHeaderData('', '', $headline, '');

        // set font
        $pdf->SetFont('times', '', 10);

        // add a page
        $pdf->AddPage();

        // Create table object for display
        $table = new HtmlTable('history_table', null, $hoverRows, $datatable, $classTable);
        $table->addAttribute('border', '1');
        $table->addTableHeader();
        $table->addRow();
    } 
    elseif ($getMode === 'html') 
    {
        $datatable = true;
        $hoverRows = true;
        
        $gNavigation->addUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/arbeitsdienst.php', array('show_option' => 'history')));
        $gNavigation->addUrl(CURRENT_URL);

        // create html page object
        $page = new HtmlPage('plg-arbeitsdienst-history-html', $headline);
        
        $page->setTitle($title);
        
        $page->addJavascript('
            $("#menu_item_lists_print_view").click(function() {
                window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/history.php', array('filter_date_from'  => $getDateFrom,
                                                                                                                            'filter_date_to'    => $getDateTo,
                                                                                                                            'filter_last_name'  => $getLastName,
                                                                                                                            'filter_first_name' => $getFirstName,
                                                                                                                            'export_and_filter' => $getExportAndFilter,
                                                                                                                            'mode'              => 'print')) . '", "_blank");
            });
            $("#export_and_filter").change(function() {
                $("#navbar_checkbox_form").submit();
            });
            $("#filter_date_from").change(function() {
                $("#navbar_filter_form").submit();
            });
            $("#filter_date_to").change(function() {
                $("#navbar_filter_form").submit();
            });
             $("#filter_last_name").change(function() {
                $("#navbar_filter_form").submit();
            });
            $("#filter_first_name").change(function() {
                $("#navbar_filter_form").submit();
            });
        ', true); 
        
                if ($getExportAndFilter)
                {
                    // links to print and exports
                    $page->addPageFunctionsMenuItem('menu_item_lists_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');
                    
                    // dropdown menu item with all export possibilities
                    $page->addPageFunctionsMenuItem('menu_item_lists_export', $gL10n->get('SYS_EXPORT_TO'), '#', 'fa-file-download');
                    $page->addPageFunctionsMenuItem('menu_item_lists_csv_ms', $gL10n->get('SYS_MICROSOFT_EXCEL'),
                        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/history.php', array(
                            'filter_date_from'  => $getDateFrom,
                            'filter_date_to'    => $getDateTo,
                            'filter_last_name'  => $getLastName,
                            'filter_first_name' => $getFirstName,
                            'export_and_filter' => $getExportAndFilter,
                            'mode'              => 'csv-ms')),
                        'fa-file-excel', 'menu_item_lists_export');
                    $page->addPageFunctionsMenuItem('menu_item_lists_pdf', $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_PORTRAIT').')',
                        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/history.php', array(
                            'filter_date_from'  => $getDateFrom,
                            'filter_date_to'    => $getDateTo,
                            'filter_last_name'  => $getLastName,
                            'filter_first_name' => $getFirstName,
                            'export_and_filter' => $getExportAndFilter,
                            'mode'              => 'pdf')),
                        'fa-file-pdf', 'menu_item_lists_export');
                    $page->addPageFunctionsMenuItem('menu_item_lists_pdfl', $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_LANDSCAPE').')',
                        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/history.php', array(
                            'filter_date_from'  => $getDateFrom,
                            'filter_date_to'    => $getDateTo,
                            'filter_last_name'  => $getLastName,
                            'filter_first_name' => $getFirstName,
                            'export_and_filter' => $getExportAndFilter,
                            'mode'              => 'pdfl')),
                        'fa-file-pdf', 'menu_item_lists_export');
                    $page->addPageFunctionsMenuItem('menu_item_lists_csv', $gL10n->get('SYS_CSV').' ('.$gL10n->get('SYS_UTF8').')',
                        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/history.php', array(
                            'filter_date_from'  => $getDateFrom,
                            'filter_date_to'    => $getDateTo,
                            'filter_last_name'  => $getLastName,
                            'filter_first_name' => $getFirstName,
                            'export_and_filter' => $getExportAndFilter,
                            'mode'              => 'csv-oo')),
                        'fa-file-csv', 'menu_item_lists_export');
                }
        
                $form = new HtmlForm('navbar_checkbox_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/history.php'),  $page, array('type' => 'navbar', 'setFocus' => false));
                $form->addCheckbox('export_and_filter', $gL10n->get('PLG_MITGLIEDSBEITRAG_EXPORT_AND_FILTER'), $getExportAndFilter);
                
                $page->addHtml($form->show());
                
                if ($getExportAndFilter)
                {
                    $form = new HtmlForm('navbar_filter_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/history.php'),  $page, array('type' => 'navbar', 'setFocus' => false));
                    $form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
                    $form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
                    //$form->addInput('filter_last_name', $gL10n->get('SYS_LASTNAME'), $getLastName) ;
                    //$form->addInput('filter_first_name', $gL10n->get('SYS_FIRSTNAME'), $getFirstName);
                    $form->addInput('export_and_filter', '', $getExportAndFilter, array('property' => HtmlForm::FIELD_HIDDEN));
                    
                    $page->addHtml($form->show());
                }
                
                $table = new HtmlTable('history_table', $page, $hoverRows, $datatable, $classTable);
                $table->setDatatablesRowsPerPage($gSettingsManager->getString('groups_roles_members_per_page'));
    }
    else
    {
        $table = new HtmlTable('history_table', $page, $hoverRows, $datatable, $classTable);
    }
        
}

// header definitions
$columnHeading = array();
$columnHeading[] = $gL10n->get('SYS_NAME');
$columnHeading[] = $gL10n->get('PLG_ARBEITSDIENST_PAID_ON');
$columnHeading[] = $gL10n->get('PLG_ARBEITSDIENST_DUEDATE');
$columnHeading[] = $gL10n->get('PLG_ARBEITSDIENST_FEE');
$columnHeading[] = $gL10n->get('PLG_ARBEITSDIENST_CONTRIBUTORY');

foreach ($columnHeading as $headerData) 
{
    if ($getMode === 'csv') 
    {
        $csvStr .= $separator . $valueQuotes . $headerData . $valueQuotes;
    }
    elseif ($getMode === 'pdf') 
    {
        $table->addColumn($headerData, array('style' => 'text-align: center;font-size:14;background-color:#C7C7C7;'), 'th');
    }
}

if ($getMode === 'csv') 
{
    $csvStr = substr($csvStr, strlen($separator)) . "\n";
} 
elseif ($getMode === 'html') 
{
    $table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left'));
    $table->setDatatablesOrderColumns(array(array(5, 'desc')));
    $table->addRowHeadingByArray($columnHeading);
} 
elseif ($getMode === 'print') 
{
    $table->setColumnAlignByArray(array('center', 'center', 'center', 'center', 'center'));
    $table->setDatatablesOrderColumns(array(array(5, 'desc')));
    $table->addRowHeadingByArray($columnHeading);
} 
else 
{
    $table->addTableBody();
}

$columnValues = array();
$presetColumnValues = array();

if ($getMode === 'csv') {
    $presetColumnValues[$gProfileFields->getProperty('WORKPAID', 'usf_id')] = '';
    $presetColumnValues[$gProfileFields->getProperty('WORKDUEDATE', 'usf_id')] = '';
    $presetColumnValues[$gProfileFields->getProperty('WORKFEE', 'usf_id')] = '';
    $presetColumnValues[$gProfileFields->getProperty('WORKREFERENCE', 'usf_id')] = '';
} else {
    $presetColumnValues[$gProfileFields->getProperty('WORKPAID', 'usf_id')] = '&nbsp;';
    $presetColumnValues[$gProfileFields->getProperty('WORKDUEDATE', 'usf_id')] = '&nbsp;';
    $presetColumnValues[$gProfileFields->getProperty('WORKFEE', 'usf_id')] = '&nbsp;';
    $presetColumnValues[$gProfileFields->getProperty('WORKREFERENCE', 'usf_id')] = '&nbsp;';
}

while ($row = $fieldHistoryStatement->fetch()) {
    if (! array_key_exists($row['usl_usf_id'], $presetColumnValues)) {
        continue;
    }

    if (! isset($columnValues[$row['usl_usr_id']])) {
        $columnValues[$row['usl_usr_id']] = $presetColumnValues;
    }

    $columnValues[$row['usl_usr_id']][$row['usl_usf_id']] = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['usl_usf_id'], 'usf_name_intern'), $row['usl_value_new']);

    if ($row['usl_usf_id'] == $gProfileFields->getProperty('WORKPAID', 'usf_id') 
                                                            && $row['usl_value_new'] != '' 
                                                            && $row['usl_value_new'] >= $dateFromIntern 
                                                            && $row['usl_value_new'] <= $dateToIntern) 
    {
        // add last_name and first_name to the first column
        array_unshift($columnValues[$row['usl_usr_id']], $row['last_name'] . ', ' . $row['first_name']);

        if ($getMode === 'csv') 
        {
            $csvTmp = '';
            foreach ($columnValues[$row['usl_usr_id']] as $dummy => $data) 
            {
                $csvTmp .= $separator . $valueQuotes . $data . $valueQuotes;
            }
            $csvStr .= substr($csvTmp, strlen($separator)) . "\n";
        } 
        elseif ($getMode === 'print' || $getMode === 'pdf') 
        {
            $table->setColumnAlignByArray(array('center',  'center',  'center',  'center',  'center'));
            $table->addRowByArray($columnValues[$row['usl_usr_id']], null, array('nobr' => 'true'));
        } 
        else 
        {
            $user->readDataById($row['usl_usr_id']);
            $table->addRowByArray($columnValues[$row['usl_usr_id']], null, array('style' => 'cursor: pointer',
                                                                                 'onclick' => 'window.location.href=\'' . ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php?user_uuid=' . $row['usr_uuid'] . '\''));
        }
        unset($columnValues[$row['usl_usr_id']]);
    }
}

// Settings for export file
if ($getMode === 'csv' || $getMode === 'pdf') {
    $filename .= '.' . $getMode;

    // for IE the filename must have special chars in hexadecimal
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
        $filename = urlencode($filename);
    }

    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // neccessary for IE6 to 8, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');
}


if($getMode === 'csv')							// send the CSV-File to the user
{
	header('Content-Type: text/comma-separated-values; charset='.$charset);

	if($charset === 'iso-8859-1')
	{
		echo utf8_decode($csvStr);
	}
	else
	{
		echo $csvStr;
	}
}
elseif($getMode === 'pdf')						// send the PDF-File to the User
{
	// output the HTML content
	$pdf->writeHTML($table->getHtmlTable(), true, false, true, false, '');

	//Save PDF to file
	$pdf->Output(ADMIDIO_PATH . FOLDER_DATA . '/'.$filename, 'F');

	//Redirect
	header('Content-Type: application/pdf');

	readfile(ADMIDIO_PATH . FOLDER_DATA . '/'.$filename);
	ignore_user_abort(true);
	unlink(ADMIDIO_PATH . FOLDER_DATA . '/'.$filename);
}
elseif ($getMode == 'html' && $getExportAndFilter)
{
    $page->addHtml('<div style="width:100%; height: 500px; overflow:auto; border:20px;">');
    $page->addHtml($table->show(false));
    $page->addHtml('</div><br/>');
    
    $page->show();
}
elseif (($getMode == 'html' && !$getExportAndFilter) || $getMode == 'print')
{
    $page->addHtml($table->show(false));
    
    $page->show();
}