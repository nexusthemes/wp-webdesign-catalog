<?php

function wp_webdesign_catalog_sc_embed($attributes, $content = null, $name='') 
{
	extract($attributes);
	
	$args = array
	(
		"render_behaviour" => "code",
	);
	// blend the parameters given
	$args = array_merge($args, $attributes);
	
	$renderresult = wdc_widgets_embed_render_htmlvisualization($args);
	return $renderresult["html"];
}
add_shortcode('nxsembed', 'wp_webdesign_catalog_sc_embed');

function wdc_sc_item($attributes, $content = null, $name='') 
{
	$warnings = array();

	$id = $attributes["id"];
	if ($id == "")
	{
		$id = $_REQUEST["itemmeta_id"];
		if ($id == "")
		{
			$id = $_REQUEST["id"];
		}
	}
	if ($id == "")
	{
		$warnings[] = "shortcode error; specify an id attribute specified or add an itemmeta_id query parameter to the url or add an id query parameter to the url. Example would be id=514 (for list of values, see <a target='_blank' href='https://nexusthemes.com/webdesigner-catalog/#themeids'>here</a>)";
	}
	
	if ($id != "")
	{
		
		global $wdc_g_modelmanager;
		$lookup = $wdc_g_modelmanager->getmodeltaxonomyproperties(array("modeluri" => "{$id}@nxs.nexusthemes.itemmeta"));

		$template = $attributes["template"];
		$next_text = $attributes["next_text"];
		$next_url = $attributes["next_url"];
		$back_text = $attributes["back_text"];

		// fallback handling
		if (true)
		{
			if ($template == "")
			{
				$template = "default";
				$warnings[] = "template attribute not specified, using template='{$template}'";
			}
			
			if ($next_text == "")
			{
				$next_text = "Next";
				$warnings[] = "next_text attribute not specified, using next_text='{$next_text}'";
			}
			
			if ($next_url == "")
			{
				$next_url = wdc_geturlcurrentpage();
				$warnings[] = "next_url attribute not specified, using next_url='{$next_url}'";
			}
			
			if ($back_text == "")
			{
				$back_text = "Back";
				$warnings[] = "back_text attribute not specified, using back_text='{$back_text}'";
			}
		}
		
		//
		
		if ($template == "default")
		{
			ob_start();
			?>
			<a href="{{preview_url}}">
			<h1>{{title}}</h1>
			<img src="{{preview_image}}" />
			</a>
			<?php
			$template_html = ob_get_clean();
		}
		else if ($template == "default.inlist")
		{
			ob_start();
			?>
			<a href="{{preview_url}}">
			<h1>Layout {{repeater.indexplusone}}</h1>
			<img src="{{preview_image}}" />
			</a>
			<?php
			$template_html = ob_get_clean();
		}
		else if ($template == "thumb")
		{
			ob_start();
			?>
			<img src="{{preview_image}}" />
			<?php
			$template_html = ob_get_clean();
		}
		else if ($template == "thumb-linked")
		{
			ob_start();
			?>
			<a href="{{preview_url}}">
			<img src="{{preview_image}}" />
			</a>
			<?php
			$template_html = ob_get_clean();
		}
		else if ($template == "thumb-front")
		{
			ob_start();
			?>
			<a href="{{preview_url}}">
			<img src="{{preview_image}}" />
			</a>
			<?php
			$template_html = ob_get_clean();
		}
		else
		{
			$template_html = $template;
		}
		
		$back_url = wdc_geturlcurrentpage();
		
		// pimp the lookups based upon the settings
		$lookup["preview_url"] = wdc_addqueryparametertourl_v2($lookup["preview_url"], "catalogitem_personalize", "c1");	
		$lookup["preview_url"] = wdc_addqueryparametertourl_v2($lookup["preview_url"], "next_text", $next_text);
		$lookup["preview_url"] = wdc_addqueryparametertourl_v2($lookup["preview_url"], "next_url", $next_url);
		$lookup["preview_url"] = wdc_addqueryparametertourl_v2($lookup["preview_url"], "itemmeta_id", $id);
		$lookup["preview_url"] = wdc_addqueryparametertourl_v2($lookup["preview_url"], "itemtitle", $lookup["title"]);
		$lookup["preview_url"] = wdc_addqueryparametertourl_v2($lookup["preview_url"], "back_text", $back_text);
		$lookup["preview_url"] = wdc_addqueryparametertourl_v2($lookup["preview_url"], "back_url", $back_url);
		
		if (isset($attributes["lookup"]))
		{
			$lookup = array_merge($lookup, $attributes["lookup"]);
		}
		
		// append additional parameters to the return url
		$metadata = array("template_html" => $template_html);
		$fields = array("template_html");
		$prefixtoken = "{{";
		$postfixtoken = "}}";
		
		$metadata = wdc_filter_translategeneric($metadata, $fields, $prefixtoken, $postfixtoken, $lookup);
		
		$result = $metadata["template_html"];
		
		$result = do_shortcode($result);
		$result = "<div class=\"wdc-item\">" . $result . "</div>";
	}
	
	if (is_user_logged_in())
	{
		if (count($warnings) > 0)
		{
			$result .= "<div class=\"wdc-errs\">";
			foreach ($warnings as $warning)
			{
				$result .= "<div class=\"wdc-err\">{$warning}</div>";
			}
			$result .= "</div>";
		}
	}
	
	return $result;
}
add_shortcode("wdc_item", "wdc_sc_item");

