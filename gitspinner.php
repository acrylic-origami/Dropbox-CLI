<?php
abstract class gitSpinner {
	const GIT = "git --git-dir='../.git' --work-tree=../ ";
	protected static $mysqli;
	protected static $dbx;

	abstract protected static function m($rest, Exception $e=NULL);
	abstract protected static function a($rest, Exception $e=NULL);
	abstract protected static function d($rest, Exception $e=NULL);
	abstract protected static function r($rest, Exception $e=NULL);
	private static function removeslashes($string) {
	    $string=implode("",explode("\\",$string));
	    return stripslashes(trim($string));
	}
	public static function go($dbx, $mysqli) {
		if(is_null(self::$mysqli))
			self::$mysqli = $mysqli;
		if(is_null(self::$dbx))
			self::$dbx = $dbx;

		$revisions = array();
		$deleted = array();
		exec(self::GIT."add --all :/");
		exec(self::GIT."status --porcelain", $revisions);
		foreach($revisions as $rev) {
			$code = $rev[0]; //all files are staged: only care about the first flag
			$rest = trim(stripslashes(substr($rev, 3)), "\"");
			switch($code) {
				//case modified file
				case "M":
					try {
						$old_rev = $dbx->getRevisions($rest);
						print_r($old_rev);
						$sql = "SELECT rev FROM revs WHERE path=".$rest;
						$result = mysqli_query(self::$mysqli, $sql);
						$rev = mysqli_fetch_array($result, MYSQLI_NUM);
						if($old_rev!=$rev[0]) {
							throw new Exception_ConflictingRevisions;
						}
						static::m($rest);
					}
					catch(Exception $e) {
						static::m($rest, $e);
					}
					break;

				//case added file
				case "A":
					try {
						if(is_null(self::$dbx->getMetadata("/".$rest))) {
							static::a($rest);
						}
						else {
							throw new Exception_NotExpectingFile;
						}
					}
					catch(Exception $e) {
						static::a($rest, $e);
					}
					break;

				//case deleted file
				case "D":
					try {
						static::d($rest);
					}
					catch(Exception $e) {
						static::d($rest, $e);
					}
					break;

				//case renamed
				case "R":
					try {
						static::r($rest);
					}
					catch (Exception $e) {
						static::r($rest, $e);
					}
					break;
			}
		}
	}
}
class pushInterface extends gitSpinner {
	private static $deleted = array();
	protected static function m($rest, Exception $e=NULL) {
		switch(true) {
			case ($e instanceof Dropbox\Exception_BadResponseCode):
				switch($e->getStatusCode()) {
					case 404:
						//no file exists: create it!
						$dir_meta = self::$dbx->getMetadata("/".dirname($rest));
						if(is_null($dir_meta) || !$dir_meta["is_dir"]) {
							self::$dbx->createFolder(dirname($rest));
						}
						break;
					case ($e!==NULL):
						exec(self::GIT."reset HEAD ../".$rest); //unstage this file if an exception occurs
					case 406:
						//...
						return;
					case 403:
						//...
						return;
				}
				break;
			case ($e!==NULL):
				exec(self::GIT."reset HEAD ../".$rest); //unstage this file if an exception occurs
			case ($e instanceof Exception_ConflictingRevisions):
				echo "WARNING: File \"".$rest."\" was not uploaded due to conflicting revisions. Resolve manually. Continuing...\n";
				//Dropbox has an option to name a "conflicted copy", but this allows us to define the behaviour.
				return; //equivalent of continue;
		}

		$f = fopen(SUPER.DIRECTORY_SEPARATOR.$rest, "r");
		$response = self::$dbx->uploadFile(DBX_DIRECTORY_SEPARATOR.$rest, @\Dropbox\WriteMode::update(), $f);
		fclose($f);
		$sql = "UPDATE revs SET rev=\"".$response["rev"]."\" WHERE path=".$rest." LIMIT 1;";
		mysqli_query(self::$mysqli, $sql);
	}

