<?php
/*
Plugin Name: Custom enhancements for St. Albert Further Education
Description: Custom code
Version: 1.2
Author: Vladimir Dudas
Author URI: http://www.dudas.ca/
*/

remove_filter('template_redirect', 'redirect_canonical'); 
    
add_filter( 'FHEE__EE_Register_CPTs__register_CPT__rewrite', 'my_custom_event_slug', 10, 2 );
add_filter( 'EES_Espresso_Events__process_shortcode__default_espresso_events_shortcode_atts', 'my_custom_sort_order', 10, 2);
add_filter( 'wpcf7_mail_components', 'filter_wpcf7_mail_components', 10, 3 );

// define the wpcf7_mail_components callback
function filter_wpcf7_mail_components( $components, $wpcf7_get_current_contact_form, $instance ) {
    // make filter magic happen here...
	$userid = preg_replace("/https?:\/\/[^\/]+\/contact-a-member\/\?(\d+)/",'$1',$_SERVER['HTTP_REFERER']);
	$auser = get_user_meta( $userid);
	$displayOnWebsite = $auser["display_on_website"][0]."";

	if (strpos($displayOnWebsite,"_main_email") > 0 && isset($auser["pmpro_bemail"])) 
	{
		$components['recipient'] = $auser["contact_name"][0]." <".$auser["pmpro_bemail"][0].">";
    }

    return $components;
};

add_shortcode('user_meta', 'user_meta_shortcode_handler');
/**
 * User Meta Shortcode handler
 * usage: [USER_META meta="first_name"]
 * @param  array $atts   
 * @param  string $content
 * @return string
 */
function user_meta_shortcode_handler($atts,$content=null){
	$userid = preg_replace("/^\/contact-a-member\/\?(\d+)/",'$1',$_SERVER["REQUEST_URI"]);
	return esc_html(get_user_meta($userid, $atts['meta'], true));
}

add_shortcode('member_alpha_listing', 'member_alpha_listing_shortcode_handler');
/**
 * User Meta Shortcode handler
 * usage: [member_alpha_listing]
 * @param  array $atts   
 * @param  string $content
 * @return string
 */
function member_alpha_listing_shortcode_handler($atts,$content=null){

	$groups = array();

	$users = get_users();

	// For each user, determine which business name we need to display
	foreach ($users as $user) {

		// Get the company name
		$meta = get_user_meta($user->ID, 'company', true);	
		$display = get_user_meta($user->ID, 'display_on_website', true);	

		if (!is_string($meta) || strlen($meta) == 0 || $meta == "" ||
			!is_string($display) || strlen($display) == 0 || $display == "") {
			continue;
		}

		// Figure out which bucket they're in
		$initial = $meta[0];

		// If the bucket doesn't exist, create one
		if (!isset($groups[$initial])) {
			$groups[$initial] = array();
		}

		// 
		$groups[$initial][$meta] = $user->ID;
	}

	// Sort our groupings
	ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

	// Make the jump bar
	$result = array_map(function ($letter) {$letter = strtoupper($letter); return "<a href=\"#members_$letter\">$letter</a>";}, array_keys($groups));

	$result = implode(" ", $result);

	echo $result;

	// For each group, display members in that bucket
	foreach($groups as $key => $group)
	{
		// Get out current letter
		$letter = strtoupper($key[0]);

		// Add header and navigation anchor
		echo "<div class=\"category-subsection\"><div class=\"h2\"><a name=\"members_$letter\">$letter</a></div>";

		// Sort the members by name
		ksort($group, SORT_NATURAL | SORT_FLAG_CASE);


		// For each member, display their information
		foreach($group as $member_name => $member_id)
		{
			do_action('furthered_format_member',$member_id);
		}

		echo "</div>";
	}
}



// add the filter

function my_custom_event_slug( $slug, $post_type ) {
    	if ( $post_type == 'espresso_events' ) {
    		$custom_slug = array( 'slug' => 'courses' );
    		return $custom_slug;
    	}
    	return $slug;
    }