function wdc_sc_items($attributes, $content = null, $name='') 
{
	$warnings = array();
	
	$next_text = $attributes["next_text"];
	$next_url = $attributes["next_url"];
	$back_text = $attributes["back_text"];
	
	$id = $attributes["id"];
	
	if ($id == "")
	{
		$id = $_REQUEST["id"];
		if ($id == "")
		{
			$id = 631;	// ultimate fallback; 631 = plumber
			$warnings[] = "No id specified, using fallback id={$id} (for a complete list of valid ids, see <a target='_blank' href='https://nexusthemes.com/webdesigner-catalog/#businesstypeids'>here</a>)";
		}
	}
	
	$template = htmlspecialchars_decode($attributes["template"]);
	if ($template == "")
	{
		$template = "default.inlist";
		$warnings[] = "No template attribute specified (example are template=default.inlist, template=default, template=customhtml)";
	}
	
	if ($id != "")
	{
		global $wdc_g_modelmanager;
		
		if ($id == "*")
		{
			$itemmeta_ids = array();
			$entirecatalog = $wdc_g_modelmanager->gettaxonomypropertiesofallmodels(array("singularschema" => "nxs.nexusthemes.itemmeta"));
			foreach ($entirecatalog as $catalogitem)
			{
				$itemmeta_ids[] = $catalogitem["nxs.nexusthemes.itemmeta_id"];
			}
		}
		else
		{
			$lookup = $wdc_g_modelmanager->getmodeltaxonomyproperties(array("modeluri" => "{$id}@nxs.nexusthemes.itemmetaidsbybusinesstype"));
			if ($lookup == null)
			{
				$warnings []= "No businesstype found with id $id";
			}
		
			$itemmeta_ids = explode(";", $lookup["nxs.nexusthemes.itemmeta_ids"]);
		}
		
		$result .= "<style>";
		$result .= ".wdc-item img { max-width: 350px; }";
		$result .= ".wdc-items { display: flex; flex-wrap: wrap; }";
		$result .= "</style>";
		
		$result .= "<div class=\"wdc-items\">";
		$repeaterindex = -1;
		
		foreach ($itemmeta_ids as $itemmeta_id)
		{
			$repeaterindex++;
			$repeaterindexplusone = $repeaterindex + 1;
			
			$pieces = explode("@", $itemmeta_id);
			$id = $pieces[0];
			
			
			$result .= wdc_sc_item
			(
				array
				(
					"id" => $id, 
					"lookup" => array
					(
						"repeater.index" => $repeaterindex, 
						"repeater.indexplusone" => $repeaterindexplusone
					),
					"template" => $template,
					"next_text" => $next_text,
					"next_url" => $next_url,
					"back_text" => $back_text,
				), 
				null, 
				""
			);
		}
		$result .= "</div>";
	}
	
	if (is_user_logged_in())
	{
		if (count($warnings) > 0)
		{
			$result .= "<div class=\"wdc-errs\">";
			foreach ($warnings as $warning)
			{
				$result .= "<div class=\"wdc-err\">{$warning}</div>";
			}
			$result .= "</div>";
		}
	}
	
	return $result;
}
add_shortcode("wdc_items", "wdc_sc_items");

function wdc_sc_businesstypes($attributes, $content = null, $name='') 
{
	$warnings = array();
	
	$next_url = $attributes["next_url"];
	if ($next_url == "")
	{
		$warnings []= "shortcode warning; next_url attribute not specified";
	}
	
	$result = "<div>business types</div>";
	
	global $wdc_g_modelmanager;
	$entries = $wdc_g_modelmanager->gettaxonomypropertiesofallmodels(array("singularschema" => "nxs.nexusthemes.itemmetaidsbybusinesstype"));
	$n = -1;
	foreach ($entries as $entry)
	{
		$n++;

		$btid = $entry["nxs.business.businesstype_id"];
		$lookup = $wdc_g_modelmanager->getmodeltaxonomyproperties(array("modeluri" => "{$btid}@nxs.business.businesstype"));
		
		$next_url = $attributes["next_url"];
		$next_url= wdc_addqueryparametertourl_v2($next_url, "id", $btid);
		
		$title = $lookup["title"];
		
		$result .= "<div class='nxs-wdc-businsstype'><a href='{$next_url}'>{$title}</a></div>";
		
		if ($n > 5)
		{
			// break the loop
			break;
		}
	}
	
	if (is_user_logged_in())
	{
		if (count($warnings) > 0)
		{
			$result .= "<div class=\"wdc-errs\">";
			foreach ($warnings as $warning)
			{
				$result .= "<div class=\"wdc-err\">{$warning}</div>";
			}
			$result .= "</div>";
		}
	}
	
	return $result;
}
add_shortcode("wdc_businesstypes", "wdc_sc_businesstypes");


function wdc_sc_get($attributes, $content = null, $name='') 
{
	$key = $attributes["key"];
	return "AA" . $_REQUEST[$key];
}
add_shortcode("wdc_get", "wdc_sc_get");

function wdc_sc_wdc($attributes, $content = null, $name='') 
{
	$warnings = array();
	
	$type = $attributes["type"];
	if ($type == "theme")
	{
		$result = wdc_sc_item($attributes, $content = null, $name='');
	}
	else if ($type == "listthemesinbusinesstype")
	{
		$result = wdc_sc_items($attributes, $content = null, $name='');
	}
	else if ($type == "listbusinesstypes")
	{
		$result = wdc_sc_businesstypes($attributes, $content = null, $name='');
	}
	else 
	{
		$warnings[] = "shortcode error; value for type attribute not supported, use any of the following: type=listbusinesstypes, type=listthemesinbusinesstype or type=theme";
	}
	
	if (is_user_logged_in())
	{
		if (count($warnings) > 0)
		{
			$result .= "<div class=\"wdc-errs\">";
			foreach ($warnings as $warning)
			{
				$result .= "<div class=\"wdc-err\">{$warning}</div>";
			}
			$result .= "</div>";
		}
	}
	
	return $result;
}
add_shortcode("wdc", "wdc_sc_wdc");