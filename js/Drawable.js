function Drawable() {
	this.color = [255, 255, 255];
	this.polygon = new Array();
	this.viewPolygon = new Array();

	this.draw = function(context) {}

}

Drawable.compare = function(a, b) {
	return b.meanDepth - a.meanDepth;
}