<!DOCTYPE html>
<html>
<head>

<script src="http://www.parsecdn.com/js/parse-1.6.14.js"></script>

<meta name='keywords' content='WebRTC, HTML5, JavaScript' />
<meta name='description' content='WebRTC Reference App' />
<meta name='viewport' content='width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1'>

<link rel="stylesheet" href="style.css" type="text/css" />

<script type="text/javascript" src="https://code.jquery.com/jquery-2.2.3.js"></script>
<script type="text/javascript" src="underscore.js"></script>
<script src="http://d3js.org/d3.v3.js"></script>

<base target='_blank'>

<title>WebRTC client</title>

<!-- <link rel='stylesheet' href='css/main.css' /> -->

</head>

<body>

<div id='container'>

<div id="my-peer-id"></div>

<div id="leader-peer-id"></div>

<div id="handle"></div>

<br>
<br>

<form id="connect-form">
  Enter a Peer ID to connect to: <input type="text" name="peer-id" id="id-entry"><br>
  <input id="connect-btn" type="submit" value="Connect!">
</form>

<br>
<br>

<form id="name-form">
  Enter a handle: <input type="text" name="handle" id="handle-entry"><br>
  <input id="handle-btn" type="submit" value="Rename">
</form>

<br>
<br>

<form id="message-form">
  Enter a message for all peers: <input type="text" id="message-entry"><br>
  <input id="send-btn" type="submit" value="Send!">
</form>

<br>
<br>

<div id="log"></div>

</div>

<div id="graph"></div>

<script src="http://cdn.peerjs.com/0.3/peer.js"></script>

<script type="text/javascript">var my_id = "not set";</script>

<?php 
   function checkForLeader() {
      $f_name = "leader_peer_id.txt";

      $f_handle = fopen($f_name, r) or die ("Unable to access leader information!");

      if (filesize($f_name) > 0) {
         $leader_peer = fread($f_handle, filesize($f_name));
      } else {
         $leader_peer = "";
      }

      fclose($f_handle);

      return $leader_peer;
   }
?>

