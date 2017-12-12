<?php
/*
  Plugin Name: wp-webdesign-catalog
  Plugin URI: http://nexusthemes.com
  Description: wp-webdesign-catalog
  Version: 1.0.0
  Author: NexusThemes
  Author URI: http://nexusthemes.com
*/

// hint for ourselves how to deploy new release;
// step 1) increase the version number
// step 2) zip
// step 3) remove .git folder
// step 4) deploy zip in /partners/nexus/9/www/wp-update-server/packages

if ($_REQUEST["nxspartnerlogintoken"] != "")
{
	return;
}
if (function_exists("wdc_lookuptable_getlookup_v2"))
{
	// required for first time activation of the plugin...
	return;
}
// used to ensure no framework is loaded on the studios
add_filter("wdc_bypassall", "wp_webdesign_catalog_bypassall");
function wp_webdesign_catalog_bypassall($result)
{
	return true;
}

// ----

$plugin_path = plugin_dir_path( __FILE__ );
// Include updater logic
require_once($plugin_path . '/thirdparty/plugin-update-checker/plugin-update-checker.php');
$MyUpdateChecker = PucFactory::buildUpdateChecker
(
	'http://wpupdates.nexus.c1.us-e1.nexusthemes.com/wp-update-server/?action=get_metadata&slug=wp-webdesign-catalog', //Metadata URL.
	__FILE__, //Full path to the main plugin file.
	//Plugin slug. Usually it's the same as the name of the directory.
	'wp-webdesign-catalog'
);

// -----

require_once("wp-webdesign-catalog-functions.php");;
require_once("wp-webdesign-catalog-modelmanager.php");
require_once("wp-webdesign-catalog-widgets.php");
require_once("wp-webdesign-catalog-shortcodes.php");
// require_once("wp-webdesign-catalog-customizer.php");

function wp_webdesign_catalog_getbusinessrules()
{
	// todo: can / should be derived from a model instead of being hardcoded ...
	$result = array
	(
	/*
		array
		(
			"type" => "busruleurl",
			"operator" => "template",
			"p1" => "/virtual-slug/*-{{humanid@schema}}",
		),
		*/
	);
	
	return $result;
}

function wp_webdesign_catalog_query_vars($vars) 
{
	// lets take it into consideration
	$templateproperties = wdc_gettemplateproperties();
	if ($templateproperties["lastmatchingrule"] == "busruleurl")
	{
		$wdc_g_businesssite_didoverride = "true";
  	array_push($vars, 'nxsvirtual');
  }

  return $vars;
}
add_filter("query_vars", "wp_webdesign_catalog_query_vars");

function wp_webdesign_catalog_the_posts($result, $args)
{
	global $wdc_gl_theposts_count;
	$wdc_gl_theposts_count++;
	
	global $wp,$wp_query;
	global $wdc_g_businesssite_didoverride;
	if (is_admin()) { return $result; }
	if (wdc_iswebmethodinvocation()) { return $result; }
		
	if ($wdc_g_businesssite_didoverride === "true") { return $result; }

	if (!is_main_query()) { return $result; }
	
	$wdc_g_businesssite_didoverride = "true";

	// lets take it into consideration
	$templateproperties = wdc_gettemplateproperties();
	if ($templateproperties["lastmatchingrule"] == "busruleurl")
	{
		$url_fragment_variables = $templateproperties["url_fragment_variables"];
		
		// intercept it
		$schema = "wdc_vtemplate";
		$isvirtual = true;
		
		// apparently there's a match;
		// mimic a post by creating a virtual post
		
		// derived the seo title
		global $wdc_g_modelmanager;
		$title = $wdc_g_modelmanager->wpseo_title();	
		$excerpt = "";	// intentionally left blank; not practical to fill this
		
		$rightnow = current_time('mysql');
		$post_date = $rightnow;
		$post_date_gmt = $rightnow;
		$post_modified = $rightnow;
		$post_modified_gmt = $rightnow;
		
		$result = array();
		
		$foundmatch = true;

		//$wp_query->is_wdc_portfolio = true;
		$wp_query->is_singular = true;
		$wp_query->is_page = true;
		$wp_query->is_404 = false;
		$wp_query->is_attachment = false;
		$wp_query->is_archive = false;
		unset($wp_query->query_vars["error"]);
		if ($wp_query->queried_object != NULL)
		{
			$wp_query->queried_object->term_id = -1;
			$wp_query->queried_object->name = $schema;
		}
		
		$newpost = new stdClass;
		
		$newpost->ID = -999001;
		$newpost->post_author = 1;
		$newpost->post_name = "slug123";	// slug of current uri basically
		$newpost->guid = "test guid";
		$newpost->post_title = $title;
		$newpost->post_excerpt = $excerpt;
		$newpost->to_ping = "";
		$newpost->pinged = "";
		$newpost->post_content = $content;
		$newpost->post_status = "publish";
		$newpost->comment_status = "closed";
		$newpost->ping_status = "closed";
		$newpost->post_password = "";
		$newpost->comment_count = 0;
		$newpost->post_date = $post_date;
		$newpost->filter = "raw";
		$newpost->post_date_gmt = $post_date_gmt;	// current_time('mysql',1);
		$newpost->post_modified = $post_modified;
		$newpost->post_modified_gmt = $post_modified_gmt;
		$newpost->post_parent = 0;
		$newpost->post_type = $schema;
		
		$wp_query->posts[0] = $newpost;
		$wp_query->found_posts = 1;	 
		$wp_query->max_num_pages = 1;
			
		$result[]= $newpost;
		
		//
		
		
		// there can/may be only one match
	}
	
	return $result;
}
add_filter("the_posts", "wp_webdesign_catalog_the_posts", 1000, 2);

