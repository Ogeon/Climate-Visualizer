function DataPoint() {

	this.draw = function(context) {
		var point = this.viewPosition;
		point = point.x(scale);
		var size = 2*scale/point.e(3);

		r = this.color[0];
		g = this.color[1];
		b = this.color[2];

		context.fillStyle = "rgb("+r+", "+g+","+b+")";
		context.beginPath();

		context.moveTo(point.e(1) - size, point.e(2) - size);
		context.lineTo(point.e(1) + size, point.e(2) - size);
		context.lineTo(point.e(1) + size, point.e(2) + size);
		context.lineTo(point.e(1) - size, point.e(2) + size);

		context.closePath();
		context.fill();
	}

	this.getDrawable = function() {
		return this.drawable;
	}

}

DataPoint.compare = Drawable.compare;

DataPoint.prototype = new Drawable();
DataPoint.prototype.contructor = DataPoint;