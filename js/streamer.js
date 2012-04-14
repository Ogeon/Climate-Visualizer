var streamer = {
	streams: new Array(),

	openStreamTo: function(url, headers, callback, onReadyCallback) {
		if(streamer.isActive(url))
			return;

		streams.push(new streamer.stream(url, headers, callback, onReadyCallback));
	},

	closeStreamTo: function(url) {
		var streams = streamer.streams;
		for(var i = streams.length; i--;)
			if(streams[i].url == url) {
				streams[i].closeConnection();
				streams.pop(i);
				break;
			}
	},

	isActive: function(url) {
		var streams = streamer.streams;
		for(var i = streams.length; i--;)
			if(streams[i].url == url) {
				return true;
			}
		return false;
	}

	endOfStream: function(stream) {
		var streams = streamer.streams;
		for(var i = streams.length; i--;)
			if(streams[i] == stream) {
				streams.pop(i);
				break;
			}
	},

	stream: function(url, headers, callback, onReadyCallback) {
		this.http;
		this.url = url;
		this.callback = callback;
		this.onReady = onReadyCallback;
		this.checkLoop;

		// Mozilla/Safari
		if(window.XMLHttpRequest) {
			this.http = new XMLHttpRequest();
		}
		// IE
		else if(window.ActiveXObject) {
			this.http = new ActiveXObject("Microsoft.XMLHTTP");
		}

		this.http.open('POST', this.url);
		this.http.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		this.http.onreadystatechange = this.onClose;
		this.http.send(null);

		this.checkLoop = setInterval(this.loopCallback, 1000/4);

		this.onClose = function() {
			if(this.http.readyState == 4) {
				clearInterval(this.checkLoop);
				this.callback(this.http.responseText);
				this.onReady(this.http.responseText);
				streamer.endOfStream(this);
			}
		}

		this.loopCallback = function() {
			this.callback(this.http.responseText);
		}

		this.closeConnection = function() {
			this.http.close();
		}
	}
}