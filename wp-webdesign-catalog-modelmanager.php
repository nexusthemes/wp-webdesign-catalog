<?php

// use /?clearplatformdiagnosticcache=true to clear all transients

class wdc_g_modelmanager
{
	function geturlcontents($args) 
	{
		if (!$this->isretrievalallowed())
		{
			$r = array
			(
				"found" => false,
				"meta" => array
				(
					"shouldcache" => "no",
				),
			);
			
			$result = json_encode($r);
			return $result;
		}
		
		$url = $args["url"];
	
		// first try curl (as file_get_contents is more likely to be blocked on hosts)
		if (function_exists('curl_version'))
		{
			// error_log("nxs; invoking curl; $url");
			
			// note; function.php already ensures curl is available
		  $session = curl_init();
		  curl_setopt($session, CURLOPT_URL, $url);
		  curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		  $timeoutsecs = $args["timeoutsecs"];
		  if (!$timeoutsecs)
		  {
		  	$timeoutsecs = 300;
		  }
			curl_setopt($session, CURLOPT_TIMEOUT, $timeoutsecs);
			curl_setopt($session, CURLOPT_USERAGENT, 'NexusService');
			
			curl_setopt($session, CURLOPT_FORBID_REUSE, 1);	// 1 means true
			curl_setopt($session, CURLOPT_FRESH_CONNECT, 1);	// 1 means true
			
			// 2017 07 17
			curl_setopt($session, CURLOPT_SSL_VERIFYPEER, FALSE);	//
			// 2017 10 06
			curl_setopt($session, CURLOPT_SSL_VERIFYHOST, FALSE);
			
			//curl_setopt($session, CURLOPT_HEADER, false);
			//curl_setopt($session, CURLOPT_FOLLOWLOCATION, true);
			//curl_setopt($session, CURLOPT_REFERER, $url);	//
			curl_setopt($session, CURLOPT_ENCODING, '');	// no weird encodings to be returned please, thanks :)
			
			$postargs = $args["postargs"];
			if (isset($postargs))
			{
				curl_setopt($session, CURLOPT_POSTFIELDS, $postargs);
			}
			$output = curl_exec($session);
			
			$haserror = false;	
			
			if (FALSE === $output)
			{
				$haserror = true;
				$curlerror = curl_error($session);
				$curlerrorno = curl_errno($session);
		  }
			
		  curl_close($session);
		  
		  if ($haserror)
		  {
		  	if ($curlerrorno == 28)
		  	{
		  		//echo "connection timeout, retrying";
		  		
		  		// connection time out
		  		$args["connectiontimeoutretriesleft"] = $args["connectiontimeoutretriesleft"] - 1;
		  		if ($args["connectiontimeoutretriesleft"] > 0)
		  		{
		  			// recursion
		  			$output = $this->geturlcontents($args);
				  }
				  else
				  {
				  	// fatal
				  	error_log("Nxs; time out for $url; $timeoutsecs");
				  	return false;
				  }
		  
		  		// timeout
		  	}
		  }
		}
		else
		{
			// if curl not available, try file_get_contents
			$output = file_get_contents($url);
		}
	  
	  return $output;
	}
	
	function getnormalizedschema($input)
	{
		$result = preg_replace('/[^A-Za-z0-9\.]/', '', $input); // Removes special chars.
		return $result;
	}
	
	function getnormalizedhumanmodelidentification($input)
	{
		$result = preg_replace('/[^A-Za-z0-9]/', '', $input); // Removes special chars.
		return $result;
	}
	
	function getcontentmodeltaxonomyinstances($args)
	{
		$taxonomy = $args["taxonomy"];
		$contentmodel = $this->getcontentmodel();
		$result = $contentmodel[$taxonomy]["instances"];
		return $result;
	}
	
	function getmodeltaxonomyproperty($args)
	{
		$modeluri = $args["modeluri"];
		$property = $args["property"];
		$contentmodel = $this->getcontentmodel($modeluri);
		$taxonomy = "properties";
		$result = $contentmodel[$taxonomy]["taxonomy"][$property];
		//error_log("model result:" . $result);
		return $result;
	}
	
	function getmodeltaxonomyproperties($args)
	{
		$modeluri = $args["modeluri"];
		$contentmodel = $this->getcontentmodel($modeluri);
		$taxonomy = "properties";
		$result = $contentmodel[$taxonomy]["taxonomy"];
		//error_log("model result:" . $result);
		return $result;
	}
	
	function gettaxonomypropertiesofallmodels($args)
	{
		$result = array();
		
		$singularschema = $args["singularschema"];
		$iterator_datasource = "listof{$singularschema}";
		$iteratormodeluri = "singleton@{$iterator_datasource}";
		
		// refetch first if instructed to do so
		if ($args["refetch"] === "true")
		{		
			$this->cachebulkmodels($singularschema);
		}
		
		$contentmodel = $this->getcontentmodel($iteratormodeluri);
		
		$instances = $contentmodel[$singularschema]["instances"];
		foreach ($instances as $instance)
		{
			$itemhumanmodelid = $instance["content"]["humanmodelid"];
			$itemmodeluri = "{$itemhumanmodelid}@{$singularschema}";
			$itemargs = array("modeluri" => $itemmodeluri);
			$result[] = $this->getmodeltaxonomyproperties($itemargs);
		}
		
		return $result;
	}
	
	function evaluatereferencedmodelsinmodeluris($modeluris)
	{
		//error_log("evaluatereferencedmodelsinmodeluris (" . $modeluris . ")");
		
		$args = array
		(
			"modeluris" => $modeluris,
			"shouldapply_templaterules_lookups" => true,
			"shouldapplyurlvariables" => true,
		);
		$result = $this->evaluatereferencedmodelsinmodeluris_v2($args);
		return $result;
	}
	
