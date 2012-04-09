function DataPoint() {

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

		var baseAlpha = Math.min(0.4, Math.max(0.2, 1-1/z));
		var alpha = Math.max(baseAlpha, Math.min(1, 100/mousePos.distanceFrom(start.x(scale))));

		var end = this.viewNormal.x(value/(10000*z)).add(start);

		//Stick
		context.strokeStyle = "rgba(" + color[0] +
								 ", " + color[1] +
								 ", " + color[2] + 
								 ", " + 0.7*alpha + ")";
		context.beginPath();

		context.moveTo(start.e(1) * scale, start.e(2) * scale);
		context.lineTo(end.e(1) * scale, end.e(2) * scale);

		context.closePath();
		context.stroke();


		//Ball
		var size = scale*.0005/z;
		if(size < 1.5)
			return;
		context.beginPath();

		context.fillStyle = "rgba(" + color[0] +
								 ", " + color[1] +
								 ", " + color[2] + 
								 ", " + alpha + ")";
		context.arc(end.e(1) * scale, end.e(2) * scale, size, 0, Math.PI*2, true);

		context.closePath();
		context.fill();

	}

}

DataPoint.compare = Drawable.compare;

DataPoint.prototype = new Drawable();
DataPoint.prototype.contructor = DataPoint;