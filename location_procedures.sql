delimiter $$
DROP PROCEDURE IF EXISTS around_place $$
CREATE PROCEDURE around_place (IN placeid int, IN dist float(10,6)) BEGIN
declare mylat float(10,6);
declare mylon float(10,6);
declare lonMin float(10,6);
declare lonMax float(10,6);
declare latMin float(10,6);
declare latMax float(10,6);
declare halfSquareDiagonal float(10,6);
declare r float(10,6);
declare deltaLon float(10,6);
-- get the original lon and lat for the placeid
SELECT latitude, longitude into mylat, mylon from place where id=placeid limit 1;
-- calculate r, lon and lat for the rectangle:
set halfSquareDiagonal = dist / Sqrt(2);
set r = (halfSquareDiagonal/6367);
set deltaLon = asin(sin(r)/cos(radians(mylat)));
set latMin = degrees(radians(mylat) - r);
set latMax = degrees(radians(mylat) + r);
set lonMin = mylon - degrees(deltaLon);
set lonMax = mylon + degrees(deltaLon);
-- SELECT latMin, latMax, lonMin, lonMax, deltaLon, r, placeid, mylat, mylon, dist, halfSquareDiagonal; -- For DEBUG
SELECT place.*, ( 6367 * acos( cos( radians(mylat) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(mylon) ) + sin( radians(mylat) ) * sin( radians( latitude ) ) ) ) AS distance FROM place WHERE (latitude >= latMin AND latitude <= latMax) AND (longitude >= lonMin AND longitude <= lonMax) HAVING distance <= dist ORDER BY distance;
END $$

DROP PROCEDURE IF EXISTS around_location $$
CREATE PROCEDURE around_location (IN mylat float(10,6), IN mylon float(10,6), IN dist float(10,6)) BEGIN
declare lonMin float(10,6);
declare lonMax float(10,6);
declare latMin float(10,6);
declare latMax float(10,6);
declare halfSquareDiagonal float(10,6);
declare r float(10,6);
declare deltaLon float(10,6);
-- calculate r, lon and lat for the rectangle:
set halfSquareDiagonal = dist / Sqrt(2);
set r = (halfSquareDiagonal/6367);
set deltaLon = asin(sin(r)/cos(radians(mylat)));
set latMin = degrees(radians(mylat) - r);
set latMax = degrees(radians(mylat) + r);
set lonMin = mylon - degrees(deltaLon);
set lonMax = mylon + degrees(deltaLon);
-- SELECT latMin, latMax, lonMin, lonMax, deltaLon, r, mylat, mylon, dist, halfSquareDiagonal; -- For DEBUG
SELECT place.*, ( 6367 * acos( cos( radians(mylat) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(mylon) ) + sin( radians(mylat) ) * sin( radians( latitude ) ) ) ) AS distance FROM place WHERE (latitude >= latMin AND latitude <= latMax) AND (longitude >= lonMin AND longitude <= lonMax) HAVING distance <= dist ORDER BY distance;
END $$

DROP PROCEDURE IF EXISTS bounds_around_location $$
CREATE PROCEDURE bounds_around_location (IN mylat float(10,6), IN mylon float(10,6), IN dist float(10,6)) BEGIN
declare lonMin float(10,6);
declare lonMax float(10,6);
declare latMin float(10,6);
declare latMax float(10,6);
declare halfSquareDiagonal float(10,6);
declare r float(10,6);
declare deltaLon float(10,6);
-- calculate r, lon and lat for the rectangle:
set halfSquareDiagonal = dist / Sqrt(2);
set r = (halfSquareDiagonal/6367);
set deltaLon = asin(sin(r)/cos(radians(mylat)));
set latMin = degrees(radians(mylat) - r);
set latMax = degrees(radians(mylat) + r);
set lonMin = mylon - degrees(deltaLon);
set lonMax = mylon + degrees(deltaLon);
SELECT latMin, latMax, lonMin, lonMax, deltaLon, r, mylat, mylon, dist, halfSquareDiagonal;
END $$
delimiter ;

-- CALL around_location (40.629181, -8.656120, 0.05);
-- CALL around_place(12, 0.05);
-- CALL around_location(40.63108, -8.65862, 0.1);