	// evaluates nested references and optionally evaluate variables as set by the seowebsiterules (url parameters)
	function evaluatereferencedmodelsinmodeluris_v2($args)
	{
		$modeluris = $args["modeluris"];
		
		global $wdc_gl_isevaluatingreferencedmodels;
		$wdc_gl_isevaluatingreferencedmodels[$modeluris]++;
		
		if ($modeluris == "")
		{
			return $modeluris;
		}
		
		$shoulddebug = $_REQUEST["magic"] == "debug";		
		if ($shoulddebug)
		{
			echo "debugging evaluatereferencedmodelsinmodeluris for $modeluris <br />";
		}
		
		// make uniform
		
		// error_log("before; $modeluris");
		
		$modeluris = str_replace(";", "|", $modeluris);
		$modeluris = str_replace("\r\n", "|", $modeluris);
		$modeluris = str_replace("\r", "|", $modeluris);
		$modeluris = str_replace("\n", "|", $modeluris);

		// apply templateuri mappings
		// for example in the modeluris "titlemodel:{{@@templateuri.titlemodel}}"
		// the templated variable "@@templateuri.titlemodel" could map to "{{@@url.id}}@game"
		// meaning modeluris would evaluate to "titlemodel:{{@url.id}}@game
		// whether or not these are applied is determined by a argument,
		// when evaluating the modeluris a recursive call is made, and for this recursive call
		// we should NOT apply them (to avoid endless loops), see #23029458092475
		$shouldapply_templaterules_lookups = $args["shouldapply_templaterules_lookups"];
		if ($shouldapply_templaterules_lookups)
		{
			$templateurimappingslookup = array();
			
			// various options to implement the behaviour of the templateuri mapping;
			// see https://docs.google.com/document/d/1rcRJR8sX8OIdofu7rlR3gd_jqFv0IDW6eoVPcpE4cQA/edit#
			// for now we implement the mapping using the businessrules
			$templateproperties = wdc_gettemplateproperties();
			$templaterules_lookups = $templateproperties["templaterules_lookups"];
			if ($templaterules_lookups != "")
			{
				foreach ($templaterules_lookups as $key => $val)
				{
					if ($key != "" && $val != "")
					{
						$key = trim($key);
						$val = trim($val);
						$templateurimappingslookup["@@templateuri.{$key}"] = $val;
					}
				}
				
				// apply the lookup tables to the parts we've evaluated so far
				$translateargs = array
				(
					"lookup" => $templateurimappingslookup,
					"item" => $modeluris,
				);
				$modeluris = wdc_filter_translate_v2($translateargs);
			}
		}
		
		// applying of url variables v2
		if (true)
		{
			$shouldapplyurlvariables = $args["shouldapplyurlvariables"];
			if ($shouldapplyurlvariables)
			{
				// todo: perhaps use the condition here on whether or not we should do this,
				// preventing possible endless loops... for now we will assume this will always go saf
				
				$templateproperties = wdc_gettemplateproperties();
				$url_fragment_variables = $templateproperties["url_fragment_variables"];
				
				$fragmentslookup = array();
				foreach ($url_fragment_variables as $key => $value)
				{
					// $key = "@@url.{$key}";	// for example {{@@url.id}}} would become 1234
					$key = $key;	// for example {{@@url.id}}} would become 1234
					$value = $value;
					$fragmentslookup[$key] = $value;
				}
				
				// add the hostname
				$key = "@@url.hostname";
				$value = $_SERVER['HTTP_HOST'];
				$fragmentslookup[$key] = $value;			
				
				// apply the lookup tables to the parts we've evaluated so far
				$translateargs = array
				(
					"lookup" => $fragmentslookup,
					"item" => $modeluris,
				);
				$modeluris = wdc_filter_translate_v2($translateargs);
			}
		}
		
		// loop through each parts of the sequence of modeluris and apply the variables from previous steps
		// (one part could evaluate a variable that is defined in previous parts)
		if (true)
		{
			$modelurisparts = explode("|", $modeluris);
			
			$recursivelookup = array();
			$updatedmodeluris = array();
			
			foreach ($modelurisparts as $index=>$modelurispart)
			{
				$orig = $modelurispart;
				
				// sanitize element
				$modelurispart = trim($modelurispart);
				
				
				if ($modelurispart == "")
				{
					// if its blank, ignore
					continue;
				}
				else if (wdc_stringstartswith($modelurispart, "//"))
				{
					// a comment, ignore
					continue;
				}
				
				if ($shoulddebug)
				{
					echo "index: {$index}<br />";
					echo "modelurispart: {$modelurispart}<br />";
					echo "recursivelookup: " . json_encode($recursivelookup) . "<br />";
				}
				
				// apply the lookup tables to the parts we've evaluated so far
				$translateargs = array
				(
					"lookup" => $recursivelookup,
					"item" => $modelurispart,
				);
				$modelurispart = wdc_filter_translate_v2($translateargs);
				
				if ($shoulddebug)
				{
					echo "modelurispart; stage 2; modelurispart: {$modelurispart}<br />";
				}
				
				// now apply lookup values again 
				
				// apply lookup values to the modelurispart "extended" models
				$lookupargs = array
				(
					"modeluris" => $modelurispart,
				);
				$lookupcurrentpart = $this->getlookups_v2($lookupargs);
				$recursivelookup = array_merge($recursivelookup, $lookupcurrentpart);
				
				$translateargs = array
				(
					"lookup" => $recursivelookup,
					"item" => $modelurispart,
				);
				$modelurispart = wdc_filter_translate_v2($translateargs);
				
				$hasvalidreferences = true;
				
				//
				if (wdc_stringcontains($modelurispart, "{{"))
				{
					$hasvalidreferences = false;
				}
				else if (wdc_stringcontains($modelurispart, "}}"))
				{
					$hasvalidreferences = false;
				}
				
				if ($shoulddebug)
				{
					
					echo "modelurispart; hasvalidreferences; " . json_encode($hasvalidreferences) . "<br />";
				}
				
				if ($hasvalidreferences)
				{
					// good :)
					$updatedmodeluris[] = $modelurispart;
				}
				else
				{
					$sofar = implode("|", $updatedmodeluris);
					// bad
					do_action("wdc_a_modelnotfound", "(sofar=>$sofar) unresolved:{$orig}");
					// no need to add the fraction to the list, as it wont resolve
					// $updatedmodeluris[] = $orig;
				}
	
				if ($shoulddebug)
				{
					echo "recursivelookup: ";
					var_dump($recursivelookup);
					echo "<br />";
					echo "modelurispart becomes: {$modelurispart}<br />";
				}
			}
	
			// stitch all elements
			$result = implode("|", $updatedmodeluris);
		}
			
		if ($shoulddebug)
		{
			echo "result: $result";
			//die();
		}
		
		return $result;
	}
	
