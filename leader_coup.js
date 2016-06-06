// Handles the situation where a client can't connect to the given leader ID
function make_myself_leader(dead_leader_id) {
   // See if the current leader is LESS than my ID.
   $.ajax({
      url: 'getLeader.php',
      type: 'GET',
      success: function(resp) {
         if (resp == dead_leader_id ||
             resp < my_id) {
                $.ajax({
                  url: 'updateLeader.php',
                  type: 'GET',
                  data: {'newID':my_id},
                  success: function(resp) {
                     leader.peer_id = my_id;
                     $("#leader-peer-id").html("The Leader is: <br /> <b>" + leader.peer_id + "</b> That's me!");
                     update_leader(leader.peer_id);
                  }
               });
         }
         else {
            // I will defer to this new leader.
            $("#leader-peer-id").html("The Leader is: <br /> <b>" + resp + "</b>");
            leader_told_me_to_connect(resp, function(success, result) {
               if (success) {
                  leader.conn = result;
                  leader.peer_id = resp;
                  update_leader(resp);
               }
               else {
                  // Leader connection timed out
                  if (leader.conn === null) {
                     make_myself_leader(resp);
                  }
               }
            });
         }
      }
   });
}
