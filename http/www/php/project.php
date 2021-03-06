<?php require_once("sql.php"); ?>
<?php
	$project = new DomDocument();
	$racine  = null;
	
	//Ouverture / fermeture
	function openProject() {
		header('Content-Type: text/plain charset=utf-8');
		
		global $project;
		global $racine;
		$project->preserveWhiteSpace = false;
		$project->load("../file/project.xml");
		$project->formatOutput = true;
		$racine = $project->documentElement;
	}
	function closeProject() {
		global $project;
		global $racine;
		$project->save("../file/project.xml");
	}
	
	//Ajoute un fichier
	function addFileToProject($file, $metas, $tcIn, $tcOut) {
		global $project;
		global $racine;
		//echo $file."=".$tc;
		$tcIn        = floatval($tcIn);
		$tcOut       = floatval($tcOut);
		$document    = $project->createElement("document");
		
		$finfo       = finfo_open(FILEINFO_MIME_TYPE);

		$user        = "";
		if(fileowner($file) != FALSE) {
			$user = fileowner($file);
			$user = posix_getpwuid($user);
			$user = explode(",", $user["gecos"]);
			$user = $user[0];
		}
		
		//Création des métadatas
		$metasAdd = array(
	        "Rekall->Comments"			=> "",

			"File->Hash"				=> strtoupper(sha1_file($file)),
			"Rekall->Flag"				=> "File",
			
			"File->Thumbnail"			=> "",
			"File->Owner"				=> $user,

			"File->MIME Type"			=> finfo_file($finfo, $file),
			"File->File Type"			=> finfo_file($finfo, $file),
	        "Rekall->Type"				=> finfo_file($finfo, $file),

			"File->File Name"			=> pathinfo($file, PATHINFO_BASENAME),
			"File->Extension"			=> pathinfo($file, PATHINFO_EXTENSION),
	        "File->Basename"			=> pathinfo($file, PATHINFO_FILENAME),
			"Rekall->Name"				=> pathinfo($file, PATHINFO_FILENAME),
			"Rekall->Extension"			=> strtoupper(pathinfo($file, PATHINFO_EXTENSION)),
			"Rekall->Folder"			=> "",

			"Rekall->File Size"			=> filesize($file),
			"Rekall->File Size (MB)"	=> filesize($file)/(1024.*1024.),
		); 
		$metas = array_merge($metas, $metasAdd);
		$key = "/".$metas["Rekall->Folder"].$metas["File->File Name"];
		$metas["key"] = $key;
		
		//Génère une vignette
		$fileDestBasename = strtoupper(sha1($metas["Rekall->Folder"])."-".$metas["File->Hash"]);
		$fileDest = "../file/rekall_cache/".$fileDestBasename.".jpg";
		createThumb($file, $fileDest, 160);
		if(file_exists($fileDest))
			$metas["File->Thumbnail"] = $fileDestBasename;

		//Ajout des métadatas
		foreach($metas as $metaCategory => $metaContent) {
			$meta = $project->createElement("meta");
			$meta->setAttribute("ctg", $metaCategory);
			$meta->setAttribute("cnt", $metaContent);
			$document->appendChild($meta);
			$racine->appendChild($document);
		}
		
		//Tag de timeline
		$tag = $project->createElement("tag");
		$tag->setAttribute("key",       $key);
		$tag->setAttribute("timeStart", $tcIn);
		$tag->setAttribute("timeEnd",   $tcOut);
		$tag->setAttribute("version",   0);
		$racine->appendChild($tag);
		
		return $metas;
	}

	
	//Ajoute un marker
	function addMarkerToProject($type, $name, $metas, $tcIn, $tcOut) {
		global $project;
		global $racine;

		$tcIn        = floatval($tcIn);
		$tcOut       = floatval($tcOut);
		$document    = $project->createElement("document");

		//Création des métadatas
		$metasAdd = array(
	        "Rekall->Comments"					=> "",
	        "Rekall->Type"						=> $type,
	        "Rekall->Date/Time"					=> date("Y:m:d H:i:s"),
	        "Rekall->Import Date"				=> date("Y:m:d H:i:s"),
			"Rekall->Flag"						=> "Marker",
			"Rekall->Name"						=> $name,
		); 
		$metas = array_merge($metas, $metasAdd);
		$key = "marker-".sha1(mktime().rand()."");
		$document->setAttribute("key", $key);
		$metas["key"] = $key;

		//Ajout des métadatas
		foreach($metas as $metaCategory => $metaContent) {
			$meta = $project->createElement("meta");
			$meta->setAttribute("ctg", $metaCategory);
			$meta->setAttribute("cnt", $metaContent);
			$document->appendChild($meta);
			$racine->appendChild($document);
		}
		
		//Tag de timeline
		$tag = $project->createElement("tag");
		$tag->setAttribute("key",       $key);
		$tag->setAttribute("timeStart", $tcIn);
		$tag->setAttribute("timeEnd",   $tcOut);
		$tag->setAttribute("version",   0);
		$racine->appendChild($tag);
		
		return $metas;
	}
	
	
	//Supprime un fichier
	function removeDocumentFromProject($key) {
		global $project;
		global $racine;
		$retours = array("document" => 0, "tag" => 0, "edition" => 0, "file" => 0);
		$thumbnail = "";
		
		//Recherche dans les documents
		$documents = $racine->getElementsByTagName("document");
		foreach($documents as $document) {
			$searchKey = $document->getAttribute("key");
			if($searchKey == $key) {
				$retours["document"]++;
				$racine->removeChild($document);
			}
			else {
				$metas = $document->getElementsByTagName("meta");
				$searchFile   = "";
				$searchFolder = "";
				foreach($metas as $meta) {
					if     ($meta->getAttribute("ctg") == "File->File Name")
						$searchFile = $meta->getAttribute("cnt");
					else if($meta->getAttribute("ctg") == "Rekall->Folder")
						$searchFolder = $meta->getAttribute("cnt");
					else if($meta->getAttribute("ctg") == "File->Thumbnail")
						$thumbnail = $meta->getAttribute("cnt");
				}
				$searchKey = "/".$searchFolder.$searchFile;
				if($searchKey == $key) {
					$retours["document"]++;
					$racine->removeChild($document);
					break;
				}
				else
					$thumbnail = "";
			}
		}
		
		//Recherche dans les tags
		$tags = $racine->getElementsByTagName("tag");
		foreach($tags as $tag) {
			$searchKey = $tag->getAttribute("key");
			if($searchKey == $key) {
				$retours["tag"]++;
				$racine->removeChild($tag);
			}
		}
		
		//Recherche dans les editions
		$editions = $racine->getElementsByTagName("edition");
		foreach($editions as $edition) {
			$searchKey = $edition->getAttribute("key");
			if($searchKey == $key) {
				$retours["edition"]++;
				$racine->removeChild($edition);
			}
		}
		
		//Supprime le fichier
		$fileToRemove = "../file".$key;
		if(file_exists($fileToRemove)) {
			$retours["file"]++;
			unlink($fileToRemove);
		}
		//et sa vignette
		$fileToRemove = "../file/rekall_cache/".$thumbnail.".jpg";
		if(file_exists($fileToRemove)) {
			$retours["file"]++;
			unlink($fileToRemove);
		}
		
		
		
		echo json_encode($retours);
	}
	
	//Change le TC
	function editTc($key, $tcIn, $tcOut) {
		global $project;
		global $racine;
		$retours = array("success" => 1, "value" => "");
		
		//Tag de timeline
		$tag = $project->createElement("tag");
		$tag->setAttribute("key",       $key);
		$tag->setAttribute("timeStart", $tcIn);
		$tag->setAttribute("timeEnd",   $tcOut);
		$tag->setAttribute("version",   0);
		$racine->appendChild($tag);
		$retours["value"] = $key." = [".$tcIn.", ".$tcOut."]";

		echo json_encode($retours);
	}
	
	//Change les metadonnées
	function editMetadata($key, $metadataKey, $metadataValue) {
		global $project;
		global $racine;
		$retours = array("success" => 1, "value" => "");

		//Tag de timeline
		$edition = $project->createElement("edition");
		$edition->setAttribute("key",           $key);
		$edition->setAttribute("metadataKey",   $metadataKey);
		$edition->setAttribute("metadataValue", $metadataValue);
		$edition->setAttribute("version",   0);
		$racine->appendChild($edition);
		$retours["value"] = $key." => '".$metadataKey."' = '".$metadataValue."'";

		echo json_encode($retours);
	}
	
	//Change les metadonnées
	function editProjectMeta($baliseName, $metadataKey, $metadataValue) {
		global $project;
		global $racine;
		$retours = array("success" => 1, "value" => "");

		//Tag de timeline
		$balise = $project->createElement($baliseName);
		$balise->setAttribute($metadataKey, $metadataValue);
		$racine->appendChild($balise);
		$retours["value"] = $metadataKey." = ".$metadataValue;

		echo json_encode($retours);
	}
	function editProjectMeta2($baliseName, $metadataKey, $metadataValue) {
		global $project;
		global $racine;
		$retours = array("success" => 1, "value" => "");

		//Tag de timeline
		$balise = $project->createElement($baliseName);
		$balise->setAttribute("ctg", $metadataKey);
		$balise->setAttribute("cnt", $metadataValue);
		$racine->appendChild($balise);
		$retours["value"] = $metadataKey." = ".$metadataValue;

		echo json_encode($retours);
	}
	
	
	//Renomme un projet Rekall
	function editProject($oldName, $newName) {
		$retours = array("success" => 1, "error" => "", "value" => "");
		$oldName = sanitize($oldName);
		$newName = sanitize($newName);

		if($newName == "")
			$newName = sha1(rand());
		if(file_exists("../".$oldName)) {
			rename("../".$oldName, "../".$newName);
		}
		else {
			$retours["success"] = 0;
			$retours["error"] = "Project doesn't exist";
		}

		$retours["value"] = $newName;

		echo json_encode($retours);
	}
	
	
	//Met à jour Rekall
	function update() {
		$retours = array("success" => 0, "error" => "", "value" => "");
		
		if(!file_exists("create.zip")) {
			$retours["value"] .= "Download of create.zip... ";
			downloadFile("http://project.memorekall.fr/create.zip", "create.zip");
		}

		$zip = new ZipArchive;
		$res = $zip->open("create.zip");
		if ($res === TRUE) {
			$retours["value"] .= "Moving files... ";
			rename("../file", "../file_cpy");

			$retours["value"] .= "Unzipping... ";
			$zip->extractTo("../");
			$zip->close();

			$retours["value"] .= "Cleaning... ";
			SureRemoveDir("../file", true);

			$retours["value"] .= "Moving files... ";
			rename("../file_cpy", "../file");

			$retours["value"] .= "Cleaning update... ";
			unlink("create.zip");
			$retours["success"] = 1;
		} else {
			$retours["success"] = -1;
			$retours["error"] = "No seed found";
		}

		echo json_encode($retours);
	}
	
	

	//API
	$_GET = array_merge($_GET, $_POST);
	if(!isset($_SESSION["canEdit"]))
		$_SESSION["canEdit"] = "auth_required";
	
	if(isset($_GET["p"])) {
		$sha1password = strtoupper(file_get_contents("../file/projectPassword.txt"));
		if(($_GET["p"] != "") && ($_GET["p"] == $sha1password))
			$_SESSION["canEdit"] = realpath(dirname(__FILE__));
		else
			$_SESSION["canEdit"] = "auth_required";
	}
	if($_SESSION["canEdit"] === realpath(dirname(__FILE__))) {
		//Opérations sur les fichiers
		if((isset($_GET["folder"])) && (isset($_GET["file"]))) {
			if(isset($_GET["remove"])) {
				openProject();
				removeDocumentFromProject("/".$_GET["folder"].$_GET["file"]);
				closeProject();
			}
			else if((isset($_GET["tcIn"])) && (isset($_GET["tcOut"]))) {
				openProject();
				editTc("/".$_GET["folder"].$_GET["file"], $_GET["tcIn"], $_GET["tcOut"]);
				closeProject();
			}
			else if((isset($_GET["metadataKey"])) && (isset($_GET["metadataValue"]))) {
				openProject();
				editMetadata("/".$_GET["folder"].$_GET["file"], $_GET["metadataKey"], $_GET["metadataValue"]);
				closeProject();
			}
		}
		else if(isset($_GET["key"])) {
			if(isset($_GET["remove"])) {
				openProject();
				removeDocumentFromProject($_GET["key"]);
				closeProject();
			}
			else if((isset($_GET["tcIn"])) && (isset($_GET["tcOut"]))) {
				openProject();
				editTc($_GET["key"], $_GET["tcIn"], $_GET["tcOut"]);
				closeProject();
			}
			else if((isset($_GET["metadataKey"])) && (isset($_GET["metadataValue"]))) {
				openProject();
				editMetadata($_GET["key"], $_GET["metadataKey"], $_GET["metadataValue"]);
				closeProject();
			}
		}
		else {
			if(isset($_GET["video"])) {
				openProject();
				editProjectMeta("video", "url", $_GET["video"]);
				closeProject();
			}
			else if(isset($_GET["remove"])) {
				SureRemoveDir(realpath(".."), true);
			}
			else if(isset($_GET["downloadXML"])) {
				$file_url = '../file/project.xml';
				header('Content-Type: application/octet-stream');
				header("Content-Transfer-Encoding: Binary"); 
				header("Content-disposition: attachment; filename=\"".basename($file_url)."\""); 
				readfile($file_url);
			}
			else if((isset($_GET["metadataKey"])) && (isset($_GET["metadataValue"]))) {
				openProject();
				editProjectMeta2("projectMeta", $_GET["metadataKey"], $_GET["metadataValue"]);
				closeProject();
			}
			else if((isset($_GET["edit"])) && (isset($_GET["to"]))) {
				editProject($_GET["edit"], $_GET["to"]);
			}
		}
	}
	if(isset($_GET["status"])) {
		//Récupère le lieu de l'édition
		//$details = json_decode(file_get_contents("http://ipinfo.io/{$_SERVER['REMOTE_ADDR']}"));
		$retour  = array("uploadMax" => file_upload_max_size(), "owner" => array("canEdit" => false, "author" => "", "locationGps" => "", "locationName" => ""));
		if((isset($details)) && (property_exists($details, "city")))
			$retour["owner"]["locationName"] = $details->city;
		if($_SESSION["canEdit"] === realpath(dirname(__FILE__)))
			$retour["owner"]["canEdit"] = true;
		$retour["owner"]["author"] = "";
		echo json_encode($retour);
	}
	else if(isset($_GET["update"])) {
		update();
	}
?>