function my_custom_sort_order($defaults)
{
	/*
        $default_espresso_events_shortcode_atts = array(
 91             'title' => NULL,
 92             'limit' => 10,
 93             'css_class' => NULL,
 94             'show_expired' => FALSE,
 95             'month' => NULL,
 96             'category_slug' => NULL,
 97             'order_by' => 'start_date',
 98             'sort' => 'ASC',
 99             'fallback_shortcode_processor' => FALSE
100         );
		 *
	 * */

	if ($defaults["order_by"] == "start_date")
		$defaults["order_by"] = "event_name";

	return $defaults;
}

//we have to put everything in a function called on init, so we are sure Register Helper is loaded
function add_selection_field($field_name, $label, $options, $profile="admins", $type = "select", $required = true)
{
	return new PMProRH_Field(
		$field_name,                   // input name, will also be used as meta key
		$type,                   // type of field
		array(
			"required" => $required,
			"label" => $label, // Display label
			"profile" => $profile, // User editable? Or profile group name to show it to
			"options"=> $options
		));

}

function add_yes_no_field($field_name, $label)
{
	return add_selection_field($field_name, $label,
		array(       // <option> elements for select field
			"" => "",       // blank option - cannot be selected if this field is required
			"yes" => "Yes",     // <option value="male">Male</option>
			"no" => "No"  // <option value="female">Female</option>
		));
}

function add_text_field($field_name, $label, $short_form = false, $profile="admins")
{
	return new PMProRH_Field(
		$field_name, // meta key
		$short_form ? "text" : "textarea", // type of field "text", "select"
		array( // Options for the control
			"required" => true,
			"label" => __($label, 'furthered'), // Display label
			"profile" => $profile // User editable? Or profile group name to show it to
		));
}

