<?php

/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin Search Member
 *
 * Rename this file to config.php if you want to change some of the preferences below. The plugin
 * will only read the parameters from config.php and not the example file.
 *
 * @copyright 2004-2022 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// How should the name of the search result be displayed?
// 1 - (Default) First name Last name (John Doe)
// 2 - Last name, First name (Doe, John)
// 3 - First name (John)
// 4 - Login name (John)
$plg_show_names = 1;

// Here you can define which roles users must have who should be shown in the search results. Within the
// default setting all users will be shown. Fill the array with ids of the roles to only
// allow members of these roles to be shown in the search results.
// Example: $plg_rolle_sql = array(2, 4, 10);
$plg_rolle_sql = null;

// Here you can define if the plugin should also search for members who live in a certain city.
// 0 - (Default) Only search first name and last name
// 1 - Also search for members in a city
$plg_search_city = 0;
