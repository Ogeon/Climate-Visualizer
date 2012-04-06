var startX = 0;            // mouse starting positions
var startY = 0;
var offsetX = 0;           // current element offset
var offsetY = 0;
var dragElement;           // needs to be passed from OnMouseDown to OnMouseMove
var oldZIndex = 0;         // we temporarily increase the z-index during drag
//var debug = $('debug');    // makes life easier

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
	window.camera.setDistance(Math.pow(2, cameraDistance));

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

InitDragDrop();

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

	var normal = $V([
						parseFloat(data[0]),
						parseFloat(data[1]),
						parseFloat(data[2])
					]);

	var polygon = 	[
						$V([
							parseFloat(data[3]),
							parseFloat(data[4]),
							parseFloat(data[5])
						]),
						$V([
							parseFloat(data[6]),
							parseFloat(data[7]),
							parseFloat(data[8])
						]),
						normal
					];

	point.polygon = polygon;

	var r = parseInt(data[3]);
	var g = parseInt(data[4]);
	var b = parseInt(data[5]);
	point.color = [r, g, b];

	points.push(point);
}

function logic() {
	/*var currentTime = Date.now();
	var timeDiff = (currentTime - time)/1000;
	time = currentTime;

	*/

	if(cameraChanged) {
		for(var i in points) {
			camera.transform(points[i]);
		}
		points.sort(DataPoint.compare);
		cameraChanged = false;
		draw();
	}
}

function draw() {

	mainContext.clearRect(-width/2, -height/2, width, height);


	for(var i = 0; i < points.length; i++){
		if(points[i].meanDepth > 0)
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

function OnMouseDown(e)
{
    // IE is retarded and doesn't pass the event object
    if (e == null) 
        e = window.event; 
    
    // IE uses srcElement, others use target
    var target = e.target != null ? e.target : e.srcElement;
    
    /*debug.innerHTML = target.className == 'drag' 
        ? 'draggable element clicked' 
        : 'NON-draggable element clicked';*/

    // for IE, left click == 1
    // for Firefox, left click == 0
    if ((e.button == 1 && window.event != null || 
        e.button == 0) && 
        target.className == 'drag')
    {
        // grab the mouse position
        startX = e.clientX;
        startY = e.clientY;
        
        // grab the clicked element's position
        offsetX = ExtractNumber(target.style.left);
        offsetY = ExtractNumber(target.style.top);
        
        // bring the clicked element to the front while it is being dragged
        oldZIndex = target.style.zIndex;
        target.style.zIndex = 10000;
        
        // we need to access the element in OnMouseMove
        dragElement = target;

        // tell our code to start moving the element with the mouse
        document.onmousemove = OnMouseMove;
        
        // cancel out any text selections
        document.body.focus();

        // prevent text selection in IE
        document.onselectstart = function () { return false; };
        // prevent IE from trying to drag an image
        target.ondragstart = function() { return false; };
        
        // prevent text selection (except IE)
        return false;
    }
}

function InitDragDrop()
{
    document.onmousedown = OnMouseDown;
    document.onmouseup = OnMouseUp;
}

function OnMouseMove(e)
{
    if (e == null) 
        var e = window.event; 

    // this is the actual "drag code"

    var parent = dragElement.parentNode;

    var x = offsetX + e.clientX - startX;
    var y = offsetY + e.clientY - startY;

    if(x < 0)
    	x = 0;

    if(y < 0)
    	y = 0;

    if(x + dragElement.clientWidth > parent.clientWidth) 
    	x = parent.clientWidth - dragElement.clientWidth;

    if(y + dragElement.clientHeight > parent.clientHeight) 
    	y = parent.clientHeight - dragElement.clientHeight;

    dragElement.style.left = x + 'px';
    dragElement.style.top = y + 'px';
    
    /*debug.innerHTML = '(' + dragElement.style.left + ', ' + 
        dragElement.style.top + ')';   */
}

function OnMouseUp(e)
{
    if (dragElement != null)
    {
        dragElement.style.zIndex = oldZIndex;

        // we're done with these events until the next OnMouseDown
        document.onmousemove = null;
        document.onselectstart = null;
        dragElement.ondragstart = null;

        // this is how we know we're not dragging      
        dragElement = null;
        
        //debug.innerHTML = 'mouse up';
    }
}

function ExtractNumber(value)
{
    var n = parseInt(value);
	
    return n == null || isNaN(n) ? 0 : n;
}