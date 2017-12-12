<?php

/* */


function wdc_filter_translategeneric($metadata, $fields, $prefixtoken, $postfixtoken, $lookup)
{
	$patterns = array();
	$replacements = array();
	$build = true;
	
	foreach ($fields as $currentfield)
	{
		$source = $metadata[$currentfield];
		if (isset($source))
		{
			if (wdc_stringcontains($source, $prefixtoken))
			{				
				// very likely there's a lookup used, let's replace the tokens!
				
				if ($build)
				{
					$build = false;

					// optimization; only do this when the lookup is not yet set,
					// note this can be further optimized					
					foreach ($lookup as $key => $val)
					{
						$patterns[] = '/' . $prefixtoken . $key . $postfixtoken . '/';
						$replacements[] = $val;
					}
				}

				// the actual replacing of tokens for this item
				$metadata[$currentfield] = preg_replace($patterns, $replacements, $metadata[$currentfield]);
			}
			else
			{
				// ignore
			}
		}
	}
	
	return $metadata;
}

function wdc_lookuptable_getlookup_v2($includeruntimeitems)
{
	$result = array();
	return $result;
}

// derives the template properties for the current executing request, cached
function wdc_gettemplateproperties()
{
	global $wdc_gl_cache_templateprops;
	if (!isset($wdc_gl_cache_templateprops))
	{
		// stage 1; the evaluation that determines which rules are active
		if (true)
		{
			$result = wdc_gettemplateproperties_internal();
			
			// important step; here we already set the global variable,
			// even though the variables have not yet been processed,
			// this is because while processing the variables (which happens in the next stage)
			// the logic requires the template properties themselves!
			$wdc_gl_cache_templateprops = $result;
		}
		
		// stage 2; set the template variables (see #43856394587)
		if (true)
		{
			// only AFTER the templateproperties have been evaluated,
			// (which means also AFTER the url parameters have been evaluate),
			// and AFTER the cached variable has been set,
			// we can THEN derive the template variables
			
			// handle the modelmappings
			
			$modeluris = $result["templaterules_modeluris"];
			$templaterules_lookups = $result["templaterules_lookups"];
			
			/*
			if ($modeluris != "" && $templaterules_lookups != "")
			{
				global $wdc_g_modelmanager;
				//var_dump($templaterules_modeluris);
				// to prevent endless loop here we invoke the evaluatereferencedmodelsinmodeluris without
				// re-applying the shouldapply_templaterules_lookups, see #23029458092475
				$args = array
				(
					"modeluris" => $modeluris,
					"shouldapply_templaterules_lookups" => false,
					"shouldapplyurlvariables" => !($wdc_gl_isevaluatingreferencedmodels === true),
				);
				$modeluris = $wdc_g_modelmanager->evaluatereferencedmodelsinmodeluris_v2($args);
			}
			*/
			
			if ($templaterules_lookups != "")
			{
				$parsed_templaterules_lookups = wdc_parse_keyvalues($templaterules_lookups);
	
				// evaluate the lookups widget values line by line
				$sofar = array();
				foreach ($parsed_templaterules_lookups as $key => $val)
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
					$val = trim($val);
					$sofar[$key] = $val;
		
					//echo "step 5; $key evaluates to $val (after applying shortcodes)<br /><br />";
		
					$parsed_templaterules_lookups[$key] = $val;
				}
				
				// store the lookup table
				$wdc_gl_cache_templateprops["templaterules_lookups_lookup"] = $parsed_templaterules_lookups;
			}
		}
	}
	else
	{
		$result = $wdc_gl_cache_templateprops;
	}
	
	return $result;
}

