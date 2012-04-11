function DataPoint() {
	this.index = 0;

	this.draw = function(context) {
		var polygon = this.viewPolygon;
		var scale = window.scale;
		context.fillStyle = "rgb(" + this.color[0] +
								", " + this.color[1] +
								", " + this.color[2] + ")";
		context.beginPath();

		if(polygon.length > 0)
			context.moveTo(polygon[0].e(1)*scale, polygon[0].e(2)*scale);
		for(var i = 1, len = polygon.length; i < len; i++) {
			if(polygon[i].e(3) > .015)
				context.lineTo(polygon[i].e(1)*scale, polygon[i].e(2)*scale);
		}

		context.closePath();
		context.fill();
	}

	this.drawData = function(context, value, color, mousePos) {
		var start = this.centrum;
		var z = start.e(3);
		if(z < .015)
			return;

		var v = Math.max(0, Math.min(1, value));

		var polygon = this.viewPolygon;
		var len = polygon.length;
		var newPolygon = new Array(len);
		for(var i = len; i--;) {
			newPolygon[i] = start.x(1-v).add(polygon[i].x(v));
		}

		context.beginPath();

		context.fillStyle = "rgba(" + color[0] +
								 ", " + color[1] +
								 ", " + color[2] + 
								 ", " + .5 + ")";

		if(len > 0)
			context.moveTo(newPolygon[0].e(1)*scale, newPolygon[0].e(2)*scale);
		for(var i = 1; i < len; i++) {
			if(polygon[i].e(3) > .015)
				context.lineTo(newPolygon[i].e(1)*scale, newPolygon[i].e(2)*scale);
		}

		context.closePath();
		context.fill();

	}

}

DataPoint.compare = Drawable.compare;

DataPoint.prototype = new Drawable();
DataPoint.prototype.contructor = DataPoint;