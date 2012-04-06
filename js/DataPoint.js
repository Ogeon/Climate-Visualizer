function DataPoint() {

	this.draw = function(context) {
		var polygon = this.viewPolygon;

		r = this.color[0];
		g = this.color[1];
		b = this.color[2];

		context.fillStyle = #fff;//"rgb("+r+", "+g+","+b+")";
		context.beginPath();


		context.moveTo(polygon[0].e(1)*scale*20, polygon[0].e(2)*scale*20);
		for(var i = 1, len = polygon.length; i < len; i++) {
			context.lineTo(polygon[i].e(1)*scale*20, polygon[i].e(2)*scale*20);
		}

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