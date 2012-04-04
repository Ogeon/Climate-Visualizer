importScripts("Drawable.js", "DataPoint.js");

var points = new Array();
var distance = 2;
var longitude = 0;
var latitude = 0;

onmessage = function(event) {
	var params = event.data;

	if(params["type"] == "newPoint") {
		newPoint(params["chunk"]);
	} else if(params["type"] == "getDrawables") {
		sendDrawables();
	} else if(params["type"] == "updateCamera") {
		distance = params["distance"];
		longitude = params["longitude"];
		latitude = params["latitude"];
		updatePerspective();
	}
	
}

function newPoint(data) {
	var point = new DataPoint();

	var x = parseFloat(data[0]);
	var y = parseFloat(data[1]);
	var z = parseFloat(data[2]);
	point.getDrawable().setPosition([x, y, z]);

	var r = parseInt(data[3]);
	var g = parseInt(data[4]);
	var b = parseInt(data[5]);
	point.getDrawable().setColor([r, g, b, 1]);

	points.push(point);
}

function sendDrawables() {
	var drawables = points.map(function(p) {return p.getDrawable().clone();});
	postMessage({"type": "sendDrawables", "drawables": drawables});
}

function updatePerspective() {
	var position;
	for (var i = points.length - 1; i >= 0; i--) {
		position = points[i].getDrawable().getPosition();
		position = rotateZ(position, longitude);
		position = rotateX(position, latitude);
		position = translateVector(position, [0, 0, Math.pow(2, distance)]);
		position = perspective(position);
		points[i].getDrawable().setScreenPosition(position);
	};

	//sendDrawables();
}

function rotateX(vector, angle) {
	var newVector = new Array(3);

	newVector[0] = vector[0];
	newVector[1] = vector[1]*Math.cos(angle) - vector[2]*Math.sin(angle);
	newVector[2] = vector[1]*Math.sin(angle) + vector[2]*Math.cos(angle);

	return newVector;
}

function rotateZ(vector, angle) {
	var newVector = new Array(3);

	newVector[0] = vector[0]*Math.cos(angle) - vector[1]*Math.sin(angle);
	newVector[1] = vector[0]*Math.sin(angle) + vector[1]*Math.cos(angle);
	newVector[2] = vector[2];

	return newVector;
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

function translateVector(vector, translation) {
	var newVector = new Array(3);

	newVector[0] = vector[0] + translation[0]
	newVector[1] = vector[1] + translation[1]
	newVector[2] = vector[2] + translation[2]

	return newVector;
}

function scaleVector(vector, scale) {
	var newVector = new Array(3);

	newVector[0] = vector[0] * scale;
	newVector[1] = vector[1] * scale;
	newVector[2] = vector[2] * scale;

	return newVector;
}