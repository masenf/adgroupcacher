<?php
// array_file.php - store and retrieve php arrays as files

/* array_load -- load and unserialize filename
 *	returns the data stored in filename or
 *  False on error
 */
function array_load ( $filename )
{
	if (!is_file($filename)) {
		return False;
	}

	// read the file
	$contents = file_get_contents($filename);

	// if its empty we have no data also
	if (empty($contents)) {
		return False;
	}

	$data = unserialize($contents);

	// check for invalid data
	if ($data === FALSE) {
		// delete invalid files
		@unlink($filename);
		return False;
	}

	// place the settings array data
	return $data;
}
/* array_store -- serialize and write arrData to filename
 *	returns the number of bytes written on success or 
 *  False on error
 */
function array_store( $arrData, $filename ) 
{
	$content = serialize($arrData);
	return file_put_contents($filename, $content, LOCK_EX);
}
?>