	function derivemodelbyuri($uri)
	{
		global $wdc_gl_modelbyuri;
		
		if (!isset($wdc_gl_modelbyuri[$uri]))
		{
			$uripieces = explode("?", $uri);
			$requestedslug = $uripieces[0];
			$requestedslug = trim($requestedslug, "/");
			
			$slugcount = count($requestedslug);
			$slugpieces = explode("/", $requestedslug);
			
			// find first condition that matches
			$result = false;
			$entries = $this->getentries();
			foreach ($entries as $entryid => $entrymeta)
			{
				$currententryvalid = "sofar";
				$currententryderivedparameters = array();
				
				$conditions = $entrymeta["conditions"];
				foreach ($conditions as $conditionid => $conditionmeta)
				{
					$currentconditionvalid = false;
					$conditiontype = $conditionmeta["type"];
					if ($conditiontype == "homeurl")
					{
						// 
						$operator = $conditionmeta["operator"];
						$value = $conditionmeta["value"];
						if ($operator == "equals" && $value == wdc_geturl_home())
						{
							// ok, proceed
						}
						else
						{
							//error_log("homeurl mismatch (".$value . ") vs (" . wdc_geturl_home() . ")");
							$currententryvalid = false;
							break;
						}
					}
					else if (wdc_stringstartswith($conditiontype, "slugatindex"))
					{
						$index = str_replace("slugatindex", "", $conditiontype);
						// obsolete/backwards compatibility
						if ($index == "")
						{
							$index = $conditionmeta["index"];
						}
						$operator = $conditionmeta["operator"];
						$value = $conditionmeta["value"];
						if ($operator == "equals" && $value == $slugpieces[$index])
						{
							// ok, proceed
						}
						else if ($operator == "exactmatchhumanmodelforschema")
						{
							$humanid = $slugpieces[$index];
							if ($humanid != "")
							{
								$conditionschema = $conditionmeta["value"];
								$currententryderivedparameters["humanid"] = "{$humanid}";
								$currententryderivedparameters["schema"] = "{$conditionschema}";
								$currententryderivedparameters["fragments"][$conditionschema] = $humanid;
								// ok, proceed
							}
							else
							{
								$currententryvalid = false;
								break;
							}
						}
						else if ($operator == "startswithhumanmodelforschema")
						{
							$currentslugpiece = $slugpieces[$index];
							// for example the following;
							// "{{X}}-grab-before-" 
							// "{{X}}-grab-before-*"
							// would be a match for "p13-grab-before-hello world" (X would then be "p13")
							$value = $conditionmeta["value"];
							
							$seperator = $value;
							$seperator = str_replace("*", "", $seperator);
							$seperator = str_replace("{{", "(", $seperator);
							$seperator = str_replace("}}", ")", $seperator);
							$seperator = str_replace("{", "(", $seperator);
							$seperator = str_replace("}", ")", $seperator);
							$seperator = preg_replace("/\([^)]+\)/","",$seperator);
							// for example "{{X}}-grab-before-" then seperator would be "-grab-before-"
							
							$slugsubpieces = explode($seperator, $currentslugpiece);
							// for example ("p13", "hello world")
							
							$humanid = $slugsubpieces[0];
							if ($humanid != "")
							{
								$schematemp = $value;
								$schematemp = str_replace("{{", "", $schematemp);
								$schematemp = str_replace("{", "", $schematemp);
								$schematemp = str_replace("}}", "|", $schematemp);
								$schematemp = str_replace("}", "|", $schematemp);
								$schematemppieces = explode("|", $schematemp);
								$conditionschema = $schematemppieces[0];
								
								if ($_REQUEST["debugmodel"] == "true")
								{
									echo "value:" . $value . "<br />";
									echo "schematemp:" . $schematemp . "<br />";
									echo "conditionschema:" . $conditionschema . "<br />";
									echo "value:" . $value . "<br />";
								}
								
								// for example "{{X}}-grab-before-" then conditionschema be "X"
								$currententryderivedparameters["fragments"][$conditionschema] = $humanid;
								// ok, proceed
							}
							else
							{
								$currententryvalid = false;
								break;
							}
						}
						else if ($operator == "endswithhumanmodelforschema")
						{
							$currentslugpiece = $slugpieces[$index];
							// for example the following;
							// "-grab-after-{{X}}" 
							// "*-grab-after-{{X}}"
							// would be a match for "hello-world-grab-after-{{X}}" (X would then be "p13")
							$value = $conditionmeta["value"];	
							
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
								$schematemppieces = explode("|", $schematemp);			// "-", "X"
								$conditionschema = $schematemppieces[1];
								
								// if the conditionschema has a "@"
								// we have to use the first part as the variable
								// and the 2nd part indicated the true modelschema
								// we should in that case only accept the URL
								// if the humanid exists in that schema
								if (wdc_stringcontains($conditionschema, "@"))
								{
									$conditionschemapieces = explode("@", $conditionschema);
									$conditionschema = $conditionschemapieces[0];
									$modelschema = $conditionschemapieces[1];
									$toverify = "{$humanid}@{$modelschema}";
									
									
									// check if such model exists
									$verified = $this->getmodel($toverify);
									if ($verified === false)
									{
										// error_log("model $toverify doesn't exist, it should result in a 404!");	
										$currententryvalid = false;
										break;
									}
								}
								
								if ($_REQUEST["debugmodel2"] == "true")
								{
									echo "value:" . $value . "<br />";
									echo "schematemp:" . $schematemp . "<br />";
									echo "humanid:" . $humanid . "<br />";
									echo "conditionschema:" . $conditionschema . "<br />";
									
								}
								
								// for example "grab-after-{X}" then conditionschema be "X"

								$currententryderivedparameters["fragments"][$conditionschema] = $humanid;
								// ok, proceed
							}
							else
							{
								$currententryvalid = false;
								break;
							}
						}
						else if ($operator == "betweenpreandpostfixmatchhumanmodelforschema")
						{
							$value = $conditionmeta["value"];
							
							$value = str_replace("{{", "{", $value);
							$value = str_replace("}}", "}", $value);
							$value = str_replace("}", "|", $value);
							$value = str_replace("{", "|", $value);
							
							$valuepieces = explode("|", $value);
							$conditionprefix = $valuepieces[0];
							$conditionschema = $valuepieces[1];
							$conditionpostfix = $valuepieces[2];
							$slug = $slugpieces[$index];
							
							if (wdc_stringstartswith($slug, $conditionprefix) && wdc_stringendswith($slug, $conditionpostfix))
							{
								// humanid is {X} as in "prefix{X}postfix"
								$sluglength = strlen($slug);				
								$prefixlength = strlen($conditionprefix);		
								$postfixlength = strlen($conditionpostfix);	
								$start = $prefixlength;
								$length = $sluglength - $prefixlength - $postfixlength;
								$humanid = substr($slug, $start, $length);
								if ($humanid != "")
								{
									$currententryderivedparameters["fragments"][$conditionschema] = $humanid;
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
								$currententryvalid = false;
								break;
							}
						}
						else if ($operator == "equals" && $value != $slugpieces[$index])
						{
							// mismatch
							$currententryvalid = false;
							break;
						}
						else
						{
							error_log("rule; unsupported operator:($operator) val:($value) index:($index) sp:(" . $slugpieces[$index] . ")");
							
							$currententryvalid = false;
							break;
						}
					}
					else
					{
						echo "unsupported conditiontype?";
						die();
					}
					
					// error_log("processing rule; conditiontype $conditiontype; valid? $currententryvalid");
				}
				
				if ($currententryvalid == "sofar")
				{
					// if we come this far, it means it valid
					$result = array
					(
						"entryid" => $entryid,
						"parameters" => $currententryderivedparameters,
					);
					break;
				}
				else
				{
					$homeurl = wdc_geturl_home();
					if (wdc_stringcontains($homeurl, "websitesexample"))
					{
						// absorb
					}
					else if (wdc_stringcontains($homeurl, "nexusthemes"))
					{
						// absorb
					}
					else
					{
						// $currenturl = wdc_geturlcurrentpage();
						// error_log("rules; condition failed; $conditionid; $conditiontype; $homeurl; $currenturl;");
					}
					
					// perhaps next entry is valid, loop
				}
			}
			
			if ($result != false)
			{
				// error_log("rules; conclusion; " . json_encode($result));
			}
			
			$wdc_gl_modelbyuri[$uri] = $result;
		}
		else
		{	
			$result = $wdc_gl_modelbyuri[$uri];
		}
		
				
		return $result;
	}
	
