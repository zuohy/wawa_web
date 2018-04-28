

/*global require, logger. setInterval, clearInterval, Buffer, exports*/

var config = require('./../../licode_config');
global.config = config || {};
global.config.erizoController = global.config.erizoController || {};
var logger = require('./../common/logger').logger;
var rpcPublic = require('./rpc/rpcPublic');


// Logger
var log = logger.getLogger("server_erizo");
var amqper = require('./../common/amqper');

//cloud handle
var ecch = require('./ecCloudHandler').EcCloudHandler({amqper: amqper});


var controller = require('./roomController');
var moment = require('moment');
var fs = require("fs");
var path = require("path");


//+++++++++++ server functions +++++++++++++++++


amqper.connect(function () {
	"use strict";
	log.info("server amqper connect");
	try {
		log.info("rpcPublic:", JSON.stringify(rpcPublic));
		amqper.setPublicRPC(rpcPublic);


		var rpcID = 'server_erizo_' + 333;

		amqper.bind(rpcID, listen);


	} catch (error) {
		log.error("Error in Erizo Controller: ", error);
	}
});

var listen = function () {
	log.info("server listen");
};

var LOGDECOLLATOR = function(){
	log.info('++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++');
};

var extramsgNotifyingUrl=global.config.extramsgnotifyingurl;
//var extramsgNotifyingUrl="http://203.130.44.83/echoself.php?json=";
var notifyExtraMsg = function(extramsg) {
	if (extramsgNotifyingUrl == undefined) {
		return;
	}

	var http = require("http");
	var qs = require("querystring");

	var strUrl = extramsgNotifyingUrl + qs.escape(JSON.stringify(extramsg));
	log.info('Extra message:', JSON.stringify(extramsg), ', notify URL: ', strUrl);

	http.get(strUrl, function(res){
		log.info('Extra message notify got response: ', res.statusCode);
		res.setEncoding("utf-8");
		var resData = [];
		res.on("data", function(chunk){
			resData.push(chunk);
		})
		.on("end", function(){
			log.info('Extra message notify response: ', resData.join(""));
		});
	}).on('error',function(e){
		log.warn("Extra message notify error: ", strUrl, "\n", e.stack);
	});
};
//+++++++++++ server functions end +++++++++++++++++



var express = require('express'),
app = express();
//server = require('http').createServer(app);

//var keyPath = global.config.erizoController.sslkeypath || {};//'cert/2_webrtctest.hlwyy.cn.key'
//var certPath = global.config.erizoController.sslcertpath || {};//'cert/1_webrtctest.hlwyy.cn_bundle.crt'

var keyPath = global.config.erizoController.ssl_key || '../../cert/key.pem';
var certPath = global.config.erizoController.ssl_cert || '../../cert/cert.pem';

var options = {
	key: fs.readFileSync(keyPath).toString(),
	cert: fs.readFileSync(certPath).toString()

	//key: fs.readFileSync(certPath).toString(),
	//cert: fs.readFileSync(keyPath).toString()
};

var server = require('https').createServer(options, app);


server.listen(3004);
server.on('error', function(err) {
    log.error(err.stack);
    log.info("Node NOT Exiting...");
});

app.get('/', function(req, res) {
	var downfile = __dirname + '/wv_demo.html';
	res.sendFile(downfile);
	log.info('set filename:' + downfile + '\r\n');
});

app.get('/wv_controller.js', function(req, res) {
	var downfile = __dirname + '/wv_controller.js';
	res.sendFile(downfile);
	log.info('set filename:' + downfile + '\r\n');
});

var WebSocketServer = require('ws').Server,
wss = new WebSocketServer({server: server});






////////////////////////////////////////////////
//rooms:
//{id: room}

//stream:
//{id: random, vido: true, audio: true, status: init, subscriber[socket id, ...]}

//socket:
//{id:socket id, publisher:id,}
//room:
//{id: room id, name: room name, isVideo: true, sockets:[socket 1, socket 2, ...], controller: room controller, streams:{stream 1,stream 2, ...}}

//extramsg
//{userType: publisher, actor, looker}
//{client_type: android, ios, web}

//ws:
//{room: room, publisher:[id, ...], extramsg:{userType, client_type,} }

//wsc:
//[ws, ...]

//recordNums:
//{roomname: name, recordindex: nums}

//recordFiles:
//{date: date, recordNums: records}

var roomMaxUser = 10;//Set this more than 2 to allow multiple user video chat!
// var PUBLISH_STATE_IDLE = 0;
var PUBLISH_STATE_ENGAGED = 1;
var PUBLISH_STATE_INIT = 2;
var PUBLISH_STATE_READY = 3;