function my_pmprorh_init()
{
	$pmprorh_options["use_email_for_login"] = true;

	//don't break if Register Helper is not loaded
	if(!function_exists("pmprorh_add_registration_field"))
	{
		return false;
	}

	//define the fields
	$fields = array();

	/* --- EXAMPLES ---
    $fields[] = new PMProRH_Field(
        "company",              // input name, will also be used as meta key
        "text",                 // type of field
        array(
            "size"=>40,         // input size
            "class"=>"company", // custom class
            "profile"=>true,    // show in user profile
            "required"=>true    // make this field required
        ));
    $fields[] = new PMProRH_Field(
        "referral",                     // input name, will also be used as meta key
        "text",                         // type of field
        array(
            "label"=>"Referral Code",   // custom field label
            "profile"=>"admins"         // only show in profile for admins
        ));
    $fields[] = new PMProRH_Field(
        "gender",                   // input name, will also be used as meta key
        "select",                   // type of field
        array(
            "options"=>array(       // <option> elements for select field
                    "" => "",       // blank option - cannot be selected if this field is required
                "male"=>"Male",     // <option value="male">Male</option>
                "female"=>"Female"  // <option value="female">Female</option>
        )));
    */

	$fields[] = add_text_field(
		"existence",
		"How long has your organization existed in St. Albert?");

	$fields[] = add_text_field(
		"reference", // meta key
		"How did you hear about the Association?");

	$fields[] = add_text_field(
		"why", // meta key
		"Why do you want to be a member of the Association?");

	$fields[] = add_text_field(
		"gain_contribute",
		"What can you contribute to the Association?");

	$fields[] = add_selection_field(
		"classification",                   // input name, will also be used as meta key
		"How would you classify your organization?",
		array(       // <option> elements for select field
			"education" => __("School, College, University", 'furthered'),
			"business" => __("Business", 'furthered'),
			"nonprofit" => __("Non-profit (please provide certificate number below)", 'furthered'),
			"other" => __("Other", 'furthered')
		));

	$nonprofit_cert = add_text_field(
		"nonprofit_cert",
		"Non-profit Certificate number",
		true
	);

	$nonprofit_cert->depends = array(
		array("id" => "classification", "value" => "nonprofit")
	);
	$nonprofit_cert->required = false;

	$fields[] = $nonprofit_cert;

	$other_desc = add_text_field(
		"other_desc",
		"Please provide a description of your organization",
		true
	);

	$other_desc->depends = array(
		array("id" => "classification", "value" => "other")
	);
	$other_desc->required = false;

	$fields[] = $other_desc;

	$fields[] = add_text_field(
		"age_group", // meta key
		"What age group are you targeting?");

	// Children, Teens, Adult, Senior (multi select)

	$fields[] = add_text_field(
		"adult_percentage", // meta key
		"What percentage of your program participants are adults?",
		true);

	$fields[] = add_text_field(
		"primary_mandate", // meta key
		"What is your organization's mandate or purpose?");

	$educational_services = add_selection_field(
		"educational_services",
		"What educational services do you offer?",
		array(
			"courses" => __("Courses", 'furthered'),
			"workshops" => __("Workshops", 'furthered'),
			"individual" => __("Individually tailored (tutoring, one-on-one)", 'furthered'),
			"group" => __("Group sessions", 'furthered')
		), "admins", "select2");


	$fields[] = $educational_services;

	$fields[] = add_text_field(
		"affiliated_orgs", // meta key
		"Who are you affiliated with?");

	$fields[] = add_text_field(
		"educational_services_current", // meta key
		"What are your educational programs about?");

	$fields[] = add_text_field(
		"educational_services_future", // meta key
		"What might you offer in the future?");

	$fields[] = add_yes_no_field(
		"liability_insurance",
		"Do you hold liability insurance?");

	$fields[] = add_text_field(
		"safety_measures",
		"What safety measures do you have in place?");

	$fields[] = add_text_field(
		"qualified_instructors",
		"What are your instructor's qualifications?");

		$options = array(
			"display_company" => __("Organization / Business name", 'furthered'),
			"display_rep_name" => __("Name of Main representative", 'furthered'),
			"display_mailing_address" => __("Mailing address", 'furthered'),
			"display_bphone" => __("Main Contact phone", 'furthered'),
			"display_private_phone" => __("Home / Cell phone", 'furthered'),
			"display_main_email" => __("Account email address", 'furthered')
		);

	$display_selection = array();
	$display_selection[] = add_selection_field("display_on_website", __("Website",'furthered'), $options, true, "select2", true);
	$display_selection[] = add_selection_field("display_in_printed_calendar", __("Printed Calendar",'furthered'), $options, true, "select2", true);

	pmprorh_add_checkout_box("profile", __("Organization Information", "furthered"), "");
//	pmprorh_add_checkout_box("application_info", __("APPLICATION FOR MEMBERSHIP","furthered"),
//		__("Please fill in as much of the information as possible", 'furthered'));

	foreach($fields as $field)
		pmprorh_add_registration_field("application_info", $field);


	$mailingFields = array();
	$mailingFields[] = new PMProRH_Field("company", "text", array("label"=>__("Organization / Business name", 'furthered'), "profile"=>true, "required"=>true));
	$mailingFields[] = new PMProRH_Field("bfirstname", "text", array("label"=>__("Main Contact First Name", 'furthered'), "size"=>30, "profile"=>true, "required"=>true));
	$mailingFields[] = new PMProRH_Field("blastname", "text", array("label"=>__("Main Contact Last Name", 'furthered'), "size"=>30, "profile"=>true, "required"=>true));
	$mailingFields[] = new PMProRH_Field("user_url", "text", array("label"=>__("Website", 'furthered'), "profile"=>true, "required"=>true));
	$mailingFields[] = new PMProRH_Field("mailing_address1", "text", array("label"=>__("Address 1", 'furthered'), "size"=>40, "profile"=>true, "required"=>false));
	$mailingFields[] = new PMProRH_Field("mailing_address2", "text", array("label"=>__("Address 2", 'furthered'), "size"=>40, "profile"=>true, "required"=>false));
	$mailingFields[] = new PMProRH_Field("mailing_city", "text", array("label"=>__("City", 'furthered'), "size"=>40, "profile"=>true, "required"=>false));
	$mailingFields[] = new PMProRH_Field("mailing_state", "text", array("label"=>__("Province", 'furthered'), "size"=>10, "profile"=>true, "required"=>false));
	$mailingFields[] = new PMProRH_Field("mailing_zipcode", "text", array("label"=>__("Postal Code", 'furthered'), "size"=>10, "profile"=>true, "required"=>false));
	$mailingFields[] = new PMProRH_Field("mailing_country", "select", array("label"=>__("Country", 'furthered'), "profile"=>true, "required"=>false, "options"=> array("CA"=>"Canada")));
	$mailingFields[] = new PMProRH_Field("bphone", "text", array("label"=>__("Phone", 'furthered'), "profile"=>true, "required"=>true));
	$mailingFields[] = new PMProRH_Field("private_phone", "text", array("label"=>__("Home / Cell Phone", 'furthered'), "profile"=>true, "required"=>false));

	//add the fields into a new checkout_boxes are of the checkout page
	//pmprorh_add_checkout_box("mailing_address", __("Mailing Address", "furthered"), "");
	foreach($mailingFields as $field)
		pmprorh_add_registration_field("mailing_address", $field);

	add_alternate(1, "billing_financial", 'Billing / Financials');
	add_alternate(2, "newsletter_updates", 'Newsletter / Updates');
	add_alternate(3, "course_calendar", "Course Calendar Information");

	pmprorh_add_checkout_box("info_display", __("Information Display Preferences", "furthered"),
		__("Please select which information you would like published",'furthered'), 100);

	foreach($display_selection as $field)
			pmprorh_add_registration_field("info_display", $field);
}

