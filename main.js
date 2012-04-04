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
	mainCanvas = document.getElementById("mainCanvas");
	camera.setLatitude(-Math.PI * 45/180);
	camera.setDistance(2);

	if(mainCanvas.getContext) {
		mainContext = mainCanvas.getContext("2d");
		onresize();
		hookEvent(mainCanvas, "mousewheel", scroll);
		getData();
		//step();
		//canvasLoop = setInterval(draw, 1000/30);
		setInterval(logic, 1000/30);
		//recalculatePerspective();
	}
}

window.onresize = function() {
	width = mainCanvas.parentNode.clientWidth;
	height = mainCanvas.parentNode.clientHeight;
	mainCanvas.width = width;
	mainCanvas.height = height;
	mainContext.translate(width/2, height/2);
	scale = Math.min(width/2, height/2)/Math.tan(Math.PI*30/180);
	draw();
}

window.onkeydown = function(event) {
	switch(event.keyCode) {
	case 65: //A
	case 37: //Left arrow
		camera.setLongitude(camera.getLongitude() - .05);
		cameraChanged = true;
		break;

	case 68: //D
	case 39: //Right arrow
		camera.setLongitude(camera.getLongitude() + .05);
		cameraChanged = true;
		break;

	case 87: //W
	case 38: //Up arrow
		var lat = camera.getLatitude();
		if(lat < 0) {
			camera.setLatitude(lat + .05);
			cameraChanged = true;
		}
		break;

	case 83: //S
	case 40: //Down arrow
		var lat = camera.getLatitude();
		if(lat > - Math.PI/2) {
			camera.setLatitude(lat - .05);
			cameraChanged = true;
		}
		break;

	case 107: //Numpad+
		cameraDistance -= .1;
		camera.setDistance(Math.pow(2, cameraDistance));
		cameraChanged = true;
		break;

	case 109: //Numpad-
		cameraDistance += .1;
		camera.setDistance(Math.pow(2, cameraDistance));
		cameraChanged = true;
		break;
	}
}

function getData() {
	
    // Mozilla/Safari
    if (window.XMLHttpRequest) {
        xmlHttpReq = new XMLHttpRequest();
    }
    // IE
    else if (window.ActiveXObject) {
        xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlHttpReq.open('POST', "data2.php");
    xmlHttpReq.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xmlHttpReq.onreadystatechange = function() {
        if (xmlHttpReq.readyState == 4) {
        	clearInterval(streamLoop);
            checkStream();
        }
    }
    xmlHttpReq.send(null);

    streamLoop = setInterval(checkStream, 1000/10);
}

function checkStream() {
	var newIndex = xmlHttpReq.responseText.lastIndexOf(":");
	
	if(newIndex > streamPosition) {
		var substr = xmlHttpReq.responseText.substring(streamPosition, newIndex);
		streamPosition = newIndex+1;
		getPoints(substr);
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
	point.getDrawable().setPosition($V([x, y, z]));

	var r = parseInt(data[3]);
	var g = parseInt(data[4]);
	var b = parseInt(data[5]);
	point.getDrawable().setColor([r, g, b, 1]);

	points.push(point);
}

function step() {
	logic();
	draw();
}

function logic() {
	/*var currentTime = Date.now();
	var timeDiff = (currentTime - time)/1000;
	time = currentTime;

	*/

	if(cameraChanged) {
		var drawable;
		for(var i in points) {
			drawable = points[i].getDrawable();
			drawable.setScreenPosition(camera.transform(drawable.getPosition()));
		}
		points.sort(DataPoint.compare);
		cameraChanged = false;
		draw();
	}
}

function draw() {

	mainContext.clearRect(-width/2, -height/2, width, height);


	for(var i = 0; i < points.length; i++){
		points[i].getDrawable().draw(mainContext);
	}
}

function perspective(vector) {
	var newVector = new Array(3);

	if(vector[2] > 0) {
		newVector[0] = vector[0] / vector[2];
		newVector[1] = vector[1] / vector[2];
		newVector[2] = vector[2];
		return newVector;
	}

	return [0, 0, 0];
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