	// virtual posts; allow virtual posts to be mapped to a local or remote template
	function businesssite_the_posts($result, $args)
	{
		global $wp,$wp_query;
		global $wdc_g_businesssite_didoverride;
		
		if (!is_main_query()) { return $result; }
		if (is_admin()) { return $result; }
		
		// 20170305; if its a webmethod, dont override it
		// if (wdc_iswebmethodinvocation()) { return $result; }
		
		// only override 1x
		if ($wdc_g_businesssite_didoverride === true) { return $result; }
		$wdc_g_businesssite_didoverride = true;
		
		$countmatches = count($result);
		
		$modeluri = false;
		$schema = false;
		$humanid = false;
		
		//
		$templateproperties = wdc_gettemplateproperties();
		if ($templateproperties["lastmatchingrule"] == "busruleurl")
		{
			$schema = "wdc_vtemplate";
			$isvirtual = true;
		}
		
		// loop over the contentmodel and verify if the requestedslug matches 
		// any of the elements of the contentmodel
		if ($isvirtual)
		{
			// apparently there's a match;
			// mimic a post by creating a virtual post
			
			// derived the seo title
			$title = $this->wpseo_title();	
			$excerpt = "";	// intentionally left blank; not practical to fill this
			$content = "";  // intentionally left blank; lets use the front end instead
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
			
			// there can/may be only one match
			return $result;
		}
		else
		{
			// no match means keep post from regular WP, or perhaps 404 if its not found
		}
		
		return $result;
	}
	