/**
 * @param $index
 * @param $area
 * @param $area_label
 * @return mixed
 */
function add_alternate($index, $area, $area_label)
{
	$localLabel = __($area_label, "furthered");
	pmprorh_add_checkout_box($area, sprintf(__("Alternate Contact for %s", 'furthered'), $localLabel), "");
	foreach (getContactFields($index, $localLabel) as $field)
		pmprorh_add_registration_field($area, $field);
}

/**
 * @param $tablerow
 * @return array
 */
function getContactFields($index, $area)
{
	$tableRows = array();
	$tableRows[] = new PMProRH_Field(
		"use_main_address".$index, // meta key
		"select", // type of field "text", "select"
		array( // Options for the control
			"required" => true,
			"label" => sprintf(__("Use main contact for %s?", 'furthered'),$area), // Display label
			"options" => array("yes" => __("Yes",'furtered'), "no" => __("No, use the information below",'furthered')),
			"profile" => true // User editable? Or profile group name to show it to
		));

	$tableRows[] = new PMProRH_Field(
		"contact_name".$index, // meta key
		"text", // type of field "text", "select"
		array( // Options for the control
			"required" => false,
			"label" => sprintf(__("Contact name for %s",'furthered'), $area), // Display label
			"profile" => true,
			"depends" => array(array("id" => "use_main_address".$index, "value" => "no"))
		));

	$tableRows[] = new PMProRH_Field(
		"contact_email".$index, // meta key
		"text", // type of field "text", "select"
		array( // Options for the control
			"required" => false,
			"label" => sprintf(__("Contact e-mail for %s",'furthered'), $area),// Display label
			"profile" => true,
			"depends" => array(array("id" => "use_main_address".$index, "value" => "no"))
		));

	$tableRows[] = new PMProRH_Field(
		"contact_phone".$index, // meta key
		"text", // type of field "text", "select"
		array( // Options for the control
			"required" => false,
			"label" => sprintf(__("Contact phone for %s",'furthered'), $area), // Display label
			"profile" => true,
			"depends" => array(array("id" => "use_main_address".$index, "value" => "no"))
		));

	return $tableRows;
}

