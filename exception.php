<?php
class Exception_ConflictingRevisions extends Exception {
	public function __construct($path, $rev, $revision, $dbx_rev, $dbx_revision) {
		$this->message = "Conflicting revisions on \"".$path."\":\nLocal revision ".$rev." (".$revision.")\nDropbox revision ".$dbx_rev." (".$dbx_revision.")\n";
	}
}
class Exception_NotExpectingFile extends Exception {

}
?>