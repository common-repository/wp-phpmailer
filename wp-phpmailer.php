<?php
/*
Plugin Name: wpPHPMailer
Version: 1.6.2
Plugin URI: http://www.coffee2code.com/wp-plugins/
Author: Scott Reilly
Author URI: http://www.coffee2code.com
Description: Enable WordPress to send e-mail via SMTP instead of via PHP's mail() function (aka sendmail).

**NOTE: This plugin is no longer necessary as of WordPress 2.2 as SMTP was then built into WordPress.
Please refer to my Configure SMTP (http://coffee2code.com/wp-plugins/configure-smtp/) plugin for the successor to this
plugin, which allows you to configure the SMTP settings, including sending e-mail via SSL/TLS (such as through GMail).**

=>> Visit the plugin's homepage for more information and latest updates  <<=

!!! 
Note for upgraders:
If you are upgrading from a version of this plugin prior to 1.5 you will have to make note of the
settings you had previously set in the wp-phpmailer.php file and re-enter them via the plugin's new
admin options page.
!!!


Installation:

1. Unzip/unpack the plugin distribution file into your wp-content/plugins/ directory.

[Unless you wish to alternatively install the full PHPMailer yourself, then instead do this:
	a. Obtain the PHPMailer package from http://phpmailer.sourceforge.net
	b. Extract the contents into your wp-content/plugins/ directory (this should create a
	   subdirectory called something like 'phpmailer'
	c. Rename the directory created in (b.) to 'wp-phpmailer']
	
NOTE: This plugin was last tested against PHPMailer version 1.73.  The plugin distribution file 
only contains the English-language file for PHPMailer; many other language files are available from 
the official PHPMailer distribution

2. [Skip this step unless you chose to install PHPMailer yourself]
Copy the file wp-phpmailer.php from the plugin distribution file into your wp-content/plugins/wp-phpmailer/ directory
-OR-
Copy and paste the contents of http://www.coffee2code.com/wp-plugins/wp-phpmailer.phps into a file called 
wp-phpmailer.php, and put that file into your wp-content/plugins/wp-phpmailer/ directory.

3. Activate the plugin from your WordPress admin 'Plugins' page.

4. In WordPress's Admin section, click the Options tab.  Then click the "wpPHPMailer" subtab.  Adjust the configuration
options to suit your situation.  Be sure to change the very first option, which tells WP to use wpPHPMailer instead of
the built-in mailer.

NOTE: If you are using WP 1.5.1 or later, you do not have to do anything else.  Just be aware that you cannot have more
than one plugin activated that attempt to override the core WP function, wp_mail().  If you activate this plugin and
do not see the "wpPHPMailer" tab under "Options" in the Admin section, then you *may* have mail plugin conflicts.

5. For those using a version of WP prior to 1.5.1 : In the WordPress core file wp-includes/functions.php, find and 
replace the single occurrence of "function wp_mail(" to function old_wp_mail("

*/

