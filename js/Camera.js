function Camera() {
	this.longitude = 0;
	this.latitude = 0;
	this.distance = $V([0, 0, 0]);

	this.rotZMatrix = Matrix.RotationZ(0);
	this.rotXMatrix = Matrix.RotationX(0);
	this.rotMatrix = null;
	this.normMatrix = null;

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
		this.distance = dist;
	}

	this.getDistance = function() {
		return this.distance.e(3);
	}

	this.transform = function(object) {
		if(this.rotMatrix == null) {
			this.rotMatrix = this.rotXMatrix.x(this.rotZMatrix);
			this.normMatrix = this.rotMatrix.transpose().inv();
		}

		var m = this.rotMatrix;

		object.viewNormal = this.normMatrix.x(object.normal);

		if(object.viewNormal.e(3) > 0)
			return;

		var polygon = object.polygon;
		var len = polygon.length;
		var newPoly = new Array(len);
		var closestDepth = -10000000000;
		var v;
		var z;
		for(var i = len; i--;) {
			v = m.x(polygon[i]);
			z = v.e(3) + this.distance;
			closestDepth = Math.max(closestDepth, z);
			newPoly[i] = $V([v.e(1)/z, v.e(2)/z, z]);
		}


		object.closestDepth = closestDepth;
		object.viewPolygon = newPoly;
	}
}