	function gethumanid($modeluri = "")
	{
		$pieces = explode("@", $modeluri);
		$result = $pieces[0];
		return $result;
	}
	
	function getschema($modeluri = "")
	{
		$pieces = explode("@", $modeluri);
		$result = end($pieces);
		return $result;
	}
	
	//
	//
	//
	function getlookups_v2($args)
	{
		$result = array();
		
		$modeluris = $args["modeluris"];

		$orig = $modeluris;
		
		// error_log("invoked; getlookups; $modeluris");
		
		$modeluris = str_replace(" ", "", $modeluris);
		$modeluris = str_replace(";", ",", $modeluris);
		$modeluris = str_replace("|", ",", $modeluris);
		$modeluripieces = explode(",", $modeluris);
		
		$index = -1;
		foreach ($modeluripieces as $modeluripiece)
		{
			$index++;
			
			$isvalid = true;
			if (wdc_stringcontains($modeluripiece, "{{"))
			{
				$isvalid = false;
			}
			else if (wdc_stringcontains($modeluripiece, "}}"))
			{
				$isvalid = false;
			}
			
			if (!$isvalid)
			{
				// error_log("invalid model lookup; $modeluripiece (in orig:'$orig')");
				do_action("wdc_a_modelnotfound", array("modeluri" => "$modeluripiece"));
				
				// skip!
				continue;
			}
			
			$subpieces = explode(":", $modeluripiece);
			
			if (count($subpieces) == 1)
			{
				$prefix = "";
				$modeluri = $subpieces[0];
			}
			else
			{				
				$prefix = $subpieces[0] . ":";
				$modeluri = $subpieces[1];
			}
			
			// format = prefix:foo@bar
			if ($modeluri != "")
			{
				$lookup = $this->getlookup($modeluri, "{$prefix}");
				$result = array_merge($result, $lookup);
			}
		}
		
		//
		if (true)
		{
			// include parameters as derived by the template engine
			$templateproperties = wdc_gettemplateproperties();
			$modelmapping = $templateproperties["templaterules_lookups_lookup"];
			
			foreach ($modelmapping as $key => $val)
			{
				$lookupkey = "{$key}";
				$result[$lookupkey] = $val;
			}
		}
		
		return $result;
	}
	
	function getlookup($modeluri = "", $prefix = "")
	{
		// error_log("invoked; getlookup; $modeluri ($prefix)");
		
		$schema = $this->getcontentschema($modeluri);
		$contentmodel = $this->getcontentmodel($modeluri);
		
		foreach ($schema as $taxonomyid => $taxonomymeta)
		{
			$taxonomyextendedproperties = $taxonomymeta["taxonomyextendedproperties"];
			foreach ($taxonomyextendedproperties as $fieldid => $fieldmeta)
			{
				$val = $contentmodel[$taxonomyid]["taxonomy"][$fieldid];
				// to avoid issues we transform the < signs
				$val = htmlentities($val); // str_replace("<", "&lt;", $val);
				$lookup["{$prefix}{$taxonomyid}.{$fieldid}"] = $val;
			}
		}
		
		return $lookup;
	}
	
	function isvalidschema($singularschema = "")
	{
		$normalized = $this->getnormalizedschema($singularschema);
		$result = ($normalized == $singularschema);
		
		if (!$result)
		{
			do_action("wdc_a_invalidschema", array("schema" => "$singularschema"));
		}

		return $result;
	}
	
	function isvalidmodeluri($modeluri = "")
	{
		$isvalid = true;
		if (wdc_stringendswith($modeluri, "@"))
		{
			$isvalid = false;
		}
		else if (wdc_stringstartswith($modeluri, "@"))
		{
			$isvalid = false;
		}
		else if (!wdc_stringcontains($modeluri, "@"))
		{
			$isvalid = false;
		}
		else if (wdc_stringcontains($modeluri, "{{"))
		{
			$isvalid = false;
		}
		else if (wdc_stringcontains($modeluri, "}}"))
		{
			$isvalid = false;
		}
		else if (wdc_stringcontains($url, "<"))
		{
			$isvalid = false;
		}
		else if (wdc_stringcontains($url, ">"))
		{
			$isvalid = false;
		}
		else if ($url == "singleton@listof")
		{
			$isvalid = false;
		}

		//$st = json_encode(wdc_getstacktrace());
		if (!$isvalid)
		{
			if ($modeluri != "")
			{
				do_action("wdc_a_modelnotfound", array("modeluri" => $modeluri));
			}
		
			$shoulddebug = ($_REQUEST["logrr"] == "true");
			if ($shoulddebug)
			{
				$st = json_encode(debug_backtrace());
				error_log("isvalidmodeluri; invalid; ('$modeluri'); $st");
				die();
			}
		}

		return $isvalid;
	}
	
	function getmodel($modeluri = "")
	{
		// 
		do_action("wdc_a_getmodel", array("modeluri" => $modeluri));
		
		$isvalid = $this->isvalidmodeluri($modeluri);
		if (!$isvalid)
		{
			return false;
		}
		
		// error_log("getmodel for $modeluri");
		
		// convert provided modeluri to the runtime one
		// (empty modeluri will mean modeluri will be derived from the url being accessed)
		
		$cachekey = $modeluri;
		if ($cachekey == "")
		{
			$cachekey = "/";
		}
		
		global $wdc_g_model;
		if (!isset($wdc_g_model[$cachekey]))
		{
			if ($modeluri != "")
			{
				$wdc_g_model[$cachekey] = $this->getmodel_dbcache($modeluri);
			}
			else
			{
				// error_log("businessite; no model to be retrieved");
			}
		}
		$result = $wdc_g_model[$cachekey];
		
		return $result;
	}
	