/*
Copyright (c) 2004-2006 by Scott Reilly (aka coffee2code)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the 
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

if ( !function_exists('wp_mail') ) :

require_once "class.phpmailer.php";

if ( !function_exists('c2c_is_wp_older_than') ) {
	function c2c_is_wp_older_than($version) {
		global $wp_version;
		$wp_version = explode('-',$wp_version);
		$wp_version = $wp_version[0];
		list($major, $minor, $rev, $subrev) = array_map('intval', explode('.', $wp_version));
		list($cmajor, $cminor, $crev, $csubrev) = array_map('intval', explode('.', $version));
		if ($major < $cmajor) return true;
		elseif ($major > $cmajor) return false;
		if ($minor < $cminor) return true;
		elseif ($minor > $cminor) return false;
		if ($rev || $crev) {
			if (!$crev) return false;
			if ($rev < $crev) return true;
			elseif ($rev > $crev) return false;
		}
		if ($subrev || $csubrev) {
			if (!$csubrev) return false;
			if ($subrev  < $csubrev) return true;
			elseif ($subrev > $csubrev) return false;
		}
		return false;
	}
}

if (is_plugin_page()) :
	c2c_admin_phpmailer();
else :

function wp_mail( $user, $subject, $message, $headers='', $htmlmessage='' ) {
	if( $headers == '' ) {
		$headers = "MIME-Version: 1.0\r\n" .
			"From: " . get_settings('admin_email') . "\r\n" .
			"Content-Type: text/plain; charset=\"" . get_settings('blog_charset') . "\"\r\n";
	}
	
	$options = get_option('c2c_phpmailer');
	if (! $options['use_phpmailer'] )
		return @mail($user, $subject, $message, $headers);
	
	$mail = new PHPMailer();

	$mail->IsSMTP();					// set mailer to use SMTP
	

	$mail->Host = $options['Host'];
	$mail->SMTPAuth = $options['SMTPAuth'] ? $options['SMTPAuth'] : true;
	if ($mail->SMTPAuth) {
		$mail->Username = $options['Username'];
		$mail->Password = $options['Password'];
	}
	$mail->Port = ($options['Port']) ? $options['Port'] : 25;
	
	/* A few less used settings */
	$echo_error = false;					// Echo mail failure?
	$mail->WordWrap = $options['WordWrap'] ? $options['WordWrap'] : 60;
	$use_hardcoded_FROM_values = $options['use_hardcoded_from'] ? $options['use_hardcoded_from'] : false;
	$mail->From = $options['From'] ? $options['From'] : get_settings('admin_email');
	$mail->FromName = $options['FromName'] ? $options['FromName'] : 'Blog Admin';
	
	/*---- END CONFIGURE SECTION ---- */

	if (!empty($headers)) {
		preg_match('|From: \"([^"]*)\" <([^>]*)>|', $headers, $fromfields);
		if ($use_hardcoded_FROM_values) {
			$mail->AddReplyTo($fromfields[2], $fromfields[1]);
		} else {
			$mail->From = $fromfields[2];
			$mail->FromName = $fromfields[1];
		}
		// Add headers
		foreach (explode("\r\n", $headers) as $header) {
			if (!empty($header) && !preg_match('|^From:|e', $header))
				$mail->AddCustomHeader($header);
		}
	}
	$mail->ClearAllRecipients();
	if (count($user) > 1) {
		foreach ($user AS $a_user) {
			$a_user = trim($a_user);
			if (!empty($a_user))
				$mail->AddBCC($a_user); // using BCC since we don't want to expose all emails to all recipients
		}
	} else {
		$mail->AddAddress($user);
	}

	$htmlcond = ('' != $htmlmessage) ? true : false;
	$mail->IsHTML($htmlcond);				// set email format to HTML

	$mail->Subject = $subject;
	if ($htmlcond) {
		$mail->Body    = $htmlmessage;
		$mail->AltBody = $message;
	} else {
		$mail->Body    = $message;
	}

	$success = $mail->Send();
	if (!$success && $echo_failure) {
		echo "<p>Message '" . $subject . "' could not be sent to " . $user . ". </p>";
		echo "<p>Mailer Error: " . $mail->ErrorInfo . "</p>";
		die();
	}
	return $success;
} // end phpmailer()


// Admin interface code

function c2c_admin_add_phpmailer() {
	// Add menu under Options:
	add_options_page('wpPHPMailer Options', 'wpPHPMailer', 8, 'wp-phpmailer/' . basename(__FILE__), 'c2c_admin_phpmailer');
	// Create option in options database if not there already:
	$options = array();
	$options['use_phpmailer'] = 0;			// Use wpPHPMailer instead of sendmail?
	$options['Host'] = 'smtp1.example.com';		// SMTP server
	$options['Port'] = 25;				// SMTP port
	$options['SMTPAuth'] = 1;			// SMTP server requires authentication?
	$options['Username'] = '';			// SMTP username
	$options['Password'] = '';		// SMTP password
	
	/* A few less used settings */
	$options['WordWrap'] = 60;
	$options['use_hardcoded_from'] = 0;		// Use defined 'From' and 'FromName'?
	$options['From'] = '';
	$options['FromName'] = '';
	
	add_option('c2c_phpmailer', $options, 'Options for the wpPHPMailer plugin by coffee2code');
} // end c2c_admin_add_phpmailer()