function wdc_gettemplateproperties_internal()
{
	$result = array();
	
	$ishandled = false;
	
	/*
	if (is_singular())
	{
		// users can overrule the layout engine
		$postid = get_the_ID();
		
		$wdc_semanticlayout = wdc_get_post_meta($postid, 'wdc_semanticlayout', true);
		if ($wdc_semanticlayout == "landingpage")
		{
			$ishandled = true;
			$result = array
			(
				"content_postid" => $postid,
				"wpcontenthandler" => "@template@onlywhenset",
				"result" => "OK",
			);
		}
	}
	*/
	
	$statebag = array();
	$statebag["vars"] = array();
	$statebag["out"] = array();
	
	//
	// initial values
	//
	
	if (is_singular())
	{
		// get_the_ID is not yet available here the first time we get invoked ...
		// so we cannot use $postid = get_the_ID();
		global $wp_query;
		$p = $wp_query->posts[0];
		$postid = $p->ID;
		$statebag["out"]["content_postid"] = $postid;
	}
	else if (is_archive())
	{
	}
	
	$businessrules = wp_webdesign_catalog_getbusinessrules();
	
	$index = 0;
	foreach ($businessrules as $currentbusinessrule) 
	{
		$content = $currentbusinessrule["content"];
		$placeholdertype = $currentbusinessrule["type"];
		
		if ($placeholdertype == "" || $placeholdertype == "undefined" || !isset($placeholdertype)) 
		{
			// empty row / rule, ignore it
		}
		else 
		{
			// store this item as one of the matching rules
			$busrule_processresult = wdc_busrule_process($placeholdertype, $currentbusinessrule, $statebag);
			if ($busrule_processresult["result"] == "OK")
			{
				$traceitem = array
				(
					"placeholdertype" => $placeholdertype,
					"ismatch" => $busrule_processresult["ismatch"],
				);
				$result["trace"][] = $traceitem;
				
				if ($busrule_processresult["ismatch"] == "true")
				{
					$lastmatchingrule = $placeholdertype;
					
					// the process function is responsible for filling the out property
					if ($busrule_processresult["stopruleprocessingonmatch"] == "true")
					{
						break;
					}
				}
				else
				{
					// continu to next rule
				}
			}
			else
			{
				// if applying of a rule failed, we skip it
			}
		}
	}
	
	/*
	// the system should have derived site wide elements
	$sitewideelements = wdc_pagetemplates_getsitewideelements();
	foreach($sitewideelements as $currentsitewideelement)
	{
		$result[$currentsitewideelement] = $statebag["out"][$currentsitewideelement];
	}
	*/
	
	// pass through the values for the modeluris and modelmappings of the various sections
	// for now only the content section (in the future also the header, subheader, ...)
	$result["templaterules_modeluris"] = $statebag["out"]["templaterules_modeluris"];
	$result["templaterules_lookups"] = $statebag["out"]["templaterules_lookups"];
	$result["url_fragment_variables"] = $statebag["out"]["url_fragment_variables"];
	
	$result["lastmatchingrule"] = $lastmatchingrule;
	
	$result["result"] = "OK";
	
	if ($_REQUEST["debugtemplateproperties"] == "true" && is_user_logged_in())
	{
		echo "wdc_gettemplateproperties_internal; conclusie:";
		var_dump($result);
		die();
	}
	
	return $result;
}

function wdc_busrule_process($busruletype, $metadata, &$statebag)
{
	// delegate
	$functionnametoinvoke = "wdc_busrule_{$busruletype}_process";
	if (function_exists($functionnametoinvoke))
	{
		$args = array();
		$args["template"] = $template;
		$args["metadata"] = $metadata;
		$parameters = array($args, &$statebag);
		$result = call_user_func_array($functionnametoinvoke, $parameters);
	}
	else
	{
		wdc_webmethod_return_nack("function not found; $functionnametoinvoke");
	}
	
	return $result;
}

function wdc_gettemplateruleslookups()
{
	// this invocation will set the wdc_gl_cache_templateprops
	$tp = wdc_gettemplateproperties();
	global $wdc_gl_cache_templateprops;
	$result = $wdc_gl_cache_templateprops["templaterules_lookups_lookup"];
	
	if ($result === null || $result == "")
	{
		$result = array();
	}
	
	return $result;
}

function wdc_iswebmethodinvocation()
{
	return false;
}

function wdc_lookups_blendlookupstoitselfrecursively($lookup)
{
	// recursively apply/blend the lookup table to the values, until nothing changes or when we run out of attempts 
	if (true)
	{			
		// now that the entire lookup table is filled,
		// recursively apply the lookup tables to its values
		// for those keys that have one or more placeholders in their values
		$triesleft = 4;	// to prevent endless loops
		while ($triesleft > 0)
		{
			//
			
			$triesleft--;
			
			$didsomething = false;
			foreach ($lookup as $key => $val)
			{
				if (wdc_stringcontains($val, "{{"))
				{
					$origval = $val;
					
					$translateargs = array
					(
						"lookup" => $lookup,
						"item" => $val,
					);
					$val = wdc_filter_translate_v2($translateargs);
					
					$somethingchanged = ($val != $origval);
					if ($somethingchanged)
					{
						// try to apply shortcodes (if applicable)
						$val = do_shortcode($val);
						$somethingchanged = ($val != $origval);
						if ($somethingchanged)
						{
							$lookup[$key] = $val;
							$didsomething = true;
						}
						else
						{
							// very theoretical scenario; the shortcode could have put the value back to how it was
							// meaning that nothing did change...
						}
					}
					else
					{
						// continue;
					}
				}
			}
			
			if (!$didsomething)
			{
				// if nothing changed, dont re-attempt to apply variables and/or shortcodes
				break;
			}
			else
			{
				// something changed, retry as this might have impacted the lookup itself
			}
		}
	}
	
	return $lookup;
}

