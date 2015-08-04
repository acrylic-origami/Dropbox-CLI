<?php
class Helper {

	private static $contents_count = array();
	private static $gitignore;

	public static function remove_empty_directories($path, $dbx) {
		if(!file_exists(dirname(__DIR__).$path)) {
			if($contents_count[$path]===0) {
				//contents of directory empty
				try {
					$dbx->remove($path);
				}
				catch(Dropbox\Exception_BadResponseCode $e) {}
			}
			else {
				try {
					//have to make sure that our local copy is faithful
					$response = $dbx->getMetadataWithChildren($path);
					if(($contents_count[$path]=count($response["contents"]))===0) {
						$dbx->remove($path);
						$contents_count[dirname($path)]--;
					}
					self::remove_empty_directories(dirname($path), $dbx);
				}
				catch(Dropbox\Exception_BadResponseCode $e) {
					switch($e->getStatusCode()) {
						case 404:
							echo "Removing empty directories failed for \"".$path."\" (404): superdirectories will not be touched. Continuing...";
							break;
					}
				}
			}
		}
	}
	public static function download($path, $dbx, $mysqli) {
		$metadata = $dbx->getMetadataWithChildren($path);
		if(is_null(self::$gitignore)) {
			self::$gitignore = preg_split("[\n\r|\r\n|\n|\r]", file_get_contents(__DIR__."/gitignore"));
		}
		if(!$metadata["is_dir"]) {
			$metadata["contents"] = array($metadata);
		}
		echo $path;
		foreach($metadata["contents"] as $s) {
			// echo "/^".preg_quote($path, '/').($path[strlen($path)-1]==='/'?'':'\/')."[^\/]*\/?$/\n";
			if(preg_match("/^".preg_quote($path, '/').($path[strlen($path)-1]==='/'?'':'\/')."[^\/]*\/?$/", $s["path"])) {
				//$s is a direct child of $path
				$flag = false;
				if($path==="/") {
					foreach(self::$gitignore as $ignore) {
						if(fnmatch("/".$ignore."*", $s["path"])) {
							//shoddy-ish function, since .gitignore isn't exactly the same as globs, but whatever...
							$flag = true;
						}
					}
				}
				if(!$flag) {
					$f = NULL;
					if($s["is_dir"]) {
						mkdir(".".$s["path"]); //MAKES SURE it's the current directory "."
						self::download($s["path"], $dbx, $mysqli);
					}
					else {
						$f = fopen(".".$s["path"], "w");
						$dbx->getFile($s["path"], $f);
						fclose($f);
					}
					mysqli_query($mysqli, "INSERT INTO revs (path, rev, revision) VALUES (\"".$s["path"]."\",\"".$s["rev"]."\", ".$s["revision"].") ON DUPLICATE KEY UPDATE rev=\"".$s["rev"]."\", revision=".$s["revision"].", deleted_flag=0;");
				}
			}
		}
	}
}
?>