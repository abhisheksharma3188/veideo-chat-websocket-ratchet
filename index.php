<?php

//////////////////////////// code to connect to database below //////////////////////////////////////
$hostname = 'localhost';
$username = 'root';
$password = 'alexander';
$database = 'video_chat_websocket_ratchet';
$connection = @mysqli_connect($hostname, $username, $password, $database);
//////////////////////////// code to connect to database below //////////////////////////////////////

///////////////////////////// code to get number of rows in users table below ////////////////////////
$query = 'SELECT
        *
        FROM
        users_table
        ';
$mysqli_prepare = mysqli_prepare($connection, $query);
mysqli_stmt_execute($mysqli_prepare);
$mysqli_stmt_get_result = mysqli_stmt_get_result($mysqli_prepare);
$mysqli_num_rows = mysqli_num_rows($mysqli_stmt_get_result);
///////////////////////////// code to get number of rows in users table above ////////////////////////

///////////////////////////// code to generate user id below /////////////////////////////////////////
$random_number = rand(100, 999);
$user_id = ($mysqli_num_rows + 1) . $random_number;
///////////////////////////// code to generate user id above /////////////////////////////////////////

///////////////////////////// code to get time stamp of user below ///////////////////////////////////
$user_timestamp = time();
///////////////////////////// code to get time stamp of user above ///////////////////////////////////

///////////////////////////// code to insert user id in users table below ////////////////////////////
$query = 'INSERT
        INTO
        users_table
        SET
        user_id=?
        ';
