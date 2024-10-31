<?php
/*
Plugin Name: PingPressFM
Plugin URI: http://www.soldoutactivist.com/project-pingpressfm
Description: Allows you to spread your wonderful blog to 10+ social networks via <a href="http://ping.fm">Ping.fm</a>. <strong>Now with support for scheduled posts and custom triggers.</strong>

Version: 2.2.7
Author: Sold Out Activist
Author URI: http://www.soldoutactivist.com
*/

/***********************************************************************
 * Copyright (c) 2008 Sold Out Activist, http://www.soldoutactivist.com
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 ***********************************************************************/

// set basic functionality hooks
add_action('transition_post_status', 'ppfm_publish', 1, 3);

//options settings
add_action('admin_menu', 'ppfm_admin');
add_action('init', 'ppfm_init');

// set up defaults when plugin is activated
register_activation_hook(__FILE__, 'ppfm_install');


function ppfm_install() {
	global $wpdb;

	if (!is_blog_installed()) return;

	// create options
	add_option('ppfm_firstTime', 1, '', 'no');
	add_option('ppfm_optionsUpdated', '0', '', 'no');
	add_option('ppfm_keyVerified', 0, '', 'no');
	add_option('ppfm_reverifyKey', 0, '', 'no');
	add_option('ppfm_deleteOldSettings', 0, '', 'no');
	add_option('ppfm_apiDev', '', '', 'no');
	add_option('ppfm_apiUser', '', '', 'no');
	add_option('ppfm_ellipse', '...', '', 'no');
	add_option('ppfm_triggers', 'active=B(false)\tlabel=S(m)\ttype=S(m)\tdescription=S(Microblog: 140 Characters Max, No HTML)\tcategories=A()\ttags=A()\tformat=S([Blog]
 \$subject: \$body \$slink)\nactive=B(false)\tlabel=S(s)\ttype=S(s)\tdescription=S(Status Update: 200 Characters Max, No HTML)\tcategories=A()\ttags=A()\tformat=S([Blog] \$subj
ect: \$body \$slink)\nactive=B(false)\tlabel=S(b)\ttype=S(b)\tdescription=S(Blog Entry: Unlimited Length, HTML Preserved)\tcategories=A()\ttags=A()\tformat=S((My Original Blog
Post: <a href=\"\$link\">\$link</a>)\\n\$body)', '', 'no');
	add_option('ppfm_dbVersion');

	// get old verison values
	$api_user = get_option('pingpressfm2_api_user');
	$ellipse = get_option('pingpressfm2_dotdotdot');

	// if not old versions, look for new values
	if (!$api_user) $api_user = get_option('ppfm_apiUser');
	if (!$ellipse) $ellipse = get_option('ppfm_ellipse');

	// update options
	update_option('ppfm_firstTime', 1);
	update_option('ppfm_apiDev', 'ee001e6b5e35f5a8b57cbca3126632db');
	update_option('ppfm_apiUser', $api_user);
	update_option('ppfm_dbVersion', '1.1');

	// add the database table
	$table = $wpdb->prefix. 'pingpressfm';
	$sql = 'CREATE TABLE `'. $table. '` (
		`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`post_id` BIGINT UNSIGNED NOT NULL DEFAULT \'0\',
		`timestamp` INT UNSIGNED NOT NULL DEFAULT \'0\',
		`trigger` VARCHAR(16) NOT NULL, 
		`ping_id` VARCHAR(20) NOT NULL
	);';

	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}

function ppfm_init($type) {
	add_action('admin_notices', 'ppfm_notices');
}

function ppfm_admin() {
	add_options_page('PingPressFM', 'PingPressFM', 7, 'pingpressfm', 'ppfm_options');
}

function ppfm_slink($link=false) {
	$s3nt_url = 'http://www.s3nt.com/robot/'. $link;

	$pingfm_curl = curl_init($s3nt_url);
	curl_setopt($pingfm_curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($pingfm_curl, CURLOPT_TIMEOUT, 30);
	$new_url = curl_exec($pingfm_curl);
	$pingfm_curl = curl_close($pingfm_curl);

	if (!preg_match('/^FAIL/i', $new_url) && $new_url)
		return trim($new_url);
	return $link;
}

function ppfm_notices($type) {
	if (get_option('ppfm_firstTime')) {
		echo '<div id="message" class="updated" style="background: #FFA; border: 1px #AA3 solid;"><p>';
		echo (!get_option('ppfm_apiUser')) ? 'You need to configure your PingPressFM plugin. Click <a href="options-general.php?page=pingpressfm">here</a> to do that.' : ppfm_verify();
		echo '</p></div>';
    update_option('ppfm_firstTime', 0);
  }

	if (get_option('ppfm_optionsUpdated')) {
		if (!get_option('ppfm_apiUser')) {
			echo '<div id="message" class="updated" style="background: #FFA; border: 1px #AA3 solid;"><p>Your options have been updated, but you still haven\'t updated your API key. <a href="http://www.soldoutactivist.com/project-pingpressfm#get-my-key">How do I get my key?</a></p></div>';
		} else {
			if (!get_option('ppfm_keyVerified') || get_option('ppfm_reverifyKey'))
				echo '<div id="message" class="updated" style="background: #FFA; border: 1px #AA3 solid;"><p>', ppfm_verify(), '</p></div>';
		}
		update_option('ppfm_optionsUpdated', 0);
	}
}

function ppfm_verify() {
	// do the deed with ping.fm
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_URL, 'http://api.ping.fm/v1/user.validate');
	curl_setopt($ch, CURLOPT_POSTFIELDS, Array('api_key' => get_option('ppfm_apiDev'), 'user_app_key' => get_option('ppfm_apiUser')));
	$output = curl_exec($ch);

	// smoke a cigarette afterwards?
	if (preg_match('/OK/', $output)) {
		echo ('<strong>Your key has been verified.</strong> Your can now post to your <a href="http://www.ping.fm">Ping.fm</a> account.');
		update_option('ppfm_keyVerified', 1);
	} else {
		echo ('<strong>Your key could not be verified.</strong> Check your key, or the <a href="http://www.soldoutactivist.com/project-pingpressfm#troubleshooting">troubleshooting guide</a>.');
		update_option('ppfm_keyVerified', 0);
		return false;
	}
	update_option('ppfm_reverifyKey', 0);
}

function ppfm_call($type=false, $post_data=false) {
	if (!$type || !$post_data) return false;

	// do the deed with ping.fm
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_URL, 'http://api.ping.fm/v1/'. $type);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	$output = curl_exec($ch);

	// smoke a cigarette afterwards?
	if (preg_match('/OK/', $output)) {
		preg_match('/\<transaction\>([^\<]*)\<\/transaction\>/', $output, $match);
		$ping_id = addslashes(trim($match[1]));

	// no, it wasn't that good
	} else {
		preg_match('/\<message\>([^\<]*)\<\/message\>/', $output, $match);
		$ping_id = addslashes(trim($match[1]));
	}

	return $ping_id;
}

function ppfm_publish($new_status, $old_status, $post) {
	global $wpdb;

	if ($old_status == 'publish' || $new_status != 'publish' || $post->post_type != 'post') return;

	$ppfm_apiUser = get_option('ppfm_apiUser');
	$ppfm_apiDev = get_option('ppfm_apiDev');

	$post->permalink = get_permalink($post->ID);

	// grab triggers
	$triggers = ppfm_triggers();

	// get post's categories and tags
	$meta = ppfm_query('SELECT x.`term_id`, x.`taxonomy` FROM `'. $wpdb->term_taxonomy. '` AS x LEFT JOIN `'. $wpdb->terms. '` AS t ON x.`term_id` = t.`term_id` LEFT JOIN `'. $wpdb->term_relationships. '` AS r ON r.`term_taxonomy_id` = x.`term_taxonomy_id` WHERE r.`object_id` = \''. $post->ID. '\'');

	// if query doesn't have any rows, database error, all posts have at least one row which is the category `Uncategorized`
	// and since this is a database error, and using this plugin my only recourse is to write to the database...
	// best to just return and let wordpress tell the user about database errors on the next page as it probably will.
	if (!$meta['count']) return;

	// determine if match conditions
	$firing = Array();
	foreach ($triggers as $trigger) {
		if (!$trigger['active']) continue;

		if (!count($trigger['categories']) && !count($trigger['tags'])) {
			$firing[] = $trigger;
			continue;
		}

		$in_category = Array(0);
		$in_tag = Array(0);
		if (count($trigger['categories'])) {
			foreach ($meta['data'] as $row) {
				if ($row['taxonomy'] == 'category') {
					 $in_category[] = in_array($row['term_id'], $trigger['categories']);
				} else if ($row['taxonomy'] == 'post_tag') {
					$in_tag[] = in_array($row['term_id'], $trigger['tags']);
				}
			}
		}

		$in_category = array_sum($in_category);
		$in_tag = array_sum($in_tag);

		if (($trigger['both'] && $in_category && $in_tag) || (!$trigger['both'] && ($in_category || $in_tag)))
			$firing[] = $trigger;
	}

	// format pings
	$output = Array();
	foreach ($firing as $trigger)
		$output[$trigger['label']] = ($output[$trigger['label']]) ? $output[$trigger['label']] : ppfm_parser($trigger, $post);

	// fire off pings
	foreach ($output as $label => $message) {
		$post_data = Array(
			'api_key' => $ppfm_apiDev,
			'user_app_key' => $ppfm_apiUser,
//			'debug' => 1
		);

		if ($label == 'm' || $label == 's' || $label == 'b') {
			switch ($label) {
				case 'm': $post_data['post_method'] = 'microblog'; break;
				case 's': $post_data['post_method'] = 'status'; break;
				case 'b': 
					$post_data['post_method'] = 'blog'; 
					$post_data['title'] = $post->post_title;
					break;
			}

			$call_type = 'user.post';
		} else {
			$call_type = 'user.tpost';
			$post_data['trigger'] = $label;
		}

		$post_data['body'] = $message;
		$ping_id = ppfm_call($call_type, $post_data);

		// insert results into the database
		ppfm_query('INSERT INTO `'. $wpdb->prefix. 'pingpressfm` (`post_id`, `timestamp`, `trigger`, `ping_id`) VALUES (\''. $post->ID. '\', UNIX_TIMESTAMP(), \''. addslashes($label). '\', \''. $ping_id. '\')');
	}
}

function ppfm_parser($trigger=false, $post=false) {
	if (!$trigger || !$post) return false;

	$format = $trigger['format'];
	$link = $post->permalink;
	$slink = 'http://ping.fm/123456';
	$subject = $post->post_title;
	$body = $post->post_content;
	$ellipse = get_option('ppfm_ellipse');
	$content = $format;

	$length = false;
	switch ($trigger['type']) {
		case 'm': $length = 140; break;
		case 's': $length = 200; break;
	}

	if ($length) {
		$subject = strip_tags($subject);
		$body = strip_tags($body);
	}

	$subject = trim(preg_replace('/ +/', ' ', $subject));
	$body = trim(preg_replace('/ +/', ' ', $body));

	$content = str_replace('$link', $link, $content);
	$content = str_replace('$slink', $slink, $content);

	$temp_subject = $subject;
	if ($length) {
		$remaining = $length - strlen($content) + strlen('$subject') + 1;
		if ($remaining < 0) $remaining = 0;

		if (strlen($content) + strlen($ellipse) + strlen($subject) > $length)
			$temp_subject = substr($subject, 0, $remaining - strlen($ellipse)). $ellipse;
	}

	$content = str_replace('$subject', $temp_subject, $content);

	$temp_body = $body;
	if ($length) {
		$remaining = $length - strlen($content) + strlen('$body') + 1;
		if ($remaining < 0) $remaining = 0;

		if (strlen($content) + strlen($ellipse) + strlen($body) > $length)
			$temp_body = substr($body, 0, $remaining - strlen($ellipse) - 1). $ellipse;
	}

	$content = str_replace('$body', $temp_body, $content);

	$content = str_replace('\n', "\n", $content);
	$content = str_replace($slink, $link, $content);

	return $content;
}

function ppfm_triggers() {
	$triggers = explode("\n", get_option('ppfm_triggers'));
	foreach ($triggers as $index => $trigger) {
		$triggers[$index] = explode("\t", $triggers[$index]);
		foreach ($triggers[$index] as $radix => $trigger_variable) {
			list($key, $value) = explode("=", $trigger_variable, 2);
			$key = trim($key);
			$value = trim($value);
			if (preg_match('/^S\(/i', $value)) {
				$value = trim(preg_replace('/^S\((.*)\)$/is', '$1', $value));
			} else if (preg_match('/^A\(/i', $value)) {
				$value = explode(',', trim(preg_replace('/^A\((.*)\)$/i', '$1', $value)));
				foreach ($value as $i => $row)
					if (!$value[$i])
						unset($value[$i]);
			} else if (preg_match('/^B\(true\)$/i', $value)) {
				$value = true;
			} else if (preg_match('/^B\(false\)$/i', $value)) {
				$value = false;
			}
      $triggers[$index][$key] = $value;
      unset($triggers[$index][$radix]);
		}
	}
	return $triggers;
}

function ppfm_query($query=false, $public=false) {
	global $wpdb;

	$result = @mysql_query($query);
	$error = @mysql_error();
	$count = @mysql_num_rows($result);

	$data = Array();
	while ($data[] = @mysql_fetch_assoc($result));
	@array_pop($data);

	if ($error) {
		if ($public) {
			echo 'Unfortunately, there was a database error.<br /><strong>MySQL:</strong> ', $error;
		}
	}

	return Array('count' => $count, 'query' => $query, 'error' => $error, 'data' => $data);
}

function ppfm_options() {
	global $wpdb;

	$ppfm_apiUser = get_option('ppfm_apiKey');
	$ppfm_keyVerified = get_option('ppfm_keyVerified');

	$categories = ppfm_query('SELECT wt.`term_id`, wt.`name` FROM `'. $wpdb->term_taxonomy. '` AS wtt LEFT JOIN `'. $wpdb->terms. '` AS wt ON wtt.`term_id` = wt.`term_id` WHERE wtt.`taxonomy` = \'category\' ORDER BY wt.`name` ASC', true);
	if ($categories['error']) return false;

	$tags = ppfm_query('SELECT wt.`term_id`, wt.`name` FROM `'. $wpdb->term_taxonomy. '` AS wtt LEFT JOIN `'. $wpdb->terms. '` AS wt ON wtt.`term_id` = wt.`term_id` WHERE wtt.`taxonomy` = \'post_tag\' ORDER BY wt.`name` ASC', true);
	if ($tags['error']) return false;

	$ppfm_triggers = get_option('ppfm_triggers');

	$triggers = ppfm_triggers();

	$ellipse = get_option('ppfm_ellipse');
	$subject = 'A Subject For The Ages';
	$body = 'A Body For The Ages. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Sed nec metus vel enim eleifend adipiscing. Donec venenatis. Quisque scelerisque nunc at nisl. Suspendisse laoreet nulla ut ante. Nullam ultricies turpis id orci. Proin elementum aliquet pede. Integer in libero. Vestibulum eu tortor. Donec fringilla, elit eget pretium dignissim, augue orci interdum orci, et blandit elit pede a lacus. Quisque tempus tortor vitae risus. Fusce neque. Duis aliquet gravida ligula. Maecenas varius. Proin cursus rutrum urna. Curabitur semper, est a convallis tristique, dolor est sodales nisi, id viverra risus augue vel erat. Nullam vitae orci. Aliquam molestie ipsum at quam. Donec fringilla sollicitudin urna.';
	$link = 'http://asitefortheages.com/a-subject-for-the-ages';
	$slink = 'http://ping.fm/'. chr(rand(97, 122)). chr(rand(97, 122)). chr(rand(97, 122)). chr(rand(97, 122)). chr(rand(97, 122)). chr(rand(97, 122));

	echo '<script type="text/javascript">
/* <![CDATA[ */
String.prototype.trim = function() { return this.replace(/^\s*/g, "").replace(/\s*$/g, ""); }

var subject = "', addslashes($subject), '";
var body = "', addslashes($body), '";
var link = "', addslashes($link), '";
var slink = "', $slink, '";
var ellipse = "', addslashes($ellipse), '";

var triggers = {};
';

foreach ($triggers as $trigger) {
	echo 'triggers["', $trigger['label'], '"] = {';

	$out = '';
	foreach ($trigger as $key => $value) {
		$out .= '"'. $key. '": ';
		if ($value === true) $out .= 'true';
		else if ($value === false) $out .= 'false';
		else if (is_numeric($value)) $out .= $value;
		else if (is_array($value)) {
			$out .= '[';
			foreach ($value as $key)
				$out .= $key. ', ';
			$out .= ']';
		} else {
			$out .= '"'. str_replace("\\\\n", "\\n", addslashes($value)). '"';
		}
		$out .= ', ';
	}
	echo substr($out, 0, -2);
	echo '};', "\n";
}
echo "\n";

echo 'var categories = {';
$out = '';
foreach ($categories['data'] as $category)
 	$out .= '"'. preg_replace('/\"/', '\\"', htmlspecialchars_decode(preg_replace("/(\r?\n)/s", " ", $category['name']))). '": '. $category['term_id']. ', ';
echo substr($out, 0, -2);
echo '};', "\n\n";

echo 'var tags = {';
$out = '';
foreach ($tags['data'] as $tag)
	$out .= '"'. preg_replace('/\"/', '\\"', htmlspecialchars_decode(preg_replace("/(\r?\n)/s", " ", $tag['name']))). '": '. $tag['term_id']. ', ';
echo substr($out, 0, -2);
echo '};', "\n\n";

echo 'function install() {
	var index, i, label, paragraph;

	var triggerSetup = id("triggerSetup");
	truncate(triggerSetup);

	triggerSetup.appendChild(elementWithText("strong", "Current Trigger"));
	triggerSetup.appendChild(element("br"));

	var triggerSelect = element("select", "triggerSelect");
	triggerSelect.options[0] = new Option("--- Select a Trigger", "");
	triggerSelect.options[0].style.backgroundColor = "#FFFFAA";

	var i = 1;
	var on = 0;
	var trigger;
	for (index in triggers) {
		trigger = triggers[index];

		label = (trigger.active) ? "(On) " : "(Off) ";
		label = label + trigger.description;
		label = label + " (@" + trigger.label + ")";

		triggerSelect.options[i] = new Option(label, trigger.label);
		triggerSelect.options[i].style.backgroundColor = (trigger.active) ? "#AAFFAA" : "#FFAAAA";

		on += (trigger.active) ? 1 : 0;
		i++;
	}

	triggerSelect.options[i] = new Option("--- Create New Trigger", "_create");
	triggerSelect.options[i].style.backgroundColor = "#FFFFAA";

	triggerSetup.appendChild(triggerSelect);

	if (!on) {
		paragraph = element("div", false, false, "background: #F77; color: #FFF; margin-top: 10px; padding: 10px;");
		paragraph.appendChild(node("None of your triggers are active! If you publish a post, it will not be sent to Ping.fm! Select a trigger to start activation."));
		triggerSetup.appendChild(paragraph);
	}

	triggerSelect.onchange = buildTrigger;
}

function buildTrigger() {
	var i, j, theAlert, paragraph, small, span, strong, select, input, label, textarea, option;

	var triggerSelect = id("triggerSelect").value;
	var trigger = triggers[triggerSelect];

	var triggerSetup = id("triggerSetup");
	truncateUp(triggerSetup, 3);

	if (!triggerSelect) return;

	if (triggerSelect == "_create")
		trigger = {"active": false, "label": "new", "type": "m", "description": "New trigger.", "categories": [], "tags": [], "format": "[Blog] $subject: $body $slink"};

	if (triggerSelect.match(/^(m|s|b)$/)) {
		theAlert = element("p", false, false, "background: #FFA; padding: 10px;");
		theAlert.appendChild(elementWithText("strong", "This is one of the 3 default trigger types. You can not edit the Label, Type, or Description. "));
		theAlert.appendChild(node("If you get randy and use Firefox to get at the diabled controls and muck up the defaults, deactivate and reactivate the plugin to restore the defaults."));
		triggerSetup.appendChild(theAlert);
	}

	paragraph = element("p");
	label = element("label");
	input = element("input", "triggerActive");
	input.checked = trigger.active;
	input.type = "checkbox";
	label.appendChild(input);
	label.appendChild(elementWithText("strong", " Trigger is active"));
	paragraph.appendChild(label);
	triggerSetup.appendChild(paragraph);

	paragraph = element("p");
	paragraph.appendChild(elementWithText("strong", "Trigger Label/Type"));
	paragraph.appendChild(element("br"));
	
	span = elementWithText("span", "@", false, false, "font-size: 125%;");
	paragraph.appendChild(span);

	input = element("input", "triggerLabel");
	input.size = 16;
	input.maxLength = 16;
	input.value = trigger.label;
	if (triggerSelect.match(/^(m|s|b)$/)) input.disabled = true;
	paragraph.appendChild(input);

	select = element("select", "triggerType");
	select.options[0] = new Option("Microblog: 140 Characters Max, No HTML", "m");
	select.options[1] = new Option("Status Update: 200 Characters Max, No HTML", "s");
	select.options[2] = new Option("Blog Entry: Unlimited Length, HTML Preserved", "b");
	if (triggerSelect.match(/^(m|s|b)$/)) select.disabled = true;
	paragraph.appendChild(select);

	switch (trigger.type) {
		case "m": select.options[0].selected = true; break;
		case "s": select.options[1].selected = true; break;
		case "b": select.options[2].selected = true; break;
	}

	paragraph.appendChild(element("br"));
	paragraph.appendChild(elementWithText("small", "A Blog type ping has the $subject sent automatically, so using $subject in the Trigger Format is not required."));
	triggerSetup.appendChild(paragraph);

	if (triggerSelect == "_create") {
		alert = element("p", false, false, "background: #FFA; padding: 10px;");
		alert.appendChild(elementWithText("strong", "Be sure you have created a Ping.fm trigger with the label below before you use this trigger to ping posts. "));
		alert.appendChild(node("Otherwise your pings from your blog might fail, or worse, could be broadcast to all default triggers."));
		triggerSetup.appendChild(alert);
	}

	paragraph = element("p");
	paragraph.appendChild(elementWithText("strong", "Trigger Description"));
	paragraph.appendChild(element("br"));

	input = element("input", "triggerDescription");
	input.type = "text";
	input.value = trigger.description;
	input.size = 45;
	input.maxLength = 50;
	if (triggerSelect.match(/^(m|s|b)$/))
		input.disabled = true;
	paragraph.appendChild(input);

	paragraph.appendChild(element("br"));
	paragraph.appendChild(elementWithText("small", "50 Characters max."));
	triggerSetup.appendChild(paragraph);

	paragraph = element("p");
	paragraph.appendChild(elementWithText("strong", "Trigger Categories/Tags"));
	paragraph.appendChild(element("br"));

	select = element("select", "triggerCategories", false, "height: 144px;");
	select.multiple = "multiple";
	option = element("option");
	option.text = "--- All Categories";
	option.value = "";
	if (!trigger.categories.length)
		option.selected = true;
	select.options.add(option);

	i = 1;
	for (index in categories) {
		if (index) {
			option = element("option");
			option.text = index;
			option.value = categories[index];
			for (j=0; j<trigger.categories.length; j++)
				if (trigger.categories[j] == categories[index]) 
					option.selected = true;
			select.options.add(option);
			i++;
		}
	}

	paragraph.appendChild(select);

	select = element("select", "triggerTags", false, "height: 144px;");
	select.multiple = "multiple";
	option = document.createElement("option");
	option.value = "";
	option.text = "--- All Tags";
	option.selected = (!trigger.tags.length) ? true : false;
	select.options.add(option);

	i = 1;
	for (index in tags) {
		if (index) {
			option = element("option");
			option.text = index;
			option.value = tags[index];
			for (j=0; j<trigger.tags.length; j++)
				if (trigger.tags[j] == tags[index]) 
					option.selected = true;
			select.options.add(option);
			i++;
		}
	}

	paragraph.appendChild(select);
	paragraph.appendChild(element("br"));
	paragraph.appendChild(elementWithText("small", "If no categories or tags are selected, this trigger will be used for all posts."));
	triggerSetup.appendChild(paragraph);


	// trigger both
	paragraph = element("p");
	label = element("label");
	input = element("input", "triggerBoth");
	input.type = "checkbox";
	input.value = 1;
	input.checked = (trigger.both) ? "checked" : "";
	label.appendChild(input);
	strong = elementWithText("strong", " Use this trigger only when the post has at least one category and tag from ");
	strong.appendChild(elementWithText("em", "both"));
	strong.appendChild(node(" of the lists above."));
	label.appendChild(strong);
	paragraph.appendChild(label);
	paragraph.appendChild(element("br"));
	paragraph.appendChild(elementWithText("small", "Say you have a trigger, @funnyhatemail, a category, Hate Mail, and a tag, Funny. Turning the above option on would only use this plugin to send a @funnyhatemail ping if the post was in the Hate Mail category and had the tag Funny."));
	triggerSetup.appendChild(paragraph);


	// trigger format
	paragraph = element("p");
	paragraph.appendChild(elementWithText("strong", "Trigger Format"));
	paragraph.appendChild(element("br"));

	textarea = element("textarea", "triggerFormat");
	textarea.cols = 45;
	textarea.rows = 5;
	textarea.value = trigger.format;
	paragraph.appendChild(textarea);
	paragraph.appendChild(element("br"));

	small = element("small");
	small.appendChild(elementWithText("strong", "Tags:"));
	small.appendChild(node(" $subject, $body, $link, $slink (shortened link), you can insert new lines by hitting enter.)"));
	small.appendChild(element("br"));

	paragraph.appendChild(small);
	triggerSetup.appendChild(paragraph);


	paragraph = element("p");
	paragraph.appendChild(elementWithText("strong", "Trigger Example"));
	paragraph.appendChild(element("br"));

	span = elementWithText("span", "As you contruct your Trigger Format, this section will update in real-time.", "triggerFormatOutput");

	paragraph.appendChild(span);
	triggerSetup.appendChild(paragraph);

	if (trigger.format) parseTriggerFormat();

	// triggerDelete
	paragraph = element("p");
	label = element("label");
	input = element("input", "triggerDelete");
	input.type = "checkbox";
	input.value = 1;
	if (triggerSelect.match(/^(m|s|b)$/))
		input.disabled = true;
	label.appendChild(input);
	label.appendChild(elementWithText("strong", " Delete Trigger"));
	paragraph.appendChild(label);
	triggerSetup.appendChild(paragraph);


	paragraph = element("p");
	input = element("input", "triggerUpdate");
	input.type = "button";
	input.value = (triggerSelect == "_create") ? "Create Trigger" : "Update Trigger";
	input.onclick = triggerUpdate;
	paragraph.appendChild(input);
	triggerSetup.appendChild(paragraph);

	paragraph = element("p", false, false, "background-color: #FFA; padding: 10px;");
	paragraph.appendChild(elementWithText("strong", "This will only update the information temporarily."));
	paragraph.appendChild(node(" You still need to use the button below to make the changes permanent. After you\'ve updated a trigger temporarily, you can make changes to other triggers before doing a permanent update."));
	paragraph.appendChild(element("br"));
	paragraph.appendChild(element("br"));
	paragraph.appendChild(elementWithText("strong", "If you accidentally delete a trigger before permanently updating all the options,"));
	paragraph.appendChild(node(" you can just refresh the page and everything will go back to the way it was."));
	triggerSetup.appendChild(paragraph);

	id("triggerActive").checked = trigger.active;
	id("triggerBoth").checked = trigger.both;

	id("triggerFormat").onkeyup = parseTriggerFormat;
	id("ellipse").onkeyup = function() { ellipse = this.value; parseTriggerFormat(); }
}

function triggerUpdate() {
	var triggerSelect = id("triggerSelect").value;
	var deleting = id("triggerDelete").checked;

	var i = 0;
	if (!deleting) {
		var active = id("triggerActive").checked;
		var label = id("triggerLabel").value.replace(/\t|\n/g, "");
		var type = id("triggerType").value.replace(/\t|\n/g, "");
		var both = id("triggerBoth").checked;
		var description = id("triggerDescription").value.replace(/\t|\n/g, "");
		var format = id("triggerFormat").value;
		var categories = [];
		var tags = [];

		if (triggerSelect == "_create" && label.match(/^(m|s|b)$/)) {
			alert("You can not name a new trigger \"m\", \"s\", or \"b\".");
			return;
		}

		var triggerCategories = id("triggerCategories");
		for (i=1; i<triggerCategories.options.length; i++)
			if (triggerCategories.options[i].selected && triggerCategories.options[i].value)
				categories.push(triggerCategories.options[i].value);

		var triggerTags = id("triggerTags");
		for (i=1; i<triggerTags.options.length; i++)
			if (triggerTags.options[i].selected && triggerTags.options[i].value)
				tags.push(triggerTags.options[i].value);

		var obj = {
			"active": active,
			"default": active,
			"label": label,
			"type": type,
			"both": both,
			"description": description,
			"categories": categories,
			"tags": tags,
			"format": format
		};

		triggers[obj.label] = obj;
	} else {
		for (i in triggers) 
			if (i == triggerSelect) 
				delete triggers[i];
	}

	var trigger, output = "";
	for (i in triggers) {
		trigger = triggers[i];
		output = output + "active=B("+ ((trigger.active) ? "true" : "false")+ ")\tlabel=S("+ trigger.label+ ")\ttype=S("+ trigger.type+ ")\tboth=B("+ ((trigger.both) ? "true" : "false")+ ")\tdescription=S("+ trigger.description+ ")\tcategories=A("+ trigger.categories.join(",")+ ")\ttags=A("+ trigger.tags.join(",")+ ")\tformat=S("+ trigger.format.replace(/\t/g, "").replace(/\r?\n/g, "\\\\n")+ ")\n";
	}

	output = output.replace(/\n$/, "");

	id("ppfm_triggers").value = output;

	install();
}

function parseTriggerFormat() {
	if (!id("triggerSelect").value) return;

	var type = id("triggerType").value;

	var len = false;
	switch (type) {
		case "m": len = 140; break;
		case "s": len = 200; break;
	}

	var text = id("triggerFormat").value;
	text = text.trim().replace(/ +/g, " ");
	if (len)
		text = strip_tags(text);

	var output = id("triggerFormatOutput");

	text = text.replace(/\$link/g, link);
	text = text.replace(/\$slink/g, slink);

	var subject_out = subject;

	if (len) {
		var left = len - text.length
		if (left < 0) left = 0;

		if (text.length + ellipse.length + subject.length > len)
			subject_out = subject.substr(0, left - ellipse.length) + ellipse;
	}

	text = text.replace(/\$subject/g, subject_out);

	var body_out = body;

	if (len) {		
		left = len - text.length;
		if (left < 0) left = 0;

		if (text.length + ellipse.length + body.length > len)
			body_out = body.substr(0, left + ellipse.length -1) + ellipse;
	}

	text = text.replace(/\$body/, body_out);

	text = text.replace(/\n/g, "<br />");

	truncate(output);
	if (text) {
		output.appendChild(elementWithText("small", text.length + " character" + ((text.length==1) ? "" : "s")));
		output.appendChild(element("br"));
		output.innerHTML += text;
	} else {
		output.appendChild(node("As you construct your Trigger Format, this section will update in real time."));
	}
}

function updateTriggerInformation() {
	var triggerSelect = id("triggerSelect").value;
	var triggerCategories = id("triggerCategories");
	var triggerTags = id("triggerTags");

	id("triggerLabel").value = trigger[triggerSelect].label;
	id("triggerDescription").value = trigger[triggerSelect].description;
	id("triggerBoth").checked = trigger[triggerSelect].both;
	id("triggerFormat").value = trigger[triggerSelect].format;

	var triggerType = id("triggerType");
	var i, j = 0;
	for (i=0; i<triggerType.options.length; i++)
		if (triggerType.options[i].value == trigger[triggerSelect].type)
			triggerType.options[i].selected = true;

	for (i=0; i<triggerCategories.options.length; i++)
		for (j=0; j<trigger[triggerSelect].categories.length; i++)
			triggerCategories.options[i].selected = (triggerCategories.options[i].value == trigger[triggerSelect].categories[i]) ? true : false;

	parseTriggerFormat();
}

function element(type, id, className, cssText) { 
	var obj = document.createElement(type);
	if (id) obj.id = id
	if (className) obj.className = className;
	if (cssText) obj.style.cssText = cssText;
	return obj;
}
function elementWithText(type, text, id, className, cssText) {
	var obj = element(type, id, className, cssText);
	obj.appendChild(node(text));
	return obj;
}
function id(name) { return document.getElementById(name); }
function truncate(obj) { while (obj.childNodes.length > 0) obj.removeChild(obj.firstChild); }
function truncateUp(obj, len) { while (obj.childNodes.length > len) obj.removeChild(obj.lastChild); }
function truncateDown(obj, len) { while (obj.childNodes.length > len) obj.removeChild(obj.firstChild); }
function node(text) { return document.createTextNode(text); }
function strip_tags(str, allowed_tags) {
    // http://kevin.vanzonneveld.net
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Luke Godfrey
    
    var key = \'\', tag = \'\';
    var matches = allowed_array = [];
    var allowed_keys = {};
    
    // Build allowes tags associative array
    if (allowed_tags) {
        allowed_tags  = allowed_tags.replace(/[\<\> ]+/g, \'\');;
        allowed_array = allowed_tags.split(\',\');
        
        for (key in allowed_array) {
            tag = allowed_array[key];
            allowed_keys[\'<\' + tag + \'>\']   = true;
            allowed_keys[\'<\' + tag + \' />\'] = true;
            allowed_keys[\'</\' + tag + \'>\']  = true;
        }
    }
    
    // Match tags
    matches = str.match(/(<\/?[^>]+>)/gi);
    
    // Is tag not in allowed list? Remove from str! 
    for (key in matches) {
        // IE7 Hack
        if (!isNaN(key)) {
            tag = matches[key].toString();
            if (!allowed_keys[tag]) {
                // Looks like this is
                // reg = RegExp(tag, \'g\');
                // str = str.replace(reg, \'\');
                str = str.replace(tag, "");
            }
        }
    }
    
    return str;
}
/* ]]> */
</script>

<form method="post" action="options.php">
	<div>
		<div class="wrap">
			', wp_nonce_field('update-options'), '
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="page_options" value="ppfm_optionsUpdated, ppfm_apiUser, ppfm_reverifyKey, ppfm_ellipse, ppfm_triggers" />
			<input type="hidden" name="ppfm_optionsUpdated" value="1" />
			<input type="hidden" id="ppfm_triggers" name="ppfm_triggers" value="', htmlspecialchars($ppfm_triggers), '" />

			<h2>PingPressFM Settings</h2>

			<p>In order to get the ball rolling, you need a <a href="http://www.ping.fm">Ping.fm account</a>, a <a href="http://www.soldoutactivist.com/project-pingpressfm#get-my-key">user API key</a> (', (($ppfm_keyVerified) ? '<span style="color: #0A0;">verified</span>' : (($ppfm_apiUser) ? '<span style="color: #C30;">unverified</span>' : '<span style="color: #F00;">empty</span>')), '), and at least PHP 5.1.0 (', ((version_compare(PHP_VERSION, '5.1.0', '>=')) ? '<span style="color: #0A0;">verified</span>, '. phpversion(): '<span style="color: #F00;">'. phpversion(). '</span>'), ') with the CURL module (', ((function_exists('curl_init')) ? '<span style="color: #0A0;">verified</span>' : '<span style="color: #F00;">missing</span>'), ').</p>

			<p>For updates follow <a href="http://www.twitter.com/soldoutactivist">my twitter</a>, and please follow <a href="http://www.twitter.com/pingpressfm">PingPressFM\'s twitter</a> so I know roughly how many people are using the plugin. If you have a problem, please visit the plugin\'s <a href="http://getsatisfaction.com/soldoutactivist/products/soldoutactivist_pingpressfm">GetSatisfaction page</a>, but if you have comments, post to the <a href="http://www.soldoutactivist.com/project-pingpressfm">project page</a>.
			</p>

			<div style="background: #FFA; padding: 10px; margin: 10px 0 0 0;">
        <strong>There are some significant changes from the previous version that only affect upgrading users.</strong> First, this plugin is in a new folder, pingpressfm, as opposed to: pingpressfm2. So be sure to delete the folder, pingpressfm2, in your plugins folder or at least deactivate PingPressFM 2.1.4. Second, this version of the plugin uses a new database table, pingpressfm, instead of: pingpressfm2. Do not delete the old database table, I will release an update that will translate your old pings into the new database as well as delete the old settings. Please bear with me, this is my first WordPress plugin.
      </div>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						API Key<br />
						<small>(<a href="http://www.soldoutactivist.com/project-pingpressfm#get-my-key">How do I get my key?</a>)</small>
					</th>
					<td>
						<strong>Status: ', (($ppfm_keyVerified) ? '<span style="color: #0A0;">Verified</span>' : (($ppfm_apiUser) ? '<span style="color: #C30;">Unverified</span>' : '<span style="color: #F00;">Empty</span>')), '</strong><br />
						<input type="text" name="ppfm_apiUser" value="', get_option('ppfm_apiUser'), '" size="45" /><br />
						<label><input type="checkbox" name="ppfm_reverifyKey" value="1" /> I have changed my API key, reverify it, please!</label>

						<p><small><strong>As of the time of this documentation, 09/25/08, the Rate Limit is 3 pings per rolling 60 sixty seconds.</strong> So it is a good idea not to have more than 3 active triggers. For the latest information on the topic, you can read the <a href="http://groups.google.com/group/pingfm-developers/web/api-documentation">API Documentation</a>. Note that this plugin does not prevent you from having more than 3 active triggers, but someone at Ping.fm might ban your IP if you abuse the service with this plugin. I have received emails from him already about abuse from bad blogs which he blocked, so it <em>does</em> happen.</small></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						Ellipse
					</th>
					<td>
						Used when a variable is truncated during the formatting process. $slink and $link are never truncated.<br />
						<input type="text" id="ellipse" name="ppfm_ellipse" value="', $ellipse, '" size="4" /><br />
						<small>Default: ...</small>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						Triggers<br />
						<small>(<a href="http://www.soldoutactivist.com/project-pingpressfm#triggers">How do I set up custom triggers?</a>)</small>
					</th>
					<td id="triggerSetup">
						<h3>Please wait until the page completely loads.</h3>
						This verson of this plugin does not work with the following browsers: Internet Explorer 6.x, Safari 2.x, Opera 7.x - 9.27. If you have a newer version or a better broswer, it may be that JavaScript has been turned off. This plugin requires JavaScript. Please report seeing this to the <a href="http://getsatisfaction.com/soldoutactivist/products/soldoutactivist_pingpressfm">GetSatisfaction</a> page and include your broswer and its version.
					</td>
				</tr>
			</table>
			<p><input type="submit" name="Submit" value="', _e('Update Options &raquo;'), '" /></p>
		</div>
	</div>
</form>

<script type="text/javascript">
install();
</script>
';
}

if (!function_exists("htmlspecialchars_decode")) {
	function htmlspecialchars_decode($string,$style=ENT_COMPAT) {
		$translation = array_flip(get_html_translation_table(HTML_SPECIALCHARS,$style));
		if ($style === ENT_QUOTES) $translation['&#039;'] = '\'';
		return strtr($string,$translation);
	}
}