function c2c_admin_phpmailer() {
	// See if user has submitted form
	if ( isset($_POST['submitted']) ) {
		$options = array();
		
		foreach (array('use_phpmailer', 'Host', 'Port', 'SMTPAuth', 'Username', 'Password', 'WordWrap', 'use_hardcoded_from', 'From', 'FromName') AS $opt) {
			$options[$opt] = $_POST[$opt];
		}
		
		// Remember to put all the other options into the array or they'll get lost!
		update_option('c2c_phpmailer', $options);
		echo '<div class="updated"><p>Plugin settings saved.</p></div>';
	}
	
	// Draw the Options page for the plugin.
	$options = get_option('c2c_phpmailer');
	
	$action_url = $_SERVER[PHP_SELF] . '?page=wp-phpmailer/' . basename(__FILE__);
	if ( $options['use_phpmailer'] ) {
		$use_yes = ' checked="checked"';
		$use_no = '';
	} else {
		$use_yes = '';
		$use_no = ' checked="checked"';
	}
	$do_auth = $options['SMTPAuth'] ? ' checked="checked"' : '';
	$do_from = $options['use_hardcoded_from'] ? ' checked="checked"' : '';
echo <<<END
	<div class='wrap'>\n
		<h2>wpPHPMailer Plugin Options</h2>\n
		<p>wpPHPMailer is a plugin that enables WordPress to use an SMTP mail server to send mail, instead of relying on the default mail().</p>
END;

if ( c2c_is_wp_older_than('1.5.1') ) :
echo <<<END
	<p><strong>Note:</strong> This plugin requires a single, simple modification of one of the core WordPress files.  A future release of WordPress may
	make such a manual change unnecessary, but for the moment that is not the case.</p>
	
	<p>The hack:
	<ol>
	<li>Edit the file <code>wp-includes/functions.php</code></li>
	<li>Search for the <code>wp_mail()</code> function:<br />
	<code>function wp_mail(\$to, \$subject, \$message, \$headers = '') {</code></li>
	<li>Change the name of the function from <code>@wp_mail(</code> to <code>@old_wp_mail(</code></li>
	</ol>
END;
endif;

echo <<<END
	<hr />
<form name="phpmailer" action="$action_url" method="post">
		<input type="hidden" name="submitted" value="1" />
		
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
		   <tr valign="top">
		   	<th scope="row"> Mail handler: </th>
			<td>
			<label><input name="use_phpmailer" id="use_phpmailer_no" type="radio" value="0"{$use_no} />Sendmail via <code>mail()</code> (Default WordPress mail handler)</label><br />
			<label for="use_phpmailer"><input name="use_phpmailer" id="use_phpmailer_yes" type="radio" value="1"{$use_yes} />SMTP via wpPHPMailer</label>
			</td>
		   </tr>
		</table>
<fieldset class="option">
<legend>wpPHPMailer configuration</legend>
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
		   <tr>
		   	<th scope="row"> SMTP mail server: </th>
			<td><input name="Host" type="text" id="phpmailer_host" size="40" value="{$options['Host']}" />
			<label for="phpmailer_port">Port:</label>
			<input name="Port" type="text" id="phpmailer_port" value="{$options['Port']}" size="6" /><br />
			You can also define backup SMTP servers: <code>smtp1.example.com;smtp2.example.com</code></td>
		   </tr>
		   <tr>
		   	<th scope="row"> SMTP server requires authentication? </th>
			<td><input name="SMTPAuth" type="checkbox" id="phpmailer_smtpauth" value="1"{$do_auth} /> (If checked, specify authentication settings below.)</td>
		   </tr>
		   <tr>
		   	<th scope="row"> SMTP mail username: </th>
			<td><input name="Username" type="text" id="phpmailer_username" size="40" value="{$options['Username']}" /></td>
		   </tr>
		   <tr>
		   	<th scope="row"> SMTP mail password: </th>
			<td><input name="Password" type="password" id="phpmailer_password" size="40" value="{$options['Password']}" /></td>
		   </tr>
		
		   <tr>
		   	<th scope="row"> Wordwrap length: </th>
			<td><input name="WordWrap" type="text" id="phpmailer_wordwrap" size="6" value="{$options['WordWrap']}" /></td>
		   </tr>

		   <tr>
		   	<th scope="row"> Use hardcoded 'From:' values? </th>
			<td><input name="use_hardcoded_from" type="checkbox" id="phpmailer_use_hardcoded_from" value="1"{$do_from} /></td>
		   </tr>
		   <tr>
		   	<th scope="row"> Hardcoded 'From:' e-mail: </th>
			<td><input name="From" type="text" id="phpmailer_from" size="40" value="{$options['From']}" /></td>
		   </tr>
		   <tr>
		   	<th scope="row"> Hardcoded 'From:' name: </th>
			<td><input name="FromName" type="text" id="phpmailer_fromname" size="40" value="{$options['FromName']}" /></td>
		   </tr>
		
		</table>
</fieldset>		
	<div class="submit"><input type="submit" name="Submit" value="Save changes &raquo;" /></div>
</form>
	</div>
END;
} // end c2c_admin_phpmailer()
add_action('admin_menu', 'c2c_admin_add_phpmailer');

endif;

endif;	// end if function_exists('wp_mail')

?>