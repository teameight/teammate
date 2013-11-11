<table class="form-table">
    <tr valign="top">
        <th scope="row"><label for="admin_email">Text Input </label></th>
        <td>
            <input name="admin_email" type="text" id="admin_email" value="spencer@team-eight.com" class="regular-text ltr" />
            <p class="description">This address is used for admin purposes, like new user notification.</p>
        </td>
    </tr>
    <tr valign="top">
        <th scope="row">Checkbox</th>
        <td> 
            <fieldset>
            	<legend class="screen-reader-text"><span>Checkbox</span></legend>
                <label for="users_can_register"><input name="users_can_register" type="checkbox" id="users_can_register" value="1"  /> Anyone can register</label>
            </fieldset>
        </td>
    </tr>
    <tr valign="top">
        <th scope="row"><label for="default_role">Select</label></th>
        <td>
            <select name="default_role" id="default_role">
                <option selected='selected' value='editor'>Editor</option>
                <option value='administrator'>Administrator</option>
                <option value='author'>Author</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="timezone_string">Select w/ Groups</label></th>
        <td>
            <select id="timezone_string" name="timezone_string">
                <optgroup label="Africa">
                    <option value="Africa/Abidjan">Abidjan</option>
                </optgroup>
                <optgroup label="America">
                    <option value="America/Adak">Adak</option>
                </optgroup>
            </select>
            <span id="utc-time"><abbr title="Coordinated Universal Time">UTC</abbr> time is <code>2012-12-07 2:20:42</code></span>
            <p class="description">Choose a city in the same timezone as you.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Radios</th>
        <td>
            <fieldset>
                <legend class="screen-reader-text"><span>Date Format</span></legend>
                <label title='F j, Y'><input type='radio' name='date_format' value='F j, Y' checked='checked' /> <span>December 6, 2012</span></label><br />
                <label title='Y/m/d'><input type='radio' name='date_format' value='Y/m/d' /> <span>2012/12/06</span></label><br />
                <label title='m/d/Y'><input type='radio' name='date_format' value='m/d/Y' /> <span>12/06/2012</span></label><br />
                <label title='d/m/Y'><input type='radio' name='date_format' value='d/m/Y' /> <span>06/12/2012</span></label><br />
                <label><input type="radio" name="date_format" id="date_format_custom_radio" value="\c\u\s\t\o\m"/> Custom: </label><input type="text" name="date_format_custom" value="F j, Y" class="small-text" /> <span class="example"> December 6, 2012</span> <img class='ajax-loading' src='http://team-eight.com/dev/t8/wp-admin/images/wpspin_light.gif' />
                <p><a href="http://codex.wordpress.org/Formatting_Date_and_Time">Documentation on date and time formatting</a>.</p>
            </fieldset>
        </td>
    </tr>
</table>