	function getcontentmodel($modeluri = "")
	{
		$model = $this->getmodel($modeluri);
		return $model["contentmodel"];
	}
	
	function getcontentmodelproperty($modeluri, $property)
	{
		$contentmodel = $this->getcontentmodel($modeluri);
		$value = $contentmodel["properties"]["taxonomy"][$property];
		return $value;
	}
	
	function getcontentschema($modeluri = "")
	{
		$model = $this->getmodel($modeluri);
		return $model["meta"]["schema"];
	}
	
	function wdc_getmodeltransientkeyprefix()
	{
		$result = "models_";
	}
	
	function wdc_getmodeltransientkey($modeluri)
	{
		$result = $this->wdc_getmodeltransientkeyprefix() . md5("modeldb_{$modeluri}");
		return $result;
	}
	
	function getmodel_dbcache($modeluri)
	{
		//
		$transientkey = $this->wdc_getmodeltransientkey($modeluri);
		$result = get_transient($transientkey);
		$shouldrefreshdbcache = false;
		
		if ($shouldrefreshdbcache == false && $result == "")
		{
			$shouldrefreshdbcache = true;
		}
		if ($shouldrefreshdbcache == false && $result == false)
		{
			$shouldrefreshdbcache = true;
		}
		if ($shouldrefreshdbcache == false && $_REQUEST["transients"] == "refresh")
		{
			$shouldrefreshdbcache = true;
		}
		if ($shouldrefreshdbcache == false && $_REQUEST["transients"] == "refresh_modeluricontains")
		{
			$needle = $_REQUEST["modeluricontains"];
			if ($needle != "")
			{
				$ignorecasing = true;
				if (wdc_stringcontains_v2($modeluri, $needle, $ignorecasing))
				{
					$shouldrefreshdbcache = true;
				}
			}
		}
		
		if ($shouldrefreshdbcache)
		{
			
			
			$result = $this->getmodel_actual($modeluri);
			
			// update cache
			$cacheduration = 60 * 60 * 24 * 30; // 30 days cache

			if (isset($result["cachedurationinsecs"]))
			{
				$cacheduration = $result["cachedurationinsecs"];
				if ($cacheduration == 0)
				{
					$cacheduration = 60 * 60 * 24 * 30; // 30 days cache
				}
			}
			
			if ($result["meta"]["shouldcache"] == "no")
			{
				// dont cache
			}
			else
			{
				set_transient($transientkey, $result, $cacheduration);
			}
			
			// check for greedy fetch request
			if ($result["meta"]["greedyfetchsupport"])
			{
				error_log("found greedyfetchsupport instruction when fetching $modeluri :)");
				// 
				$schema = $this->getschema($modeluri);
				$this->cachebulkmodels($schema);
			}
			else
			{
				
			}
			
			if ($_REQUEST["loggetmodels"] == "true" && is_super_admin())
			{
				error_log("getmodel;result;$modeluri;" . json_encode($result));
			}
		}
		
		if ($result["found"] === false)
		{
			error_log("getmodel_dbcache; {$modeluri}; not found");
			$result = false;
		}
		
		return $result;
	 }
	
	function getmodel_actual($modeluri)
	{
		error_log("getmodel_actual; attempt; $modeluri");
		
		$isvalid = $this->isvalidmodeluri($modeluri);
		if (!$isvalid)
		{
			error_log("getmodel_actual; not valid; $modeluri; returning false");
			return false;
		}
		
		// if modeluri is specified retrieve the model through the modeluri
		$url = "https://turnkeypagesprovider.websitesexamples.com/api/1/prod/model-by-uri/{$modeluri}/?nxs=contentprovider-api&licensekey={$licensekey}&wdc_json_output_format=prettyprint";
		$content = $this->geturlcontents(array("url" => $url));

		// error_log("getmodel_actual; returned content");
		
		$json = json_decode($content, true);
		
		// 
		if ($json === null)
		{
			do_action("wdc_a_modelerror", array("modeluri" => "$modeluripiece"));
			
			$json = array
			(
				"found" => false,
				"err" => array
				(
					"scenario" => "empty/invalid json",
					"content" => $content,
				),
			);
			
			return $json;
		}
		
		if ($json["found"] === false)
		{
			do_action("wdc_a_modelnotfound", array("modeluri" => "$modeluripiece"));
			// error_log("getmodel_actual; not found; $url");
			return $json;
		}
		
		if ($json["found"] === false)
		{
			do_action("wdc_a_modelnotfound", array("modeluri" => "$modeluripiece"));
			// error_log("getmodel_actual; not found; $url");
			return $json;
		}
		
		if ($json["wdc_queued"] == "true")
		{
			// overrule the cache behaviour; we disable it,
			// since some items were queued; don't cache pages that render information based upon 
			// items that are queued
			wdc_disablecacheforthisrequest();
			
			// error_log("instructing the page to not store cache because of queued content");
			// its throttled/queued
			// for now we return false
			return false;
		}
		
		$result = $json;
	
		return $result;
	}
	
	function isretrievalallowed()
	{
		global $wdc_retrieval_enabled;
		return $wdc_retrieval_enabled == true;
	}
	
	function enableretrieval()
	{
		global $wdc_retrieval_enabled;
		$wdc_retrieval_enabled = true;
	}
	
	function disableretrieval()
	{
		global $wdc_retrieval_enabled;
		$wdc_retrieval_enabled = false;
	}
	