add_action("init", "my_pmprorh_init");

//add level cost text field to level price settings
function pmprosed_pmpro_membership_level_after_other_settings()
{
	$level_id = intval($_REQUEST['edit']);
	if($level_id > 0)
		$set_expiration_date = pmpro_getSetExpirationDate($level_id);
	else
		$set_expiration_date = "";
	?>
	<h3 class="topborder">Set Expiration Date</h3>
	<p>To have this level expire on a specific date, enter it below in YYYY-MM-DD format. <strong>Note:</strong> You must also set an expiration date above (e.g. 1 Year) which will be overwritten by the value below.</p>
	<table>
		<tbody class="form-table">
		<tr>
			<th scope="row" valign="top"><label for="set_expiration_date">Expiration Date:</label></th>
			<td>
				<input type="text" name="set_expiration_date" value="<?php echo esc_attr($set_expiration_date);?>" />
				<br /><small>YYYY-MM-DD format. Enter "Y" for current year, "Y2" for next year. M, M2 for current/next month.</small>
			</td>
		</tr>
		</tbody>
	</table>
	<?php
}
add_action("pmpro_membership_level_after_other_settings", "pmprosed_pmpro_membership_level_after_other_settings");

//save level cost text when the level is saved/added
function pmprosed_pmpro_save_membership_level($level_id)
{
	pmpro_saveSetExpirationDate($level_id, $_REQUEST['set_expiration_date']);			//add level cost text for this level
}
add_action("pmpro_save_membership_level", "pmprosed_pmpro_save_membership_level");

/*
	Function to replace Y and M/etc with actual dates
*/
function pmprosed_fixDate($set_expiration_date)
{
	$Y = date("Y", current_time('timestamp'));
	$Y2 = intval($Y) + 1;

	$M = date("m", current_time('timestamp'));
	if($M == 12)
		$M2 = "01";
	else
		$M2 = str_pad(intval($M) + 1, 2, "0", STR_PAD_LEFT);

	if (intval($M) >= 7)
	{
		$Y = $Y2;
		$Y2 = $Y2 + 1;
	}

	$searches = array("Y-", "Y2-", "M-", "M2-");
	$replacements = array($Y . "-", $Y2 . "-", $M . "-", $M2 . "-");

	$set_expiration_date = str_replace($searches, $replacements, $set_expiration_date);
	return $set_expiration_date;
}

/*
	Update expiration date of level at checkout.
*/
function pmprosed_pmpro_checkout_level($level)
{
	global $wpdb;

	//get discount code passed in
	if(!empty($_REQUEST['discount_code']))
		$discount_code = preg_replace("/[^A-Za-z0-9\-]/", "", $_REQUEST['discount_code']);

	if(!empty($discount_code))
		$discount_code_id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($discount_code) . "' LIMIT 1");
	else
		$discount_code_id = NULL;

	//does this level have a set expiration date?
	$set_expiration_date = pmpro_getSetExpirationDate($level->id, $discount_code_id);

	//check for Y
	if(strpos($set_expiration_date, "Y") !== false)
		$used_y = true;

	if(!empty($set_expiration_date))
	{
		//replace vars
		$set_expiration_date = pmprosed_fixDate($set_expiration_date);

		//how many days until expiration
		$todays_date = time();
		$time_left = strtotime($set_expiration_date) - $todays_date;
		if($time_left > 0)
		{
			$days_left = ceil($time_left/(60*60*24));

			//update number and period
			$level->expiration_number = $days_left;
			$level->expiration_period = "Day";

			return $level;	//stop
		}
		elseif(!empty($used_y))
		{
			$timestamp = strtotime($set_expiration_date);

			//add one year to expiration date
			$set_expiration_date = date("Y-m-d", mktime(0, 0, 0, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp) + 1));

			//how many days until expiration
			$time_left = strtotime($set_expiration_date) - $todays_date;
			$days_left = ceil($time_left/(60*60*24));

			//update number and period
			$level->expiration_number = $days_left;
			$level->expiration_period = "Day";

			return $level; //stop
		}
		else
		{
			//expiration already here, don't let people signup
			$level = NULL;

			return $level; //stop
		}
	}

	return $level;	//no change
}
add_filter("pmpro_checkout_level", "pmprosed_pmpro_checkout_level");

