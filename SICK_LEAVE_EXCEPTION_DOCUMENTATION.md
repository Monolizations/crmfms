# Sick Leave Exception Documentation

## Overview
The CRMFMS Leave Management System now includes a special exception for sick leave requests, allowing them to be submitted without the standard 14-day advance notice requirement.

## Feature Details

### **Sick Leave Exception Rules**

1. **No Advance Notice Required**: Sick leave can be requested on the same day or retroactively
2. **Retroactive Limit**: Sick leave can be requested up to 7 days in the past
3. **Same Day Requests**: Sick leave can be requested for the current day
4. **All Other Leave Types**: Still require 14 days advance notice

### **Leave Types Supported**

- **Sick Leave** - Exception applies (no 14-day rule)
- **Vacation Leave** - 14-day rule applies
- **Personal Leave** - 14-day rule applies  
- **Other** - 14-day rule applies

## Implementation Details

### **Database Changes**
- Added `leave_type` column to `leave_requests` table
- Column type: `ENUM('Sick Leave', 'Vacation Leave', 'Personal Leave', 'Other')`
- Default value: `'Other'`
- Migration file: `migration_add_leave_type.sql`

### **Backend API Changes** (`/api/leaves/leaves.php`)

#### **Leave Creation Logic**
```php
// Enforce 2 weeks prior rule - EXCEPTION for Sick Leave
$minDate = date('Y-m-d', strtotime('+14 days'));
if ($start < $minDate && $leave_type !== 'Sick Leave') {
    echo json_encode(['success'=>false,'message'=>'Leave must be requested at least 2 weeks in advance (except for sick leave)']);
    exit;
}

// Additional validation for sick leave (can be requested same day or retroactively within reasonable limits)
if ($leave_type === 'Sick Leave') {
    $maxSickLeaveRetroactive = date('Y-m-d', strtotime('-7 days')); // Allow sick leave up to 7 days retroactively
    if ($start < $maxSickLeaveRetroactive) {
        echo json_encode(['success'=>false,'message'=>'Sick leave cannot be requested more than 7 days in the past']);
        exit;
    }
}
```

#### **Database Insert**
```php
$stmt = $db->prepare("INSERT INTO leave_requests(user_id,leave_type,start_date,end_date,reason,status) 
                      VALUES(:u,:lt,:s,:e,:r,'pending')");
$stmt->execute([':u'=>$uid, ':lt'=>$leave_type, ':s'=>$start, ':e'=>$end, ':r'=>$reason]);
```

### **Frontend Changes**

#### **JavaScript Validation** (`/public/modules/leaves/leaves.js`)
```javascript
// Apply 14-day advance notice rule - EXCEPTION for Sick Leave
if (diffDays < 14 && leaveType !== 'Sick Leave') {
    alert("Leave requests must be submitted at least two weeks (14 days) in advance (except for sick leave).");
    return;
}

// Additional validation for sick leave (can be retroactive up to 7 days)
if (leaveType === 'Sick Leave') {
    const maxSickLeaveRetroactive = new Date();
    maxSickLeaveRetroactive.setDate(today.getDate() - 7); // 7 days ago
    
    if (startDate < maxSickLeaveRetroactive) {
        alert("Sick leave cannot be requested more than 7 days in the past.");
        return;
    }
}
```

#### **UI Updates**
- Added "Type" column to leave requests table
- Updated table headers to include leave type
- Leave type dropdown already existed in the form

## Usage Examples

### **Sick Leave Scenarios**

1. **Same Day Request**
   - Date: Today
   - Type: Sick Leave
   - Result: ✅ **ALLOWED**

2. **Retroactive Request**
   - Date: 3 days ago
   - Type: Sick Leave
   - Result: ✅ **ALLOWED**

3. **Too Far in Past**
   - Date: 10 days ago
   - Type: Sick Leave
   - Result: ❌ **REJECTED** (more than 7 days)

### **Other Leave Types**

1. **Vacation Leave - Same Day**
   - Date: Today
   - Type: Vacation Leave
   - Result: ❌ **REJECTED** (needs 14 days notice)

2. **Personal Leave - Future**
   - Date: 20 days from now
   - Type: Personal Leave
   - Result: ✅ **ALLOWED** (14+ days notice)

## Database Migration

To apply the database changes, run:
```sql
-- Run the migration script
source migration_add_leave_type.sql;
```

Or manually execute:
```sql
ALTER TABLE leave_requests 
ADD COLUMN leave_type ENUM('Sick Leave', 'Vacation Leave', 'Personal Leave', 'Other') DEFAULT 'Other' 
AFTER user_id;

UPDATE leave_requests 
SET leave_type = 'Other' 
WHERE leave_type IS NULL;

ALTER TABLE leave_requests 
MODIFY COLUMN leave_type ENUM('Sick Leave', 'Vacation Leave', 'Personal Leave', 'Other') NOT NULL DEFAULT 'Other';
```

## User Experience

### **For Faculty Members**
1. Select "Sick Leave" from the leave type dropdown
2. Can choose today's date or up to 7 days in the past
3. Receive confirmation message: "Sick leave request submitted (no advance notice required)"
4. See leave type displayed in their leave requests table

### **For Administrators**
1. Can see leave type in the leave requests table
2. Sick leave requests appear with "Sick Leave" type
3. Can approve/deny sick leave requests like any other leave type
4. Clear indication of which requests are sick leave exceptions

## Validation Messages

### **Success Messages**
- **Sick Leave**: "Sick leave request submitted (no advance notice required)"
- **Other Types**: "Leave request submitted"

### **Error Messages**
- **14-Day Rule**: "Leave must be requested at least 2 weeks in advance (except for sick leave)"
- **Sick Leave Too Far Past**: "Sick leave cannot be requested more than 7 days in the past"
- **Frontend Validation**: "Leave requests must be submitted at least two weeks (14 days) in advance (except for sick leave)"

## Benefits

1. **Realistic Workflow**: Accommodates the unpredictable nature of illness
2. **Retroactive Support**: Allows faculty to request sick leave for past days when they were actually sick
3. **Flexible Policy**: Maintains strict advance notice for planned leaves while allowing emergency sick leave
4. **Clear Communication**: Users understand the different rules for different leave types
5. **Administrative Clarity**: Administrators can easily identify sick leave requests

## Future Enhancements

### **Potential Improvements**
1. **Medical Documentation**: Require doctor's note for extended sick leave
2. **Sick Leave Balance**: Track remaining sick leave days
3. **Automatic Approval**: Auto-approve single-day sick leave requests
4. **Notification System**: Notify administrators of sick leave requests immediately
5. **Reporting**: Generate reports on sick leave patterns and trends

### **Integration Opportunities**
1. **Attendance Integration**: Automatically mark sick days as excused absence
2. **Schedule Integration**: Handle class coverage for sick faculty
3. **Email Notifications**: Send notifications to relevant parties
4. **Mobile App**: Allow sick leave requests via mobile device

## Testing

The implementation has been tested with various scenarios:
- ✅ Same day sick leave requests
- ✅ Retroactive sick leave requests (within 7 days)
- ❌ Sick leave requests more than 7 days in the past
- ❌ Other leave types without 14 days notice
- ✅ Other leave types with 14+ days notice

## Support

For technical support or questions about the sick leave exception feature, refer to the system administrator or check the application logs for detailed error information.
