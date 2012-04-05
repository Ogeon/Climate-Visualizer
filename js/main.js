var canvasLoop = null;
var mainCanvas = null;
var mainContext = null;
var time = Date.now();

var camera = new Camera();
var points = new Array();

var longitude = 0;
var latitude = -Math.PI * 45/180;
var cameraDistance = 1;
var cameraChanged = true;
var redrawScene = true;

var xmlHttpReq = false;
var streamPosition = 0;
var streamLoop = null;

var width = 1;
var height = 1;
var scale = 1;

window.onload = function() {
	window.mainCanvas = document.getElementById("mainCanvas");
	window.camera.setLatitude(-Math.PI * 45/180);
	window.camera.setDistance(2);

	if(window.mainCanvas.getContext) {
		window.mainContext = mainCanvas.getContext("2d");
		window.onresize();
		window.hookEvent(mainCanvas, "mousewheel", scroll);
		window.getData();
		//step();
		//canvasLoop = setInterval(draw, 1000/30);
		setInterval(logic, 1000/30);
		//recalculatePerspective();
	}
}

window.onresize = function() {
	window.width = mainCanvas.parentNode.clientWidth;
	window.height = mainCanvas.parentNode.clientHeight;
	window.mainCanvas.width = width;
	window.mainCanvas.height = height;
	window.mainContext.translate(width/2, height/2);
	window.scale = Math.min(width/2, height/2)/Math.tan(Math.PI*30/180);
	window.draw();
}

window.onkeydown = function(event) {
	switch(event.keyCode) {
	case 65: //A
	case 37: //Left arrow
		window.camera.setLongitude(window.camera.getLongitude() - .05);
		window.cameraChanged = true;
		break;

	case 68: //D
	case 39: //Right arrow
		window.camera.setLongitude(window.camera.getLongitude() + .05);
		window.cameraChanged = true;
		break;

	case 87: //W
	case 38: //Up arrow
		var lat = window.camera.getLatitude();
		if(lat < 0) {
			window.camera.setLatitude(lat + .05);
			window.cameraChanged = true;
		}
		break;

	case 83: //S
	case 40: //Down arrow
		var lat = window.camera.getLatitude();
		if(lat > - Math.PI/2) {
			window.camera.setLatitude(lat - .05);
			window.cameraChanged = true;
		}
		break;

	case 107: //Numpad+
		window.cameraDistance -= .1;
		window.camera.setDistance(Math.pow(2, window.cameraDistance));
		window.cameraChanged = true;
		break;

	case 109: //Numpad-
		window.cameraDistance += .1;
		window.camera.setDistance(Math.pow(2, window.cameraDistance));
		window.cameraChanged = true;
		break;
	}
}

function getData() {
    // Mozilla/Safari
    if (window.XMLHttpRequest) {
        window.xmlHttpReq = new XMLHttpRequest();
    }
    // IE
    else if (window.ActiveXObject) {
        window.xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");
    }
    window.xmlHttpReq.open('POST', "data.php");
    window.xmlHttpReq.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    window.xmlHttpReq.onreadystatechange = function() {
        if (window.xmlHttpReq.readyState == 4) {
        	clearInterval(streamLoop);
            window.checkStream();
        }
    }
    window.xmlHttpReq.send(null);

    window.streamLoop = setInterval(window.checkStream, 1000/10);
}

function checkStream() {
	var newIndex = window.xmlHttpReq.responseText.lastIndexOf(":");
	
	if(newIndex > window.streamPosition) {
		var substr = window.xmlHttpReq.responseText.substring(window.streamPosition, newIndex);
		window.streamPosition = newIndex+1;
		window.getPoints(substr);
	}
}

function getquerystring() {
    var form     = document.forms['f1'];
    var word = form.word.value;
    qstr = 'w=' + escape(word);  // NOTE: no '?' before querystring
    return qstr;
}

function getPoints(text) {
	var chunks = text.split(":");
	for(var n in chunks) {
		var data = chunks[n].split(";");
		newPoint(data);
		
	}
	cameraChanged = true;
}

function newPoint(data) {
	var point = new DataPoint();

	var x = parseFloat(data[0]);
	var y = parseFloat(data[1]);
	var z = parseFloat(data[2]);
	point.setPosition($V([x, y, z]));

	var r = parseInt(data[3]);
	var g = parseInt(data[4]);
	var b = parseInt(data[5]);
	point.setColor([r, g, b, 1]);

	points.push(point);
}

function logic() {
	/*var currentTime = Date.now();
	var timeDiff = (currentTime - time)/1000;
	time = currentTime;

	*/

	if(cameraChanged) {
		for(var i in points) {
			points[i].setScreenPosition(camera.transform(points[i].getPosition()));
		}
		points.sort(DataPoint.compare);
		cameraChanged = false;
		draw();
	}
}

function draw() {

	mainContext.clearRect(-width/2, -height/2, width, height);


	for(var i = 0; i < points.length; i++){
		if(points[i].viewPosition.e(3) > 0)
			points[i].draw(mainContext);
	}
}


function hookEvent(element, eventName, callback)
{
	if(typeof(element) == "string")
		element = document.getElementById(element);
		if(element == null)
			return;
	if(element.addEventListener)
	{
		if(eventName == 'mousewheel')
			element.addEventListener('DOMMouseScroll', callback, false);  
		element.addEventListener(eventName, callback, false);
	}
	else if(element.attachEvent)
		element.attachEvent("on" + eventName, callback);
}

function unhookEvent(element, eventName, callback)
{
	if(typeof(element) == "string")
		element = document.getElementById(element);
		if(element == null)
			return;
	if(element.removeEventListener)
	{
		if(eventName == 'mousewheel')
			element.removeEventListener('DOMMouseScroll', callback, false);  
		element.removeEventListener(eventName, callback, false);
	}
	else if(element.detachEvent)
		element.detachEvent("on" + eventName, callback);
}

function scroll(e) {
	e = e ? e : window.event;
	var wheelData = e.detail ? e.detail * -1 : e.wheelDelta / 40;
	cameraDistance -= wheelData/30;
	
	camera.setDistance(Math.pow(2, cameraDistance));
	cameraChanged = true;
}