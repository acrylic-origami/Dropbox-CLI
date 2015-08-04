<?php
error_reporting(E_ERROR | E_WARNING);
//The race conditions are SO real omfg
define("SUPER", dirname(__DIR__));
define("DBX_DIRECTORY_SEPARATOR", '/');

chdir(__DIR__);

require_once "lib/Dropbox/autoload.php";
use \Dropbox as dbx;

require_once "helper.php";
require_once "exception.php";
require_once "gitspinner.php";
list($m_user, $m_pass, $db) = json_decode(file_get_contents(__DIR__."/mysql_creds.json"), true);
$mysqli = mysqli_connect("localhost", $m_user, $m_pass, $db);
// $appInfo = dbx\AppInfo::loadFromJsonFile(__DIR__."/creds.json");
// $webAuth = new dbx\WEbAuthNoRedirect($appInfo, "PHP-Example/1.0");
$dbxClient = new dbx\Client(file_get_contents(__DIR__."/tok.txt"), "Ad Hoc CLI");
if(!is_dir("../.git")) {
	exec(gitSpinner::GIT."init");
	exec("cat gitignore > ../.gitignore");
}
switch($argv[1]) {
	case "push":
		pushInterface::go($dbxClient, $mysqli);
		// foreach($deleted as $p) {
		// 	Helper::remove_empty_directories($p, $dbxClient);
		// }
		exec(gitSpinner::GIT."commit -m \"Push to Dropbox: ".@date("D M d, Y @ h:i:s")."\"");
		break;
	case "pull":
		//NOT SAFE FOR PUBLIC USE: ALL ERROR MESSAGES ARE TOO DESCRIPTIVE!

		
			chdir(SUPER);

			for($i=2; $i<count($argv); $i++) {
				if(preg_match('/^[^\*\?\"\<\>\|\:]*$/',$argv[$i])) {
					$sub = realpath(trim($argv[$i]));
					if(strpos($sub, SUPER)===0 && __DIR__!==$sub) {
						//$argv[2] is a subdirectory of __DIR__/../
						if(!is_dir($sub) && !is_file($sub)) {
							echo "ERROR: File/directory \"".$sub."\" does not exist.";
							end;
						}

						$dropbox_sub = substr($sub, strlen(SUPER)) ?: "/"; //fringe case: if there is a file at path/to/app as well as the directory path/to/app/
						if($dbxClient->getMetadata($dropbox_sub)===NULL) {
							echo "ERROR: The file/directory \"".substr($sub, strlen(SUPER))."\" does not exist in Dropbox.";
							end;
						}
						exec("git commit -am \"Backup before pulling from Dropbox: ".@date("D M d, Y @ h:i:s")."\"");
						if(is_dir($sub))
							chdir($sub);
						else
							chdir(dirname($sub)); //go to superdirectory of file

						if(SUPER===$sub) {
							$query = "find .";
							$gitignore = preg_split("[\n\r|\r\n|\n|\r]", file_get_contents(__DIR__.DIRECTORY_SEPARATOR."gitignore"));
							foreach($gitignore as $ignore) {
								$query.=" -not -path \"./".$ignore."*\"";
							}
							$query.=" -not -name \".\" -not -name \"..\" -print0 | xargs -0 rm -rf";
							echo $query;
							exec($query);
						}
						chdir(SUPER);
						Helper::download($dropbox_sub, $dbxClient, $mysqli);
						exec("git commit -am \"Initial commit after pulling from Dropbox: ".@date("D M d, Y @ h:i:s")."\"");
					}
					else {
						echo "ERROR: Permission denied to file/directory: \"".$sub."\".\n";
					}
				}
				else {
					echo "ERROR: Invalid file/directory path at: \"".$argv[$i]."\".\n";
				}
			}
		break;
	case "sync":
		//Cricket cricket...
		break;
	default:
		echo "\"".$argv[1]."\"";
		?> is not a valid option. Valid options for this tool include:
	push - pushes all revised files to Dropbox and commits the changes to git;
	pull - pulls all revised files from Dropbox, and creates an initial commit to git for new files
	sync - does both?
<?php
		end;
}
// $f = fopen(__DIR__."/test.txt", "rb");
// // $result = $dbxClient->uploadFile("/test.txt", dbx\WriteMode::update(), $f);
// // fclose($f);
// // print_r($result);
// try {
// 	$folderMetadata = $dbxClient->getRevisions("test.txt");
// }
// catch(Dropbox\Exception_BadResponseCode $e) {

// }
// print_r($folderMetadata);
?>