/*
	This function will save a the set expiration dates into wp_options.
*/
function pmpro_saveSetExpirationDate($level_id, $set_expiration_date, $code_id = NULL)
{
	if($code_id)
		$key = "pmprosed_" . $level_id . "_" . $code_id;
	else
		$key = "pmprosed_" . $level_id;

	update_option($key, $set_expiration_date);
}

/*
	This function will return the expiration date for a level or discount code/level combo
*/
function pmpro_getSetExpirationDate($level_id, $code_id = NULL)
{
	if($code_id)
		$key = "pmprosed_" . $level_id . "_" . $code_id;
	else
		$key = "pmprosed_" . $level_id;

	return get_option($key, "");
}


/*
	This next set of functions adds our field to the edit discount code page
*/
//add our field to level price settings
function pmprosed_pmpro_discount_code_after_level_settings($code_id, $level)
{
	$set_expiration_date = pmpro_getSetExpirationDate($level->id, $code_id);
	?>
	<table>
		<tbody class="form-table">
    		<tr>
    			<td>
            		<tr>
            			<th scope="row" valign="top"><label for="set_expiration_date">Expiration Date:</label></th>
            			<td>
            				<input type="text" name="set_expiration_date[]" value="<?php echo esc_attr($set_expiration_date);?>" />
            				<br /><small>YYYY-MM-DD format. Enter "Y" for current year, "Y2" for next year. M, M2 for current/next month. Be sure to set an expiration date above as well.</small>
            			</td>
            		</tr>
    			</td>
    		</tr>
		</tbody>
	</table>
	<?php
}
add_action("pmpro_discount_code_after_level_settings", "pmprosed_pmpro_discount_code_after_level_settings", 10, 2);

//save level cost text for the code when the code is saved/added
function pmprosed_pmpro_save_discount_code_level($code_id, $level_id)
{
	$all_levels_a = $_REQUEST['all_levels'];							//array of level ids checked for this code
	$set_expiration_date_a = $_REQUEST['set_expiration_date'];			//expiration dates for levels checked

	if(!empty($all_levels_a))
	{
		$key = array_search($level_id, $all_levels_a);				//which level is it in the list?
		pmpro_saveSetExpirationDate($level_id, $set_expiration_date_a[$key], $code_id);
	}
}
add_action("pmpro_save_discount_code_level", "pmprosed_pmpro_save_discount_code_level", 10, 2);

/*
Function to add links to the plugin row meta
*/
function pmprosed_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-set-expiration-dates.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plugins-on-github/pmpro-expiration-date/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprosed_plugin_row_meta', 10, 2);

/*
	Update expiration text on levels page.
*/
function pmprosed_pmpro_level_expiration_text($expiration_text, $level)
{
	$set_expiration_date = pmpro_getSetExpirationDate($level->id);

	if(!empty($set_expiration_date))
	{
		$set_expiration_date = pmprosed_fixDate($set_expiration_date);
		$expiration_text = sprintf(__("Membership starts on Jan 1st and expires on Dec 31st of %s.", 'furthered'), date("Y", strtotime($set_expiration_date, current_time('timestamp'))));
	}

	return $expiration_text;
}
add_filter('pmpro_level_expiration_text', 'pmprosed_pmpro_level_expiration_text', 10, 2);

function pmpro_custom_default_country(){
	return 'CA';
}
add_filter('pmpro_default_country', 'pmpro_custom_default_country');