	protected static function a($rest, Exception $e=NULL) {
		switch(true) {
			case ($e!==NULL):
				exec(self::GIT."reset HEAD ../".$rest); //unstage this file if an exception occurs
			case ($e instanceof Exception_NotExpectingFile):
				echo "WARNING: File \"".$rest."\" exists on Dropbox already, but there is no history of it locally. Upload the file to Dropbox manually if this version is the most recent, delete the file and run `php main.php pull`. Continuing...\n";
				return;
			case ($e instanceof Dropbox\Exception_BadResponseCode):
				echo "WARNING: ".$e->getMessage()." Continuing...\n";
				return;
			case ($e instanceof Dropbox\Exception_BadRequest):
			case ($e instanceof InvalidArgumentException):
				echo "WARNING: Request failed, likely due to bad characters. Dropbox says: \"".$rest."\" with message: ".$e->getMessage()." Continuing...\n";
				return;
			case ($e!==NULL):
				//o_O
				echo "Unexpected Exception met on \"".$rest."\" with message: ".$e->getMessage()." Continuing...\n";
				return;
		}
		// upload at will; file does not exist!
		$f = fopen(SUPER.DIRECTORY_SEPARATOR.$rest, "r");
		$response = self::$dbx->uploadFile(DBX_DIRECTORY_SEPARATOR.$rest, \Dropbox\WriteMode::add(), $f);
		fclose($f);
		$sql = "INSERT INTO revs (path, rev, revision) VALUES (\"".$rest."\", \"".$response["rev"]."\", ".$response["revision"].") ON DUPLICATE KEY UPDATE rev=\"".$response["rev"]."\", revision=".$response["revision"].", deleted_flag=0;";
		mysqli_query(self::$mysqli, $sql);
	}

	protected static function d($rest, Exception $e=NULL) {
		switch(true) {
			case ($e!==NULL):
				exec(self::GIT."reset HEAD ../".$rest); //unstage this file if an exception occurs
			case ($e instanceof Dropbox\Exception_BadResponseCode):
				switch($e->getStatusCode()) {
					case 404:
						echo "WARNING: File \"".$rest."\" doesn't exist on Dropbox, and was not deleted. Ensure its superdirectory structure matches that of Dropbox, and run re-`pull` if necessary. Continuing...";
						return;
					case 406:
						echo "WARNING: Too many files to delete (somehow). Continuing...";
						return;

				}
				return; //for the sake of generality
			case ($e instanceof Exception_ConflictingRevisions):
			case ($e instanceof Exception_ExpectingFile):
				echo "WARNING: Conflicting revisions: the file was not deleted. Exception returned:\n".$e->getMessage();
				return;
		}

		$result = mysqli_query(self::$mysqli, "SELECT rev, revision FROM revs WHERE path=\"".$rest."\";");
		$rev = mysqli_fetch_array($result, MYSQLI_NUM);
		$dbx_rev = self::$dbx->getMetadata(DBX_DIRECTORY_SEPARATOR.$rest);
		if(is_null($dbx_rev))
			throw new Exception_ExpectingFile;
		if($rev[0]!=$dbx_rev["rev"]) {
			throw new Exception_ConflictingRevisions(DBX_DIRECTORY_SEPARATOR.$rest, $rev[0], $rev[1], $dbx_rev["rev"], $dbx_rev["revision"]);
		}

		self::$dbx->delete(DBX_DIRECTORY_SEPARATOR.$rest);
		mysqli_query(self::$mysqli, "UPDATE revs SET delete_flag=1 WHERE path=\"".$rest."\";");
		array_push(self::$deleted, $rest);
	}

	protected static function r($rest, Exception $e=NULL) {
		switch(true) {
			case ($e!==NULL):
				exec(self::GIT."reset HEAD ../".$rest); //unstage this file if an exception occurs
			case ($e instanceof Dropbox\Exception_BadResponseCode):
				switch($e->getStatusCode()) {
					case 403:
						echo "WARNING: The move \"".$from."\" -> \"".$to."\" is invalid. Repair manually. Continuing...";
						return;
					case 404:
						echo "WARNING: File \"".$from."\" doesn't exist on Dropbox, and was not moved. Ensure its superdirectory structure matches that of Dropbox, and re-`pull` if necessary. Continuing...";
						return;
					case 406:
						echo "WARNING: Too many files to move (somehow). Continuing...";
						return;
				}
				return; //doesn't matter; just for the sake of generality
			case ($e instanceof Dropbox\Exception_BadRequest):
			case ($e instanceof InvalidArgumentException):
				echo "WARNING: Request failed, likely due to bad characters. File not reuploaded. Dropbox says: \"".$rest."\" with message: ".$e->getMessage()." Continuing...\n";
				return;
			case ($e!==NULL):
				print_r($e);
				return;
		}

		list($from, $to) = explode(" -> ", $rest);
		$response = self::$dbx->move(DBX_DIRECTORY_SEPARATOR.$from, DBX_DIRECTORY_SEPARATOR.$to);
		$sql = "INSERT INTO revs (path, rev, revision) VALUES (\"".$to."\", \"".$response["rev"]."\", ".$response["revision"].") ON DUPLICATE KEY UPDATE rev=\"".$response["rev"]."\", revision=".$response["revision"].", delete_flag=0;";
		mysqli_query(self::$mysqli, $sql);
	}
}
// class syncInterface extends gitSpinner {
// 	protected static function m() {
// 		//if modified, same revision
// 	}
// 	protected static function a() {}
// 	protected static function d() {}
// 	protected static function r() {}
// }
?>