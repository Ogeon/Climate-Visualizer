function Camera() {
	this.longitude = 0;
	this.latitude = 0;
	this.distance = $V([0, 0, 0]);

	this.rotZMatrix = Matrix.RotationZ(0);
	this.rotXMatrix = Matrix.RotationX(0);
	this.rotMatrix = null;

	this.setLongitude = function(lon) {
		this.longitude = lon;
		this.rotZMatrix = Matrix.RotationZ(this.longitude);
		this.rotMatrix = null;
	}

	this.getLongitude = function() {
		return this.longitude;
	}

	this.setLatitude = function(lat) {
		this.latitude = lat;
		this.rotXMatrix = Matrix.RotationX(this.latitude);
		this.rotMatrix = null;
	}

	this.getLatitude = function() {
		return this.latitude;
	}

	this.setDistance = function(dist) {
		this.distance = $V([0, 0, dist]);
	}

	this.getDistance = function() {
		return this.distance.e(3);
	}

	this.transform = function(object) {
		if(this.rotMatrix == null) {
			this.rotMatrix = this.rotXMatrix.x(this.rotZMatrix);
		}

		var polygon = object.polygon;
		var len = polygon.length;
		var newPoly = new Array(len);
		var meanDepth = 0;
		var v;
		var z;
		for(var i = len; i--;) {
			v = this.rotMatrix.x(polygon[i]);
			v = v.add(this.distance);
			z = v.e(3);
			meanDepth += z;
			newPoly[i] = $V([v.e(1)/z, v.e(2)/z, z]);
		}

		object.meanDepth /= len;
		object.viewPolygon = newPoly;
	}
}