function add_billing_fields_to_profile()
{
	//check for register helper
	if(!function_exists("pmprorh_add_registration_field"))
		return;

	// Update the title of our additional fields
	//define the fields
	$fields = array();
	$fields[] = new PMProRH_Field("pmpro_baddress1", "text", array("label"=>"Address 1", "size"=>40, "profile"=>true, "required"=>false));
	$fields[] = new PMProRH_Field("pmpro_baddress2", "text", array("label"=>"Address 2", "size"=>40, "profile"=>true, "required"=>false));
	$fields[] = new PMProRH_Field("pmpro_bcity", "text", array("label"=>"City", "size"=>40, "profile"=>true, "required"=>false));
	$fields[] = new PMProRH_Field("pmpro_bstate", "text", array("label"=>"Province", "size"=>10, "profile"=>true, "required"=>false));
	$fields[] = new PMProRH_Field("pmpro_bzipcode", "text", array("label"=>"Postal Code", "size"=>10, "profile"=>true, "required"=>false));
	$fields[] = new PMProRH_Field("pmpro_bcountry", "select", array("label"=>"Country", "profile"=>true, "required"=>false, "options"=> array("CA"=>"Canada")));
	$fields[] = new PMProRH_Field("pmpro_bphone", "text", array("label"=>"Phone", "size"=>40, "profile"=>true, "required"=>false));

	//add the fields into a new checkout_boxes are of the checkout page
	foreach($fields as $field)
		pmprorh_add_registration_field("only", $field);
}
add_action("init", "add_billing_fields_to_profile");

/*
add_filter ('the_content', 'my_remove_event_tickets', 100 );
// remove tickets
function my_remove_event_tickets( $content ) {
	if ( 'espresso_events' == get_post_type() && is_singular() && !post_password_required() ) {
		remove_filter( 'the_content', array( 'EED_Event_Single', 'event_tickets' ), 120 );
		remove_filter( 'the_content', array( 'EED_Event_Single', 'event_datetimes' ), 110 );
		add_filter( 'the_content', 'my_add_event_tickets', 121);
	}
	return $content;
}
// add tickets after the content
function my_add_event_tickets( $content ) {
	return $content . EEH_Template::locate_template( 'content-espresso_events-tickets.php' ) . EEH_Template::locate_template( 'content-espresso_events-datetimes.php' );
}
*/

add_action( 'init', 'register_my_taxonomies', 0 );

function register_my_taxonomies() {

	register_taxonomy('espresso_event_categories', 'user', array(
		'public'		=>true,
		'single_value' => false,
		'show_admin_column' => true,
		'labels'		=>array(
			'name'						=>'Course Categories',
			'singular_name'				=>'Course Category',
			'menu_name'					=>'Course Categories',
			'search_items'				=>'Search Course Categories',
			'popular_items'				=>'Popular Course Categories',
			'all_items'					=>'All Course Categories',
			'edit_item'					=>'Edit Course Category',
			'update_item'				=>'Update Course Category',
			'add_new_item'				=>'Add New Course Category',
			'new_item_name'				=>'New Course Category Name',
			'separate_items_with_commas'=>'Separate Course Categories with commas',
			'add_or_remove_items'		=>'Add or remove Course Categories',
			'choose_from_most_used'		=>'Choose from the most popular Course Categories',
		),
		'rewrite'		=>array(
			'with_front'				=>true,
			'slug'						=>'members/courses',
		),
		'capabilities'	=> array(
			'manage_terms'				=>'edit_users',
			'edit_terms'				=>'edit_users',
			'delete_terms'				=>'edit_users',
			'assign_terms'				=>'read',
		),
	));
}