var USER_TYPE_PUBLISHER = 1;  //publisher
var USER_TYPE_ACTOR = 2;    //actor   //not used
var USER_TYPE_LOOKER = 3;  //looker
var CLIENT_TYPE_WEB = 1;   //web client
var CLIENT_TYPE_AND = 2;   // android client
var CLIENT_TYPE_IOS = 3;   // ios client


// var SUBSCRIBE_STATE_IDLE = 0;
// var SUBSCRIBE_STATE_ENGAGED = 1;
// var SUBSCRIBE_STATE_INIT = 2;
// var SUBSCRIBE_STATE_READY = 3;

var recordNums = [];
var recordFiles = {};
var rooms = {};
var wsc = {},
roomIndex = 1,
wsPos = 1;

var configPath = global.config.erizoController.recording_path;
//var maxRooms = 10;
var streamOptions = {"audio": "false",
    "video": "true",
    "minVideoBW": "10",
    "maxVideoBW": "300",
    "mediaConfiguration": "default"};

log.info("Erizo server running!");

wss.on("connection", function(ws) {
	LOGDECOLLATOR();   //log decollator
	log.info("Client connected");
	LOGDECOLLATOR();   //log decollator
	// wsc array for save all sockets
	wsc[wsPos++]= ws;

	//+++++++++++ room functions +++++++++++++++++
	var backupRecordNums = function(){
		//new recordFiles as date
		var recordDate = moment().format("YYYYMMDD");

		if (recordFiles.hasOwnProperty(recordDate))
		{
			log.info("info: record nums on today");
		}else{
			//new date for record files
			recordFiles[recordDate] = recordNums;
			log.info("info: new date for record, recordFiles=", recordFiles[recordDate]);
			recordNums = [];
		}
	};

	var getRecordIndex = function(roomname){
		var numData = {};

		for(var i=0; i<recordNums.length; i++)
		{
			if(recordNums[i].name == roomname)
			{
				return recordNums[i].recordindex;
			}
		}
		//new room for recording index
		log.info("New room for recording index, roomname = ", roomname);
		numData.name = roomname;
		numData.recordindex = 1;
		var len = recordNums.push(numData);

		return recordNums[len-1].recordindex;
	};

	var addRecordIndex = function(roomname){
		for(var i=0; i<recordNums.length; i++)
		{
			if(recordNums[i].name == roomname)
			{
				recordNums[i].recordindex = recordNums[i].recordindex + 1;
				log.info("add record index=", recordNums[i], ", all record nums is ", recordNums.length);
				return true;
			}

		}

		//not found room for recording index
		log.warn("error: set record index failed, roomname=", roomname);
		return false;
	};

	var mkdirpSync = function (dir) {
		
		var args = dir.split(path.sep);
		for (var i = 0; i < args.length; i++) {
			
			var dirname = args.slice(0, i + 1).join(path.sep);
			if (dirname.length == 0) {
				continue;
			}
			var exists = fs.existsSync(dirname);
			if (exists) {
				var stat = fs.statSync(dirname);
				if (!stat.isDirectory()) {
					log.error("Directory name can not be a file name!");
					return null;
				}
			} else {
				fs.mkdirSync(dirname, 777);
			}
		}

		return dir;
	};

	var createRecordPath = function(){
		if (configPath == undefined){
			configPath = "/tmp/";
		}

		if (configPath.slice(-1) != "/") {
			configPath = configPath + "/";
		}

		var date = moment().format("YYYYMMDD");
		var dstPath = configPath + date + "/";
		return mkdirpSync(dstPath);
	};

	var createRecordTmpPath = function(){
		if (configPath == undefined){
			configPath = "/tmp/";
		}

		if (configPath.slice(-1) != "/") {
			configPath = configPath + "/";
		}

		var date = moment().format("YYYYMMDD");
		var dstPath = configPath + date + "/" + "tmp/";
		return mkdirpSync(dstPath);
	};

	var getWSindex = function(ws)
	{
		var i;
		for(i in wsc)
		{
			if (wsc.hasOwnProperty(i)){
				if(wsc[i] == ws)
				{
					return i;
				}
			}
		}
		log.warn("getWSindex failed");
		return -1;
	};

	var getReadyStreamList = function (streams){
		var readyList = [];
		var i;

		for(i in streams){
			if (streams.hasOwnProperty(i)){
				if(streams[i].status == "ready"){
					var st = {};
					st.id = streams[i].id;
					st.vido = streams[i].vido;
					st.audio = streams[i].audio;
					st.status = streams[i].status;
                    st.role = streams[i].role;
					readyList.push(st);
				}
			}
		}
		return readyList;
	};

  var sendMsgToRoom = function (room, name, stream, exceptWsIndex) {
  	"use strict";
  	var wsIndex;
  	var sockets = room.sockets;
  	var webSock;
  
  	for(var i=0; i<sockets.length; i++)
  	{
  		var socket = sockets[i];
  		wsIndex = socket.id;
  		if (wsIndex == exceptWsIndex) {
  			continue;
  		}
  		webSock = wsc[wsIndex];
  		if (webSock != undefined) {
  			webSock.send(JSON.stringify({
  				"msgname": name,
  				"stream": stream
  			}), function(error){
  				if(error){
  					log.warn("Send message error:", error);
  				}
  			});
  		} else {
  			log.error("room socket id is invalid: ", wsIndex);
  		}
  	}
  };

	var limitRoomUser = function (room, maxUserNum) {
		"use strict";
		var userNum = room.sockets.length;

        return userNum >= maxUserNum;
	};

	var getRoomNumber = function () {
		var i;
		var num = 0;

		for(i in rooms)
		{
			if (rooms.hasOwnProperty(i)){
				num++;
			}
		}
		return num;
	};

	var closeWS = function(ws) {
		LOGDECOLLATOR();   //log decollator
		log.info("Closing websocket!");
		var wsIndex = getWSindex(ws);

		if(wsIndex < 0){
			log.warn("Websocket is not found in websocket array, probably already closed!");
			return;
		}
		
		if(ws.room == undefined){
			log.info("Ws room not found");
			ws.close();
			delete wsc[wsIndex];
			log.info("Clean wsc index", wsIndex);
			return;
		}

		//clean socket id in sockets
		var sockets = ws.room.sockets;
		if (sockets == undefined) {
			log.error("Room sockets is undefined!");
			return;
		}

		log.info("socket length", sockets.length);
		log.info("sockets", sockets);
		var skfound = false;
		for (i=0; i<sockets.length; i++) {
			var socket = sockets[i];
			if (socket.id == wsIndex){
				sockets.splice(i, 1);
				skfound = true;
				log.info("clean socket ", wsIndex);
				break;
			}
		}

		if (skfound == false){
			log.error("Socket not found ", wsIndex);
		}

		var i;
		var streams = ws.room.streams;
		var publisherId = undefined;
		if (ws.publishStream != undefined) {
			publisherId = ws.publishStream.id;
		}

		//clean subscribers in streams
		for (i in streams) {
			if (streams.hasOwnProperty(i)) {
				var subscribers = streams[i].subscriber;
				if (subscribers != undefined){
					var index = subscribers.indexOf(wsIndex);
					if (index !== -1){
						subscribers.splice(index, 1);
						log.info("Clean subscriber ", wsIndex, " of publisher ", streams[i].id);
					} else {
						//log.info("not found subscriber ", wsIndex, "in publisher ", tmpstreams[i].id);
					}
				} else {
					log.warn("No subscribers of publisher ", streams[i].id);
				}
			}
		}
		
		//clean stream in streams
		if (publisherId != undefined) {
			for (i in streams){
				if (streams.hasOwnProperty(i)) {
					if (publisherId == streams[i].id) {
						//remove external output
						var stream = ws.room.streams[publisherId];
						if (stream != undefined && stream.recordtmpurl != undefined && stream.recordurl != undefined) {
							log.info("Request to stop recorder, streamid = ", stream.id);
							log.info("Stopping record url: ", stream.recordtmpurl);
							(function (strm) {
                                ws.room.controller.removeExternalOutput(strm.recordtmpurl, function(ret){
                                    log.info("Record url:", strm.recordtmpurl, "stop: ", ret);
                                    if (ret != undefined && ret.type == "success") {
                                        var start = new Date().getTime();
                                        fs.rename(strm.recordtmpurl, strm.recordurl, function (err) {
                                            if (err) {
                                                log.error("Rename file failed: ", strm.recordtmpurl, "error:", err);
                                            }
                                        });
                                        log.info("Rename file cost: " + (new Date().getTime() - start));
                                    }
                                });
                            })(stream);
						}

						//notify others in the room that this publisher removed
						var streamInfo = {};
						var rawStream = streams[i];
						streamInfo.id = rawStream.id;

						var exceptWsIndex = getWSindex(ws);
						if (exceptWsIndex == -1) {
							log.warn("Get ws index failed when try send msg to room!");
						}
						
						sendMsgToRoom(ws.room, "onRemoveStream", streamInfo, exceptWsIndex);
						
						delete streams[i];
					} else {
						//log.info("not found publisher", publisherId);
					}
				}
			}
		}

		//remove subscriptions
		log.info("Remove subscriptions: ", wsIndex);
		ws.room.controller.removeSubscriptions(wsIndex);
		
		if (publisherId != undefined) {
			//unpublish
			log.info("Remove publisher: ", publisherId);
			ws.room.controller.removePublisher(publisherId);
		}

		//clean room if all socket closed
		if (ws.room.sockets.length === 0) {
			log.info("Empty room, id:", ws.room.id, ", name:", ws.room.name, ", Deleting it!");
			delete rooms[ws.room.id];
		}

		//close web socket
		ws.close();
		delete wsc[wsIndex];
		log.info("clean wsc index", wsIndex);
		LOGDECOLLATOR();   //log decollator
	};
	
	ws._socket.setKeepAlive(true, 60000);

	ws.onclose = function(event) {
		var index = getWSindex(ws);

		log.info("Websocket onclose, index:", index);
		closeWS(ws);
	};
    ws.onerror = function(evt) {
        var index = getWSindex(ws);
        log.info("WebSocketError! index:", index);
    };
    //+++++++++++ room functions end+++++++++++++++++

	// ws message handler
	ws.on("message", function(message) {
		var wsIndex = getWSindex(ws);
		if (wsIndex < 0) {
			log.warn("Message call back called after websocket closed!");
			return;
		}

		try{
			var json = JSON.parse(message);
			log.info("Received json:", json);
		} catch (e){
			log.warn("Received json error: ", e);
			return;
		}

		if (json.msgname === undefined) {
			log.warn("Websocket message without msgname!");
			return;
		}

		var stream;//one stream in the room streams array
		var socket;//websocket info accessor
		var streamList;//room stream list
		var roomInfo;//room info accessor

		switch(json.msgname)
		{
			///////////////////////msg create room////////////////////////////
			case "create_room":
			
			if (ws.room != undefined) {
				log.warn("Invalid request for already in room:", json.msgname);
				return;
			}
				
			log.info("Request join room!");
			
			var roomName = json.name;
			var extramsg = json.extramsg;
			if(extramsg != null){
				log.info("Received extra msg: ", extramsg);
				ws.extramsg = extramsg;
			}

			if (roomName == undefined){
				log.error("Error: room name is undefined!");
				return;
			}
			//check max room number

			//find current room id
			var existRoomId = -1;
			for (var rIdx in rooms) {
				if (rooms.hasOwnProperty(rIdx)) {
					if (rooms[rIdx].name === roomName){
						existRoomId = rooms[rIdx].id;
					}
				}
			}

			if(existRoomId >= 0)
			{
				var isLimited = limitRoomUser(rooms[existRoomId], roomMaxUser);
				if (isLimited == true){
					//send reject message to client
					var errorStr = "max user number is " + roomMaxUser;
					ws.send(JSON.stringify({
						"msgname": "reject_room_join",
						"error": '1',
						"reason": errorStr
					}), function(error){
						if(error){
							log.warn("Send message error:", error);
						}
					});
					log.warn("Too many user to join room(room index = ", existRoomId, "), room maxuser number=", roomMaxUser);
					LOGDECOLLATOR();   //log decollator
					return;
				}
				
				ws.room = rooms[existRoomId];   //join room
				// var publishStream = {};
				// publishStream.id = -1;
				// publishStream.status = PUBLISH_STATE_IDLE;
				// ws.publishStream = publishStream;

				socket = {};
				socket.id = wsIndex;
				ws.room.sockets.push(socket);
				streamList = getReadyStreamList(ws.room.streams);

				log.info("Join existing room, name:", roomName, "index:", existRoomId, "room sockets:", ws.room.sockets, "streamList:", streamList);

				LOGDECOLLATOR();   //log decollator
				roomInfo = {};
				roomInfo.id = ws.room.id;
				roomInfo.name = ws.room.name;
				roomInfo.isVideo = ws.room.isVideo;
				ws.send(JSON.stringify({
					"msgname": "create_room_erizo",
					"roominfo": roomInfo,
					"streamlist": streamList
				}), function(error){
					if(error){
						log.warn("Send message error:", error);
					}
				});
				return;
			} else {
                //new room!
                log.info("Create new room, name:", roomName, "index:", roomIndex, "ws index:", wsIndex, "current room num:", getRoomNumber());

                var room = {};

                room.id = roomIndex;
                room.name = roomName;
                if(ws.extramsg.userType != USER_TYPE_PUBLISHER){
                    //looker or actor
                    room.isVideo = false;
                }else{
                    //publisher
                    room.isVideo = true;
                }


                socket = {};
                socket.id = wsIndex;
                room.sockets = [];
                room.sockets.push(socket);

                room.streams = {};
                room.controller = controller.RoomController({amqper: amqper, ecch: ecch});
                //handle error case
                room.controller.addEventListener(function (type, event) {
                    // TODO Send message to room? Handle ErizoJS disconnection.
                    if (type === "unpublish") {
                        var streamId = parseInt(event); // It's supposed to be an integer.
                        log.warn("ErizoJS stopped, should unpublish:", streamId);
                        // room.controller.removePublisher(streamId); this should be done by 'closeWS' if necessarily

												var closingWsList = [];
												
                        for (var i = 0; i < room.sockets.length; i++) {
                            var websocket = wsc[room.sockets[i].id];
                            if (websocket != undefined) {
                            	closingWsList.push(websocket);
                            }
                        }
                        
                        while (closingWsList.length > 0) {
                        	log.info("Close websocket for it's publish erzio process stopped!");
                          closeWS(closingWsList[0]);
                          closingWsList.splice(0, 1);
                        }
                    }
                });


                rooms[roomIndex++] = room;

                ws.room = room;
                // var publishStream = {};
                // publishStream.id = -1;
                // publishStream.status = PUBLISH_STATE_IDLE;
                // ws.publishStream = publishStream;

                //Feed back room stream list info to client
                roomInfo = {};
                roomInfo.id = ws.room.id;
                roomInfo.name = ws.room.name;
                roomInfo.isVideo = ws.room.isVideo;

                streamList = [];

                ws.send(JSON.stringify({
                    "msgname": "create_room_erizo",
                    "roominfo": roomInfo,
                    "streamlist": streamList
                }), function (error) {
                    if (error) {
                        log.warn("Send message error:", error);
                    }
                });

                // log.info("ws room id=", ws.room.id);
                // log.info("ws room name=", ws.room.name);
                // log.info("ws room socktes=", ws.room.sockets);
                // log.info("rooms number", getRoomNumber());
                // log.info("streamList length in room=", streamList.length);
                LOGDECOLLATOR();   //log decollator
            }
			break;
			
			//////////////////////msg publish////////////////////////////
			case "publish":
			
			if (ws.room == undefined) {
				log.warn("invalid request for not in room:", json.msgname);
				return;
			}
			
			if (ws.publishStream != undefined) {
				log.warn("Already published! Unpublish first! wsIndex = ", wsIndex);
				return;
			}
			
			//var options = json.options;
            //var pubOptions = {"audio": "true", "video": "true", "minVideoBW": "10", "maxVideoBW": "300", "mediaConfiguration": "default"};
            var pubOptions = streamOptions;

			log.info("New publish requeset, wsIndex=", wsIndex);

			var id = Math.random() * 1000000000000000000;
			id = Math.floor(id);
			var publishStream = {};
			publishStream.id = id;
			publishStream.status = PUBLISH_STATE_ENGAGED;
			ws.publishStream = publishStream;

			ws.room.controller.addPublisher(id, pubOptions, function(signMess){
				
				var curWsIndex = getWSindex(ws);
				if (curWsIndex < 0) {
					log.warn("addPublisher call back called after websocket closed, ignore it!");
					return;
				}

				if (ws.room == undefined) {
					log.warn("addPublisher call back called when not joined in a room, wsIndex:", curWsIndex);
					return;
				}

				if (ws.publishStream == undefined) {
					log.warn("addPublisher call back called when already unpublished! wsIndex:", curWsIndex);
					return;
				}
				
				if (id != ws.publishStream.id) {
					log.warn("addPublisher callback for non-concerned id:", id, "current publisher id:", ws.publishStream.id);
					return;
				}
				
				if (signMess == "timeout"){
					signMess = {"type": "timeout"};
				}

				if (signMess.type === "initializing" && ws.publishStream.status == PUBLISH_STATE_ENGAGED){
					log.info("Publisher init, id=", id);
					ws.publishStream.status = PUBLISH_STATE_INIT;
					//st = new ST.Stream({id: id, socket: wsIndex, audio: options.audio, video: options.video, data: options.data, screen: options.screen, attributes: options.attributes});
					//log.info("streams=", ws.room.streams);
				} else if (signMess.type ==="failed"){
					log.warn("Publish failed, id:", id, ", reset publish state");
					delete ws.publishStream;
					LOGDECOLLATOR();   //log decollator
				} else if (signMess.type === "ready" && ws.publishStream.status == PUBLISH_STATE_INIT){
					log.info("Publish ready, id:", id);

					ws.publishStream.status = PUBLISH_STATE_READY;

					var st = {};
					var notifyStInfo = {};
					st.id = id;
					st.vido = streamOptions.video;//true;
					st.audio = streamOptions.audio;//true;
					st.status = "ready";
					st.subscriber = [];

                    //save role of steam
                    st.role = ws.extramsg.userType;

					ws.room.streams[id] = st;
					
					notifyStInfo.id = id;
					notifyStInfo.vido = streamOptions.video;
					notifyStInfo.audio = streamOptions.audio;
					notifyStInfo.status = "ready";
                    //save role of steam
                    notifyStInfo.role = ws.extramsg.userType;

					sendMsgToRoom(ws.room, "onAddStream", notifyStInfo, curWsIndex); //send onAddStream message to ready user in room

					//FIXME:don't take publish success as the signal of join room successfully
					if(ws.extramsg != null) {
						notifyExtraMsg(ws.extramsg);
					} else {
						log.info("Extra message is null, don't notify external web server!");
					}

					LOGDECOLLATOR();   //log decollator

				} else if (signMess.type === "timeout"){
					log.warn("Publish timeout, id:", id, ", reset publish state.");
					delete ws.publishStream;
					LOGDECOLLATOR();   //log decollator
				} else if (signMess.type === "answer"){
					//limit bandwidth value
					var str = JSON.stringify(signMess);
					var strSubs = str.replace(/c=IN/g, "b=AS:800\\r\\nc=IN");
					signMess = JSON.parse(strSubs);

					log.info("Publish answer:", JSON.stringify(signMess));

				}

				//log.info("erizo signMess=", signMess);

				ws.send(JSON.stringify({"msgname": 'signaling_message_erizo',
					"streamid": id,
					"msg": signMess
				}), function(error){
					if(error){
						log.warn("Send message error:", error, "msg:", signMess);
					}});
				});

				break;
				//////////////////////signaling_message////////////////////////////
				case "signaling_message":
				
				if (ws.room == undefined) {
					log.warn("invalid request for not in room:", json.msgname);
					break;
				}
				
				try {
					log.info("signaling_message. wsIndex=", wsIndex);
					log.info("signaling_message. streamId=", json.streamid);
					var sdpdata = json.data;
					//test code
					//if (sdpdata.type == 'offer'){
					//	sdpdata.sdp = sdpdata.sdp + "a=fmtp:100 x-google-start-bitrate=600" + "\r\n";

					//	log.info("append bitrate in for remote sdp");
					//	}
					//test code end	
					ws.room.controller.processSignaling(json.streamid, wsIndex, sdpdata);  		

				}catch(e){
					log.warn("signaling_message error: ", e);
				} 	


				break;
				//////////////////////subscribe////////////////////////////  	
				case "subscribe": 	
				
				if (ws.room == undefined) {
					log.warn("invalid request for not in room:", json.msgname);
					return;
				}
				
				var st = json.stream;
				
				if (st == undefined || st.id == undefined) {
					log.warn("Subscribe from wsIndex:", wsIndex, "to stream undefined, ignore it!");
					return;
				}  	

				log.info("Subscribe from wsIndex:", wsIndex, "to stream: ", st.id);

				if (ws.room.streams == undefined) {
					log.warn("Currently no streams to subscribe!");
					return;
				}

				stream = ws.room.streams[st.id];
				if (stream == undefined) {
					log.warn("No stream with this id in the room to subscribe: ", st.id);
					return;
				}

                //just subscriber a publisher steam
                if(stream.role != USER_TYPE_PUBLISHER){
                    log.warn("Can not match user type to subscribe: ", st.id, stream.role);
                    return;
                }
				var options = streamOptions;
				//options.video = true;
				//options.audio = true;
                //options.mediaConfiguration = "default";

				ws.room.controller.addSubscriber(wsIndex, st.id, options, function(signMess){
					var curWsIndex = getWSindex(ws);
					if (curWsIndex < 0) {
						log.warn("addSubscriber call back called after websocket closed, ignore it!");
						return;
					}

					if (ws.room.streams == undefined) {
						log.warn("addSubscriber call back called after all streams in room is removed! wsIndex:", curWsIndex);
						return;
					}
					
					var stream = ws.room.streams[st.id];
					if (stream == undefined) {
						log.warn("addSubscriber call back called after stream removed, ignore it! wsIndex:", curWsIndex);
						return;
					}
					
					if (signMess == "timeout"){
						signMess = {"type": "timeout"};
					}

					if (signMess.type === "initializing"){
						log.info("Initializing subscriber"); 			
					} else if (signMess.type ==="failed"){
						log.warn("Subscribe failed");
						//unsubscribe
        				log.info("Remove subscriber: ", curWsIndex, "from ", st.id);
        				ws.room.controller.removeSubscriber(curWsIndex, st.id);
					} else if (signMess.type === "ready"){
						log.info("Subscribe ready, from ", wsIndex, " to ", st.id);
						if (stream.subscriber.indexOf(curWsIndex) === -1) {
							stream.subscriber.push(curWsIndex);
						}   
						log.info("Subscribers of stream ", stream.id, ": ", stream.subscriber);
					} else if (signMess.type === "timeout"){
						log.warn("Subscribe timeout");	
						//unsubscribe
        				log.info("Remove subscriber: ", curWsIndex, "from ", st.id);
        				ws.room.controller.removeSubscriber(curWsIndex, st.id);		
					}                                             

					if (signMess.type === "answer") {
						log.info("Subscribe answer:", JSON.stringify(signMess));
					}

					ws.send(JSON.stringify({
						"msgname": "signaling_message_erizo",
						"peerid": st.id,
						"msg": signMess          	                         
					}),
					function(error){
						if(error){
							log.warn("Send message error:", error, "msg:", signMess);
						}
					});  		
				});


				break;
				//////////////////////recording////////////////////////////  
				case "recording":
				
				if (ws.room == undefined) {
					log.warn("invalid request for not in room:", json.msgname);
					return;
				}

				if (json.streamid == undefined){
					log.warn("json.streamid undefined");
					return;
				}

				if (ws.publishStream == undefined || ws.publishStream.id != json.streamid || ws.publishStream.status != PUBLISH_STATE_READY) {
					log.warn("Request record stream when not publish ready!wsIndex:", wsIndex);
					return;
				}
				
				stream = ws.room.streams[json.streamid];
				if (stream == undefined) {
					log.error("Strange state, published stream can't be found in room streams list!");
					return;
				}

				if(stream.status == "ready") {  		
					//var videoNum = ws.room.sockets.length;
					var videoName = ws.room.name;
					var videoPath = createRecordPath();
					var videoTmpPath = createRecordTmpPath();
					var videoNum;
					var videoId;
					var mvurl;

					backupRecordNums(); 		
					videoNum = getRecordIndex(videoName);
					videoId = videoNum;

					if (ws.extramsg != undefined && ws.extramsg.userType != undefined) {
						if (ws.extramsg.userType == 1) {
							log.info("Client user type read:", ws.extramsg.userType, "set video id to 1");
							videoId = 1;
                            return;
						} else {
							log.info("Client user type read:", ws.extramsg.userType, "set video id to 2");
							videoId = 2;
                            return;
						}
					}

					mvurl = videoPath + videoName + "_" + videoId + ".mkv";
					log.info("recording streamid= ", stream.id);

					stream.recordurl = mvurl;
					stream.recordtmpurl = videoTmpPath + videoName + "_" + videoId + ".mkv";
					log.info("recording mvurl", stream.recordtmpurl);

                    var options = {};
                    options.mediaConfiguration = "default";

					ws.room.controller.addExternalOutput(json.streamid, stream.recordtmpurl, options,  function(ret){
						var tmperror = addRecordIndex(videoName);
						if(tmperror == false){
							log.warn("warning: add record index failed, room name is ", videoName);
						}
						log.info("recording ", ret);
					});

				} else {
					log.error("Stream status in correct!");
				}
				break;

				//////////////////////stoprecorder////////////////////////////  
				case "stoprecorder":
				
				if (ws.room == undefined) {
					log.warn("invalid request for not in room:", json.msgname);
					break;
				}
				
				if (json.streamid == undefined){
					log.warn("json.streamid  undefined");
					break;
				}

				if (ws.publishStream == undefined || ws.publishStream.id != json.streamid || ws.publishStream.status != PUBLISH_STATE_READY) {
					log.warn("Request stop record stream when not publish ready!wsIndex:", wsIndex);
					return;
				}

				stream = ws.room.streams[json.streamid];
				if (stream == undefined || stream.recordtmpurl == undefined || stream.recordurl == undefined) {
					log.warn("stream not found or stream not recorded!");
					break;
				}  	

				log.info("Request to stop recorder, streamid = ", stream.id);
				log.info("Stopping record url: ", stream.recordtmpurl);
				ws.room.controller.removeExternalOutput(stream.recordtmpurl, function(ret){
					log.info("Record url:", stream.recordtmpurl, "stop: ", ret);
					if (ret != undefined && ret.type == "success") {
						var start = new Date().getTime();
						fs.rename(stream.recordtmpurl, stream.recordurl, function (err) {
							if (err) {
								log.error("Rename file failed: ", stream.recordtmpurl, "error:", err);
							}
						});
						log.info("Rename file cost: " + (new Date().getTime() - start));
					}
				});
				break;  

				//////////////////////unsubscribe////////////////////////////  
				case "unsubscribe":
				
				if (ws.room == undefined) {
					log.warn("invalid request for not in room:", json.msgname);
					break;
				}
				
				log.info("Unsubscribe, stream id:", json.streamid);
				
				if (json.streamid == undefined){
					log.warn("json.streamid  undefined");
					break;
				}
				
				stream = ws.room.streams[json.streamid];
				if (stream == undefined) {
					log.warn("Invalid stream id:", json.streamid);
					break;
				}

				if (stream.subscriber == undefined) {
					log.warn("No subscribers!");
					break;
				}
				
				var subIdx = stream.subscriber.indexOf(wsIndex);
				if (subIdx != -1) {
					stream.subscriber.splice(subIdx, 1);
				} 

				//unsubscribe
        		log.info("Remove subscriber: ", wsIndex, "from ", json.streamid);
        		ws.room.controller.removeSubscriber(wsIndex, json.streamid);
				
				break; 

				//////////////////////unpublisher////////////////////////////  
				case "unpublish":
				
				if (ws.room == undefined) {
					log.warn("invalid request for not in room:", json.msgname);
					break;
				}
				
		        try {
		        	if (ws.publishStream == undefined) {
		        		log.warn("Invalid unpublish request!");
		        		break;
		        	}
		        	log.info("Request to unpublish, streamid = ", ws.publishStream.id);
		        
		        	var streams = ws.room.streams;
		        	var publisherId = ws.publishStream.id;
		        				
		        	for (var i in streams){
		        		if (streams.hasOwnProperty(i)) {
		        			if (publisherId == streams[i].id) {
		        				//remove external output
		        				stream = ws.room.streams[publisherId];
		        				if (stream != undefined && stream.recordtmpurl != undefined && stream.recordurl != undefined) {
		        					log.info("Request to stop recorder, streamid = ", stream.id);
		        					log.info("Stopping record url: ", stream.recordtmpurl);
									(function (strm) {
                                        ws.room.controller.removeExternalOutput(strm.recordtmpurl, function(ret){
                                            log.info("Record url:", strm.recordtmpurl, "stop: ", ret);
                                            if (ret != undefined && ret.type == "success") {
                                                var start = new Date().getTime();
                                                fs.rename(strm.recordtmpurl, strm.recordurl, function (err) {
                                                    if (err) {
                                                        log.error("Rename file failed: ", strm.recordtmpurl, "error:", err);
                                                    }
                                                });
                                                log.info("Rename file cost: " + (new Date().getTime() - start));
                                            }
                                        });
                                    })(stream);
		        				}
		        				
		        				//notify others in the room that this publisher removed
		        				var streamInfo = {};
		        				var rawStream = streams[i];
								streamInfo.id = rawStream.id;

								sendMsgToRoom(ws.room, "onRemoveStream", streamInfo, wsIndex);
										
		        				delete streams[i];
		        			} else {
		        				//log.info("not found publisher", publisherId);
		        			}
		        		}
		        	}

		        	//unpublish
		        	log.info("Remove publisher: ", publisherId);
		        	ws.room.controller.removePublisher(publisherId);

		        	delete ws.publishStream;
		        } catch (error) {
		        	log.warn("Error unpublish: ", error);
		        }  	


				break;

				//////////////////////mute video notification////////////////////////////  
				case "mute_video":
				
				if (ws.room == undefined) {
					log.warn("invalid request for not in room:", json.msgname);
					break;
				}
				
				if (ws.room.isVideo == json.ismute) {
					log.warn("room mute state not change, ignore mute request!");
					break;
				}
				
				try {
					var roomname = ws.room.name;
					var muteinfo = {};
					muteinfo.streamid = json.streamid;
					muteinfo.ismute = json.ismute;
					log.info("mutevideo roomname= ", roomname);
					log.info("request mutevideo info= ", muteinfo);

					sendMsgToRoom(ws.room, "on_mute_video", muteinfo, wsIndex); //notification mute video in room

					//update room info 
					ws.room.isVideo = muteinfo.ismute;
				} catch (error) {
					log.warn("Error mutevideo: ", error);
				}


				break;

				default:
					log.warn("unknow message");
				break;  	
		} //switch(json.msgname)
	});  //ws.on
});  //wss.on  
