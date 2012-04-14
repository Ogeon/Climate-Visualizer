function Drawable() {
	this.color = [255, 255, 255];
	this.polygon = new Array();
	this.viewPolygon = new Array();
	this.closestDepth = 0;
	this.centrum = $V([0, 0, 0]);
	this.viewCentrum = $V([0, 0, 0]);

	this.draw = function(context) {}

}

Drawable.compare = function(a, b) {
	return a.closestDepth - b.closestDepth;
}