/* */

function wdc_stringendswith($haystack, $needle)
{
  $length = strlen($needle);
  if ($length == 0) {
      return true;
  }

  return (substr($haystack, -$length) === $needle);
}

function wdc_stringstartswith($haystack, $needle)
{
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function wdc_stringcontains($haystack, $needle)
{
	$ignorecasing = false;
	$result = wdc_stringcontains_v2($haystack, $needle, $ignorecasing);
	return $result;
}

function wdc_stringcontains_v2($haystack, $needle, $ignorecasing)
{
	if ($ignorecasing === true)
	{
		$pos = stripos($haystack,$needle);
	}
	else
	{
		$pos = strpos($haystack,$needle);
	}
	
	if($pos === false) 
	{
	 // string needle NOT found in haystack
	 return false;
	}
	else 
	{
	 // string needle found in haystack
	 return true;
	}
}

function wdc_get_home_url()
{
	$result = get_bloginfo('url') . "/";
	return $result;
}

function wdc_strleft($s1, $s2) 
{
	return substr($s1, 0, strpos($s1, $s2));
}

// return the url after setting/updating the parameter (other occurences of the same parameter are removed)
function wdc_addqueryparametertourl_v2($url, $parameter, $value, $shouldurlencode = true, $shouldremoveparameterfirst = true)
{
	if (!isset($shouldremoveparameterfirst))
	{
		$shouldremoveparameterfirst = true;
	}
	
	if ($shouldremoveparameterfirst === true)
	{
		// first remove parameter (if set)
		$url = wdc_removequeryparameterfromurl($url, $parameter);
	}
	
	$result = $url;
	if (wdc_stringcontains($url, "?"))
	{
		$result = $result . "&";
	}
	else
	{
		$result = $result . "?";
	}
	
	if ($shouldurlencode === true)
	{
		$result = $result . $parameter . "=" . urlencode($value);
	}
	else
	{
		$result = $result . $parameter . "=" . $value;
	}
	
	return $result;
}

// kudos to http://stackoverflow.com/questions/4937478/strip-off-url-parameter-with-php
function wdc_removequeryparameterfromurl($url, $parametertoremove)
{
	$parsed = parse_url($url);
	if (isset($parsed['query'])) 
	{
		$params = array();
		foreach (explode('&', $parsed['query']) as $param) 
		{
		  $item = explode('=', $param);
		  if ($item[0] != $parametertoremove) 
		  {
		  	$params[$item[0]] = $item[1];
		  }
		}
		//
		$result = '';
		if (isset($parsed['scheme']))
		{
		  $result .= $parsed['scheme'] . "://";
		}
		if (isset($parsed['host']))
		{
		  $result .= $parsed['host'];
		}
		if (isset($parsed['path']))
		{
		  $result .= $parsed['path'];
		}
		if (count($params) > 0) 
		{
		  $result .= '?' . urldecode(http_build_query($params));
		}
		if (isset($parsed['fragment']))
		{
		  $result .= "#" . $parsed['fragment'];
		}
	}
	else
	{
		$result = $url;
	}
	return $result;
}

function wdc_parse_keyvalues($lookups)
{
	$result = array();
	
	$lines = explode("\n", $lookups);
	foreach ($lines as $line)
	{
		$limit = 2;	// 
		$pieces = explode("=", $line, $limit);
		$key = trim($pieces[0]);
		
		if ($key == "")
		{
			// empty line, ignore
		}
		else if (wdc_stringstartswith($key, "//"))
		{
			// its a comment, ignore
		}
		else
		{
			$val = $pieces[1];
			$result[$key] = $val;
		}
	}
	
	return $result;
}

function wdc_geturlcurrentpage()
{
	// note; the "fragment" part (after "#"), is not available by definition;
	// its something browsers use; its not send to the server (unless some clientside
	// logic does so)
	$args = array
	(
		"rewritewebmethods" => "true",
	);
  $serverrequri = wdc_geturicurrentpage($args);
  $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
  $protocol = wdc_strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
  $port = ($_SERVER["SERVER_PORT"] == "80" || $_SERVER["SERVER_PORT"] == "443") ? "" : (":".$_SERVER["SERVER_PORT"]);
  return $protocol."://".$_SERVER['HTTP_HOST'].$port.$serverrequri;   
}


function wdc_outputbuffer_popall()
{
	$existingoutput = array();
	
	$numlevels = ob_get_level();
	for ($i = 0; $i < $numlevels; $i++)
	{
		$existingoutput[] = ob_get_clean();
	}
	
	return $existingoutput;
}

function wdc_webmethod_return_nack($message)
{
	// cleanup output that was possibly produced before,
	// if we won't this could cause output to not be json compatible
	$existingoutput = wdc_outputbuffer_popall();
	
	http_response_code(500);
	//header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error");
	//header("Status: 500 Internal Server Error"); // for fast cgi
	
	$output = array
	(
		"result" => "NACK",
		"message" => "Halted; " . $message
	);
	
	if (wdc_DEFINE_NXSDEBUGWEBSERVICES)
	{
		// very practical; the stacktrace and request are returned too,
		// see the js console window to ease the debugging
		$output["outputbeforenack"] = $existingoutput;
		$output["request"] = $_REQUEST;
		$output["stacktrace"] = wdc_getstacktrace();
	}

	if (wdc_iswebmethodinvocation())
	{
		// system is processing a nxs webmethod; output in json
		$output=json_encode($output);
		echo $output;
	}
	else
	{
		// system is processing regular request; output in text
		echo "<div style='background-color: white; color: black;'>NACK;<br />";
		echo "raw print:<br />";
		var_dump($output);
		echo "pretty print:<br />";
		if ($_REQUEST["pp"] == "false")
		{
			// in some situation the prettyprint can stall
			
		}
		else
		{
			echo "<!-- hint; in case code breaks after this comment, add querystring parameter pp with value false (pp=false) to output in non-pretty format -->";
			echo wdc_prettyprint_array($output);
		}
		echo "<br />(raw printed)<br />";
		echo "</div>";
	}
	die();
}

// pretty_print
function wdc_prettyprint_array($arr)
{
	$retStr = '<h1>Pretty print</h1>';
  $retStr = '<ul>';
  if (is_array($arr)){
      foreach ($arr as $key=>$val){
          if (is_array($val))
          {
          	$retStr .= '<li>' . $key . ' => ' . wdc_prettyprint_array($val) . '</li>';
          } 
          else if (is_string($val))
          {
          	$retStr .= '<li>' . $key . ' => ' . $val . '</li>';
          }
          else
          {
          	$type = get_class($val);
          	if ($type === false)
          	{
          		// primitive
          		$retStr .= '<li>' . $key . ' => ' . $val . '</li>';
          	}
          	else
          	{
          		$retStr .= '<li>' . $key . ' => {some object of type ' . $type . ' }</li>';
          	}
          }
      }
  }
  else
  {
  	$retStr .= '<li>Not an array</li>';
  }
  $retStr .= '</ul>';
  return $retStr;
}

// delete transients, clear_transients
function wdc_cache_cleartransients($prefix)
{
	error_log("clearing transients for '$prefix'");

	$tunedprefix = preg_replace('/[^A-Za-z0-9\_]/', '', $prefix); // Removes special chars.
	if ($prefix != $tunedprefix)
	{
		echo "unable to proceed; ";
		die();		
	}
	
	global $wpdb;
	$sqlquery = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_{$prefix}%%'" );
}

function wdc_getstacktrace()
{
	if (is_super_admin())
	{
		$result = debug_backtrace();
	}
	else
	{
		$result = array();
		$result["tip"] = "stacktrace suppressed; only available for admin users";
	}
	
	return $result;
}

function wdc_webmethod_return_ok($args)
{
	$content = $args;
	$content["result"] = "OK";
	wdc_webmethod_return_raw($content);
}

function wdc_webmethod_return_raw($args)
{
	if (headers_sent($filename, $linenum)) 
	{
		echo "nxs headers already send; $filename $linenum";
		exit();
	}
	
	$existingoutput = array();
	
	$numlevels = ob_get_level();
	for ($i = 0; $i < $numlevels; $i++)
	{
		$existingoutput[] = wdc_ob_get_clean();
	}
	
	wdc_set_jsonheader();
	http_response_code(200);

	if (wdc_DEFINE_NXSDEBUGWEBSERVICES)
	{
		// very practical; the stacktrace and request are returned too,
		// see the js console window to ease the debugging
		$args["outputbeforeok"] = $existingoutput;
		$args["request"] = $_REQUEST;
		$args["stacktrace"] = wdc_getstacktrace();
	}

	// add 'result' to array
	// $args["result"] = "OK";
	
	// sanitize malformed utf8 (if the case)
	$args = wdc_array_toutf8string($args);
	
	// in some very rare situations the json_encode
	// can stall/break the execution (see support ticket 13459)
	// if there's weird Unicode characters in the HTML such as (C2 A0)
	// which is a no-break character that is messed up
	// (invoking json_encode on that output would not throw an exception
	// but truly crash the server). To solve that problem, we use the following
	// kudos to:
	// http://stackoverflow.com/questions/12837682/non-breaking-utf-8-0xc2a0-space-and-preg-replace-strange-behaviour
	foreach ($args as $k => $v)
	{
		if (is_string($v))
		{
			$v = preg_replace('~\xc2\xa0~', ' ', $v);
			$args[$k] = $v;
		}
	}
	
	if ($_REQUEST["wdc_json_output_format"] == "prettyprint")
	{
		// only works in PHP 5.4 and above
		$options = 0;
		$options = $options | JSON_PRETTY_PRINT;
		$output = json_encode($args, $options);
	}
	else
	{
		// important!! the json_encode can return nothing,
		// on some servers, when the 2nd parameter (options),
		// is specified; ticket 22986!
		
		$output = json_encode($args);
	}
	
	echo $output;
	
	exit();
}

function wdc_ob_get_clean()
{
	$shouldbufferoutput = true;
	
	if ($_REQUEST["nxs"] == "nobuffer")
	{
		if (wdc_has_adminpermissions())
		{
			$shouldbufferoutput = false;
		}
	}
	
	if ($shouldbufferoutput)
	{
		$result = ob_get_clean();
	}
	else
	{
		$result = "overruled (no output buffering)";
	}
	
	return $result;
}

function wdc_set_jsonheader()
{
		// set headers
	if (!wdc_detect_ie())
	{
		if(!headers_sent())
		{
			header('Content-Type: application/json; charset=utf-8');
		}
	}
	else
	{
		// for IE / Internet Explorer, use text/javascript, implements bug 931
		// kudos to http://stackoverflow.com/questions/6114360/stupid-ie-prompts-to-open-or-save-json-result-which-comes-from-server
		if(!headers_sent())
		{
			header('Content-type: text/html');
		}
	}
}

// kudos to http://www.anyexample.com/programming/php/how_to_detect_internet_explorer_with_php.xml
function wdc_detect_ie()
{
  if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
  {
    return true;
  }
  else
  {
  	// IE11 works in a slightly different way...
  	if (preg_match("/Trident\/7.0;(.*)rv:11.0/", $_SERVER["HTTP_USER_AGENT"], $match) != 0)
  	{
  		return true;
  	}
  	else
  	{
    	return false;
    }
  }
}

// 2012 06 04; GJ; in some particular situation (unclear yet when exactly) the result cannot be json encoded
// erroring with 'Invalid UTF-8 sequence in range'.
// Solution appears to be to UTF encode the input
function wdc_array_toutf8string($result)
{
	foreach ($result as $resultkey => $resultvalue)
	{
		if (is_string($resultvalue))
		{
			if (!wdc_isutf8($resultvalue))
			{
				$result[$resultkey] = wdc_toutf8string($resultvalue);
			}

			// also fix the special character \u00a0 (no breaking space),
			// as this one also could result into issues
			$result[$resultkey] = preg_replace('~\x{00a0}~siu', ' ', $result[$resultkey]);   
		}
		else if (is_array($resultvalue))
		{
			$result[$resultkey] = wdc_array_toutf8string($resultvalue);
		}
		else
		{
			// leave as is...
		}
	}
	
	return $result;
}

function wdc_isutf8($string) 
{
  if (function_exists("mb_check_encoding")) 
  {
    return mb_check_encoding($string, 'UTF8');
  }
  
  return (bool)preg_match('//u', serialize($string));
}

function wdc_geturicurrentpage($args = array())
{
	if ($args["rewritewebmethods"] == "true")
	{
		if (wdc_iswebmethodinvocation())
		{
			$result = $_REQUEST["uricurrentpage"];
			return $result;
		}
	}
	
	// note; the "fragment" part (after "#"), is not available by definition;
	// its something browsers use; its not send to the server (unless some clientside
	// logic does so)
  if(!isset($_SERVER['REQUEST_URI']))
  {
  	$result = $_SERVER['PHP_SELF'];
  }
  else
  {
    $result = $_SERVER['REQUEST_URI'];
  }
  return $result;
}

function wdc_gethostname()
{
	$url = wdc_geturlcurrentpage();
	$pieces = parse_url($url);
	$result = $pieces["host"];
	return $result;
}