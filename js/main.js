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
var cameraDistance = 0;

var cameraChanged = true;
var redrawScene = true;

var xmlHttpReq = false;
var streamPosition = 0;
var streamLoop = null;
var parseTemperatures = false;

var width = 1;
var height = 1;
var scale = 1;

var mousePos = $V([0, 0, 0]);

var colors = [
				[159, 191, 255],
				[176, 202, 255],
				[227, 233, 255],
				[230, 235, 255],
				[255, 209, 	63],
				[255, 150,  12],
				[255,  65,  00]
			];

var firstYear = null;
var latestYear = 0;
var temperatures = new Array();
var currentTemperatures = null;
var timeIndex = 0;

var menu;
var statusBar;
var timeline;
var timelineSlider;

var timeSpeed = 0;

var graphView;
var aboutView;

var currentView = 0;

window.onload = function() {
	window.mainCanvas = document.getElementById("mainCanvas");
	window.camera.setLatitude(-Math.PI * 45/180);
	window.camera.setDistance(Math.pow(2, cameraDistance));

	menu = document.getElementById("menu");
	statusBar = document.getElementById("statusBar");
	timeline = document.getElementById("timeline");
	timelineSlider = document.getElementById("slider");
	graphView = document.getElementById("graphs");
	aboutView = document.getElementById("about");

	if(window.mainCanvas.getContext) {
		window.mainContext = mainCanvas.getContext("2d");
		window.onresize();
		window.hookEvent(mainCanvas, "mousewheel", scroll);
		window.getData();
		statusBar.innerHTML = "Loading map...";
		statusBar.style.top = "0px";
		statusBar.style.opacity = "1";
		//step();
		//canvasLoop = setInterval(draw, 1000/30);
		setInterval(logic, 1000/30);
		//recalculatePerspective();
	}
}

InitDragDrop();

window.onresize = function() {
	if(mainCanvas != null) {
		window.width = mainCanvas.parentNode.clientWidth;
		window.height = mainCanvas.parentNode.clientHeight;
		window.mainCanvas.width = width;
		window.mainCanvas.height = height;
		window.mainContext.translate(width/2, height/2);
		window.scale = Math.min(width/2, height/2)/Math.tan(Math.PI*30/180);
		window.draw();
		menu.style.top = Math.round((height - menu.clientHeight)/2) + "px";
	}
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

//document.onmousemove = trackMouse;

function trackMouse(e) {
	if(e == null)
		e = window.event;

	var x = e.clientX - width/2;
	var y = e.clientY - height/2;

	mousePos = $V([x, y, 0]);

	var z = 100000000000000000000;
	var dist = 100000000000000000000;

	for(var i = points.length; i--;){
		var d = points[i].centrum.x(scale).distanceFrom(mousePos);
		if(d < dist) {
			dist = d;
			z = points[i].centrum.e(3);
		}
	}

	mousePos = $V([mousePos.e(1), mousePos.e(2), z]);

	draw();
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
        	statusBar.style.top = "-30px";
			statusBar.style.opacity = "0";
        	clearInterval(streamLoop);
            window.checkStream();
        }
    }
    window.xmlHttpReq.send(null);

    window.streamLoop = setInterval(window.checkStream, 1000/4);
}

function checkStream() {
	var newIndex = window.xmlHttpReq.responseText.lastIndexOf(":");
	
	if(newIndex > window.streamPosition) {
		var substr = window.xmlHttpReq.responseText.substring(window.streamPosition, newIndex);
		window.streamPosition = newIndex+1;
		window.parseData(substr);
	}
}

function getquerystring() {
    var form     = document.forms['f1'];
    var word = form.word.value;
    qstr = 'w=' + escape(word);  // NOTE: no '?' before querystring
    return qstr;
}

function parseData(text) {
	var chunks = text.split(":");
	for(var n = 0, len = chunks.length; i < len; i++) {
		if(!parseTemperatures) {
			if(chunks[n] == "T") {
				cameraChanged = true;
				parseTemperatures = true;
				statusBar.style.top = "-30px";
				statusBar.style.opacity = "0";
				continue;
			}
		}

		var data = chunks[n].split(";");

		if(parseTemperatures)
			newMonth(data);
		else {
			newPoint(data);
		}
		
	}
	
	if(!parseTemperatures)
			cameraChanged = true;
}