<script type="text/javascript">

   // Constants
   var MESSAGE = 0;
   var ADD_CONNECTION = 1;
   var USER_DISCONNECTED = 2;

   // Stores the incoming connections
   var connected_friends = [];
   var message_log = [];

   var global_conn;
   var peer = new Peer({key: 'is1zfbruud31sjor'});
   //var my_id = "not set";
   var handle = "not set";
   var leader = "not set";

   // Open the connection to the PeerJS servers, and update the label
   peer.on('open', function(id) {
      $("#my-peer-id").html("My ID is: <br /> <b>" + id + "</b>");
      my_id = id;
      
      // check if there is a leader
      leader = <?php echo json_encode(checkForLeader()); ?>;
      if (leader.length == 0) {
         // there is no leader, make me the leader!
         //window.location.href = "index.php?newLeader=" + my_id;
         $.ajax({
            url: 'updateLeader.php',
            type: 'GET',
            data: {'newID':my_id},
            success: function(resp) {
                leader = my_id;
                $("#leader-peer-id").html("The Leader is: <br /> <b>" + leader + "</b>")
            }
        }); 
      }
      else
      {
        $("#leader-peer-id").html("The Leader is: <br /> <b>" + leader + "</b>");
        leader_told_me_to_connect(leader);
      }
   });

   // Leader told me to connect!
   function leader_told_me_to_connect(new_peer_id) {
      // Verify I'm not already connected
      for (var ndx = 0; ndx < connected_friends.length; ndx++) {
         if (connected_friends[ndx].peer == new_peer_id ||
             my_id == new_peer_id) {
            return;
         }
      }

      var new_connection = peer.connect(new_peer_id);

      new_connection.on('open', function() {
         new_connection_established(new_connection);
      });
   }

   function receive_message(data) {
      console.log("Received message with data type " + data.type);
      switch (data.type) {
         case MESSAGE:
            if (data.username == handle) {
               $("#log").append(
                                $('<div/>')
                                .addClass("message")
                                .html("<b>me: </b>" + data.message)
                                );
            }
            else {
               $("#log").append(
                                $('<div/>')
                                .addClass("message")
                                .html("<b>" + data.username + ": </b>" + data.message)
                                );
            }
            message_log.push(data);
            break;
         case ADD_CONNECTION:
            // The leader has told us to add a new friend. DO IT
            leader_told_me_to_connect(data.new_id)
            break;
         case USER_DISCONNECTED:
            // Nothing yet, this will be used to remake routes etc.
            break;
         default:
            console.warn("Bad data.type value: " + data.type);
            break;   
      }
   }

   // Sends each message in the message log.
   function send_log(connection, messages) {
      for (var ndx = 0; ndx < messages.length; ndx++) {
      // Send the message log to the new user
      connection.send({
            type: MESSAGE,
            username: messages[ndx].username,
            message: messages[ndx].message});   
      }
   }


   // Called when I connect to someone, or when someone connects to me!
   function new_connection_established(connection) {
      $("#log").append(
                       $('<div/>')
                       .addClass("new-connection-message")
                       .html("<b>Successfully connected to " + connection.peer + "</b>")
                       );

      // TODO: smarter graphs
      graph.addNode(connection.peer);
      graph.addLink(connection.peer, handle);

      // Tell the new user about all my friends
      if (leader == my_id)
      {
        for (var ndx = 0; ndx < connected_friends.length; ndx++) {
           console.log("Telling the peer " + connection.peer + " all about my old friend " + connected_friends[ndx].peer);
           connection.send({
              type: ADD_CONNECTION,
              new_id: connected_friends[ndx].peer
           });

           // Dumb: link all the existing connections together
           graph.addLink(connection.peer, connected_friends[ndx].peer);
        }

        // Send the message log to the newly connected client.
        send_log(connection, message_log);
      }

      // This sets up a function to be called whenever we receive data
      //  from this connection.
      connection.on('data', function(data) {
         receive_message(data);
      });

      // Peer died! Let the user know.
      connection.on('close', function() {
         $("#log").append(
                          $('<div/>')
                          .addClass("connection-closed-message")
                          .html("<b>" + connection.peer + " disconnected </b>")
                          );
         remove_connected_friend(connected_friends.indexOf(connection));
      });

      connected_friends.push(connection);
   }

   	// Remove the specified friend from the connection list
   	function remove_connected_friend(index)
	{
		console.log("remove_connected_friend at index " + index);

		connected_friends.splice(index, 1);
	}

   // Stop the default form action (which is to open another page)
   $('#connect-form').submit(false);

   // This is called when the user hits "Connect"
   $("#connect-btn").click(function() {

      conn_id = $("#id-entry").val();
      
      if (conn_id.length > 0) {
         // Tries to connect to a new friend with the peer ID "other_id"
         var new_connection = peer.connect(conn_id);

         new_connection.on('open', function() {
            new_connection_established(new_connection);
         });

         // Clear the id field after connection established
         $('#id-entry').val("");
      }

      return false;
   });

   // Stop the default form action (which is to open another page)
   $('#message-form').submit(false);

   // This is called when the user hits "Send"
   $("#send-btn").click(function() {
      //find proper name
      var name = 0;
      if(handle != "not set") {
         name = handle;
      }
      else {
         name = my_id;
      }

      msg = $("#message-entry").val() ;

      if (msg.length > 0) {
         // Send a data message to all peers
         for (var ndx = 0; ndx < connected_friends.length; ndx++) {


         connected_friends[ndx].send({
            type: MESSAGE,
            username: name, 
            message: msg});
         }

         // Also print my own messages
         receive_message({
            type: MESSAGE,
            username: name, 
            message: msg});

         // Clear the message entry field after connection established
         $('#message-entry').val("");
      }

      return false;
   });

    $("#handle-btn").click(function() {
      //Rename handle and display
        handle = $("#handle-entry").val();

        if (handle.length > 0)
        {
         $("#handle").html("My handle is: <br /> <b>" + handle + "</b>");
         
         // Clear the message entry field after connection established
         $('#handle-entry').val("");
        }
      
        // TODO: smarter graphs
        graph.addNode(handle);

        return false;
   });

   // Listen for new peers connecting TO me
   peer.on('connection', function(incoming_connection) { 
      incoming_connection.on('open', function() {
         new_connection_established(incoming_connection);
      });
   });

   // Alert friends when we're disconnecting
   window.onbeforeunload = function() {
      for (var ndx = 0; ndx < connected_friends.length; ndx++) {
         connected_friends[ndx].send({
               type: MESSAGE,
               username: my_id, 
               message: "Goodbye everyone, I'm leaving!"});
      }
   }
</script>


<script type="text/javascript" src="graph.js"></script>

</body>
</html>