	function cachebulkmodels($singularschema)
	{
		if ($singularschema == "") { echo "singularschema not specified?"; die(); }
		if (!$this->isvalidschema($singularschema)) { echo "invalid singularschema $singularschema?"; die(); }
		if (wdc_stringstartswith($singularschema, "listof")) { echo "cannot cachebulkmodels lists; cachebulkmodels the singular model to refetch the list"; die(); }

		// the fetching of the models
		$this->cachebulkmodels_internal($singularschema);
		
		// the fetching of the list model conneced to it
		$listofschema = "listof{$singularschema}";
		$this->cachebulkmodels_internal($listofschema);
	}
	
	function cachebulkmodels_internal($singularschema)
	{
		if (wdc_stringstartswith($singularschema, "listoflistof"))
		{
			error_log("cachebulkmodels; skipping for $singularschema :)");
			return;
		}
		
		error_log("cachebulkmodels for $singularschema");
		
		
		
		// step 1; load the bulk model information
		$url = "https://turnkeypagesprovider.websitesexamples.com/api/1/prod/bulkmodels/{$singularschema}/?nxs=contentprovider-api&licensekey={$licensekey}&wdc_json_output_format=prettyprint";
		$content = $this->geturlcontents(array("url" => $url));
		$json = json_decode($content, true);
		
		$itemcount = 0;
		
		if (isset($json["items"]))
		{	
			// step 2; loop over each item in the bulk model
			foreach ($json["items"] as $modeluri => $item)
			{
				$itemcount++;
				
				// step 3; combine /blend/ the content with the schema
				$schema = $item["schema"];
				$item["meta"]["schema"] = $json["schemas"][$schema];
				
				// step 3; store the item in the cache
				$transientkey = $this->wdc_getmodeltransientkey($modeluri);
	
				// update cache
				$cacheduration = 60 * 60 * 24 * 30; // 30 days cache
	
				if (isset($item["cachedurationinsecs"]))
				{
					$cacheduration = $item["cachedurationinsecs"];
					if ($cacheduration == 0)
					{
						$cacheduration = 60 * 60 * 24 * 30; // 30 days cache
					}
				}
				
				set_transient($transientkey, $item, $cacheduration);
			}
		}
		
		error_log("cachebulkmodels; finished updating $itemcount items for $singularschema");
	}
	
	function getwidgets($result, $widgetargs)
	{
		$nxsposttype = $widgetargs["nxsposttype"];
		$pagetemplate = $widgetargs["pagetemplate"];
	
		if (wdc_enableconceptualwidgets())
		{
			$result[] = array("widgetid" => "entities");
		}
				
		if ($nxsposttype == "post") 
		{
			$result[] = array("widgetid" => "list");
			$result[] = array("widgetid" => "embed");
			
			//$result[] = array("widgetid" => "socialaccounts");
			//$result[] = array("widgetid" => "commercialmsgs");
			

		}
		else if ($nxsposttype == "sidebar") 
		{
			$result[] = array("widgetid" => "list");
			$result[] = array("widgetid" => "embed");
			
			//$result[] = array("widgetid" => "socialaccounts");
		}
		else if ($nxsposttype == "header") 
		{
			$result[] = array("widgetid" => "list");
			//$result[] = array("widgetid" => "phone");
			//$result[] = array("widgetid" => "buslogo");
			$result[] = array("widgetid" => "embed");
			
			//$result[] = array("widgetid" => "socialaccounts");
			//$result[] = array("widgetid" => "commercialmsgs");
		}
		
		if ($pagetemplate == "pagedecorator") 
		{
			//$result[] = array("widgetid" => "taxpageslider", "tags" => array("nexus"));		
		}
		
		return $result;
	}
	
	/* YOAST SEO */
	
	function getruntimeseoproperties()
	{
		/*
		global $wdc_gl_runtimeseoproperties;
		
		if ($wdc_gl_runtimeseoproperties == "")
		{
			$templateproperties = wdc_gettemplateproperties();
			$content_postid = $templateproperties["content_postid"];
			// locate all "seo" widget(s) in the front-end content "template"
			$filterargs = array
			(
				"postid" => $content_postid,
				"widgettype" => "seo",	// all seo widgets
			);
			$seowidgets = wdc_getwidgetsmetadatainpost_v2($filterargs);
			$mixedattributes = reset($seowidgets);
			
			// apply the lookups
			$templateruleslookups = wdc_gettemplateruleslookups();
			$parsedlookups = wdc_parse_keyvalues($mixedattributes["lookups"]);

			$combined_lookups = array();
			$combined_lookups = array_merge($combined_lookups, $templateruleslookups);
			$combined_lookups = array_merge($combined_lookups, $parsedlookups);
			
			if ($combined_lookups > 0)
			{
				// evaluate the lookups widget values line by line
				$sofar = array();
				foreach ($combined_lookups as $key => $val)
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
		
					$combined_lookups[$key] = trim($val);
				}
			}
			
			// apply the lookups and shortcodes to the customhtml
			$magicfields = array("title", "metadescription", "canonicalurl");
			$translateargs = array
			(
				"lookup" => $combined_lookups,
				"items" => $mixedattributes,
				"fields" => $magicfields,
			);
			$mixedattributes = wdc_filter_translate_v2($translateargs);
			
			$wdc_gl_runtimeseoproperties = $mixedattributes;
			
			$canonicalurl = $wdc_gl_runtimeseoproperties["canonicalurl"];
			$wdc_gl_runtimeseoproperties["canonicalurl"] = do_shortcode($canonicalurl);
		}
		
		return $wdc_gl_runtimeseoproperties;
		*/
		
		$result = array();
		return $result;
	}
	