// ---

function wdc_string_convertkeyvaluestokeyvaluequotestring($keyvalues)
{
	$result = "";
	$isfirst = true;
	foreach ($keyvalues as $key => $val)
	{
		if ($isfirst === false)
		{
			$result .= " ";
		}
		else
		{
			$isfirst = false;
		}
		$result .= "{$key}='{$val}'";
	}
	return $result;
}

function wdc_footer()
{
	?>
	<style>
		.wdc-item
		{
			background-color: #EEE;
			margin: 5px;
			padding: 5px;
		}
	</style>
	<?php
}
add_action("wp_footer", "wdc_footer");
add_action("admin_footer", "wdc_footer"); 

function wdc_init()
{
	
	
}
add_action("init", "wdc_init");

function wdc_admin_init()
{
	if ($_REQUEST["wdc"] != "initialize")
	{
		global $wdc_g_modelmanager;
		$r = $wdc_g_modelmanager->gettaxonomypropertiesofallmodels(array("singularschema" => "nxs.nexusthemes.itemmeta"));
		if (count($r) == 0)
		{
			function wdc_data_load_required() 
			{
				$class = 'notice notice-error';
				
				$url = wdc_get_home_url() . "wp-admin/options-general.php?page=wdc-unique-identifier&wdc=initialize";
				
				// $url = wdc_geturlcurrentpage();
				$url = wdc_addqueryparametertourl_v2($url, "wdc", "initialize");
				$message = "Webdesign Catalog; please click <a target='_blank' href='$url'>here</a> to initialize the catalog plugin (this will download the catalog items to your site)";
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message); 
			}
			add_action( 'admin_notices', 'wdc_data_load_required' );
		}
		else
		{
			// echo "gevonden; " . count($r) . " rows";
		}
	}
}
add_action("admin_init", "wdc_admin_init");

function wdc_my_plugin_menu() 
{
	add_options_page( 'My Plugin Options', 'Webdesign Catalog', 'manage_options', 'wdc-unique-identifier', 'wdc_plugin_options' );
	
	/*
	 add_menu_page(
        __( 'Custom Menu Title', 'textdomain' ),
        'Web Design Catalog',
        'manage_options',
        'wp-webdesign-catalog/wdc-admin.php',
        '',
        plugins_url( 'myplugin/images/icon.png' ),
        6
    );
	*/
}
add_action( 'admin_menu', 'wdc_my_plugin_menu' );

