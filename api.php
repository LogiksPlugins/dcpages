<?php
if(!defined('ROOT')) exit('No direct script access allowed');

if(!function_exists("findDCPage")) {

	function findDCPage($file) {
		$fileName=$file;
		if(!file_exists($file)) {
			$file=str_replace(".","/",$file);
		}

		$fsArr=[
				$file,
				APPROOT.APPS_MISC_FOLDER."dcpages/{$file}.json",
			];
		if(isset($_REQUEST['forSite']) && defined("CMS_SITENAME")) {
			$fsArr[]=ROOT."apps/".CMS_SITENAME."/".APPS_MISC_FOLDER."dcpages/{$file}.json";
		}
		
		$fArr = explode("/",$file);
		if(count($fArr)>1) {
			$fPath = checkModule($fArr[0]);
			if($fPath) {
				unset($fArr[0]);
				$fsArr[] = dirname($fPath)."/dcpages/".implode("/",$fArr).".json";
			}
		}
		
		$file=false;
		foreach ($fsArr as $fs) {
			if(file_exists($fs)) {
				$file=$fs;
				break;
			}
		}
		if(!file_exists($file)) {
			return false;
		}

		$pageConfig=json_decode(file_get_contents($file),true);

		$pageConfig['sourcefile']=$file;
		$pageConfig['srckey']=$fileName;

		if(!isset($pageConfig['dbkey'])) $pageConfig['dbkey']="app";

		//$pageConfig['policy']

		return $pageConfig;
	}

	function printDCPage($pageConfig = [], $params = []) {
		if(!is_array($pageConfig)) $pageConfig=findForm($pageConfig);

		if(!is_array($pageConfig) || count($pageConfig)<=2) {
			trigger_logikserror("Corrupt page defination");
			return false;
		}

		if(isset($pageConfig['policy']) && strlen($pageConfig['policy'])>0) {
	      $allow=checkUserPolicy($pageConfig['policy']);
	      if(!$allow) {
	        trigger_logikserror("Sorry, you are not allowed to access this page");
	        return false;
	      }
	    }

		if($params==null) $params=[];
		$pageConfig=array_replace_recursive($pageConfig,$params);
		
		if(!isset($pageConfig['slug'])) $pageConfig['slug'] = "a/src/tab/refid";
		$slug = _slug($pageConfig['slug']);

		//printArray([$slug,$pageConfig]);

		loadModule("pages");

		//Call Hooks

		$toolBar=[];

		if(isset($pageConfig['actions']) && is_array($pageConfig['actions'])) {
			foreach ($pageConfig['actions'] as $key => $config) {
				if(isset($config['icon'])) {
					$icns = explode(" ", $config['icon']);
					if(in_array($icns[0], ["fa","glyphicon"])) {
						$pageConfig['actions'][$key]['icon'] = "<i class='{$config['icon']}'></i>";
					} else {
						//$pageConfig['actions'][$key]['icon'] = "<i class='{$config['icon']}'></i>";
					}
				}
				if(isset($config['label'])) {
					$pageConfig['actions'][$key]['title'] = $config['label'];
				}
				if(isset($pageConfig['actions'][$key]['title']) && strlen($pageConfig['actions'][$key]['title'])>0) {
					$pageConfig['actions'][$key]['title'] = _ling($pageConfig['actions'][$key]['title']);
				}
				
			}
			$toolBar = array_merge($toolBar, $pageConfig['actions']);
		}

		$scriptFunc = [];
		if(isset($pageConfig["tabs"]) && is_array($pageConfig["tabs"])) {
			foreach ($pageConfig["tabs"] as $tabKey => $tabConfig) {
				//$tabConfig
				$funcName = "dcTab_".md5($tabKey);

				$tabKeyArr = explode("@",$tabKey);
				if(count($tabKeyArr)>1) {
					switch ($tabKeyArr[0]) {
						case "forms":case "reports":case "infoview":
							$tabLink = _replace(_link("modules/".str_replace("@", "/", $tabKey)));
							$scriptFunc[$funcName] = ["url"=>$tabLink,"type"=>"url"];
							break;
						case "module":case "modules":
							$tabLink = _replace(_link("modules/{$tabKeyArr[1]}"));
							$scriptFunc[$funcName] = ["url"=>$tabLink,"type"=>"url"];
							break;
						case "popup":
							$tabLink = _replace(_link("popup/{$tabKeyArr[1]}"));
							$scriptFunc[$funcName] = ["url"=>$tabLink,"type"=>"url"];
							break;
						case "uri":
							$scriptFunc[$funcName] = ["url"=>$tabKeyArr[1],"type"=>"url"];
							break;
						default:
							$scriptFunc[$funcName] = ["params"=>$tabKeyArr[1],"type"=>$tabKeyArr[0]];
							break;
					}
				} else {
					$scriptFunc[$funcName] = ["params"=>$tabKeyArr[0],"type"=>"script"];
				}

				if(isset($tabConfig['label'])) {
					$tabConfig['title'] = $tabConfig['label'];
				}

				$toolBar[$funcName] = array_merge([
						"title"=>"Tab0",
						"align"=>"right",
						//"class"=>($reportType=="new")?"active":"",
						//"policy"=>"hrApplications.new.access"
					], $tabConfig);

				$toolBar[$funcName]['title'] = _ling($toolBar[$funcName]['title']);
			}
		}

		if(isset($pageConfig['style']) && strlen($pageConfig['style'])>0) {
			echo _css($pageConfig['style']);
		}
		if(isset($pageConfig['script']) && strlen($pageConfig['script'])>0) {
			echo _js($pageConfig['script']);
		}

		echo _css('dcpages');
		echo _js('dcpages');

		printPageComponent(false,[
		    "toolbar"=>$toolBar,
		    "sidebar"=>false,//pageDCSidebar
		    "contentArea"=>"pageDCContentArea"
		]);

		$startFunc = "";
		if(count($scriptFunc)>0) {
			$funcName = array_keys($scriptFunc)[0];
			$startFunc = "{$funcName}($('*[data-cmd=\'{$funcName}\']','#pgtoolbar'))";
		}
		?>
		<script>
		$(function() {
			$("*[data-cmd]","#pgtoolbar .nav.navbar-left").click(function() {
				    cmd = $(this).data("cmd");

				    if(cmd==null || cmd.length<=0) return;

				    cmd = cmd.split("@");
				    title = $(this).text();
				    if(title==null && $(this).attr("title")) {
				    	title = $(this).attr("title");
				    }

				    if(cmd.length==1) {
				        if(typeof cmd[0] == "function") cmd[0](this);
				    } else {
				        switch(cmd[0]) {
				            case "forms":case "reports":case "infoview":
				            	if(typeof showLoader == "function") showLoader();
						        lgksOverlayURL(_link("popup/"+cmd[0]+"/"+cmd[1]),title,function() {
						            if(typeof hideLoader == "function")  hideLoader();
						          },{"className":"overlayBox reportPopup"});
				            break;
				            case "modules":case "popup":
				            	if(cmd[0]=="module" || cmd[0]=="modules") {
									openLinkFrame(title,_link("modules/"+cmd[1]),true);
								} else {
									if(typeof showLoader == "function") showLoader();
									lgksOverlayURL(_link("popup/"+cmd[1]),title,function() {
											if(typeof hideLoader == "function")  hideLoader();
										},{"className":"overlayBox reportPopup"});
								}
				            break;
				            case "page":
				            	window.location=_link(cmd[1]);
				            break;
				        }
				    }
				});
			<?=$startFunc?>
		});
		function reloadDCPage() {
			window.location.reload();
		}
		<?php
			foreach ($scriptFunc as $funcName => $tabConfig) {
				switch ($tabConfig['type']) {
					case 'url':
						echo "function {$funcName}(ele) {
							$('#pgtoolbar .nav.navbar-right li.active').removeClass('active');
							$(ele).parent().addClass('active');
							
							$('#pgworkspace').html('<div class=\'ajaxloading ajaxloading5\'><br><br><br><br><br><br><br><br><br></div>');
							$('#pgworkspace').load('{$tabConfig['url']}', function() {
							    //console.log('Tab Loaded');
							});
						}";
						break;
					case 'script':
						echo "function {$funcName}(ele) {
							$('#pgtoolbar .nav.navbar-right li.active').removeClass('active');
							$(ele).parent().addClass('active');

							if(typeof {$tabConfig['params']} == 'function') {$tabConfig['params']}(ele);
						}";
						break;
					case "forms":case "reports":case "infoview":
						echo "function {$funcName}(ele) {
							//$('#pgtoolbar .nav.navbar-right li.active').removeClass('active');
							//$(ele).parent().addClass('active');

							//if(typeof {$tabConfig['params']} == 'function') {$tabConfig['params']}(ele);
							alert('{$tabConfig['params']}');
						}";
						break;
				}
			}
		?>
		</script>
		<?php







		
	}

	function pageDCSidebar() {
		return "";
	}

	function pageDCContentArea() {
		return "";
	}
}
?>