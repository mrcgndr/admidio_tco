====== Search Member ======

Version 1.0.0

==== Description ====
This plugin can be used in the overview page. It provides a search field to search for members of 
the organisation. It is available to all logged in users. Roles that should included in the search 
can be specified in the config, otherwise it searches all members in the database. The search results 
are shown as links to the user profile. You can specify if you want to show first and/or last name or 
the login name.

If your organisation has members across multiple cities, you can include the city in the search.

{{:en:plugins:search_member_screenshot.png?400|}}

==== Download ====
The plugin is available at [[https://github.com/nanoflow/admidio-search_member]]

==== Installation ====
copy the plugin to your adm_plugins folder
add the following codeblock to /adm_themes/simple/templates/overview.tpl
```
<div class="admidio-overview-plugin col-sm-6 col-lg-4 col-xl-3" id="admidio-plugin-search-member">
    <div class="card admidio-card">
        <div class="card-body">
            {load_admidio_plugin plugin="search_member" file="search_member.php"}
        </div>
    </div>
</div>
```

==== Contribution ====
Contributions and feature requests are welcome