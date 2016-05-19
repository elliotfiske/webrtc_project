<?php
	$url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	$parts = parse_url($url);
	parse_str($parts['query'], $query);

	$new_peer_id = $query['newID'];

	//if (strlen($new_peer_id) > 0) {

		$f_name = "leader_peer_id.txt";

		$f_handle = fopen($f_name, w) or die ("Unable to overwrite leader information!");

		fwrite($f_handle, $new_peer_id) or die ("Failed to Update leader");

		fclose($f_handle);

		echo "Updated Succesfully";
	/*}
	else
	{
		die ("No valid new peer id passed in");
	}*/
?>
