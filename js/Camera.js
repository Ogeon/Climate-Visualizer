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

	this.transform = function(vector) {
		if(this.rotMatrix == null) {
			this.rotMatrix = this.rotXMatrix.x(this.rotZMatrix);
		}

		var v = this.rotMatrix.x(vector);
		v = v.add(this.distance);
		var z = v.e(3);
		return $V([v.e(1)/z, v.e(2)/z, z]);
	}
}