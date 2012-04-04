function Drawable() {
	this.color = [255, 255, 255, 1];
	this.position = $V([0, 0, 0]);
	this.viewPosition = $V([0, 0, 0]);

	this.draw = function(context) {}

	this.setPosition = function(vector) {
		this.position = vector;
	}

	this.getPosition = function() {
		return this.position;
	}

	this.setColor = function(rgbArray) {
		this.color = rgbArray;
	}

	this.setScreenPosition = function(vector) {
		this.viewPosition = vector;
	}


}

Drawable.compare = function(a, b) {
	return b.viewPosition.e(3) - a.viewPosition.e(3);
}