function newPoint(data) {
	var point = new DataPoint();

	var centrum = $V([
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
						$V([
							parseFloat(data[9]),
							parseFloat(data[10]),
							parseFloat(data[11])
						]),
						$V([
							parseFloat(data[12]),
							parseFloat(data[13]),
							parseFloat(data[14])
						])
					];

	point.centrum = centrum;
	point.polygon = polygon;

	var r = Math.round(parseInt(data[15])/2);
	var g = Math.round(parseInt(data[16])/2);
	var b = Math.round(parseInt(data[17])/2);
	point.color = [r, g, b];

	point.index = points.length;

	points.push(point);
}

function newMonth(data) {
	var date = data[0].split("-");
	var year = parseInt(date[0]);

	if(firstYear == null) {
		firstYear = year;
		latestYear = year;
	}

	if(year > latestYear) {
		latestYear = year;
		statusBar.innerHTML = "Loading data for year " + year + "...";

		var slider = timelineSlider;
		slider.style.width = (100/(latestYear - firstYear + 1))+"%";
	}

	var temps = new Array();
	for(var i = 1, len = data.length; i < len; i++) {
		temps.push(parseFloat(data[i]));
	}

	temperatures[temperatures.length] = temps;
	updateTimeline(timeIndex);

	if(currentTemperatures == null){
		statusBar.style.top = "0px";
		statusBar.style.opacity = "1";
		document.getElementById("time-text").innerHTML = year + "-1";

		if(currentView == 0) {
			timeline.style.bottom = "0px";
			timeline.style.opacity = "1";
		}
		
		currentTemperatures = temperatures[0];
		draw();
	}
}

function temperatureToColor(index) {
	 //clamp index to 0-1 and scale it to match the color array
	index = Math.max(0, Math.min(1, index)) * (colors.length-1);

	//Calculate the transition between two colors
	var trans = index - Math.floor(index);
	
	//Take a shortcut if index is an exact match
	if(trans == 0)
		return colors[index];
	
	//Interpolate between two colors
	var minI = Math.floor(index);
	var maxI = Math.ceil(index);
	
	var r = colors[minI][0] + trans*(colors[maxI][0]-colors[minI][0]);
	var g = colors[minI][1] + trans*(colors[maxI][1]-colors[minI][1]);
	var b = colors[minI][2] + trans*(colors[maxI][2]-colors[minI][2]);
	return [Math.round(r), Math.round(g), Math.round(b)];
}

function updateTimeline(monthIndex) {
	var max = temperatures.length-1;

	if(monthIndex < 0)
		monthIndex = 0;

	if(monthIndex > max)
		monthIndex = max;

	var slider = timelineSlider;
	var parent = slider.parentNode;

	var x = monthIndex/max;
	x *= parent.clientWidth - slider.clientWidth;

	slider.style.left = Math.round(x) + "px";

    var year = Math.floor(monthIndex/12);
    var month = monthIndex - year*12;

    document.getElementById("time-text").innerHTML =
    		(year + firstYear) + "-" + (month+1);
}

function stepMonths(stepSize) {
	var max = temperatures.length-1;

	timeIndex += stepSize;

	if(timeIndex < 0)
		timeIndex = 0;

	if(timeIndex > max)
		timeIndex = max;

	currentTemperatures = temperatures[timeIndex];
	updateTimeline(timeIndex);
	draw();
}

function logic() {
	/*var currentTime = Date.now();
	var timeDiff = (currentTime - time)/1000;
	time = currentTime;

	*/

	if(timeSpeed != 0)
		stepMonths(timeSpeed);

	if(cameraChanged) {
		var points = window.points;
		for(var i = points.length; i--;) {
			camera.transform(points[i]);
		}
		points.sort(DataPoint.compare);
		cameraChanged = false;
		draw();
	}

	if(redrawScene) {
		draw();
		redrawScene = false;
	}
}

function draw() {
	mainContext.clearRect(-width/2, -height/2, width, height);

	var p;

	var temps = currentTemperatures;

	for(var i = points.length; i--;){
		p = points[i];
		//
			p.draw(mainContext);
		//}

		if(temperatures.length > 0) {
			var temp = (temps[p.index] - 240)/70;
			
			p.drawData(mainContext, temp, temperatureToColor(temp), mousePos);
		}
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
    
    var index = x / (parent.clientWidth - dragElement.clientWidth);
    index = Math.floor(index * (temperatures.length-1));
    timeIndex = index;

    var year = Math.floor(index/12);
    var month = index - year*12;

    document.getElementById("time-text").innerHTML =
    		(year + firstYear) + "-" + (month+1);

	currentTemperatures = temperatures[timeIndex];
	redrawScene = true;

    /*debug.innerHTML = '(' + dragElement.style.left + ', ' + 
        dragElement.style.top + ')';   */
}

function OnMouseUp(e)
{
	timeSpeed = 0;

    if (dragElement != null)
    {
        dragElement.style.zIndex = oldZIndex;

        currentTemperatures = temperatures[timeIndex];
        draw();

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

function setView(index) {
	if(index == 0 && temperatures.length > 0) {
		timeline.style.bottom = "0px";
		timeline.style.opacity = "1";
	} else {
		timeline.style.bottom = "-30px";
		timeline.style.opacity = "0";
	}

	mainCanvas.style.top = -100*index + "%";
	graphView.style.top = (100 -100*index) + "%";
	aboutView.style.top = (200 -100*index) + "%";

	currentView = index;
}