function wdc_plugin_options() 
{
	echo "<div class='wrap'>";
	
	if ( !current_user_can( 'manage_options' ) )  
	{
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	
	if ($_REQUEST["wdc"] == "initialize")
	{
		// fetch data
		global $wdc_g_modelmanager;
		$wdc_g_modelmanager->enableretrieval();
		$wdc_g_modelmanager->cachebulkmodels("nxs.nexusthemes.itemmeta");
		$wdc_g_modelmanager->cachebulkmodels("nxs.nexusthemes.itemmetaidsbybusinesstype");
		$wdc_g_modelmanager->cachebulkmodels("nxs.business.businesstype");
		$wdc_g_modelmanager->disableretrieval();
		
		// 
		$url = wdc_get_home_url() . "wp-admin/options-general.php?page=wdc-unique-identifier";
		
		echo "Finished loading :) Click <a href='$url'>here</a> to continue";
	}
	else
	{
		$next_url = wdc_geturlcurrentpage();
		$next_url = wdc_addqueryparametertourl_v2($next_url, "wdc-after-theme-selected", "true");
		
		function page_tabs( $current = 'first' ) 
		{
	    $tabs = array(
	        'first'   => __( 'Specific Design', 'plugin-textdomain' ), 
	        'second'  => __( 'Designs by businesstype', 'plugin-textdomain' )
	    );
	    $html = '<h2 class="nav-tab-wrapper">';
	    foreach( $tabs as $tab => $name )
	    {
        $class = ( $tab == $current ) ? 'nav-tab-active' : '';
        $page = $_REQUEST["page"];
        $html .= '<a class="nav-tab ' . $class . '" href="?page='. $page . '&tab=' . $tab . '">' . $name . '</a>';
	    }
	    $html .= '</h2>';
	    echo $html;
		}
		
		$tab = ( ! empty( $_GET['tab'] ) ) ? esc_attr( $_GET['tab'] ) : 'first';
		
		?>
		<h1>Webdesign catalog</h1>
		<?php page_tabs( $tab ); ?>
		<?php if ( $tab == 'first' ) 
		{
			?>
			<p>
				At any place in your website you can render a specific design of a website (a list of the available ids can be found <a target='_blank' href='https://nexusthemes.com/webdesigner-catalog/#themeids'>here</a>), like so;<br />
				<br />
			  <?php echo do_shortcode("[wdc type=theme id=263 template=thumb-linked next_text='Next' next_url='{$next_url}' back_text='Back']"); ?>
			  <br />
				Use the following shortcode to render a single design:<br />
				<br />
			  
			  <textarea style="min-width: 50vw; min-height: 150px;" class="js-copytextarea2">
			  	
	[wdc type=theme id=263 template=thumb-linked next_text='Next' next_url='<?php echo $next_url; ?>' back_text='Back']	
			  </textarea> 
			  <br />
			  <br />
			  <button class="js-textareacopybtn2" style="vertical-align:top;">Copy To Clipboard</button><br />
			  <br />
			  <script>
			  	//
			  	var copyTextareaBtn = document.querySelector('.js-textareacopybtn2');
					copyTextareaBtn2.addEventListener('click', function(event) {
					  var copyTextarea = document.querySelector('.js-copytextarea2');
					  copyTextarea.select();
					  try {
					    var successful = document.execCommand('copy');
					    var msg = successful ? 'successful' : 'unsuccessful';
					    console.log('Copying text command was ' + msg);
					  } catch (err) {
					    console.log('Oops, unable to copy');
					  }
					});
					//
			  </script>  
			</p>
		<?php 
		} else if ( $tab == 'second' ) 
		{
			?>
			<p>
				At any place in your site you can render a list of designs within a specific businesstype (for a list of the valid ids, see <a target='_blank' href='https://nexusthemes.com/webdesigner-catalog/#businesstypeids'>here</a>)<br />
				<br />
				<?php echo do_shortcode("[wdc_items id=631 next_text='Next' next_url='{$next_url}' back_text='Back' template='default.inlist']"); ?>
				 <br />
				Use the following shortcode to render:<br />
				<br />
			  <textarea style="min-width: 50vw; min-height: 150px;" class="js-copytextarea1">
			  	
	[wdc_items id="631" next_text="Next" next_url="<?php echo $next_url; ?>" back_text="Back" template="default.inlist"]	
			  </textarea> 
			  <br />
			  <br />
			  <button class="js-textareacopybtn1" style="vertical-align:top;">Copy To Clipboard</button><br />
			  <br />
			  <script>
			  	//
			  	var copyTextareaBtn = document.querySelector('.js-textareacopybtn1');
					copyTextareaBtn1.addEventListener('click', function(event) {
					  var copyTextarea = document.querySelector('.js-copytextarea1');
					  copyTextarea.select();
					  try {
					    var successful = document.execCommand('copy');
					    var msg = successful ? 'successful' : 'unsuccessful';
					    console.log('Copying text command was ' + msg);
					  } catch (err) {
					    console.log('Oops, unable to copy');
					  }
					});
					//
			  </script>
			  
			  <!-- -->
			  
			  <br />
				<hr />
				<br />
	
			</p>
			<?php
		}
	}
	
	echo "</div>";
}