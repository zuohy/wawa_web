
var videoController = {};


videoController = function (config) {
    "use strict";
    
    //interface functions for application
    var that = {};
    
    //viewLocal:
    //{isLocal: true, view: "localVideo", maxWidth:352, maxHeight: 288}
    var viewLocal = {};
    var viewRemote = {};
    var wsSocket;
    var iceServer;
    var onCallBack;
    var errCallBack;
    var isInit = false;
    var roomName;
    var mediaConfig = config;
    //var videoStatus = {"InitReady", "JoinRoom", "PeerConnected", "VideoError"};
    
    //localst:
    //{streamid: , stream: , pc:}
    //remotest:
    //{streamid: , pc:}
    //room:
    //{roominifo: , lstreams: , rstreams: }
    var viemList = {};
    var connection = {};
    var room = {};
    var localst = {};
    var localstreams = {};
    var remotestreams = {};
    var streamlist = [];
    var muteVideo = true;

    var USER_TYPE_PUBLISHER = 1;  //publisher
    var USER_TYPE_ACTOR = 2;    //actor   //not used
    var USER_TYPE_LOOKER = 3;  //looker
    
    ////////////////////////////stream functions////////////////////////////////////////
    
    var GetRemoteStreams = function(streamList){
        var streams ={};
        var i;
        
        for(i=0; i<streamList.length; i++){
            var id = streamList[i].id;
            streams[id] = streamList[i];
            streams.pc = {};
        }
        return streams;
    };
    
    /*
     var qvgaConstraints  = {
     audio: true,
     video: true,
     video: {
     mandatory: {
     maxWidth: 352,
     maxHeight: 288,
     maxFrameRate: 15
     }
     }
     };
     */
    
    
    var getUserMedia = function(constraints, callback){
        navigator.webkitGetUserMedia(constraints, function(stream){
                                     //绑定本地媒体流到video标签用于输出
                                     document.getElementById(viewLocal.view).src = URL.createObjectURL(stream);
                                     
                                     //向PeerConnection中加入需要发送的流
                                     callback(stream);
                                     
                                     
                                     }, function(error){
                                     //处理媒体流创建失败错误
                                     console.log("getUserMedia error:" + error);
                                     });
        
    };
    
    var SendSubscribe = function(stream){
        if(stream.role != USER_TYPE_PUBLISHER){
            log.info("Not need to subscribe:", stream.role);
            return;
        }
        wsSocket.send(JSON.stringify({
                                     "msgname": "subscribe",
                                     "stream": stream
                                     }), function(error){
                      if(error){
                      log.info("Send message error:", error);
                      }
                      });
       	
    };
    
    var SubscribeToStream = function(streams){
       	var i;
       	for(i=0; i<streams.length; i++){
            var st = streams[i];
            if(localst.streamid != st.id){
                SendSubscribe(st);
            }
       	}
    };
    
    var RecordingStart = function(){
        console.log("start recording");
        
        if (localst){
            wsSocket.send(JSON.stringify({
                                         "msgname": "recording",
                                         "streamid": localst.streamid
                                         }), function(error){
                          if(error){
                          log.info("Send message error:", error);
                          }
                          });
            
        }
        
    };
    
    var RecordingStop = function(){
        console.log("stop recording");
        
        if (localst){
            wsSocket.send(JSON.stringify({
                                         "msgname": "stoprecorder",
                                         "streamid": localst.streamid
                                         }), function(error){
                          if(error){
                          log.info("Send message error:", error);
                          }
                          });
        }
    };
    ////////////////////////////connection functions////////////////////////////////////////
    
    connection.webrtcconnection = function(spec) {
        "use strict";
        var that = {};
        var _ice = spec;
        var _stream;
        var _callback;
        
        var _localDesc;
        var _pc = new webkitRTCPeerConnection(_ice);
        var _remoteDescriptionSet = false;
        var _localCandidates = [];
        
        
        var setLocalDescription = function(desc){
            
        };
        
        _pc.onaddstream = function(stream){
            if(that.onaddstream){
                that.onaddstream(stream);
            }
        };
        
        var removeRemb = function (sdp) {
            var newSdp = "";
            newSdp = sdp.replace(/a=rtcp-fb:(\d+) goog-remb[\r][\n]/g,'');
            return newSdp;
        };
        
        var sendOfferFn = function(desc){
            _pc.setLocalDescription(desc);
            _localDesc = desc;
            console.log("offer:" + desc.sdp);
            
            //limit bandwidth value
            //var str = JSON.stringify(_localDesc);
            //var strSubs = str.replace(/c=IN/g, "b=AS:600\\r\\nc=IN");
            
            //var strSdp = _localDesc.sdp;
            //strSdp = removeRemb(strSdp);
            //_localDesc.sdp = strSdp;
            
            
            _callback(_localDesc);
            
        };
        
        that.addStream = function(stream, callback){
            _stream = stream;
            _callback = callback;
            if(_stream){
                _pc.addStream(_stream);
            }
        };
        
        that.mediaConstraints = {
            mandatory : {
                "OfferToReceiveVideo": true,
                "OfferToReceiveAudio": true
            }
        };
        
        that.createOffer = function(isSubscriber){
            if(isSubscriber){
                _pc.createOffer(sendOfferFn, function (error) {
                                console.log("PeerConnection create offer failed, error:" + error);
                                }, that.mediaConstraints);
            } else {
                _pc.createOffer(sendOfferFn, function (error) {
                                console.log("PeerConnection create offer failed, error:" + error);
                                });
            }
        };
        //    	that.getPc(){
        //    		return pc;
        //    		};
        
        
        that.processSignalingMessage = function (msg) {
            if (msg.type == "offer") {
                
            } else if (msg.type == "answer"){
            	msg.sdp = msg.sdp.replace(/\r\n/g, "\n");
            	msg.sdp = msg.sdp.replace(/\n/g, "\r\n");
            	console.log("answer:" + msg.sdp);
                _pc.setRemoteDescription(new RTCSessionDescription(msg));
                _remoteDescriptionSet = true;
                
                console.log("Local candidates to send:", _localCandidates.length, _localCandidates);
                while(_localCandidates.length > 0) {
                    // IMPORTANT: preserve ordering of candidates
                    _callback({type:"candidate", candidate: _localCandidates.shift()});
                }
            }
        };
        
        // 发送ICE候选到其他客户端
        _pc.onicecandidate = function(event){
            if (event.candidate) {
                if (!event.candidate.candidate.match(/a=/)) {
                    event.candidate.candidate ="a="+event.candidate.candidate;
                };
                
                if (_remoteDescriptionSet) {
                    console.log("Local candidate to send:", event.candidate);
                    _callback({type:"candidate", candidate: event.candidate});
                } else {
                		console.log("Local candidate to store:", event.candidate);
                    _localCandidates.push(event.candidate);
                    console.log("Local candidates stored: ", _localCandidates.length, _localCandidates);
                }
                
            } //if (event.candidate)
        };
        
        return that;
    };
    
    
    var wsSocketListen = function(wsSocket, roomId, extramsg){
        var socket = wsSocket;
        
        socket.onopen = function(session){
            
            console.log("Join room, extra msg: ", extramsg);
            var extramsgobj = JSON.parse(extramsg);
            //	      	if (extramsg != null) {
            socket.send(JSON.stringify({
                                       "msgname": "create_room",
                                       "name": roomId,
                                       "extramsg":extramsgobj
                                       }), function(error){
                      if(error){
                      log.info("Send message error:", error);
                      }
                      });
            //          } else {
            //          	socket.send(JSON.stringify({
            //                                 "msgname": 'create_room',
            //                                 "name": roomId
            //                                 }));
            //          }
            room.info = {};
            room.lstreams = localstreams;
            room.rstreams = remotestreams;
            
            
        };
        
        socket.onclose = function(session){
            console.log("Websocket closed!");
        };
        
        ////////////////////////////handle receive messages////////////////////////////////////////
        socket.onmessage = function(event){
            var json = JSON.parse(event.data);
            var connpc;
            
            console.log("On message: ", event.data);
            
            
            switch(json.msgname)
            {
                    //////////////////////create_room_erizo////////////////////////////
                case "create_room_erizo":
                    
                    room.info = json.roominfo;
                    streamlist = json.streamlist;
                    console.log("Get join room response, room info: ", room.info, "stream list:", streamlist);
                    
                    getUserMedia(mediaConfig, function(stream){
                                 localst.stream = stream;
                                 socket.send(JSON.stringify({
                                                            "msgname": "publish"
                                                            }), function(error){
                      if(error){
                      log.info("Send message error:", error);
                      }
                      });
                                 });
                    
                    
                    break;
                    //////////////////////signaling_message_erizo////////////////////////////
                case"signaling_message_erizo":
                    var msg = json.msg;
                    
                    
                    
                    //process signa message
                    switch(msg.type)
                {
                        //////////////////////initializing////////////////////////////
                    case"initializing":
                        var tmpStream = {};
                        var isSubscriber = false;
                        if(tmpStream.pc == undefined){
                            tmpStream.pc = {};
                        }
                        if(localst.pc == undefined){
                            localst.pc = {};
                        }
                        tmpStream.pc = new connection.webrtcconnection(iceServer);
                        
                        
                        if(json.peerid){
                            isSubscriber = true;
                            tmpStream.streamid = json.peerid;
                            remotestreams[json.peerid] = tmpStream;
                            
                        }else{
                            tmpStream.streamid = json.streamid;
                            
                            tmpStream.stream = localst.stream;
                            localst = tmpStream;
                            
                            
                            localstreams[json.streamid] = tmpStream;
                            
                            
                        }
                        
                        if(json.peerid){
                            tmpStream.pc.onaddstream = function(evt){
                                document.getElementById(viewRemote.view).src = URL.createObjectURL(evt.stream);
                            };
                        }
                        
                        tmpStream.pc.addStream(tmpStream.stream, function(desc){
                                               socket.send(JSON.stringify({
                                                                          "msgname": "signaling_message",
                                                                          "streamid": tmpStream.streamid,
                                                                          "data": desc
                                                                          }), function(error){
                      if(error){
                      log.info("Send message error:", error);
                      }
                      });
                                               
                                               });
                        tmpStream.pc.createOffer(isSubscriber);
                        
                        
                        break;
                        
                        //////////////////////failed////////////////////////////
                    case"failed":
                        //disconnection
                        
                        break;
                        
                        //////////////////////ready////////////////////////////
                    case"ready":
                        if(json.streamid){
                            onCallBack("JoinRoom");
                            SubscribeToStream(streamlist);
                            RecordingStart();
                            SetVideoModel();
                        } else {
                            onCallBack("PeerConnected");
                        }
                        
                        break;
                        
                        //////////////////////timeout////////////////////////////
                    case"timeout":
                        break;
                        
                } //switch(msg.type)
                    
                    var stream;
                    if(json.peerid){
                        stream = remotestreams[json.peerid];
                    }else if(json.streamid){
                        stream = localstreams[json.streamid];
                    }
                    if(stream){
                        stream.pc.processSignalingMessage(json.msg);
                    }
                    
                    break;
                    
                    //////////////////////onAddStream////////////////////////////
                case"onAddStream":
                    var st;
                    st = json.stream;
                    console.log("On add stream: ", st);
                    
                    streamlist.push(st);
                    SendSubscribe(st);
                    break;
                    
                    //////////////////////onRemoveStream////////////////////////////
                case"onRemoveStream":
                    console.log("On remove stream");
                    
                    
                    break;
                    
                    //////////////////////onAddStream////////////////////////////
                case"reject_room_join":
                    
                    var errorCode = json.error;
                    var errorStr = json.reason;
                    
                    console.log("Reject room join, reason: ", errorStr);
                    
                    errCallBack(errorCode, errorStr);
                    
                    break;
                    
                    //////////////////////onAddStream////////////////////////////
                case"on_mute_video":
                    console.log("On mute video");
                    
                    var muteinfo = {};
                    muteinfo = json.stream;
                    if (muteinfo.streamid != localst.streamid){
                        muteVideoInternal(muteinfo.ismute);
                        onCallBack("PeerMuteVideo", muteinfo.ismute);
                    }
                    
                    
                    break;
                    
            } //switch(json.msgname)
            
            
        }; //socket.onmessage
        
    };
    
    
    ////////////////////////////main////////////////////////////////////////
    
   	
    var ExitRoomInternal = function(){
        
        viemList = null;
        room = null;
        localst = null;
        localstreams = null;
        remotestreams = null;
        streamlist = null;
    };
    
    var muteVideoInternal = function(ismute){
   	    muteVideo = ismute;
        if (muteVideo == true){
            
            //localst.stream.getVideoTracks()[0].enabled = !localst.stream.getVideoTracks()[0].enabled;
            localst.stream.getVideoTracks()[0].enabled = muteVideo
            console.log("Video on");
      		}else{
                
                //localst.stream.getVideoTracks()[0].enabled = !localst.stream.getVideoTracks()[0].enabled;
                localst.stream.getVideoTracks()[0].enabled = muteVideo
                console.log("Video off");
                
            }
        
   	}
   	
   	var SetVideoModel = function(){
        //if(room.info.isVideo == false){
            muteVideoInternal(false);
        //}
        
   	}
   	
    //////////////////////////// interface functions ////////////////////////////////////////
    that.VideoInit = function(viewList,  callback){
        
        for(var i=0; i<viewList.length; i++){
            var viewPara = viewList[i];
            if(viewPara.isLocal == true){
                viewLocal = viewPara;
            }else{
                viewRemote = viewPara;
            }
        }
        
        muteVideo = true;
        onCallBack = callback;
        onCallBack("InitReady");
   	};
    
   	
   	that.JoinRoom = function(roomId, extramsg, roomUrl, roomPort, stunUrl, stunPort, callback){
        // 与信令服务器的WebSocket连接
        var tmpRoomServer = "wss://" + roomUrl + ":" + roomPort;
        var tmpStunServer = "stun:" + stunUrl + ":" + stunPort;
        var tmpTurnServer = "turn:" + stunUrl + ":" + stunPort;
        
        
        wsSocket = new WebSocket(tmpRoomServer);
        
        // stun和turn服务器
        // stun and turn server in beijing
       // iceServer = {
       //     "iceServers": [{"url":tmpStunServer
       //     							},
       //                    {
       //                    "url": tmpTurnServer,
       //                    "username": "test",
       //                    "credential": "testpwd"
       //                    }]
       // };
        iceServer = {
        "iceServers": [{'url': 'stun:stun.l.google.com:19302'}]
        };
        
        isInit = true;
        errCallBack = callback;
        
        roomName = roomId;
        wsSocketListen(wsSocket, roomId, extramsg);
        
    };
    
    that.ExitRoom = function(){
        console.log("Exit room");
        
        RecordingStop();
        if (localst){
            
            wsSocket.send(JSON.stringify({
                                         "msgname": "unpublish",
                                         "streamid": localst.streamid
                                         }), function(error){
                      if(error){
                      log.info("Send message error:", error);
                      }
                      }, function(error){
                      if(error){
                      log.info("Send message error:", error);
                      }
                      });
       	}
        
       	ExitRoomInternal();
       	onCallBack("ExitRoom");
    };
    
    /*      	          
     that.RecordingStart = function(){
     console.log("start record");
     
     if (localst){
     wsSocket.send(JSON.stringify({
     "msgname": 'recording',
     "streamid": localst.streamid
     }));
     
     }                           
     
     }; 
     
     that.RecordingStop = function(){
     console.log('stop recording');
     
     if (localst){
     wsSocket.send(JSON.stringify({
     "msgname": 'stoprecorder',
     "streamid": localst.streamid
     }));  
     }     	
     };
     */
    
    
    that.MuteVideo = function(){
        console.log("Toggle video mute!");
        if (localst.stream == undefined){
            console.log("No local video stream to toggle mute!");
            return;
        }
      		
        muteVideo = !muteVideo;
        muteVideoInternal(muteVideo);
        onCallBack("MuteVideo", muteVideo);
        var muteInfo ={};
        muteInfo.streamid = localst.streamid;
        muteInfo.ismute = muteVideo;
        wsSocket.send(JSON.stringify({
            	                        "msgname": "mute_video",
            	                        "streamid": muteInfo.streamid,
            	                        "ismute": muteInfo.ismute
                                     }), function(error){
                      if(error){
                      log.info("Send message error:", error);
                      }
                      }); 	
    };        	   		
    
    return that;
};//videoController




