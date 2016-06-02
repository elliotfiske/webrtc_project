<?php

$f_name = "leader_peer_id.txt";

$f_handle = fopen($f_name, 'r') or die ("Unable to access leader information!");

if (filesize($f_name) > 0) {
   $leader_peer = fread($f_handle, filesize($f_name));
} else {
   $leader_peer = "";
}

fclose($f_handle);

echo $leader_peer;

?>