function ee_change_ticket_messaging_registration( $translated, $original, $domain ) {
	$strings = array(
		'Available Tickets' => 'Available Registrations',
		'Ticket Details' => 'Registration Details',
		'Event' => 'Course',
		'Events' => 'Courses',
		'Upcoming Events' => 'Upcoming Courses',
		'Event Location' => 'Course Location',
		'Ticket' => 'Registration',
		'Ticket' => 'Registration',
		'Tickets' => 'Registrations',
		'Ticket Sale Dates' => 'Registration Sale Dates',
		'This ticket is required and must be purchased.' => 'Course registration is required and must be purchased.',
		'Please note that a maximum number of %d tickets can be purchased for this event per order.' => 'Please note that a maximum number of %d registrations can be purchased for this course per order.',
		'The dates when this ticket is available for purchase.' => 'The dates when this registration is available for purchase.',
		'This ticket allows access to the following event dates and times. â€œRemainingâ€� shows the number of this ticket type left:' => 'This registration allows access to the following course dates and times. â€œRemainingâ€� shows the number of this registration type left:',
		'This Ticket<br/>Sold' => 'This Registration<br/>Sold',
		'This Ticket<br/>Left' => 'This Registration<br/>Left',
		'Total Tickets<br/>Sold' => 'Total Registrations<br/>Sold',
		'You need to select a ticket quantity before you can proceed.' => 'You need to select a quantity before you can proceed.',
		'No tickets were added for the event.' => 'No registrations were added for the course.',
		'Ticket Name and Description' => 'Course Name and Description',
		'The following checkboxes allow you to use the above information for only the selected additional tickets/attendees.' => 'The following checkboxes allow you to use the above information for only the selected additional registrants/attendees.',
		'Add to event cart' => 'Add to Course Cart',
		'View Event Cart' => 'View Course Cart',
		'Event Cart' => 'Course Cart',
		'Event Espresso' => 'Course Manager',
		'The number of tickets that can be purchased per transaction (if available).' => '',
		'Event Date Ticket Uses' => '',
		'The number of separate event datetimes (see table below) that this ticket can be used to gain admittance to.' => '',
		'Admission is always one person per ticket.' => 'Admission is always one person per registration.',
		'Return to Events List' => 'Return to Course List'
	);
	
	if ( isset( $strings[$original] ) ) {
		$translations = get_translations_for_domain( $domain );
		$translated = $translations->translate( $strings[$original] );
	}
	return $translated;
}

add_filter( 'gettext', 'ee_change_ticket_messaging_registration', 10, 3 );
	//* Additional changes to messaging for ticket to registration

add_action( 'FHEE__ticket_selector_chart_template__ticket_details_price_breakdown_heading', 'ee_additional_change_ticket_messaging_registration_a' );

function ee_additional_change_ticket_messaging_registration_a() {
	return 'Registration Price Breakdown';
}
	//* Additional changes to messaging for ticket to registration
add_action( 'FHEE__ticket_selector_chart_template__ticket_details_event_access_message', 'ee_additional_change_ticket_messaging_registration_b' );
function ee_additional_change_ticket_messaging_registration_b() {
	return 'This registration allows access to the following Course dates and times.';
}
//* Additional changes to messaging for ticket to registration
add_action( 'FHEE__registration_page_attendee_information__attendee_info_not_required_pg', 'ee_additional_change_ticket_messaging_registration_c' );
function ee_additional_change_ticket_messaging_registration_c() {
	return 'This registration type does not require any information for additional attendees, so attendee #1\'s information will be used for registration purposes.';
}
//* Additional changes to messaging for ticket to registration
add_action( 'FHEE__registration_page_attendee_information__auto_copy_attendee_pg', 'ee_additional_change_ticket_messaging_registration_d' );
function ee_additional_change_ticket_messaging_registration_d() {
	return 'The above information will be used for any additional registrants/attendees.';
}
//* Additional changes to messaging for ticket to registration
add_action( 'FHEE__ticket_selector_chart_template__ticket_details_total_price', 'ee_additional_change_ticket_messaging_registration_e' );
function ee_additional_change_ticket_messaging_registration_e() {
	return 'Total Price';
}	
		?>
