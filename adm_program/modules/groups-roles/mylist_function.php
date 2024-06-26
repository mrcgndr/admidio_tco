<?php
/**
 ***********************************************************************************************
 * Various functions for mylist module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * list_uuid : UUID of the list configuration that should be edited
 * mode      : save           - Save list configuration
 *             save_temporary - Save temporary list configuration and show list
 *             delete         - Delete list configuration
 * name      : (optional) Name of the list that should be used to save list
 ***********************************************************************************************
 */
try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getListUuid = admFuncVariableIsValid($_GET, 'list_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('save', 'save_temporary', 'delete')));
    $getName = admFuncVariableIsValid($_GET, 'name', 'string');

    $_SESSION['mylist_request'] = $_POST;

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('groups_roles_enable_module')
        || ($gSettingsManager->getInt('groups_roles_edit_lists') === 2 && !$gCurrentUser->checkRolesRight('rol_edit_user')) // users with the right to edit all profiles
        || ($gSettingsManager->getInt('groups_roles_edit_lists') === 3 && !$gCurrentUser->isAdministrator())) {
        throw new AdmException('SYS_MODULE_DISABLED');
    }

    // At least one field should be assigned
    if (!isset($_POST['column1']) || strlen($_POST['column1']) === 0) {
        throw new AdmException('SYS_FIELD_EMPTY', array('1. ' . $gL10n->get('SYS_COLUMN')));
    }

    // role must be filled when displaying
    if ($getMode === 'save_temporary'
        && (!isset($_POST['sel_roles']) || !is_array($_POST['sel_roles']))) {
        throw new AdmException('SYS_FIELD_EMPTY', array('SYS_ROLE'));
    }

    if (!isset($_POST['sel_relation_types'])) {
        $_POST['sel_relation_types'] = array();
    }

    $list = new ListConfiguration($gDb);
    if ($getListUuid !== '') {
        $list->readDataByUuid($getListUuid);
    }

    // check if user has the rights to edit this list
    if ($getMode !== 'save_temporary') {
        // global lists can only be edited by administrator
        if ($list->getValue('lst_global') == 1 && !$gCurrentUser->isAdministrator()) {
            throw new AdmException('SYS_NO_RIGHTS');
        } elseif ((int)$list->getValue('lst_usr_id') !== $gCurrentUserId
            && $list->getValue('lst_global') == 0 && $list->getValue('lst_id') > 0) {
            throw new AdmException('SYS_NO_RIGHTS');
        }
    }

    // save list
    if (in_array($getMode, array('save', 'save_temporary'), true)) {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        $globalConfiguration = admFuncVariableIsValid($_POST, 'cbx_global_configuration', 'bool', array('defaultValue' => false));

        // go through all existing columns
        for ($columnNumber = 1; isset($_POST['column' . $columnNumber]); ++$columnNumber) {
            if (strlen($_POST['column' . $columnNumber]) > 0) {
                // add column to list and check if it's a profile field or another column
                if (StringUtils::strStartsWith($_POST['column' . $columnNumber], 'usr_') || StringUtils::strStartsWith($_POST['column' . $columnNumber], 'mem_')) {
                    $list->addColumn($_POST['column' . $columnNumber], $columnNumber, $_POST['sort' . $columnNumber], $_POST['condition' . $columnNumber]);
                } else {
                    $list->addColumn($gProfileFields->getProperty($_POST['column' . $columnNumber], 'usf_id'), $columnNumber, $_POST['sort' . $columnNumber], $_POST['condition' . $columnNumber]);
                }
            } else {
                $list->deleteColumn($columnNumber, true);
            }
        }

        if ($getName !== '') {
            $list->setValue('lst_name', $getName);
        }

        // set list global only in save mode
        if ($getMode === 'save' && $gCurrentUser->isAdministrator()) {
            $list->setValue('lst_global', $globalConfiguration);
        } else {
            $list->setValue('lst_global', 0);
        }

        $list->save();

        $listUuid = $list->getValue('lst_uuid');

        if ($getMode === 'save') {
            // save new id to session so that we can restore the configuration with new list name
            $_SESSION['mylist_request']['sel_select_configuration'] = $listUuid;

            // go back to mylist configuration
            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist.php', array('list_uuid' => $listUuid)));
            // => EXIT
        }

        // redirect to general list page
        admRedirect(SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php',
            array(
                'list_uuid' => $listUuid,
                'mode' => 'html',
                'role_list' => implode(',', $_POST['sel_roles']),
                'relation_type_list' => implode(',', $_POST['sel_relation_types'])
            )
        ));
        // => EXIT
    } elseif ($getMode === 'delete') {
        // delete list configuration
        $list->delete();
        unset($_SESSION['mylist_request']);

        // go back to list configuration
        admRedirect(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist.php');
        // => EXIT
    }
} catch (AdmException|Exception|\Smarty\Exception $e) {
    $gMessage->show($e->getMessage());
}