$mysqli_prepare = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($mysqli_prepare, 's', $user_id);
if (!mysqli_stmt_execute($mysqli_prepare)) {
    die;
}
///////////////////////////// code to insert user id in users table above ////////////////////////////
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <style>
            body {
                background: #0098ff;
                display: flex;
                height: 100vh;
                margin: 0;
                align-items: center;
                justify-content: center;
                padding: 0 50px;
                font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            }

            video {
                max-width: calc(50% - 100px);
                margin: 0 50px;
                box-sizing: border-box;
                border-radius: 2px;
                padding: 0;
                background: white;
            }

            .copy {
                position: fixed;
                top: 10px;
                left: 50%;
                transform: translateX(-50%);
                font-size: 16px;
                color: white;
            }
        </style>
    </head>

    <body>
        <div class="copy">Send your URL to a friend to start a video call</div>
        <video id="localVideo" autoplay muted></video>
        <video id="remoteVideo" autoplay></video>

        <script>
            ///////////////////////////// code to set variables below //////////////////////
            var user_id = '<?php echo $user_id; ?>';
            var user_timestamp = '<?php echo $user_timestamp; ?>';
            var remote_user_id;
            var remote_user_timestamp;
            var users_array = [user_id];
            var isOfferer = false;
            const configuration = {
                iceServers: [{
                    urls: 'stun:stun.l.google.com:19302'
                }]
            };
            let pc;
            ///////////////////////////// code to set variables above //////////////////////

            startWebRTC(isOfferer); // This code runs startWebRTC function so that local video start streaming, otherwise it will only stream after getting connected to other user.

            //////////////// code to create roomHash for unique web links below (this code is not compulsory) ////////////
            /*if (!location.hash) {
            location.hash = Math.floor(Math.random() * 0xFFFFFF).toString(16);
            }
            const roomHash = location.hash.substring(1);
            console.log(roomHash);*/
            //////////////// code to create roomHash for unique web links above (this code is not compulsory) ////////////

            ////////////////////////////// code to create object of wbesocket below ////////////////////////////////////
            var conn = new WebSocket('ws://localhost:8080'); // link of websocket server
            conn.onopen = function(e) { // fuction to open websocket connection
                console.log(e);
                console.log("Connection established!");
                sendUserIdAndTimestamp(user_id, user_timestamp);
            };
            ////////////////////////////// code to create object of wbesocket above ////////////////////////////////////

            ////////////////////////////// code to read messages below /////////////////////////////////////////////////
            conn.onmessage = function(e) { //socket function to read message
                //console.log(e.data);// received message in e.data
                var message = JSON.parse(e.data);
                console.log(message);

                ////////////// code to read sdp and candidate messages below ///////////
                try {
                    if (message.sdp != null) {
                        // This is called after receiving an offer or answer from another peer
                        pc.setRemoteDescription(new RTCSessionDescription(message.sdp), () => {
                            // When receiving an offer lets answer it
                            if (pc.remoteDescription.type === 'offer') {
                                pc.createAnswer().then(localDescCreated).catch(onError);

                            }
                        }, onError);
                    } else if (message.candidate != null) {
                        // Add the new ICE candidate to our connections remote description
                        pc.addIceCandidate(
                            new RTCIceCandidate(message.candidate), onSuccess, onError
                        );
                    }
                } catch (e) {

                }
                ////////////// code to read sdp and candidate messages below ///////////

                ///////////////// code to get user ids and timestamp from all users below //////////////
                if (message.user_id != null) {
                    remote_user_id = message.user_id;
                    if (users_array.indexOf(remote_user_id) < 0) {
                        if (users_array.length < 2) {
                            users_array.push(remote_user_id);
                            remote_user_timestamp = message.user_timestamp;
                            if (user_timestamp > remote_user_timestamp) {
                                isOfferer = true;
                                startWebRTC(isOfferer);
                            } else {
                                isOfferer = false;
                                startWebRTC(isOfferer);
                            }
                            console.log(users_array);
                            sendUserIdAndTimestamp(user_id, user_timestamp);
                        } else {
                            sendDisconnectMessageToThirdUser(remote_user_id);
                        }
                    }
                    ///////////////// code to get user ids and timestamp from all users above ///////////
                }
                //////////////////////////// code to disconnect the third users below //////////////
                if (message.disconnect == user_id) {
                    conn.close();
                }
                //////////////////////////// code to disconnect the third users above //////////////
            }
            ////////////////////////////// code to read messages above /////////////////////////////////////////////////

            ////////////////////////////// code to send message below //////////////////////////////
            function sendMessage(message) {
                conn.send(message); // socket function to send message
            }
            ////////////////////////////// code to send message above //////////////////////////////

            ///////////////////// code to send user id and timestamp below /////////////////////////
            function sendUserIdAndTimestamp(user_id, user_timestamp) {
                conn.send(`{"user_id":"${user_id}","user_timestamp":${user_timestamp}}`); // socket function to send message
            }
            ///////////////////// code to send user id and timestamp above /////////////////////////

            ///////////////////// code to send disconnect message for third user below /////////////////////////
            function sendDisconnectMessageToThirdUser(remote_user_id) {
                conn.send(`{"disconnect":"${remote_user_id}"}`); // socket function to send message
            }
            ///////////////////// code to send disconnect message for third user above /////////////////////////


            ///////////////////////// function to startWebRTC below ////////////////////////////////////////////

            function startWebRTC(isOfferer) {
                pc = new RTCPeerConnection(configuration);

                // 'onicecandidate' notifies us whenever an ICE agent needs to deliver a
                // message to the other peer through the signaling server
                pc.onicecandidate = event => {
                    if (event.candidate) {
                        //sendMessage({'candidate': event.candidate});
                        sendMessage(`{"candidate":${JSON.stringify(event.candidate)}}`);
                    }
                };

                // If user is offerer let the 'negotiationneeded' event create the offer
                if (isOfferer) {
                    pc.onnegotiationneeded = () => {
                        pc.createOffer().then(localDescCreated).catch(onError);
                    }
                }

                // When a remote stream arrives display it in the #remoteVideo element
                pc.onaddstream = event => {
                    remoteVideo.srcObject = event.stream;
                };

                navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: true,
                }).then(stream => {
                    // Display your local video in #localVideo element
                    localVideo.srcObject = stream;
                    // Add your stream to be sent to the conneting peer
                    pc.addStream(stream);
                }, onError);

                // Listen to signaling data from Scaledrone

            }
            ///////////////////////// function to startWebRTC above ////////////////////////////////////////////

            ///////////////////////// function localDescCreated below //////////////////////////////////////////
            function localDescCreated(desc) {
                pc.setLocalDescription(
                    desc,
                    () => sendMessage(`{"sdp":${JSON.stringify(pc.localDescription)}}`),
                    onError
                );
            }
            ///////////////////////// function localDescCreated above //////////////////////////////////////////

            ///////////////////////// function onError below ///////////////////////////////////////////////////
            function onError(error) {
                console.error(error);
            };
            ///////////////////////// function onError above ///////////////////////////////////////////////////

            ///////////////////////// function onSuccess below /////////////////////////////////////////////////
            function onSuccess() {};
            ///////////////////////// function onSuccess above ///////////////////////////////////////////////////
        </script>
    <body>
</html>    