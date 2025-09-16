# QR Code System Documentation

## Overview
The CRMFMS QR Code System provides functionality to generate, manage, and display QR codes for faculty members and rooms. The system uses a local QR code generation implementation that creates QR-like patterns for identification purposes.

## Features

### 1. QR Code Generation
- **Automatic Generation**: QR codes are automatically generated when creating new faculty members or rooms
- **Bulk Generation**: Generate QR codes for existing records that don't have them
- **Customizable**: Support for different sizes and margins

### 2. QR Code Management
- **Preview**: View QR codes in a modal dialog
- **Download**: Download QR codes as PNG files
- **Regenerate**: Create new QR codes for existing records
- **Delete**: Remove QR codes from the system

### 3. Integration Points
- **Faculty Management**: QR codes are automatically created for new faculty members
- **Room Management**: QR codes are automatically created for new rooms
- **Attendance System**: QR codes can be scanned for attendance tracking

## API Endpoints

### QR Code Generation
```
GET /api/qr/generate.php?data={data}&size={size}&margin={margin}
```
- `data`: The text to encode in the QR code (required)
- `size`: Image size in pixels (100-600, default: 200)
- `margin`: Margin size in pixels (5-50, default: 10)

### QR Code Management
```
GET /api/qr/qr_manager.php?action=list
```
Returns a list of all QR codes with their associated data.

```
GET /api/qr/qr_manager.php?action=preview&code_id={id}
```
Generates a preview of the QR code for the specified ID.

```
GET /api/qr/qr_manager.php?action=download&code_id={id}
```
Downloads the QR code as a PNG file.

```
POST /api/qr/qr_manager.php
```
With JSON body containing:
- `action`: 'regenerate', 'bulk_generate', or 'delete'
- `code_id`: ID of the QR code (for regenerate/delete)
- `type`: 'faculty', 'room', or 'all' (for bulk_generate)

## Database Schema

### qr_codes Table
```sql
CREATE TABLE qr_codes (
    code_id INT AUTO_INCREMENT PRIMARY KEY,
    code_type VARCHAR(50) NOT NULL, -- 'faculty' or 'room'
    ref_id INT NOT NULL, -- Reference to user_id or room_id
    code_value VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## QR Code Values

### Faculty QR Codes
- Format: `QR-FACULTY-{employee_id}`
- Example: `QR-FACULTY-EMP001`

### Room QR Codes
- Format: `QR-ROOM-{room_code}`
- Example: `QR-ROOM-R101`

## Frontend Interface

### QR Management Page
Access the QR management interface at `/public/modules/rooms/rooms.html`

Features:
- View all QR codes in a table
- Statistics showing total, faculty, and room QR codes
- Bulk generation for missing QR codes
- Individual QR code actions (preview, download, regenerate, delete)

### Integration in Other Modules
QR codes are automatically displayed in:
- Faculty management pages
- Room management pages
- Attendance scanning interface

## Usage Examples

### Generate QR Code for Faculty
```javascript
// When creating a new faculty member
const response = await apiCall('/api/faculties/faculties.php', 'POST', {
    action: 'create',
    employee_id: 'EMP001',
    first_name: 'John',
    last_name: 'Doe',
    email: 'john.doe@example.com',
    password: 'password123',
    roles: [2] // faculty role ID
});

// Response includes qr_code_url
console.log(response.qr_code_url);
```

### Generate QR Code for Room
```javascript
// When creating a new room
const response = await apiCall('/api/rooms/rooms.php', 'POST', {
    action: 'create',
    floor_id: 1,
    room_code: 'R101',
    name: 'Computer Lab 1'
});

// Response includes qr_code_url
console.log(response.qr_code_url);
```

### Bulk Generate QR Codes
```javascript
// Generate QR codes for all missing records
const response = await apiCall('/api/qr/qr_manager.php', 'POST', {
    action: 'bulk_generate',
    type: 'all' // or 'faculty' or 'room'
});
```

## Technical Implementation

### SimpleQR Class
The system uses a custom `SimpleQR` class located in `/api/qr/simple_qr.php` that:
- Creates QR-like patterns based on data hashing
- Generates PNG images with customizable size and margin
- Includes corner markers for QR code appearance
- Supports both file output and direct browser output

### Error Handling
- Fallback error images when QR generation fails
- Proper HTTP status codes for API errors
- Input validation for all parameters

## Security Considerations

### Access Control
- QR management requires admin, dean, or secretary roles
- Bulk operations restricted to admin and dean roles
- Individual QR operations available to authorized users

### Data Validation
- All input parameters are validated and sanitized
- QR code values are unique and follow specific formats
- File operations include proper error handling

## Performance Notes

### Optimization
- QR codes are generated on-demand
- No persistent storage of QR code images
- Efficient database queries with proper indexing

### Limitations
- Current implementation is a simplified QR-like pattern
- For production use with actual QR scanning, consider implementing a proper QR code library
- Large bulk operations may take time for many records

## Future Enhancements

### Potential Improvements
1. **Real QR Code Library**: Implement a proper QR code library for actual scanning compatibility
2. **Caching**: Add caching for frequently accessed QR codes
3. **Batch Operations**: Optimize bulk operations for large datasets
4. **Custom Styling**: Add support for custom QR code colors and logos
5. **Analytics**: Track QR code usage and scanning statistics

### Integration Opportunities
1. **Mobile App**: QR codes can be scanned by mobile applications
2. **Print Integration**: Generate QR codes for printing on ID cards or room signs
3. **API Integration**: Provide RESTful API for external systems
4. **Reporting**: Generate reports on QR code usage and effectiveness

## Troubleshooting

### Common Issues
1. **QR Code Not Generating**: Check if GD extension is enabled in PHP
2. **Permission Errors**: Ensure proper file permissions for QR code generation
3. **Database Errors**: Verify qr_codes table exists and has proper structure
4. **API Errors**: Check error logs for detailed error messages

### Debug Mode
Enable debug mode by checking PHP error logs and ensuring proper error reporting is configured.

## Support
For technical support or questions about the QR code system, refer to the system administrator or check the application logs for detailed error information.
