<?php

/**
 ***********************************************************************************************
 * Search Member
 *
 * Plugin shows a search field to search for members of the organisation
 *
 * Compatible with Admidio version 4.2
 *
 * @copyright 2004-2022 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');

// only include config file if it exists
if (is_file(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}

// set default values if there no value has been stored in the config.php
if (!isset($plg_show_names) || !is_numeric($plg_show_names) || $plg_show_names > 4) {
    $plg_show_names = 1;
}

if (!isset($plg_rolle_sql) || !is_array($plg_rolle_sql)) {
    $plg_rolle_sql = null;
}

if (!isset($plg_search_city) || !is_numeric($plg_search_city) || $plg_search_city > 1) {
    $plg_search_city = 0;
}

// Check if the role condition has been set
if (isset($plg_rolle_sql) && is_array($plg_rolle_sql) && count($plg_rolle_sql) > 0) {
    $sqlRol = 'IN (' . implode(',', $plg_rolle_sql) . ')';
} else {
    $sqlRol = 'IS NOT NULL';
}



echo '<div id="plugin_' . $pluginFolder . '" class="admidio-plugin-content">';
echo '<h3>' . $gL10n->get('PLG_SEARCH_HEADLINE') . '</h3>';
if ($gValidLogin) {
    $form = new HtmlForm('search_member_form', null, null, array('type' => 'vertical', 'method' => 'get', 'setFocus' => 'false'));

    $placeholder = $gL10n->get('PLG_SEARCH_PLACEHOLDER');
    if ($plg_search_city == 1) {
        $placeholder = $gL10n->get('PLG_SEARCH_PLACEHOLDER_INCLUDING_CITY');
    }

    $form->addInput(
        'plg_search_usr',
        '',
        '',
        array('type' => 'search', 'placeholder' => $placeholder)
    );
    $form->addSubmitButton('btn_search', 'suchen', array('icon' => 'fa-search'));
    echo $form->show();

    $getPlgSearchUser = admFuncVariableIsValid($_GET, 'plg_search_usr', 'string');

    if ($getPlgSearchUser) {
        $search_string = '%' . $getPlgSearchUser . '%';
        $sql = 'SELECT
                    usr_uuid,
                    usr_login_name,
                    first_name ,
                    last_name,
                    city
                FROM
                    (SELECT
                        DISTINCT usr_id,
                            usr_uuid,
                            usr_login_name,
                            last_name.usd_value AS last_name,
                            first_name.usd_value AS first_name,
                            city.usd_value AS city
                        FROM 
                            ' . TBL_USERS . ' AS users
                        LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                                ON last_name.usd_usr_id = usr_id
                            AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                        LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                                ON first_name.usd_usr_id = usr_id
                            AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                        LEFT JOIN ' . TBL_USER_DATA . ' AS city
                                ON city.usd_usr_id = usr_id
                            AND city.usd_usf_id = ? -- $gProfileFields->getProperty(\'CITY\', \'usf_id\')
                        LEFT JOIN ' . TBL_MEMBERS . '
                                ON mem_usr_id = usr_id
                            AND mem_begin <= ? -- DATE_NOW
                            AND mem_end    > ? -- DATE_NOW
                        INNER JOIN ' . TBL_ROLES . '
                                ON mem_rol_id = rol_id
                            AND rol_valid  = true
                        INNER JOIN ' . TBL_CATEGORIES . '
                                ON rol_cat_id = cat_id
                            AND cat_org_id = ? -- $gCurrentOrgId
                        WHERE usr_valid = true
                            AND mem_rol_id ' . $sqlRol . '
                    ) as data
                WHERE 
                    CONCAT (first_name, \' \', last_name) LIKE ?';

        $queryParams = array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $gProfileFields->getProperty('CITY', 'usf_id'),
            DATE_NOW,
            DATE_NOW,
            $gCurrentOrgId,
            $search_string
        );

        if ($plg_search_city == 1) {
            $sql .= ' OR city LIKE ?';
            $queryParams[] = $search_string;
        }
        $searchUserStatement = $gDb->queryPrepared($sql, $queryParams);

        $allSearchResults = array();
        $textSearchResults = '';

        if ($searchUserStatement->rowCount() > 0) {
            while ($row = $searchUserStatement->fetch()) {
                switch ($plg_show_names) {
                    case 1:  // first name, last name
                        $plgShowName = $row['first_name'] . ' ' . $row['last_name'];
                        break;
                    case 2:  // last name, first name
                        $plgShowName = $row['last_name'] . ', ' . $row['first_name'];
                        break;
                    case 3:  // first name
                        $plgShowName = $row['first_name'];
                        break;
                    case 4:  // Loginname
                        $plgShowName = $row['usr_login_name'];
                        break;
                    default: // first name, last name
                        $plgShowName = $row['first_name'] . ' ' . $row['last_name'];
                }

                $resultString = '<strong><a target="' . $plg_link_target . '" title="' . $gL10n->get('SYS_SHOW_PROFILE') . '"href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $row['usr_uuid'])) . '">' . $plgShowName . '</a></strong>';
                if ($plg_search_city == 1) {
                    $resultString .= ' (' . $row['city'] . ')';
                }
                $allSearchResults[] = $resultString;
            }
            $textSearchResults = implode('<br />', $allSearchResults);
            echo $textSearchResults;
        } else {
            echo $gL10n->get('PLG_SEARCH_NO_RESULTS');
        }
    }
    echo '</div>';
} else {
    echo '<div>' . $gL10n->get('PLG_SEARCH_NOT_LOGGED_IN') . '</div>';
}
