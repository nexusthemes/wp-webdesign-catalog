<?php
function wdc_busrule_busruleurl_process($args, &$statebag)
{
	$result = array();
	$result["result"] = "OK";

	$metadata = $args["metadata"];
	
	$currenturl = wdc_geturlcurrentpage();
	
	$operator = $metadata["operator"];
	$p1 = $metadata["p1"];

	if ($operator == "contains" && wdc_stringcontains($currenturl, $p1))
	{
		$result["ismatch"] = "true";
		
		// process configured site wide elements
		$sitewideelements = wdc_pagetemplates_getsitewideelements();
		foreach($sitewideelements as $currentsitewideelement)
		{
			$selectedvalue = $metadata[$currentsitewideelement];
			if ($selectedvalue == $filter_authoremail)
			{
				// skip
			} 
			else if ($selectedvalue == "@leaveasis")
			{
				// skip
			}
			else if ($selectedvalue == "@suppressed")
			{
				// reset
				$statebag["out"][$currentsitewideelement] = 0;
			}
			else
			{
				// set the value as selected
				$statebag["out"][$currentsitewideelement] = $metadata[$currentsitewideelement];
			}
		}
		
		// concatenate the modeluris and modelmapping (do NOT yet evaluate them; this happens in stage 2, see #43856394587)
		$statebag["out"]["templaterules_modeluris"] .= "\r\n" . $metadata["templaterules_modeluris"];
		$statebag["out"]["templaterules_lookups"] .= "\r\n" . $metadata["templaterules_lookups"];
		
		// instruct rule engine to stop further processing if configured to do so (=default)
		$flow_stopruleprocessingonmatch = $metadata["flow_stopruleprocessingonmatch"];
		if ($flow_stopruleprocessingonmatch != "")
		{
			$result["stopruleprocessingonmatch"] = "true";
		}
	}
	else if ($operator == "template")
	{
		$isconditionvalid = true;
		
		// check condition
		if (true)
		{
			$template = $p1;														// for example "/detail/*-{{name@model}}/"
			$template = trim($template, "/");						// for example "detail/*-{{name@model}}"
			$templatepieces = explode("/", $template);	// for example ["detail", "*-{{name@model}}"]
			$cnttemplatepieces = count($templatepieces);
			
			$uriargs = array
			(
				"rewritewebmethods" => true,
			);
			$uri = wdc_geturicurrentpage($uriargs);			// for example "/detail/very-nice-1/?page=2"
			$uripieces = explode("?", $uri);
			$uri = $uripieces[0];												// for example "/detail/very-nice-1/"
			$uri = trim($uri, "/");											// for example "detail/very-nice-1"
			$uripieces = explode("/", $uri);						// for example ["detail", "very-nice-1"]
			$cnturipieces = count($uripieces);
			
			if ($cnttemplatepieces == $cnturipieces)
			{
				// its valid, until we conclude one piece is not valid
				$isconditionvalid = true;
				
				$derivedurlfragmentkeyvalues = "";
				$url_fragment_variables = array();
				
				// possible match
				for ($fragmentindex = 0; $fragmentindex < $cnturipieces; $fragmentindex++)
				{
					$uripiece = $uripieces[$fragmentindex];
					$templatepiece = $templatepieces[$fragmentindex];
					
					$containsvariable = false;
					if (wdc_stringcontains_v2($templatepiece, "{", false))
					{
						$containsvariable = true;
					}
					
					if ($containsvariable)
					{
						$startswithvariable = wdc_stringstartswith($templatepiece, "{");
						$endswithvariable = wdc_stringendswith($templatepiece, "}");
						if ($startswithvariable && $endswithvariable)
						{
							// wildcard for complete fragment,
							// for example "/detail/{{name@model}}/"
							
							$humanid = $uripiece;
							
							$conditionschema = $templatepiece;													// {{name@model}}
							$conditionschema = str_replace("{", "", $conditionschema);	// name@model}}
							$conditionschema = str_replace("}", "", $conditionschema);	// name@model
								
							// if the conditionschema has a "@"
							// we have to use the first part as the variable
							// and the 2nd part indicated the true modelschema
							// we should in that case only accept the URL
							// if the humanid exists in that schema
							$representsmodellookup = wdc_stringcontains($conditionschema, "@");
							if ($representsmodellookup)
							{
								$conditionschemapieces = explode("@", $conditionschema);
								$conditionschema = $conditionschemapieces[0];
								$modelschema = $conditionschemapieces[1];
								$toverify = "{$humanid}@{$modelschema}";
								
								// check if such model exists
								global $wdc_g_modelmanager;								
								$verified = $wdc_g_modelmanager->getmodel($toverify);
								if ($verified === false)
								{
									// error_log("model $toverify doesn't exist, it should result in a 404!");	
									$currententryvalid = false;
									break;
								}
							}
							else
							{
								// its "just" a variable, not a model lookup
							}
							
							// for example "grab-after-{X}" then conditionschema be "X"
							$derivedurlfragmentkeyvalues .= "{$conditionschema}={$humanid}\r\n";
							$url_fragment_variables[$conditionschema] = $humanid;
							
							// ok, proceed
						}
						else if ($endswithvariable)
						{
							// wildcard / model lookup check, which should/will set a variable,
							// for example "/detail/*-{{name@model}}/"
							
							$currentslugpiece = $uripiece;
							// for example the following;
							// "-grab-after-{{X}}" 
							// "*-grab-after-{{X}}"
							// would be a match for "hello-world-grab-after-{{X}}" (X would then be "p13")
							$value = $templatepiece;
							
							$seperator = $value;
							$seperator = str_replace("*", "", $seperator);
							$seperator = str_replace("{{", "(", $seperator);
							$seperator = str_replace("}}", ")", $seperator);
							$seperator = str_replace("{", "(", $seperator);
							$seperator = str_replace("}", ")", $seperator);
							// for example "-grab-after-(X)"
							$seperator = preg_replace("/\([^)]+\)/","",$seperator);
							// for example "-grab-after-"
							
							$slugsubpieces = explode($seperator, $currentslugpiece);
							// for example ("hello-world", "p13")
							
							$humanid = end($slugsubpieces);
							if ($humanid != "")
							{
								$schematemp = $value;																// -{{X}}
								$schematemp = str_replace("{{", "|", $schematemp);	// -|X}}
								$schematemp = str_replace("{", "|", $schematemp);		// -|X}}
								$schematemp = str_replace("}}", "", $schematemp);		// -|X
								$schematemp = str_replace("}", "", $schematemp);		// -|X
								$schematemppieces = explode("|", $schematemp);			// ["-", "X"]
								$conditionschema = $schematemppieces[1];
								
								// if the conditionschema has a "@"
								// we have to use the first part as the variable
								// and the 2nd part indicated the true modelschema
								// we should in that case only accept the URL
								// if the humanid exists in that schema
								$representsmodellookup = wdc_stringcontains($conditionschema, "@");
								if ($representsmodellookup)
								{
									$conditionschemapieces = explode("@", $conditionschema);
									$conditionschema = $conditionschemapieces[0];
									$modelschema = $conditionschemapieces[1];
									$toverify = "{$humanid}@{$modelschema}";
									
									// check if such model exists
									global $wdc_g_modelmanager;
									$verified = $wdc_g_modelmanager->getmodel($toverify);
									if ($verified === false)
									{
										// error_log("model $toverify doesn't exist, it should result in a 404! (b)");	
										$currententryvalid = false;
										$isconditionvalid = false;
										
										break;
									}
								}
								else
								{
									// its "just" a variable, not a model lookup
								}
								
								// for example "grab-after-{X}" then conditionschema be "X"
								$derivedurlfragmentkeyvalues .= "{$conditionschema}={$humanid}\r\n";
								$url_fragment_variables[$conditionschema] = $humanid;
								
								// ok, proceed
							}
							else
							{
								$currententryvalid = false;
								break;
							}
						}
						else
						{
							// format is not (yet) supported
							$currententryvalid = false;
							break;
						}
					}
					else
					{
						// static 1:1 comparison
						if ($templatepiece === $uripiece)
						{
							// yes its identical, continue to the next fragment
						}
						else
						{
							// fragment mismatch; break the loop!
							$isconditionvalid = false;
							break;
						}
					}
				}
			}
			else
			{
				// mismatch
				$isconditionvalid = false;
			}
		}
		
		if ($isconditionvalid && $currententryvalid === false)
		{
			$isconditionvalid = false;
		}
		
		if ($isconditionvalid)
		{
			// yes, unless one of the fragments is a mismatch
			$result["ismatch"] = "true";
			
			/*
			// process configured site wide elements
			$sitewideelements = wdc_pagetemplates_getsitewideelements();
			foreach($sitewideelements as $currentsitewideelement)
			{
				$selectedvalue = $metadata[$currentsitewideelement];
				if ($selectedvalue == $filter_authoremail)
				{
					// skip
				} 
				else if ($selectedvalue == "@leaveasis")
				{
					// skip
				}
				else if ($selectedvalue == "@suppressed")
				{
					// reset
					$statebag["out"][$currentsitewideelement] = 0;
				}
				else
				{
					// set the value as selected
					$statebag["out"][$currentsitewideelement] = $metadata[$currentsitewideelement];
				}
			}
			*/

			// the following is UNIQUE for this specific rule;
			// also add the url fragment keyvalues as derived from the url
			// NOTE; its very important to add the derivedurlfragmentkeyvalues
			// to the templaterules_lookups PRIOR to adding the templaterules_lookups
			// as likely the templaterules_lookups use the variable. If this is done
			// in the wrong order, its likely that modelproperty shortcodes
			// will try to fetch a model with an unreplaced variable, resulting in empty
			// values.
			$statebag["out"]["templaterules_lookups"] .= "\r\n" . trim($derivedurlfragmentkeyvalues);
			$statebag["out"]["url_fragment_variables"] = $url_fragment_variables;


			
			// concatenate the modeluris and modelmapping (do NOT yet evaluate them; this happens in stage 2, see #43856394587)
			$statebag["out"]["templaterules_modeluris"] .= "\r\n" . $metadata["templaterules_modeluris"];
			$statebag["out"]["templaterules_lookups"] .= "\r\n" . trim($metadata["templaterules_lookups"]);
			
			
			// instruct rule engine to stop further processing if configured to do so (=default)
			$flow_stopruleprocessingonmatch = $metadata["flow_stopruleprocessingonmatch"];
			if ($flow_stopruleprocessingonmatch != "")
			{
				$result["stopruleprocessingonmatch"] = "true";
			}
		}
	}
	else
	{
		$result["ismatch"] = "false";
	}
	
	return $result;
}

