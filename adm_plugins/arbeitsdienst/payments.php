<?php

/***********************************************************************************************
 * Verarbeiten der Menueeinstellungen des Admidio-Plugins Arbeitsstunden / Zahlungsübersicht
 *
 * @copyright 2018-2023 WSVBS,       The Admidio Team
 * @see       https://wsv-bs.de,     https://www.admidio.org/
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *  Hinweis: Funktion vom Plugin Mitgliederbeitrag übernommen
 * 
 * Parameters:
 *
 * mode             : html   - Standardmodus zun Anzeigen einer html-Liste aller Benutzer mit Beitraegen
 *                    assign - Setzen eines Bezahlt-Datums
 * usr_id           : Id des Benutzers, fuer den das Bezahlt-Datum gesetzt/geloescht wird
 * datum_neu        : das neue Bezahlt-Datum
 * mem_show_choice  : 0 - (Default) Alle Benutzer anzeigen
 *                    1 - Nur Benutzer anzeigen, bei denen ein Bezahlt-Datum vorhanden ist
 *                    2 - Nur Benutzer anzeigen, bei denen kein Bezahlt-Datum vorhanden ist
 * full_screen      : 0 - Normalbildschirm
 *                    1 - Vollbildschirm
 ***********************************************************************************************
 */
require_once (__DIR__ . '/../../adm_program/system/common.php');
require_once (__DIR__ . '/common_function.php');
require_once (__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html','validValues' => array('html','assign')));
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', array('defaultValue' => 0,'directOutput' => true));
$getDatumNeu = admFuncVariableIsValid($_GET, 'datum_neu', 'date');
$getMembersShow = admFuncVariableIsValid($_GET, 'mem_show_choice', 'numeric', array('defaultValue' => 0));
$getFullScreen = admFuncVariableIsValid($_GET, 'full_screen', 'numeric');
// $getdatefilteractual = admFuncVariableIsValid($_GET, 'datefilteractual', 'int');

// only authorized user are allowed to start this module
if (! isUserAuthorized($_SESSION['pMembershipFee']['script_name'])) {
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}


$getdatefilteractual = date('Y');


$pPreferences = new ConfigTablePAD();
$pPreferences->read(); // Konfigurationsdaten auslesen

$user = new User($gDb, $gProfileFields);

// set headline of the script
$headline = $gL10n->get('PLG_ARBEITSDIENST_CONTRIBUTION_PAYMENTS');

// add current url to navigation stack if last url was not the same page
if (strpos($gNavigation->getUrl(), 'payments.php') === false) {
    $gNavigation->addUrl(CURRENT_URL, $headline);
}

if ($getMode == 'assign') {
    $ret_text = 'ERROR';
    
    $userArray = array();
    if ($getUserId != 0) // Bezahlt-Datum nur fuer einen einzigen User aendern
    {
        $userArray[0] = $getUserId;
    } else // Alle aendern wurde gewaehlt
    {
        $userArray = $_SESSION['pMembershipFee']['payments_user'];
    }
    
    try {
        foreach ($userArray as $dummy => $data) {
            $user = new User($gDb, $gProfileFields, $data);
            
            // zuerst mal sehen, ob bei diesem user bereits ein BEZAHLT-Datum vorhanden ist
            if (strlen($user->getValue('WORKPAID')) === 0) {
                // er hat noch kein BEZAHLT-Datum, deshalb ein neues eintragen
                $user->setValue('WORKPAID', $getDatumNeu);
                
                // wenn Lastschrifttyp noch nicht gesetzt ist: als Folgelastschrift kennzeichnen
                // BEZAHLT bedeutet, es hat bereits eine Zahlung stattgefunden
                // die naechste Zahlung kann nur eine Folgelastschrift sein
                // Lastschrifttyp darf aber nur geaendert werden, wenn der Einzug per SEPA stattfand, also ein Faelligkeitsdatum vorhanden ist
                if (strlen($user->getValue('WORKSEQUENCETYPE')) === 0 && strlen($user->getValue('WORKDUEDATE')) !== 0) {
                    $user->setValue('WORKSEQUENCETYPE', 'RCUR');
                }
                
                // falls Daten von einer Mandatsaenderung vorhanden sind, diese loeschen
                if (strlen($user->getValue('ORIG_MANDATEID' . ORG_ID)) !== 0) {
                    $user->setValue('ORIG_MANDATEID' . ORG_ID, '');
                }
                if (strlen($user->getValue('ORIG_IBAN')) !== 0) {
                    $user->setValue('ORIG_IBAN', '');
                }
                if (strlen($user->getValue('ORIG_DEBTOR_AGENT')) !== 0) {
                    $user->setValue('ORIG_DEBTOR_AGENT', '');
                }
                
                // das Faelligkeitsdatum loeschen (wird nicht mehr gebraucht, da ja bezahlt)
                if (strlen($user->getValue('WORKDUEDATE')) !== 0) {
                    $user->setValue('WORKDUEDATE', '');
                }
            } else {
                // er hat bereits ein BEZAHLT-Datum, deshalb das vorhandene loeschen
                $user->setValue('WORKPAID', '');
            }
            $user->save();
            $ret_text = 'success';
        }
    } catch (AdmException $e) {
        $e->showText();
    }
    echo $ret_text;
} 

else {
    $userArray = array();
    $membersList = array();

    $membersListRols = 0;

    $membersListFields = $pPreferences->config['columnconfig']['payments_fields'];
    
    $membersListSqlCondition = 'AND mem_usr_id IN (SELECT DISTINCT usr_id
        FROM ' . TBL_USERS . '
        LEFT JOIN ' . TBL_USER_DATA . ' AS paid
          ON paid.usd_usr_id = usr_id
         AND paid.usd_usf_id = ' . $gProfileFields->getProperty('WORKPAID', 'usf_id') . '
        LEFT JOIN ' . TBL_USER_DATA . ' AS fee
          ON fee.usd_usr_id = usr_id
         AND fee.usd_usf_id = ' . $gProfileFields->getProperty('WORKFEE', 'usf_id') . '
             
        LEFT JOIN ' . TBL_MEMBERS . ' AS mem
          ON mem.mem_usr_id  = usr_id
             
       WHERE fee.usd_value IS NOT NULL ';

    if ($getMembersShow == 1) // Nur Benutzer anzeigen, bei denen ein Bezahlt-Datum vorhanden ist
    {
        $membersListSqlCondition .= ' AND paid.usd_value IS NOT NULL ) ';
    } elseif ($getMembersShow == 2) // Nur Benutzer anzeigen, bei denen kein Bezahlt-Datum vorhanden ist
    {
        $membersListSqlCondition .= ' AND paid.usd_value IS NULL ) ';
    } else // Alle Benutzer anzeigen
    {
        $membersListSqlCondition .= ' ) ';
    }

    $membersList = list_members($getdatefilteractual, $membersListFields, $membersListRols, $membersListSqlCondition);

    // create html page object
    $page = new HtmlPage($headline);

    if ($getFullScreen == true) {
        $page->hideThemeHtml();
    }

    $javascriptCode = '
            // Anzeige abhaengig vom gewaehlten Filter
            $("#mem_show").change(function () {
                if($(this).val().length > 0) {
                    window.location.replace("'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/payments.php').'?mem_show_choice="+$(this).val());
                }
            });
                        
            // if checkbox in header is clicked then change all data
            $("input[type=checkbox].change_checkbox").click(function(){
                var datum = $("#datum").val();
                $.post("'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/payments.php', array('mode' => 'assign')) .'&datum_neu=" + datum,
                    function(data){
                        // check if error occurs
                        if(data == "success") {
                        var mem_show = $("#mem_show").val();
                            window.location.replace("'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/payments.php').'?mem_show_choice=" + mem_show);
                        }
                        else {
                            alert(data);
                            return false;
                        }
                        return true;
                    }
                );
            });
                                
            // if checkbox of user is clicked then change data
            $("input[type=checkbox].memlist_checkbox").click(function(e){
                e.stopPropagation();
                var checkbox = $(this);
                var row_id = $(this).parent().parent().attr("id");
                var pos = row_id.search("_");
                var userid = row_id.substring(pos+1);
                var datum = $("#datum").val();
                var member_checked = $("input[type=checkbox]#member_"+userid).prop("checked");
                                
                // change data in database
                $.post("'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/payments.php', array('mode' => 'assign')) .'&datum_neu=" + datum + "&usr_id=" + userid,
                    function(data){
                        // check if error occurs
                        if(data == "success") {
                            if(member_checked){
                                $("input[type=checkbox]#member_"+userid).prop("checked", true);
                                $("#bezahlt_"+userid).text(datum);
                    
                                var lastschrifttyp  = $("#lastschrifttyp_"+userid).text();
                                lastschrifttyp      = lastschrifttyp.trim();
                    
                                var duedate         = $("#duedate_"+userid).text();
                                duedate             = duedate.trim();
                                $("#duedate_"+userid).text("");
                    
    							var orig_mandateid  = $("#orig_mandateid_"+userid).text();
                                orig_mandateid      = orig_mandateid.trim();
                                $("#orig_mandateid_"+userid).text("");
                    
    							var orig_iban       = $("#orig_iban_"+userid).text();
                                orig_iban           = orig_iban.trim();
                                $("#orig_iban_"+userid).text("");
                    
    							var orig_debtor_agent = $("#orig_debtor_agent_"+userid).text();
                                orig_debtor_agent     = orig_debtor_agent.trim();
                                $("#orig_debtor_agent_"+userid).text("");
                    
                                if(lastschrifttyp.length == 0 && duedate.length != 0){
                                    $("#lastschrifttyp_"+userid).text("RCUR");
                                }
                            }
                            else {
                                $("input[type=checkbox]#member_"+userid).prop("checked", false);
                                $("#bezahlt_"+userid).text("");
                            }
                        }
                        else {
                            alert(data);
                            return false;
                        }
                        return true;
                    }
                );
            });
        ';

    $page->addJavascript($javascriptCode, true);
    
    $form = new HtmlForm('payments_filter_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
    
    $datumtemp = \DateTime::createFromFormat('Y-m-d', DATE_NOW);
    $datum = $datumtemp->format($gSettingsManager->getString('system_date'));
    $form->addInput('datum', $gL10n->get('PLG_ARBEITSDIENST_DATE_PAID'), $datum, array('type' => 'date', 'helpTextIdLabel' => 'PLG_ARBEITSDIENST_DATE_PAID_DESC'));
    
    $selectBoxEntries = array('0' => $gL10n->get('SYS_SHOW_ALL'), '1' => $gL10n->get('PLG_ARBEITSDIENST_WITH_PAID'), '2' => $gL10n->get('PLG_ARBEITSDIENST_WITHOUT_PAID'));
    $form->addSelectBox('mem_show', $gL10n->get('PLG_ARBEITSDIENST_FILTER'), $selectBoxEntries, array('defaultValue' => $getMembersShow, 'helpTextIdLabel' => 'PLG_ARBEITSDIENST_FILTER_DESC', 'showContextDependentFirstEntry' => false));
    
    $page->addHtml($form->show(false));

 
   

    // create table object
    $table = new HtmlTable('tbl_assign_role_membership', $page, true, true, 'table table-condensed');
    $table->setMessageIfNoRowsFound('SYS_NO_ENTRIES_FOUND');

    $columnAlign = array('center');
    $columnValues = array('<input type="checkbox" id="change" name="change" class="change_checkbox admidio-icon-help" title="' . $gL10n->get('PLG_MITGLIEDSBEITRAG_DATE_PAID_CHANGE_ALL_DESC') . '"/>');

    // headlines for columns
    foreach ($membersList as $member => $memberData) {
        foreach ($memberData as $usfId => $dummy) {
            if (! is_int($usfId)) {
                continue;
            }

            // Find name of the field
            $columnHeader = $gProfileFields->getPropertyById($usfId, 'usf_name');

            if ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX' || $gProfileFields->getPropertyById($usfId, 'usf_name_intern') === 'GENDER') {
                $columnAlign[] = 'center';
            } elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'NUMBER' || $gProfileFields->getPropertyById($usfId, 'usf_type') === 'DECIMAL') {
                $columnAlign[] = 'right';
            } else {
                $columnAlign[] = 'left';
            }
            $columnValues[] = $columnHeader;
        } // End-Foreach
        break; // Abbruch nach dem ersten Mitglied, da nur die usfIds eines Mitglieds benoetigt werden um die headlines zu erzeugen
    }

    $table->setColumnAlignByArray($columnAlign);
    $table->addRowHeadingByArray($columnValues);
    $table->disableDatatablesColumnsSort(array(1));

    // user data
    foreach ($membersList as $member => $memberData) {
        if (strlen($memberData[$gProfileFields->getProperty('WORKPAID', 'usf_id')]) > 0) {
            $content = '<input type="checkbox" id="member_' . $member . '" name="member_' . $member . '" checked="checked" class="memlist_checkbox memlist_member" /><b id="loadindicator_member_' . $member . '"></b>';
        } else {
            $content = '<input type="checkbox" id="member_' . $member . '" name="member_' . $member . '" class="memlist_checkbox memlist_member" /><b id="loadindicator_member_' . $member . '"></b>';
        }

        $columnValues = array(
            $content
        );

        $user->readDataById($member);

        foreach ($memberData as $usfId => $data) {
            if (! is_int($usfId)) {
                continue;
            }

            // fill content with data of database
            $content = $data;

            /**
             * **************************************************************
             */
            // in some cases the content must have a special output format
            /**
             * **************************************************************
             */
 
            if ($usfId === (int) $gProfileFields->getProperty('COUNTRY', 'usf_id')) {
                $content = $gL10n->getCountryByCode($data);
            } elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX') {
                if ($content != 1) {
                    $content = 0;
                }
            } elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'DATE') {
                if (strlen($data) > 0) {
                    // date must be formated
                    $date = DateTime::createFromFormat('Y-m-d', $data);
                    $content = $date->format($gSettingsManager->getString('system_date'));
                }
            }

            if ($usfId == $gProfileFields->getProperty('WORKPAID', 'usf_id')) {
                $content = '<div class="bezahlt_' . $member . '" id="bezahlt_' . $member . '">' . $content . '</div>';
            } elseif ($usfId == $gProfileFields->getProperty('WORKDUEDATE', 'usf_id')) {
                $content = '<div class="duedate_' . $member . '" id="duedate_' . $member . '">' . $content . '</div>';
            } elseif ($usfId == $gProfileFields->getProperty('WORKSEQUENCETYPE', 'usf_id')) {
                $content = '<div class="lastschrifttyp_' . $member . '" id="lastschrifttyp_' . $member . '">' . $data . '</div>';
            } elseif ($usfId == $gProfileFields->getProperty('ORIG_MANDATEID' . ORG_ID, 'usf_id')) {
                $content = '<div class="orig_mandateid_' . $member . '" id="orig_mandateid_' . $member . '">' . $data . '</div>';
            } elseif ($usfId == $gProfileFields->getProperty('ORIG_IBAN', 'usf_id')) {
                $content = '<div class="orig_iban_' . $member . '" id="orig_iban_' . $member . '">' . $data . '</div>';
            } elseif ($usfId == $gProfileFields->getProperty('ORIG_DEBTOR_AGENT', 'usf_id')) {
                $content = '<div class="orig_debtor_agent_' . $member . '" id="orig_debtor_agent_' . $member . '">' . $data . '</div>';
            }
            // firstname and lastname get a link to the profile
            
            if (($usfId === (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id') || $usfId === (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id'))) {
                $htmlValue = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $member);
                $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$htmlValue.'</a>';
            } elseif (($usfId === (int) $gProfileFields->getProperty('EMAIL', 'usf_id') || $usfId === (int) $gProfileFields->getProperty('DEBTOR_EMAIL', 'usf_id'))) {
                $columnValues[] = getEmailLink($data, $member);
            } else {
                // checkbox must set a sorting value
                if ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX') {
                    $columnValues[] = array(
                        'value' => $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $member),
                        'order' => $content
                    );
                } else {
                    $columnValues[] = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $member);
                }
            }
        }

        $table->addRowByArray($columnValues, 'userid_' . $member, array('nobr' => 'true'));

        $userArray[] = $member;
    } // End-foreach User
    
    
    $_SESSION['pMembershipFee']['payments_user'] = $userArray;

    $page->addHtml($table->show(false));
    $page->addHtml('<p>' . $gL10n->get('SYS_CHECKBOX_AUTOSAVE') . '</p>');

    $page->show();
}
