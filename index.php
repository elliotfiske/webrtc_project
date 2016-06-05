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
   <script type="text/javascript" src="leader_coup.js"></script>
   <script src="http://d3js.org/d3.v3.js"></script>

   <base target='_blank'>

   <title>WebRTC client</title>

   <!-- <link rel='stylesheet' href='css/main.css' /> -->

</head>

<body>
   <audio id="find-me" controls style="display: none;">
      <source src="ping.wav" type="audio/wav">
      Your browser does not support the audio element.
   </audio>

   <div id='container'>

      <div id="my-peer-id"></div>

      <div id="leader-peer-id"></div>

      <div id="handle"></div>

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

         $f_handle = fopen($f_name, 'r') or die ("Unable to access leader information!");

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
   var UPDATE_HANDLE = 3;
   var SELECT_NEW_LEADER = 4;
   var NEW_LEADER_ANNOUCEMENT = 5;
   var PLAY_SOUND = 6; // Please play a sound/show an alert so I can find your tab :O
   var UPDATE_GRAPH = 7; // When a new person joins the room, the leader will tell them to update the connections

   // Stores the incoming connections
   var connected_friends = [];
   var message_log = [];

   var global_conn;
   var peer = new Peer({key: 'is1zfbruud31sjor'});
   var my_id = "not set";
   var handle = "not set";
   var leader = {peer_id: "not set",
                 conn: null};

   function getHandle() {
      return handle;
   }

   // Open the connection to the PeerJS servers, and update the label
   peer.on('open', function(id) {
      $("#my-peer-id").html("My ID is: <br /> <b>" + id + "</b>");
      my_id = id;

      // TODO: smarter graphs
      graph.addNode(id);

      // check if there is a leader
      leader.peer_id = <?php echo json_encode(checkForLeader()); ?>;

      if (leader.peer_id.length == 0) {
         window.alert("No leader found, ASSUMING DIRECT CONTROL");
         // there is no leader, make me the leader!
         $.ajax({
            url: 'updateLeader.php',
            type: 'GET',
            data: {'newID':my_id},
            success: function(resp) {
               leader.peer_id = my_id;
               $("#leader-peer-id").html("The Leader is: <br /> <b>" + leader.peer_id + "</b>")
            }
         });
      }
      else
      {
         $("#leader-peer-id").html("The Leader is: <br /> <b>" + leader.peer_id + "</b>");
         leader_told_me_to_connect(leader.peer_id, "Leader", function(success, result) {
            if (success) {
               leader.conn = result;
            }
            else {
               // Leader connection timed out
               if (leader.conn === null) {
                  console.warn("Dead leader detected!!! Attempting to make myself leader!")
                  make_myself_leader(leader.peer_id);
               }
            }
         });
      }
   });

   // Leader told me to connect!
   function leader_told_me_to_connect(new_peer_id, new_handle, result_callback) {
      result_callback = result_callback || function(){};

      // Verify I'm not already connected
      for (var ndx = 0; ndx < connected_friends.length; ndx++) {
         if (connected_friends[ndx].their_id.peer == new_peer_id ||
          my_id == new_peer_id) {
            result_callback(connected_friends[ndx]);
            return;
         }
      }

      var new_connection = peer.connect(new_peer_id, {reliable: true});

      new_connection.on('open', function() {
         result_callback(true, new_connection);
         new_connection_established(new_connection, new_handle);
         console.log("Nice, found the leader and he's alive!")
      });

      new_connection.on('error', function(err) {
         console.warn("HEY: this FINALLY worked. Probably too late now tho :/");
      });

      // Wait and see if we successfully connected to the leader.
      setTimeout(function() {
         if (!new_connection.open) {
            result_callback(false, null);
         }
      }, 3000);
   }

   function receive_message(data, connection_id) {
      console.log("Received message with data type " + data.type);
      switch (data.type) {
         case MESSAGE:
            // check if I am leader, so can forward message as needed
            if (leader.peer_id == my_id) {
               // I am the leader, forward this message to everyone else*
               // *except for who sent the message!
               for (var ndx = 0; ndx < connected_friends.length; ndx++) {
                  if (connected_friends[ndx].their_id.peer != connection_id) {
                     connected_friends[ndx].their_id.send(data);
                  }
               }
            }

            // add this message to my log
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
            leader_told_me_to_connect(data.new_id, data.username);
            break;
         case USER_DISCONNECTED:
            // Nothing yet, this will be used to remake routes etc.
            //alert("User disconnected: " + data.username);
            peer_disconnected(data.disconnected_id);

            break;
         case UPDATE_HANDLE:
            // update the handle for the specified connection
            if (leader.peer_id == my_id) {
               // I am the leader, forward this message to everyone else*
               // *except for who sent the message!
               for (var ndx = 0; ndx < connected_friends.length; ndx++) {
                  if (connected_friends[ndx].their_id.peer != data.peer_id) {
                     connected_friends[ndx].their_id.send(data);
                  }
               }
            }

            console.log("Finding connected friend with peer: " + data.peer_id);
            var friend_ndx = findIndexOf(data.peer_id);
            console.log("Found connected friend with peer: " + data.peer_id + " at index: " + friend_ndx);
            if (friend_ndx >= 0) {
               console.log("changing username from: " + connected_friends[friend_ndx].username + ", to: " + data.username);
               connected_friends[friend_ndx].username = data.username;
            }

            update_handle(data.peer_id, data.username);
            break;
         case SELECT_NEW_LEADER:
            console.log("Running leader election");
            leader_election();
            break;
         case NEW_LEADER_ANNOUCEMENT:
            alert("New Leader going to be: " + data.peer_id);
            if (leader.peer_id == data.peer_id) {
               console.log("I agree that the leader should be you (" + data.peer_id + ")");
               var leader_ndx = findIndexOf(data.peer_id);
               console.log("Your in my connected friends at position: " + leader_ndx);
               if (leader_ndx >= 0) {
                  leader.conn = connected_friends[leader_ndx].their_id;
                  $("#leader-peer-id").html("The Leader is: <br /> <b>" + leader.peer_id + "</b>")
               }
               else {
                  alert("ERROR! Leader not one of my friends?!");
               }
            }
            break;
         case PLAY_SOUND:
            if (data.alert) {
               window.alert("Hello! It's me, " + handle);
            }
            $("#find-me").get(0).play();
            break;
         case UPDATE_GRAPH:
            if (leader.peer_id == my_id) {
               // forward this new connection on to everyone
               for (var ndx = 0; ndx < connected_friends.length; ndx ++) {
                  if (connected_friends[ndx].their_id.peer != data.from_id &&
                      connected_friends[ndx].their_id.peer != data.to_id) {
                     connected_friends[ndx].their_id.send(data);
                  }
               }
            }
            //console.log("Adding link ( " + data.from_id + "," + data.to_id + " )")
            graph.addLink(data.from_id, data.to_id);
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
            message: messages[ndx].message
         });
      }
   }


   // Called when I connect to someone, or when someone connects to me!
   function new_connection_established(connection, handle) {
      var my_handle = getHandle();

      $("#log").append(
         $('<div/>')
         .addClass("new-connection-message")
         .html("<b>Successfully connected to " + connection.peer + "</b>")
      );

      // TODO: smarter graphs
      graph.addNode(connection.peer);
      graph.addLink(connection.peer, my_id);

      connected_friends.push({their_id:connection, username:handle});

      // Tell the new user about all my friends
      if (leader.peer_id == my_id) {
         for (var ndx = 0; ndx < connected_friends.length; ndx++) {
            console.log("Telling the peer " + connection.peer + " all about my old friend " + connected_friends[ndx].their_id.peer);
            connection.send({
               type: ADD_CONNECTION,
               new_id: connected_friends[ndx].their_id.peer,
               new_handle: connected_friends[ndx].username
            });
         }

         // Send the message log to the newly connected client.
         send_log(connection, message_log);
      }
      else {
         // Tell everyone that this person connected to you
         leader.conn.send({
            type: UPDATE_GRAPH,
            to_id: connection.peer,
            from_id: my_id
         });

         // send out all of your connections to the new person
         for (var ndx = 0; ndx < connected_friends.length; ndx++) {
            connection.send({
               type: UPDATE_GRAPH,
               to_id: connected_friends[ndx].their_id.peer,
               from_id: my_id
            });
         };
      }

      //alert("Letting my new friend " + connection.peer + " know that my handle is: " + my_handle);
      connection.send({
         type: UPDATE_HANDLE,
         peer_id: my_id,
         username: my_handle
      });
      // This sets up a function to be called whenever we receive data
      //  from this connection.
      connection.on('data', function(data) {
         receive_message(data, connection.peer);
      });

      // Peer was somehow disconnected, try to re-establish connection if possible
      connection.on('disconnected', function() {
         alert("Connection (" + connection.peer + ") disconnected");
         // try to reconnect
         consloe.log("Trying to reconnect to: " + connection.peer);
         if (connection.disconnected && !connection.destroyed) {
            console.log("They were disconnected but not destroyed, attempting to reconnect now");
            connection.reconnect();
         }
         else {
            console.log("All hope is lost for them....");
         }
      });

      connection.on('error', function(err) {
         alert("Error occurred!");
         console.log(err);
      });

      // Peer died! Let the user know.
      connection.on('close', function() {
         peer_disconnected(connection.peer);
      });

      connection.on('error', function(err) {
         window.alert("PEER JS ERROR :(")
         window.alert(err.type);
      })
   }

   function peer_disconnected(disconnected_id) {
      var old_index_in_array = findIndexOf(disconnected_id);

      if (old_index_in_array >= 0) {
         $("#log").append(
            $('<div/>')
            .addClass("connection-closed-message")
            .html("<b>" + disconnected_id + " disconnected </b>")
         );
         console.log("Peer disconnected: " + disconnected_id);
         console.log("Leader is/was: " + leader.peer_id);
         remove_connected_friend(old_index_in_array);
         graph.removeNode(disconnected_id);
         if (disconnected_id == leader.peer_id)
         {
            // make sure to clear out the leader file
            /*
            $.ajax({
               url: 'updateLeader.php',
               type: 'GET',
               data: {'newID':""},
               success: function(resp) {
                  receive_message({
                     type: SELECT_NEW_LEADER
                  });
               }
            });
            */
            receive_message({
               type: SELECT_NEW_LEADER
            }, my_id);
         }
      }
   }

   function findIndexOf (connection_id)
   {
      for (var ndx = 0; ndx < connected_friends.length; ndx++)
      {
         if (connected_friends[ndx].their_id.peer == connection_id) {
            return ndx;
         }
      }
      return -1;
   }

   // Remove the specified friend from the connection list
   function remove_connected_friend(index) {
      if (index >= 0)
      {
         console.log("remove_connected_friend at index " + index);
         connected_friends.splice(index, 1);
      }
   }

   // Stop the default form action (which is to open another page)
   $('#message-form').submit(false);

   // This is called when the user hits "Send"
   $("#send-btn").click(function() {
      console.log("message to be sent out: " + $("#message-entry").val());

      //find proper name
      var name = 0;
      if(handle != "not set") {
         //alert("my handle is: " + handle);
         name = handle;
      }
      else {
         name = my_id;
      }

      msg = $("#message-entry").val() ;

      if (msg.length > 0) {
         // Send a data message to all peers

         // check if I am leader
         if (leader.peer_id == my_id) {
            // I am leader, send my message to all other friends
            receive_message({
               type: MESSAGE,
               username: name,
               message: msg
            }, my_id);
         }
         else {
            // I am not the leader, send my message to the leader to forward out
            leader.conn.send({
               type: MESSAGE,
               username: name,
               message: msg
            });
            // Also print my own messages - this could result in an out of order message?
            receive_message({
               type: MESSAGE,
               username: name,
               message: msg
            }, my_id);
         }

         // Clear the message entry field after connection established
         $('#message-entry').val("");
      }

      return false;
   });

   // Stop the default form action (which is to open another page)
   $('#handle-btn').submit(false);

   $("#handle-btn").click(function() {
      //Rename handle and display
      handle = $("#handle-entry").val();

      if (handle.length > 0) {
         $("#handle").html("My handle is: <br /> <b>" + handle + "</b>");

         // Clear the message entry field after connection established
         $('#handle-entry').val("");

         if (leader.peer_id == my_id) {
            receive_message({
               type: UPDATE_HANDLE,
               peer_id: my_id,
               username: handle
            }, my_id);
         }
         else {
            // tell the leader that I changed my handle
            leader.conn.send({
               type: UPDATE_HANDLE,
               peer_id: my_id,
               username: handle
            }, my_id);
         }
      }

      update_handle(my_id, handle);

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

      if (leader.peer_id == my_id) {
         // update leader on server to be empty to prevent someone who
         // joins from trying to connect to me
         $.ajax({
            url: 'updateLeader.php',
            type: 'GET',
            data: {'newID':""},
            success: function(resp) {
               for (var ndx = 0; ndx < connected_friends.length; ndx++) {
                  leader.conn.send ({
                     type: USER_DISCONNECTED,
                     disconnected_id: my_id
                  });
                  /*
                  connected_friends[ndx].their_id.send({
                     type: SELECT_NEW_LEADER
                  });
                  */
               }
            }
         });
      }
      else {
         leader.conn.send ({
            type: USER_DISCONNECTED,
            disconnected_id: my_id
         });
      }
   }

   function leader_election()
   {
      // find the peer with the greatest id
      var next_leader = my_id;
      console.log("Running Leader Election");

      for (var ndx = 0; ndx < connected_friends.length; ndx++) {
         console.log("comparng next_leader (" + next_leader + ") to connected_friend: (" + connected_friends[ndx].their_id.peer + ")");
         if (next_leader < connected_friends[ndx].their_id.peer)
         {
            next_leader = connected_friends[ndx].their_id.peer;
         }
      }

      console.log("I think the next leader should be: " + next_leader);

      if (next_leader == my_id) {
         $.ajax({
            url: 'updateLeader.php',
            type: 'GET',
            data: {'newID':my_id},
            success: function(resp) {
               // send a new leader annoucement packet to everyone
               for (var ndx = 0; ndx < connected_friends.length; ndx ++) {
                  console.log("Telling my friend " + connected_friends[ndx].their_id.peer + " that I am their new leader");
                  connected_friends[ndx].their_id.send({
                     type: NEW_LEADER_ANNOUCEMENT,
                     peer_id: my_id
                  });
               }
            }
         });
         // wait for leader ACKS?
         leader.peer_id = my_id;
         $("#leader-peer-id").html("The Leader is: <br /> <b>" + leader.peer_id + "</b>")
      }
      else {
         leader.peer_id = next_leader;
      }
   }

   // When we click a node, ask it to play a sound!
   function ask_for_sound(other_id, show_alert) {
      for (var ndx = 0; ndx < connected_friends.length; ndx++) {
         if (connected_friends[ndx].their_id.peer == other_id) {
            connected_friends[ndx].their_id.send({
               type: PLAY_SOUND,
               alert: show_alert
            });
         }
      }
   }

   </script>


<script type="text/javascript" src="graph.js"></script>

</body>
</html>