function wdc_widgets_embed_render_htmlvisualization($args) 
{
	// Importing variables
	extract($args);
	
	if ($render_behaviour == "code")
	{
		//
		$temp_array = array();
	}
	else
	{
		$temp_array = array();
		// wdc_getwidgetmetadata($postid, $placeholderid);
	}
	
	// The $mixedattributes is an array which will be used to set various widget specific variables (and non-specific).
	$mixedattributes = array_merge($temp_array, $args);
	
	unset($mixedattributes["id"]);
	
	// Output the result array and setting the "result" position to "OK"
	$result = array();
	$result["result"] = "OK";
	
	// Widget specific variables
	extract($mixedattributes);
	
	// Setting the widget name variable to the folder name
	$widget_name = basename(dirname(__FILE__));

	global $wdc_global_row_render_statebag;
	global $wdc_global_placeholder_render_statebag;
	
	// EXPRESSIONS
	// ---------------------------------------------------------------------------------------------------- 
	
	global $wdc_global_current_containerpostid_being_rendered;
	$containerpostid = $wdc_global_current_containerpostid_being_rendered;

	if ($embeddabletypemodeluri == "")
	{
		$result["html"] = "specify embeddabletypemodeluri";
		return $result;
	}

	// OUTPUT
	// ---------------------------------------------------------------------------------------------------- 
	
	if (true)
	{
		//
		global $wdc_g_modelmanager;
		$templateurl = $wdc_g_modelmanager->getcontentmodelproperty($embeddabletypemodeluri, "templateurl");
		$fieldsjsonstring = $wdc_g_modelmanager->getcontentmodelproperty($embeddabletypemodeluri, "fields");
		$fields = json_decode($fieldsjsonstring, true);
		
		if ($_REQUEST["gj"] == "31")
		{
			var_dump($embeddabletypemodeluri);
			var_dump($templateurl);
			var_dump($fields);
			die();
		}
	
		$args = $temp_array;
		//  override the following parameter
		$args["postid"] = $containerpostid;
		$args["placeholderid"] = $placeholderid;
		$args["placeholdertemplate"] = "embed";
		
		$url = $templateurl;

		// add query parameters based upon the lookup tables of the widget (options)
		
		$thelookup = array();
				
		$sitelookups = wdc_lookuptable_getlookup_v2(true);
		$thelookup = array_merge($thelookup, $sitelookups);
		
		$moreitems = wdc_gettemplateruleslookups();
		$thelookup = array_merge($thelookup, $moreitems);
		
		$moreitems = wdc_parse_keyvalues($lookups);				
		$thelookup = array_merge($thelookup, $moreitems);
		
		// evaluate the thelookup values line by line
		$sofar = array();
		foreach ($thelookup as $key => $val)
		{
			$sofar[$key] = $val;
			//echo "step 1; processing $key=$val sofar=".json_encode($sofar)."<br />";

			//echo "step 2; about to evaluate lookup tables on; $val<br />";
			// apply the lookup values
			$sofar = wdc_lookups_blendlookupstoitselfrecursively($sofar);

			// apply shortcodes
			$val = $sofar[$key];
			//echo "step 3; result is $val<br />";

			//echo "step 4; about to evaluate shortcode on; $val<br />";

			$val = do_shortcode($val);
			$sofar[$key] = $val;

			//echo "step 5; $key evaluates to $val (after applying shortcodes)<br /><br />";

			$thelookup[$key] = $val;
		}
		
		foreach ($fields as $field => $fieldmeta)
		{
			$id = $fieldmeta["id"];
			$value = $$id;
			
			// it could be that the value contains a lookup placeholder; replace those
			if (wdc_stringcontains($value, "{"))
			{
				
				
				//error_log("thelookup:" . json_encode($thelookup));
				//error_log("value before:" . $value);
								
				// interpret the iterator_datasource by applying the lookup tables from the pagetemplate_rules
				$translateargs = array
				(
					"lookup" => $thelookup,
					"item" => $value,
				);
				$value = wdc_filter_translate_v2($translateargs);

				//error_log("value after:" . $value);
			}
				
			$url = wdc_addqueryparametertourl_v2($url, $id, $value, true, true);
		}
		
		// include/override the properties of the include_parameters_of_uri model
		if ($include_parameters_of_uri != "")
		{
			$properties =  $wdc_g_modelmanager->getmodeltaxonomyproperties(array("modeluri" => $include_parameters_of_uri));
			foreach ($properties as $key => $val)
			{
				if ($key == "{$coach_schema}_id")
				{
					// skip this one
				}
				else
				{
					$sanitizedkey = $key;
					$sanitizedkey = str_replace(".", "_", $sanitizedkey);
					$url = wdc_addqueryparametertourl_v2($url, $sanitizedkey, $val, true, true);
				}
			}
		}

		$url = wdc_addqueryparametertourl_v2($url, "frontendframework", "alt", true, true);
		$url = wdc_addqueryparametertourl_v2($url, "wdc_triggeredby", "embedwidget", true, true);
		// $url = wdc_addqueryparametertourl_v2($url, "wdc_hostname", wdc_gethostname(), true, true);
		
		//
			
		if ($_REQUEST["gj"] == "30")
		{
			var_dump($url);
			die();
		}
		
		$prefix = "embed_tr_";
		$cacheduration = 60 * 60 * 24 * 30; // 30 days cache
		
		do_action("wdc_a_usetransients", array("prefix" => $prefix, "title" => "Embed widget", "cacheduration" => $cacheduration));
		
		if ($_REQUEST["embed_transients"] == "refresh")
		{
			if (is_user_logged_in())
			{
				wdc_cache_cleartransients($prefix);
			}
		}
		
		$transientkey = $prefix . md5("{$url}");
		$content = get_transient($transientkey);
		$shouldrefreshdbcache = false;
		if ($shouldrefreshdbcache == false && $content == "")
		{
			$shouldrefreshdbcache = true;
		}
		if ($shouldrefreshdbcache == false && $_REQUEST["embed_transients"] == "refresh")
		{
			$shouldrefreshdbcache = true;
		}
		
		if ($shouldrefreshdbcache)
		{			
			$content = file_get_contents($url);
			
			// update cache
			set_transient($transientkey, $content, $cacheduration);
		}

		if ($_REQUEST["debugembed"] == "true" && is_user_logged_in())
		{
			echo $url;
			die();
		}
		
		// apply shortcodes (used in for example the google maps widget)
		// this has to be done prior to changing the clases (see below)
		$content = do_shortcode($content);
		
		// tune the output (should be done by the content platform)
		
		$content = str_replace("nxs-sitewide-element", "template-sitewide-element", $content);
		$content = str_replace("nxs-content-container", "template-content-container", $content);
		$content = str_replace("nxs-article-container", "template-article-container", $content);
		$content = str_replace("nxs-postrows", "template-postrows", $content);
		$content = str_replace("nxs-row", "template-row", $content);
		$content = str_replace("nxs-placeholder-list", "template-placeholder-list", $content);
		$content = str_replace("ABC", "template-ABC", $content);
		$content = str_replace("XYZ", "template-XYZ", $content);
		$content = str_replace("nxs-widget-", "template-widget-", $content);
		$content = str_replace("nxs-widget", "template-widget", $content);
		
		$content = str_replace("nxs-placeholder", "template-placeholder", $content);
		
		$content = str_replace("nxs-containsimmediatehovermenu", "template-containsimmediatehovermenu", $content);
		$content = str_replace("has-no-sidebar", "template-has-no-sidebar", $content);
		$content = str_replace("nxs-elements-container", "template-XYZ", $content);
		$content = str_replace("nxs-runtime-autocellsize", "template-runtime-autocellsize", $content);
		
		
		/*
		echo "<style>";
		echo ".template-placeholder-list { display: flex; }";
		echo ".template-ABC.nxs-height100 { height: 100% !important; }";
		echo ".template-placeholder-list .nxs-one-whole { width: 100% !important; border-right: 0px !important; }";
		echo "</style>";
		*/
		
		echo "<style>";
		echo ".template-placeholder-list { display: flex; }";
		echo "@media (max-width: 400px) {  .template-placeholder-list { display: block !important; }}";
		echo ".template-placeholder-list { display: flex; }";
		echo ".template-placeholder { list-style: none; flex: 1; }";
		// echo ".template-ABC.nxs-height100 { height: 100% !important; }";
		echo ".template-placeholder-list .nxs-one-whole { width: 100% !important; border-right: 0px !important; }";
		echo "</style>";
		
		echo $content;
		
		
	}

	// -------------------------------------------------------------------------------------------------
	 
	// Setting the contents of the output buffer into a variable and cleaning up te buffer
	//$html = ob_get_contents();
	//ob_end_clean();
	
	// Setting the contents of the variable to the appropriate array position
	// The framework uses this array with its accompanying values to render the page
	$result["html"] = $html;	
	
	return $result;
}