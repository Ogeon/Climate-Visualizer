function DataPoint() {

	this.draw = function(context) {
		var polygon = this.viewPolygon;
		var scale = window.scale;
		context.fillStyle = "rgb(" + this.color[0] +
								", " + this.color[1] +
								", " + this.color[2] + ")";
		context.beginPath();

		context.moveTo(polygon[0].e(1)*scale, polygon[0].e(2)*scale);
		for(var i = 1, len = polygon.length; i < len; i++) {
			context.lineTo(polygon[i].e(1)*scale, polygon[i].e(2)*scale);
		}

		context.closePath();
		context.fill();
	}

}

DataPoint.compare = Drawable.compare;

DataPoint.prototype = new Drawable();
DataPoint.prototype.contructor = DataPoint;