	function wpseo_title($result) 
	{
		// if we reach this point, it means the page has a model as its context
		// the title, description and canonical url can be determined by 
		// a "seo widget" that is stored in the content template defined
		// in the page template rules.
		$runtimeseoproperties = $this->getruntimeseoproperties();
		$title = $runtimeseoproperties["title"];		
		if ($title != "")
		{
			// 
			
			$result = $title;
		}
		return $result;
	}
	
	function wpseo_metadesc($result) 
	{
		// if we reach this point, it means the page has a model as its context
		// the title, description and canonical url can be determined by 
		// a "seo widget" that is stored in the content template defined
		// in the page template rules.
		$runtimeseoproperties = $this->getruntimeseoproperties();
		$title = $runtimeseoproperties["metadescription"];		
		if ($title != "")
		{
			// 
			
			$result = $title;
		}
		return $result;
	}
	
	function wpseo_canonical($result)
	{
		// if we reach this point, it means the page has a model as its context
		// the title, description and canonical url can be determined by 
		// a "seo widget" that is stored in the content template defined
		// in the page template rules.
		$runtimeseoproperties = $this->getruntimeseoproperties();
		$title = $runtimeseoproperties["canonicalurl"];		
		if ($title != "")
		{
			// 
			
			$result = $title;
		}
		return $result;
	}
	
	function wpseo_robots($result = "index,follow")
	{
		// if we reach this point, it means the page has a model as its context
		// the title, description and canonical url can be determined by 
		// a "seo widget" that is stored in the content template defined
		// in the page template rules.
		$runtimeseoproperties = $this->getruntimeseoproperties();
		$canonicalurl = $runtimeseoproperties["canonicalurl"];		
		if ($canonicalurl != "")
		{
			$result = "index,follow";
		}
		return $result;
	}
	
	function instance_init()
	{
		// enable an outside manager to clear/flush the cache
		// such that new content will be fetched the next time
		if ($_REQUEST["clearplatformdiagnosticcache"] == "true")
		{
			$prefix = $this->wdc_getmodeltransientkeyprefix();
			wdc_cache_cleartransients($prefix);
			$result = array("cache" => "clear");
			wdc_webmethod_return_ok($result);
		}
		
		if ($_REQUEST["wdc"] == "dobulk1")
		{
			$schema = "nxs.nexusthemes.itemmeta";
			$this->cachebulkmodels($schema);
			echo "all items were loaded :)";
			die();
		}
		
		if ($_REQUEST["wdc"] == "dobulk2")
		{
			$schema = "nxs.nexusthemes.itemmetaidsbybusinesstype";
			$this->cachebulkmodels($schema);
			echo "all items were loaded :)";
			die();
		}
		
		// handle bulk model prefetching
		if ($_REQUEST["bulkmodels"] == "true")
		{
			
			if (!is_user_logged_in())
			{
				if ($_SERVER['REMOTE_ADDR'] == "52.21.12.12")
				{
					// if the content server triggers this, its ok
				}
				else
				{
					echo "sorry only available if you are logged in, or if you are the content server which you are not";
					die();
				}
			}
			else
			{
				// ok
			}

			$singularschema = $_REQUEST["singularschema"];
			echo "Bulk fetching ($singularschema)... <br />";
			
			if ($singularschema == "") { echo "singularschema not specified?"; die(); }
			if (wdc_stringstartswith($singularschema, "listof")) { echo "cannot refetch lists; refetch the singular model to refetch the list"; die(); }

			// this function will itself also update the list
			$this->cachebulkmodels($singularschema);
			
			// also expire the cache of the site
			$path = wdc_cache_getcachefolder();
			wdc_recursive_removedirectory($path);
			
			echo "cache wiped... <br />";
			echo "done :)";
			die();
			// todo: output some json instead of text...
		}
		
		if (is_user_logged_in())
		{
			$dumpmodeluri = $_REQUEST["dumpmodeluri"];
			if ($dumpmodeluri != "")
			{
				echo "output for $dumpmodeluri:<br /><br />";
				$d = $this->getmodel($dumpmodeluri);
				echo json_encode($d);
				echo "<br />so far :)";
				die();
			}
			$dumpmodel = $_REQUEST["dumpmodel"];
			if ($dumpmodel != "")
			{
				echo "output for $dumpmodel:<br /><br />";
				$iteratormodeluri = "singleton@listof{$dumpmodel}";
				$contentmodel = $this->getcontentmodel($iteratormodeluri);
				
				$instances = $contentmodel[$dumpmodel]["instances"];
				foreach ($instances as $instance)
				{
					$itemhumanmodelid = $instance["content"]["humanmodelid"];
					$itemmodeluri = "{$itemhumanmodelid}@$dumpmodel";
					echo "output for entry $itemmodeluri:<br />";
					$d = $this->getmodel($itemmodeluri);
					echo json_encode($d);
					echo "<br /><br />";
				}
				echo "<br />so far :)";
				die();
			}
		}
	}
		
	function __construct()
  {
  	add_filter('init', array($this, "instance_init"), 5, 1);
		// add_action('admin_head', array($this, "instance_admin_head"), 30, 1);
		
		// allow seo widgets to override SEO settings (Yoast)
		if (true)
		{
			add_filter('wpseo_title', array($this, 'wpseo_title'), 99999);
			add_filter('wpseo_metadesc', array($this, 'wpseo_metadesc'), 1999);
			add_filter('wpseo_canonical', array($this, 'wpseo_canonical'), 99999);
			add_filter('wpseo_robots', array($this, 'wpseo_robots'), 99999);
		}
  }
  
	/* ---------- */
}

global $wdc_g_modelmanager;
$wdc_g_modelmanager = new wdc_g_modelmanager();
