-- Add floors 2 to 5 for 'Main South' building

SET @main_south_building_id = (SELECT building_id FROM buildings WHERE name = 'Main South');

INSERT IGNORE INTO floors (building_id, floor_number, name)
VALUES
    (@main_south_building_id, 2, 'Second Floor'),
    (@main_south_building_id, 3, 'Third Floor'),
    (@main_south_building_id, 4, 'Fourth Floor'),
    (@main_south_building_id, 5, 'Fifth Floor');
